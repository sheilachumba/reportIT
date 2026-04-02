<?php

declare(strict_types=1);

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>ReportIT</title>
  <link rel="stylesheet" href="/assets/app.css" />
</head>
<body>
  <div class="container">
    <div class="header">
      <div class="brand">
        <div class="brand-row">
          <a class="logo-link" href="/">
            <img class="app-logo" src="/assets/ksg-logo.png" alt="KSG" />
          </a>
          <div class="brand-text">
            <h1><?php echo htmlspecialchars((string)($pageTitle ?? 'ReportIT')); ?></h1>
            <p><?php echo htmlspecialchars((string)($pageSubtitle ?? '')); ?></p>
          </div>
        </div>
      </div>

      <div class="topnav">
        <?php $u = $_SESSION['auth_user'] ?? null; ?>
        <?php $path = (string)(parse_url((string)($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?? '/'); ?>
        <?php $isAdminPath = strncmp($path, '/admin', 6) === 0; ?>
        <?php if (is_array($u) && !empty($u['email'])): ?>
          <div class="topnav-user">
            <div class="pill"><?php echo htmlspecialchars((string)($u['name'] ?? '')); ?></div>
            <div class="pill"><?php echo htmlspecialchars((string)($u['email'] ?? '')); ?></div>
          </div>
          <?php $admins = $this->config['app']['admin_emails'] ?? []; ?>
          <?php if (!$isAdminPath && is_array($admins) && in_array((string)($u['email'] ?? ''), $admins, true)): ?>
            <a class="btn btn-secondary" href="/admin">Admin Dashboard</a>
            <a class="btn btn-secondary" href="/admin/tickets">Ticket Management</a>
            <a class="btn btn-secondary" href="/admin/staff">Staff Overview</a>
          <?php endif; ?>
          <?php if (!$isAdminPath): ?>
            <a class="btn btn-secondary" href="/logout">Logout</a>
          <?php endif; ?>
        <?php else: ?>
          <a class="btn btn-secondary" href="/login">Login</a>
        <?php endif; ?>
      </div>
    </div>

    <?php $flashNotice = $_SESSION['_flash']['notice'] ?? null; ?>
    <?php $flashError = $_SESSION['_flash']['error'] ?? null; ?>
    <?php if (is_string($flashNotice) && $flashNotice !== ''): ?>
      <?php unset($_SESSION['_flash']['notice']); ?>
      <div class="alert success"><?php echo htmlspecialchars($flashNotice); ?></div>
    <?php endif; ?>
    <?php if (is_string($flashError) && $flashError !== ''): ?>
      <?php unset($_SESSION['_flash']['error']); ?>
      <div class="alert"><?php echo htmlspecialchars($flashError); ?></div>
    <?php endif; ?>

    <?php echo $content; ?>
  </div>

  <script src="/assets/app.js"></script>
</body>
</html>
