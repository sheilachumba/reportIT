<?php

declare(strict_types=1);

$tickets = $tickets ?? [];

?>

<div class="card">
  <div class="card-h" style="display:flex; align-items:center; justify-content:space-between; gap:12px">
    <div>
      <h2>All Tickets</h2>
      <p>Click a ticket to view full details.</p>
    </div>
    <div class="admin-actions">
      <a class="btn btn-secondary" href="/admin">Back to Dashboard</a>
    </div>
  </div>
  <div class="card-b" style="padding-top:10px">
    <table class="table">
      <thead>
        <tr>
          <th>Ticket ID</th>
          <th>Location</th>
          <th>Device</th>
          <th>Issue</th>
          <th>Severity</th>
          <th>Status</th>
          <th>Reported</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!is_array($tickets) || count($tickets) === 0): ?>
          <tr>
            <td colspan="8" style="background:transparent; border:0; padding:10px 2px; color:var(--muted)">No tickets yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($tickets as $t): ?>
            <?php
              $ticketNumber = (string)($t['ticket_number'] ?? '');
              $loc1 = trim((string)($t['campus_name'] ?? ''));
              $loc2 = trim((string)($t['building_name'] ?? ''));
              $loc3 = trim((string)($t['room_name'] ?? ''));
              $location = $loc1;
              if ($loc2 !== '') $location .= ($location !== '' ? ' — ' : '') . $loc2;
              if ($loc3 !== '') $location .= ($location !== '' ? ' — ' : '') . $loc3;

              $dev = trim((string)($t['device_type'] ?? ''));
              $dev2 = trim((string)($t['device_label'] ?? ''));
              $device = $dev;
              if ($dev2 !== '' && $dev2 !== $dev) $device .= ($device !== '' ? ' — ' : '') . $dev2;

              $sev = (string)($t['severity'] ?? '');
              $sevClass = $sev === 'Critical' ? 'crit' : ($sev === 'High' ? 'high' : ($sev === 'Medium' ? 'med' : ($sev === 'Low' ? 'low' : '')));
              $desc = trim((string)($t['description'] ?? ''));
              $descShort = $desc;
              if (function_exists('mb_strlen') ? mb_strlen($descShort) > 55 : strlen($descShort) > 55) {
                  $descShort = (function_exists('mb_substr') ? mb_substr($descShort, 0, 55) : substr($descShort, 0, 55)) . '...';
              }
              $reported = (string)($t['created_at'] ?? '');
            ?>
            <tr>
              <td>
                <a href="/admin/tickets/view?ticket=<?php echo urlencode($ticketNumber); ?>" style="text-decoration:none; color:inherit">
                  <strong><?php echo htmlspecialchars($ticketNumber); ?></strong>
                </a>
              </td>
              <td><?php echo htmlspecialchars($location); ?></td>
              <td><?php echo htmlspecialchars($device); ?></td>
              <td><?php echo htmlspecialchars($descShort); ?></td>
              <td><span class="chip <?php echo $sevClass; ?>"><?php echo htmlspecialchars($sev); ?></span></td>
              <td><span class="chip"><?php echo htmlspecialchars((string)($t['status'] ?? '')); ?></span></td>
              <td><?php echo htmlspecialchars($reported); ?></td>
              <td>
                <a class="btn btn-secondary" href="/admin/tickets/view?ticket=<?php echo urlencode($ticketNumber); ?>">View Details</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
