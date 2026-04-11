/* ════════════════════════════════════════════════════════
   manage.js  –  DepEd LRMDS Admin Panel
   Single consolidated file. No inline scripts needed.
════════════════════════════════════════════════════════ */

/* ════════════════════════════
   STATIC DATA
════════════════════════════ */
const RESOURCES = [
  { title:"SLM – Mathematics 6: Fractions",  type:"SLM",        grade:"6",  subject:"Mathematics", melc:"M6NS-Ia-1",      status:"published", dl:1820, updated:"Jan 10, 2026" },
  { title:"Teacher's Guide – English 10",     type:"TG",         grade:"10", subject:"English",     melc:"EN10RC-Ic-4",    status:"published", dl:1340, updated:"Jan 8, 2026"  },
  { title:"DLL – Science 8 Q1 W2",            type:"DLL",        grade:"8",  subject:"Science",     melc:"S8LT-Ib-2",      status:"review",    dl:530,  updated:"Jan 5, 2026"  },
  { title:"SLM – English 3: Reading Comp.",   type:"SLM",        grade:"3",  subject:"English",     melc:"EN3RC-Ia-2",     status:"published", dl:760,  updated:"Dec 20, 2025" },
  { title:"DLP – English 6: Figurative Lang.",type:"DLP",        grade:"6",  subject:"English",     melc:"EN6VC-Ia-1",     status:"review",    dl:340,  updated:"Dec 15, 2025" },
  { title:"Video – Science 9: Mitosis",       type:"Video",      grade:"9",  subject:"Science",     melc:"S9LT-IIa-b-1",   status:"published", dl:1100, updated:"Dec 12, 2025" },
  { title:"SLM – Filipino 5: Pabula",         type:"SLM",        grade:"5",  subject:"Filipino",    melc:"F5PT-Ia-1",      status:"published", dl:670,  updated:"Dec 10, 2025" },
  { title:"Assessment – AP 7",                type:"Assessment", grade:"7",  subject:"AP",          melc:"AP7KSA-Id-5",    status:"review",    dl:310,  updated:"Dec 5, 2025"  },
  { title:"LM – SHS Oral Communication",      type:"LM",         grade:"11", subject:"SHS Core",    melc:"EN11/12OC-Ia-1", status:"published", dl:950,  updated:"Nov 28, 2025" },
  { title:"SLM – SHS Earth Science (STEM)",   type:"SLM",        grade:"11", subject:"SHS Core",    melc:"ES11-Ia-1",      status:"published", dl:820,  updated:"Nov 20, 2025" },
  { title:"SLM – Math 4 (SY 2021)",           type:"SLM",        grade:"4",  subject:"Mathematics", melc:"M4NS-IIa-1",     status:"archived",  dl:300,  updated:"Jun 1, 2021"  },
  { title:"Worksheet – Math 3: Add. & Sub.",  type:"Worksheet",  grade:"3",  subject:"Mathematics", melc:"M3NS-Ia-2",      status:"published", dl:740,  updated:"Nov 15, 2025" },
];

const FEED_ITEMS = [
  { color:"#0B4F9C", initials:"QA", text:"<strong>QA Review completed</strong> for 'TG – English 10' — <span style='color:var(--green)'>Passed ✓</span>", time:"2 hours ago", tag:"QA", tagClass:"chip-green" },
  { color:"#F59E0B", initials:"SY", text:"<strong>New submission</strong> by C. Dela Cruz — 'SLM – Math 6: Fractions' awaiting review.", time:"3 hours ago", tag:"Pipeline", tagClass:"chip-yellow" },
  { color:"#C62828", initials:"⚠", text:"<strong>Overdue QA</strong>: 'DLP – English 6' has been in review for <strong>18 days</strong>.", time:"5 hours ago", tag:"Alert", tagClass:"chip-red" },
  { color:"#047857", initials:"DL", text:"<strong>Download milestone</strong> — 'Video – Science 9: Mitosis' hit 1,000 downloads.", time:"Yesterday", tag:"Analytics", tagClass:"chip-green" },
  { color:"#7C3AED", initials:"SY", text:"<strong>System update</strong>: New MELCs-aligned filter added to search.", time:"Yesterday", tag:"System", tagClass:"chip-purple" },
  { color:"#0B4F9C", initials:"QA", text:"<strong>QA Review completed</strong> for 'SLM – Filipino 5: Pabula' — <span style='color:var(--green)'>Passed ✓</span>", time:"2 days ago", tag:"QA", tagClass:"chip-green" },
  { color:"#F59E0B", initials:"MA", text:"<strong>New submission</strong> by A. Morales — 'LM – SHS Oral Communication' queued.", time:"2 days ago", tag:"Pipeline", tagClass:"chip-yellow" },
  { color:"#0B4F9C", initials:"AD", text:"<strong>Admin notice</strong>: Quarterly inventory deadline is March 31, 2026.", time:"3 days ago", tag:"Notice", tagClass:"chip-blue" },
];

const ROLE_COLORS = {
  teacher:      '#0B4F9C',
  learner:      '#047857',
  parent:       '#7C3AED',
  'school-head':'#C62828',
  developer:    '#F59E0B',
  admin:        '#0F172A',
  guest:        '#94A3B8',
};
const ROLE_LABELS = {
  teacher:      'Teacher',
  learner:      'Learner',
  parent:       'Parent',
  'school-head':'School Head',
  developer:    'Developer',
  admin:        'Admin',
  guest:        'Guest',
};

// Roles that require TOTP — must mirror users_handler.php / signin_handler.php
const TOTP_ROLES    = ['teacher', 'school-head', 'developer', 'admin'];
const NO_TOTP_ROLES = ['guest', 'learner', 'parent'];

