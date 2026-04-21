<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/update_checker.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $idFalla = trim($_POST['id_falla'] ?? '');
    $brigadaId = (int) ($_POST['brigada_id'] ?? 0);
    $fecha = $_POST['fecha'] ?? '';
    $materialId = (int) ($_POST['material_id'] ?? 0);
    $cantidad = (float) ($_POST['cantidad_utilizada'] ?? 0);
    $observaciones = trim($_POST['observaciones'] ?? '');

    try {
        if ($idFalla === '' || $brigadaId <= 0 || $materialId <= 0 || $fecha === '') {
            throw new RuntimeException('Todos los campos obligatorios deben completarse.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new RuntimeException('La fecha no tiene un formato valido.');
        }
        if ($cantidad <= 0) {
            throw new RuntimeException('La cantidad utilizada debe ser mayor a cero.');
        }

        $pdo->beginTransaction();

        $stmtStock = $pdo->prepare('SELECT stock_actual FROM materiales WHERE id = :id FOR UPDATE');
        $stmtStock->execute([':id' => $materialId]);
        $material = $stmtStock->fetch();

        if (!$material) {
            throw new RuntimeException('El material seleccionado no existe.');
        }

        $stockActual = (float) $material['stock_actual'];
        if ($cantidad > $stockActual) {
            throw new RuntimeException('Stock insuficiente. Stock actual: ' . number_format($stockActual, 2, ',', '.'));
        }

        $stmtInsert = $pdo->prepare('INSERT INTO registro_fallas (id_falla, brigada_id, fecha, material_id, cantidad_utilizada, observaciones) VALUES (:id_falla, :brigada_id, :fecha, :material_id, :cantidad_utilizada, :observaciones)');
        $stmtInsert->execute([
            ':id_falla' => $idFalla,
            ':brigada_id' => $brigadaId,
            ':fecha' => $fecha,
            ':material_id' => $materialId,
            ':cantidad_utilizada' => $cantidad,
            ':observaciones' => $observaciones !== '' ? $observaciones : null,
        ]);

        $stmtUpdate = $pdo->prepare('UPDATE materiales SET stock_actual = stock_actual - :cantidad WHERE id = :id');
        $stmtUpdate->execute([
            ':cantidad' => $cantidad,
            ':id' => $materialId,
        ]);

        $pdo->commit();

        header('Location: index.php?ok=4');
        exit;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $e->getMessage();
    }
}

