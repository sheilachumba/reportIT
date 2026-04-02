<?php

declare(strict_types=1);

$root = realpath(__DIR__ . '/..');
if (!is_string($root) || $root === '') {
    fwrite(STDERR, "Failed to resolve project root\n");
    exit(1);
}

$config = require $root . '/config/config.php';
if (!is_array($config)) {
    fwrite(STDERR, "Invalid config\n");
    exit(1);
}

date_default_timezone_set((string)($config['app']['timezone'] ?? 'UTC'));

require $root . '/app/Core/Database.php';
require $root . '/app/Core/Mailer.php';

use App\Core\Database;
use App\Core\Mailer;
use PDO;

function argValue(string $name, ?string $default = null): ?string
{
    global $argv;
    $prefix1 = '--' . $name . '=';
    foreach ($argv as $a) {
        if (!is_string($a)) continue;
        if (strncmp($a, $prefix1, strlen($prefix1)) === 0) {
            return substr($a, strlen($prefix1));
        }
    }
    return $default;
}

function isValidPeriod(string $p): bool
{
    return in_array($p, ['weekly', 'biweekly', 'custom'], true);
}

function baseUrl(array $config): string
{
    $b = trim((string)($config['app']['base_url'] ?? ''));
    if ($b !== '') {
        return $b;
    }
    return 'http://localhost:8000';
}

function ensureDir(string $dir): void
{
    if (!is_dir($dir)) {
        @mkdir($dir, 0777, true);
    }
}

function logLine(string $path, string $line): void
{
    $dir = dirname($path);
    ensureDir($dir);
    @file_put_contents($path, $line . "\n", FILE_APPEND);
}

$reportCfg = $config['reports']['management'] ?? [];
if (!is_array($reportCfg)) {
    $reportCfg = [];
}

$enabled = (bool)($reportCfg['enabled'] ?? true);
if (!$enabled) {
    exit(0);
}

$period = trim((string)(argValue('period', null) ?? (string)($reportCfg['default_period'] ?? 'weekly')));
if (!isValidPeriod($period)) {
    $period = 'weekly';
}

$startRaw = trim((string)(argValue('start', '')));
$endRaw = trim((string)(argValue('end', '')));

$now = new DateTimeImmutable('now');
$endDt = $now;
$startDt = $now->modify('-7 days');
if ($period === 'biweekly') {
    $startDt = $now->modify('-14 days');
}
if ($period === 'custom') {
    try {
        if ($startRaw !== '') {
            $startDt = new DateTimeImmutable($startRaw . ' 00:00:00');
        }
        if ($endRaw !== '') {
            $endDt = new DateTimeImmutable($endRaw . ' 23:59:59');
        }
    } catch (Throwable $e) {
    }
}
if ($startDt > $endDt) {
    $tmp = $startDt;
    $startDt = $endDt;
    $endDt = $tmp;
}

$start = $startDt->format('Y-m-d H:i:s');
$end = $endDt->format('Y-m-d H:i:s');

$periodLabel = $period === 'biweekly' ? 'Bi-weekly' : ($period === 'custom' ? 'Custom' : 'Weekly');

$recipients = $reportCfg['recipients'] ?? [];
if (!is_array($recipients)) {
    $recipients = [];
}
$recipients = array_values(array_filter(array_map(static fn($x) => trim((string)$x), $recipients), static fn($x) => $x !== ''));

if (count($recipients) === 0) {
    fwrite(STDERR, "No report recipients configured\n");
    exit(1);
}

$db = new Database($config['db'] ?? []);
$db->migrate();
$pdo = $db->pdo();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE created_at >= :s AND created_at <= :e');
$stmt->execute([':s' => $start, ':e' => $end]);
$createdCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE resolved_at IS NOT NULL AND resolved_at >= :s AND resolved_at <= :e');
$stmt->execute([':s' => $start, ':e' => $end]);
$resolvedCount = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE created_at <= :e AND (resolved_at IS NULL OR resolved_at > :e)");
$stmt->execute([':e' => $end]);
$openAsOfEnd = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE severity = 'Critical' AND created_at <= :e AND (resolved_at IS NULL OR resolved_at > :e)");
$stmt->execute([':e' => $end]);
$criticalOpenAsOfEnd = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM tickets WHERE created_at <= :e AND (resolved_at IS NULL OR resolved_at > :e) AND created_at <= :d");
$stmt->execute([
    ':e' => $end,
    ':d' => $endDt->modify('-7 days')->format('Y-m-d H:i:s'),
]);
$aging7 = (int)$stmt->fetchColumn();

