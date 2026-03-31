<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Mailer;
use PDO;

final class AdminController extends Controller
{
    private function requireAdmin(): array
    {
        $u = $this->requireAuth();

        $admins = $this->config['app']['admin_emails'] ?? [];
        $email = (string)($u['email'] ?? '');
        if (!is_array($admins) || !in_array($email, $admins, true)) {
            http_response_code(403);
            echo '403 Forbidden';
            exit;
        }

        return $u;
    }

    public function dashboard(): void
    {
        $this->requireAdmin();

        $pdo = $this->db->pdo();

        $total = (int)$pdo->query('SELECT COUNT(*) FROM tickets')->fetchColumn();
        $critical = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE severity = 'Critical'")->fetchColumn();
        $pending = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status != 'Resolved'")->fetchColumn();
        $resolved = (int)$pdo->query("SELECT COUNT(*) FROM tickets WHERE status = 'Resolved'")->fetchColumn();

        $severityRows = $pdo->query("SELECT severity, COUNT(*) AS c FROM tickets GROUP BY severity")->fetchAll(PDO::FETCH_ASSOC);
        $severityCounts = [
            'Critical' => 0,
            'High' => 0,
            'Medium' => 0,
            'Low' => 0,
        ];
        foreach ($severityRows as $r) {
            $k = (string)($r['severity'] ?? '');
            if (array_key_exists($k, $severityCounts)) {
                $severityCounts[$k] = (int)($r['c'] ?? 0);
            }
        }

        $statusRows = $pdo->query("SELECT status, COUNT(*) AS c FROM tickets GROUP BY status")->fetchAll(PDO::FETCH_ASSOC);
        $statusCounts = [
            'Open' => 0,
            'In Progress' => 0,
            'Resolved' => 0,
        ];
        foreach ($statusRows as $r) {
            $k = (string)($r['status'] ?? '');
            if (array_key_exists($k, $statusCounts)) {
                $statusCounts[$k] = (int)($r['c'] ?? 0);
            }
        }

        $stmtRecent = $pdo->prepare('SELECT
                t.ticket_number,
                t.severity,
                t.status,
                t.created_at,
                c.name AS campus_name,
                b.name AS building_name,
                r.name AS room_name,
                d.device_type,
                d.label AS device_label
            FROM tickets t
            LEFT JOIN campuses c ON c.id = t.campus_id
            LEFT JOIN buildings b ON b.id = t.building_id
            LEFT JOIN rooms r ON r.id = t.room_id
            LEFT JOIN devices d ON d.id = t.device_id
            ORDER BY t.created_at DESC
            LIMIT 10');
        $stmtRecent->execute();
        $recent = $stmtRecent->fetchAll(PDO::FETCH_ASSOC);

        $this->view('admin/dashboard', [
            'pageTitle' => 'Admin Dashboard',
            'pageSubtitle' => 'Overview of IT issue tickets across all campuses',
            'stats' => [
                'total' => $total,
                'critical' => $critical,
                'pending' => $pending,
                'resolved' => $resolved,
            ],
            'severityCounts' => $severityCounts,
            'statusCounts' => $statusCounts,
            'recent' => $recent,
        ]);
    }

    public function tickets(): void
    {
        $this->requireAdmin();

        $pdo = $this->db->pdo();

        $stmt = $pdo->prepare('SELECT
                t.ticket_number,
                t.description,
                t.severity,
                t.status,
                t.created_at,
                t.reporter_name,
                t.reporter_email,
                c.name AS campus_name,
                b.name AS building_name,
                r.name AS room_name,
                d.device_type,
                d.label AS device_label
            FROM tickets t
            LEFT JOIN campuses c ON c.id = t.campus_id
            LEFT JOIN buildings b ON b.id = t.building_id
            LEFT JOIN rooms r ON r.id = t.room_id
            LEFT JOIN devices d ON d.id = t.device_id
            ORDER BY t.created_at DESC');
        $stmt->execute();
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->view('admin/tickets', [
            'pageTitle' => 'Ticket Management',
            'pageSubtitle' => 'Manage and track all IT issue tickets',
            'tickets' => $tickets,
        ]);
    }

    public function ticketView(): void
    {
        $this->requireAdmin();

        $ticketNumber = trim((string)($_GET['ticket'] ?? ''));
        if ($ticketNumber === '') {
            http_response_code(404);
            echo 'Ticket not found';
            return;
        }

        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('SELECT
                t.*,
                c.name AS campus_name,
                b.name AS building_name,
                r.name AS room_name,
                d.device_type,
                d.label AS device_label
            FROM tickets t
            LEFT JOIN campuses c ON c.id = t.campus_id
            LEFT JOIN buildings b ON b.id = t.building_id
            LEFT JOIN rooms r ON r.id = t.room_id
            LEFT JOIN devices d ON d.id = t.device_id
            WHERE t.ticket_number = :t
            LIMIT 1');
        $stmt->execute([':t' => $ticketNumber]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            http_response_code(404);
            echo 'Ticket not found';
            return;
        }

        $attachments = [];
        $ticketId = (int)($ticket['id'] ?? 0);
        if ($ticketId > 0) {
            $stmtA = $pdo->prepare('SELECT id, original_name, stored_path, mime_type, bytes, created_at FROM ticket_attachments WHERE ticket_id = :tid ORDER BY id DESC');
            $stmtA->execute([':tid' => $ticketId]);
            $attachments = $stmtA->fetchAll(PDO::FETCH_ASSOC);
        }

        $staff = $this->config['app']['it_staff'] ?? [];

        $this->view('admin/ticket_view', [
            'pageTitle' => 'Ticket Details - ' . $ticketNumber,
            'pageSubtitle' => 'Ticket details and reporter information',
            'ticket' => $ticket,
            'attachments' => $attachments,
            'staff' => is_array($staff) ? $staff : [],
        ]);
    }

    public function ticketUpdate(): void
    {
        $this->requireAdmin();

        $ticketNumber = trim((string)($_POST['ticket'] ?? ''));
        $action = trim((string)($_POST['action'] ?? ''));

        if ($ticketNumber === '' || $action === '') {
            $this->redirect('/admin/tickets');
        }

        $pdo = $this->db->pdo();
        $now = date('Y-m-d H:i:s');

        if ($action === 'assign') {
            $email = trim((string)($_POST['staff_email'] ?? ''));
            $staff = $this->config['app']['it_staff'] ?? [];

            $name = '';
            if (is_array($staff)) {
                foreach ($staff as $s) {
                    if (!is_array($s)) continue;
                    $se = (string)($s['email'] ?? '');
                    if ($se !== '' && strcasecmp($se, $email) === 0) {
                        $name = (string)($s['name'] ?? '');
                        $email = $se;
                        break;
                    }
                }
            }

            if ($email !== '') {
                $stmt = $pdo->prepare('UPDATE tickets SET assigned_to_name = :n, assigned_to_email = :e, updated_at = :u WHERE ticket_number = :t');
                $stmt->execute([':n' => $name !== '' ? $name : null, ':e' => $email, ':u' => $now, ':t' => $ticketNumber]);

                $mailer = new Mailer($this->config);
                $ok = $this->notifyAssignedTicket($ticketNumber, $email, $mailer);
                if ($ok) {
                    $this->flash('notice', 'Ticket assigned to ' . $email . ' and email notification sent.');
                } else {
                    $err = trim($mailer->lastError());
                    $this->flash('error', 'Ticket assigned to ' . $email . ' but email failed to send.' . ($err !== '' ? (' Error: ' . $err) : '')); 
                }
            } else {
                $this->flash('error', 'Please select an IT staff member to assign this ticket.');
            }

            $this->redirect('/admin/tickets/view?ticket=' . urlencode($ticketNumber));
        }

        if ($action === 'mark_in_progress') {
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'In Progress', updated_at = :u WHERE ticket_number = :t");
            $stmt->execute([':u' => $now, ':t' => $ticketNumber]);
            $this->redirect('/admin/tickets/view?ticket=' . urlencode($ticketNumber));
        }

        if ($action === 'mark_resolved') {
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'Resolved', resolved_at = :r, updated_at = :u WHERE ticket_number = :t");
            $stmt->execute([':r' => $now, ':u' => $now, ':t' => $ticketNumber]);
            $this->redirect('/admin/tickets/view?ticket=' . urlencode($ticketNumber));
        }

        if ($action === 'reopen') {
            $stmt = $pdo->prepare("UPDATE tickets SET status = 'Open', resolved_at = NULL, updated_at = :u WHERE ticket_number = :t");
            $stmt->execute([':u' => $now, ':t' => $ticketNumber]);
            $this->redirect('/admin/tickets/view?ticket=' . urlencode($ticketNumber));
        }

        $this->redirect('/admin/tickets/view?ticket=' . urlencode($ticketNumber));
    }

    private function notifyAssignedTicket(string $ticketNumber, string $assigneeEmail, Mailer $mailer): bool
    {
        $assigneeEmail = trim($assigneeEmail);
        if ($ticketNumber === '' || $assigneeEmail === '') {
            return false;
        }

        $from = (string)($this->config['notifications']['from'] ?? 'no-reply@localhost');

        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('SELECT
                t.ticket_number,
                t.severity,
                t.status,
                t.created_at,
                t.reporter_name,
                t.reporter_email,
                t.description,
                c.name AS campus_name,
                b.name AS building_name,
                r.name AS room_name,
                d.device_type,
                d.label AS device_label
            FROM tickets t
            LEFT JOIN campuses c ON c.id = t.campus_id
            LEFT JOIN buildings b ON b.id = t.building_id
            LEFT JOIN rooms r ON r.id = t.room_id
            LEFT JOIN devices d ON d.id = t.device_id
            WHERE t.ticket_number = :t
            LIMIT 1');
        $stmt->execute([':t' => $ticketNumber]);
        $t = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$t) {
            return false;
        }

        $baseUrl = (string)($this->config['app']['base_url'] ?? '');
        if ($baseUrl === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost:8000');
            $baseUrl = $scheme . '://' . $host;
        }

        $link = rtrim($baseUrl, '/') . '/admin/tickets/view?ticket=' . urlencode($ticketNumber);
        $logoUrl = rtrim($baseUrl, '/') . '/assets/ksg-logo.png';
        $logoSrc = $logoUrl;
        $inlineImages = [];

        $logoPath = __DIR__ . '/../../public/assets/ksg-logo.png';
        if (is_file($logoPath)) {
            $data = @file_get_contents($logoPath);
            if (is_string($data) && $data !== '') {
                $logoSrc = 'cid:ksg_logo';
                $inlineImages = [
                    [
                        'cid' => 'ksg_logo',
                        'mime' => 'image/png',
                        'filename' => 'ksg-logo.png',
                        'data' => $data,
                    ]
                ];
            }
        }

        $subject = 'Ticket Assigned: ' . $ticketNumber;
        $campus = (string)($t['campus_name'] ?? '');
        $building = (string)($t['building_name'] ?? '');
        $room = (string)($t['room_name'] ?? '');
        $deviceType = (string)($t['device_type'] ?? '');
        $deviceLabel = (string)($t['device_label'] ?? '');
        $severity = (string)($t['severity'] ?? '');
        $status = (string)($t['status'] ?? '');
        $createdAt = (string)($t['created_at'] ?? '');
        $reporterName = (string)($t['reporter_name'] ?? '');
        $reporterEmail = (string)($t['reporter_email'] ?? '');
        $desc = (string)($t['description'] ?? '');

        $title = 'New Ticket Assigned: ' . $ticketNumber;
        $intro = 'You have been assigned a new IT issue ticket. Please review and begin resolution.';
        $rows = [
            'Ticket' => $ticketNumber,
            'Severity' => $severity,
            'Status' => $status,
            'Reported' => $createdAt,
            'Location' => trim($campus . ($building !== '' ? ' — ' . $building : '') . ($room !== '' ? ' — ' . $room : '')),
            'Device' => trim($deviceType . (($deviceLabel !== '' && $deviceLabel !== $deviceType) ? ' — ' . $deviceLabel : '')),
            'Reporter' => trim($reporterName . ($reporterEmail !== '' ? ' (' . $reporterEmail . ')' : '')),
            'Description' => $desc,
        ];

        $html = Mailer::renderBrandedEmail($title, $intro, $rows, $link, 'View Ticket', $logoSrc);
        $ok = $mailer->sendHtml($assigneeEmail, $subject, $html, '', $inlineImages);
        if ($ok) {
            return true;
        }

        $logDir = __DIR__ . '/../../storage/logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $line = '[' . date('Y-m-d H:i:s') . '] ASSIGN notify (email not sent) to ' . $assigneeEmail . ' for ' . $ticketNumber . "\n";
        @file_put_contents($logDir . '/notifications.log', $line, FILE_APPEND);
        return false;
    }

    public function attachment(): void
    {
        $this->requireAdmin();

        $attachmentId = (int)($_GET['id'] ?? 0);
        $ticketNumber = trim((string)($_GET['ticket'] ?? ''));
        if ($attachmentId <= 0 || $ticketNumber === '') {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('SELECT
                a.original_name,
                a.stored_path,
                a.mime_type,
                a.bytes
            FROM ticket_attachments a
            JOIN tickets t ON t.id = a.ticket_id
            WHERE a.id = :id AND t.ticket_number = :t
            LIMIT 1');
        $stmt->execute([':id' => $attachmentId, ':t' => $ticketNumber]);
        $a = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$a) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $base = (string)($this->config['uploads']['base_path'] ?? '');
        $storedPath = (string)($a['stored_path'] ?? '');
        if ($base === '' || $storedPath === '') {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $full = rtrim($base, '/\\') . DIRECTORY_SEPARATOR . str_replace(['..', '/', '\\'], ['', DIRECTORY_SEPARATOR, DIRECTORY_SEPARATOR], $storedPath);
        if (!is_file($full)) {
            http_response_code(404);
            echo 'File not found';
            return;
        }

        $mime = (string)($a['mime_type'] ?? 'application/octet-stream');
        $name = (string)($a['original_name'] ?? 'attachment');
        $bytes = (int)($a['bytes'] ?? filesize($full));

        http_response_code(200);
        header('Content-Type: ' . ($mime !== '' ? $mime : 'application/octet-stream'));
        header('Content-Length: ' . $bytes);
        header('Content-Disposition: inline; filename="' . str_replace('"', '', $name) . '"');
        readfile($full);
        exit;
    }
}
