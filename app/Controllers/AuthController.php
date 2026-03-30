<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use PDO;

final class AuthController extends Controller
{
    public function showLogin(): void
    {
        if ($this->currentUser() !== null) {
            $this->redirect('/');
        }

        $this->view('auth/login', [
            'pageTitle' => 'Login',
            'pageSubtitle' => 'Sign in with your KSG email to continue.',
            'notice' => $this->flash('login_notice'),
            'error' => $this->flash('login_error'),
            'old' => [
                'email' => (string)($_GET['email'] ?? ''),
            ],
        ]);
    }

    public function showRegister(): void
    {
        if ($this->currentUser() !== null) {
            $this->redirect('/');
        }

        $allowed = $this->allowedCampuses();
        $campuses = $this->fetchCampusesByNames($allowed);

        $this->view('auth/register', [
            'pageTitle' => 'Create Account',
            'pageSubtitle' => 'Register using your official KSG email address.',
            'error' => $this->flash('register_error'),
            'campuses' => $campuses,
            'old' => [
                'name' => (string)($_GET['name'] ?? ''),
                'email' => (string)($_GET['email'] ?? ''),
                'campus_id' => (string)($_GET['campus_id'] ?? ''),
            ],
        ]);
    }

    public function register(): void
    {
        if ($this->currentUser() !== null) {
            $this->redirect('/');
        }

        $name = trim((string)($_POST['name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $campusId = (int)($_POST['campus_id'] ?? 0);
        $password = (string)($_POST['password'] ?? '');
        $password2 = (string)($_POST['password_confirm'] ?? '');

        if ($name === '' || strlen($name) < 2) {
            $this->flash('register_error', 'Please enter your name.');
            $this->redirect('/register?name=' . urlencode($name) . '&email=' . urlencode($email) . '&campus_id=' . urlencode((string)$campusId));
        }

        if (!$this->isKsgEmail($email)) {
            $this->flash('register_error', 'Only @ksg.ac.ke email addresses are allowed.');
            $this->redirect('/register?name=' . urlencode($name) . '&email=' . urlencode($email) . '&campus_id=' . urlencode((string)$campusId));
        }

        if ($campusId <= 0) {
            $this->flash('register_error', 'Please select your campus.');
            $this->redirect('/register?name=' . urlencode($name) . '&email=' . urlencode($email) . '&campus_id=' . urlencode((string)$campusId));
        }

        $stmtC = $this->db->pdo()->prepare('SELECT 1 FROM campuses WHERE id = :id');
        $stmtC->execute([':id' => $campusId]);
        if (!$stmtC->fetchColumn()) {
            $this->flash('register_error', 'Selected campus was not found.');
            $this->redirect('/register?name=' . urlencode($name) . '&email=' . urlencode($email) . '&campus_id=' . urlencode((string)$campusId));
        }

        $stmtCN = $this->db->pdo()->prepare('SELECT name FROM campuses WHERE id = :id LIMIT 1');
        $stmtCN->execute([':id' => $campusId]);
        $campusName = (string)($stmtCN->fetchColumn() ?: '');
        if ($campusName === '' || !in_array($campusName, $this->allowedCampuses(), true)) {
            $this->flash('register_error', 'Please select a valid campus.');
            $this->redirect('/register?name=' . urlencode($name) . '&email=' . urlencode($email) . '&campus_id=' . urlencode((string)$campusId));
        }

        if (!$this->isStrongPassword($password)) {
            $this->flash('register_error', 'Password must be at least 10 characters and include uppercase, lowercase, number, and symbol (no spaces).');
            $this->redirect('/register?name=' . urlencode($name) . '&email=' . urlencode($email) . '&campus_id=' . urlencode((string)$campusId));
        }

        if ($password2 !== '' && $password2 !== $password) {
            $this->flash('register_error', 'Passwords do not match.');
            $this->redirect('/register?name=' . urlencode($name) . '&email=' . urlencode($email) . '&campus_id=' . urlencode((string)$campusId));
        }

        $stmt = $this->db->pdo()->prepare('SELECT 1 FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        if ($stmt->fetchColumn()) {
            $this->flash('register_error', 'An account with this email already exists.');
            $this->redirect('/register?name=' . urlencode($name) . '&email=' . urlencode($email) . '&campus_id=' . urlencode((string)$campusId));
        }

        $stmtI = $this->db->pdo()->prepare('INSERT INTO users (name, email, campus_id, password_hash, created_at)
            VALUES (:name, :email, :campus_id, :password_hash, :created_at)');
        $stmtI->execute([
            ':name' => $name,
            ':email' => $email,
            ':campus_id' => $campusId,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->flash('login_notice', 'Account created successfully. Please login to continue.');
        $this->redirect('/login?email=' . urlencode($email));
    }

    public function login(): void
    {
        $email = trim((string)($_POST['email'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $next = (string)($_POST['next'] ?? ($_GET['next'] ?? '/'));

        if (!$this->isKsgEmail($email)) {
            $this->flash('login_error', 'Only @ksg.ac.ke email addresses are allowed.');
            $this->redirect('/login?email=' . urlencode($email) . '&next=' . urlencode($next));
        }

        $stmt = $this->db->pdo()->prepare('SELECT id, name, email, password_hash FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !isset($user['password_hash']) || !password_verify($password, (string)$user['password_hash'])) {
            $this->flash('login_error', 'Invalid email or password.');
            $this->redirect('/login?email=' . urlencode($email) . '&next=' . urlencode($next));
        }

        $code = (string) random_int(100000, 999999);
        $expiresAt = date('Y-m-d H:i:s', time() + 10 * 60);

        $this->db->pdo()->prepare('DELETE FROM auth_2fa_codes WHERE user_id = :uid')->execute([':uid' => (int)$user['id']]);
        $stmt2 = $this->db->pdo()->prepare('INSERT INTO auth_2fa_codes (user_id, code_hash, expires_at, created_at) VALUES (:uid, :code_hash, :expires_at, :created_at)');
        $stmt2->execute([
            ':uid' => (int)$user['id'],
            ':code_hash' => password_hash($code, PASSWORD_DEFAULT),
            ':expires_at' => $expiresAt,
            ':created_at' => date('Y-m-d H:i:s'),
        ]);

        $_SESSION['2fa_pending_user_id'] = (int)$user['id'];
        $_SESSION['2fa_next'] = $next;

        $this->flash('twofa_notice', '2FA code (simulation): ' . $code);

        $this->redirect('/2fa');
    }

    public function show2fa(): void
    {
        if ($this->currentUser() !== null) {
            $this->redirect('/');
        }

        $pending = (int)($_SESSION['2fa_pending_user_id'] ?? 0);
        if ($pending <= 0) {
            $this->redirect('/login');
        }

        $this->view('auth/2fa', [
            'pageTitle' => 'Two-Factor Authentication',
            'pageSubtitle' => 'Enter the 6-digit code sent to your email (simulated).',
            'notice' => $this->flash('twofa_notice'),
            'error' => $this->flash('twofa_error'),
        ]);
    }

    public function verify2fa(): void
    {
        if ($this->currentUser() !== null) {
            $this->redirect('/');
        }

        $pending = (int)($_SESSION['2fa_pending_user_id'] ?? 0);
        if ($pending <= 0) {
            $this->redirect('/login');
        }

        $code = trim((string)($_POST['code'] ?? ''));
        if ($code === '' || !preg_match('/^\d{6}$/', $code)) {
            $this->flash('twofa_error', 'Please enter a valid 6-digit code.');
            $this->redirect('/2fa');
        }

        $stmt = $this->db->pdo()->prepare('SELECT user_id, code_hash, expires_at FROM auth_2fa_codes WHERE user_id = :uid LIMIT 1');
        $stmt->execute([':uid' => $pending]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->flash('login_error', '2FA session expired. Please login again.');
            unset($_SESSION['2fa_pending_user_id']);
            $this->redirect('/login');
        }

        $expiresAt = (string)($row['expires_at'] ?? '');
        if ($expiresAt !== '' && strtotime($expiresAt) < time()) {
            $this->db->pdo()->prepare('DELETE FROM auth_2fa_codes WHERE user_id = :uid')->execute([':uid' => $pending]);
            $this->flash('login_error', '2FA code expired. Please login again.');
            unset($_SESSION['2fa_pending_user_id']);
            $this->redirect('/login');
        }

        if (!password_verify($code, (string)($row['code_hash'] ?? ''))) {
            $this->flash('twofa_error', 'Incorrect code.');
            $this->redirect('/2fa');
        }

        $this->db->pdo()->prepare('DELETE FROM auth_2fa_codes WHERE user_id = :uid')->execute([':uid' => $pending]);

        $stmtU = $this->db->pdo()->prepare('SELECT id, name, email FROM users WHERE id = :uid LIMIT 1');
        $stmtU->execute([':uid' => $pending]);
        $user = $stmtU->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            $this->flash('login_error', 'Account not found.');
            unset($_SESSION['2fa_pending_user_id']);
            $this->redirect('/login');
        }

        $this->setAuthUser($user);
        unset($_SESSION['2fa_pending_user_id']);

        $next = (string)($_SESSION['2fa_next'] ?? '/');
        unset($_SESSION['2fa_next']);

        $this->redirect($next !== '' ? $next : '/');
    }

    public function logout(): void
    {
        $this->clearAuth();
        $this->redirect('/');
    }

    private function isKsgEmail(string $email): bool
    {
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        return (bool)preg_match('/@ksg\.ac\.ke$/i', $email);
    }

    private function isStrongPassword(string $password): bool
    {
        if (strlen($password) < 10) {
            return false;
        }

        if (preg_match('/\s/', $password)) {
            return false;
        }

        $hasUpper = (bool)preg_match('/[A-Z]/', $password);
        $hasLower = (bool)preg_match('/[a-z]/', $password);
        $hasDigit = (bool)preg_match('/\d/', $password);
        $hasSymbol = (bool)preg_match('/[^A-Za-z0-9]/', $password);

        return $hasUpper && $hasLower && $hasDigit && $hasSymbol;
    }

    private function allowedCampuses(): array
    {
        $c = $this->config['app']['campuses'] ?? [];
        return is_array($c) ? array_values(array_filter($c, 'is_string')) : [];
    }

    private function fetchCampusesByNames(array $names): array
    {
        $names = array_values(array_filter($names, static fn($v) => is_string($v) && trim($v) !== ''));
        if (count($names) === 0) {
            return $this->db->pdo()->query('SELECT id, name FROM campuses ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        }

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

        return $rows;
    }
}
