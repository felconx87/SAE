<?php

require_once __DIR__ . '/auth.php';

if (isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$error = '';
$redirect = (string) ($_GET['redirect'] ?? 'index.php');
if ($redirect === '' || strpos($redirect, 'http') === 0) {
    $redirect = 'index.php';
}

$csrfToken = csrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrfInput = $_POST['csrf_token'] ?? '';
    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $redirectPost = (string) ($_POST['redirect'] ?? 'index.php');

    if (!csrfIsValid(is_string($csrfInput) ? $csrfInput : null)) {
        $error = 'Solicitud invalida (CSRF).';
    } elseif ($username === '' || $password === '') {
        $error = 'Usuario y contraseña son obligatorios.';
    } elseif (!attemptLogin($username, $password)) {
        $error = 'Credenciales invalidas.';
    } else {
        if ($redirectPost === '' || strpos($redirectPost, 'http') === 0) {
            $redirectPost = 'index.php';
        }
        header('Location: ' . $redirectPost);
        exit;
    }
}
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Iniciar Sesion - Panel SAE</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    body {
      min-height: 100vh;
      background: linear-gradient(135deg, #0f172a, #1e3a8a);
    }
    .login-card { max-width: 430px; }
  </style>
</head>
<body class="d-flex align-items-center justify-content-center p-3">
  <div class="card shadow-lg border-0 login-card w-100">
    <div class="card-body p-4 p-md-5">
      <div class="text-center mb-4">
        <h4 class="mb-1"><i class="fa-solid fa-bolt text-primary me-2"></i>Panel SAE</h4>
        <p class="text-muted mb-0">Inicio de sesion</p>
      </div>

      <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
      <?php endif; ?>

      <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
        <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">

        <div class="mb-3">
          <label class="form-label">Usuario</label>
          <input type="text" name="username" class="form-control" required maxlength="80" autofocus>
        </div>
        <div class="mb-3">
          <label class="form-label">Contraseña</label>
          <input type="password" name="password" class="form-control" required maxlength="120">
        </div>
        <button type="submit" class="btn btn-primary w-100">
          <i class="fa-solid fa-right-to-bracket me-1"></i>Ingresar
        </button>
      </form>
    </div>
  </div>
</body>
</html>
