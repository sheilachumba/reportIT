<?php

declare(strict_types=1);

$notice = $notice ?? null;
$error = $error ?? null;
$old = $old ?? [];
$next = (string)($_GET['next'] ?? '/');

?>

<div class="grid">
  <div class="card">
    <div class="card-h">
      <div style="display:flex; align-items:center; gap:12px">
        <img src="/assets/ksg-logo.png" alt="KSG" style="height:44px; width:auto" />
        <div>
      <h2>Login</h2>
      <p>Use your official KSG email account to access the reporting system.</p>
        </div>
      </div>
    </div>
    <div class="card-b">
      <?php if (is_string($notice) && $notice !== ''): ?>
        <div class="alert success"><?php echo htmlspecialchars($notice); ?></div>
      <?php endif; ?>

      <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="post" action="/login">
        <input type="hidden" name="next" value="<?php echo htmlspecialchars($next); ?>" />

        <div class="field">
          <div class="label">Email</div>
          <input class="input" type="email" name="email" placeholder="name@ksg.ac.ke" value="<?php echo htmlspecialchars((string)($old['email'] ?? '')); ?>" required />
          <div class="help">Only emails ending with <strong>@ksg.ac.ke</strong> are allowed.</div>
        </div>

        <div class="field">
          <div class="label">Password</div>
          <div class="pw-wrap">
            <input id="login_password" class="input" type="password" name="password" placeholder="Enter your password" required />
            <button type="button" class="pw-toggle" data-target="login_password" aria-pressed="false" aria-label="Show password">
              <span class="icon-on">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M2.5 12s3.5-7 9.5-7 9.5 7 9.5 7-3.5 7-9.5 7-9.5-7-9.5-7Z" stroke="currentColor" stroke-width="1.8" />
                  <path d="M12 15.5a3.5 3.5 0 1 0 0-7 3.5 3.5 0 0 0 0 7Z" stroke="currentColor" stroke-width="1.8" />
                </svg>
              </span>
              <span class="icon-off">
                <svg viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                  <path d="M3 3l18 18" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                  <path d="M10.6 10.6a2 2 0 0 0 2.8 2.8" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                  <path d="M6.6 6.6C4.3 8.3 2.5 12 2.5 12s3.5 7 9.5 7c1.7 0 3.2-.4 4.5-1" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                  <path d="M9.2 4.6c.9-.4 1.8-.6 2.8-.6 6 0 9.5 7 9.5 7s-1.1 2.2-3 4" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" />
                </svg>
              </span>
            </button>
          </div>
          <div class="help">Default seeded account: admin@ksg.ac.ke / 123456</div>
        </div>

        <div style="display:flex; gap:12px; align-items:center; justify-content:flex-end; margin-top:10px">
          <button class="btn" type="submit">Continue</button>
        </div>
      </form>

      <div style="margin-top:14px">
        <a class="btn btn-secondary" href="/register">Create an account</a>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-h">
      <h2>Security</h2>
      <p>This system uses 2FA to secure access.</p>
    </div>
    <div class="card-b">
      <div class="kv">
        <div class="line"><span>Step</span><span>Password login</span></div>
        <div class="line"><span>Step</span><span>2FA code verification</span></div>
      </div>
      <div class="footer-note">
        For production, connect email delivery (SMTP/API). Right now the 2FA code is shown as a simulation.
      </div>
    </div>
  </div>
</div>
