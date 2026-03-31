<?php

declare(strict_types=1);

namespace App\Core;

final class Mailer
{
    private string $lastError = '';
    private string $lastResponse = '';

    public function __construct(private array $config)
    {
    }

    public function lastError(): string
    {
        return $this->lastError;
    }

    public function lastResponse(): string
    {
        return $this->lastResponse;
    }

    public function send(string $to, string $subject, string $body): bool
    {
        return $this->sendWithContentType($to, $subject, $body, 'text/plain; charset=UTF-8');
    }

    public function sendHtml(string $to, string $subject, string $html, string $textAlt = '', array $inlineImages = []): bool
    {
        $boundaryAlt = $this->makeBoundary();
        $useRelated = is_array($inlineImages) && count($inlineImages) > 0;

        $boundaryRelated = '';
        $contentType = 'multipart/alternative; boundary="' . $boundaryAlt . '"';
        if ($useRelated) {
            $boundaryRelated = $this->makeBoundary();
            $contentType = 'multipart/related; boundary="' . $boundaryRelated . '"';
        }

        $textAlt = trim($textAlt);
        if ($textAlt === '') {
            $textAlt = $this->htmlToText($html);
        }

        $altPart = '--' . $boundaryAlt . "\r\n" .
            'Content-Type: text/plain; charset=UTF-8' . "\r\n" .
            'Content-Transfer-Encoding: 8bit' . "\r\n\r\n" .
            $textAlt . "\r\n" .
            '--' . $boundaryAlt . "\r\n" .
            'Content-Type: text/html; charset=UTF-8' . "\r\n" .
            'Content-Transfer-Encoding: 8bit' . "\r\n\r\n" .
            $html . "\r\n" .
            '--' . $boundaryAlt . "--\r\n";

        if (!$useRelated) {
            $body = $altPart;
            return $this->sendWithContentType($to, $subject, $body, $contentType);
        }

        $body = '--' . $boundaryRelated . "\r\n" .
            'Content-Type: multipart/alternative; boundary="' . $boundaryAlt . '"' . "\r\n\r\n" .
            $altPart;

        foreach ($inlineImages as $img) {
            if (!is_array($img)) {
                continue;
            }
            $cid = trim((string)($img['cid'] ?? ''));
            $mime = trim((string)($img['mime'] ?? 'application/octet-stream'));
            $filename = trim((string)($img['filename'] ?? 'inline'));
            $data = $img['data'] ?? '';
            if ($cid === '' || !is_string($data) || $data === '') {
                continue;
            }

            $b64 = chunk_split(base64_encode($data), 76, "\r\n");
            $body .= '--' . $boundaryRelated . "\r\n" .
                'Content-Type: ' . $mime . '; name="' . str_replace('"', '', $filename) . '"' . "\r\n" .
                'Content-Transfer-Encoding: base64' . "\r\n" .
                'Content-ID: <' . str_replace(['<', '>'], '', $cid) . '>' . "\r\n" .
                'Content-Disposition: inline; filename="' . str_replace('"', '', $filename) . '"' . "\r\n\r\n" .
                $b64 . "\r\n";
        }

        $body .= '--' . $boundaryRelated . "--\r\n";

        return $this->sendWithContentType($to, $subject, $body, $contentType);
    }

