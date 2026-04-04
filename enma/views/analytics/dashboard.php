<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ENMA - Analytics & Seguridad</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <style>
        body { background-color: #f4f6f9; }
        .sidebar { min-height: 100vh; background: #343a40; color: white; }
        .sidebar a { color: #cfd2d6; text-decoration: none; padding: 10px 15px; display: block; }
        .sidebar a:hover, .sidebar a.active { background: #495057; color: white; }
        .content { padding: 20px; }
        
        /* Dashboard Tabs */
        .dashboard-tabs { display: flex; gap: 10px; margin-bottom: 20px; border-bottom: 2px solid #dee2e6; padding-bottom: 10px; flex-wrap: wrap; }
        .tab-btn { padding: 10px 20px; border: none; background: #e9ecef; cursor: pointer; border-radius: 5px; font-weight: bold; transition: 0.3s; color: #495057; }
        .tab-btn:hover { background: #dee2e6; }
        .tab-btn.active { background: #0d6efd; color: white; }
        .tab-content { display: none; animation: fadeIn 0.4s; }
        .tab-content.active { display: block; }
        
        /* KPI Cards */
        .kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .kpi-card { background: white; padding: 25px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.05); text-align: center; border-left: 5px solid #0d6efd; }
        .kpi-card.danger { border-left-color: #dc3545; }
        .kpi-card.warning { border-left-color: #ffc107; }
        .kpi-card.success { border-left-color: #198754; }
        .kpi-value { font-size: 2.2em; font-weight: bold; color: #212529; margin: 10px 0; }
        .kpi-label { color: #6c757d; font-size: 0.85em; text-transform: uppercase; letter-spacing: 1px; }
        
        /* Charts & Containers */
        .chart-container { position: relative; height: 350px; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .data-dump { background: #1e1e1e; color: #00ff9d; padding: 20px; border-radius: 8px; font-family: 'Consolas', monospace; font-size: 0.85em; max-height: 500px; overflow-y: auto; white-space: pre-wrap; border: 1px solid #333; }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        
        .table-responsive { background: white; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .badge-bot { background-color: #17a2b8; }
        .badge-attack { background-color: #dc3545; }
    </style>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar p-0">
            <div class="p-3 text-center border-bottom border-secondary">
                <h4><i class="fas fa-shield-alt"></i> ENMA Admin</h4>
                <small class="text-muted">Panel de Seguridad</small>
            </div>
            <nav class="mt-3">
                <a href="?action=dashboard"><i class="fas fa-home me-2"></i> Inicio</a>
                <a href="?action=analytics" class="active"><i class="fas fa-chart-line me-2"></i> Analytics & Seguridad</a>
                <a href="?action=users"><i class="fas fa-users me-2"></i> Usuarios</a>
                <a href="?action=posts"><i class="fas fa-newspaper me-2"></i> Posts</a>
                <a href="?action=products"><i class="fas fa-box me-2"></i> Productos</a>
                <a href="?action=maintenance"><i class="fas fa-tools me-2"></i> Mantenimiento</a>
                <a href="?action=logout" class="text-danger mt-5"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2><i class="fas fa-globe-americas"></i> Monitor de Tráfico y Seguridad</h2>
                <span class="badge bg-primary"><?= htmlspecialchars($data['user']['username']) ?></span>
            </div>

            <!-- Pestañas -->
            <div class="dashboard-tabs">
                <button class="tab-btn active" onclick="openTab(event, 'overview')"><i class="fas fa-tachometer-alt"></i> Resumen Ejecutivo</button>
                <button class="tab-btn" onclick="openTab(event, 'security')"><i class="fas fa-user-shield"></i> Seguridad & Amenazas</button>
                <button class="tab-btn" onclick="openTab(event, 'traffic')"><i class="fas fa-chart-area"></i> Tráfico Detallado</button>
                <button class="tab-btn" onclick="openTab(event, 'export')"><i class="fas fa-robot"></i> Exportar para IA</button>
            </div>

            <!-- TAB 1: Resumen Ejecutivo -->
            <div id="overview" class="tab-content active">
                <div class="kpi-grid">
                    <div class="kpi-card success">
                        <div class="kpi-label">Vistas Totales</div>
                        <div class="kpi-value"><?= number_format($data['stats']['total_views']) ?></div>
                        <small class="text-muted">Acumulado histórico</small>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Visitantes Únicos (IPs)</div>
                        <div class="kpi-value"><?= number_format($data['stats']['unique_ips']) ?></div>
                        <small class="text-muted">Direcciones IP distintas</small>
                    </div>
                    <div class="kpi-card">
                        <div class="kpi-label">Clics Salientes</div>
                        <div class="kpi-value"><?= number_format($data['stats']['total_clicks']) ?></div>
                        <small class="text-muted">Interacción con enlaces</small>
                    </div>
                    <div class="kpi-card success">
                        <div class="kpi-label">Tráfico Humano Estimado</div>
                        <div class="kpi-value"><?= number_format($data['stats']['human_traffic']) ?></div>
                        <small class="text-muted">Usuarios reales probables</small>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-8">
                        <div class="chart-container">
                            <canvas id="trafficChart"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm h-100">
                            <div class="card-header bg-white fw-bold">Top User Agents</div>
                            <ul class="list-group list-group-flush small">
                                <?php foreach($data['top_agents'] as $agent): ?>
                                    <li class="list-group-item d-flex justify-content-between align-items-start">
                                        <div class="ms-2 me-auto text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($agent['user_agent']) ?>">
                                            <?= htmlspecialchars(substr($agent['user_agent'], 0, 40)) ?>...
                                        </div>
                                        <span class="badge bg-primary rounded-pill"><?= $agent['count'] ?></span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: Seguridad -->
            <div id="security" class="tab-content">
                <div class="kpi-grid">
                    <div class="kpi-card warning">
                        <div class="kpi-label"><i class="fas fa-robot"></i> Bots Detectados</div>
                        <div class="kpi-value"><?= number_format($data['stats']['suspected_bots']) ?></div>
                        <small class="text-muted">Google, Bing, Crawlers</small>
                    </div>
                    <div class="kpi-card danger">
                        <div class="kpi-label"><i class="fas fa-skull-crossbones"></i> Intentos de Ataque</div>
                        <div class="kpi-value"><?= number_format($data['stats']['suspected_attacks']) ?></div>
                        <small class="text-muted">SQLi, XSS, Scanners</small>
                    </div>
                </div>

                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white fw-bold">
                        <i class="fas fa-exclamation-triangle"></i> Top IPs Sospechosas
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>IP Address</th>
                                        <th>Intentos</th>
                                        <th>Last User Agent</th>
                                        <th>Acción</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if(empty($data['suspicious_ips'])): ?>
                                        <tr><td colspan="4" class="text-center py-4 text-muted">No se detectaron IPs sospechosas recientes.</td></tr>
                                    <?php else: ?>
                                        <?php foreach($data['suspicious_ips'] as $ip): ?>
                                        <tr>
                                            <td class="fw-bold text-danger"><?= $ip['ip_address'] ?></td>
                                            <td><span class="badge bg-danger"><?= $ip['attempts'] ?></span></td>
                                            <td><small class="text-muted"><?= htmlspecialchars(substr($ip['last_agent'], 0, 60)) ?>...</small></td>
                                            <td><button class="btn btn-sm btn-outline-dark">Bloquear</button></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 3: Tráfico Detallado -->
            <div id="traffic" class="tab-content">
                <div class="card shadow-sm">
                    <div class="card-header bg-white fw-bold">Últimos Registros de Page Views</div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover mb-0 small">
                                <thead class="table-dark">
                                    <tr>
                                        <th>ID</th>
                                        <th>Fecha</th>
                                        <th>URL</th>
                                        <th>IP</th>
                                        <th>User Agent</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($data['recent_logs'] as $log): 
                                        $isBot = stripos($log['user_agent'], 'bot') !== false || stripos($log['user_agent'], 'crawler') !== false;
                                        $isAttack = stripos($log['url'], 'union') !== false || stripos($log['user_agent'], 'sqlmap') !== false;
                                    ?>
                                    <tr class="<?= $isAttack ? 'table-danger' : ($isBot ? 'table-info' : '') ?>">
                                        <td><?= $log['id'] ?></td>
                                        <td><?= $log['created_at'] ?></td>
                                        <td><code><?= htmlspecialchars(substr($log['url'], 0, 60)) ?>...</code></td>
                                        <td><?= $log['ip_address'] ?></td>
                                        <td>
                                            <small title="<?= htmlspecialchars($log['user_agent']) ?>">
                                                <?= htmlspecialchars(substr($log['user_agent'], 0, 40)) ?>...
                                            </small>
                                            <?php if($isAttack): ?>
                                                <span class="badge bg-danger ms-2">ATAQUE</span>
                                            <?php elseif($isBot): ?>
                                                <span class="badge bg-info ms-2">BOT</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 4: Exportar para IA -->
            <div id="export" class="tab-content">
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> 
                    <strong>¿Cómo usar esto?</strong> Copia el bloque JSON de abajo y pégalo en tu chat con la IA. 
                    Luego pregunta: <em>"Analiza estos patrones de tráfico. ¿Hay picos inusuales? ¿Qué porcentaje es bot vs humano? ¿Detectas patrones de ataque específicos?"</em>
                </div>
                
                <div class="card bg-dark text-white shadow">
                    <div class="card-header border-secondary">
                        <h5 class="mb-0"><i class="fas fa-code"></i> Datos Crudos (JSON)</h5>
                    </div>
                    <div class="card-body">
                        <textarea class="data-dump w-100" rows="20" readonly><?= $data['raw_json'] ?></textarea>
                        <div class="mt-3">
                            <button class="btn btn-success" onclick="copyToClipboard()"><i class="fas fa-copy"></i> Copiar JSON</button>
                            <button class="btn btn-outline-light ms-2" onclick="document.getElementById('export').scrollIntoView({behavior: 'smooth'})"><i class="fas fa-sync"></i> Refrescar Vista</button>
                        </div>
                    </div>
                </div>

                <div class="mt-4">
                    <h5><i class="fas fa-download"></i> Exportar Schema</h5>
                    <p class="text-muted">Descarga la estructura de la base de datos sin datos para consultar a la IA sobre el diseño.</p>
                    <form method="POST" action="?action=maintenance" class="d-inline">
                        <input type="hidden" name="task" value="export_schema">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-file-code"></i> Descargar Schema SQL</button>
                    </form>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
    // Lógica de Pestañas
    function openTab(evt, tabName) {
        let i, tabcontent, tablinks;
        tabcontent = document.getElementsByClassName("tab-content");
        for (i = 0; i < tabcontent.length; i++) {
            tabcontent[i].classList.remove("active");
        }
        tablinks = document.getElementsByClassName("tab-btn");
        for (i = 0; i < tablinks.length; i++) {
            tablinks[i].classList.remove("active");
        }
        document.getElementById(tabName).classList.add("active");
        evt.currentTarget.classList.add("active");
        
        // Renderizar gráfico si es la primera vez que se abre
        if(tabName === 'overview' && !window.chartRendered) {
            renderChart();
        }
    }

    // Renderizar Gráfico
    function renderChart() {
        const ctx = document.getElementById('trafficChart').getContext('2d');
        window.myChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= $data['chart_labels_json'] ?>,
                datasets: [{
                    label: 'Vistas por Día',
                    data: <?= $data['chart_values_json'] ?>,
                    borderColor: 'rgba(13, 110, 253, 1)',
                    backgroundColor: 'rgba(13, 110, 253, 0.1)',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointRadius: 5,
                    pointHoverRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: true, position: 'top' },
                    tooltip: { mode: 'index', intersect: false }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f0f0f0' } },
                    x: { grid: { display: false } }
                }
            }
        });
        window.chartRendered = true;
    }

    function copyToClipboard() {
        const textarea = document.querySelector('.data-dump');
        textarea.select();
        document.execCommand('copy');
        
        const btn = document.querySelector('.btn-success');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-check"></i> ¡Copiado!';
        btn.classList.replace('btn-success', 'btn-dark');
        setTimeout(() => {
            btn.innerHTML = originalText;
            btn.classList.replace('btn-dark', 'btn-success');
        }, 2000);
    }

    // Auto-renderizar al cargar si estamos en overview
    document.addEventListener('DOMContentLoaded', () => {
        renderChart();
    });
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
