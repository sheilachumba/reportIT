<?php

declare(strict_types=1);

$ticket = $ticket ?? [];

?>

<div class="grid">
  <div class="card">
    <div class="card-h">
      <h2>Confirmation</h2>
      <p>Your issue has been logged successfully.</p>
    </div>
    <div class="card-b">
      <div class="alert success">
        <div style="font-weight:700; margin-bottom:6px">Ticket #<?php echo htmlspecialchars((string)($ticket['ticket_number'] ?? '')); ?></div>
        <div>Keep this number for follow-up.</div>
      </div>

      <div class="kv">
        <div class="line"><span>Severity</span><span><?php echo htmlspecialchars((string)($ticket['severity'] ?? '')); ?></span></div>
        <div class="line"><span>Condition</span><span><?php echo htmlspecialchars((string)($ticket['device_condition'] ?? '')); ?></span></div>
        <div class="line"><span>Created</span><span><?php echo htmlspecialchars((string)($ticket['created_at'] ?? '')); ?></span></div>
        <div class="line"><span>Status</span><span><?php echo htmlspecialchars((string)($ticket['status'] ?? 'Open')); ?></span></div>
      </div>

      <div style="margin-top:14px">
        <a class="btn btn-secondary" href="/issue/new">Log another issue</a>
      </div>
    </div>
  </div>
</div>