    public static function renderBrandedEmail(string $title, string $intro, array $rows, string $ctaUrl, string $ctaLabel, string $logoUrl = ''): string
    {
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeIntro = htmlspecialchars($intro, ENT_QUOTES, 'UTF-8');
        $safeCtaUrl = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
        $safeCtaLabel = htmlspecialchars($ctaLabel, ENT_QUOTES, 'UTF-8');
        $safeLogoUrl = trim($logoUrl) !== '' ? htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') : '';

        $rowsHtml = '';
        foreach ($rows as $k => $v) {
            if (!is_string($k)) {
                continue;
            }
            $label = htmlspecialchars(trim($k), ENT_QUOTES, 'UTF-8');
            $value = '';
            if (is_string($v) || is_numeric($v)) {
                $value = (string)$v;
            }
            $value = trim($value);
            $valueHtml = $value !== ''
                ? nl2br(htmlspecialchars($value, ENT_QUOTES, 'UTF-8'))
                : '<span style="color:#6b7280">—</span>';

            $rowsHtml .= '<tr>' .
                '<td style="padding:10px 12px; border-top:1px solid #e5e7eb; width:160px; color:#374151; font-weight:600; vertical-align:top">' . $label . '</td>' .
                '<td style="padding:10px 12px; border-top:1px solid #e5e7eb; color:#111827; vertical-align:top">' . $valueHtml . '</td>' .
                '</tr>';
        }

        $logoBlock = $safeLogoUrl !== ''
            ? '<div style="text-align:center; padding:22px 0 10px">' .
                '<img src="' . $safeLogoUrl . '" alt="KSG" style="height:56px; width:auto; display:inline-block" />' .
              '</div>'
            : '';

        return '<!doctype html>' .
            '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"></head>' .
            '<body style="margin:0; padding:0; background:#f3f4f6; font-family:Arial, Helvetica, sans-serif; color:#111827">' .
            '<div style="max-width:640px; margin:0 auto; padding:24px 14px">' .
            '<div style="background:#ffffff; border:1px solid #e5e7eb; border-radius:14px; overflow:hidden">' .
            $logoBlock .
            '<div style="padding:0 22px 18px">' .
            '<h1 style="margin:0 0 8px; font-size:20px; line-height:1.35; color:#111827">' . $safeTitle . '</h1>' .
            '<p style="margin:0 0 16px; font-size:14px; line-height:1.6; color:#374151">' . $safeIntro . '</p>' .
            '<table role="presentation" cellpadding="0" cellspacing="0" style="width:100%; border-collapse:collapse; font-size:14px; line-height:1.5; border:1px solid #e5e7eb; border-radius:12px; overflow:hidden">' .
            $rowsHtml .
            '</table>' .
            '<div style="text-align:center; padding:18px 0 4px">' .
            '<a href="' . $safeCtaUrl . '" style="display:inline-block; background:#0f766e; color:#ffffff; text-decoration:none; padding:11px 16px; border-radius:10px; font-weight:700; font-size:14px">' . $safeCtaLabel . '</a>' .
            '</div>' .
            '<p style="margin:14px 0 0; font-size:12px; line-height:1.6; color:#6b7280; text-align:center">ReportIT — Kenya School of Government</p>' .
            '</div>' .
            '</div>' .
            '</div>' .
            '</body></html>';
    }

    private function sendWithContentType(string $to, string $subject, string $body, string $contentType): bool
    {
        $to = trim($to);
        if ($to === '') {
            return false;
        }

        $smtp = $this->config['smtp'] ?? [];
        if (is_array($smtp)) {
            $enabled = (bool)($smtp['enabled'] ?? false);
            if ($enabled) {
                if ($this->sendSmtp($to, $subject, $body, $smtp, $contentType)) {
                    return true;
                }
            } else {
                $u = trim((string)($smtp['username'] ?? ''));
                $p = trim((string)($smtp['password'] ?? ''));
                $f = trim((string)($smtp['from_email'] ?? ''));
                if ($u === '' || $p === '' || $f === '') {
                    $this->lastError = 'SMTP is not configured in this server process (missing username/password/from_email).';
                    $this->logFailure('disabled');
                }
            }
        }

        $fromEmail = (string)($this->config['notifications']['from'] ?? 'no-reply@localhost');
        $fromName = (string)($this->config['notifications']['from_name'] ?? '');

        $headers = $this->buildHeaders($fromEmail, $fromName, $contentType);
        $ok = @mail($to, $subject, $body, $headers);
        if ($ok) {
            return true;
        }

        if (trim($this->lastError) === '') {
            $this->lastError = 'mail() failed and SMTP was not used.';
        }

        return false;
    }

