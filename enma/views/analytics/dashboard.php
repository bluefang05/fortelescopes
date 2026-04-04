<?php
/** @var array $data */
$labelsJson = $data['chart_labels_json'] ?? '[]';
$valuesJson = $data['chart_values_json'] ?? '[]';
$user = $data['user']['username'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ENMA - Analytics & Security</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --bg: #f0f4f9;
            --panel: #ffffff;
            --line: #dde6f3;
            --muted: #5c6f88;
            --ink: #17283f;
            --brand: #0e3a77;
        }
        body { background: var(--bg); color: var(--ink); }
        .shell { min-height: 100vh; }
        .sidebar { min-height: 100vh; background: #1d2f45; color: #d8e1ef; }
        .sidebar a { color: #d8e1ef; text-decoration: none; display: block; padding: 10px 14px; border-radius: 8px; }
        .sidebar a:hover, .sidebar a.active { background: #2a4d73; color: #fff; }
        .content { padding: 22px; }
        .card-ui { background: var(--panel); border: 1px solid var(--line); border-radius: 12px; box-shadow: 0 10px 26px rgba(15,38,77,.08); }
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; margin-bottom: 18px; }
        .kpi { padding: 16px; border-left: 5px solid #2f7edb; }
        .kpi h3 { margin: 0; font-size: 1.85rem; font-weight: 800; }
        .kpi p { margin: 0; color: var(--muted); font-size: .8rem; text-transform: uppercase; letter-spacing: .04em; }
        .kpi small { color: var(--muted); }
        .kpi.warn { border-left-color: #d17a00; }
        .kpi.danger { border-left-color: #ba3232; }
        .kpi.ok { border-left-color: #2d9b62; }
        .tabs { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 12px; }
        .tab-btn { border: 1px solid var(--line); background: #fff; color: #355175; border-radius: 999px; padding: 8px 13px; font-weight: 700; }
        .tab-btn.active { background: var(--brand); color: #fff; border-color: var(--brand); }
        .tab-pane { display: none; }
        .tab-pane.active { display: block; }
        .chart-wrap { height: 340px; }
        .mono { font-family: Consolas, Menlo, Monaco, monospace; font-size: .82rem; }
    </style>
</head>
<body>
<div class="container-fluid shell">
    <div class="row">
        <aside class="col-lg-2 sidebar p-3">
            <h5 class="mb-1"><i class="fas fa-shield-alt"></i> ENMA</h5>
            <p class="small text-secondary mb-3">Analytics panel</p>
            <nav class="d-grid gap-1">
                <a href="?tab=overview"><i class="fas fa-home me-2"></i>Overview</a>
                <a href="?action=analytics" class="active"><i class="fas fa-chart-line me-2"></i>Analytics</a>
                <a href="?tab=products"><i class="fas fa-box me-2"></i>Products</a>
                <a href="?tab=posts"><i class="fas fa-newspaper me-2"></i>Posts</a>
                <a href="?tab=views"><i class="fas fa-chart-area me-2"></i>Views</a>
                <a href="?tab=maintenance"><i class="fas fa-tools me-2"></i>Maintenance</a>
                <a href="?tab=overview" class="mt-3 text-danger"><i class="fas fa-arrow-left me-2"></i>Back to panel</a>
            </nav>
        </aside>

        <main class="col-lg-10 content">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2 class="h4 mb-0"><i class="fas fa-globe-americas"></i> Traffic & Security Monitor</h2>
                <span class="badge bg-primary"><?= htmlspecialchars((string) $user) ?></span>
            </div>

            <div class="tabs">
                <button class="tab-btn active" onclick="openTab(event, 'overview')">Executive</button>
                <button class="tab-btn" onclick="openTab(event, 'security')">Security</button>
                <button class="tab-btn" onclick="openTab(event, 'traffic')">Traffic</button>
                <button class="tab-btn" onclick="openTab(event, 'export')">Export</button>
            </div>

            <section id="overview" class="tab-pane active">
                <div class="kpi-grid">
                    <div class="card-ui kpi ok"><p>Total views</p><h3><?= number_format((int) ($data['stats']['total_views'] ?? 0)) ?></h3><small>All time</small></div>
                    <div class="card-ui kpi"><p>Unique visitors</p><h3><?= number_format((int) ($data['stats']['unique_ips'] ?? 0)) ?></h3><small>Unique IP/hash</small></div>
                    <div class="card-ui kpi"><p>Outbound clicks</p><h3><?= number_format((int) ($data['stats']['total_clicks'] ?? 0)) ?></h3><small>Affiliate interactions</small></div>
                    <div class="card-ui kpi ok"><p>Estimated human traffic</p><h3><?= number_format((int) ($data['stats']['human_traffic'] ?? 0)) ?></h3><small>Heuristic</small></div>
                </div>

                <div class="row g-3">
                    <div class="col-lg-8">
                        <div class="card-ui p-3 chart-wrap"><canvas id="trafficChart"></canvas></div>
                    </div>
                    <div class="col-lg-4">
                        <div class="card-ui p-3 h-100">
                            <h6 class="mb-3">Top user agents</h6>
                            <ul class="list-group list-group-flush small">
                                <?php foreach (($data['top_agents'] ?? []) as $agent): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div class="me-2 text-truncate" style="max-width: 220px;" title="<?= htmlspecialchars((string) ($agent['user_agent'] ?? '')) ?>">
                                            <?= htmlspecialchars(substr((string) ($agent['user_agent'] ?? ''), 0, 42)) ?>...
                                        </div>
                                        <span class="badge bg-primary rounded-pill"><?= (int) ($agent['count'] ?? 0) ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </section>

            <section id="security" class="tab-pane">
                <div class="kpi-grid">
                    <div class="card-ui kpi warn"><p>Suspected bots</p><h3><?= number_format((int) ($data['stats']['suspected_bots'] ?? 0)) ?></h3><small>crawler/bot UA</small></div>
                    <div class="card-ui kpi danger"><p>Suspected attacks</p><h3><?= number_format((int) ($data['stats']['suspected_attacks'] ?? 0)) ?></h3><small>sqli/xss/scanners</small></div>
                </div>

                <div class="card-ui p-3">
                    <h6 class="mb-3">Top suspicious IP/hash</h6>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead><tr><th>IP/hash</th><th>Attempts</th><th>Last user agent</th></tr></thead>
                            <tbody>
                            <?php if (empty($data['suspicious_ips'])): ?>
                                <tr><td colspan="3" class="text-muted">No suspicious data detected.</td></tr>
                            <?php else: ?>
                                <?php foreach ($data['suspicious_ips'] as $ip): ?>
                                    <tr>
                                        <td class="text-danger fw-bold"><?= htmlspecialchars((string) ($ip['ip_address'] ?? '')) ?></td>
                                        <td><span class="badge bg-danger"><?= (int) ($ip['attempts'] ?? 0) ?></span></td>
                                        <td><small><?= htmlspecialchars(substr((string) ($ip['last_agent'] ?? ''), 0, 70)) ?>...</small></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="traffic" class="tab-pane">
                <div class="card-ui p-3">
                    <h6 class="mb-3">Recent traffic rows</h6>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover mb-0 small">
                            <thead><tr><th>ID</th><th>Date</th><th>URL</th><th>IP/hash</th><th>User agent</th></tr></thead>
                            <tbody>
                            <?php foreach (($data['recent_logs'] ?? []) as $log): ?>
                                <tr>
                                    <td><?= (int) ($log['id'] ?? 0) ?></td>
                                    <td><?= htmlspecialchars((string) ($log['created_at'] ?? '')) ?></td>
                                    <td><code><?= htmlspecialchars(substr((string) ($log['url'] ?? ''), 0, 60)) ?>...</code></td>
                                    <td><?= htmlspecialchars((string) ($log['ip_address'] ?? '')) ?></td>
                                    <td><small><?= htmlspecialchars(substr((string) ($log['user_agent'] ?? ''), 0, 50)) ?>...</small></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <section id="export" class="tab-pane">
                <div class="card-ui p-3 mb-3">
                    <h6>Raw JSON for AI analysis</h6>
                    <textarea class="form-control mono" rows="16" readonly><?= htmlspecialchars((string) ($data['raw_json'] ?? '[]')) ?></textarea>
                    <div class="mt-2 d-flex gap-2">
                        <button class="btn btn-success btn-sm" onclick="copyToClipboard()">Copy JSON</button>
                        <a href="?tab=maintenance" class="btn btn-primary btn-sm">Open maintenance</a>
                    </div>
                </div>
            </section>
        </main>
    </div>
</div>

<script>
function openTab(evt, tabName) {
    document.querySelectorAll('.tab-pane').forEach(el => el.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(el => el.classList.remove('active'));
    document.getElementById(tabName).classList.add('active');
    evt.currentTarget.classList.add('active');
    if (tabName === 'overview' && !window.chartRendered) renderChart();
}

function renderChart() {
    const ctx = document.getElementById('trafficChart');
    if (!ctx) return;
    new Chart(ctx.getContext('2d'), {
        type: 'line',
        data: {
            labels: <?= $labelsJson ?>,
            datasets: [{
                label: 'Views per day',
                data: <?= $valuesJson ?>,
                borderColor: 'rgba(14, 58, 119, 1)',
                backgroundColor: 'rgba(14, 58, 119, .12)',
                borderWidth: 3,
                fill: true,
                tension: .35,
                pointRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: { y: { beginAtZero: true } }
        }
    });
    window.chartRendered = true;
}

function copyToClipboard() {
    const textarea = document.querySelector('.mono');
    if (!textarea) return;
    textarea.select();
    document.execCommand('copy');
}

document.addEventListener('DOMContentLoaded', renderChart);
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
