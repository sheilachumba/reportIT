<?php

declare(strict_types=1);

$ticket = $ticket ?? [];
$attachments = $attachments ?? [];
$staff = $staff ?? [];

$ticketNumber = (string)($ticket['ticket_number'] ?? '');
$reported = (string)($ticket['created_at'] ?? '');

$sev = (string)($ticket['severity'] ?? '');
$sevClass = $sev === 'Critical' ? 'crit' : ($sev === 'High' ? 'high' : ($sev === 'Medium' ? 'med' : ($sev === 'Low' ? 'low' : '')));

$status = (string)($ticket['status'] ?? '');

$assignedName = trim((string)($ticket['assigned_to_name'] ?? ''));
$assignedEmail = trim((string)($ticket['assigned_to_email'] ?? ''));

$createdAt = (string)($ticket['created_at'] ?? '');
$updatedAt = (string)($ticket['updated_at'] ?? '');
$resolvedAt = (string)($ticket['resolved_at'] ?? '');

$campus = trim((string)($ticket['campus_name'] ?? ''));
$building = trim((string)($ticket['building_name'] ?? ''));
$room = trim((string)($ticket['room_name'] ?? ''));

$deviceType = trim((string)($ticket['device_type'] ?? ''));
$deviceLabel = trim((string)($ticket['device_label'] ?? ''));
$deviceCondition = trim((string)($ticket['device_condition'] ?? ''));

$reporterName = trim((string)($ticket['reporter_name'] ?? ''));
$reporterEmail = trim((string)($ticket['reporter_email'] ?? ''));

$description = trim((string)($ticket['description'] ?? ''));

?>

<div class="admin-actions" style="margin-bottom:12px">
  <a class="btn btn-secondary" href="/admin/tickets">Back to Tickets</a>
  <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap">
    <span class="chip <?php echo $sevClass; ?>"><?php echo htmlspecialchars($sev); ?></span>
    <span class="chip"><?php echo htmlspecialchars($status); ?></span>
  </div>
</div>

