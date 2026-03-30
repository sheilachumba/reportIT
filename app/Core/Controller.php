<?php

declare(strict_types=1);

namespace App\Core;

abstract class Controller
{
    public function __construct(protected array $config, protected Database $db)
    {
    }

    protected function currentUser(): ?array
    {
        $u = $_SESSION['auth_user'] ?? null;
        return is_array($u) ? $u : null;
    }

    protected function requireAuth(): array
    {
        $u = $this->currentUser();
        if ($u === null) {
            $next = $_SERVER['REQUEST_URI'] ?? '/';
            $this->redirect('/login?next=' . urlencode($next));
        }

        return $u;
    }

    protected function setAuthUser(array $user): void
    {
        $_SESSION['auth_user'] = [
            'id' => (int)($user['id'] ?? 0),
            'name' => (string)($user['name'] ?? ''),
            'email' => (string)($user['email'] ?? ''),
        ];
    }

    protected function clearAuth(): void
    {
        unset($_SESSION['auth_user']);
        unset($_SESSION['2fa_pending_user_id']);
    }

    protected function flash(string $key, ?string $value = null): ?string
    {
        if ($value !== null) {
            $_SESSION['_flash'][$key] = $value;
            return null;
        }

        $v = $_SESSION['_flash'][$key] ?? null;
        unset($_SESSION['_flash'][$key]);
        return is_string($v) ? $v : null;
    }

    protected function view(string $view, array $data = []): void
    {
        $v = new View($this->config);
        $v->render($view, $data);
    }

    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    protected function redirect(string $to): void
    {
        header('Location: ' . $to);
        exit;
    }
}
