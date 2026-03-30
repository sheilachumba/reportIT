<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PDO;

final class ApiController extends Controller
{
    public function campuses(): void
    {
        $this->requireAuth();
        $allowed = $this->config['app']['campuses'] ?? [];
        if (is_array($allowed) && count($allowed) > 0) {
            $names = array_values(array_filter($allowed, static fn($v) => is_string($v) && trim($v) !== ''));
            $placeholders = implode(',', array_fill(0, count($names), '?'));
            $stmt = $this->db->pdo()->prepare('SELECT id, name FROM campuses WHERE name IN (' . $placeholders . ')');
            $stmt->execute($names);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $order = array_flip($names);
            usort($rows, static function ($a, $b) use ($order) {
                $an = (string)($a['name'] ?? '');
                $bn = (string)($b['name'] ?? '');
                return ($order[$an] ?? 9999) <=> ($order[$bn] ?? 9999);
            });
        } else {
            $rows = $this->db->pdo()->query('SELECT id, name FROM campuses ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        }
        $this->json(['data' => $rows]);
    }

    public function buildings(): void
    {
        $this->requireAuth();
        $campusId = (int) ($_GET['campus_id'] ?? 0);
        if ($campusId <= 0) {
            $this->json(['error' => 'campus_id is required'], 422);
            return;
        }

        $stmt = $this->db->pdo()->prepare('SELECT id, name FROM buildings WHERE campus_id = :id ORDER BY name');
        $stmt->execute([':id' => $campusId]);
        $this->json(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function rooms(): void
    {
        $this->requireAuth();
        $buildingId = (int) ($_GET['building_id'] ?? 0);
        if ($buildingId <= 0) {
            $this->json(['error' => 'building_id is required'], 422);
            return;
        }

        $stmt = $this->db->pdo()->prepare('SELECT id, name FROM rooms WHERE building_id = :id ORDER BY name');
        $stmt->execute([':id' => $buildingId]);
        $this->json(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    public function devices(): void
    {
        $this->requireAuth();
        $roomId = (int) ($_GET['room_id'] ?? 0);
        if ($roomId <= 0) {
            $this->json(['error' => 'room_id is required'], 422);
            return;
        }

        $stmt = $this->db->pdo()->prepare('SELECT id, device_type, asset_tag, label FROM devices WHERE room_id = :id ORDER BY device_type');
        $stmt->execute([':id' => $roomId]);
        $this->json(['data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }
}
