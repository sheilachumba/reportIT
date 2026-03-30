<?php

declare(strict_types=1);

$notice = $notice ?? null;
$error = $error ?? null;

?>

<div class="grid">
  <div class="card">
    <div class="card-h">
      <h2>Two-Factor Authentication</h2>
      <p>Enter the 6-digit code.</p>
    </div>
    <div class="card-b">
      <?php if (is_string($notice) && $notice !== ''): ?>
        <div class="alert success"><?php echo htmlspecialchars($notice); ?></div>
      <?php endif; ?>

      <?php if (is_string($error) && $error !== ''): ?>
        <div class="alert"><?php echo htmlspecialchars($error); ?></div>
      <?php endif; ?>

      <form method="post" action="/2fa">
        <div class="field">
          <div class="label">2FA code</div>
          <input class="input" type="text" name="code" inputmode="numeric" pattern="\d{6}" maxlength="6" placeholder="123456" required />
          <div class="help">The code expires in 10 minutes.</div>
        </div>

        <div style="display:flex; gap:12px; align-items:center; justify-content:flex-end; margin-top:10px">
          <button class="btn" type="submit">Verify</button>
        </div>
      </form>

      <div style="margin-top:14px">
        <a class="btn btn-secondary" href="/login">Back to login</a>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-h">
      <h2>Email delivery (simulated)</h2>
      <p>In a live deployment the 2FA code would be emailed to your @ksg.ac.ke address.</p>
    </div>
    <div class="card-b">
      <div class="kv">
        <div class="line"><span>Delivery</span><span>Email</span></div>
        <div class="line"><span>Mode</span><span>Simulation</span></div>
      </div>
    </div>
  </div>
</div>
