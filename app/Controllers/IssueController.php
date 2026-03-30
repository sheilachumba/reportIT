<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PDO;

final class IssueController extends Controller
{
    public function create(): void
    {
        $u = $this->requireAuth();
        $this->view('issue/create', [
            'pageTitle' => 'Log an IT Issue',
            'pageSubtitle' => 'Choose the exact campus, room, and device. Submit the issue and receive a ticket number instantly.',
            'errors' => [],
            'old' => [
                'reporter_name' => (string)($u['name'] ?? ''),
                'reporter_email' => (string)($u['email'] ?? ''),
                'campus_id' => '',
                'building_id' => '',
                'room_id' => '',
                'device_id' => '',
                'device_condition' => 'Good',
                'severity' => 'Medium',
                'description' => '',
            ],
        ]);
    }

    public function store(): void
    {
        $u = $this->requireAuth();
        $post = $_POST;

        $old = [
            'reporter_name' => trim((string)($post['reporter_name'] ?? (string)($u['name'] ?? ''))),
            'reporter_email' => (string)($u['email'] ?? ''),
            'campus_id' => (string)($post['campus_id'] ?? ''),
            'building_id' => (string)($post['building_id'] ?? ''),
            'room_id' => (string)($post['room_id'] ?? ''),
            'device_id' => (string)($post['device_id'] ?? ''),
            'device_condition' => (string)($post['device_condition'] ?? ''),
            'severity' => (string)($post['severity'] ?? ''),
            'description' => trim((string)($post['description'] ?? '')),
        ];

        $errors = [];

        $campusId = (int) $old['campus_id'];
        $buildingId = (int) $old['building_id'];
        $roomId = (int) $old['room_id'];
        $deviceId = (int) $old['device_id'];

        if ($campusId <= 0) $errors['campus_id'] = 'Please select a campus.';
        if ($buildingId <= 0) $errors['building_id'] = 'Please select a building.';
        if ($roomId <= 0) $errors['room_id'] = 'Please select a room.';
        if ($deviceId <= 0) $errors['device_id'] = 'Please select a device.';

        $allowedCondition = ['Excellent', 'Good', 'Fair', 'Poor'];
        $allowedSeverity = ['Critical', 'High', 'Medium', 'Low'];

        if (!in_array($old['device_condition'], $allowedCondition, true)) {
            $errors['device_condition'] = 'Invalid device condition.';
        }

        if (!in_array($old['severity'], $allowedSeverity, true)) {
            $errors['severity'] = 'Invalid severity.';
        }

        $descLen = function_exists('mb_strlen') ? mb_strlen($old['description']) : strlen($old['description']);
        if ($old['description'] === '' || $descLen < 10) {
            $errors['description'] = 'Please describe the issue (at least 10 characters).';
        }

        if ($old['reporter_email'] !== '' && !filter_var($old['reporter_email'], FILTER_VALIDATE_EMAIL)) {
            $errors['reporter_email'] = 'Please enter a valid email address.';
        }

        if (empty($errors)) {
            $this->validateHierarchy($campusId, $buildingId, $roomId, $deviceId, $errors);
        }

        if (!empty($errors)) {
            $this->view('issue/create', [
                'pageTitle' => 'Log an IT Issue',
                'pageSubtitle' => 'Choose the exact campus, room, and device. Submit the issue and receive a ticket number instantly.',
                'errors' => $errors,
                'old' => $old,
            ]);
            return;
        }

        $pdo = $this->db->pdo();
        $pdo->beginTransaction();

        $ticketNumber = $this->generateTicketNumber($pdo);
        $createdAt = gmdate('Y-m-d H:i:s');

        $stmt = $pdo->prepare('INSERT INTO tickets (
            ticket_number, reporter_name, reporter_email,
            campus_id, building_id, room_id, device_id,
            device_condition, severity, description, status, created_at
        ) VALUES (
            :ticket_number, :reporter_name, :reporter_email,
            :campus_id, :building_id, :room_id, :device_id,
            :device_condition, :severity, :description, :status, :created_at
        )');

        $stmt->execute([
            ':ticket_number' => $ticketNumber,
            ':reporter_name' => $old['reporter_name'] !== '' ? $old['reporter_name'] : null,
            ':reporter_email' => $old['reporter_email'] !== '' ? $old['reporter_email'] : null,
            ':campus_id' => $campusId,
            ':building_id' => $buildingId,
            ':room_id' => $roomId,
            ':device_id' => $deviceId,
            ':device_condition' => $old['device_condition'],
            ':severity' => $old['severity'],
            ':description' => $old['description'],
            ':status' => 'Open',
            ':created_at' => $createdAt,
        ]);

        $ticketId = (int) $pdo->lastInsertId();

        $this->handleUpload($ticketId, $ticketNumber);

        $pdo->commit();

        if ((string)$old['severity'] === 'Critical') {
            $this->notifyCriticalTicket($ticketNumber, $campusId, $buildingId, $roomId, $deviceId, (string)$old['description']);
        }

        $this->redirect('/issue/confirm?ticket=' . urlencode($ticketNumber));
    }

