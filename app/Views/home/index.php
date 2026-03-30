<?php

declare(strict_types=1);

$user = $user ?? null;

?>

<div class="grid">
  <div class="card">
    <div class="card-h">
      <h2>Welcome</h2>
      <p>Report IT issues by campus, building, room and device. Tickets are logged instantly.</p>
    </div>
    <div class="card-b">
      <?php if (is_array($user) && !empty($user['email'])): ?>
        <div class="kv">
          <div class="line"><span>Signed in as</span><span><?php echo htmlspecialchars((string)($user['name'] ?? '')); ?></span></div>
          <div class="line"><span>Email</span><span><?php echo htmlspecialchars((string)($user['email'] ?? '')); ?></span></div>
        </div>

        <div style="margin-top:14px; display:flex; gap:12px; flex-wrap:wrap">
          <a class="btn" href="/issue/new">Report an issue</a>
          <a class="btn btn-secondary" href="/logout">Logout</a>
        </div>
      <?php else: ?>
        <div class="alert">You must login with your KSG email (@ksg.ac.ke) before submitting an issue.</div>
        <div style="margin-top:14px">
          <a class="btn" href="/login">Login</a>
          <a class="btn btn-secondary" href="/register">Create account</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-h">
      <h2>How it works</h2>
      <p>Accurate selection helps the IT team resolve issues faster.</p>
    </div>
    <div class="card-b">
      <div class="kv">
        <div class="line"><span>Step 1</span><span>Login (KSG email)</span></div>
        <div class="line"><span>Step 2</span><span>Select location and device</span></div>
        <div class="line"><span>Step 3</span><span>Describe the issue + attach image</span></div>
        <div class="line"><span>Step 4</span><span>Receive ticket number + 2FA secured account</span></div>
      </div>
    </div>
  </div>
</div>
