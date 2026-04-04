<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'ENMA Admin') ?></title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { background-color: #f4f6f9; }
        .sidebar { min-height: 100vh; background: #343a40; color: white; }
        .sidebar a { color: #cfd2d6; text-decoration: none; padding: 10px 15px; display: block; transition: all 0.3s; }
        .sidebar a:hover { background: #495057; color: white; }
        .sidebar a.active { background: #0d6efd; color: white; }
        .sidebar .brand { border-bottom: 1px solid #495057; }
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
        
        /* Form Styles */
        .box { background: white; border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 20px; margin-bottom: 20px; }
        .btn-primary-custom { background: #0d6efd; color: white; border: none; padding: 10px 20px; border-radius: 6px; cursor: pointer; }
        .btn-primary-custom:hover { background: #0b5ed7; color: white; }
    </style>
    <?= $extraHead ?? '' ?>
</head>
<body>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <div class="col-md-2 sidebar p-0">
            <div class="p-3 text-center brand">
                <h4><i class="fas fa-shield-alt"></i> ENMA Admin</h4>
                <small class="text-muted">Panel de Administración</small>
            </div>
            <nav class="mt-3">
                <a href="/enma/?tab=overview" class="<?= ($activePage ?? '') === 'overview' ? 'active' : '' ?>"><i class="fas fa-home me-2"></i> Inicio</a>
                <a href="/enma/?action=analytics" class="<?= ($activePage ?? '') === 'analytics' ? 'active' : '' ?>"><i class="fas fa-chart-line me-2"></i> Analytics & Seguridad</a>
                <a href="/enma/?tab=products" class="<?= ($activePage ?? '') === 'products' ? 'active' : '' ?>"><i class="fas fa-box me-2"></i> Productos</a>
                <a href="/enma/?tab=posts" class="<?= ($activePage ?? '') === 'posts' ? 'active' : '' ?>"><i class="fas fa-newspaper me-2"></i> Posts</a>
                <a href="/enma/?tab=maintenance" class="<?= ($activePage ?? '') === 'maintenance' ? 'active' : '' ?>"><i class="fas fa-tools me-2"></i> Mantenimiento</a>
                <a href="/enma/?action=logout" class="text-danger mt-5"><i class="fas fa-sign-out-alt me-2"></i> Cerrar Sesión</a>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="col-md-10 content">
            <?= $content ?? '' ?>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<?= $extraScripts ?? '' ?>

</body>
</html>