/* ════════════════════════════
   ROLE-BASED ACCESS HELPER
════════════════════════════ */
function canEditUser(u) {
  if (typeof CURRENT_USER_ROLE === 'undefined') return false;
  if (CURRENT_USER_ROLE === 'admin') return true;
  if (CURRENT_USER_ROLE === 'school-head' && u.role === 'teacher') return true;
  return false;
}

/* ════════════════════════════
   PANEL SWITCHING
════════════════════════════ */
const PANELS = ['dashboard','pipeline','resources','analytics','qa','notifications','users'];
const PANEL_TITLES = {
  dashboard:     ['Dashboard',           'Carcar City Division — SY 2025–2026'],
  pipeline:      ['Submission Pipeline', '7 items need action'],
  resources:     ['Resource Manager',   '1,284 total resources'],
  analytics:     ['Analytics',          'Downloads, search & engagement metrics'],
  qa:            ['QA Tools',           'Quality Assurance Office'],
  notifications: ['Notifications',      '3 unread'],
  users:         ['User Management',    'Pending approvals & account directory'],
};

function showPanel(name) {
  PANELS.forEach(p => {
    const el = document.getElementById('panel-' + p);
    if (el) el.style.display = p === name ? '' : 'none';
  });
  document.querySelectorAll('.nav-item').forEach(btn => {
    btn.classList.toggle('active', btn.getAttribute('onclick') === `showPanel('${name}')`);
  });
  const [title, sub] = PANEL_TITLES[name] || [name, ''];
  document.getElementById('topbar-title').textContent = title;
  document.getElementById('topbar-sub').textContent   = sub;

  if (name === 'analytics') initAnalyticsCharts();
  if (name === 'users')     umInit();
}

/* ════════════════════════════
   RESOURCE TABLE
════════════════════════════ */
function renderTable(data) {
  document.getElementById('res-tbody').innerHTML = data.map(r => `
    <tr>
      <td><div class="resource-title">${r.title}</div><div class="resource-meta">${r.melc}</div></td>
      <td><span class="chip chip-gray">${r.type}</span></td>
      <td>Grade ${r.grade}</td>
      <td>${r.subject}</td>
      <td><code style="font-family:var(--mono);font-size:11px;background:var(--bg);padding:2px 5px;border-radius:4px">${r.melc}</code></td>
      <td><span class="status-badge ${r.status}"><span class="dot"></span>${r.status.charAt(0).toUpperCase()+r.status.slice(1)}</span></td>
      <td>${r.dl.toLocaleString()}</td>
      <td style="color:var(--muted);font-size:12px">${r.updated}</td>
      <td><div class="action-row"><button class="tbl-btn primary">View</button><button class="tbl-btn">Edit</button></div></td>
    </tr>`).join('');
}

function filterTable(q) {
  const query = (q || '').toLowerCase();
  renderTable(query
    ? RESOURCES.filter(r =>
        r.title.toLowerCase().includes(query) ||
        r.subject.toLowerCase().includes(query) ||
        r.melc.toLowerCase().includes(query))
    : RESOURCES);
}

/* ════════════════════════════
   ACTIVITY FEED
════════════════════════════ */
function renderFeed(containerId, limit) {
  const items = limit ? FEED_ITEMS.slice(0, limit) : FEED_ITEMS;
  document.getElementById(containerId).innerHTML = items.map(f => `
    <div class="feed-item">
      <div class="feed-avatar" style="background:${f.color}">${f.initials}</div>
      <div class="feed-body"><div class="feed-text">${f.text}</div><div class="feed-time">${f.time}</div></div>
      <span class="chip ${f.tagClass}">${f.tag}</span>
    </div>`).join('');
}

/* ════════════════════════════
   CHARTS
════════════════════════════ */
let dlChart, analyticsInited = false;

const DL_DATA = {
  '7d':  { labels:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'], data:[820,1040,760,1180,1390,980,1120] },
  '30d': { labels:['W1','W2','W3','W4'],                       data:[6200,7100,7800,7300] },
  '90d': { labels:['Jan','Feb','Mar'],                          data:[18400,21200,24800] },
};

function initDlChart() {
  const ctx = document.getElementById('dlChart').getContext('2d');
  const d   = DL_DATA['7d'];
  dlChart   = new Chart(ctx, {
    type: 'line',
    data: { labels: d.labels, datasets: [{ label:'Downloads', data: d.data, borderColor:'#0B4F9C', backgroundColor:'rgba(11,79,156,.08)', borderWidth:2, pointBackgroundColor:'#0B4F9C', pointRadius:4, tension:.4, fill:true }] },
    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ x:{ grid:{ display:false }, ticks:{ font:{ size:11 }, color:'#94A3B8' } }, y:{ grid:{ color:'#F1F5F9' }, ticks:{ font:{ size:11 }, color:'#94A3B8' } } } }
  });
}

function switchChartPeriod(btn, period) {
  document.querySelectorAll('.tabs .tab').forEach(t => t.classList.remove('active'));
  btn.classList.add('active');
  const d = DL_DATA[period];
  dlChart.data.labels = d.labels;
  dlChart.data.datasets[0].data = d.data;
  dlChart.update();
}

function initSubjectChart() {
  new Chart(document.getElementById('subjectChart').getContext('2d'), {
    type: 'bar',
    data: { labels:['English','Filipino','Math','Science','AP','MAPEH','EsP','SHS'], datasets:[{ data:[220,185,240,195,130,90,75,149], backgroundColor:['#3B82F6','#10B981','#F59E0B','#8B5CF6','#EF4444','#06B6D4','#F97316','#6366F1'], borderRadius:6 }] },
    options: { responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ x:{ grid:{ display:false }, ticks:{ font:{ size:10 }, color:'#94A3B8' } }, y:{ grid:{ color:'#F1F5F9' }, ticks:{ font:{ size:11 }, color:'#94A3B8' } } } }
  });
}

