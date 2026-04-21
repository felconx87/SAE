# Deploy automatico por SSH (GitHub Actions)

El workflow activo es:
- `.github/workflows/deploy.yml`

Despliega cuando haces `push` a `main`.

## 1) Secretos en GitHub
En tu repo: `Settings > Secrets and variables > Actions`

Crea estos secretos:
- `SSH_HOST` = `rmsgestion.cl`
- `SSH_USER` = `felconx`
- `SSH_PRIVATE_KEY` = contenido completo de tu llave privada (la que corresponde a la publica autorizada en el servidor)
- `APP_DIR` = `/home/felconx/public_html/app`

## 2) Preparar servidor
Conectado por SSH al servidor:

```bash
mkdir -p /home/felconx/public_html/app
cd /home/felconx/public_html/app
git init
git remote add origin https://github.com/felconx87/SAE.git
git fetch origin main
git checkout -b main origin/main
```

## 3) Flujo de deploy
1. Haces cambios local.
2. `git push origin main`.
3. GitHub Actions entra por SSH y ejecuta:
   - `git fetch origin main`
   - `git checkout main`
   - `git pull --ff-only origin main`
   - ajuste de permisos

## 4) Importante para base de datos
- Mantener `db.php` fuera del repo.
- Configurar `db.php` directo en servidor.
- Importar `schema.sql` en `felconx_materiales` solo la primera vez (o en cambios de esquema).
