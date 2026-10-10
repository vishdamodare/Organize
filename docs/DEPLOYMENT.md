# Setup & Deployment

## Requirements

- Linux server (Ubuntu 22.04+/Debian 12) — 2 vCPU / 4 GB RAM handles roughly 10–20 small companies; add RAM per ~20 more.
- PHP **8.3 or 8.4** + PHP-FPM with: pdo_mysql, mbstring, gd, intl, bcmath, zip, xml, curl, exif, fileinfo, openssl.
- MariaDB 10.6+ / MySQL 8.0+.
- nginx (or Apache), a domain, and a TLS certificate (Let's Encrypt). **HTTPS is mandatory.**
- `rsync`, `openssl`, `curl`, `unzip`, `mysqldump`; Node 20+ only for running tests.

## Local development

```bash
cp .env.example .env                    # set DB_ROOT_USER / DB_ROOT_PASSWORD
scripts/fetch-espocrm.sh                # downloads pinned EspoCRM into build/
scripts/create-company.sh demo "Shree Lakshmi Jewellers" http://localhost:8080 owner
scripts/serve.sh demo 8080              # → http://localhost:8080
php scripts/seed-demo.php instances/demo 'Some-Pass-123'   # optional demo data
tests/run.sh                            # automated tests
```

## Production layout (per company)

One subdomain per company, e.g. `shreeganesh.goldcrm.example` → `instances/shreeganesh/public`.

```nginx
server {
    listen 443 ssl http2;
    server_name shreeganesh.goldcrm.example;
    root /srv/goldcrm/instances/shreeganesh/public;
    index index.php;
    client_max_body_size 20m;

    location /client/ { alias /srv/goldcrm/instances/shreeganesh/client/; }
    location /api/v1/ {
        if (!-e $request_filename) { rewrite ^/api/v1/(.*)$ /api/v1/index.php last; }
    }
    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
    location ~ /\.  { deny all; }
}
```
(Matches EspoCRM's documented nginx config; check https://docs.espocrm.com for the current version.) Create a company with its real URL: `scripts/create-company.sh shreeganesh "Shree Ganesh Jewellers" https://shreeganesh.goldcrm.example owner`.

File permissions: instance files owned by the PHP-FPM user; `data/`, `custom/`, `client/custom/` writable.

## Hostinger shared hosting (one company per subdomain)

1. hPanel → **Domains → Subdomains**: create `crm.yoursite.com`. Note its folder (e.g. `public_html/crm`).
2. hPanel → **Websites → (the site) → Advanced → PHP Configuration**: PHP **8.3** or newer.
3. hPanel → **Databases → Management**: create a database + user; note name, user, password.
4. hPanel → **Advanced → SSH Access**: enable, then connect and run:
   ```bash
   git clone https://github.com/vishdamodare/Organize.git ~/organize
   bash ~/organize/scripts/install-hostinger.sh
   ```
   It asks for the subdomain folder (full path, e.g. `/home/u123456789/domains/yoursite.com/public_html/crm`),
   the URL (`https://crm.yoursite.com`), company name and DB details, then prints the owner login.
5. hPanel → **Advanced → Cron Jobs**: add the every-minute command the script prints.
6. hPanel → **Security → SSL**: confirm the subdomain has SSL (free, usually automatic).

The root `.htaccess` shipped with EspoCRM routes requests into `public/` and blocks `data/`, `custom/`, `vendor/`.
Module updates: `bash ~/organize/scripts/update-hostinger.sh` (pulls, copies the module, rebuilds, and bumps the
app timestamp so every browser loads the new CSS/JS — open tabs get a "Refresh" prompt; nobody needs to clear caches).

## Database

`create-company.sh` creates `goldcrm_<slug>` (utf8mb4) and a DB user `gc_<slug>` with privileges only on that database. `.env` holds the provisioning account; it is not used by the running app.

## Scheduled jobs

Every minute: `* * * * * /srv/goldcrm/scripts/cron.sh >/dev/null 2>&1` (reminders, notifications, cleanup for all companies).

## Email

Per company: Administration → Outbound Emails (SMTP). Required for invitations, password reset and email reminders.

## Migrations / deploying module changes

```bash
scripts/backup.sh <slug>            # for each company (or loop)
scripts/deploy-module.sh --all      # copies src/ into every instance, runs rebuild + clear-cache
tests/run.sh                        # on staging first
```
`rebuild` only adds schema. Destructive schema changes need a reviewed SQL migration and a backup.

## Backup & restore

```bash
scripts/backup.sh <slug>                                        # → backups/<slug>-<ts>.sql.gz + .files.tar.gz
scripts/restore.sh <slug> backups/<f>.sql.gz backups/<f>.files.tar.gz   # asks for confirmation
```
Run nightly per company, copy off-server encrypted, keep ≥14 days, test a restore monthly.

## Upgrading EspoCRM

1. Set `ESPO_VERSION` in `.env`, run `scripts/fetch-espocrm.sh`, record the printed sha256.
2. On staging: back up, upgrade one instance with EspoCRM's CLI upgrade package (`php command.php upgrade` — UI upgrade is disabled by restricted mode), `scripts/deploy-module.sh <slug>`, `tests/run.sh`.
3. Roll out company by company. Rollback: restore that company's backup and the previous instance directory.

## Logs & monitoring

- App logs: `instances/<slug>/data/logs/espo-YYYY-MM-DD.log`; nginx/PHP-FPM logs.
- Health check: `GET https://<company>/api/v1/App/user` → 401 when healthy (app up, auth required); 5xx = alert.
- Watch disk (uploads), DB size, failed cron runs.

## Rollback (module release)

Check out the previous `src/` version, `scripts/deploy-module.sh --all`. If data was migrated, restore from the pre-deploy backup.
