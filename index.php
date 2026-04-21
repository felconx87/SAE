<?php

require_once __DIR__ . '/auth.php';
requireAuth();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/update_checker.php';

$error = '';
$stockBajoUmbral = 10;
$updateInfo = checkForAppUpdate();

$desde = trim((string) ($_GET['desde'] ?? ''));
$hasta = trim((string) ($_GET['hasta'] ?? ''));
$brigadaFiltro = (int) ($_GET['brigada_id'] ?? 0);

$brigadas = [];
$totalMateriales = 0;
$fallasAtendidas = 0;
$stockBajo = 0;
$consumoHoy = 0.0;
$topMaterial = 'Sin datos';
$reporte = [];

try {
    $brigadas = $pdo->query('SELECT id, nombre FROM brigadas ORDER BY nombre ASC')->fetchAll();

    $totalMateriales = (int) $pdo->query('SELECT COUNT(*) FROM materiales')->fetchColumn();
    $fallasAtendidas = (int) $pdo->query('SELECT COUNT(DISTINCT id_falla) FROM registro_fallas')->fetchColumn();

    $stmtStockBajo = $pdo->prepare('SELECT COUNT(*) FROM materiales WHERE stock_actual <= :umbral');
    $stmtStockBajo->execute([':umbral' => $stockBajoUmbral]);
    $stockBajo = (int) $stmtStockBajo->fetchColumn();

    $stmtConsumoHoy = $pdo->prepare('SELECT COALESCE(SUM(cantidad_utilizada), 0) FROM registro_fallas WHERE fecha = CURDATE()');
    $stmtConsumoHoy->execute();
    $consumoHoy = (float) $stmtConsumoHoy->fetchColumn();

    $stmtTop = $pdo->query('SELECT m.nombre, SUM(rf.cantidad_utilizada) AS total
                            FROM registro_fallas rf
                            INNER JOIN materiales m ON m.id = rf.material_id
                            GROUP BY m.id, m.nombre
                            ORDER BY total DESC
                            LIMIT 1');
    $top = $stmtTop->fetch();
    if ($top && !empty($top['nombre'])) {
        $topMaterial = (string) $top['nombre'];
    }

    $where = [];
    $params = [];

    if ($desde !== '') {
        $where[] = 'rf.fecha >= :desde';
        $params[':desde'] = $desde;
    }
    if ($hasta !== '') {
        $where[] = 'rf.fecha <= :hasta';
        $params[':hasta'] = $hasta;
    }
    if ($brigadaFiltro > 0) {
        $where[] = 'rf.brigada_id = :brigada_id';
        $params[':brigada_id'] = $brigadaFiltro;
    }

    $sql = 'SELECT rf.id, rf.id_falla, rf.fecha, b.nombre AS brigada, m.nombre AS material,
                   m.unidad_medida, rf.cantidad_utilizada, rf.observaciones
            FROM registro_fallas rf
            INNER JOIN brigadas b ON b.id = rf.brigada_id
            INNER JOIN materiales m ON m.id = rf.material_id';

    if (!empty($where)) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    $sql .= ' ORDER BY rf.fecha DESC, rf.id DESC';

    $stmtReporte = $pdo->prepare($sql);
    $stmtReporte->execute($params);
    $reporte = $stmtReporte->fetchAll();
} catch (PDOException $e) {
    if (($e->getCode() ?? '') === '42S02') {
        $error = 'Faltan tablas en la base de datos. Importa schema.sql en felconx_materiales.';
    } else {
        $error = 'Error de base de datos: ' . $e->getMessage();
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Dashboard - Panel SAE</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap5.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    body { background-color: #f4f6f9; }
    .sidebar { min-height: 100vh; width: 260px; background: #1f2937; color: #fff; }
    .sidebar .nav-link { color: #d1d5db; }
    .sidebar .nav-link.active, .sidebar .nav-link:hover { color: #fff; background: #374151; }
    .main-content { min-width: 0; }
    .offcanvas.offcanvas-start { width: 260px; }
    .stat-card i { font-size: 1.5rem; opacity: .85; }
  </style>
</head>
<body>
  <div class="d-flex">
    <aside class="sidebar d-none d-lg-block p-0">
      <div class="p-3 border-bottom border-secondary">
        <h5 class="mb-0"><i class="fa-solid fa-bolt me-2"></i>Panel SAE</h5>
      </div>
      <nav class="nav flex-column p-2">
        <a class="nav-link active rounded mb-1" href="index.php"><i class="fa-solid fa-chart-line me-2"></i>Dashboard</a>
        <a class="nav-link rounded mb-1" href="brigadas.php"><i class="fa-solid fa-people-group me-2"></i>Brigadas</a>
        <a class="nav-link rounded mb-1" href="materiales.php"><i class="fa-solid fa-boxes-stacked me-2"></i>Materiales</a>
        <a class="nav-link rounded mb-1" href="registrar_falla.php"><i class="fa-solid fa-file-circle-plus me-2"></i>Registrar Solicitud</a>
      </nav>
    </aside>

    <main class="main-content flex-grow-1">
      <nav class="navbar navbar-light bg-white border-bottom px-3">
        <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar">
          <i class="fa-solid fa-bars"></i>
        </button>
        <span class="navbar-brand fw-semibold mb-0"><i class="fa-solid fa-chart-line me-2"></i>Dashboard de Reportes</span>
        <div class="d-flex align-items-center gap-2">
          <span class="badge text-bg-light border">v<?= htmlspecialchars($updateInfo['current_version'], ENT_QUOTES, 'UTF-8') ?></span>
          <a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-right-from-bracket me-1"></i>Salir</a>
        </div>
      </nav>

      <div class="p-3 p-md-4">
        <?php if ($error !== ''): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-4">
          <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card shadow-sm border-0 h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">Total materiales</h6><h3 class="mb-0"><?= $totalMateriales ?></h3></div><i class="fa-solid fa-boxes-stacked text-primary"></i></div></div>
          </div>
          <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card shadow-sm border-0 h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">Fallas atendidas</h6><h3 class="mb-0"><?= $fallasAtendidas ?></h3></div><i class="fa-solid fa-list-check text-success"></i></div></div>
          </div>
          <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card shadow-sm border-0 h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">Stock bajo (&lt;= <?= $stockBajoUmbral ?>)</h6><h3 class="mb-0"><?= $stockBajo ?></h3></div><i class="fa-solid fa-circle-exclamation text-danger"></i></div></div>
          </div>
          <div class="col-12 col-md-6 col-xl-3">
            <div class="card stat-card shadow-sm border-0 h-100"><div class="card-body d-flex justify-content-between align-items-center"><div><h6 class="text-muted mb-1">Consumo hoy</h6><h3 class="mb-0"><?= number_format($consumoHoy, 2, ',', '.') ?></h3></div><i class="fa-solid fa-calendar-day text-warning"></i></div></div>
          </div>
        </div>

        <div class="card shadow-sm border-0 mb-4">
          <div class="card-header bg-white"><h5 class="mb-0">Filtros de reporte</h5></div>
          <div class="card-body">
            <form class="row g-3" method="get">
              <div class="col-12 col-md-3">
                <label class="form-label">Desde</label>
                <input type="date" class="form-control" name="desde" value="<?= htmlspecialchars($desde, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="col-12 col-md-3">
                <label class="form-label">Hasta</label>
                <input type="date" class="form-control" name="hasta" value="<?= htmlspecialchars($hasta, ENT_QUOTES, 'UTF-8') ?>">
              </div>
              <div class="col-12 col-md-4">
                <label class="form-label">Brigada</label>
                <select name="brigada_id" class="form-select">
                  <option value="0">Todas</option>
                  <?php foreach ($brigadas as $b): ?>
                    <option value="<?= (int) $b['id'] ?>" <?= $brigadaFiltro === (int) $b['id'] ? 'selected' : '' ?>><?= htmlspecialchars($b['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-2 d-flex align-items-end">
                <button class="btn btn-primary w-100" type="submit"><i class="fa-solid fa-filter me-1"></i>Filtrar</button>
              </div>
            </form>
          </div>
        </div>

        <div class="card shadow-sm border-0">
          <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Reporte de solicitudes de materiales</h5>
            <span class="badge text-bg-info">Material mas solicitado: <?= htmlspecialchars($topMaterial, ENT_QUOTES, 'UTF-8') ?></span>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tablaReporte" class="table table-striped align-middle">
                <thead>
                  <tr>
                    <th>ID</th>
                    <th>Falla</th>
                    <th>Fecha</th>
                    <th>Brigada</th>
                    <th>Material</th>
                    <th>Cantidad</th>
                    <th>Observaciones</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($reporte as $r): ?>
                    <tr>
                      <td><?= (int) $r['id'] ?></td>
                      <td><?= htmlspecialchars($r['id_falla'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($r['fecha'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($r['brigada'], ENT_QUOTES, 'UTF-8') ?></td>
                      <td><?= htmlspecialchars($r['material'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($r['unidad_medida'], ENT_QUOTES, 'UTF-8') ?>)</td>
                      <td><?= number_format((float) $r['cantidad_utilizada'], 2, ',', '.') ?></td>
                      <td><?= htmlspecialchars((string) $r['observaciones'], ENT_QUOTES, 'UTF-8') ?></td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="mobileSidebar">
    <div class="offcanvas-header border-bottom border-secondary">
      <h5 class="offcanvas-title"><i class="fa-solid fa-bolt me-2"></i>Panel SAE</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button>
    </div>
    <div class="offcanvas-body p-2">
      <nav class="nav flex-column">
        <a class="nav-link active rounded mb-1 text-white bg-secondary" href="index.php"><i class="fa-solid fa-chart-line me-2"></i>Dashboard</a>
        <a class="nav-link rounded mb-1 text-white-50" href="brigadas.php"><i class="fa-solid fa-people-group me-2"></i>Brigadas</a>
        <a class="nav-link rounded mb-1 text-white-50" href="materiales.php"><i class="fa-solid fa-boxes-stacked me-2"></i>Materiales</a>
        <a class="nav-link rounded mb-1 text-white-50" href="registrar_falla.php"><i class="fa-solid fa-file-circle-plus me-2"></i>Registrar Solicitud</a>
      </nav>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
  <script>
    $(function () {
      $('#tablaReporte').DataTable({
        order: [[0, 'desc']],
        language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' }
      });
    });
  </script>
</body>
</html>
