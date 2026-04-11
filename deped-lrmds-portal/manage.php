<?php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: index.php?signin=1&dest=manage.php');
    exit;
}

$actor_role = $_SESSION['user_role'] ?? 'guest';
$actor_name = htmlspecialchars($_SESSION['user_name'] ?? 'User');
$actor_init = strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 2));

// Roles that can access manage.php at all
$manage_roles = ['admin', 'developer', 'school-head'];
if (!in_array($actor_role, $manage_roles)) {
    header('Location: index.php');
    exit;
}

// What this role can approve
function approvable_labels(string $role): string {
    return match($role) {
        'admin'       => 'Teachers, School Heads, Developers, Admins',
        'developer'   => 'School Heads & Developers',
        'school-head' => 'Teachers',
        default       => 'None',
    };
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1"/>
  <title>Manage — DepEd LRMDS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com"/>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin/>
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/manage.css"/>
  <link rel="stylesheet" href="assets/css/manage-users.css"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    /* ── Online stats KPI cards ── */
    .online-kpi-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 12px;
      margin-bottom: 16px;
    }
    .online-kpi {
      background: #fff;
      border: 1.5px solid var(--border, #E5E7EB);
      border-radius: 12px;
      padding: 16px 20px;
      display: flex;
      align-items: center;
      gap: 14px;
    }
    .online-kpi-icon {
      width: 44px; height: 44px;
      border-radius: 10px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .online-kpi-icon.green { background: #ECFDF5; }
    .online-kpi-icon.blue  { background: #EFF6FF; }
    .online-kpi-val {
      font-size: 28px; font-weight: 700; line-height: 1;
      font-family: 'DM Mono', monospace;
    }
    .online-kpi-val.green { color: #059669; }
    .online-kpi-val.blue  { color: #2563EB; }
    .online-kpi-label { font-size: 12px; color: #6B7280; margin-top: 3px; font-weight: 500; }
    .online-dot {
      width: 8px; height: 8px; background: #10B981;
      border-radius: 50%; display: inline-block;
      box-shadow: 0 0 0 3px rgba(16,185,129,.2);
      animation: pulse-dot 2s ease-in-out infinite;
      margin-right: 4px;
    }
    @keyframes pulse-dot {
      0%, 100% { box-shadow: 0 0 0 3px rgba(16,185,129,.2); }
      50%       { box-shadow: 0 0 0 6px rgba(16,185,129,.05); }
    }

    /* ── Hierarchy notice banner ── */
    .hierarchy-notice {
      display: flex; align-items: flex-start; gap: 12px;
      background: #FFFBEB; border: 1.5px solid #FDE68A;
      border-radius: 10px; padding: 12px 16px;
      font-size: 13px; color: #78350F;
      margin-bottom: 16px;
    }
    .hierarchy-notice strong { color: #92400E; }

    /* ── Applicant cards ── */
    .applicant-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
      gap: 14px;
      padding: 4px 0 8px;
    }
    .app-card {
      background: #fff;
      border: 1.5px solid #E5E7EB;
      border-radius: 12px;
      padding: 16px;
      position: relative;
      transition: border-color .15s, box-shadow .15s;
    }
    .app-card:hover { border-color: #93C5FD; box-shadow: 0 2px 12px rgba(59,130,246,.1); }
    .app-card-head {
      display: flex; align-items: flex-start; gap: 12px; margin-bottom: 12px;
    }
    .app-avatar {
      width: 42px; height: 42px; border-radius: 10px;
      background: linear-gradient(135deg, #0B4F9C, #3B82F6);
      color: #fff; font-weight: 700; font-size: 15px;
      display: flex; align-items: center; justify-content: center;
      flex-shrink: 0;
    }
    .app-name  { font-weight: 700; font-size: 14px; color: #111827; }
    .app-email { font-size: 12px; color: #6B7280; margin-top: 2px; word-break: break-all; }
    .app-meta  {
      display: flex; flex-wrap: wrap; gap: 6px;
      margin-bottom: 12px; font-size: 12px;
    }
    .app-meta-item {
      background: #F3F4F6; border-radius: 6px;
      padding: 3px 8px; color: #374151; font-weight: 500;
    }
    .app-meta-item.totp-ok  { background: #ECFDF5; color: #065F46; }
    .app-meta-item.totp-no  { background: #FEF2F2; color: #991B1B; }
    .app-meta-item.role-badge {
      background: #EFF6FF; color: #1D4ED8; font-weight: 700; text-transform: capitalize;
    }
    .app-date { font-size: 11px; color: #9CA3AF; margin-bottom: 12px; }
    .app-actions { display: flex; gap: 8px; }
    .btn-approve {
      flex: 1; padding: 8px 12px; border: none; border-radius: 8px;
      background: #0B4F9C; color: #fff; font-weight: 600; font-size: 13px;
      cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 5px;
      transition: background .15s;
    }
    .btn-approve:hover { background: #1D4ED8; }
    .btn-reject-card {
      padding: 8px 12px; border: 1.5px solid #FECACA; border-radius: 8px;
      background: #fff; color: #DC2626; font-weight: 600; font-size: 13px;
      cursor: pointer; transition: background .15s;
    }
    .btn-reject-card:hover { background: #FEF2F2; }
    .btn-view-card {
      padding: 8px 12px; border: 1.5px solid #E5E7EB; border-radius: 8px;
      background: #fff; color: #374151; font-weight: 600; font-size: 13px;
      cursor: pointer; transition: background .15s;
    }
    .btn-view-card:hover { background: #F9FAFB; }

    /* empty state */
    .um-empty {
      text-align: center; padding: 48px 24px; color: #9CA3AF;
    }
    .um-empty svg { margin-bottom: 12px; opacity: .35; }
    .um-empty p   { font-size: 14px; margin: 0; }
  </style>
</head>
<body>
<div class="shell">

  <!-- ══════════════════════ SIDEBAR ══════════════════════ -->
  <aside class="sidebar">
    <div class="sidebar-brand">
      <div class="logo-box">DE</div>
      <div class="brand-text">
        <span class="brand-name">LRMDS</span>
        <span class="brand-sub">Manage Portal</span>
      </div>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-section-label">Overview</div>
      <button class="nav-item active" onclick="showPanel('dashboard')">
        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
        Dashboard
      </button>
      <button class="nav-item" onclick="showPanel('pipeline')">
        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
        Pipeline
        <span class="nav-badge">7</span>
      </button>
      <button class="nav-item" onclick="showPanel('resources')">
        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
        Resources
      </button>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-section-label">Analytics</div>
      <button class="nav-item" onclick="showPanel('analytics')">
        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
        Analytics
      </button>
      <button class="nav-item" onclick="showPanel('qa')">
        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
        QA Tools
      </button>
    </div>

    <div class="sidebar-section">
      <div class="sidebar-section-label">System</div>
      <button class="nav-item" onclick="showPanel('notifications')">
        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
        Notifications
        <span class="nav-badge">3</span>
      </button>
      <button class="nav-item" onclick="showPanel('users')" id="nav-users">
        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        User Management
        <span class="nav-badge" id="pending-nav-badge" style="display:none">0</span>
      </button>
      <a class="nav-item" href="index.php">
        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        Back to Site
      </a>
    </div>

    <div class="sidebar-footer">
      <div class="user-chip">
        <div class="user-avatar"><?= $actor_init ?></div>
        <div>
          <div class="user-name"><?= $actor_name ?></div>
          <div class="user-role"><?= htmlspecialchars($actor_role) ?></div>
        </div>
      </div>
    </div>
  </aside>

  <!-- ══════════════════════ MAIN ══════════════════════ -->
  <div class="main">

    <!-- Top Bar -->
    <div class="topbar">
      <span class="topbar-title" id="topbar-title">Dashboard</span>
      <span class="topbar-sub"   id="topbar-sub">Carcar City Division — SY 2025–2026</span>
      <div class="topbar-right">
        <div class="notif-btn" onclick="showPanel('notifications')">
          <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <span class="notif-dot"></span>
        </div>
        <button class="btn btn-primary" onclick="showPanel('pipeline')">
          <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>
          New Submission
        </button>
      </div>
    </div>

    <!-- Canvas -->
    <div class="canvas">

      <!-- ══ DASHBOARD ══ -->
      <div id="panel-dashboard">
        <!-- Online stats KPIs -->
        <div class="online-kpi-row">
          <div class="online-kpi">
            <div class="online-kpi-icon green">
              <svg width="22" height="22" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
              <div class="online-kpi-val green"><span class="online-dot"></span><span id="kpi-online">—</span></div>
              <div class="online-kpi-label">Users Online Now (5 min)</div>
            </div>
          </div>
          <div class="online-kpi">
            <div class="online-kpi-icon blue">
              <svg width="22" height="22" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
              <div class="online-kpi-val blue" id="kpi-today">—</div>
              <div class="online-kpi-label">Logins Today</div>
            </div>
          </div>
        </div>

        <div class="kpi-grid">
          <div class="kpi-card blue">
            <div class="kpi-top"><span class="kpi-label">Total Resources</span><div class="kpi-icon blue"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg></div></div>
            <div class="kpi-value">1,284</div>
            <div class="kpi-delta up"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>+48 this month</div>
          </div>
          <div class="kpi-card green">
            <div class="kpi-top"><span class="kpi-label">QA Passed</span><div class="kpi-icon green"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div></div>
            <div class="kpi-value">1,051</div>
            <div class="kpi-delta up"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>81.9% pass rate</div>
          </div>
          <div class="kpi-card yellow">
            <div class="kpi-top"><span class="kpi-label">Pending QA</span><div class="kpi-icon yellow"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
            <div class="kpi-value">47</div>
            <div class="kpi-delta down"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>7 overdue &gt;14 days</div>
          </div>
          <div class="kpi-card red">
            <div class="kpi-top"><span class="kpi-label">Downloads (30d)</span><div class="kpi-icon red"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg></div></div>
            <div class="kpi-value">28.4K</div>
            <div class="kpi-delta up"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>+12% vs last month</div>
          </div>
        </div>
        <div class="row row-3-2">
          <div class="card">
            <div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg><span class="card-title">Downloads Over Time</span><div class="tabs" style="margin-left:auto"><button class="tab active" onclick="switchChartPeriod(this,'7d')">7d</button><button class="tab" onclick="switchChartPeriod(this,'30d')">30d</button><button class="tab" onclick="switchChartPeriod(this,'90d')">90d</button></div></div>
            <div class="card-body"><div class="chart-wrap"><canvas id="dlChart"></canvas></div></div>
          </div>
          <div class="card">
            <div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg><span class="card-title">Activity Feed</span><span class="card-sub">Today</span></div>
            <div class="card-body" style="padding-top:8px"><div class="feed" id="feed-short"></div></div>
          </div>
        </div>
        <div class="row row-3-2">
          <div class="card">
            <div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg><span class="card-title">Resources by Learning Area</span></div>
            <div class="card-body"><div class="chart-wrap" style="height:190px"><canvas id="subjectChart"></canvas></div></div>
          </div>
          <div class="card">
            <div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M7 12h10M11 18h2"/></svg><span class="card-title">Pipeline Snapshot</span></div>
            <div class="card-body">
              <div class="progress-row">
                <div class="prog-item"><div class="prog-head"><span class="prog-label">Draft</span><span class="prog-val">23</span></div><div class="prog-bar"><div class="prog-fill" style="width:45%;background:#94A3B8"></div></div></div>
                <div class="prog-item"><div class="prog-head"><span class="prog-label">Submitted</span><span class="prog-val">18</span></div><div class="prog-bar"><div class="prog-fill" style="width:35%;background:#60A5FA"></div></div></div>
                <div class="prog-item"><div class="prog-head"><span class="prog-label">Under QA</span><span class="prog-val">47</span></div><div class="prog-bar"><div class="prog-fill" style="width:65%;background:#F59E0B"></div></div></div>
                <div class="prog-item"><div class="prog-head"><span class="prog-label">Approved</span><span class="prog-val">12</span></div><div class="prog-bar"><div class="prog-fill" style="width:24%;background:#10B981"></div></div></div>
                <div class="prog-item"><div class="prog-head"><span class="prog-label">Published</span><span class="prog-val">1,051</span></div><div class="prog-bar"><div class="prog-fill" style="width:90%;background:#0B4F9C"></div></div></div>
                <div class="prog-item"><div class="prog-head"><span class="prog-label">Archived</span><span class="prog-val">133</span></div><div class="prog-bar"><div class="prog-fill" style="width:20%;background:#F87171"></div></div></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ PIPELINE ══ -->
      <div id="panel-pipeline" style="display:none">
        <div class="card">
          <div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M7 12h10M11 18h2"/></svg><span class="card-title">Submission Pipeline</span><span class="card-sub">7 items need action</span></div>
          <div class="card-body">
            <div class="pipeline-row">
              <div class="pipeline-col"><div class="pipeline-col-head"><span class="stage-dot" style="background:#94A3B8"></span><span class="stage-label">Draft</span><span class="stage-count">3</span></div><div class="pipe-card"><div class="pipe-card-title">SLM – Filipino 8: Maikling Kuwento</div><div class="pipe-card-meta"><span class="chip chip-gray">Grade 8</span><span class="chip chip-gray">Filipino</span></div></div><div class="pipe-card"><div class="pipe-card-title">Worksheet – Math 4: Fractions Review</div><div class="pipe-card-meta"><span class="chip chip-gray">Grade 4</span><span class="chip chip-gray">Math</span></div></div></div>
              <div class="pipeline-col"><div class="pipeline-col-head"><span class="stage-dot" style="background:#60A5FA"></span><span class="stage-label">Submitted</span><span class="stage-count">4</span></div><div class="pipe-card"><div class="pipe-card-title">TG – English 10 Q2</div><div class="pipe-card-meta"><span class="chip chip-blue">Grade 10</span><span class="chip chip-gray">English</span></div></div><div class="pipe-card"><div class="pipe-card-title">DLL – MAPEH 7 Week 3</div><div class="pipe-card-meta"><span class="chip chip-blue">Grade 7</span><span class="chip chip-gray">MAPEH</span></div></div></div>
              <div class="pipeline-col"><div class="pipeline-col-head"><span class="stage-dot" style="background:#F59E0B"></span><span class="stage-label">Under QA</span><span class="stage-count">5</span></div><div class="pipe-card"><div class="pipe-card-title">SLM – Math 6: Fractions</div><div class="pipe-card-meta"><span class="chip chip-yellow">QA #1042</span><span class="chip chip-gray">14d</span></div></div><div class="pipe-card"><div class="pipe-card-title">DLP – English 6: Figurative</div><div class="pipe-card-meta"><span class="chip chip-red">⚠ 18d overdue</span></div></div></div>
              <div class="pipeline-col"><div class="pipeline-col-head"><span class="stage-dot" style="background:#10B981"></span><span class="stage-label">Approved</span><span class="stage-count">3</span></div><div class="pipe-card"><div class="pipe-card-title">TG – Math 3: Addition</div><div class="pipe-card-meta"><span class="chip chip-green">Ready to Publish</span></div></div></div>
              <div class="pipeline-col"><div class="pipeline-col-head"><span class="stage-dot" style="background:#0B4F9C"></span><span class="stage-label">Published</span><span class="stage-count">1,051</span></div><div class="pipe-card"><div class="pipe-card-title">SLM – English 3: Reading</div><div class="pipe-card-meta"><span class="chip chip-blue">760 DL</span></div></div><div class="pipe-card" style="cursor:default;color:var(--muted);font-size:12px;text-align:center;padding:8px">+ 1,049 more…</div></div>
              <div class="pipeline-col"><div class="pipeline-col-head"><span class="stage-dot" style="background:#F87171"></span><span class="stage-label">Archived</span><span class="stage-count">133</span></div><div class="pipe-card"><div class="pipe-card-title">SLM – Math 4 (SY 2021)</div><div class="pipe-card-meta"><span class="chip chip-red">Outdated</span></div></div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ RESOURCES ══ -->
      <div id="panel-resources" style="display:none">
        <div class="card">
          <div class="card-header">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <span class="card-title">All Resources</span>
            <div class="filter-bar" style="margin-left:auto">
              <input type="search" placeholder="Search…" oninput="filterTable(this.value)"/>
              <select><option value="">All Status</option><option>Published</option><option>Under Review</option><option>Archived</option></select>
              <select><option>All Grades</option><option>Grade 3</option><option>Grade 6</option><option>Grade 10</option></select>
              <button class="btn btn-primary" style="font-size:12px;padding:7px 12px">Export CSV</button>
            </div>
          </div>
          <div class="card-body" style="padding:0">
            <div class="table-wrap"><table><thead><tr><th>Resource</th><th>Type</th><th>Grade</th><th>Subject</th><th>MELC</th><th>Status</th><th>Downloads</th><th>Updated</th><th>Actions</th></tr></thead><tbody id="res-tbody"></tbody></table></div>
          </div>
        </div>
      </div>

      <!-- ══ ANALYTICS ══ -->
      <div id="panel-analytics" style="display:none">
        <!-- Online stats in analytics too -->
        <div class="online-kpi-row" style="margin-bottom:16px">
          <div class="online-kpi">
            <div class="online-kpi-icon green">
              <svg width="22" height="22" fill="none" stroke="#059669" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </div>
            <div>
              <div class="online-kpi-val green"><span class="online-dot"></span><span id="kpi-online-2">—</span></div>
              <div class="online-kpi-label">Users Online Now (5 min)</div>
            </div>
          </div>
          <div class="online-kpi">
            <div class="online-kpi-icon blue">
              <svg width="22" height="22" fill="none" stroke="#2563EB" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <div>
              <div class="online-kpi-val blue" id="kpi-today-2">—</div>
              <div class="online-kpi-label">Logins Today</div>
            </div>
          </div>
        </div>

        <div class="kpi-grid" style="margin-bottom:0">
          <div class="kpi-card blue"><div class="kpi-top"><span class="kpi-label">Search Success Rate</span><div class="kpi-icon blue"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></div></div><div class="kpi-value">73.2%</div><div class="kpi-delta up"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>+3.1% vs last month</div></div>
          <div class="kpi-card red"><div class="kpi-top"><span class="kpi-label">Zero-Result Queries</span><div class="kpi-icon red"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg></div></div><div class="kpi-value">418</div><div class="kpi-delta down"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>Top gap: "SLM Grade 2 MTB"</div></div>
          <div class="kpi-card green"><div class="kpi-top"><span class="kpi-label">Avg. Time-to-Download</span><div class="kpi-icon green"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div><div class="kpi-value">1m 42s</div><div class="kpi-delta up"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>Improved 18s</div></div>
          <div class="kpi-card yellow"><div class="kpi-top"><span class="kpi-label">Repeat Sessions</span><div class="kpi-icon yellow"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></div></div><div class="kpi-value">62.8%</div><div class="kpi-delta neu">Stable this week</div></div>
        </div>
        <div class="charts-grid">
          <div class="card"><div class="card-header"><span class="card-title">Downloads by Resource Type (30d)</span></div><div class="card-body"><div class="chart-wrap"><canvas id="typeChart"></canvas></div></div></div>
          <div class="card"><div class="card-header"><span class="card-title">Top Queries with Zero Results</span></div><div class="card-body"><div class="progress-row"><div class="prog-item"><div class="prog-head"><span class="prog-label">SLM Grade 2 MTB</span><span class="prog-val">87</span></div><div class="prog-bar"><div class="prog-fill" style="width:87%;background:#F87171"></div></div></div><div class="prog-item"><div class="prog-head"><span class="prog-label">Kinder Filipino SLM</span><span class="prog-val">74</span></div><div class="prog-bar"><div class="prog-fill" style="width:74%;background:#FBBF24"></div></div></div><div class="prog-item"><div class="prog-head"><span class="prog-label">Grade 1 Math video</span><span class="prog-val">61</span></div><div class="prog-bar"><div class="prog-fill" style="width:61%;background:#FBBF24"></div></div></div></div></div></div>
        </div>
        <div class="card"><div class="card-header"><span class="card-title">Contribution Funnel — SY 2025–2026</span></div><div class="card-body"><div class="chart-wrap" style="height:180px"><canvas id="funnelChart"></canvas></div></div></div>
      </div>

      <!-- ══ QA TOOLS ══ -->
      <div id="panel-qa" style="display:none">
        <div class="card" style="margin-bottom:16px">
          <div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg><span class="card-title">QA Tools &amp; Rubrics</span><span class="card-sub">Quality Assurance Office</span></div>
          <div class="card-body"><div class="qa-grid">
            <a class="qa-card" href="#"><div class="qa-icon-box" style="background:#EEF5FF">📋</div><div><div class="qa-card-title">General QA Rubric</div><div class="qa-card-desc">Standard evaluation form for all LR types.</div></div></a>
            <a class="qa-card" href="#"><div class="qa-icon-box" style="background:#ECFDF5">📄</div><div><div class="qa-card-title">SLM Checklist</div><div class="qa-card-desc">Completeness and accessibility checklist.</div></div></a>
            <a class="qa-card" href="#"><div class="qa-icon-box" style="background:#FFFBEB">🎬</div><div><div class="qa-card-title">Video QA Form</div><div class="qa-card-desc">Caption accuracy, production quality rubric.</div></div></a>
            <a class="qa-card" href="#"><div class="qa-icon-box" style="background:#F5F3FF">📊</div><div><div class="qa-card-title">Assessment Bank QA</div><div class="qa-card-desc">Item analysis, Bloom's taxonomy alignment.</div></div></a>
            <a class="qa-card" href="#"><div class="qa-icon-box" style="background:#FEF2F2">📁</div><div><div class="qa-card-title">Submission Template</div><div class="qa-card-desc">Standard cover sheet and metadata template.</div></div></a>
            <a class="qa-card" href="#"><div class="qa-icon-box" style="background:#EEF5FF">🏅</div><div><div class="qa-card-title">QA Certification Guide</div><div class="qa-card-desc">Step-by-step guide for QA reviewers.</div></div></a>
          </div></div>
        </div>
        <div class="card">
          <div class="card-header"><span class="card-title">Pending QA Queue</span><span class="card-sub">47 items · 7 overdue</span></div>
          <div class="card-body" style="padding:0"><div class="table-wrap"><table><thead><tr><th>Resource</th><th>Submitted By</th><th>Type</th><th>Days in QA</th><th>Reviewer</th><th>Action</th></tr></thead><tbody>
            <tr><td><div class="resource-title">DLP – English 6: Figurative Language</div><div class="resource-meta">M6EN-Ia-1 · Grade 6</div></td><td>J. Reyes</td><td><span class="chip chip-gray">DLP</span></td><td><span style="color:var(--red);font-weight:700">18d ⚠</span></td><td><span style="color:var(--muted)">Unassigned</span></td><td><div class="action-row"><button class="tbl-btn primary">Assign</button><button class="tbl-btn">View</button></div></td></tr>
            <tr><td><div class="resource-title">SLM – Math 6: Fractions</div><div class="resource-meta">M6NS-Ia-1 · Grade 6</div></td><td>C. Dela Cruz</td><td><span class="chip chip-blue">SLM</span></td><td><span style="color:var(--yellow);font-weight:700">14d</span></td><td>L. Navarro</td><td><div class="action-row"><button class="tbl-btn primary">Review</button><button class="tbl-btn">View</button></div></td></tr>
          </tbody></table></div></div>
        </div>
      </div>

      <!-- ══ NOTIFICATIONS ══ -->
      <div id="panel-notifications" style="display:none">
        <div class="card">
          <div class="card-header"><span class="card-title">Notifications &amp; Activity</span>
            <div class="tabs" style="margin-left:auto"><button class="tab active">All</button><button class="tab">QA</button><button class="tab">System</button></div>
            <button class="btn btn-ghost" style="font-size:12px;padding:6px 10px;margin-left:8px">Mark all read</button>
          </div>
          <div class="card-body" style="padding-top:8px"><div class="feed" id="feed-full"></div></div>
        </div>
      </div>

      <!-- ══ USER MANAGEMENT ══ -->
      <div id="panel-users" style="display:none">

        <!-- Hierarchy notice -->
        <div class="hierarchy-notice">
          <svg width="18" height="18" fill="none" stroke="#92400E" stroke-width="2" viewBox="0 0 24 24" style="flex-shrink:0;margin-top:1px"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
          <div>
            <strong>Your approval scope (<?= htmlspecialchars($actor_role) ?>):</strong>
            You can review and approve: <?= approvable_labels($actor_role) ?>.
            <?php if ($actor_role === 'school-head'): ?>
              Approvals are scoped to your division only.
            <?php endif; ?>
          </div>
        </div>

        <div class="um-stats-bar">
          <div class="um-stat"><div class="um-stat-val" id="stat-total">—</div><div class="um-stat-label">Total Users</div></div>
          <div class="um-stat"><div class="um-stat-val um-val-green" id="stat-active">—</div><div class="um-stat-label">Active</div></div>
          <div class="um-stat"><div class="um-stat-val um-val-yellow" id="stat-pending">—</div><div class="um-stat-label">Pending (Your Queue)</div></div>
          <div class="um-stat"><div class="um-stat-val um-val-red" id="stat-suspended">—</div><div class="um-stat-label">Suspended</div></div>
          <div class="um-stat"><div class="um-stat-val um-val-muted" id="stat-guest">—</div><div class="um-stat-label">Guests</div></div>
        </div>

        <div class="card">
          <div class="card-header">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            <span class="card-title">User Management</span>
            <span class="card-sub" id="um-card-sub">Loading…</span>
            <button class="btn btn-ghost" style="margin-left:auto;font-size:12px;padding:6px 10px" onclick="umRefresh()">
              <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M23 4v6h-6"/><path d="M1 20v-6h6"/><path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/></svg>
              Refresh
            </button>
          </div>

          <div class="card-body">
            <div class="um-tabs">
              <button class="um-tab active" id="um-tab-pending" onclick="umSwitchTab('pending')">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Pending Approval
                <span class="um-tab-badge" id="um-pending-count">0</span>
              </button>
              <button class="um-tab" id="um-tab-all" onclick="umSwitchTab('all')">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                All Users
                <span class="um-tab-badge um-badge-green" id="um-all-count">0</span>
              </button>
            </div>

            <!-- Pending Applications -->
            <div class="um-section active" id="um-section-pending">
              <div class="um-filter-bar">
                <input type="search" id="pending-search" placeholder="Search by name, email, or ID…" oninput="umLoadPending()"/>
                <select id="pending-role-filter" onchange="umLoadPending()">
                  <option value="">All Roles</option>
                  <?php if (in_array($actor_role, ['admin'])): ?>
                    <option value="teacher">Teacher</option>
                    <option value="school-head">School Head</option>
                    <option value="developer">Content Developer</option>
                  <?php elseif ($actor_role === 'developer'): ?>
                    <option value="school-head">School Head</option>
                    <option value="developer">Content Developer</option>
                  <?php elseif ($actor_role === 'school-head'): ?>
                    <option value="teacher">Teacher</option>
                  <?php endif; ?>
                </select>
                <span class="um-result-count" id="pending-result-count"></span>
              </div>
              <div id="pending-list" class="applicant-grid"></div>
            </div>

            <!-- All Users -->
            <div class="um-section" id="um-section-all">
              <div class="um-filter-bar">
                <input type="search" id="users-search" placeholder="Search by name, email, or ID…" oninput="umLoadUsers()"/>
                <select id="users-role-filter" onchange="umLoadUsers()">
                  <option value="">All Roles</option>
                  <option value="teacher">Teacher</option>
                  <?php if (in_array($actor_role, ['admin', 'developer'])): ?>
                    <option value="learner">Learner</option>
                    <option value="parent">Parent</option>
                    <option value="school-head">School Head</option>
                    <option value="developer">Developer</option>
                    <option value="guest">Guest</option>
                  <?php endif; ?>
                  <?php if ($actor_role === 'admin'): ?>
                    <option value="admin">Admin</option>
                  <?php endif; ?>
                </select>
                <select id="users-status-filter" onchange="umLoadUsers()">
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="pending">Pending</option>
                  <option value="suspended">Suspended</option>
                </select>
                <span class="um-result-count" id="users-result-count"></span>
              </div>
              <div class="table-wrap">
                <table id="users-table">
                  <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Region</th><th>2FA</th><th>Last Login</th><th>Joined</th><th>Actions</th></tr></thead>
                  <tbody id="users-tbody"></tbody>
                </table>
              </div>
            </div>

          </div>
        </div>
      </div><!-- /panel-users -->

    </div><!-- /canvas -->
  </div><!-- /main -->
</div><!-- /shell -->

<!-- ══ REJECT MODAL ══ -->
<div class="um-modal-overlay" id="reject-modal">
  <div class="um-modal">
    <div class="um-modal-title">Reject Application</div>
    <div class="um-modal-body">This will permanently delete the pending registration. The applicant will need to register again.<br><br>Reason (optional — not shown to applicant, logged internally):</div>
    <textarea id="reject-reason" placeholder="e.g. Invalid employee ID, unverifiable credentials…"></textarea>
    <div class="um-modal-actions">
      <button class="btn btn-ghost" onclick="closeRejectModal()">Cancel</button>
      <button class="btn-reject" id="reject-confirm-btn" onclick="confirmReject()">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Reject Application
      </button>
    </div>
  </div>
</div>

<!-- ══ ROLE-CHANGE CONFIRMATION MODAL ══ -->
<div class="um-modal-overlay" id="role-change-modal">
  <div class="um-modal rcm-modal">

    <div class="rcm-icon-wrap">
      <svg width="22" height="22" fill="none" stroke="#92400E" stroke-width="2" viewBox="0 0 24 24">
        <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/>
        <line x1="12" y1="9" x2="12" y2="13"/>
        <line x1="12" y1="17" x2="12.01" y2="17"/>
      </svg>
    </div>

    <div class="um-modal-title" style="text-align:center">Confirm Role Change</div>

    <p class="rcm-who">You are changing the role of <strong id="rcm-user-name">—</strong>:</p>

    <div class="rcm-arrow-row">
      <span class="rcm-role-badge" id="rcm-from-badge">—</span>
      <svg width="20" height="20" fill="none" stroke="#6B7280" stroke-width="2.5" viewBox="0 0 24 24">
        <path d="M5 12h14M12 5l7 7-7 7"/>
      </svg>
      <span class="rcm-role-badge" id="rcm-to-badge">—</span>
    </div>

    <div id="rcm-impact"></div>

    <p class="rcm-note">This action takes effect immediately. Are you sure you want to proceed?</p>

    <div class="um-modal-actions">
      <button class="btn btn-ghost" onclick="rcmClose(true)">Cancel</button>
      <button class="btn btn-primary" id="rcm-confirm-btn" onclick="rcmConfirm()" style="background:#C62828;border-color:#C62828">
        Yes, Change Role
      </button>
    </div>

  </div>
</div>

<!-- ══ VIEW USER MODAL ══ -->
<div class="um-modal-overlay" id="view-modal">
  <div class="um-modal um-modal-view">
    <div class="vm-header">
      <div class="vm-avatar" id="vm-avatar">AB</div>
      <div class="vm-header-info">
        <div class="vm-name"  id="vm-name">—</div>
        <div class="vm-email" id="vm-email">—</div>
        <div class="vm-badges" id="vm-badges"></div>
      </div>
      <button class="eu-close" onclick="closeViewModal()" style="margin-left:auto" aria-label="Close">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="vm-body" id="vm-body">Loading…</div>
    <div class="um-modal-actions" style="padding-top:16px;border-top:1px solid var(--border)">
      <button class="btn btn-ghost" onclick="closeViewModal()">Close</button>
      <button class="btn btn-primary" id="vm-edit-btn" onclick="vmSwitchToEdit()">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
        Edit This User
      </button>
    </div>
  </div>
</div>

<!-- ══ EDIT USER DRAWER ══ -->
<div class="eu-overlay" id="eu-overlay">
  <div class="eu-drawer" id="eu-drawer" role="dialog" aria-modal="true" aria-label="Edit User">
    <div class="eu-header">
      <div class="eu-header-left">
        <div class="eu-avatar" id="eu-avatar">AB</div>
        <div>
          <div class="eu-title" id="eu-title">Edit User</div>
          <div class="eu-sub"   id="eu-sub">—</div>
        </div>
      </div>
      <button class="eu-close" onclick="euCloseDrawer()" aria-label="Close">
        <svg width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
      </button>
    </div>
    <div class="eu-body">
      <div class="eu-error" id="eu-error" style="display:none"></div>
      <div class="eu-section-label">Personal Information</div>
      <div class="eu-row-2">
        <div class="eu-field"><label class="eu-label" for="eu-fname">First Name</label><input class="eu-input" id="eu-fname" type="text" placeholder="First name"/></div>
        <div class="eu-field"><label class="eu-label" for="eu-lname">Last Name</label><input class="eu-input" id="eu-lname" type="text" placeholder="Last name"/></div>
      </div>
      <div class="eu-field">
        <label class="eu-label" for="eu-email">Email Address</label>
        <input class="eu-input" id="eu-email" type="email" placeholder="user@example.com"/>
        <div class="eu-hint">Changing this updates the sign-in email.</div>
      </div>
      <div class="eu-section-label" style="margin-top:20px">Role &amp; Status</div>
      <div class="eu-row-2">
        <div class="eu-field">
          <label class="eu-label" for="eu-role">Role</label>
          <select class="eu-select" id="eu-role">
            <option value="teacher">Teacher</option>
            <option value="learner">Learner</option>
            <option value="parent">Parent</option>
            <option value="school-head">School Head</option>
            <option value="developer">Content Developer</option>
            <?php if ($actor_role === 'admin'): ?>
              <option value="admin">Admin</option>
            <?php endif; ?>
            <option value="guest">Guest</option>
          </select>
          <div class="eu-hint" id="eu-role-hint"></div>
        </div>
        <div class="eu-field">
          <label class="eu-label" for="eu-status">Status</label>
          <select class="eu-select" id="eu-status">
            <option value="active">Active</option>
            <option value="pending">Pending</option>
            <option value="suspended">Suspended</option>
          </select>
        </div>
      </div>
      <div class="eu-section-label" style="margin-top:20px">Organization</div>
      <div class="eu-field"><label class="eu-label" for="eu-region">Region</label><input class="eu-input" id="eu-region" type="text" placeholder="e.g. Region VII"/></div>
      <div class="eu-row-2">
        <div class="eu-field"><label class="eu-label" for="eu-division">Division</label><input class="eu-input" id="eu-division" type="text" placeholder="e.g. Carcar City Division"/></div>
        <div class="eu-field"><label class="eu-label" for="eu-employee-id">Employee / School ID</label><input class="eu-input" id="eu-employee-id" type="text" placeholder="e.g. 10042"/></div>
      </div>
      <div class="eu-section-label" style="margin-top:20px">Security</div>
      <div class="eu-security-row" id="eu-totp-row">
        <div>
          <div class="eu-security-label">Two-Factor Authentication</div>
          <div class="eu-security-hint" id="eu-totp-hint">—</div>
        </div>
        <button class="eu-danger-btn" id="eu-totp-btn" onclick="euDisableTotp()">Disable 2FA</button>
      </div>
      <div class="eu-security-row" style="margin-top:10px">
        <div>
          <div class="eu-security-label">Password Reset</div>
          <div class="eu-security-hint">Send a password-reset email to this user.</div>
        </div>
        <button class="eu-ghost-btn" onclick="euSendPasswordReset()">Send Reset Email</button>
      </div>
      <div class="eu-field" style="margin-top:12px">
        <label class="eu-label" for="eu-new-password">Set New Password <span style="font-weight:400;color:var(--muted)">(leave blank to keep current)</span></label>
        <input class="eu-input" id="eu-new-password" type="password" placeholder="Min. 8 characters" autocomplete="new-password"/>
      </div>
    </div>
    <div class="eu-footer">
      <button class="btn btn-ghost" onclick="euCloseDrawer()">Cancel</button>
      <button class="btn btn-primary" id="eu-save-btn" onclick="euSave()">
        <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
        Save Changes
      </button>
    </div>
  </div>
</div>

<!-- ══ TOAST ══ -->
<div id="um-toast">
  <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" id="toast-icon"><path d="M20 6 9 17l-5-5"/></svg>
  <span id="um-toast-msg"></span>
</div>

<script>
  const CURRENT_USER_ROLE = <?= json_encode($actor_role) ?>;

  /* ── Online stats polling ── */
  function fetchOnlineStats() {
    const fd = new FormData();
    fd.append('action', 'online_stats');
    fetch('user_api.php', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (!d.ok) return;
        const fmt = n => n.toLocaleString();
        ['kpi-online','kpi-online-2'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.textContent = fmt(d.online);
        });
        ['kpi-today','kpi-today-2'].forEach(id => {
          const el = document.getElementById(id);
          if (el) el.textContent = fmt(d.today);
        });
      })
      .catch(() => {});
  }
  fetchOnlineStats();
  setInterval(fetchOnlineStats, 60000); // refresh every minute
</script>
<script src="assets/js/manage.js"></script>
</body>
</html>
//console.log(CURRENT_USER_ROLE)
//console.log(canEditUser({role:'teacher'}))// For testing role permissions in the console