$brigadas = $pdo->query('SELECT id, nombre FROM brigadas ORDER BY nombre ASC')->fetchAll();
$materiales = $pdo->query('SELECT id, nombre, unidad_medida, stock_actual FROM materiales ORDER BY nombre ASC')->fetchAll();
$updateInfo = checkForAppUpdate();
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Registrar Falla - Panel de Materiales</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    body { background-color: #f4f6f9; }
    .sidebar {
      min-height: 100vh;
      width: 260px;
      background: #1f2937;
      color: #fff;
    }
    .sidebar .nav-link { color: #d1d5db; }
    .sidebar .nav-link.active, .sidebar .nav-link:hover { color: #fff; background: #374151; }
    .main-content { min-width: 0; }
    .offcanvas.offcanvas-start { width: 260px; }
  </style>
</head>
<body>
  <div class="d-flex">
    <aside class="sidebar d-none d-lg-block p-0">
      <div class="p-3 border-bottom border-secondary">
        <h5 class="mb-0"><i class="fa-solid fa-bolt me-2"></i>Panel SAE</h5>
      </div>
      <nav class="nav flex-column p-2">
        <a class="nav-link rounded mb-1" href="index.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a>
        <a class="nav-link active rounded mb-1" href="registrar_falla.php"><i class="fa-solid fa-triangle-exclamation me-2"></i>Registrar Falla</a>
      </nav>
    </aside>

    <main class="main-content flex-grow-1">
      <nav class="navbar navbar-light bg-white border-bottom px-3">
        <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar" aria-controls="mobileSidebar">
          <i class="fa-solid fa-bars"></i>
        </button>
        <span class="navbar-brand fw-semibold mb-0"><i class="fa-solid fa-notes-medical me-2"></i>Registro de Uso de Material</span>
        <span class="badge text-bg-light border">v<?= htmlspecialchars($updateInfo['current_version'], ENT_QUOTES, 'UTF-8') ?></span>
      </nav>

      <div class="p-3 p-md-4">
        <?php if ($updateInfo['enabled'] && $updateInfo['update_available']): ?>
          <div class="alert alert-warning d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
              <strong>Actualizacion disponible:</strong>
              v<?= htmlspecialchars($updateInfo['latest_version'], ENT_QUOTES, 'UTF-8') ?>
              <?php if (!empty($updateInfo['release_date'])): ?>
                (<?= htmlspecialchars($updateInfo['release_date'], ENT_QUOTES, 'UTF-8') ?>)
              <?php endif; ?>
            </div>
            <?php if (!empty($updateInfo['download_url'])): ?>
              <a class="btn btn-sm btn-dark" target="_blank" rel="noopener noreferrer" href="<?= htmlspecialchars($updateInfo['download_url'], ENT_QUOTES, 'UTF-8') ?>">
                Descargar actualizacion
              </a>
            <?php endif; ?>
          </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0">
          <div class="card-header bg-white">
            <h5 class="mb-0">Formulario de Registro</h5>
          </div>
          <div class="card-body">
            <?php if ($error !== ''): ?>
              <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <?php if (empty($brigadas) || empty($materiales)): ?>
              <div class="alert alert-warning">
                Debes tener al menos una brigada y un material cargado para registrar una falla.
              </div>
            <?php else: ?>
              <form method="post">
                <div class="row g-3">
                  <div class="col-12 col-md-6">
                    <label class="form-label">ID de Falla</label>
                    <input type="text" name="id_falla" class="form-control" required maxlength="60" placeholder="Ej: FALLA-2026-0001">
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label">Brigada Responsable</label>
                    <select name="brigada_id" class="form-select" required>
                      <option value="">Seleccione una brigada</option>
                      <?php foreach ($brigadas as $b): ?>
                        <option value="<?= (int) $b['id'] ?>"><?= htmlspecialchars($b['nombre'], ENT_QUOTES, 'UTF-8') ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label">Fecha</label>
                    <input type="date" name="fecha" class="form-control" required value="<?= date('Y-m-d') ?>">
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label">Material</label>
                    <select name="material_id" class="form-select" required>
                      <option value="">Seleccione un material</option>
                      <?php foreach ($materiales as $m): ?>
                        <option value="<?= (int) $m['id'] ?>">
                          <?= htmlspecialchars($m['nombre'], ENT_QUOTES, 'UTF-8') ?>
                          (<?= htmlspecialchars($m['unidad_medida'], ENT_QUOTES, 'UTF-8') ?>) -
                          Stock: <?= number_format((float) $m['stock_actual'], 2, ',', '.') ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="col-12 col-md-6">
                    <label class="form-label">Cantidad Utilizada</label>
                    <input type="number" name="cantidad_utilizada" class="form-control" required min="0.01" step="0.01">
                  </div>
                  <div class="col-12">
                    <label class="form-label">Observaciones</label>
                    <textarea name="observaciones" class="form-control" rows="3" maxlength="1000" placeholder="Detalle adicional (opcional)"></textarea>
                  </div>
                </div>

                <div class="mt-4 d-flex flex-wrap gap-2">
                  <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i>Volver</a>
                  <button type="submit" class="btn btn-success"><i class="fa-solid fa-floppy-disk me-1"></i>Registrar</button>
                </div>
              </form>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div class="offcanvas offcanvas-start text-bg-dark" tabindex="-1" id="mobileSidebar" aria-labelledby="mobileSidebarLabel">
    <div class="offcanvas-header border-bottom border-secondary">
      <h5 class="offcanvas-title" id="mobileSidebarLabel"><i class="fa-solid fa-bolt me-2"></i>Panel SAE</h5>
      <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Cerrar"></button>
    </div>
    <div class="offcanvas-body p-2">
      <nav class="nav flex-column">
        <a class="nav-link rounded mb-1 text-white-50" href="index.php"><i class="fa-solid fa-gauge me-2"></i>Dashboard</a>
        <a class="nav-link active rounded mb-1 text-white bg-secondary" href="registrar_falla.php"><i class="fa-solid fa-triangle-exclamation me-2"></i>Registrar Falla</a>
      </nav>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
