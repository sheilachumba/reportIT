<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class HomeController extends Controller
{
    public function index(): void
    {
        $u = $this->currentUser();

        if (is_array($u) && !empty($u['email'])) {
            $admins = $this->config['app']['admin_emails'] ?? [];
            if (is_array($admins) && in_array((string)($u['email'] ?? ''), $admins, true)) {
                $this->redirect('/admin');
            }
        }

        $this->view('home/index', [
            'pageTitle' => 'ReportIT',
            'pageSubtitle' => 'Kenya School of Government — IT Issue Reporting System',
            'user' => $u,
        ]);
    }
}
