<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-home"></i> Inicio - Panel de Control</h2>
    <span class="badge bg-primary"><?= htmlspecialchars($user['username'] ?? 'Admin') ?></span>
</div>

<!-- KPI Cards -->
<div class="kpi-grid">
    <div class="kpi-card success">
        <div class="kpi-label">Productos</div>
        <div class="kpi-value"><?= number_format($overviewStats['products'] ?? 0) ?></div>
        <small class="text-muted">Total en catálogo</small>
    </div>
    <div class="kpi-card">
        <div class="kpi-label">Categorías</div>
        <div class="kpi-value"><?= number_format($overviewStats['categories'] ?? 0) ?></div>
        <small class="text-muted">Categorías únicas</small>
    </div>
    <div class="kpi-card warning">
        <div class="kpi-label">Vistas (30d)</div>
        <div class="kpi-value"><?= number_format($overviewStats['views_30d'] ?? 0) ?></div>
        <small class="text-muted">Últimos 30 días</small>
    </div>
    <div class="kpi-card danger">
        <div class="kpi-label">Faltan Tags</div>
        <div class="kpi-value"><?= number_format($overviewStats['missing_tags'] ?? 0) ?></div>
        <small class="text-muted">Productos sin tag afiliado</small>
    </div>
    <div class="kpi-card danger">
        <div class="kpi-label">Faltan Imágenes</div>
        <div class="kpi-value"><?= number_format($overviewStats['missing_images'] ?? 0) ?></div>
        <small class="text-muted">Productos sin imagen</small>
    </div>
    <div class="kpi-card success">
        <div class="kpi-label">Posts</div>
        <div class="kpi-value"><?= number_format($overviewStats['posts'] ?? 0) ?></div>
        <small class="text-muted">Publicaciones activas</small>
    </div>
</div>

<div class="box">
    <h4 class="mb-3"><i class="fas fa-info-circle"></i> Bienvenido al Panel ENMA</h4>
    <p class="text-muted">Utiliza el menú lateral para navegar entre las diferentes secciones del panel de administración:</p>
    <ul class="list-group list-group-flush">
        <li class="list-group-item"><i class="fas fa-box text-primary me-2"></i> <strong>Productos:</strong> Gestiona tu catálogo de productos Amazon</li>
        <li class="list-group-item"><i class="fas fa-newspaper text-success me-2"></i> <strong>Posts:</strong> Crea y edita contenido para tu sitio</li>
        <li class="list-group-item"><i class="fas fa-chart-line text-info me-2"></i> <strong>Analytics & Seguridad:</strong> Monitoriza tráfico y detecta amenazas</li>
        <li class="list-group-item"><i class="fas fa-tools text-warning me-2"></i> <strong>Mantenimiento:</strong> Herramientas de base de datos y exportación</li>
    </ul>
</div>