    private function sendSmtp(string $to, string $subject, string $body, array $smtp, string $contentType): bool
    {
        $this->lastError = '';
        $this->lastResponse = '';

        $host = (string)($smtp['host'] ?? '');
        $port = (int)($smtp['port'] ?? 587);
        $encryption = (string)($smtp['encryption'] ?? 'tls');
        $username = (string)($smtp['username'] ?? '');
        $password = (string)($smtp['password'] ?? '');
        $fromEmail = (string)($smtp['from_email'] ?? $username);
        $fromName = (string)($smtp['from_name'] ?? '');

        if ($host === '' || $username === '' || $password === '' || $fromEmail === '') {
            $this->lastError = 'Missing SMTP configuration (host/username/password/from_email).';
            $this->logFailure('config');
            return false;
        }

        $transport = $encryption === 'ssl' ? 'ssl://' : '';
        $fp = @fsockopen($transport . $host, $port, $errno, $errstr, 12);
        if (!$fp) {
            $this->lastError = 'Connection failed: ' . $errno . ' ' . $errstr;
            $this->logFailure('connect');
            return false;
        }

        stream_set_timeout($fp, 12);

        if (!$this->expect($fp, 220)) {
            $this->lastError = 'SMTP greeting not received.';
            $this->logFailure('greeting');
            fclose($fp);
            return false;
        }

        $ehloHost = (string)($smtp['ehlo_host'] ?? 'localhost');
        if (!$this->cmd($fp, 'EHLO ' . $ehloHost, 250)) {
            $this->lastError = 'EHLO failed.';
            $this->logFailure('ehlo');
            fclose($fp);
            return false;
        }

        if ($encryption === 'tls') {
            if (!$this->cmd($fp, 'STARTTLS', 220)) {
                $this->lastError = 'STARTTLS failed.';
                $this->logFailure('starttls');
                fclose($fp);
                return false;
            }

            $cryptoOk = @stream_socket_enable_crypto($fp, true, $this->tlsCryptoMethod());
            if ($cryptoOk !== true) {
                $this->lastError = 'TLS negotiation failed.';
                $this->logFailure('tls');
                fclose($fp);
                return false;
            }

            if (!$this->cmd($fp, 'EHLO ' . $ehloHost, 250)) {
                $this->lastError = 'EHLO after STARTTLS failed.';
                $this->logFailure('ehlo2');
                fclose($fp);
                return false;
            }
        }

        if (!$this->cmd($fp, 'AUTH LOGIN', 334)) {
            $this->lastError = 'AUTH LOGIN failed.';
            $this->logFailure('auth_login');
            fclose($fp);
            return false;
        }

        if (!$this->cmd($fp, base64_encode($username), 334)) {
            $this->lastError = 'SMTP username rejected.';
            $this->logFailure('auth_user');
            fclose($fp);
            return false;
        }

        if (!$this->cmd($fp, base64_encode($password), 235)) {
            $this->lastError = 'SMTP password rejected.';
            $this->logFailure('auth_pass');
            fclose($fp);
            return false;
        }

        if (!$this->cmd($fp, 'MAIL FROM:<' . $fromEmail . '>', 250)) {
            $this->lastError = 'MAIL FROM rejected.';
            $this->logFailure('mail_from');
            fclose($fp);
            return false;
        }

        if (!$this->cmd($fp, 'RCPT TO:<' . $to . '>', 250)) {
            $this->lastError = 'RCPT TO rejected.';
            $this->logFailure('rcpt_to');
            fclose($fp);
            return false;
        }

        if (!$this->cmd($fp, 'DATA', 354)) {
            $this->lastError = 'DATA rejected.';
            $this->logFailure('data');
            fclose($fp);
            return false;
        }

        $headers = $this->buildHeaders($fromEmail, $fromName, $contentType);
        $msg = 'Subject: ' . $this->encodeHeader($subject) . "\r\n" . $headers . "\r\n" . $this->normalizeBody($body) . "\r\n";

        fwrite($fp, $this->dotStuff($msg) . "\r\n.\r\n");
        if (!$this->expect($fp, 250)) {
            $this->lastError = 'Message not accepted.';
            $this->logFailure('data_end');
            fclose($fp);
            return false;
        }

        $this->cmd($fp, 'QUIT', 221);
        fclose($fp);
        return true;
    }