    public function confirm(): void
    {
        $this->requireAuth();
        $ticketNumber = (string) ($_GET['ticket'] ?? '');
        $ticketNumber = trim($ticketNumber);

        if ($ticketNumber === '') {
            http_response_code(404);
            echo 'Ticket not found';
            return;
        }

        $stmt = $this->db->pdo()->prepare('SELECT * FROM tickets WHERE ticket_number = :t LIMIT 1');
        $stmt->execute([':t' => $ticketNumber]);
        $ticket = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ticket) {
            http_response_code(404);
            echo 'Ticket not found';
            return;
        }

        $this->view('issue/confirm', [
            'pageTitle' => 'Confirmation',
            'pageSubtitle' => 'Your issue has been logged successfully.',
            'ticket' => $ticket,
        ]);
    }

    private function generateTicketNumber(PDO $pdo): string
    {
        $date = gmdate('Ymd');

        for ($i = 0; $i < 8; $i++) {
            $rand = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $ticket = 'RPT-' . $date . '-' . $rand;

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM tickets WHERE ticket_number = :t');
            $stmt->execute([':t' => $ticket]);
            if ((int) $stmt->fetchColumn() === 0) {
                return $ticket;
            }
        }

        throw new \RuntimeException('Failed to generate ticket number.');
    }

    private function handleUpload(int $ticketId, string $ticketNumber): void
    {
        if (!isset($_FILES['attachment']) || !is_array($_FILES['attachment'])) {
            return;
        }

        $file = $_FILES['attachment'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            return;
        }

        $config = $this->config['uploads'] ?? [];
        $maxBytes = (int) ($config['max_bytes'] ?? (10 * 1024 * 1024));
        $allowed = $config['allowed_mime'] ?? [];
        $basePath = (string) ($config['base_path'] ?? '');

        if ($basePath === '') {
            return;
        }

        $size = (int) ($file['size'] ?? 0);
        if ($size <= 0 || $size > $maxBytes) {
            return;
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return;
        }

        $mime = '';
        if (function_exists('mime_content_type')) {
            $mime = (string) mime_content_type($tmp);
        }
        if ($mime === '') {
            $mime = (string) ($file['type'] ?? '');
        }
        if (!in_array($mime, $allowed, true)) {
            return;
        }

        $original = (string) ($file['name'] ?? 'attachment');
        $original = trim($original);
        if ($original === '') {
            $original = 'attachment';
        }

        $safeName = preg_replace('/[^a-zA-Z0-9._-]+/', '_', $original) ?? 'attachment';
        $ticketDir = rtrim($basePath, '/\\') . DIRECTORY_SEPARATOR . $ticketNumber;
        if (!is_dir($ticketDir)) {
            mkdir($ticketDir, 0777, true);
        }

        $stored = $ticketDir . DIRECTORY_SEPARATOR . $safeName;
        $n = 1;
        while (file_exists($stored)) {
            $stored = $ticketDir . DIRECTORY_SEPARATOR . $n . '_' . $safeName;
            $n++;
        }

        if (!move_uploaded_file($tmp, $stored)) {
            return;
        }

        $relPath = str_replace('\\', '/', $ticketNumber . '/' . basename($stored));
        $stmt = $this->db->pdo()->prepare('INSERT INTO ticket_attachments (ticket_id, original_name, stored_path, mime_type, bytes, created_at)
            VALUES (:ticket_id, :original_name, :stored_path, :mime_type, :bytes, :created_at)');
        $stmt->execute([
            ':ticket_id' => $ticketId,
            ':original_name' => $original,
            ':stored_path' => $relPath,
            ':mime_type' => $mime,
            ':bytes' => $size,
            ':created_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    private function validateHierarchy(int $campusId, int $buildingId, int $roomId, int $deviceId, array &$errors): void
    {
        $pdo = $this->db->pdo();

        $stmt = $pdo->prepare('SELECT 1 FROM campuses WHERE id = :id');
        $stmt->execute([':id' => $campusId]);
        if (!$stmt->fetchColumn()) {
            $errors['campus_id'] = 'Selected campus was not found.';
            return;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM buildings WHERE id = :id AND campus_id = :campus_id');
        $stmt->execute([':id' => $buildingId, ':campus_id' => $campusId]);
        if (!$stmt->fetchColumn()) {
            $errors['building_id'] = 'Selected building does not belong to the selected campus.';
            return;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM rooms WHERE id = :id AND building_id = :building_id');
        $stmt->execute([':id' => $roomId, ':building_id' => $buildingId]);
        if (!$stmt->fetchColumn()) {
            $errors['room_id'] = 'Selected room does not belong to the selected building.';
            return;
        }

        $stmt = $pdo->prepare('SELECT 1 FROM devices WHERE id = :id AND room_id = :room_id');
        $stmt->execute([':id' => $deviceId, ':room_id' => $roomId]);
        if (!$stmt->fetchColumn()) {
            $errors['device_id'] = 'Selected device does not belong to the selected room.';
        }
    }

    private function notifyCriticalTicket(string $ticketNumber, int $campusId, int $buildingId, int $roomId, int $deviceId, string $description): void
    {
        $recipients = $this->config['notifications']['critical_recipients'] ?? [];
        if (!is_array($recipients) || count($recipients) === 0) {
            return;
        }

        $from = (string)($this->config['notifications']['from'] ?? 'no-reply@localhost');

        $pdo = $this->db->pdo();
        $stmt = $pdo->prepare('SELECT c.name AS campus, b.name AS building, r.name AS room,
                d.device_type AS device_type, d.label AS device_label
            FROM campuses c
            JOIN buildings b ON b.id = :building_id
            JOIN rooms r ON r.id = :room_id
            JOIN devices d ON d.id = :device_id
            WHERE c.id = :campus_id
            LIMIT 1');
        $stmt->execute([
            ':campus_id' => $campusId,
            ':building_id' => $buildingId,
            ':room_id' => $roomId,
            ':device_id' => $deviceId,
        ]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $campus = (string)($row['campus'] ?? '');
        $building = (string)($row['building'] ?? '');
        $room = (string)($row['room'] ?? '');
        $deviceType = (string)($row['device_type'] ?? '');
        $deviceLabel = (string)($row['device_label'] ?? '');

        $subject = '[CRITICAL] IT Issue ' . $ticketNumber;
        $body = "A CRITICAL IT issue has been reported.\n\n" .
            "Ticket: {$ticketNumber}\n" .
            "Campus: {$campus}\n" .
            "Building: {$building}\n" .
            "Room: {$room}\n" .
            "Device: {$deviceType}" . ($deviceLabel !== '' ? " — {$deviceLabel}" : '') . "\n\n" .
            "Description:\n{$description}\n";

        $headers = "From: {$from}\r\n" .
            "MIME-Version: 1.0\r\n" .
            "Content-Type: text/plain; charset=UTF-8\r\n";

        $sentAny = false;
        foreach ($recipients as $to) {
            if (!is_string($to) || trim($to) === '') {
                continue;
            }
            $ok = @mail($to, $subject, $body, $headers);
            if ($ok) {
                $sentAny = true;
            }
        }

        if (!$sentAny) {
            $logDir = __DIR__ . '/../../storage/logs';
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0777, true);
            }
            $line = '[' . date('Y-m-d H:i:s') . '] CRITICAL ticket notification (email not sent). ' . $subject . "\n";
            @file_put_contents($logDir . '/notifications.log', $line, FILE_APPEND);
        }
    }
}
