<?php

declare(strict_types=1);

$staff = $staff ?? [];
$selectedEmail = (string)($selectedEmail ?? '');

?>

<div class="card" style="margin-bottom:18px">
  <div class="card-h" style="display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap">
    <div>
      <h2>IT Staff Overview</h2>
      <p>View all IT staff and the tickets currently allocated to them.</p>
    </div>
    <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap">
      <select id="staff_email" class="input" style="min-width:300px">
        <?php foreach ($staff as $s): ?>
          <?php
            if (!is_array($s)) continue;
            $e = trim((string)($s['email'] ?? ''));
            $n = trim((string)($s['name'] ?? ''));
            if ($e === '') continue;
          ?>
          <option value="<?php echo htmlspecialchars($e); ?>" <?php echo $selectedEmail !== '' && strcasecmp($selectedEmail, $e) === 0 ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars(($n !== '' ? $n : $e) . ' — ' . $e); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <a class="btn btn-secondary" href="/admin/tickets">Ticket Management</a>
    </div>
  </div>
</div>

<div id="staff_panel">
  <div class="alert">Select a staff member to view allocations.</div>
</div>

<script>
  (function(){
    const sel = document.getElementById('staff_email');
    const panel = document.getElementById('staff_panel');

    if(!sel || !panel) return;

    function esc(s){
      return String(s ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;','\'':'&#039;'}[c]));
    }

    function sevClass(sev){
      if(sev === 'Critical') return 'crit';
      if(sev === 'High') return 'high';
      if(sev === 'Medium') return 'med';
      if(sev === 'Low') return 'low';
      return '';
    }

    function shorten(desc){
      const maxLen = 70;
      const s = String(desc ?? '').trim();
      return s.length > maxLen ? (s.slice(0, maxLen) + '…') : s;
    }

    function render(card){
      const st = card && card.staff ? card.staff : {};
      const counts = card && card.counts ? card.counts : {};
      const tickets = Array.isArray(card && card.tickets ? card.tickets : null) ? card.tickets : [];

      const name = (st.name || st.email || 'Staff');
      const email = (st.email || '');

      const total = Number(counts.total || 0);
      const open = Number(counts.open || 0);
      const inProgress = Number(counts.in_progress || 0);
      const resolved = Number(counts.resolved || 0);

      let rows = '';
      if(tickets.length === 0){
        rows = '<tr><td colspan="6" style="background:transparent; border:0; padding:10px 2px; color:var(--muted)">No tickets assigned.</td></tr>';
      } else {
        rows = tickets.map(t => {
          const tn = esc(t.ticket_number || '');
          const desc = esc(shorten(t.description || ''));
          const sev = esc(t.severity || '');
          const stt = esc(t.status || '');
          const reported = esc(t.created_at || '');
          const cls = sevClass(t.severity || '');
          return (
            '<tr>' +
              '<td><a href="/admin/tickets/view?ticket=' + encodeURIComponent(t.ticket_number || '') + '" style="text-decoration:none; color:inherit"><strong>' + tn + '</strong></a></td>' +
              '<td>' + desc + '</td>' +
              '<td><span class="chip ' + cls + '">' + sev + '</span></td>' +
              '<td><span class="chip">' + stt + '</span></td>' +
              '<td>' + reported + '</td>' +
              '<td style="text-align:right"><a class="btn btn-secondary" style="padding:8px 10px; border-radius:12px" href="/admin/tickets/view?ticket=' + encodeURIComponent(t.ticket_number || '') + '">View Details</a></td>' +
            '</tr>'
          );
        }).join('');
      }

      panel.innerHTML = '' +
        '<div class="card" style="margin-bottom:18px">' +
          '<div class="card-h" style="display:flex; align-items:flex-start; justify-content:space-between; gap:12px; flex-wrap:wrap">' +
            '<div>' +
              '<h2 style="margin-bottom:6px">' + esc(name) + '</h2>' +
              (email ? '<div class="pill">' + esc(email) + '</div>' : '') +
            '</div>' +
          '</div>' +
          '<div class="card-b" style="padding-top:12px">' +
            '<div class="dash-grid" style="grid-template-columns:repeat(4,1fr); margin-bottom:16px">' +
              '<div class="stat b1"><div class="k">Total Assigned</div><div class="v">' + total + '</div><div class="s">All statuses</div></div>' +
              '<div class="stat b2"><div class="k">Open</div><div class="v">' + open + '</div><div class="s">Unworked</div></div>' +
              '<div class="stat b3"><div class="k">In Progress</div><div class="v">' + inProgress + '</div><div class="s">Being handled</div></div>' +
              '<div class="stat b4"><div class="k">Resolved</div><div class="v">' + resolved + '</div><div class="s">Completed</div></div>' +
            '</div>' +
            '<div class="card" style="background:transparent; box-shadow:none">' +
              '<div class="card-h"><h2>Assigned Tickets</h2><p>Most recent first.</p></div>' +
              '<div class="card-b" style="padding-top:10px">' +
                '<table class="table">' +
                  '<thead><tr><th>Ticket ID</th><th>Issue</th><th>Severity</th><th>Status</th><th>Reported</th><th></th></tr></thead>' +
                  '<tbody>' + rows + '</tbody>' +
                '</table>' +
              '</div>' +
            '</div>' +
          '</div>' +
        '</div>';
    }

    async function load(email){
      if(!email){
        panel.innerHTML = '<div class="alert">Select a staff member to view allocations.</div>';
        return;
      }
      panel.innerHTML = '<div class="alert success">Loading staff allocations...</div>';
      try{
        const res = await fetch('/admin/staff/data?email=' + encodeURIComponent(email), {headers: {'Accept':'application/json'}});
        const data = await res.json();
        if(!res.ok || !data || data.ok !== true){
          const msg = (data && data.error) ? data.error : 'Failed to load staff data.';
          panel.innerHTML = '<div class="alert">' + esc(msg) + '</div>';
          return;
        }
        render(data.data);
      }catch(e){
        panel.innerHTML = '<div class="alert">Failed to load staff data.</div>';
      }
    }

    sel.addEventListener('change', () => load(sel.value));
    load(sel.value);
  })();
</script>
