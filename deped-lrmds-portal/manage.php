<?php
session_start();
if (empty($_SESSION['user'])) {
    header('Location: index.php?signin=1&dest=manage.php');
    exit;
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
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet"/>
  <link rel="stylesheet" href="assets/css/manage.css"/>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
  <style>
    /* ── User Management Panel ── */
    .um-tabs {
      display: flex;
      gap: 0;
      border-bottom: 2px solid var(--border);
      margin-bottom: 20px;
    }
    .um-tab {
      padding: 10px 20px;
      font-size: 13px;
      font-weight: 600;
      color: var(--muted);
      cursor: pointer;
      border: none;
      background: none;
      font-family: var(--font);
      border-bottom: 2px solid transparent;
      margin-bottom: -2px;
      transition: color .15s, border-color .15s;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .um-tab:hover { color: var(--text); }
    .um-tab.active { color: var(--blue); border-bottom-color: var(--blue); }
    .um-tab-badge {
      background: var(--red);
      color: #fff;
      font-size: 10px;
      font-weight: 700;
      border-radius: 20px;
      padding: 1px 6px;
      min-width: 18px;
      text-align: center;
    }
    .um-tab-badge.green { background: var(--green); }

    .um-section { display: none; }
    .um-section.active { display: block; }

    /* Filter bar for user management */
    .um-filter-bar {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap;
      margin-bottom: 16px;
      padding: 14px 16px;
      background: var(--bg);
      border: 1px solid var(--border);
      border-radius: 10px;
    }
    .um-filter-bar input[type="search"] {
      flex: 1;
      min-width: 200px;
      padding: 8px 12px;
      border: 1px solid var(--border);
      border-radius: 7px;
      font-family: var(--font);
      font-size: 13px;
      color: var(--text);
      background: var(--surface);
      outline: none;
    }
    .um-filter-bar input:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(11,79,156,.1); }
    .um-filter-bar select {
      padding: 8px 10px;
      border: 1px solid var(--border);
      border-radius: 7px;
      font-family: var(--font);
      font-size: 12px;
      color: var(--text);
      background: var(--surface);
      outline: none;
      cursor: pointer;
    }
    .um-filter-bar select:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(11,79,156,.1); }

    /* Pending applicant card */
    .applicant-grid {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .applicant-card {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 16px 18px;
      display: flex;
      align-items: flex-start;
      gap: 14px;
      transition: box-shadow .15s;
    }
    .applicant-card:hover { box-shadow: var(--shadow-sm); }
    .applicant-avatar {
      width: 40px; height: 40px;
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 14px; font-weight: 700;
      color: #fff; flex-shrink: 0;
    }
    .applicant-main { flex: 1; min-width: 0; }
    .applicant-name {
      font-size: 14px; font-weight: 700;
      color: var(--text); margin-bottom: 2px;
    }
    .applicant-email { font-size: 12px; color: var(--muted); margin-bottom: 6px; font-family: var(--mono); }
    .applicant-chips { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 6px; }
    .applicant-meta { font-size: 12px; color: var(--muted); }
    .applicant-actions { display: flex; gap: 8px; align-items: center; flex-shrink: 0; flex-wrap: wrap; justify-content: flex-end; }

    .btn-approve {
      background: var(--green-lt);
      color: var(--green);
      border: 1px solid #A7F3D0;
      font-size: 12px; font-weight: 700;
      padding: 7px 14px;
      border-radius: 7px;
      cursor: pointer;
      font-family: var(--font);
      display: flex; align-items: center; gap: 5px;
      transition: background .15s, box-shadow .15s;
    }
    .btn-approve:hover { background: #D1FAE5; box-shadow: 0 2px 8px rgba(4,120,87,.15); }

    .btn-reject {
      background: var(--red-light);
      color: var(--red);
      border: 1px solid #FECACA;
      font-size: 12px; font-weight: 700;
      padding: 7px 14px;
      border-radius: 7px;
      cursor: pointer;
      font-family: var(--font);
      display: flex; align-items: center; gap: 5px;
      transition: background .15s;
    }
    .btn-reject:hover { background: #FEE2E2; }

    .btn-suspend {
      background: var(--yellow-lt);
      color: #92400E;
      border: 1px solid #FDE68A;
      font-size: 12px; font-weight: 600;
      padding: 5px 10px;
      border-radius: 6px;
      cursor: pointer;
      font-family: var(--font);
      transition: background .15s;
    }
    .btn-suspend:hover { background: #FEF3C7; }
    .btn-reactivate {
      background: var(--green-lt);
      color: var(--green);
      border: 1px solid #A7F3D0;
      font-size: 12px; font-weight: 600;
      padding: 5px 10px;
      border-radius: 6px;
      cursor: pointer;
      font-family: var(--font);
      transition: background .15s;
    }
    .btn-reactivate:hover { background: #D1FAE5; }

    /* Role selector inline */
    .role-select-inline {
      padding: 4px 8px;
      border: 1px solid var(--border);
      border-radius: 6px;
      font-family: var(--font);
      font-size: 12px;
      color: var(--text);
      background: var(--surface);
      cursor: pointer;
      outline: none;
    }
    .role-select-inline:focus { border-color: var(--blue); }

    /* Empty state */
    .um-empty {
      text-align: center;
      padding: 48px 24px;
      color: var(--muted);
    }
    .um-empty svg { margin: 0 auto 12px; display: block; opacity: .3; }
    .um-empty-title { font-size: 15px; font-weight: 700; color: var(--text); margin-bottom: 4px; }
    .um-empty-sub { font-size: 13px; }

    /* Loading skeleton */
    .um-loading {
      display: flex;
      flex-direction: column;
      gap: 10px;
    }
    .um-skeleton {
      height: 80px;
      background: linear-gradient(90deg, var(--bg) 25%, #E2E8F0 50%, var(--bg) 75%);
      background-size: 200% 100%;
      border-radius: 10px;
      animation: shimmer 1.2s infinite;
    }
    @keyframes shimmer { to { background-position: -200% 0; } }

    /* Toast notification */
    #um-toast {
      position: fixed;
      bottom: 28px;
      right: 28px;
      background: var(--text);
      color: #fff;
      padding: 12px 18px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 600;
      box-shadow: 0 8px 24px rgba(0,0,0,.2);
      z-index: 9999;
      transform: translateY(80px);
      opacity: 0;
      transition: transform .3s cubic-bezier(.34,1.56,.64,1), opacity .2s;
      display: flex;
      align-items: center;
      gap: 8px;
      max-width: 340px;
    }
    #um-toast.show { transform: translateY(0); opacity: 1; }
    #um-toast.success { background: var(--green); }
    #um-toast.error   { background: var(--red); }

    /* User summary stats bar */
    .um-stats-bar {
      display: grid;
      grid-template-columns: repeat(5, 1fr);
      gap: 10px;
      margin-bottom: 20px;
    }
    .um-stat {
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 10px;
      padding: 12px 16px;
      text-align: center;
    }
    .um-stat-val { font-size: 22px; font-weight: 700; color: var(--text); line-height: 1; }
    .um-stat-label { font-size: 11px; color: var(--muted); margin-top: 4px; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; }

    /* Confirm modal */
    .um-modal-overlay {
      position: fixed; inset: 0;
      background: rgba(15,23,42,.5);
      z-index: 1000;
      display: flex; align-items: center; justify-content: center;
      opacity: 0; pointer-events: none;
      transition: opacity .2s;
    }
    .um-modal-overlay.open { opacity: 1; pointer-events: all; }
    .um-modal {
      background: var(--surface);
      border-radius: 14px;
      padding: 28px;
      max-width: 420px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(0,0,0,.2);
      transform: scale(.95);
      transition: transform .2s cubic-bezier(.34,1.56,.64,1);
    }
    .um-modal-overlay.open .um-modal { transform: scale(1); }
    .um-modal-title { font-size: 16px; font-weight: 700; color: var(--text); margin-bottom: 8px; }
    .um-modal-body  { font-size: 13px; color: var(--muted); margin-bottom: 20px; line-height: 1.6; }
    .um-modal textarea {
      width: 100%; padding: 10px 12px;
      border: 1px solid var(--border); border-radius: 8px;
      font-family: var(--font); font-size: 13px; color: var(--text);
      resize: vertical; min-height: 80px; margin-bottom: 16px;
      outline: none; box-sizing: border-box;
    }
    .um-modal textarea:focus { border-color: var(--blue); box-shadow: 0 0 0 3px rgba(11,79,156,.1); }
    .um-modal-actions { display: flex; gap: 8px; justify-content: flex-end; }
  </style>
</head>
<body>
<div class="shell">

  <!-- ══════════════════════
       SIDEBAR
  ══════════════════════ -->
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
      <!-- NEW: User Management -->
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
        <div class="user-avatar"><?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 2)) ?></div>
        <div>
          <div class="user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
          <div class="user-role"><?= htmlspecialchars($_SESSION['user_role'] ?? '') ?></div>
        </div>
      </div>
    </div>
  </aside>

  <!-- ══════════════════════
       MAIN
  ══════════════════════ -->
  <div class="main">

    <!-- Top Bar -->
    <div class="topbar">
      <span class="topbar-title" id="topbar-title">Dashboard</span>
      <span class="topbar-sub" id="topbar-sub">Carcar City Division — SY 2025–2026</span>
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
    <div class="canvas" id="canvas">

      <!-- ══ PANEL: DASHBOARD ══ -->
      <div id="panel-dashboard">
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

      <!-- ══ PANEL: PIPELINE ══ -->
      <div id="panel-pipeline" style="display:none">
        <div class="card">
          <div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M7 12h10M11 18h2"/></svg><span class="card-title">Submission Pipeline</span><span class="card-sub">7 items need action</span></div>
          <div class="card-body">
            <div class="pipeline-row">
              <div class="pipeline-col"><div class="pipeline-col-head"><span class="stage-dot" style="background:#94A3B8"></span><span class="stage-label">Draft</span><span class="stage-count">3</span></div><div class="pipe-card"><div class="pipe-card-title">SLM – Filipino 8: Maikling Kuwento</div><div class="pipe-card-meta"><span class="chip chip-gray">Grade 8</span><span class="chip chip-gray">Filipino</span></div></div><div class="pipe-card"><div class="pipe-card-title">Worksheet – Math 4: Fractions Review</div><div class="pipe-card-meta"><span class="chip chip-gray">Grade 4</span><span class="chip chip-gray">Math</span></div></div><div class="pipe-card"><div class="pipe-card-title">Video – Science 6: Water Cycle</div><div class="pipe-card-meta"><span class="chip chip-gray">Grade 6</span><span class="chip chip-gray">Science</span></div></div></div>
              <div class="pipeline-col"><div class="pipeline-col-head"><span class="stage-dot" style="background:#60A5FA"></span><span class="stage-label">Submitted</span><span class="stage-count">4</span></div><div class="pipe-card"><div class="pipe-card-title">TG – English 10 Q2</div><div class="pipe-card-meta"><span class="chip chip-blue">Grade 10</span><span class="chip chip-gray">English</span></div></div><div class="pipe-card"><div class="pipe-card-title">DLL – MAPEH 7 Week 3</div><div class="pipe-card-meta"><span class="chip chip-blue">Grade 7</span><span class="chip chip-gray">MAPEH</span></div></div><div class="pipe-card"><div class="pipe-card-title">Assessment – AP 9: Asya</div><div class="pipe-card-meta"><span class="chip chip-blue">Grade 9</span><span class="chip chip-gray">AP</span></div></div><div class="pipe-card"><div class="pipe-card-title">SLM – SHS Earth Science</div><div class="pipe-card-meta"><span class="chip chip-blue">Grade 11</span><span class="chip chip-purple">STEM</span></div></div></div>
              <div class="pipeline-col"><div class="pipeline-col-head"><span class="stage-dot" style="background:#F59E0B"></span><span class="stage-label">Under QA</span><span class="stage-count">5</span></div><div class="pipe-card"><div class="pipe-card-title">SLM – Math 6: Fractions</div><div class="pipe-card-meta"><span class="chip chip-yellow">QA #1042</span><span class="chip chip-gray">14d</span></div></div><div class="pipe-card"><div class="pipe-card-title">Video – Science 9: Mitosis</div><div class="pipe-card-meta"><span class="chip chip-yellow">QA #1039</span><span class="chip chip-gray">9d</span></div></div><div class="pipe-card"><div class="pipe-card-title">LM – SHS Oral Communication</div><div class="pipe-card-meta"><span class="chip chip-yellow">QA #1035</span></div></div><div class="pipe-card"><div class="pipe-card-title">Assessment – Math 8: Algebra</div><div class="pipe-card-meta"><span class="chip chip-yellow">QA #1033</span></div></div><div class="pipe-card"><div class="pipe-card-title">DLP – English 6: Figurative</div><div class="pipe-card-meta"><span class="chip chip-red">⚠ 18d overdue</span></div></div></div>
              <div class="pipeline-col"><div class="pipeline-col-head"><span class="stage-dot" style="background:#10B981"></span><span class="stage-label">Approved</span><span class="stage-count">3</span></div><div class="pipe-card"><div class="pipe-card-title">TG – Math 3: Addition</div><div class="pipe-card-meta"><span class="chip chip-green">Ready to Publish</span></div></div><div class="pipe-card"><div class="pipe-card-title">SLM – Filipino 5: Pabula</div><div class="pipe-card-meta"><span class="chip chip-green">Ready to Publish</span></div></div><div class="pipe-card"><div class="pipe-card-title">Video – AP 9: Asya</div><div class="pipe-card-meta"><span class="chip chip-green">Ready to Publish</span></div></div></div>
              <div class="pipeline-col"><div class="pipeline-col-head"><span class="stage-dot" style="background:#0B4F9C"></span><span class="stage-label">Published</span><span class="stage-count">1,051</span></div><div class="pipe-card"><div class="pipe-card-title">SLM – English 3: Reading</div><div class="pipe-card-meta"><span class="chip chip-blue">760 DL</span></div></div><div class="pipe-card"><div class="pipe-card-title">TG – English 10</div><div class="pipe-card-meta"><span class="chip chip-blue">1,340 DL</span></div></div><div class="pipe-card" style="cursor:default;color:var(--muted);font-size:12px;text-align:center;padding:8px">+ 1,049 more…</div></div>
              <div class="pipeline-col"><div class="pipeline-col-head"><span class="stage-dot" style="background:#F87171"></span><span class="stage-label">Archived</span><span class="stage-count">133</span></div><div class="pipe-card"><div class="pipe-card-title">SLM – Math 4 (SY 2021)</div><div class="pipe-card-meta"><span class="chip chip-red">Outdated</span></div></div><div class="pipe-card"><div class="pipe-card-title">DLL – Science 8 (Old)</div><div class="pipe-card-meta"><span class="chip chip-red">Superseded</span></div></div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- ══ PANEL: RESOURCES TABLE ══ -->
      <div id="panel-resources" style="display:none">
        <div class="card">
          <div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg><span class="card-title">All Resources</span>
            <div class="filter-bar" style="margin-left:auto">
              <input type="search" placeholder="Search…" oninput="filterTable(this.value)"/>
              <select onchange="filterTable()"><option value="">All Status</option><option>Published</option><option>Under Review</option><option>Draft</option><option>Archived</option></select>
              <select><option>All Grades</option><option>Grade 3</option><option>Grade 4</option><option>Grade 5</option><option>Grade 6</option><option>Grade 7</option><option>Grade 8</option><option>Grade 9</option><option>Grade 10</option><option>Grade 11</option></select>
              <button class="btn btn-primary" style="font-size:12px;padding:7px 12px">Export CSV</button>
            </div>
          </div>
          <div class="card-body" style="padding:0">
            <div class="table-wrap"><table id="resource-table"><thead><tr><th>Resource</th><th>Type</th><th>Grade</th><th>Subject</th><th>MELC</th><th>Status</th><th>Downloads</th><th>Updated</th><th>Actions</th></tr></thead><tbody id="res-tbody"></tbody></table></div>
          </div>
        </div>
      </div>

      <!-- ══ PANEL: ANALYTICS ══ -->
      <div id="panel-analytics" style="display:none">
        <div class="kpi-grid" style="margin-bottom:0">
          <div class="kpi-card blue"><div class="kpi-top"><span class="kpi-label">Search Success Rate</span><div class="kpi-icon blue"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></div></div><div class="kpi-value">73.2%</div><div class="kpi-delta up"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>+3.1% vs last month</div></div>
          <div class="kpi-card red"><div class="kpi-top"><span class="kpi-label">Zero-Result Queries</span><div class="kpi-icon red"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg></div></div><div class="kpi-value">418</div><div class="kpi-delta down"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>Top gap: "SLM Grade 2 MTB"</div></div>
          <div class="kpi-card green"><div class="kpi-top"><span class="kpi-label">Avg. Time-to-Download</span><div class="kpi-icon green"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div><div class="kpi-value">1m 42s</div><div class="kpi-delta up"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>Improved 18s</div></div>
          <div class="kpi-card yellow"><div class="kpi-top"><span class="kpi-label">Repeat Sessions</span><div class="kpi-icon yellow"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></div></div><div class="kpi-value">62.8%</div><div class="kpi-delta neu">Stable this week</div></div>
        </div>
        <div class="charts-grid">
          <div class="card"><div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg><span class="card-title">Downloads by Resource Type (30d)</span></div><div class="card-body"><div class="chart-wrap"><canvas id="typeChart"></canvas></div></div></div>
          <div class="card"><div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg><span class="card-title">Top Queries with Zero Results</span></div><div class="card-body"><div class="progress-row"><div class="prog-item"><div class="prog-head"><span class="prog-label">SLM Grade 2 MTB</span><span class="prog-val">87</span></div><div class="prog-bar"><div class="prog-fill" style="width:87%;background:#F87171"></div></div></div><div class="prog-item"><div class="prog-head"><span class="prog-label">Kinder Filipino SLM</span><span class="prog-val">74</span></div><div class="prog-bar"><div class="prog-fill" style="width:74%;background:#FBBF24"></div></div></div><div class="prog-item"><div class="prog-head"><span class="prog-label">Grade 1 Math video</span><span class="prog-val">61</span></div><div class="prog-bar"><div class="prog-fill" style="width:61%;background:#FBBF24"></div></div></div><div class="prog-item"><div class="prog-head"><span class="prog-label">EsP 6 worksheet</span><span class="prog-val">48</span></div><div class="prog-bar"><div class="prog-fill" style="width:48%;background:#60A5FA"></div></div></div><div class="prog-item"><div class="prog-head"><span class="prog-label">TLE Grade 7 ICT</span><span class="prog-val">39</span></div><div class="prog-bar"><div class="prog-fill" style="width:39%;background:#60A5FA"></div></div></div></div></div></div>
        </div>
        <div class="card"><div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg><span class="card-title">Contribution Funnel — SY 2025–2026</span></div><div class="card-body"><div class="chart-wrap" style="height:180px"><canvas id="funnelChart"></canvas></div></div></div>
      </div>

      <!-- ══ PANEL: QA TOOLS ══ -->
      <div id="panel-qa" style="display:none">
        <div class="card" style="margin-bottom:16px">
          <div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg><span class="card-title">QA Tools &amp; Rubrics</span><span class="card-sub">Carcar City Division — Quality Assurance Office</span></div>
          <div class="card-body"><div class="qa-grid"><a class="qa-card" href="#"><div class="qa-icon-box" style="background:#EEF5FF">📋</div><div><div class="qa-card-title">General QA Rubric</div><div class="qa-card-desc">Standard evaluation form for all LR types. MELC-alignment, accuracy, design.</div></div></a><a class="qa-card" href="#"><div class="qa-icon-box" style="background:#ECFDF5">📄</div><div><div class="qa-card-title">SLM Checklist</div><div class="qa-card-desc">Self-Learning Module completeness and accessibility checklist.</div></div></a><a class="qa-card" href="#"><div class="qa-icon-box" style="background:#FFFBEB">🎬</div><div><div class="qa-card-title">Video QA Form</div><div class="qa-card-desc">Caption accuracy, content correctness, production quality rubric.</div></div></a><a class="qa-card" href="#"><div class="qa-icon-box" style="background:#F5F3FF">📊</div><div><div class="qa-card-title">Assessment Bank QA</div><div class="qa-card-desc">Item analysis, Bloom's taxonomy alignment, item format check.</div></div></a><a class="qa-card" href="#"><div class="qa-icon-box" style="background:#FEF2F2">📁</div><div><div class="qa-card-title">Submission Template</div><div class="qa-card-desc">Standard cover sheet and metadata template for all submissions.</div></div></a><a class="qa-card" href="#"><div class="qa-icon-box" style="background:#EEF5FF">🏅</div><div><div class="qa-card-title">QA Certification Guide</div><div class="qa-card-desc">Step-by-step guide for QA reviewers. Approval workflow and sign-off.</div></div></a></div></div>
        </div>
        <div class="card">
          <div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg><span class="card-title">Pending QA Queue</span><span class="card-sub">47 items · 7 overdue</span></div>
          <div class="card-body" style="padding:0"><div class="table-wrap"><table><thead><tr><th>Resource</th><th>Submitted By</th><th>Type</th><th>Days in QA</th><th>Reviewer</th><th>Action</th></tr></thead><tbody><tr><td><div class="resource-title">DLP – English 6: Figurative Language</div><div class="resource-meta">M6EN-Ia-1 · Grade 6</div></td><td>J. Reyes</td><td><span class="chip chip-gray">DLP</span></td><td><span style="color:var(--red);font-weight:700">18d ⚠</span></td><td><span style="color:var(--muted)">Unassigned</span></td><td><div class="action-row"><button class="tbl-btn primary">Assign</button><button class="tbl-btn">View</button></div></td></tr><tr><td><div class="resource-title">SLM – Math 6: Fractions</div><div class="resource-meta">M6NS-Ia-1 · Grade 6</div></td><td>C. Dela Cruz</td><td><span class="chip chip-blue">SLM</span></td><td><span style="color:var(--yellow);font-weight:700">14d</span></td><td>L. Navarro</td><td><div class="action-row"><button class="tbl-btn primary">Review</button><button class="tbl-btn">View</button></div></td></tr><tr><td><div class="resource-title">Video – Science 9: Mitosis</div><div class="resource-meta">S9LT-IIa-b-1 · Grade 9</div></td><td>R. Bautista</td><td><span class="chip chip-purple">Video</span></td><td>9d</td><td>M. Santos</td><td><div class="action-row"><button class="tbl-btn primary">Review</button><button class="tbl-btn">View</button></div></td></tr><tr><td><div class="resource-title">LM – SHS Oral Communication</div><div class="resource-meta">EN11/12OC-Ia-1 · Grade 11</div></td><td>A. Morales</td><td><span class="chip chip-green">LM</span></td><td>6d</td><td>P. Villanueva</td><td><div class="action-row"><button class="tbl-btn primary">Review</button><button class="tbl-btn">View</button></div></td></tr></tbody></table></div></div>
        </div>
      </div>

      <!-- ══ PANEL: NOTIFICATIONS ══ -->
      <div id="panel-notifications" style="display:none">
        <div class="card">
          <div class="card-header"><svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg><span class="card-title">Notifications &amp; Activity</span>
            <div class="tabs" style="margin-left:auto"><button class="tab active">All</button><button class="tab">QA</button><button class="tab">System</button></div>
            <button class="btn btn-ghost" style="font-size:12px;padding:6px 10px;margin-left:8px">Mark all read</button>
          </div>
          <div class="card-body" style="padding-top:8px"><div class="feed" id="feed-full"></div></div>
        </div>
      </div>

      <!-- ══ PANEL: USER MANAGEMENT ══ (NEW) -->
      <div id="panel-users" style="display:none">

        <!-- Stats bar -->
        <div class="um-stats-bar" id="um-stats-bar">
          <div class="um-stat"><div class="um-stat-val" id="stat-total">—</div><div class="um-stat-label">Total Users</div></div>
          <div class="um-stat"><div class="um-stat-val" id="stat-active" style="color:var(--green)">—</div><div class="um-stat-label">Active</div></div>
          <div class="um-stat"><div class="um-stat-val" id="stat-pending" style="color:var(--yellow)">—</div><div class="um-stat-label">Pending</div></div>
          <div class="um-stat"><div class="um-stat-val" id="stat-suspended" style="color:var(--red)">—</div><div class="um-stat-label">Suspended</div></div>
          <div class="um-stat"><div class="um-stat-val" id="stat-guest" style="color:var(--muted)">—</div><div class="um-stat-label">Guests</div></div>
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

            <!-- Sub-tabs: Pending / All Users -->
            <div class="um-tabs">
              <button class="um-tab active" id="um-tab-pending" onclick="umSwitchTab('pending')">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                Pending Approval
                <span class="um-tab-badge" id="um-pending-count">0</span>
              </button>
              <button class="um-tab" id="um-tab-all" onclick="umSwitchTab('all')">
                <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                All Users
                <span class="um-tab-badge green" id="um-all-count">0</span>
              </button>
            </div>

            <!-- ── PENDING SUB-PANEL ── -->
            <div class="um-section active" id="um-section-pending">
              <div class="um-filter-bar">
                <input type="search" id="pending-search" placeholder="Search by name, email, or ID…" oninput="umLoadPending()"/>
                <select id="pending-role-filter" onchange="umLoadPending()">
                  <option value="">All Roles</option>
                  <option value="teacher">Teacher</option>
                  <option value="school-head">School Head</option>
                  <option value="developer">Content Developer</option>
                </select>
                <span style="font-size:12px;color:var(--muted);margin-left:auto" id="pending-result-count"></span>
              </div>
              <div id="pending-list" class="applicant-grid"></div>
            </div>

            <!-- ── ALL USERS SUB-PANEL ── -->
            <div class="um-section" id="um-section-all">
              <div class="um-filter-bar">
                <input type="search" id="users-search" placeholder="Search by name, email, or ID…" oninput="umLoadUsers()"/>
                <select id="users-role-filter" onchange="umLoadUsers()">
                  <option value="">All Roles</option>
                  <option value="teacher">Teacher</option>
                  <option value="learner">Learner</option>
                  <option value="parent">Parent</option>
                  <option value="school-head">School Head</option>
                  <option value="developer">Developer</option>
                  <option value="guest">Guest</option>
                </select>
                <select id="users-status-filter" onchange="umLoadUsers()">
                  <option value="">All Status</option>
                  <option value="active">Active</option>
                  <option value="pending">Pending</option>
                  <option value="suspended">Suspended</option>
                </select>
                <span style="font-size:12px;color:var(--muted);margin-left:auto" id="users-result-count"></span>
              </div>
              <div class="table-wrap">
                <table id="users-table">
                  <thead>
                    <tr>
                      <th>User</th>
                      <th>Role</th>
                      <th>Status</th>
                      <th>Region</th>
                      <th>2FA</th>
                      <th>Last Login</th>
                      <th>Joined</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="users-tbody"></tbody>
                </table>
              </div>
            </div>

          </div><!-- /card-body -->
        </div><!-- /card -->
      </div><!-- /panel-users -->

    </div><!-- /canvas -->
  </div><!-- /main -->
</div><!-- /shell -->

<!-- Reject Confirm Modal -->
<div class="um-modal-overlay" id="reject-modal">
  <div class="um-modal">
    <div class="um-modal-title">Reject Application</div>
    <div class="um-modal-body">This will permanently delete the pending registration. The applicant will need to register again.<br><br>Reason (optional):</div>
    <textarea id="reject-reason" placeholder="e.g. Invalid employee ID, incomplete requirements…"></textarea>
    <div class="um-modal-actions">
      <button class="btn btn-ghost" onclick="closeRejectModal()">Cancel</button>
      <button class="btn-reject" id="reject-confirm-btn" onclick="confirmReject()">
        <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
        Reject Application
      </button>
    </div>
  </div>
</div>

<!-- Toast -->
<div id="um-toast">
  <svg width="15" height="15" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24" id="toast-icon"><path d="M20 6 9 17l-5-5"/></svg>
  <span id="um-toast-msg"></span>
</div>

<script src="assets/js/manage.js"></script>
</body>
</html>