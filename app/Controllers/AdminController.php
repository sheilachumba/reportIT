<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PDO;

final class AdminController extends Controller
{
    public function dashboard(): void
    {
        $u = $this->requireAuth();

        $admins = $this->config['app']['admin_emails'] ?? [];
        $email = (string)($u['email'] ?? '');
        if (!is_array($admins) || !in_array($email, $admins, true)) {
            http_response_code(403);
            echo '403 Forbidden';
            return;
        }

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
}
