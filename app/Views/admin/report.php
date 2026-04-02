<?php

declare(strict_types=1);

$period = (string)($period ?? 'weekly');
$periodLabel = (string)($periodLabel ?? 'Weekly');
$start = (string)($start ?? '');
$end = (string)($end ?? '');

$stats = is_array($stats ?? null) ? $stats : [];
$severityCounts = is_array($severityCounts ?? null) ? $severityCounts : ['Critical' => 0, 'High' => 0, 'Medium' => 0, 'Low' => 0];
$workload = is_array($workload ?? null) ? $workload : [];
$oldestOpen = is_array($oldestOpen ?? null) ? $oldestOpen : [];
$criticalOpen = is_array($criticalOpen ?? null) ? $criticalOpen : [];

$created = (int)($stats['created'] ?? 0);
$resolved = (int)($stats['resolved'] ?? 0);
$openAsOfEnd = (int)($stats['open_as_of_end'] ?? 0);
$criticalOpenAsOfEnd = (int)($stats['critical_open_as_of_end'] ?? 0);
$aging7 = (int)($stats['aging_7'] ?? 0);
$aging14 = (int)($stats['aging_14'] ?? 0);

$sevTotal = array_sum(array_map('intval', $severityCounts));

function fmtDateYmd(string $dt): string {
    if ($dt === '') return '';
    $d = substr($dt, 0, 10);
    return preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) ? $d : '';
}

$startYmd = fmtDateYmd($start);
$endYmd = fmtDateYmd($end);

$generatedAt = date('Y-m-d H:i:s');

?>

