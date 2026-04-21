# Deploy automatico con GitHub Actions

Este proyecto ya incluye el workflow:
- `.github/workflows/deploy.yml`

Se ejecuta cuando haces `push` a la rama `main` (y tambien manual con `Run workflow`).

## 1) Crear secretos en GitHub
En tu repo: `Settings > Secrets and variables > Actions > New repository secret`

Debes crear:
- `FTP_SERVER` (ej: `ftp.tudominio.com`)
- `FTP_USERNAME`
- `FTP_PASSWORD`
- `FTP_SERVER_DIR` (ej: `/public_html/panel-materiales/`)

## 2) Importante sobre `db.php`
El deploy excluye `db.php` para no pisar credenciales del servidor.

Primera vez:
1. Sube `db.php` manualmente al servidor (con credenciales reales de hosting).
2. Mantelo fuera de actualizaciones automaticas.

## 3) Flujo de trabajo recomendado
1. Trabajas local.
2. `git add .`
3. `git commit -m "tu cambio"`
4. `git push origin main`
5. GitHub Actions despliega solo.

## 4) Verificar deploy
En GitHub: pestaña `Actions` y revisa el job `Deploy Panel Materiales`.

Si falla:
- Valida usuario/password FTP.
- Revisa ruta `FTP_SERVER_DIR`.
- Si tu hosting usa FTPS, cambia en workflow: `protocol: ftps`.