    private function tlsCryptoMethod(): int
    {
        $m = 0;
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT')) {
            $m |= STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT')) {
            $m |= STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT;
        }
        if (defined('STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT')) {
            $m |= STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT;
        }
        if ($m !== 0) {
            return $m;
        }
        return STREAM_CRYPTO_METHOD_TLS_CLIENT;
    }

    private function buildHeaders(string $fromEmail, string $fromName, string $contentType): string
    {
        $fromEmail = trim($fromEmail);
        $fromName = trim($fromName);
        $contentType = trim($contentType);
        if ($contentType === '') {
            $contentType = 'text/plain; charset=UTF-8';
        }
        $from = $fromEmail;
        if ($fromName !== '') {
            $from = $this->encodeHeader($fromName) . ' <' . $fromEmail . '>';
        }

        return 'From: ' . $from . "\r\n" .
            'MIME-Version: 1.0' . "\r\n" .
            'Content-Type: ' . $contentType . "\r\n";
    }

    private function makeBoundary(): string
    {
        try {
            if (function_exists('random_bytes')) {
                return 'b1_' . bin2hex(random_bytes(12));
            }
        } catch (\Throwable $e) {
        }
        return 'b1_' . bin2hex((string)microtime(true));
    }

    private function htmlToText(string $html): string
    {
        $t = preg_replace('/<\s*br\s*\/?>/i', "\n", $html) ?? $html;
        $t = preg_replace('/<\s*\/p\s*>/i', "\n\n", $t) ?? $t;
        $t = strip_tags($t);
        $t = html_entity_decode($t, ENT_QUOTES, 'UTF-8');
        $t = str_replace(["\r\n", "\r"], "\n", $t);
        $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;
        return trim((string)$t) . "\n";
    }

    private function encodeHeader(string $v): string
    {
        $v = trim($v);
        if ($v === '') {
            return '';
        }
        return '=?UTF-8?B?' . base64_encode($v) . '?=';
    }

    private function normalizeBody(string $body): string
    {
        $body = str_replace(["\r\n", "\r"], "\n", $body);
        $body = str_replace("\n", "\r\n", $body);
        return $body;
    }

    private function dotStuff(string $data): string
    {
        return preg_replace('/\r\n\./', "\r\n..", $data) ?? $data;
    }

    private function cmd($fp, string $cmd, int $expectCode): bool
    {
        fwrite($fp, $cmd . "\r\n");
        return $this->expect($fp, $expectCode);
    }

    private function expect($fp, int $code): bool
    {
        $line = '';
        $ok = false;
        $resp = '';

        for ($i = 0; $i < 30; $i++) {
            $line = fgets($fp, 515);
            if ($line === false) {
                break;
            }
            $line = rtrim($line, "\r\n");
            $resp .= $line . "\n";
            if (strlen($line) >= 3 && (int)substr($line, 0, 3) === $code) {
                $ok = true;
            }
            if (strlen($line) >= 4 && $line[3] === ' ') {
                break;
            }
        }

        $this->lastResponse = trim($resp);
        if (!$ok && $this->lastError === '') {
            $this->lastError = 'Expected SMTP code ' . $code . ' but got: ' . ($this->lastResponse !== '' ? $this->lastResponse : 'no response');
        }

        return $ok;
    }

    private function logFailure(string $stage): void
    {
        $dir = __DIR__ . '/../../storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $safeErr = str_replace(["\r", "\n"], [' ', ' '], $this->lastError);
        $safeResp = str_replace(["\r"], [''], $this->lastResponse);
        $line = '[' . date('Y-m-d H:i:s') . '] stage=' . $stage . ' error=' . $safeErr;
        if ($safeResp !== '') {
            $line .= ' response=' . str_replace(["\n"], [' | '], $safeResp);
        }
        $line .= "\n";
        @file_put_contents($dir . '/smtp.log', $line, FILE_APPEND);
    }
}
