<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Mailer;
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

        $stmtI = $this->db->pdo()->prepare('INSERT INTO users (name, email, campus_id, password_hash, twofa_enabled, twofa_enabled_at, created_at)
            VALUES (:name, :email, :campus_id, :password_hash, :twofa_enabled, :twofa_enabled_at, :created_at)');
        $stmtI->execute([
            ':name' => $name,
            ':email' => $email,
            ':campus_id' => $campusId,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':twofa_enabled' => 1,
            ':twofa_enabled_at' => gmdate('Y-m-d H:i:s'),
            ':created_at' => gmdate('Y-m-d H:i:s'),
        ]);

        $userId = (int)$this->db->pdo()->lastInsertId();
        $mailer = new Mailer($this->config);
        $ok = $this->sendVerificationEmail($userId, $email, $name, $mailer);
        if ($ok) {
            $this->flash('login_notice', 'Account created successfully. Please check your email to verify your account, then login.');
        } else {
            $this->flash('login_error', 'Account created, but verification email failed to send. Please visit /verify-email/resend?email=' . $email . ' to resend. Error: ' . $mailer->lastError());
        }
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

        $stmt = $this->db->pdo()->prepare('SELECT id, name, email, password_hash, email_verified_at, twofa_enabled FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !isset($user['password_hash']) || !password_verify($password, (string)$user['password_hash'])) {
            $this->flash('login_error', 'Invalid email or password.');
            $this->redirect('/login?email=' . urlencode($email) . '&next=' . urlencode($next));
        }

        $verifiedAt = trim((string)($user['email_verified_at'] ?? ''));
        if ($verifiedAt === '') {
            $this->flash('login_error', 'Your email is not verified. Please verify your account first. If you did not receive the email, visit /verify-email/resend?email=' . $email);
            $this->redirect('/login?email=' . urlencode($email) . '&next=' . urlencode($next));
        }

        $twofaEnabled = (int)($user['twofa_enabled'] ?? 1);
        if ($twofaEnabled !== 1) {
            $this->setAuthUser($user);
            unset($_SESSION['2fa_pending_user_id']);
            unset($_SESSION['2fa_next']);
            $this->redirect($next !== '' ? $next : '/');
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

        $mailer = new Mailer($this->config);
        $ok = $this->sendTwoFactorCodeEmail((int)$user['id'], (string)($user['email'] ?? ''), (string)($user['name'] ?? ''), $code, $expiresAt, $mailer);
        if (!$ok) {
            $this->db->pdo()->prepare('DELETE FROM auth_2fa_codes WHERE user_id = :uid')->execute([':uid' => (int)$user['id']]);
            $this->flash('login_error', 'Could not send 2FA code email. Please try again. Error: ' . $mailer->lastError());
            $this->redirect('/login?email=' . urlencode($email) . '&next=' . urlencode($next));
        }

        $_SESSION['2fa_pending_user_id'] = (int)$user['id'];
        $_SESSION['2fa_next'] = $next;

        $this->flash('twofa_notice', 'A 6-digit verification code has been sent to your email.');

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

        try {
            $stmt = $this->db->pdo()->prepare('SELECT twofa_enabled FROM users WHERE id = :uid LIMIT 1');
            $stmt->execute([':uid' => $pending]);
            $enabled = (int)($stmt->fetchColumn() ?: 1);
            if ($enabled !== 1) {
                unset($_SESSION['2fa_pending_user_id']);
                $this->redirect('/');
            }
        } catch (\Throwable $e) {
        }

        $this->view('auth/2fa', [
            'pageTitle' => 'Two-Factor Authentication',
            'pageSubtitle' => 'Enter the 6-digit code sent to your email.',
            'notice' => $this->flash('twofa_notice'),
            'error' => $this->flash('twofa_error'),
        ]);
    }

    public function verifyEmail(): void
    {
        if ($this->currentUser() !== null) {
            $this->redirect('/');
        }

        $token = trim((string)($_GET['token'] ?? ''));
        if ($token === '') {
            $this->flash('login_error', 'Invalid verification link.');
            $this->redirect('/login');
        }

        $tokenHash = hash('sha256', $token);
        $stmt = $this->db->pdo()->prepare('SELECT v.user_id, v.expires_at, v.consumed_at, u.email, u.email_verified_at
            FROM auth_email_verifications v
            JOIN users u ON u.id = v.user_id
            WHERE v.token_hash = :h
            LIMIT 1');
        $stmt->execute([':h' => $tokenHash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            $this->flash('login_error', 'Verification link is invalid or has already been used.');
            $this->redirect('/login');
        }

        $consumedAt = trim((string)($row['consumed_at'] ?? ''));
        if ($consumedAt !== '') {
            $this->flash('login_notice', 'Your account is already verified. Please login.');
            $this->redirect('/login?email=' . urlencode((string)($row['email'] ?? '')));
        }

        $expiresAt = trim((string)($row['expires_at'] ?? ''));
        if ($expiresAt === '' || strtotime($expiresAt) < time()) {
            $this->flash('login_error', 'Verification link has expired. Please request a new verification email via /verify-email/resend?email=' . (string)($row['email'] ?? ''));
            $this->redirect('/login?email=' . urlencode((string)($row['email'] ?? '')));
        }

        $uid = (int)($row['user_id'] ?? 0);
        if ($uid <= 0) {
            $this->flash('login_error', 'Verification failed.');
            $this->redirect('/login');
        }

        $now = gmdate('Y-m-d H:i:s');
        $this->db->pdo()->prepare('UPDATE users SET email_verified_at = :v WHERE id = :uid AND (email_verified_at IS NULL OR email_verified_at = "")')->execute([
            ':v' => $now,
            ':uid' => $uid,
        ]);
        $this->db->pdo()->prepare('UPDATE auth_email_verifications SET consumed_at = :c WHERE user_id = :uid AND token_hash = :h')->execute([
            ':c' => $now,
            ':uid' => $uid,
            ':h' => $tokenHash,
        ]);

        $this->flash('login_notice', 'Email verified successfully. Please login to continue.');
        $this->redirect('/login?email=' . urlencode((string)($row['email'] ?? '')));
    }

    public function resendVerification(): void
    {
        if ($this->currentUser() !== null) {
            $this->redirect('/');
        }

        $email = trim((string)($_GET['email'] ?? ''));
        if (!$this->isKsgEmail($email)) {
            $this->flash('login_error', 'Please provide a valid @ksg.ac.ke email address.');
            $this->redirect('/login');
        }

        $stmt = $this->db->pdo()->prepare('SELECT id, name, email, email_verified_at FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$user) {
            $this->flash('login_error', 'Account not found.');
            $this->redirect('/login?email=' . urlencode($email));
        }

        $verifiedAt = trim((string)($user['email_verified_at'] ?? ''));
        if ($verifiedAt !== '') {
            $this->flash('login_notice', 'Your account is already verified. Please login.');
            $this->redirect('/login?email=' . urlencode($email));
        }

        $mailer = new Mailer($this->config);
        $ok = $this->sendVerificationEmail((int)($user['id'] ?? 0), (string)($user['email'] ?? ''), (string)($user['name'] ?? ''), $mailer);
        if ($ok) {
            $this->flash('login_notice', 'Verification email sent. Please check your inbox.');
        } else {
            $this->flash('login_error', 'Failed to send verification email. Error: ' . $mailer->lastError());
        }

        $this->redirect('/login?email=' . urlencode($email));
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

        try {
            $stmtE = $this->db->pdo()->prepare('SELECT twofa_enabled FROM users WHERE id = :uid LIMIT 1');
            $stmtE->execute([':uid' => $pending]);
            $enabled = (int)($stmtE->fetchColumn() ?: 1);
            if ($enabled !== 1) {
                unset($_SESSION['2fa_pending_user_id']);

                $next = (string)($_SESSION['2fa_next'] ?? '/');
                unset($_SESSION['2fa_next']);

                $stmtU = $this->db->pdo()->prepare('SELECT id, name, email FROM users WHERE id = :uid LIMIT 1');
                $stmtU->execute([':uid' => $pending]);
                $user = $stmtU->fetch(PDO::FETCH_ASSOC);
                if (is_array($user)) {
                    $this->setAuthUser($user);
                }

                $this->redirect($next !== '' ? $next : '/');
            }
        } catch (\Throwable $e) {
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

        $admins = $this->config['app']['admin_emails'] ?? [];
        if (is_array($admins) && in_array((string)($user['email'] ?? ''), $admins, true)) {
            $this->redirect('/admin');
        }

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

    private function sendVerificationEmail(int $userId, string $email, string $name, Mailer $mailer): bool
    {
        if ($userId <= 0 || !$this->isKsgEmail($email)) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $token);
        $expiresAt = gmdate('Y-m-d H:i:s', time() + 24 * 60 * 60);
        $now = gmdate('Y-m-d H:i:s');

        $this->db->pdo()->prepare('DELETE FROM auth_email_verifications WHERE user_id = :uid')->execute([':uid' => $userId]);
        $stmt = $this->db->pdo()->prepare('INSERT INTO auth_email_verifications (user_id, token_hash, expires_at, created_at) VALUES (:uid, :h, :e, :c)');
        $stmt->execute([
            ':uid' => $userId,
            ':h' => $tokenHash,
            ':e' => $expiresAt,
            ':c' => $now,
        ]);

        $baseUrl = $this->baseUrl();
        $verifyUrl = rtrim($baseUrl, '/') . '/verify-email?token=' . urlencode($token);

        [$logoSrc, $inlineImages] = $this->emailLogo();

        $subject = 'Verify your ReportIT account';
        $title = 'Verify your email address';
        $intro = 'Hello ' . ($name !== '' ? $name : $email) . ', please verify your ReportIT account by clicking the button below. This link expires in 24 hours.';
        $rows = [
            'Email' => $email,
            'Expiry' => '24 hours',
        ];

        $html = Mailer::renderBrandedEmail($title, $intro, $rows, $verifyUrl, 'Verify Account', $logoSrc);
        return $mailer->sendHtml($email, $subject, $html, '', $inlineImages);
    }

    private function sendTwoFactorCodeEmail(int $userId, string $email, string $name, string $code, string $expiresAt, Mailer $mailer): bool
    {
        if ($userId <= 0 || !$this->isKsgEmail($email)) {
            return false;
        }

        [$logoSrc, $inlineImages] = $this->emailLogo();

        $subject = 'Your ReportIT verification code';
        $title = 'Login verification code';
        $intro = 'Hello ' . ($name !== '' ? $name : $email) . ', use the code below to complete your login. If you did not request this, you can ignore this email.';
        $rows = [
            'Code' => $code,
            'Expires At' => $expiresAt,
        ];

        $baseUrl = $this->baseUrl();
        $ctaUrl = rtrim($baseUrl, '/') . '/2fa';
        $html = Mailer::renderBrandedEmail($title, $intro, $rows, $ctaUrl, 'Enter Code', $logoSrc);
        return $mailer->sendHtml($email, $subject, $html, '', $inlineImages);
    }

    private function baseUrl(): string
    {
        $cfg = trim((string)($this->config['app']['base_url'] ?? ''));
        if ($cfg !== '') {
            return $cfg;
        }

        $host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
        $https = (string)($_SERVER['HTTPS'] ?? '');
        $proto = ($https !== '' && $https !== 'off') ? 'https' : 'http';
        return $proto . '://' . $host;
    }

    private function emailLogo(): array
    {
        $path = dirname(__DIR__, 2) . '/public/assets/ksg-logo.png';
        if (is_file($path)) {
            $data = @file_get_contents($path);
            if (is_string($data) && $data !== '') {
                return [
                    'cid:ksg-logo',
                    [[
                        'cid' => 'ksg-logo',
                        'mime' => 'image/png',
                        'filename' => 'ksg-logo.png',
                        'data' => $data,
                    ]],
                ];
            }
        }

        return [rtrim($this->baseUrl(), '/') . '/assets/ksg-logo.png', []];
    }
}
