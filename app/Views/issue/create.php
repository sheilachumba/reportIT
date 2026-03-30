<?php

declare(strict_types=1);

$errors = $errors ?? [];
$old = $old ?? [];

function field_error(array $errors, string $key): string {
    return isset($errors[$key]) ? '<div class="help" style="color: rgba(255,77,109,.95)">' . htmlspecialchars((string)$errors[$key]) . '</div>' : '';
}

?>

<div class="grid">
  <div class="card">
    <div class="card-h">
      <h2>Issue Report</h2>
      <p>Complete the form below. Fields update dynamically based on your selection.</p>
    </div>
    <div class="card-b">

      <?php if (!empty($errors)): ?>
        <div class="alert">Please fix the highlighted fields and submit again.</div>
      <?php endif; ?>

      <form method="post" action="/issue" enctype="multipart/form-data">
        <div class="row">
          <div class="field">
            <div class="label">Your name (optional)</div>
            <input class="input" type="text" name="reporter_name" value="<?php echo htmlspecialchars((string)($old['reporter_name'] ?? '')); ?>" placeholder="e.g., Jane Doe" />
          </div>
          <div class="field">
            <div class="label">Your email (optional)</div>
            <input class="input" type="email" name="reporter_email" value="<?php echo htmlspecialchars((string)($old['reporter_email'] ?? '')); ?>" placeholder="e.g., name@ksg.ac.ke" readonly />
            <?php echo field_error($errors, 'reporter_email'); ?>
            <div class="help">This is auto-filled from your login.</div>
          </div>
        </div>

        <div class="field">
          <div class="label">Campus</div>
          <select id="campus_id" name="campus_id" required data-selected="<?php echo htmlspecialchars((string)($old['campus_id'] ?? '')); ?>">
            <option value="">Select campus</option>
          </select>
          <?php echo field_error($errors, 'campus_id'); ?>
        </div>

        <div class="row">
          <div class="field">
            <div class="label">Building</div>
            <select id="building_id" name="building_id" required data-selected="<?php echo htmlspecialchars((string)($old['building_id'] ?? '')); ?>">
              <option value="">Select building</option>
            </select>
            <?php echo field_error($errors, 'building_id'); ?>
          </div>
          <div class="field">
            <div class="label">Room</div>
            <select id="room_id" name="room_id" required data-selected="<?php echo htmlspecialchars((string)($old['room_id'] ?? '')); ?>">
              <option value="">Select room</option>
            </select>
            <?php echo field_error($errors, 'room_id'); ?>
          </div>
        </div>

        <div class="field">
          <div class="label">Device</div>
          <select id="device_id" name="device_id" required data-selected="<?php echo htmlspecialchars((string)($old['device_id'] ?? '')); ?>">
            <option value="">Select device</option>
          </select>
          <?php echo field_error($errors, 'device_id'); ?>
          <div class="help">Tip: select the exact device entry (type + asset tag).</div>
        </div>

        <div class="row">
          <div class="field">
            <div class="label">Device condition</div>
            <select name="device_condition" required>
              <?php foreach (['Excellent','Good','Fair','Poor'] as $c): ?>
                <option value="<?php echo $c; ?>" <?php echo (($old['device_condition'] ?? 'Good') === $c) ? 'selected' : ''; ?>><?php echo $c; ?></option>
              <?php endforeach; ?>
            </select>
            <?php echo field_error($errors, 'device_condition'); ?>
          </div>
          <div class="field">
            <div class="label">Severity</div>
            <select name="severity" required>
              <?php foreach (['Critical','High','Medium','Low'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo (($old['severity'] ?? 'Medium') === $s) ? 'selected' : ''; ?>><?php echo $s; ?></option>
              <?php endforeach; ?>
            </select>
            <?php echo field_error($errors, 'severity'); ?>
          </div>
        </div>

        <div class="field">
          <div class="label">Issue description</div>
          <textarea name="description" placeholder="Describe what is happening, what you expected, and any relevant details..." required><?php echo htmlspecialchars((string)($old['description'] ?? '')); ?></textarea>
          <?php echo field_error($errors, 'description'); ?>
        </div>

        <div class="field">
          <div class="label">File/Image upload (optional)</div>
          <input class="input" type="file" name="attachment" accept="image/*,application/pdf" />
          <div class="help">You can attach a screenshot or photo (max 10MB).</div>
        </div>

        <div style="display:flex; gap:12px; align-items:center; justify-content:flex-end; margin-top:10px">
          <button id="submitBtn" class="btn" type="submit">Submit issue</button>
        </div>
      </form>

    </div>
  </div>

  <div class="card">
    <div class="card-h">
      <h2>What happens next?</h2>
      <p>Once submitted, a ticket is created and you’ll see a confirmation screen with the generated ticket number.</p>
    </div>
    <div class="card-b">
      <div class="kv">
        <div class="line"><span>Ticket status</span><span>Open</span></div>
        <div class="line"><span>Notifications</span><span>Email (simulated)</span></div>
        <div class="line"><span>Tracking</span><span>Campus → Building → Room → Device</span></div>
        <div class="line"><span>Attachments</span><span>Optional</span></div>
      </div>
      <div class="footer-note">
        Use <span class="pill">Critical</span> for issues that block teaching/learning or cause downtime.
      </div>
    </div>
  </div>
</div>
