# Vtiger WhatsApp Integration

This project uses the official Vtiger CRM Open Source 8.4.0 release as its base. Custom WhatsApp integration code will be added as Vtiger-compatible modules; existing Vtiger source and external XAMPP projects are kept separate.

The local runtime uses Docker Compose so the CRM database is isolated from other databases on the machine. The web service binds to `127.0.0.1` only and is not publicly exposed. Vtiger's installer registration hostname (`stats.vtiger.com`) is mapped to loopback in the app container to prevent the optional registration request from sending installer details externally.

## Current status

Vtiger 8.4.0 is installed locally. Admin login and the Dashboard, Leads, and Contacts pages have been verified. A fresh install left the dashboard-widget table without columns required by Vtiger 8.4.0; this disabled the Dashboard's Add Widget control. The repeatable compatibility patch in `database/patches/vtiger-dashboard-widgets.sql` is applied to the current database, and a Leads by Status widget loads. The CRM has no Leads yet, so the widget correctly reports no matching data. Apply the patch after completing the Vtiger installer on future installs.

The local WhatsApp module is installed. Its default transport is a mock: simulated outbound messages have per-form idempotency keys, guarded delivery/read/failure transitions, duplicate-protected inbound events, exact phone matching to Leads/Contacts, and authenticated message-list refresh approximately every five seconds. The project also contains an **unverified, opt-in adapter** for Meta's official WhatsApp Business Cloud API. It is disabled by default; no API credentials or public webhook are configured, and no real WhatsApp delivery has been tested. Do not treat this code as production-ready. Keep `.env` private and never commit credentials.

The Vtiger 8.4.0 fresh-install schema omitted `color` from `vtiger_salutationtype`, but list-view picklist rendering expects that column. The bundled schema has a nullable `VARCHAR(10)` compatibility column, and the running local database has the corresponding column. This is a schema-only adjustment; Vtiger runtime code is unchanged.

## Requirements

- Docker Desktop with Docker Compose
- The Vtiger 8.4.0 source in `vtigercrm/`
- Internet access for the first image build

## First-time setup

1. Copy `.env.example` to `.env`.
2. Set `VTIGER_DB_PASSWORD` and `MYSQL_ROOT_PASSWORD` to different, strong random values. Keep `.env` private; it is excluded from Git.
3. From this directory, build and start the services:

   ```powershell
   docker compose up --build -d
   ```

4. Open `http://127.0.0.1:8080` and complete the Vtiger installer.
5. In the installer, use database host `db`, the database name/user/password from `.env`, and database port `3306`.
6. Apply the Dashboard schema compatibility patch from this directory:

   ```powershell
   $database = (Get-Content .\.env | Where-Object { $_ -match '^VTIGER_DB_NAME=' } | Select-Object -First 1) -replace '^VTIGER_DB_NAME=', ''
   if (-not $database) { $database = 'vtiger' }
   $mysqlCommand = 'MYSQL_PWD="$MYSQL_ROOT_PASSWORD" mysql -uroot "' + $database + '"'
   Get-Content .\database\patches\vtiger-dashboard-widgets.sql -Raw |
     docker compose exec -T db sh -c $mysqlCommand
   ```

   The patch is safe to rerun and only adds missing nullable columns and a foreign key; it keeps existing widget records and assigns any unassigned widgets to the user's default Dashboard tab.

7. Install or update the WhatsApp module and its schema:

   ```powershell
   docker compose exec -T app php modules/WhatsApp/install.php
   ```

   It is safe to rerun. It creates the message, event, and outbox tables, applies missing columns, and adds a **WhatsApp Mock** action to Lead and Contact detail pages. The install script is CLI-only.

## Local WhatsApp mock demo

1. Open **WhatsApp** from the CRM's Tools menu, or open a Lead/Contact and choose **WhatsApp Mock**.
2. Submit an outbound mock message. It is saved locally as `SENT`; nothing is sent to a phone or external service. Repeated submission of the same form request ID does not create a second message. Open WhatsApp module pages refresh the message list automatically about every five seconds while visible.
3. Use **DELIVERED** then **READ**, or choose **FAILED** while a message is `SENT`, to test the allowed status transitions.
4. Submit an inbound mock event. The phone is normalized to digits and compared exactly against visible Lead/Contact phone and mobile values. It links only when exactly one CRM record matches; unmatched and ambiguous events remain unlinked.
5. To verify idempotency, submit the same inbound event again with the same Event ID. The duplicate is detected and no second message is created.