<div class="admin-detail">
  <div style="display:flex; flex-direction:column; gap:18px">
    <div class="card">
      <div class="card-h">
        <h2>Issue Description</h2>
        <p><?php echo htmlspecialchars($ticketNumber !== '' ? ('Reported on ' . $reported) : ''); ?></p>
      </div>
      <div class="card-b">
        <?php if ($description === ''): ?>
          <div class="help">No description provided.</div>
        <?php else: ?>
          <div style="line-height:1.55; white-space:pre-wrap"><?php echo htmlspecialchars($description); ?></div>
        <?php endif; ?>
      </div>
    </div>

    <div class="card">
      <div class="card-h">
        <h2>Location &amp; Device Information</h2>
        <p>Where the issue was reported.</p>
      </div>
      <div class="card-b">
        <div class="kv">
          <div class="line"><span>Campus</span><span><?php echo htmlspecialchars($campus); ?></span></div>
          <div class="line"><span>Building</span><span><?php echo htmlspecialchars($building); ?></span></div>
          <div class="line"><span>Room</span><span><?php echo htmlspecialchars($room); ?></span></div>
          <div class="line"><span>Device</span><span><?php echo htmlspecialchars($deviceType . ($deviceLabel !== '' && $deviceLabel !== $deviceType ? (' — ' . $deviceLabel) : '')); ?></span></div>
          <div class="line"><span>Condition</span><span><?php echo htmlspecialchars($deviceCondition); ?></span></div>
        </div>
      </div>
    </div>

    <?php if (is_array($attachments) && count($attachments) > 0): ?>
      <div class="card">
        <div class="card-h">
          <h2>Attachments</h2>
          <p>Files uploaded with the ticket.</p>
        </div>
        <div class="card-b">
          <div class="kv">
            <?php foreach ($attachments as $a): ?>
              <?php
                $aid = (int)($a['id'] ?? 0);
                $name = (string)($a['original_name'] ?? 'attachment');
                $path = (string)($a['stored_path'] ?? '');
              ?>
              <div class="line">
                <span><?php echo htmlspecialchars($name); ?></span>
                <span>
                  <?php if ($path !== ''): ?>
                    <a class="btn btn-secondary" href="/admin/attachment?ticket=<?php echo urlencode($ticketNumber); ?>&id=<?php echo (string)$aid; ?>" target="_blank" rel="noreferrer">Open</a>
                  <?php else: ?>
                    <span class="help">Missing file</span>
                  <?php endif; ?>
                </span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <div style="display:flex; flex-direction:column; gap:18px">
    <div class="card">
      <div class="card-h">
        <h2>Reporter Information</h2>
        <p>Contact details.</p>
      </div>
      <div class="card-b">
        <div class="kv">
          <div class="line"><span>Name</span><span><?php echo htmlspecialchars($reporterName); ?></span></div>
          <div class="line"><span>Email</span><span><?php echo htmlspecialchars($reporterEmail); ?></span></div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-h">
        <h2>Assignment</h2>
        <p>Assign this ticket to IT staff.</p>
      </div>
      <div class="card-b">
        <div class="kv">
          <div class="line"><span>Assigned to</span><span><?php echo htmlspecialchars($assignedEmail !== '' ? ($assignedName !== '' ? ($assignedName . ' — ' . $assignedEmail) : $assignedEmail) : 'Not yet assigned'); ?></span></div>
        </div>

        <form method="post" action="/admin/tickets/update" style="margin-top:12px">
          <input type="hidden" name="ticket" value="<?php echo htmlspecialchars($ticketNumber); ?>" />
          <input type="hidden" name="action" value="assign" />

          <div class="field" style="margin-bottom:10px">
            <div class="label">Select IT staff</div>
            <select name="staff_email" required>
              <option value="">Select IT staff</option>
              <?php if (is_array($staff)): ?>
                <?php foreach ($staff as $s): ?>
                  <?php if (!is_array($s)) continue; ?>
                  <?php $se = trim((string)($s['email'] ?? '')); ?>
                  <?php $sn = trim((string)($s['name'] ?? '')); ?>
                  <?php if ($se === '') continue; ?>
                  <option value="<?php echo htmlspecialchars($se); ?>" <?php echo ($assignedEmail !== '' && strcasecmp($assignedEmail, $se) === 0) ? 'selected' : ''; ?>><?php echo htmlspecialchars($sn !== '' ? ($sn . ' — ' . $se) : $se); ?></option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>

          <button class="btn btn-secondary" type="submit">Reassign Ticket</button>
        </form>
      </div>
    </div>

    <div class="card">
      <div class="card-h">
        <h2>Status Management</h2>
        <p>Update the ticket status.</p>
      </div>
      <div class="card-b">
        <div class="kv" style="margin-bottom:12px">
          <div class="line"><span>Severity</span><span><span class="chip <?php echo $sevClass; ?>"><?php echo htmlspecialchars($sev); ?></span></span></div>
          <div class="line"><span>Status</span><span><span class="chip"><?php echo htmlspecialchars($status); ?></span></span></div>
        </div>

        <div style="display:flex; flex-direction:column; gap:10px">
          <form method="post" action="/admin/tickets/update">
            <input type="hidden" name="ticket" value="<?php echo htmlspecialchars($ticketNumber); ?>" />
            <input type="hidden" name="action" value="mark_in_progress" />
            <button class="btn btn-secondary" type="submit">Mark In Progress</button>
          </form>

          <form method="post" action="/admin/tickets/update">
            <input type="hidden" name="ticket" value="<?php echo htmlspecialchars($ticketNumber); ?>" />
            <input type="hidden" name="action" value="mark_resolved" />
            <button class="btn btn-secondary" type="submit">Mark as Resolved</button>
          </form>

          <form method="post" action="/admin/tickets/update">
            <input type="hidden" name="ticket" value="<?php echo htmlspecialchars($ticketNumber); ?>" />
            <input type="hidden" name="action" value="reopen" />
            <button class="btn btn-secondary" type="submit">Reopen Ticket</button>
          </form>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="card-h">
        <h2>Timeline</h2>
        <p>Key timestamps for this ticket.</p>
      </div>
      <div class="card-b">
        <div class="kv">
          <div class="line"><span>Created</span><span><?php echo htmlspecialchars($createdAt); ?></span></div>
          <div class="line"><span>Last Updated</span><span><?php echo htmlspecialchars($updatedAt !== '' ? $updatedAt : $createdAt); ?></span></div>
          <?php if ($resolvedAt !== ''): ?>
            <div class="line"><span>Resolved</span><span><?php echo htmlspecialchars($resolvedAt); ?></span></div>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>