<div class="report-page">
  <div class="admin-shell">
    <div class="card admin-menu">
      <div class="card-h">
        <h2>Menu</h2>
        <p>Quick access</p>
      </div>
      <div class="card-b">
        <a class="admin-menu-link" href="/admin">Admin Dashboard</a>
        <a class="admin-menu-link" href="/admin/tickets">Ticket Management</a>
        <a class="admin-menu-link" href="/admin/staff">Staff Overview</a>
        <a class="admin-menu-link" href="/admin/report">Management Report</a>
        <a class="admin-menu-link" href="/logout">Logout</a>
      </div>
    </div>

    <div>
      <div class="card report-controls" style="margin-bottom:18px">
        <div class="card-h" style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap">
          <div>
            <h2>Management Report</h2>
            <p>Generate a weekly / bi-weekly report for IT leadership review.</p>
          </div>
          <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap">
            <button class="btn btn-secondary" type="button" onclick="window.print()">Print / Save as PDF</button>
          </div>
        </div>
        <div class="card-b" style="padding-top:12px">
          <form method="get" action="/admin/report" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap">
            <div class="field" style="margin:0; min-width:220px">
              <div class="label">Period</div>
              <select class="input" name="period" id="report_period">
                <option value="weekly" <?php echo $period === 'weekly' ? 'selected' : ''; ?>>Weekly (last 7 days)</option>
                <option value="biweekly" <?php echo $period === 'biweekly' ? 'selected' : ''; ?>>Bi-weekly (last 14 days)</option>
                <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom range</option>
              </select>
            </div>

            <div class="field" id="report_start_wrap" style="margin:0; min-width:180px; <?php echo $period === 'custom' ? '' : 'display:none'; ?>">
              <div class="label">Start date</div>
              <input class="input" type="date" name="start" value="<?php echo htmlspecialchars($startYmd); ?>" />
            </div>

            <div class="field" id="report_end_wrap" style="margin:0; min-width:180px; <?php echo $period === 'custom' ? '' : 'display:none'; ?>">
              <div class="label">End date</div>
              <input class="input" type="date" name="end" value="<?php echo htmlspecialchars($endYmd); ?>" />
            </div>

            <button class="btn" type="submit">Generate Report</button>
          </form>
        </div>
      </div>

      <div class="card" style="margin-bottom:18px">
        <div class="card-h" style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap">
          <div>
            <h2><?php echo htmlspecialchars($periodLabel); ?> Report</h2>
            <p>Period: <?php echo htmlspecialchars($start); ?> to <?php echo htmlspecialchars($end); ?></p>
          </div>
          <div class="pill">Generated: <?php echo htmlspecialchars($generatedAt); ?></div>
        </div>
        <div class="card-b" style="padding-top:12px">
          <div class="dash-grid" style="margin-bottom:16px">
            <div class="stat b1"><div class="k">Tickets Created</div><div class="v"><?php echo $created; ?></div><div class="s">In period</div></div>
            <div class="stat b4"><div class="k">Tickets Resolved</div><div class="v"><?php echo $resolved; ?></div><div class="s">In period</div></div>
            <div class="stat b3"><div class="k">Open (as of end)</div><div class="v"><?php echo $openAsOfEnd; ?></div><div class="s">Backlog snapshot</div></div>
            <div class="stat b2"><div class="k">Critical Open (as of end)</div><div class="v"><?php echo $criticalOpenAsOfEnd; ?></div><div class="s">Urgent attention</div></div>
          </div>

          <div class="dash-grid" style="grid-template-columns:repeat(2, 1fr)">
            <div class="stat"><div class="k">Aging backlog</div><div class="v"><?php echo $aging7; ?></div><div class="s">Open &gt; 7 days (as of end)</div></div>
            <div class="stat"><div class="k">Aging backlog</div><div class="v"><?php echo $aging14; ?></div><div class="s">Open &gt; 14 days (as of end)</div></div>
          </div>
        </div>
      </div>

      <div class="grid" style="margin-top:0">
        <div class="card">
          <div class="card-h">
            <h2>Severity Distribution (Created in Period)</h2>
            <p>New tickets by severity level.</p>
          </div>
          <div class="card-b">
            <?php foreach (['Critical','High','Medium','Low'] as $k): ?>
              <?php $c = (int)($severityCounts[$k] ?? 0); ?>
              <?php $pct = $sevTotal > 0 ? ($c / $sevTotal) * 100 : 0; ?>
              <div style="display:flex; align-items:center; justify-content:space-between; gap:12px; margin-bottom:10px">
                <span class="chip <?php echo $k === 'Critical' ? 'crit' : ($k === 'High' ? 'high' : ($k === 'Medium' ? 'med' : 'low')); ?>"><?php echo htmlspecialchars($k); ?></span>
                <span style="font-size:13px; color:var(--text)"><?php echo $c; ?></span>
              </div>
              <div class="bar" style="margin-bottom:14px">
                <span style="width:<?php echo (string)$pct; ?>%; background: rgba(45,93,255,.85)"></span>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="card">
          <div class="card-h">
            <h2>Staff Workload (Open as of End)</h2>
            <p>Tickets assigned to each IT staff member.</p>
          </div>
          <div class="card-b" style="padding-top:10px">
            <table class="table">
              <thead>
                <tr>
                  <th>Staff</th>
                  <th>Total</th>
                  <th>Open</th>
                  <th>In Progress</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!is_array($workload) || count($workload) === 0): ?>
                  <tr>
                    <td colspan="4" style="background:transparent; border:0; padding:10px 2px; color:var(--muted)">No assigned tickets found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($workload as $w): ?>
                    <?php
                      if (!is_array($w)) continue;
                      $name = trim((string)($w['assigned_to_name'] ?? ''));
                      $email = trim((string)($w['assigned_to_email'] ?? ''));
                      $label = trim($name . ($email !== '' ? (' — ' . $email) : ''));
                      $total = (int)($w['total'] ?? 0);
                      $open = (int)($w['open_c'] ?? 0);
                      $prog = (int)($w['prog_c'] ?? 0);
                    ?>
                    <tr>
                      <td><?php echo htmlspecialchars($label !== '' ? $label : 'Unassigned'); ?></td>
                      <td><?php echo $total; ?></td>
                      <td><?php echo $open; ?></td>
                      <td><?php echo $prog; ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="grid" style="margin-top:18px">
        <div class="card">
          <div class="card-h">
            <h2>Oldest Open Tickets (as of End)</h2>
            <p>These tickets have been open the longest.</p>
          </div>
          <div class="card-b" style="padding-top:10px">
            <table class="table">
              <thead>
                <tr>
                  <th>Ticket</th>
                  <th>Severity</th>
                  <th>Status</th>
                  <th>Assigned</th>
                  <th>Reported</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!is_array($oldestOpen) || count($oldestOpen) === 0): ?>
                  <tr>
                    <td colspan="5" style="background:transparent; border:0; padding:10px 2px; color:var(--muted)">No open tickets found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($oldestOpen as $t): ?>
                    <?php
                      if (!is_array($t)) continue;
                      $tn = (string)($t['ticket_number'] ?? '');
                      $sev = (string)($t['severity'] ?? '');
                      $st = (string)($t['status'] ?? '');
                      $sevClass = $sev === 'Critical' ? 'crit' : ($sev === 'High' ? 'high' : ($sev === 'Medium' ? 'med' : ($sev === 'Low' ? 'low' : '')));
                      $assn = trim((string)($t['assigned_to_name'] ?? ''));
                      $asse = trim((string)($t['assigned_to_email'] ?? ''));
                      $ass = $asse !== '' ? ($assn !== '' ? ($assn . ' — ' . $asse) : $asse) : 'Unassigned';
                      $rep = (string)($t['created_at'] ?? '');
                    ?>
                    <tr>
                      <td><a href="/admin/tickets/view?ticket=<?php echo urlencode($tn); ?>" style="text-decoration:none; color:inherit"><strong><?php echo htmlspecialchars($tn); ?></strong></a></td>
                      <td><span class="chip <?php echo $sevClass; ?>"><?php echo htmlspecialchars($sev); ?></span></td>
                      <td><span class="chip"><?php echo htmlspecialchars($st); ?></span></td>
                      <td><?php echo htmlspecialchars($ass); ?></td>
                      <td><?php echo htmlspecialchars($rep); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>

        <div class="card">
          <div class="card-h">
            <h2>Critical Open Tickets (as of End)</h2>
            <p>Critical issues requiring immediate action.</p>
          </div>
          <div class="card-b" style="padding-top:10px">
            <table class="table">
              <thead>
                <tr>
                  <th>Ticket</th>
                  <th>Status</th>
                  <th>Assigned</th>
                  <th>Reported</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!is_array($criticalOpen) || count($criticalOpen) === 0): ?>
                  <tr>
                    <td colspan="4" style="background:transparent; border:0; padding:10px 2px; color:var(--muted)">No critical open tickets found.</td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($criticalOpen as $t): ?>
                    <?php
                      if (!is_array($t)) continue;
                      $tn = (string)($t['ticket_number'] ?? '');
                      $st = (string)($t['status'] ?? '');
                      $assn = trim((string)($t['assigned_to_name'] ?? ''));
                      $asse = trim((string)($t['assigned_to_email'] ?? ''));
                      $ass = $asse !== '' ? ($assn !== '' ? ($assn . ' — ' . $asse) : $asse) : 'Unassigned';
                      $rep = (string)($t['created_at'] ?? '');
                    ?>
                    <tr>
                      <td><a href="/admin/tickets/view?ticket=<?php echo urlencode($tn); ?>" style="text-decoration:none; color:inherit"><strong><?php echo htmlspecialchars($tn); ?></strong></a></td>
                      <td><span class="chip"><?php echo htmlspecialchars($st); ?></span></td>
                      <td><?php echo htmlspecialchars($ass); ?></td>
                      <td><?php echo htmlspecialchars($rep); ?></td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="card" style="margin-top:18px">
        <div class="card-h">
          <h2>Notes / Actions</h2>
          <p>Optional: capture decisions, escalations, and follow-ups.</p>
        </div>
        <div class="card-b">
          <div class="kv">
            <div class="line"><span>Escalations</span><span>______________________________</span></div>
            <div class="line"><span>Key Risks</span><span>______________________________</span></div>
            <div class="line"><span>Action Items</span><span>______________________________</span></div>
          </div>
        </div>
      </div>

    </div>
  </div>
</div>

<script>
  (function(){
    const sel = document.getElementById('report_period');
    const sw = document.getElementById('report_start_wrap');
    const ew = document.getElementById('report_end_wrap');
    if(!sel || !sw || !ew) return;

    function sync(){
      const isCustom = sel.value === 'custom';
      sw.style.display = isCustom ? '' : 'none';
      ew.style.display = isCustom ? '' : 'none';
    }

    sel.addEventListener('change', sync);
    sync();
  })();
</script>