$stmt->execute([
    ':e' => $end,
    ':d' => $endDt->modify('-14 days')->format('Y-m-d H:i:s'),
]);
$aging14 = (int)$stmt->fetchColumn();

$severityCounts = ['Critical' => 0, 'High' => 0, 'Medium' => 0, 'Low' => 0];
$stmt = $pdo->prepare('SELECT severity, COUNT(*) AS c FROM tickets WHERE created_at >= :s AND created_at <= :e GROUP BY severity');
$stmt->execute([':s' => $start, ':e' => $end]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $r) {
    $k = (string)($r['severity'] ?? '');
    if (array_key_exists($k, $severityCounts)) {
        $severityCounts[$k] = (int)($r['c'] ?? 0);
    }
}

$stmt = $pdo->prepare('SELECT
        assigned_to_name,
        assigned_to_email,
        COUNT(*) AS total,
        SUM(CASE WHEN status = "Open" THEN 1 ELSE 0 END) AS open_c,
        SUM(CASE WHEN status = "In Progress" THEN 1 ELSE 0 END) AS prog_c
    FROM tickets
    WHERE assigned_to_email IS NOT NULL AND assigned_to_email != "" AND created_at <= :e AND (resolved_at IS NULL OR resolved_at > :e)
    GROUP BY assigned_to_email, assigned_to_name
    ORDER BY total DESC');
$stmt->execute([':e' => $end]);
$workload = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT
        t.ticket_number,
        t.severity,
        t.status,
        t.assigned_to_name,
        t.assigned_to_email,
        t.created_at
    FROM tickets t
    WHERE t.created_at <= :e AND (t.resolved_at IS NULL OR t.resolved_at > :e)
    ORDER BY t.created_at ASC
    LIMIT 10');
$stmt->execute([':e' => $end]);
$oldestOpen = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->prepare('SELECT
        t.ticket_number,
        t.status,
        t.assigned_to_name,
        t.assigned_to_email,
        t.created_at
    FROM tickets t
    WHERE t.severity = "Critical" AND t.created_at <= :e AND (t.resolved_at IS NULL OR t.resolved_at > :e)
    ORDER BY t.created_at ASC
    LIMIT 10');
$stmt->execute([':e' => $end]);
$criticalOpen = $stmt->fetchAll(PDO::FETCH_ASSOC);

$base = rtrim(baseUrl($config), '/');
$link = $base . '/admin/report?period=' . urlencode($period);
$logoUrl = $base . '/assets/ksg-logo.png';

$logoSrc = $logoUrl;
$inlineImages = [];
$logoPath = $root . '/public/assets/ksg-logo.png';
if (is_file($logoPath)) {
    $data = @file_get_contents($logoPath);
    if (is_string($data) && $data !== '') {
        $logoSrc = 'cid:ksg_logo';
        $inlineImages = [[
            'cid' => 'ksg_logo',
            'mime' => 'image/png',
            'filename' => 'ksg-logo.png',
            'data' => $data,
        ]];
    }
}

$subjectPrefix = trim((string)($reportCfg['subject_prefix'] ?? 'ReportIT Management Report'));
$subject = $subjectPrefix . ' — ' . $periodLabel . ' (' . substr($start, 0, 10) . ' to ' . substr($end, 0, 10) . ')';

$generatedAt = date('Y-m-d H:i:s');

$sevList = '';
foreach (['Critical','High','Medium','Low'] as $k) {
    $sevList .= '<tr>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb; font-weight:700; width:160px">' . htmlspecialchars($k, ENT_QUOTES, 'UTF-8') . '</td>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb">' . (int)($severityCounts[$k] ?? 0) . '</td>' .
        '</tr>';
}

$workRows = '';
foreach ($workload as $w) {
    if (!is_array($w)) continue;
    $name = trim((string)($w['assigned_to_name'] ?? ''));
    $email = trim((string)($w['assigned_to_email'] ?? ''));
    $label = trim($name . ($email !== '' ? (' — ' . $email) : ''));
    $workRows .= '<tr>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb">' . htmlspecialchars($label !== '' ? $label : 'Unassigned', ENT_QUOTES, 'UTF-8') . '</td>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb; text-align:right">' . (int)($w['total'] ?? 0) . '</td>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb; text-align:right">' . (int)($w['open_c'] ?? 0) . '</td>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb; text-align:right">' . (int)($w['prog_c'] ?? 0) . '</td>' .
        '</tr>';
}
if ($workRows === '') {
    $workRows = '<tr><td colspan="4" style="padding:8px 10px; border:1px solid #e5e7eb; color:#6b7280">No assigned tickets found.</td></tr>';
}

$oldRows = '';
foreach ($oldestOpen as $t) {
    if (!is_array($t)) continue;
    $tn = (string)($t['ticket_number'] ?? '');
    $sev = (string)($t['severity'] ?? '');
    $st = (string)($t['status'] ?? '');
    $assn = trim((string)($t['assigned_to_name'] ?? ''));
    $asse = trim((string)($t['assigned_to_email'] ?? ''));
    $ass = $asse !== '' ? ($assn !== '' ? ($assn . ' — ' . $asse) : $asse) : 'Unassigned';
    $rep = (string)($t['created_at'] ?? '');
    $oldRows .= '<tr>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb">' . htmlspecialchars($tn, ENT_QUOTES, 'UTF-8') . '</td>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb">' . htmlspecialchars($sev, ENT_QUOTES, 'UTF-8') . '</td>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb">' . htmlspecialchars($st, ENT_QUOTES, 'UTF-8') . '</td>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb">' . htmlspecialchars($ass, ENT_QUOTES, 'UTF-8') . '</td>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb">' . htmlspecialchars($rep, ENT_QUOTES, 'UTF-8') . '</td>' .
        '</tr>';
}
if ($oldRows === '') {
    $oldRows = '<tr><td colspan="5" style="padding:8px 10px; border:1px solid #e5e7eb; color:#6b7280">No open tickets found.</td></tr>';
}

$critRows = '';
foreach ($criticalOpen as $t) {
    if (!is_array($t)) continue;
    $tn = (string)($t['ticket_number'] ?? '');
    $st = (string)($t['status'] ?? '');
    $assn = trim((string)($t['assigned_to_name'] ?? ''));
    $asse = trim((string)($t['assigned_to_email'] ?? ''));
    $ass = $asse !== '' ? ($assn !== '' ? ($assn . ' — ' . $asse) : $asse) : 'Unassigned';
    $rep = (string)($t['created_at'] ?? '');
    $critRows .= '<tr>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb">' . htmlspecialchars($tn, ENT_QUOTES, 'UTF-8') . '</td>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb">' . htmlspecialchars($st, ENT_QUOTES, 'UTF-8') . '</td>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb">' . htmlspecialchars($ass, ENT_QUOTES, 'UTF-8') . '</td>' .
        '<td style="padding:8px 10px; border:1px solid #e5e7eb">' . htmlspecialchars($rep, ENT_QUOTES, 'UTF-8') . '</td>' .
        '</tr>';
}
if ($critRows === '') {
    $critRows = '<tr><td colspan="4" style="padding:8px 10px; border:1px solid #e5e7eb; color:#6b7280">No critical open tickets found.</td></tr>';
}

$html = '<!doctype html>' .
    '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>' .
    '<body style="margin:0; padding:0; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827">' .
    '<div style="max-width:920px; margin:0 auto; padding:24px 14px">' .
    '<div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden">' .
    '<div style="text-align:center; padding:18px 0 8px">' .
    '<img src="' . htmlspecialchars($logoSrc, ENT_QUOTES, 'UTF-8') . '" alt="KSG" style="height:56px; width:auto; display:inline-block" />' .
    '</div>' .
    '<div style="padding:0 22px 18px">' .
    '<h1 style="margin:0 0 8px; font-size:20px; line-height:1.35">' . htmlspecialchars($subjectPrefix, ENT_QUOTES, 'UTF-8') . '</h1>' .
    '<p style="margin:0 0 14px; font-size:14px; line-height:1.6; color:#374151">' .
    htmlspecialchars($periodLabel . ' report for ' . substr($start, 0, 10) . ' to ' . substr($end, 0, 10) . '.', ENT_QUOTES, 'UTF-8') .
    ' Generated: ' . htmlspecialchars($generatedAt, ENT_QUOTES, 'UTF-8') .
    '</p>' .

    '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:14px; line-height:1.5; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden; margin-bottom:14px">' .
    '<tr>' .
    '<td style="padding:10px 12px; border:1px solid #e5e7eb; font-weight:700">Tickets Created</td><td style="padding:10px 12px; border:1px solid #e5e7eb; text-align:right">' . $createdCount . '</td>' .
    '<td style="padding:10px 12px; border:1px solid #e5e7eb; font-weight:700">Tickets Resolved</td><td style="padding:10px 12px; border:1px solid #e5e7eb; text-align:right">' . $resolvedCount . '</td>' .
    '</tr>' .
    '<tr>' .
    '<td style="padding:10px 12px; border:1px solid #e5e7eb; font-weight:700">Open (as of end)</td><td style="padding:10px 12px; border:1px solid #e5e7eb; text-align:right">' . $openAsOfEnd . '</td>' .
    '<td style="padding:10px 12px; border:1px solid #e5e7eb; font-weight:700">Critical Open (as of end)</td><td style="padding:10px 12px; border:1px solid #e5e7eb; text-align:right">' . $criticalOpenAsOfEnd . '</td>' .
    '</tr>' .
    '<tr>' .
    '<td style="padding:10px 12px; border:1px solid #e5e7eb; font-weight:700">Aging Open &gt; 7 days</td><td style="padding:10px 12px; border:1px solid #e5e7eb; text-align:right">' . $aging7 . '</td>' .
    '<td style="padding:10px 12px; border:1px solid #e5e7eb; font-weight:700">Aging Open &gt; 14 days</td><td style="padding:10px 12px; border:1px solid #e5e7eb; text-align:right">' . $aging14 . '</td>' .
    '</tr>' .
    '</table>' .

    '<h2 style="margin:16px 0 8px; font-size:16px">Severity (Created in Period)</h2>' .
    '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:14px; line-height:1.5; margin-bottom:14px">' .
    $sevList .
    '</table>' .

    '<h2 style="margin:16px 0 8px; font-size:16px">Staff Workload (Open as of End)</h2>' .
    '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:14px; line-height:1.5; margin-bottom:14px">' .
    '<tr>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:left">Staff</th>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:right">Total</th>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:right">Open</th>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:right">In Progress</th>' .
    '</tr>' .
    $workRows .
    '</table>' .

    '<h2 style="margin:16px 0 8px; font-size:16px">Oldest Open Tickets (Top 10)</h2>' .
    '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:14px; line-height:1.5; margin-bottom:14px">' .
    '<tr>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:left">Ticket</th>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:left">Severity</th>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:left">Status</th>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:left">Assigned</th>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:left">Reported</th>' .
    '</tr>' .
    $oldRows .
    '</table>' .

    '<h2 style="margin:16px 0 8px; font-size:16px">Critical Open Tickets (Top 10)</h2>' .
    '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:14px; line-height:1.5; margin-bottom:14px">' .
    '<tr>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:left">Ticket</th>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:left">Status</th>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:left">Assigned</th>' .
    '<th style="padding:8px 10px; border:1px solid #e5e7eb; text-align:left">Reported</th>' .
    '</tr>' .
    $critRows .
    '</table>' .

    '<div style="text-align:center; padding:10px 0 4px">' .
    '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" style="display:inline-block; background:#0f766e; color:#ffffff; text-decoration:none; padding:11px 16px; border-radius:10px; font-weight:700; font-size:14px">Open Full Report</a>' .
    '</div>' .

    '<p style="margin:14px 0 0; font-size:12px; line-height:1.6; color:#6b7280; text-align:center">ReportIT — Kenya School of Government</p>' .
    '</div></div></div></body></html>';

$mailer = new Mailer($config);
$logPath = $root . '/storage/logs/management_report_email.log';

$allOk = true;
foreach ($recipients as $to) {
    $ok = $mailer->sendHtml($to, $subject, $html, '', $inlineImages);
    if ($ok) {
        logLine($logPath, '[' . date('Y-m-d H:i:s') . '] sent=1 to=' . $to . ' period=' . $period . ' start=' . $start . ' end=' . $end);
        continue;
    }

    $allOk = false;
    $err = trim($mailer->lastError());
    $line = '[' . date('Y-m-d H:i:s') . '] sent=0 to=' . $to . ' period=' . $period . ' start=' . $start . ' end=' . $end;
    if ($err !== '') {
        $line .= ' error=' . str_replace(["\r", "\n"], [' ', ' '], $err);
    }
    logLine($logPath, $line);
}

exit($allOk ? 0 : 1);
