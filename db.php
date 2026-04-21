<?php
declare(strict_types=1);

/**
 * Conexión PDO a MySQL 8.x.
 * Ajusta credenciales según tu entorno local.
 */
$dbHost = '127.0.0.1';
$dbName = 'felconx_materiales';
$dbUser = 'felconx_mat';
$dbPass = '@Felcon220389..';
$dbCharset = 'utf8mb4';

$dsn = "mysql:host={$dbHost};dbname={$dbName};charset={$dbCharset}";

try {
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $e) {
    exit('Error de conexión a la base de datos: ' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8'));
}
