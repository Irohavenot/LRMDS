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
      <a class="nav-item" href="index.php">
        <svg class="nav-icon" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/></svg>
        Back to Site
      </a>
    </div>

    <div class="sidebar-footer">
      <div class="user-chip">
        <div class="user-avatar">MA</div>
        <div>
          <div class="user-name">Ma. Santos</div>
          <div class="user-role">Curriculum Head · Carcar</div>
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

        <!-- KPI Row -->
        <div class="kpi-grid">
          <div class="kpi-card blue">
            <div class="kpi-top">
              <span class="kpi-label">Total Resources</span>
              <div class="kpi-icon blue">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
              </div>
            </div>
            <div class="kpi-value">1,284</div>
            <div class="kpi-delta up">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>
              +48 this month
            </div>
          </div>
          <div class="kpi-card green">
            <div class="kpi-top">
              <span class="kpi-label">QA Passed</span>
              <div class="kpi-icon green">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
              </div>
            </div>
            <div class="kpi-value">1,051</div>
            <div class="kpi-delta up">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>
              81.9% pass rate
            </div>
          </div>
          <div class="kpi-card yellow">
            <div class="kpi-top">
              <span class="kpi-label">Pending QA</span>
              <div class="kpi-icon yellow">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
              </div>
            </div>
            <div class="kpi-value">47</div>
            <div class="kpi-delta down">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
              7 overdue &gt;14 days
            </div>
          </div>
          <div class="kpi-card red">
            <div class="kpi-top">
              <span class="kpi-label">Downloads (30d)</span>
              <div class="kpi-icon red">
                <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
              </div>
            </div>
            <div class="kpi-value">28.4K</div>
            <div class="kpi-delta up">
              <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>
              +12% vs last month
            </div>
          </div>
        </div>

        <!-- Charts + Activity -->
        <div class="row row-3-2">
          <div class="card">
            <div class="card-header">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
              <span class="card-title">Downloads Over Time</span>
              <div class="tabs" style="margin-left:auto">
                <button class="tab active" onclick="switchChartPeriod(this,'7d')">7d</button>
                <button class="tab" onclick="switchChartPeriod(this,'30d')">30d</button>
                <button class="tab" onclick="switchChartPeriod(this,'90d')">90d</button>
              </div>
            </div>
            <div class="card-body">
              <div class="chart-wrap">
                <canvas id="dlChart"></canvas>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
              <span class="card-title">Activity Feed</span>
              <span class="card-sub">Today</span>
            </div>
            <div class="card-body" style="padding-top:8px">
              <div class="feed" id="feed-short"></div>
            </div>
          </div>
        </div>

        <!-- By Subject + Pipeline Snapshot -->
        <div class="row row-3-2">
          <div class="card">
            <div class="card-header">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
              <span class="card-title">Resources by Learning Area</span>
            </div>
            <div class="card-body">
              <div class="chart-wrap" style="height:190px">
                <canvas id="subjectChart"></canvas>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-header">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
              <span class="card-title">Pipeline Snapshot</span>
            </div>
            <div class="card-body">
              <div class="progress-row">
                <div class="prog-item">
                  <div class="prog-head"><span class="prog-label">Draft</span><span class="prog-val">23</span></div>
                  <div class="prog-bar"><div class="prog-fill" style="width:45%;background:#94A3B8"></div></div>
                </div>
                <div class="prog-item">
                  <div class="prog-head"><span class="prog-label">Submitted</span><span class="prog-val">18</span></div>
                  <div class="prog-bar"><div class="prog-fill" style="width:35%;background:#60A5FA"></div></div>
                </div>
                <div class="prog-item">
                  <div class="prog-head"><span class="prog-label">Under QA</span><span class="prog-val">47</span></div>
                  <div class="prog-bar"><div class="prog-fill" style="width:65%;background:#F59E0B"></div></div>
                </div>
                <div class="prog-item">
                  <div class="prog-head"><span class="prog-label">Approved</span><span class="prog-val">12</span></div>
                  <div class="prog-bar"><div class="prog-fill" style="width:24%;background:#10B981"></div></div>
                </div>
                <div class="prog-item">
                  <div class="prog-head"><span class="prog-label">Published</span><span class="prog-val">1,051</span></div>
                  <div class="prog-bar"><div class="prog-fill" style="width:90%;background:#0B4F9C"></div></div>
                </div>
                <div class="prog-item">
                  <div class="prog-head"><span class="prog-label">Archived</span><span class="prog-val">133</span></div>
                  <div class="prog-bar"><div class="prog-fill" style="width:20%;background:#F87171"></div></div>
                </div>
              </div>
            </div>
          </div>
        </div>

      </div><!-- /panel-dashboard -->


      <!-- ══ PANEL: PIPELINE ══ -->
      <div id="panel-pipeline" style="display:none">
        <div class="card">
          <div class="card-header">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M3 6h18M7 12h10M11 18h2"/></svg>
            <span class="card-title">Submission Pipeline</span>
            <span class="card-sub">7 items need action</span>
          </div>
          <div class="card-body">
            <div class="pipeline-row">
              <!-- Draft -->
              <div class="pipeline-col">
                <div class="pipeline-col-head">
                  <span class="stage-dot" style="background:#94A3B8"></span>
                  <span class="stage-label">Draft</span>
                  <span class="stage-count">3</span>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">SLM – Filipino 8: Maikling Kuwento</div>
                  <div class="pipe-card-meta"><span class="chip chip-gray">Grade 8</span><span class="chip chip-gray">Filipino</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">Worksheet – Math 4: Fractions Review</div>
                  <div class="pipe-card-meta"><span class="chip chip-gray">Grade 4</span><span class="chip chip-gray">Math</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">Video – Science 6: Water Cycle</div>
                  <div class="pipe-card-meta"><span class="chip chip-gray">Grade 6</span><span class="chip chip-gray">Science</span></div>
                </div>
              </div>
              <!-- Submitted -->
              <div class="pipeline-col">
                <div class="pipeline-col-head">
                  <span class="stage-dot" style="background:#60A5FA"></span>
                  <span class="stage-label">Submitted</span>
                  <span class="stage-count">4</span>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">TG – English 10 Q2</div>
                  <div class="pipe-card-meta"><span class="chip chip-blue">Grade 10</span><span class="chip chip-gray">English</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">DLL – MAPEH 7 Week 3</div>
                  <div class="pipe-card-meta"><span class="chip chip-blue">Grade 7</span><span class="chip chip-gray">MAPEH</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">Assessment – AP 9: Asya</div>
                  <div class="pipe-card-meta"><span class="chip chip-blue">Grade 9</span><span class="chip chip-gray">AP</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">SLM – SHS Earth Science</div>
                  <div class="pipe-card-meta"><span class="chip chip-blue">Grade 11</span><span class="chip chip-purple">STEM</span></div>
                </div>
              </div>
              <!-- Under QA -->
              <div class="pipeline-col">
                <div class="pipeline-col-head">
                  <span class="stage-dot" style="background:#F59E0B"></span>
                  <span class="stage-label">Under QA</span>
                  <span class="stage-count">5</span>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">SLM – Math 6: Fractions</div>
                  <div class="pipe-card-meta"><span class="chip chip-yellow">QA #1042</span><span class="chip chip-gray">14d</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">Video – Science 9: Mitosis</div>
                  <div class="pipe-card-meta"><span class="chip chip-yellow">QA #1039</span><span class="chip chip-gray">9d</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">LM – SHS Oral Communication</div>
                  <div class="pipe-card-meta"><span class="chip chip-yellow">QA #1035</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">Assessment – Math 8: Algebra</div>
                  <div class="pipe-card-meta"><span class="chip chip-yellow">QA #1033</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">DLP – English 6: Figurative</div>
                  <div class="pipe-card-meta"><span class="chip chip-red">⚠ 18d overdue</span></div>
                </div>
              </div>
              <!-- Approved -->
              <div class="pipeline-col">
                <div class="pipeline-col-head">
                  <span class="stage-dot" style="background:#10B981"></span>
                  <span class="stage-label">Approved</span>
                  <span class="stage-count">3</span>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">TG – Math 3: Addition</div>
                  <div class="pipe-card-meta"><span class="chip chip-green">Ready to Publish</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">SLM – Filipino 5: Pabula</div>
                  <div class="pipe-card-meta"><span class="chip chip-green">Ready to Publish</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">Video – AP 9: Asya</div>
                  <div class="pipe-card-meta"><span class="chip chip-green">Ready to Publish</span></div>
                </div>
              </div>
              <!-- Published -->
              <div class="pipeline-col">
                <div class="pipeline-col-head">
                  <span class="stage-dot" style="background:#0B4F9C"></span>
                  <span class="stage-label">Published</span>
                  <span class="stage-count">1,051</span>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">SLM – English 3: Reading</div>
                  <div class="pipe-card-meta"><span class="chip chip-blue">760 DL</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">TG – English 10</div>
                  <div class="pipe-card-meta"><span class="chip chip-blue">1,340 DL</span></div>
                </div>
                <div class="pipe-card" style="cursor:default;color:var(--muted);font-size:12px;text-align:center;padding:8px">
                  + 1,049 more…
                </div>
              </div>
              <!-- Archived -->
              <div class="pipeline-col">
                <div class="pipeline-col-head">
                  <span class="stage-dot" style="background:#F87171"></span>
                  <span class="stage-label">Archived</span>
                  <span class="stage-count">133</span>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">SLM – Math 4 (SY 2021)</div>
                  <div class="pipe-card-meta"><span class="chip chip-red">Outdated</span></div>
                </div>
                <div class="pipe-card">
                  <div class="pipe-card-title">DLL – Science 8 (Old)</div>
                  <div class="pipe-card-meta"><span class="chip chip-red">Superseded</span></div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div><!-- /panel-pipeline -->


      <!-- ══ PANEL: RESOURCES TABLE ══ -->
      <div id="panel-resources" style="display:none">
        <div class="card">
          <div class="card-header">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
            <span class="card-title">All Resources</span>
            <div class="filter-bar" style="margin-left:auto">
              <input type="search" placeholder="Search…" oninput="filterTable(this.value)"/>
              <select onchange="filterTable()">
                <option value="">All Status</option>
                <option>Published</option><option>Under Review</option><option>Draft</option><option>Archived</option>
              </select>
              <select>
                <option>All Grades</option>
                <option>Grade 3</option><option>Grade 4</option><option>Grade 5</option>
                <option>Grade 6</option><option>Grade 7</option><option>Grade 8</option>
                <option>Grade 9</option><option>Grade 10</option><option>Grade 11</option>
              </select>
              <button class="btn btn-primary" style="font-size:12px;padding:7px 12px">Export CSV</button>
            </div>
          </div>
          <div class="card-body" style="padding:0">
            <div class="table-wrap">
              <table id="resource-table">
                <thead>
                  <tr>
                    <th>Resource</th>
                    <th>Type</th>
                    <th>Grade</th>
                    <th>Subject</th>
                    <th>MELC</th>
                    <th>Status</th>
                    <th>Downloads</th>
                    <th>Updated</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody id="res-tbody"></tbody>
              </table>
            </div>
          </div>
        </div>
      </div><!-- /panel-resources -->


      <!-- ══ PANEL: ANALYTICS ══ -->
      <div id="panel-analytics" style="display:none">
        <div class="kpi-grid" style="margin-bottom:0">
          <div class="kpi-card blue">
            <div class="kpi-top"><span class="kpi-label">Search Success Rate</span><div class="kpi-icon blue"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg></div></div>
            <div class="kpi-value">73.2%</div>
            <div class="kpi-delta up"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>+3.1% vs last month</div>
          </div>
          <div class="kpi-card red">
            <div class="kpi-top"><span class="kpi-label">Zero-Result Queries</span><div class="kpi-icon red"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg></div></div>
            <div class="kpi-value">418</div>
            <div class="kpi-delta down"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>Top gap: "SLM Grade 2 MTB"</div>
          </div>
          <div class="kpi-card green">
            <div class="kpi-top"><span class="kpi-label">Avg. Time-to-Download</span><div class="kpi-icon green"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div></div>
            <div class="kpi-value">1m 42s</div>
            <div class="kpi-delta up"><svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path d="M18 15l-6-6-6 6"/></svg>Improved 18s</div>
          </div>
          <div class="kpi-card yellow">
            <div class="kpi-top"><span class="kpi-label">Repeat Sessions</span><div class="kpi-icon yellow"><svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M17 1l4 4-4 4"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><path d="M7 23l-4-4 4-4"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg></div></div>
            <div class="kpi-value">62.8%</div>
            <div class="kpi-delta neu">Stable this week</div>
          </div>
        </div>

        <div class="charts-grid">
          <div class="card">
            <div class="card-header">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
              <span class="card-title">Downloads by Resource Type (30d)</span>
            </div>
            <div class="card-body">
              <div class="chart-wrap"><canvas id="typeChart"></canvas></div>
            </div>
          </div>
          <div class="card">
            <div class="card-header">
              <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><path d="M12 8v4l3 3"/></svg>
              <span class="card-title">Top Queries with Zero Results</span>
            </div>
            <div class="card-body">
              <div class="progress-row">
                <div class="prog-item"><div class="prog-head"><span class="prog-label">SLM Grade 2 MTB</span><span class="prog-val">87</span></div><div class="prog-bar"><div class="prog-fill" style="width:87%;background:#F87171"></div></div></div>
                <div class="prog-item"><div class="prog-head"><span class="prog-label">Kinder Filipino SLM</span><span class="prog-val">74</span></div><div class="prog-bar"><div class="prog-fill" style="width:74%;background:#FBBF24"></div></div></div>
                <div class="prog-item"><div class="prog-head"><span class="prog-label">Grade 1 Math video</span><span class="prog-val">61</span></div><div class="prog-bar"><div class="prog-fill" style="width:61%;background:#FBBF24"></div></div></div>
                <div class="prog-item"><div class="prog-head"><span class="prog-label">EsP 6 worksheet</span><span class="prog-val">48</span></div><div class="prog-bar"><div class="prog-fill" style="width:48%;background:#60A5FA"></div></div></div>
                <div class="prog-item"><div class="prog-head"><span class="prog-label">TLE Grade 7 ICT</span><span class="prog-val">39</span></div><div class="prog-bar"><div class="prog-fill" style="width:39%;background:#60A5FA"></div></div></div>
              </div>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><rect x="3" y="3" width="18" height="18" rx="2"/><path d="M3 9h18M9 21V9"/></svg>
            <span class="card-title">Contribution Funnel — SY 2025–2026</span>
          </div>
          <div class="card-body">
            <div class="chart-wrap" style="height:180px"><canvas id="funnelChart"></canvas></div>
          </div>
        </div>
      </div><!-- /panel-analytics -->


      <!-- ══ PANEL: QA TOOLS ══ -->
      <div id="panel-qa" style="display:none">
        <div class="card" style="margin-bottom:16px">
          <div class="card-header">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10Z"/></svg>
            <span class="card-title">QA Tools &amp; Rubrics</span>
            <span class="card-sub">Carcar City Division — Quality Assurance Office</span>
          </div>
          <div class="card-body">
            <div class="qa-grid">
              <a class="qa-card" href="#">
                <div class="qa-icon-box" style="background:#EEF5FF">📋</div>
                <div><div class="qa-card-title">General QA Rubric</div><div class="qa-card-desc">Standard evaluation form for all LR types. MELC-alignment, accuracy, design.</div></div>
              </a>
              <a class="qa-card" href="#">
                <div class="qa-icon-box" style="background:#ECFDF5">📄</div>
                <div><div class="qa-card-title">SLM Checklist</div><div class="qa-card-desc">Self-Learning Module completeness and accessibility checklist.</div></div>
              </a>
              <a class="qa-card" href="#">
                <div class="qa-icon-box" style="background:#FFFBEB">🎬</div>
                <div><div class="qa-card-title">Video QA Form</div><div class="qa-card-desc">Caption accuracy, content correctness, production quality rubric.</div></div>
              </a>
              <a class="qa-card" href="#">
                <div class="qa-icon-box" style="background:#F5F3FF">📊</div>
                <div><div class="qa-card-title">Assessment Bank QA</div><div class="qa-card-desc">Item analysis, Bloom's taxonomy alignment, item format check.</div></div>
              </a>
              <a class="qa-card" href="#">
                <div class="qa-icon-box" style="background:#FEF2F2">📁</div>
                <div><div class="qa-card-title">Submission Template</div><div class="qa-card-desc">Standard cover sheet and metadata template for all submissions.</div></div>
              </a>
              <a class="qa-card" href="#">
                <div class="qa-icon-box" style="background:#EEF5FF">🏅</div>
                <div><div class="qa-card-title">QA Certification Guide</div><div class="qa-card-desc">Step-by-step guide for QA reviewers. Approval workflow and sign-off.</div></div>
              </a>
            </div>
          </div>
        </div>

        <!-- QA queue mini-table -->
        <div class="card">
          <div class="card-header">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
            <span class="card-title">Pending QA Queue</span>
            <span class="card-sub">47 items · 7 overdue</span>
          </div>
          <div class="card-body" style="padding:0">
            <div class="table-wrap">
              <table>
                <thead>
                  <tr><th>Resource</th><th>Submitted By</th><th>Type</th><th>Days in QA</th><th>Reviewer</th><th>Action</th></tr>
                </thead>
                <tbody>
                  <tr>
                    <td><div class="resource-title">DLP – English 6: Figurative Language</div><div class="resource-meta">M6EN-Ia-1 · Grade 6</div></td>
                    <td>J. Reyes</td>
                    <td><span class="chip chip-gray">DLP</span></td>
                    <td><span style="color:var(--red);font-weight:700">18d ⚠</span></td>
                    <td><span style="color:var(--muted)">Unassigned</span></td>
                    <td><div class="action-row"><button class="tbl-btn primary">Assign</button><button class="tbl-btn">View</button></div></td>
                  </tr>
                  <tr>
                    <td><div class="resource-title">SLM – Math 6: Fractions</div><div class="resource-meta">M6NS-Ia-1 · Grade 6</div></td>
                    <td>C. Dela Cruz</td>
                    <td><span class="chip chip-blue">SLM</span></td>
                    <td><span style="color:var(--yellow);font-weight:700">14d</span></td>
                    <td>L. Navarro</td>
                    <td><div class="action-row"><button class="tbl-btn primary">Review</button><button class="tbl-btn">View</button></div></td>
                  </tr>
                  <tr>
                    <td><div class="resource-title">Video – Science 9: Mitosis</div><div class="resource-meta">S9LT-IIa-b-1 · Grade 9</div></td>
                    <td>R. Bautista</td>
                    <td><span class="chip chip-purple">Video</span></td>
                    <td>9d</td>
                    <td>M. Santos</td>
                    <td><div class="action-row"><button class="tbl-btn primary">Review</button><button class="tbl-btn">View</button></div></td>
                  </tr>
                  <tr>
                    <td><div class="resource-title">LM – SHS Oral Communication</div><div class="resource-meta">EN11/12OC-Ia-1 · Grade 11</div></td>
                    <td>A. Morales</td>
                    <td><span class="chip chip-green">LM</span></td>
                    <td>6d</td>
                    <td>P. Villanueva</td>
                    <td><div class="action-row"><button class="tbl-btn primary">Review</button><button class="tbl-btn">View</button></div></td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div><!-- /panel-qa -->


      <!-- ══ PANEL: NOTIFICATIONS ══ -->
      <div id="panel-notifications" style="display:none">
        <div class="card">
          <div class="card-header">
            <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <span class="card-title">Notifications &amp; Activity</span>
            <div class="tabs" style="margin-left:auto">
              <button class="tab active">All</button>
              <button class="tab">QA</button>
              <button class="tab">System</button>
            </div>
            <button class="btn btn-ghost" style="font-size:12px;padding:6px 10px;margin-left:8px">Mark all read</button>
          </div>
          <div class="card-body" style="padding-top:8px">
            <div class="feed" id="feed-full"></div>
          </div>
        </div>
      </div><!-- /panel-notifications -->

    </div><!-- /canvas -->
  </div><!-- /main -->
</div><!-- /shell -->


<script src="assets/js/manage.js"></script>
</body>
</html>