/* ════════════════════════════
   DATA
════════════════════════════ */
const RESOURCES = [
  { title:"SLM – Mathematics 6: Fractions",  type:"SLM",        grade:"6",  subject:"Mathematics", melc:"M6NS-Ia-1",       status:"published", dl:1820, updated:"Jan 10, 2026" },
  { title:"Teacher's Guide – English 10",     type:"TG",         grade:"10", subject:"English",     melc:"EN10RC-Ic-4",     status:"published", dl:1340, updated:"Jan 8, 2026" },
  { title:"DLL – Science 8 Q1 W2",            type:"DLL",        grade:"8",  subject:"Science",     melc:"S8LT-Ib-2",       status:"review",    dl:530,  updated:"Jan 5, 2026" },
  { title:"SLM – English 3: Reading Comp.",   type:"SLM",        grade:"3",  subject:"English",     melc:"EN3RC-Ia-2",      status:"published", dl:760,  updated:"Dec 20, 2025" },
  { title:"DLP – English 6: Figurative Lang.","type":"DLP",       grade:"6",  subject:"English",     melc:"EN6VC-Ia-1",      status:"review",    dl:340,  updated:"Dec 15, 2025" },
  { title:"Video – Science 9: Mitosis",       type:"Video",      grade:"9",  subject:"Science",     melc:"S9LT-IIa-b-1",    status:"published", dl:1100, updated:"Dec 12, 2025" },
  { title:"SLM – Filipino 5: Pabula",         type:"SLM",        grade:"5",  subject:"Filipino",    melc:"F5PT-Ia-1",       status:"published", dl:670,  updated:"Dec 10, 2025" },
  { title:"Assessment – AP 7",                type:"Assessment", grade:"7",  subject:"AP",          melc:"AP7KSA-Id-5",     status:"review",    dl:310,  updated:"Dec 5, 2025" },
  { title:"LM – SHS Oral Communication",      type:"LM",         grade:"11", subject:"SHS Core",    melc:"EN11/12OC-Ia-1",  status:"published", dl:950,  updated:"Nov 28, 2025" },
  { title:"SLM – SHS Earth Science (STEM)",   type:"SLM",        grade:"11", subject:"SHS Core",    melc:"ES11-Ia-1",       status:"published", dl:820,  updated:"Nov 20, 2025" },
  { title:"SLM – Math 4 (SY 2021)",           type:"SLM",        grade:"4",  subject:"Mathematics", melc:"M4NS-IIa-1",      status:"archived",  dl:300,  updated:"Jun 1, 2021" },
  { title:"Worksheet – Math 3: Add. & Sub.",  type:"Worksheet",  grade:"3",  subject:"Mathematics", melc:"M3NS-Ia-2",       status:"published", dl:740,  updated:"Nov 15, 2025" },
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

/* ════════════════════════════
   PANEL SWITCHING
════════════════════════════ */
const PANELS = ['dashboard','pipeline','resources','analytics','qa','notifications'];
const PANEL_TITLES = {
  dashboard: ['Dashboard', 'Carcar City Division — SY 2025–2026'],
  pipeline:  ['Submission Pipeline', '7 items need action'],
  resources: ['Resource Manager', '1,284 total resources'],
  analytics: ['Analytics', 'Downloads, search & engagement metrics'],
  qa:        ['QA Tools', 'Quality Assurance Office'],
  notifications: ['Notifications', '3 unread'],
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
  document.getElementById('topbar-sub').textContent = sub;

  // Lazy-init charts when analytics panel is shown
  if (name === 'analytics') initAnalyticsCharts();
}

/* ════════════════════════════
   RESOURCE TABLE
════════════════════════════ */
function renderTable(data) {
  const tbody = document.getElementById('res-tbody');
  tbody.innerHTML = data.map(r => `
    <tr>
      <td>
        <div class="resource-title">${r.title}</div>
        <div class="resource-meta">${r.melc}</div>
      </td>
      <td><span class="chip chip-gray">${r.type}</span></td>
      <td>Grade ${r.grade}</td>
      <td>${r.subject}</td>
      <td><code style="font-family:var(--mono);font-size:11px;background:var(--bg);padding:2px 5px;border-radius:4px">${r.melc}</code></td>
      <td>
        <span class="status-badge ${r.status}">
          <span class="dot"></span>
          ${r.status.charAt(0).toUpperCase() + r.status.slice(1)}
        </span>
      </td>
      <td>${r.dl.toLocaleString()}</td>
      <td style="color:var(--muted);font-size:12px">${r.updated}</td>
      <td>
        <div class="action-row">
          <button class="tbl-btn primary">View</button>
          <button class="tbl-btn">Edit</button>
        </div>
      </td>
    </tr>`).join('');
}

function filterTable(q) {
  const query = (q || '').toLowerCase();
  const filtered = query
    ? RESOURCES.filter(r => r.title.toLowerCase().includes(query) || r.subject.toLowerCase().includes(query) || r.melc.toLowerCase().includes(query))
    : RESOURCES;
  renderTable(filtered);
}

/* ════════════════════════════
   ACTIVITY FEED
════════════════════════════ */
function renderFeed(containerId, limit) {
  const items = limit ? FEED_ITEMS.slice(0, limit) : FEED_ITEMS;
  document.getElementById(containerId).innerHTML = items.map(f => `
    <div class="feed-item">
      <div class="feed-avatar" style="background:${f.color}">${f.initials}</div>
      <div class="feed-body">
        <div class="feed-text">${f.text}</div>
        <div class="feed-time">${f.time}</div>
      </div>
      <span class="chip ${f.tagClass}">${f.tag}</span>
    </div>`).join('');
}

/* ════════════════════════════
   CHARTS
════════════════════════════ */
let dlChart, analyticsInited = false;

const DL_DATA = {
  '7d':  { labels:['Mon','Tue','Wed','Thu','Fri','Sat','Sun'],           data:[820,1040,760,1180,1390,980,1120] },
  '30d': { labels:['W1','W2','W3','W4'],                                 data:[6200,7100,7800,7300] },
  '90d': { labels:['Jan','Feb','Mar'],                                    data:[18400,21200,24800] },
};

function initDlChart() {
  const ctx = document.getElementById('dlChart').getContext('2d');
  const d = DL_DATA['7d'];
  dlChart = new Chart(ctx, {
    type: 'line',
    data: {
      labels: d.labels,
      datasets: [{
        label: 'Downloads',
        data: d.data,
        borderColor: '#0B4F9C',
        backgroundColor: 'rgba(11,79,156,.08)',
        borderWidth: 2,
        pointBackgroundColor: '#0B4F9C',
        pointRadius: 4,
        tension: .4,
        fill: true,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#94A3B8' } },
        y: { grid: { color: '#F1F5F9' }, ticks: { font: { size: 11 }, color: '#94A3B8' } }
      }
    }
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
  const ctx = document.getElementById('subjectChart').getContext('2d');
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: ['English','Filipino','Math','Science','AP','MAPEH','EsP','SHS'],
      datasets: [{
        data: [220, 185, 240, 195, 130, 90, 75, 149],
        backgroundColor: ['#3B82F6','#10B981','#F59E0B','#8B5CF6','#EF4444','#06B6D4','#F97316','#6366F1'],
        borderRadius: 6,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 10 }, color: '#94A3B8' } },
        y: { grid: { color: '#F1F5F9' }, ticks: { font: { size: 11 }, color: '#94A3B8' } }
      }
    }
  });
}

