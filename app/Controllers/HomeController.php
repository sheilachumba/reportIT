<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;

final class HomeController extends Controller
{
    public function index(): void
    {
        $u = $this->currentUser();

        $this->view('home/index', [
            'pageTitle' => 'ReportIT',
            'pageSubtitle' => 'Kenya School of Government — IT Issue Reporting System',
            'user' => $u,
        ]);
    }
}