The mock module requires a logged-in Vtiger user and Vtiger's standard CSRF validation. Polling is near-real-time UI refresh, not transport to/from WhatsApp. The local mock inbound action is authenticated and is not the public provider webhook.

## Official Meta Cloud API groundwork (not production-ready)

The optional adapter uses Meta's official Cloud API directly and does not require a paid intermediary. Real WhatsApp use is **not guaranteed to be free**: Meta's current pricing and eligibility rules, hosting, a public domain/TLS, and operations may incur costs. Verify current terms before enabling it. This adapter has not been tested with real credentials, an externally reachable webhook, approved business assets, or a production deployment.

The safe defaults are `WHATSAPP_TRANSPORT=mock` and `WHATSAPP_LIVE_SEND_ENABLED=0`. Leave them unchanged for the local demo. Live mode requires Meta-approved setup and all configuration: Graph API version, phone-number ID, a system Vtiger user ID, access token, app secret, and webhook verify token. Supply secrets through the deployment platform's secret manager; if using `*_FILE`, mount the referenced secret files read-only into **both** the web and worker containers. Do not put live credentials in source control, the web root, or screenshots.

Only after independent security, privacy, permissions, and deployment review:

1. Configure the Meta business assets and official Cloud API credentials using Meta's current documentation.
2. Set `WHATSAPP_TRANSPORT=meta`, supply all required settings, then set `WHATSAPP_LIVE_SEND_ENABLED=1` only when explicitly authorized.
3. Run the module installer from the setup section to apply the outbox/event schema.
4. Deploy the application at a stable HTTPS address and register `https://<your-host>/modules/WhatsApp/webhook.php` as the callback with Meta. Use the same high-entropy verify token in the Meta configuration and the secret manager.
5. Start the optional queue worker with `docker compose --profile whatsapp-worker up -d app db whatsapp-worker` after configuring secrets for both containers. Do not run it as a web request.
6. Verify webhook challenges, signed event handling, provider acceptance, delivery status, and recovery behavior in a non-production Meta test environment before considering production use.

Current scope and blockers:

- The adapter accepts inbound **text only** and sends free-text replies only when an inbound message from that number is within the 24-hour service window. Approved template messages, media, opt-in evidence management, unsubscribe handling, and business-policy workflows are not implemented.
- Outbound and webhook work is queued. Explicit rate limits have bounded retries; ambiguous outbound outcomes are marked `UNKNOWN` and must be reconciled with provider status before any manual retry to avoid duplicate sends.
- Inbound messages matched to exactly one Lead/Contact are visible only to CRM users permitted to view that record. Unmatched messages remain visible to the configured integration account; configure ownership and operational access deliberately.
- Raw webhook payloads are cleared after successful processing or terminal failure, but CRM message contents currently have no retention/deletion policy. Define and implement one before production.
- The worker, schema upgrade, and signature helpers have received local checks, but end-to-end live delivery, webhook retry/order behavior, permission handling, load, backups, retention, and production deployment have not been verified. This is development groundwork, not a production-ready integration.

No unofficial WhatsApp Web automation or reverse-engineered client is used. Do not expose the current local Docker setup publicly or enable live sends until the listed blockers are resolved.

The database is stored in the Docker volume `mysql_data` and survives container restarts. `docker compose down` stops the services without deleting that data. **Do not use `docker compose down -v` unless you intend to delete the local CRM database.**

## Useful commands

```powershell
docker compose ps
docker compose logs -f app
docker compose logs -f db
docker compose down
```

Change `VTIGER_HTTP_PORT` in `.env` if port 8080 is already in use, then recreate the app container with `docker compose up -d`.

## Deployment and WhatsApp scope

This setup is a local development/demo environment, not a public production deployment. Public hosting must be evaluated for runtime compatibility, persistent storage, webhook reachability, uptime, security, and payment risk. No live WhatsApp API or external WhatsApp account is configured.

## Runtime components

- Vtiger CRM Open Source 8.4.0
- PHP 8.2 with Apache HTTP Server
- MySQL 8.0
- Docker Compose
