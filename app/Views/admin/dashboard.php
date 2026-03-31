<?php

declare(strict_types=1);

$stats = $stats ?? ['total' => 0, 'critical' => 0, 'pending' => 0, 'resolved' => 0];
$severityCounts = $severityCounts ?? ['Critical' => 0, 'High' => 0, 'Medium' => 0, 'Low' => 0];
$statusCounts = $statusCounts ?? ['Open' => 0, 'In Progress' => 0, 'Resolved' => 0];
$recent = $recent ?? [];

$total = (int)($stats['total'] ?? 0);

$severityTotal = array_sum(array_map('intval', $severityCounts));
$statusTotal = array_sum(array_map('intval', $statusCounts));

$sevColor = [
    'Critical' => 'rgba(180,35,24,.95)',
    'High' => 'rgba(240,140,0,.95)',
    'Medium' => 'rgba(200,163,74,.95)',
    'Low' => 'rgba(20,140,60,.95)',
];

?>

<div class="dash-grid">
  <div class="stat b1">
    <div class="k">Total Tickets</div>
    <div class="v"><?php echo (int)($stats['total'] ?? 0); ?></div>
    <div class="s">All time</div>
  </div>
  <div class="stat b2">
    <div class="k">Critical Issues</div>
    <div class="v"><?php echo (int)($stats['critical'] ?? 0); ?></div>
    <div class="s">Require immediate attention</div>
  </div>
  <div class="stat b3">
    <div class="k">Pending Issues</div>
    <div class="v"><?php echo (int)($stats['pending'] ?? 0); ?></div>
    <div class="s">Open or in progress</div>
  </div>
  <div class="stat b4">
    <div class="k">Resolved Issues</div>
    <div class="v"><?php echo (int)($stats['resolved'] ?? 0); ?></div>
    <div class="s"><?php echo $total > 0 ? (int)round(((int)($stats['resolved'] ?? 0) / $total) * 100) : 0; ?>% resolution rate</div>
  </div>
</div>

<div class="grid" style="margin-top:18px">
  <div class="card">
    <div class="card-h">
      <h2>Issues by Severity</h2>
      <p>Distribution by severity level.</p>
    </div>
    <div class="card-b">
      <?php foreach (['Critical','High','Medium','Low'] as $k): ?>
        <?php $c = (int)($severityCounts[$k] ?? 0); ?>
        <?php $pct = $severityTotal > 0 ? ($c / $severityTotal) * 100 : 0; ?>
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px">
          <span class="chip <?php echo $k === 'Critical' ? 'crit' : ($k === 'High' ? 'high' : ($k === 'Medium' ? 'med' : 'low')); ?>"><?php echo htmlspecialchars($k); ?></span>
          <span style="font-size:13px; color:var(--text)"><?php echo $c; ?></span>
        </div>
        <div class="bar" style="margin-bottom:14px">
          <span style="width:<?php echo (string)$pct; ?>%; background:<?php echo $sevColor[$k]; ?>"></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-h">
      <h2>Issues by Status</h2>
      <p>Current operational status.</p>
    </div>
    <div class="card-b">
      <?php foreach (['Open','In Progress','Resolved'] as $k): ?>
        <?php $c = (int)($statusCounts[$k] ?? 0); ?>
        <?php $pct = $statusTotal > 0 ? ($c / $statusTotal) * 100 : 0; ?>
        <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px">
          <span class="chip"><?php echo htmlspecialchars($k); ?></span>
          <span style="font-size:13px; color:var(--text)"><?php echo $c; ?></span>
        </div>
        <div class="bar" style="margin-bottom:14px">
          <span style="width:<?php echo (string)$pct; ?>%; background: rgba(45,93,255,.85)"></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<div class="card" style="margin-top:18px">
  <div class="card-h" style="display:flex; align-items:center; justify-content:space-between; gap:12px">
    <div>
      <h2>Recent Tickets</h2>
      <p>Latest submissions (most recent first).</p>
    </div>
    <a class="btn btn-secondary" href="/admin/tickets">View All Tickets</a>
  </div>
  <div class="card-b" style="padding-top:10px">
    <table class="table">
      <thead>
        <tr>
          <th>Ticket ID</th>
          <th>Location</th>
          <th>Device</th>
          <th>Severity</th>
          <th>Status</th>
          <th>Reported</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!is_array($recent) || count($recent) === 0): ?>
          <tr>
            <td colspan="6" style="background:transparent; border:0; padding:10px 2px; color:var(--muted)">No tickets yet.</td>
          </tr>
        <?php else: ?>
          <?php foreach ($recent as $t): ?>
            <?php
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
              $reported = (string)($t['created_at'] ?? '');
            ?>
            <tr>
              <td>
                <a href="/admin/tickets/view?ticket=<?php echo urlencode((string)($t['ticket_number'] ?? '')); ?>" style="text-decoration:none; color:inherit">
                  <strong><?php echo htmlspecialchars((string)($t['ticket_number'] ?? '')); ?></strong>
                </a>
              </td>
              <td><?php echo htmlspecialchars($location); ?></td>
              <td><?php echo htmlspecialchars($device); ?></td>
              <td><span class="chip <?php echo $sevClass; ?>"><?php echo htmlspecialchars($sev); ?></span></td>
              <td><span class="chip"><?php echo htmlspecialchars((string)($t['status'] ?? '')); ?></span></td>
              <td><?php echo htmlspecialchars($reported); ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
