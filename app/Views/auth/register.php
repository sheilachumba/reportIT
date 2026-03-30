<?php

declare(strict_types=1);

$error = $error ?? null;
$old = $old ?? [];
$campuses = $campuses ?? [];

?>

<div class="grid">
  <div class="card">
    <div class="card-h">
      <h2>Create account</h2>
      <p>Register a new account to access the reporting system.</p>
    </div>
    <div class="card-b">
      <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="post" action="/register">
        <div class="field">
          <div class="label">Full name</div>
          <input class="input" type="text" name="name" value="<?php echo htmlspecialchars((string)($old['name'] ?? '')); ?>" placeholder="e.g., John Doe" required />
        </div>

        <div class="field">
          <div class="label">Email</div>
          <input class="input" type="email" name="email" value="<?php echo htmlspecialchars((string)($old['email'] ?? '')); ?>" placeholder="name@ksg.ac.ke" required />
          <div class="help">Only emails ending with <strong>@ksg.ac.ke</strong> are allowed.</div>
        </div>

        <div class="field">
          <div class="label">Campus</div>
          <select name="campus_id" required>
            <option value="">Select campus</option>
            <?php foreach ($campuses as $c): ?>
              <option value="<?php echo htmlspecialchars((string)($c['id'] ?? '')); ?>" <?php echo ((string)($old['campus_id'] ?? '') === (string)($c['id'] ?? '')) ? 'selected' : ''; ?>><?php echo htmlspecialchars((string)($c['name'] ?? '')); ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="row">
          <div class="field">
            <div class="label">Password</div>
            <div class="pw-wrap">
              <input id="register_password" class="input" type="password" name="password" placeholder="Create a strong password" required />
              <button type="button" class="pw-toggle" data-target="register_password" aria-pressed="false" aria-label="Show password">
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
            <div class="help">At least 10 characters, with uppercase, lowercase, number and symbol. No spaces.</div>
          </div>
          <div class="field">
            <div class="label">Confirm password</div>
            <div class="pw-wrap">
              <input id="register_password_confirm" class="input" type="password" name="password_confirm" placeholder="Repeat password" required />
              <button type="button" class="pw-toggle" data-target="register_password_confirm" aria-pressed="false" aria-label="Show password">
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
          </div>
        </div>

        <div style="display:flex; gap:12px; align-items:center; justify-content:flex-end; margin-top:10px">
          <button class="btn" type="submit">Create account</button>
        </div>
      </form>

      <div style="margin-top:14px">
        <a class="btn btn-secondary" href="/login">Already have an account? Login</a>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-h">
      <h2>Security</h2>
      <p>After login, you will be required to enter a 2FA code.</p>
    </div>
    <div class="card-b">
      <div class="kv">
        <div class="line"><span>Account</span><span>@ksg.ac.ke only</span></div>
        <div class="line"><span>Password</span><span>Strong rules enforced</span></div>
        <div class="line"><span>2FA</span><span>6-digit code</span></div>
      </div>
    </div>
  </div>
</div>
