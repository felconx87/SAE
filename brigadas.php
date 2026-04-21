<?php

require_once __DIR__ . '/auth.php';
requireAuth();
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/update_checker.php';

$mensaje = '';
$error = '';
$csrfToken = csrfToken();
$updateInfo = checkForAppUpdate();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    $csrfInput = $_POST['csrf_token'] ?? '';

    try {
        if (!csrfIsValid(is_string($csrfInput) ? $csrfInput : null)) {
            throw new RuntimeException('Solicitud invalida (CSRF).');
        }

        if ($accion === 'crear_brigada') {
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            if ($nombre === '') {
                throw new RuntimeException('El nombre de brigada es obligatorio.');
            }
            $stmt = $pdo->prepare('INSERT INTO brigadas (nombre) VALUES (:nombre)');
            $stmt->execute([':nombre' => $nombre]);
            header('Location: brigadas.php?ok=1');
            exit;
        }

        if ($accion === 'editar_brigada') {
            $id = (int) ($_POST['id'] ?? 0);
            $nombre = trim((string) ($_POST['nombre'] ?? ''));
            if ($id <= 0 || $nombre === '') {
                throw new RuntimeException('Datos invalidos para actualizar brigada.');
            }
            $stmt = $pdo->prepare('UPDATE brigadas SET nombre = :nombre WHERE id = :id');
            $stmt->execute([':id' => $id, ':nombre' => $nombre]);
            header('Location: brigadas.php?ok=2');
            exit;
        }

        if ($accion === 'eliminar_brigada') {
            $id = (int) ($_POST['id'] ?? 0);
            if ($id <= 0) {
                throw new RuntimeException('ID de brigada invalido.');
            }
            $stmt = $pdo->prepare('DELETE FROM brigadas WHERE id = :id');
            $stmt->execute([':id' => $id]);
            header('Location: brigadas.php?ok=3');
            exit;
        }
    } catch (PDOException $e) {
        if (($e->getCode() ?? '') === '23000') {
            $error = 'No puedes eliminar esta brigada porque tiene solicitudes registradas.';
        } else {
            $error = 'Error de base de datos: ' . $e->getMessage();
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

if (isset($_GET['ok'])) {
    $ok = (int) $_GET['ok'];
    if ($ok === 1) {
        $mensaje = 'Brigada creada correctamente.';
    } elseif ($ok === 2) {
        $mensaje = 'Brigada actualizada correctamente.';
    } elseif ($ok === 3) {
        $mensaje = 'Brigada eliminada correctamente.';
    }
}

$brigadas = [];
$solicitudes = [];

try {
    $sqlBrigadas = 'SELECT b.id, b.nombre,
                           COUNT(rf.id) AS total_solicitudes,
                           COALESCE(SUM(rf.cantidad_utilizada), 0) AS total_material
                    FROM brigadas b
                    LEFT JOIN registro_fallas rf ON rf.brigada_id = b.id
                    GROUP BY b.id, b.nombre
                    ORDER BY b.nombre ASC';
    $brigadas = $pdo->query($sqlBrigadas)->fetchAll();

    $sqlSolicitudes = 'SELECT rf.id, rf.id_falla, rf.fecha, b.nombre AS brigada,
                              m.nombre AS material, m.unidad_medida, rf.cantidad_utilizada, rf.observaciones
                       FROM registro_fallas rf
                       INNER JOIN brigadas b ON b.id = rf.brigada_id
                       INNER JOIN materiales m ON m.id = rf.material_id
                       ORDER BY rf.fecha DESC, rf.id DESC';
    $solicitudes = $pdo->query($sqlSolicitudes)->fetchAll();
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
  <title>Brigadas - Panel SAE</title>
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
  </style>
</head>
<body>
  <div class="d-flex">
    <aside class="sidebar d-none d-lg-block p-0">
      <div class="p-3 border-bottom border-secondary"><h5 class="mb-0"><i class="fa-solid fa-bolt me-2"></i>Panel SAE</h5></div>
      <nav class="nav flex-column p-2">
        <a class="nav-link rounded mb-1" href="index.php"><i class="fa-solid fa-chart-line me-2"></i>Dashboard</a>
        <a class="nav-link active rounded mb-1" href="brigadas.php"><i class="fa-solid fa-people-group me-2"></i>Brigadas</a>
        <a class="nav-link rounded mb-1" href="materiales.php"><i class="fa-solid fa-boxes-stacked me-2"></i>Materiales</a>
        <a class="nav-link rounded mb-1" href="registrar_falla.php"><i class="fa-solid fa-file-circle-plus me-2"></i>Registrar Solicitud</a>
      </nav>
    </aside>

    <main class="main-content flex-grow-1">
      <nav class="navbar navbar-light bg-white border-bottom px-3">
        <button class="btn btn-outline-secondary d-lg-none me-2" type="button" data-bs-toggle="offcanvas" data-bs-target="#mobileSidebar"><i class="fa-solid fa-bars"></i></button>
        <span class="navbar-brand fw-semibold mb-0"><i class="fa-solid fa-people-group me-2"></i>Gestion de Brigadas</span>
        <div class="d-flex align-items-center gap-2"><span class="badge text-bg-light border">v<?= htmlspecialchars($updateInfo['current_version'], ENT_QUOTES, 'UTF-8') ?></span><a href="logout.php" class="btn btn-sm btn-outline-danger"><i class="fa-solid fa-right-from-bracket me-1"></i>Salir</a></div>
      </nav>

      <div class="p-3 p-md-4">
        <?php if ($mensaje !== ''): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div><?php endif; ?>

        <div class="card shadow-sm border-0 mb-4">
          <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Listado de brigadas</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalCrear"><i class="fa-solid fa-plus me-1"></i>Nueva brigada</button>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tablaBrigadas" class="table table-striped align-middle">
                <thead><tr><th>ID</th><th>Brigada</th><th>Solicitudes</th><th>Total material</th><th>Acciones</th></tr></thead>
                <tbody>
                <?php foreach ($brigadas as $b): ?>
                  <tr>
                    <td><?= (int) $b['id'] ?></td>
                    <td><?= htmlspecialchars($b['nombre'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= (int) $b['total_solicitudes'] ?></td>
                    <td><?= number_format((float) $b['total_material'], 2, ',', '.') ?></td>
                    <td>
                      <button class="btn btn-sm btn-warning btn-editar" data-id="<?= (int) $b['id'] ?>" data-nombre="<?= htmlspecialchars($b['nombre'], ENT_QUOTES, 'UTF-8') ?>"><i class="fa-solid fa-pen-to-square"></i></button>
                      <form method="post" class="d-inline" onsubmit="return confirm('¿Eliminar esta brigada?');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                        <input type="hidden" name="accion" value="eliminar_brigada">
                        <input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-danger"><i class="fa-solid fa-trash"></i></button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="card shadow-sm border-0">
          <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
            <h5 class="mb-0">Materiales solicitados por brigadas</h5>
            <a href="registrar_falla.php" class="btn btn-success btn-sm"><i class="fa-solid fa-file-circle-plus me-1"></i>Registrar solicitud</a>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table id="tablaSolicitudes" class="table table-striped align-middle">
                <thead><tr><th>ID</th><th>Falla</th><th>Fecha</th><th>Brigada</th><th>Material</th><th>Cantidad</th><th>Observaciones</th></tr></thead>
                <tbody>
                <?php foreach ($solicitudes as $s): ?>
                  <tr>
                    <td><?= (int) $s['id'] ?></td>
                    <td><?= htmlspecialchars($s['id_falla'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($s['fecha'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($s['brigada'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($s['material'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($s['unidad_medida'], ENT_QUOTES, 'UTF-8') ?>)</td>
                    <td><?= number_format((float) $s['cantidad_utilizada'], 2, ',', '.') ?></td>
                    <td><?= htmlspecialchars((string) $s['observaciones'], ENT_QUOTES, 'UTF-8') ?></td>
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
    <div class="offcanvas-header border-bottom border-secondary"><h5 class="offcanvas-title"><i class="fa-solid fa-bolt me-2"></i>Panel SAE</h5><button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas"></button></div>
    <div class="offcanvas-body p-2">
      <nav class="nav flex-column">
        <a class="nav-link rounded mb-1 text-white-50" href="index.php"><i class="fa-solid fa-chart-line me-2"></i>Dashboard</a>
        <a class="nav-link active rounded mb-1 text-white bg-secondary" href="brigadas.php"><i class="fa-solid fa-people-group me-2"></i>Brigadas</a>
        <a class="nav-link rounded mb-1 text-white-50" href="materiales.php"><i class="fa-solid fa-boxes-stacked me-2"></i>Materiales</a>
        <a class="nav-link rounded mb-1 text-white-50" href="registrar_falla.php"><i class="fa-solid fa-file-circle-plus me-2"></i>Registrar Solicitud</a>
      </nav>
    </div>
  </div>

  <div class="modal fade" id="modalCrear" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title">Nueva brigada</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="crear_brigada"><div class="mb-3"><label class="form-label">Nombre</label><input type="text" name="nombre" class="form-control" required maxlength="120"></div></div><div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="submit">Guardar</button></div></form></div></div>

  <div class="modal fade" id="modalEditar" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><form method="post" class="modal-content"><div class="modal-header"><h5 class="modal-title">Editar brigada</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="accion" value="editar_brigada"><input type="hidden" name="id" id="edit_id"><div class="mb-3"><label class="form-label">Nombre</label><input type="text" name="nombre" id="edit_nombre" class="form-control" required maxlength="120"></div></div><div class="modal-footer"><button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-warning" type="submit">Actualizar</button></div></form></div></div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
  <script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap5.min.js"></script>
  <script>
    $(function () {
      $('#tablaBrigadas').DataTable({ language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' } });
      $('#tablaSolicitudes').DataTable({ order: [[0, 'desc']], language: { url: 'https://cdn.datatables.net/plug-ins/1.13.8/i18n/es-ES.json' } });
      $('.btn-editar').on('click', function () {
        $('#edit_id').val($(this).data('id'));
        $('#edit_nombre').val($(this).data('nombre'));
        new bootstrap.Modal(document.getElementById('modalEditar')).show();
      });
    });
  </script>
</body>
</html>
