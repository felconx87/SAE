-- ======================================================
-- Panel de Materiales - Esquema MySQL 8.x
-- ======================================================
-- IMPORTANTE:
-- 1) En hosting compartido, normalmente NO tienes permiso para CREATE DATABASE.
-- 2) Selecciona primero tu base en phpMyAdmin y luego ejecuta este script.

-- ======================================================
-- Tabla: brigadas
-- ======================================================
CREATE TABLE IF NOT EXISTS brigadas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL UNIQUE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ======================================================
-- Tabla: materiales
-- ======================================================
CREATE TABLE IF NOT EXISTS materiales (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(150) NOT NULL,
  unidad_medida VARCHAR(50) NOT NULL,
  stock_actual DECIMAL(10,2) NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uk_material_nombre_unidad (nombre, unidad_medida),
  CONSTRAINT chk_stock_no_negativo CHECK (stock_actual >= 0)
) ENGINE=InnoDB;

-- ======================================================
-- Tabla: registro_fallas
-- ======================================================
CREATE TABLE IF NOT EXISTS registro_fallas (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_falla VARCHAR(60) NOT NULL,
  brigada_id INT UNSIGNED NOT NULL,
  fecha DATE NOT NULL,
  material_id INT UNSIGNED NOT NULL,
  cantidad_utilizada DECIMAL(10,2) NOT NULL,
  observaciones TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_registro_brigada
    FOREIGN KEY (brigada_id) REFERENCES brigadas(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_registro_material
    FOREIGN KEY (material_id) REFERENCES materiales(id)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT chk_cantidad_positiva CHECK (cantidad_utilizada > 0),
  INDEX idx_registro_fecha (fecha),
  INDEX idx_registro_id_falla (id_falla)
) ENGINE=InnoDB;

-- ======================================================
-- Datos iniciales de brigadas
-- ======================================================
INSERT IGNORE INTO brigadas (id, nombre) VALUES
  (1, 'Brigada Norte'),
  (2, 'Brigada Centro'),
  (3, 'Brigada Sur');