function initAnalyticsCharts() {
  if (analyticsInited) return;
  analyticsInited = true;

  const typeCtx = document.getElementById('typeChart').getContext('2d');
  new Chart(typeCtx, {
    type: 'bar',
    data: {
      labels: ['SLM','Video','TG','DLL/DLP','Assessment','LM','Worksheet','Other'],
      datasets: [{
        label: 'Downloads',
        data: [9800, 6200, 4500, 3100, 2400, 1900, 1300, 900],
        backgroundColor: 'rgba(11,79,156,.75)',
        borderRadius: 6,
      }]
    },
    options: {
      indexAxis: 'y',
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { color: '#F1F5F9' }, ticks: { font: { size: 11 }, color: '#94A3B8' } },
        y: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#94A3B8' } }
      }
    }
  });

  const fCtx = document.getElementById('funnelChart').getContext('2d');
  new Chart(fCtx, {
    type: 'bar',
    data: {
      labels: ['Submissions','Under QA','QA Passed','Published','Active Users'],
      datasets: [{
        data: [186, 47, 139, 1051, 680],
        backgroundColor: ['#60A5FA','#F59E0B','#10B981','#0B4F9C','#8B5CF6'],
        borderRadius: 6,
      }]
    },
    options: {
      responsive: true, maintainAspectRatio: false,
      plugins: { legend: { display: false } },
      scales: {
        x: { grid: { display: false }, ticks: { font: { size: 11 }, color: '#94A3B8' } },
        y: { grid: { color: '#F1F5F9' }, ticks: { font: { size: 11 }, color: '#94A3B8' } }
      }
    }
  });
}

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