# Sistema de actualizaciones (GitHub + aviso en panel)

## 1) Configurar version local
Edita `app_config.php`:

```php
const APP_VERSION = '1.0.0';
const APP_UPDATE_MANIFEST_URL = 'https://raw.githubusercontent.com/TU_USUARIO/TU_REPO/main/update-manifest.json';
```

## 2) Publicar cambios en GitHub
1. Sube tu codigo al repositorio.
2. Cuando saques una nueva version, actualiza `update-manifest.json` en `main`:

```json
{
  "latest_version": "1.1.0",
  "release_date": "2026-04-21",
  "download_url": "https://github.com/TU_USUARIO/TU_REPO/archive/refs/tags/v1.1.0.zip",
  "changelog": "Mejoras de dashboard y correcciones de stock."
}
```

## 3) Resultado en el servidor
- El panel consulta ese JSON remoto.
- Si `latest_version` es mayor que `APP_VERSION`, muestra alerta de actualizacion.
- El chequeo usa cache local (`cache/update_check.json`) por 6 horas.

## 4) Como actualizar en servidor
Este proyecto muestra aviso de nueva version, pero la actualizacion del codigo la haces manual:
1. Descargar ZIP desde `download_url`.
2. Respaldar servidor actual.
3. Reemplazar archivos.
4. Verificar `db.php` y permisos.

## 5) Opcional: despliegue automatico
Si tu hosting permite SSH/Git pull, puedes automatizar despliegue con GitHub Actions o webhook.