function initAnalyticsCharts() {
  if (analyticsInited) return;
  analyticsInited = true;
  new Chart(document.getElementById('typeChart').getContext('2d'), {
    type:'bar', data:{ labels:['SLM','Video','TG','DLL/DLP','Assessment','LM','Worksheet','Other'], datasets:[{ label:'Downloads', data:[9800,6200,4500,3100,2400,1900,1300,900], backgroundColor:'rgba(11,79,156,.75)', borderRadius:6 }] },
    options:{ indexAxis:'y', responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ x:{ grid:{ color:'#F1F5F9' }, ticks:{ font:{ size:11 }, color:'#94A3B8' } }, y:{ grid:{ display:false }, ticks:{ font:{ size:11 }, color:'#94A3B8' } } } }
  });
  new Chart(document.getElementById('funnelChart').getContext('2d'), {
    type:'bar', data:{ labels:['Submissions','Under QA','QA Passed','Published','Active Users'], datasets:[{ data:[186,47,139,1051,680], backgroundColor:['#60A5FA','#F59E0B','#10B981','#0B4F9C','#8B5CF6'], borderRadius:6 }] },
    options:{ responsive:true, maintainAspectRatio:false, plugins:{ legend:{ display:false } }, scales:{ x:{ grid:{ display:false }, ticks:{ font:{ size:11 }, color:'#94A3B8' } }, y:{ grid:{ color:'#F1F5F9' }, ticks:{ font:{ size:11 }, color:'#94A3B8' } } } }
  });
}

/* ════════════════════════════
   UTILITY
════════════════════════════ */
function escHtml(s) {
  return String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

let toastTimer;
function umToast(msg, type = 'success') {
  const el  = document.getElementById('um-toast');
  const ico = document.getElementById('toast-icon');
  el.className = 'show ' + type;
  document.getElementById('um-toast-msg').textContent = msg;
  ico.innerHTML = type === 'success'
    ? '<path d="M20 6 9 17l-5-5"/>'
    : '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>';
  clearTimeout(toastTimer);
  toastTimer = setTimeout(() => el.classList.remove('show'), 3200);
}

function umSkeleton(n) {
  return '<div class="um-loading">' + Array(n).fill('<div class="um-skeleton"></div>').join('') + '</div>';
}
function umEmpty(title, sub) {
  return `<div class="um-empty">
    <svg width="40" height="40" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
    <div class="um-empty-title">${escHtml(title)}</div>
    <div class="um-empty-sub">${escHtml(sub)}</div>
  </div>`;
}
function umError(msg) {
  return `<div class="um-empty"><div class="um-empty-title" style="color:var(--red)">Error</div><div class="um-empty-sub">${escHtml(msg)}</div></div>`;
}

/* ════════════════════════════
   USER MANAGEMENT
════════════════════════════ */
let umCurrentTab    = 'pending';
let umRejectPending = null;
let umInited        = false;

function umInit() {
  if (umInited) return;
  umInited = true;
  umRefresh();
}

function umRefresh() {
  umLoadPending();
  umLoadUsers();
  umLoadStats();
}

function umSwitchTab(tab) {
  umCurrentTab = tab;
  document.getElementById('um-tab-pending').classList.toggle('active', tab === 'pending');
  document.getElementById('um-tab-all').classList.toggle('active',     tab === 'all');
  document.getElementById('um-section-pending').classList.toggle('active', tab === 'pending');
  document.getElementById('um-section-all').classList.toggle('active',     tab === 'all');
}

/* ── Stats ── */
async function umLoadStats() {
  try {
    const r = await fetch('users_handler.php?action=list_users');
    const d = await r.json();
    if (!d.ok) return;
    const users     = d.data;
    const total     = users.length;
    const active    = users.filter(u => u.status === 'active').length;
    const pending   = users.filter(u => u.status === 'pending').length;
    const suspended = users.filter(u => u.status === 'suspended').length;
    const guests    = users.filter(u => u.role   === 'guest').length;

    document.getElementById('stat-total').textContent     = total;
    document.getElementById('stat-active').textContent    = active;
    document.getElementById('stat-pending').textContent   = pending;
    document.getElementById('stat-suspended').textContent = suspended;
    document.getElementById('stat-guest').textContent     = guests;

    const navBadge = document.getElementById('pending-nav-badge');
    navBadge.textContent   = pending;
    navBadge.style.display = pending > 0 ? '' : 'none';
  } catch (e) { /* silent */ }
}

/* ── Pending list ── */
async function umLoadPending() {
  const search = document.getElementById('pending-search')?.value ?? '';
  const role   = document.getElementById('pending-role-filter')?.value ?? '';
  const params = new URLSearchParams({ action:'list_pending', search, role });
  const list   = document.getElementById('pending-list');

  list.innerHTML = umSkeleton(3);

  try {
    const r = await fetch('users_handler.php?' + params);
    const d = await r.json();
    if (!d.ok) { list.innerHTML = umError(d.msg); return; }

    document.getElementById('um-pending-count').textContent      = d.count;
    document.getElementById('pending-result-count').textContent  = d.count + ' result' + (d.count !== 1 ? 's' : '');

    list.innerHTML = d.data.length === 0
      ? umEmpty('No pending applications', 'All caught up! No accounts awaiting approval.')
      : d.data.map(u => umApplicantCard(u)).join('');
  } catch (e) {
    list.innerHTML = umError('Could not load pending registrations.');
  }
}

/* ── All users table ── */
async function umLoadUsers() {
  const search = document.getElementById('users-search')?.value  ?? '';
  const role   = document.getElementById('users-role-filter')?.value   ?? '';
  const status = document.getElementById('users-status-filter')?.value ?? '';
  const params = new URLSearchParams({ action:'list_users', search, role, status });
  const tbody  = document.getElementById('users-tbody');

  tbody.innerHTML = '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--muted)">Loading…</td></tr>';

  try {
    const r = await fetch('users_handler.php?' + params);
    const d = await r.json();
    if (!d.ok) {
      tbody.innerHTML = `<tr><td colspan="8" style="color:var(--red);padding:24px;text-align:center">${escHtml(d.msg)}</td></tr>`;
      return;
    }
    document.getElementById('um-all-count').textContent         = d.count;
    document.getElementById('users-result-count').textContent   = d.count + ' user' + (d.count !== 1 ? 's' : '');
    document.getElementById('um-card-sub').textContent          = d.count + ' total users';

    tbody.innerHTML = d.data.length === 0
      ? '<tr><td colspan="8" style="text-align:center;padding:32px;color:var(--muted)">No users found.</td></tr>'
      : d.data.map(u => umUserRow(u)).join('');
  } catch (e) {
    tbody.innerHTML = `<tr><td colspan="8" style="color:var(--red);padding:24px;text-align:center">Could not load users.</td></tr>`;
  }
}

/* ── Applicant card ── */
function umApplicantCard(u) {
  const name     = escHtml(u.first_name + ' ' + u.last_name);
  const email    = escHtml(u.email);
  const color    = ROLE_COLORS[u.role] || '#64748B';
  const initials = ((u.first_name[0] || '') + (u.last_name[0] || '')).toUpperCase();
  const meta     = u.meta || {};

  const chips = [`<span class="chip chip-blue">${escHtml(ROLE_LABELS[u.role] || u.role)}</span>`];
  if (u.region)         chips.push(`<span class="chip chip-gray">${escHtml(u.region)}</span>`);
  if (u.division)       chips.push(`<span class="chip chip-gray">${escHtml(u.division)}</span>`);
  if (u.employee_id)    chips.push(`<span class="chip chip-gray">ID: ${escHtml(u.employee_id)}</span>`);
  if (meta.position)    chips.push(`<span class="chip chip-purple">${escHtml(meta.position)}</span>`);
  if (meta.school_name) chips.push(`<span class="chip chip-gray">${escHtml(meta.school_name)}</span>`);

  return `
    <div class="applicant-card" id="applicant-${u.id}">
      <div class="applicant-avatar" style="background:${color}" title="View profile" onclick="vmOpen(${u.id})">${initials}</div>
      <div class="applicant-main">
        <div class="applicant-name">${name}</div>
        <div class="applicant-email">${email}</div>
        <div class="applicant-chips">${chips.join('')}</div>
        <div class="applicant-meta">Applied ${escHtml(u.created_at_human)}</div>
      </div>
      <div class="applicant-actions">
        <button class="btn-approve" onclick="umApprove(${u.id}, '${name.replace(/'/g,"\\'")}')">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg>Approve
        </button>
        <button class="btn-reject" onclick="umOpenReject(${u.id}, '${name.replace(/'/g,"\\'")}')">
          <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>Reject
        </button>
      </div>
    </div>`;
}

/* ── User table row ── */
function umUserRow(u) {
  const name   = escHtml(u.first_name + ' ' + u.last_name);
  const email  = escHtml(u.email);
  const color  = ROLE_COLORS[u.role] || '#64748B';

  const statusBadge = {
    active:    `<span class="status-badge published"><span class="dot"></span>Active</span>`,
    pending:   `<span class="status-badge review"><span class="dot"></span>Pending</span>`,
    suspended: `<span class="status-badge archived"><span class="dot"></span>Suspended</span>`,
  }[u.status] || escHtml(u.status);

  const totp = u.totp_enabled
    ? `<span style="display:inline-flex;align-items:center;gap:4px;color:var(--green);font-weight:700;font-size:12px">
         <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>On
       </span>`
    : `<span style="color:var(--muted);font-size:12px">Off</span>`;

  const statusBtn = u.status === 'active'
    ? `<button class="btn-suspend"    onclick="umSuspend(${u.id},    '${name.replace(/'/g,"\\'")}')">Suspend</button>`
    : u.status === 'suspended'
    ? `<button class="btn-reactivate" onclick="umReactivate(${u.id}, '${name.replace(/'/g,"\\'")}')">Reactivate</button>`
    : '';

  // Role select — clicking triggers the confirmation modal, not an immediate save
  const roleSelect = `
    <select class="role-select-inline" data-user-id="${u.id}" data-user-name="${escHtml(name)}" data-current-role="${escHtml(u.role)}"
            onchange="rcmOpen(this, ${u.id}, '${name.replace(/'/g,"\\'")}', '${u.role}', this.value)">
      ${['teacher','learner','parent','school-head','developer','admin','guest'].map(r =>
        `<option value="${r}" ${r === u.role ? 'selected' : ''}>${ROLE_LABELS[r] || r}</option>`
      ).join('')}
    </select>`;

  const editBtn = canEditUser(u)
    ? `<button class="tbl-btn primary" onclick="euOpen(${u.id})">Edit</button>`
    : '';

  return `
    <tr id="user-row-${u.id}">
      <td>
        <div style="display:flex;align-items:center;gap:10px">
          <div style="width:32px;height:32px;border-radius:50%;background:${color};display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;color:#fff;flex-shrink:0;cursor:pointer"
               onclick="vmOpen(${u.id})" title="View profile">
            ${((u.first_name[0]||'')+(u.last_name[0]||'')).toUpperCase()}
          </div>
          <div>
            <div class="resource-title" style="cursor:pointer" onclick="vmOpen(${u.id})">${name}</div>
            <div class="resource-meta">${email}</div>
          </div>
        </div>
      </td>
      <td>${roleSelect}</td>
      <td>${statusBadge}</td>
      <td style="font-size:12px;color:var(--muted)">${escHtml(u.region || '—')}</td>
      <td>${totp}</td>
      <td style="font-size:12px;color:var(--muted)">${escHtml(u.last_login_human)}</td>
      <td style="font-size:12px;color:var(--muted)">${escHtml(u.created_at_human)}</td>
      <td>
        <div class="action-row">
          ${editBtn}
          ${statusBtn}
        </div>
      </td>
    </tr>`;
}

/* ── Approve ── */
async function umApprove(id, name) {
  const btn = document.querySelector(`#applicant-${id} .btn-approve`);
  if (btn) { btn.disabled = true; btn.textContent = 'Approving…'; }
  try {
    const fd = new FormData();
    fd.append('action', 'approve');
    fd.append('id', id);
    const d = await (await fetch('users_handler.php', { method:'POST', body:fd })).json();
    if (d.ok) {
      const card = document.getElementById('applicant-' + id);
      if (card) { card.style.transition = 'opacity .3s,transform .3s'; card.style.opacity='0'; card.style.transform='translateX(20px)'; setTimeout(() => card.remove(), 320); }
      umToast(`${name} approved successfully.`, 'success');
      umLoadStats();
      const cnt = document.getElementById('um-pending-count');
      if (cnt) cnt.textContent = Math.max(0, parseInt(cnt.textContent||'0') - 1);
    } else {
      umToast(d.msg, 'error');
      if (btn) { btn.disabled = false; btn.innerHTML = '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M20 6 9 17l-5-5"/></svg> Approve'; }
    }
  } catch (e) {
    umToast('Server error.', 'error');
    if (btn) btn.disabled = false;
  }
}

/* ── Reject modal ── */
function umOpenReject(id, name) {
  umRejectPending = { id, name };
  document.getElementById('reject-reason').value = '';
  document.getElementById('reject-modal').classList.add('open');
}

function closeRejectModal() {
  document.getElementById('reject-modal').classList.remove('open');
  umRejectPending = null;
}

async function confirmReject() {
  if (!umRejectPending) return;
  const { id, name } = umRejectPending;
  const reason = document.getElementById('reject-reason').value.trim();
  const btn    = document.getElementById('reject-confirm-btn');
  btn.disabled = true; btn.textContent = 'Rejecting…';

  try {
    const fd = new FormData();
    fd.append('action', 'reject'); fd.append('id', id); fd.append('reason', reason);
    const d = await (await fetch('users_handler.php', { method:'POST', body:fd })).json();
    closeRejectModal();
    btn.disabled = false;
    btn.innerHTML = '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg> Reject Application';
    if (d.ok) {
      const card = document.getElementById('applicant-' + id);
      if (card) { card.style.transition='opacity .3s'; card.style.opacity='0'; setTimeout(()=>card.remove(),320); }
      umToast(`${name}'s application rejected.`, 'error');
      umLoadStats();
    } else { umToast(d.msg, 'error'); }
  } catch (e) { closeRejectModal(); umToast('Server error.', 'error'); btn.disabled = false; }
}

/* ── Suspend / reactivate ── */
async function umSuspend(id, name) {
  if (!confirm(`Suspend ${name}'s account?`)) return;
  const fd = new FormData(); fd.append('action','suspend'); fd.append('id',id);
  const d = await (await fetch('users_handler.php',{method:'POST',body:fd})).json();
  if (d.ok) { umToast(`${name} suspended.`,'error'); umLoadUsers(); umLoadStats(); }
  else umToast(d.msg,'error');
}

async function umReactivate(id, name) {
  const fd = new FormData(); fd.append('action','reactivate'); fd.append('id',id);
  const d = await (await fetch('users_handler.php',{method:'POST',body:fd})).json();
  if (d.ok) { umToast(`${name} reactivated.`,'success'); umLoadUsers(); umLoadStats(); }
  else umToast(d.msg,'error');
}

/* ════════════════════════════
   ROLE-CHANGE CONFIRMATION MODAL
════════════════════════════ */
let rcmPending = null; // { id, name, oldRole, newRole, selectEl }

function rcmOpen(selectEl, id, name, oldRole, newRole) {
  // If user picked the same role somehow (shouldn't happen but guard it)
  if (oldRole === newRole) return;

  // Store pending change — revert the select visually until confirmed
  selectEl.value = oldRole;
  rcmPending = { id, name, oldRole, newRole, selectEl };

  const oldLabel = ROLE_LABELS[oldRole] || oldRole;
  const newLabel = ROLE_LABELS[newRole] || newRole;

  // Build the impact note
  const downgradingTotp = TOTP_ROLES.includes(oldRole) && NO_TOTP_ROLES.includes(newRole);
  const upgradingTotp   = NO_TOTP_ROLES.includes(oldRole) && TOTP_ROLES.includes(newRole);

  let impactHtml = '';
  if (downgradingTotp) {
    impactHtml = `
      <div class="rcm-impact rcm-impact-warn">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <span><strong>2FA will be cleared.</strong> This role does not require Two-Factor Authentication.
        The user's authenticator secret will be wiped automatically.</span>
      </div>`;
  } else if (upgradingTotp) {
    impactHtml = `
      <div class="rcm-impact rcm-impact-info">
        <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
        <span><strong>2FA will be required.</strong> This role requires Two-Factor Authentication.
        The user will be prompted to set up their authenticator on next login.</span>
      </div>`;
  }

  document.getElementById('rcm-from-badge').textContent  = oldLabel;
  document.getElementById('rcm-to-badge').textContent    = newLabel;
  document.getElementById('rcm-from-badge').style.background = ROLE_COLORS[oldRole] || '#64748B';
  document.getElementById('rcm-to-badge').style.background   = ROLE_COLORS[newRole] || '#64748B';
  document.getElementById('rcm-user-name').textContent   = name;
  document.getElementById('rcm-impact').innerHTML         = impactHtml;

  document.getElementById('rcm-confirm-btn').disabled    = false;
  document.getElementById('rcm-confirm-btn').textContent = 'Yes, Change Role';

  document.getElementById('role-change-modal').classList.add('open');
}

function rcmClose(revert = true) {
  document.getElementById('role-change-modal').classList.remove('open');
  // If admin hit Cancel, restore the select to the old value
  if (revert && rcmPending) {
    rcmPending.selectEl.value = rcmPending.oldRole;
  }
  rcmPending = null;
}

async function rcmConfirm() {
  if (!rcmPending) return;
  const { id, name, newRole, selectEl } = rcmPending;
  const btn = document.getElementById('rcm-confirm-btn');
  btn.disabled = true;
  btn.innerHTML = '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" style="animation:eu-spin 1s linear infinite"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Saving…';

  const fd = new FormData();
  fd.append('action', 'change_role');
  fd.append('id', id);
  fd.append('role', newRole);

  try {
    const d = await (await fetch('users_handler.php', { method:'POST', body:fd })).json();
    rcmClose(false); // Don't revert — the change either went through or we'll reload anyway
    if (d.ok) {
      // Update the select to show the new role
      selectEl.value = newRole;
      selectEl.dataset.currentRole = newRole;
      umToast(d.msg || `Role updated to ${ROLE_LABELS[newRole] || newRole}.`, 'success');
      // Reload to reflect any TOTP column changes
      umLoadUsers();
      umLoadStats();
    } else {
      selectEl.value = selectEl.dataset.currentRole || selectEl.value;
      umToast(d.msg || 'Could not update role.', 'error');
    }
  } catch (e) {
    rcmClose(false);
    selectEl.value = selectEl.dataset.currentRole || selectEl.value;
    umToast('Network error. Please try again.', 'error');
  }
}

document.getElementById('role-change-modal')?.addEventListener('click', function(e) {
  if (e.target === this) rcmClose(true);
});

/* ── Close reject modal on overlay click ── */
document.getElementById('reject-modal')?.addEventListener('click', function(e) {
  if (e.target === this) closeRejectModal();
});

/* ════════════════════════════
   VIEW USER MODAL
════════════════════════════ */
let vmCurrentId = null;

async function vmOpen(id) {
  vmCurrentId = id;
  document.getElementById('vm-name').textContent   = 'Loading…';
  document.getElementById('vm-email').textContent  = '';
  document.getElementById('vm-badges').innerHTML   = '';
  document.getElementById('vm-body').innerHTML     = '<div style="padding:32px;text-align:center;color:var(--muted)"><svg width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" style="animation:eu-spin 1s linear infinite;margin:0 auto 8px;display:block"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg>Loading profile…</div>';
  document.getElementById('vm-avatar').textContent = '?';
  document.getElementById('vm-avatar').style.background = '#94A3B8';
  document.getElementById('view-modal').classList.add('open');

  try {
    const r = await fetch('users_handler.php?action=get_user&id=' + encodeURIComponent(id));
    const d = await r.json();
    if (!d.ok) {
      document.getElementById('vm-body').innerHTML = `<div style="color:var(--red);padding:20px">${escHtml(d.msg)}</div>`;
      return;
    }
    vmPopulate(d.data);
  } catch (e) {
    document.getElementById('vm-body').innerHTML = '<div style="color:var(--red);padding:20px">Network error.</div>';
  }
}

function vmPopulate(u) {
  const name     = (u.first_name + ' ' + u.last_name).trim();
  const initials = ((u.first_name[0]||'')+(u.last_name[0]||'')).toUpperCase();
  const color    = ROLE_COLORS[u.role] || '#64748B';
  const totpOn   = !!parseInt(u.totp_enabled);

  document.getElementById('vm-avatar').textContent      = initials;
  document.getElementById('vm-avatar').style.background = color;
  document.getElementById('vm-name').textContent        = name;
  document.getElementById('vm-email').textContent       = u.email;

  const statusMeta = {
    active:    { cls:'chip-green',  label:'Active' },
    pending:   { cls:'chip-yellow', label:'Pending' },
    suspended: { cls:'chip-red',    label:'Suspended' },
  }[u.status] || { cls:'chip-gray', label: u.status };

  document.getElementById('vm-badges').innerHTML = `
    <span class="chip ${statusMeta.cls}">${statusMeta.label}</span>
    <span class="chip chip-blue" style="background:${color}20;color:${color};border:1px solid ${color}40">${escHtml(ROLE_LABELS[u.role] || u.role)}</span>
    ${totpOn
      ? `<span class="chip chip-green" style="display:inline-flex;align-items:center;gap:4px">
           <svg width="10" height="10" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>2FA Active
         </span>`
      : `<span class="chip chip-gray">No 2FA</span>`
    }
  `;

  // Helper builders
  const field = (label, val, mono = false, fullWidth = false) => `
    <div ${fullWidth ? 'style="grid-column:1/-1"' : ''}>
      <div class="vm-field-label">${label}</div>
      <div class="vm-field-val ${mono ? 'mono' : ''}">${escHtml(String(val || '—'))}</div>
    </div>`;

  const sectionHeader = (title, icon) => `
    <div style="grid-column:1/-1;display:flex;align-items:center;gap:8px;margin-top:4px;padding-top:4px;border-top:1px solid var(--border)">
      <svg width="13" height="13" fill="none" stroke="var(--muted)" stroke-width="2" viewBox="0 0 24 24">${icon}</svg>
      <span style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:.07em;color:var(--muted)">${title}</span>
    </div>`;

  const totpBlock = totpOn
    ? `<div style="grid-column:1/-1">
         <div class="vm-field-label">Two-Factor Auth</div>
         <div style="display:inline-flex;align-items:center;gap:6px;background:#ECFDF5;border:1px solid #A7F3D0;border-radius:6px;padding:5px 10px;margin-top:2px">
           <svg width="13" height="13" fill="none" stroke="#047857" stroke-width="2.5" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
           <span style="font-size:12px;font-weight:700;color:#047857">Enabled — user is protected with 2FA</span>
         </div>
       </div>`
    : `<div style="grid-column:1/-1">
         <div class="vm-field-label">Two-Factor Auth</div>
         <div style="display:inline-flex;align-items:center;gap:6px;background:#F9FAFB;border:1px solid var(--border);border-radius:6px;padding:5px 10px;margin-top:2px">
           <svg width="13" height="13" fill="none" stroke="var(--muted)" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
           <span style="font-size:12px;font-weight:600;color:var(--muted)">Not set up${TOTP_ROLES.includes(u.role) ? ' — will be required on next login' : ''}</span>
         </div>
       </div>`;

  document.getElementById('vm-body').innerHTML = `
    ${sectionHeader('Identity', '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>')}
    ${field('First Name', u.first_name)}
    ${field('Last Name',  u.last_name)}
    ${field('Email Address', u.email, true, true)}
    ${field('Employee / School ID', u.employee_id || '—', true)}

    ${sectionHeader('Organization', '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>')}
    ${field('Region',   u.region   || '—')}
    ${field('Division', u.division || '—')}

    ${sectionHeader('Access', '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/>')}
    <div>
      <div class="vm-field-label">Role</div>
      <div style="display:inline-flex;align-items:center;gap:6px">
        <span style="width:8px;height:8px;border-radius:50%;background:${color};display:inline-block;flex-shrink:0"></span>
        <span class="vm-field-val">${escHtml(ROLE_LABELS[u.role] || u.role)}</span>
      </div>
    </div>
    <div>
      <div class="vm-field-label">Status</div>
      <span class="chip ${statusMeta.cls}" style="margin-top:2px;display:inline-flex">${statusMeta.label}</span>
    </div>
    ${totpBlock}

    ${sectionHeader('Activity', '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>')}
    ${field('Joined',      u.created_at_human)}
    ${field('Last Login',  u.last_login_human)}
  `;

  // Store user id on the edit button + show/hide based on permissions
  const editBtn = document.getElementById('vm-edit-btn');
  editBtn.dataset.userId = u.id;
  editBtn.style.display = canEditUser(u) ? '' : 'none';
}

function closeViewModal() {
  document.getElementById('view-modal').classList.remove('open');
  vmCurrentId = null;
}

function vmSwitchToEdit() {
  const id = document.getElementById('vm-edit-btn').dataset.userId;
  closeViewModal();
  euOpen(parseInt(id));
}

document.getElementById('view-modal')?.addEventListener('click', function(e) {
  if (e.target === this) closeViewModal();
});

/* ════════════════════════════
   EDIT USER DRAWER
════════════════════════════ */
let euCurrentUser = null;

async function euOpen(id) {
  document.getElementById('eu-overlay').classList.add('open');
  document.getElementById('eu-error').style.display   = 'none';
  document.getElementById('eu-title').textContent     = 'Loading…';
  document.getElementById('eu-sub').textContent       = '';
  document.getElementById('eu-avatar').textContent    = '…';
  document.getElementById('eu-save-btn').disabled     = true;

  try {
    const r = await fetch('users_handler.php?action=get_user&id=' + encodeURIComponent(id));
    const d = await r.json();
    if (!d.ok) { euShowError(d.msg || 'Could not load user.'); return; }
    euCurrentUser = d.data;
    euPopulate(d.data);
    document.getElementById('eu-save-btn').disabled = false;
  } catch (e) {
    euShowError('Network error. Please check the server.');
  }
}

function euPopulate(u) {
  const name     = (u.first_name + ' ' + u.last_name).trim();
  const initials = ((u.first_name[0]||'')+(u.last_name[0]||'')).toUpperCase();
  const color    = ROLE_COLORS[u.role] || '#64748B';

  document.getElementById('eu-title').textContent          = 'Edit: ' + name;
  document.getElementById('eu-sub').textContent            = u.email;
  document.getElementById('eu-avatar').textContent         = initials;
  document.getElementById('eu-avatar').style.background   = color;

  document.getElementById('eu-fname').value       = u.first_name  || '';
  document.getElementById('eu-lname').value       = u.last_name   || '';
  document.getElementById('eu-email').value       = u.email       || '';
  document.getElementById('eu-role').value        = u.role        || 'teacher';
  document.getElementById('eu-status').value      = u.status      || 'active';
  document.getElementById('eu-region').value      = u.region      || '';
  document.getElementById('eu-division').value    = u.division    || '';
  document.getElementById('eu-employee-id').value = u.employee_id || '';
  document.getElementById('eu-new-password').value = '';
  document.getElementById('eu-error').style.display = 'none';

  // Update TOTP hint
  const totpEnabled = !!parseInt(u.totp_enabled);
  document.getElementById('eu-totp-hint').textContent = totpEnabled
    ? '✓ Enabled — user must verify a code at every sign-in'
    : '✗ Not set up — account has no 2FA protection';
  const totpBtn = document.getElementById('eu-totp-btn');
  totpBtn.style.display = totpEnabled ? '' : 'none';
  totpBtn.disabled      = false;
  totpBtn.textContent   = 'Disable 2FA';

  // Update role-change hint dynamically when the role select changes
  const roleSelect = document.getElementById('eu-role');
  const roleHintEl = document.getElementById('eu-role-hint');
  const updateRoleHint = () => {
    const newRole = roleSelect.value;
    const oldRole = u.role;
    if (newRole === oldRole) {
      roleHintEl.textContent = '';
      roleHintEl.className = 'eu-hint';
    } else if (TOTP_ROLES.includes(oldRole) && NO_TOTP_ROLES.includes(newRole)) {
      roleHintEl.textContent = '⚠ Saving will clear this user\'s 2FA secret.';
      roleHintEl.className = 'eu-hint eu-hint-warn';
    } else if (NO_TOTP_ROLES.includes(oldRole) && TOTP_ROLES.includes(newRole)) {
      roleHintEl.textContent = '🔒 This role requires 2FA. User will enroll on next login.';
      roleHintEl.className = 'eu-hint eu-hint-info';
    } else {
      roleHintEl.textContent = '';
      roleHintEl.className = 'eu-hint';
    }
  };
  roleSelect.addEventListener('change', updateRoleHint);
  updateRoleHint(); // run once on populate

  /* Restrict school-head: lock Role, hide sensitive actions */
  const totpRow      = document.getElementById('eu-totp-row');
  const newPassField = document.getElementById('eu-new-password');

  if (CURRENT_USER_ROLE === 'school-head') {
    roleSelect.disabled = true;
    totpRow.style.display = 'none';
    newPassField.closest('.eu-field').style.display = 'none';
  } else {
    roleSelect.disabled = false;
    totpRow.style.display = '';
    newPassField.closest('.eu-field').style.display = '';
  }
}

async function euSave() {
  if (!euCurrentUser) return;

  const fname    = document.getElementById('eu-fname').value.trim();
  const lname    = document.getElementById('eu-lname').value.trim();
  const email    = document.getElementById('eu-email').value.trim();
  const role     = document.getElementById('eu-role').value;
  const status   = document.getElementById('eu-status').value;
  const region   = document.getElementById('eu-region').value.trim();
  const division = document.getElementById('eu-division').value.trim();
  const empId    = document.getElementById('eu-employee-id').value.trim();
  const newPass  = document.getElementById('eu-new-password').value;

  if (!fname || !lname)  { euShowError('First and last name are required.'); return; }
  if (!email)            { euShowError('Email is required.'); return; }
  if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { euShowError('Enter a valid email address.'); return; }
  if (newPass && newPass.length < 8) { euShowError('New password must be at least 8 characters.'); return; }

  const btn = document.getElementById('eu-save-btn');
  btn.disabled  = true;
  btn.innerHTML = '<svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" class="eu-spinning"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg> Saving…';

  const fd = new FormData();
  fd.append('action',      'edit_user');
  fd.append('id',          euCurrentUser.id);
  fd.append('first_name',  fname);
  fd.append('last_name',   lname);
  fd.append('email',       email);
  fd.append('role',        role);
  fd.append('status',      status);
  fd.append('region',      region);
  fd.append('division',    division);
  fd.append('employee_id', empId);
  if (newPass) fd.append('new_password', newPass);

  try {
    const r = await fetch('users_handler.php', { method:'POST', body:fd });
    const d = await r.json();
    if (d.ok) {
      euCloseDrawer();
      umToast(d.msg || 'User updated.', 'success');
      umLoadUsers();
      umLoadStats();
    } else {
      euShowError(d.msg || 'Could not save changes.');
    }
  } catch (e) {
    euShowError('Network error. Please try again.');
  } finally {
    btn.disabled  = false;
    btn.innerHTML = '<svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg> Save Changes';
  }
}

async function euDisableTotp() {
  if (!euCurrentUser) return;
  if (!confirm('Disable two-factor authentication for this user?\n\nThey will need to re-enroll on their next login.')) return;
  const btn = document.getElementById('eu-totp-btn');
  btn.disabled = true; btn.textContent = 'Disabling…';

  const fd = new FormData(); fd.append('action','disable_totp'); fd.append('id', euCurrentUser.id);
  try {
    const d = await (await fetch('users_handler.php', { method:'POST', body:fd })).json();
    if (d.ok) {
      euCurrentUser.totp_enabled = 0;
      document.getElementById('eu-totp-hint').textContent = '✗ Disabled — user must re-enroll on next login';
      btn.style.display = 'none';
      umToast('2FA disabled.', 'success');
    } else {
      euShowError(d.msg);
      btn.disabled = false; btn.textContent = 'Disable 2FA';
    }
  } catch (e) {
    euShowError('Network error.');
    btn.disabled = false; btn.textContent = 'Disable 2FA';
  }
}

async function euSendPasswordReset() {
  if (!euCurrentUser) return;
  if (!confirm(`Send a password-reset email to ${euCurrentUser.email}?`)) return;
  const fd = new FormData(); fd.append('action','send_password_reset'); fd.append('id', euCurrentUser.id);
  try {
    const d = await (await fetch('users_handler.php',{method:'POST',body:fd})).json();
    umToast(d.msg || 'Reset email sent.', d.ok ? 'success' : 'error');
  } catch (e) { umToast('Network error.','error'); }
}

function euCloseDrawer() {
  document.getElementById('eu-overlay').classList.remove('open');
  // Remove the dynamic change listener to avoid stacking on re-open
  const roleSelect = document.getElementById('eu-role');
  const fresh = roleSelect.cloneNode(true);
  roleSelect.parentNode.replaceChild(fresh, roleSelect);
  euCurrentUser = null;
}

function euShowError(msg) {
  const el = document.getElementById('eu-error');
  el.textContent   = msg;
  el.style.display = 'block';
  el.scrollIntoView({ behavior:'smooth', block:'nearest' });
}

/* Close drawer when clicking the dark overlay */
document.getElementById('eu-overlay')?.addEventListener('click', function(e) {
  if (e.target === this) euCloseDrawer();
});

/* Escape key closes whichever layer is open */
document.addEventListener('keydown', e => {
  if (e.key !== 'Escape') return;
  if (document.getElementById('role-change-modal').classList.contains('open')) { rcmClose(true); return; }
  if (document.getElementById('eu-overlay').classList.contains('open'))         { euCloseDrawer(); return; }
  if (document.getElementById('view-modal').classList.contains('open'))          { closeViewModal(); return; }
  if (document.getElementById('reject-modal').classList.contains('open'))        { closeRejectModal(); }
});

/* ════════════════════════════
   INIT
════════════════════════════ */
document.addEventListener('DOMContentLoaded', function () {
  renderTable(RESOURCES);
  renderFeed('feed-short', 4);
  renderFeed('feed-full');
  initDlChart();
  initSubjectChart();
});