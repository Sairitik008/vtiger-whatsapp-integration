# Vtiger WhatsApp Integration — Implementation Plan

## Goal and constraints

Build a demonstrable Vtiger messaging integration using open-source components and the official WhatsApp Business Cloud API where authorized. Avoid paid intermediaries, but do not assume official WhatsApp messaging, hosting, domains, or operations are free.

The default implementation uses a local mock gateway. The repository also contains disabled-by-default groundwork for the official Meta Cloud API. The adapter has not been verified with live credentials or a public HTTPS webhook and must not be described as production-ready. No unofficial WhatsApp Web automation or reverse-engineered client will be used.

Reliability will be designed for recoverable failures, not promised as zero-failure: persist work before processing, make webhook processing idempotent, retry transient errors safely, and expose failed events and logs for diagnosis.

Deployment target: evaluate a free public hosting platform for the assignment demo, but do not assume it can provide reliable always-on production hosting at no cost. The deployment choice must support Vtiger's PHP/runtime requirements, a durable compatible database, required storage, and a reachable webhook endpoint. Keep a reproducible local/self-hosted deployment path as the dependable fallback. Do not enter payment details, enable paid resources, or deploy publicly without approval.

Platform constraints checked during planning (recheck immediately before deployment because free tiers change):

- GitHub Pages serves static websites and cannot run Vtiger's PHP application or database.
- Render documents that free web services sleep after inactivity and have ephemeral local filesystems; its free Postgres expires after 30 days, and its documentation says free instances are not for production.
- Railway currently lists a $0 Free plan with $1/month usage credit and constrained service resources; uninterrupted runtime, persistent storage, and capacity must be tested rather than assumed.
- A free hosted demo may therefore be temporary or have downtime/data limitations. If no platform satisfies the technical needs without a payment risk, use the local/self-hosted path for the complete demo rather than weakening persistence or hiding limitations.

## Current starting point

- The official Vtiger Open Source 8.4.0 source is installed in `vtigercrm/`.
- The isolated Docker Compose app and MySQL services are running locally; the app is bound to `127.0.0.1:8080` and the database port is not published.
- Installer completion and admin login were verified. Dashboard, Leads, and Contacts load. A repeatable Dashboard schema patch restores widget selection. The WhatsApp module is installed; its mock workflow is verified, and its optional Meta adapter remains disabled and unverified with live credentials. The CRM has no Leads or Contacts yet, so matched-record workflows still need a sample-record test.
- The fresh-install schema omitted `vtiger_salutationtype.color`, although Vtiger's picklist rendering queries it. A schema-only compatibility fix adds the nullable `VARCHAR(10)` column to the installer schema and the running local database; no Vtiger runtime logic was changed.
- Existing XAMPP Vtiger projects and databases remain untouched. No public deployment has been attempted.
- Keep WhatsApp customizations in supported Vtiger module/extension surfaces. The current schema compatibility adjustment is the only change inside the Vtiger source tree.

## Step-by-step plan

### 1. Confirm implementation inputs

- Identify the Vtiger Open Source version and PHP/MySQL environment.
- Decide whether the project will target an existing Vtiger install or provide local setup instructions.
- Evaluate candidate free hosting against Vtiger runtime compatibility, database persistence, storage, sleep/availability behavior, public webhook reachability, resource limits, and payment/overage risk.
- Choose a free public host only for a demo if it passes those checks and the user approves; preserve a documented local/self-hosted deployment path regardless.
- Confirm that the assignment demo should use the mock gateway only. Do not configure a real WhatsApp provider or collect API credentials by default.
- Check applicable Vtiger licensing and module conventions before packaging custom code.

**Completed:** Target version is Vtiger Open Source 8.4.0 in a new, isolated local Docker environment. The local mock-gateway approach remains the default. Official Meta Cloud API groundwork exists but remains disabled; no credentials, public webhook, or public host have been configured. Avoiding a paid intermediary does not guarantee that Meta messaging or deployment is cost-free.

**Gate:** Ask before making choices that affect compatibility, deployment, data migration, payment risk, public exposure, or a real external service.

### 2. Inspect Vtiger extension points

- Review the provided Vtiger version's module, UI, event/workflow, database, and REST conventions.
- Choose the least invasive supported way to add a WhatsApp action, conversation view, and pipeline information.
- Keep extension code separate from Vtiger core so upgrades and redeployment are practical.

**Completed:** The mock is a separate `modules/WhatsApp` extension with its own Vtiger view/actions, custom message/event tables, and Lead/Contact detail links. Vtiger core runtime files were not changed for this feature.

### 3. Define data and message lifecycle

- Define message fields: unique internal/reference ID, direction, sender/recipient, content/type, timestamp, status, and related Lead/Contact.
- Define allowed status transitions: Sent → Delivered → Read, or Sent → Failed.
- Define phone normalization and matching rules for Leads and Contacts.
- Define behavior for unmatched numbers and duplicate matches; never silently attach a message to an arbitrary record.
- Design database keys/indexes and retention/logging behavior before implementation.

### 4. Implement the local mock gateway and webhook flow

- Provide a local simulator for outbound messages and simulated status events.
- Accept incoming simulated messages through a webhook endpoint.
- Validate request shape and configured authentication/signature for the local integration.
- Persist each event before applying it, use provider/event IDs or idempotency keys to prevent duplicate processing, and make processing outcomes observable.
- Retry only transient failures with bounded backoff; show permanent failures rather than hiding them.

**Implemented:** Local outbound simulation with a per-form idempotency key, inbound simulation with idempotent event handling and resumable event state, exact phone matching, concurrency-guarded `SENT → DELIVERED → READ` / `SENT → FAILED` status transitions, and authenticated five-second UI polling for saved message/status updates with refreshed CSRF tokens on generated forms. This is not a public webhook or real WhatsApp transport; the polling makes CRM pages update without manual reload but does not connect to WhatsApp.

**Additional unverified provider groundwork:** An opt-in Meta Cloud API text adapter, persistent outbound queue, CLI-only worker, signed webhook verification/queue, provider status processing, a 24-hour free-text reply window, consent acknowledgement, and inbound record-access filtering have been added. Live sending remains disabled by default. Local PHP syntax, schema installation, Compose configuration, and HTTP/CLI exposure checks have passed; real provider delivery, externally reachable signed webhooks, production permissions, retention, backups, and deployment have not been verified. Raw webhook event payloads are removed after success or terminal failure; stored message contents still need a retention/deletion policy.

### 5. Integrate with Vtiger records

- Add a send-message action on Lead/Contact records using the CRM's supported UI patterns.
- Store outbound and inbound messages and link them to the matched CRM record.
- Show conversation history and message status in the record view.
- Keep pipeline stage changes distinct from message-status changes; display the latest message/status and next action in the pipeline.
- Add safe stage movement and validation consistent with existing Vtiger behavior.

**Implemented in the mock scope:** Lead/Contact detail links and matched message references. Pipeline customization and CRM pipeline-stage changes are not implemented.

### 6. Validate end-to-end behavior

- Test sending, persistence, unique references, timestamps, status transitions, inbound webhook processing, duplicate delivery, retries, failed delivery, phone matching, unmatched numbers, and pipeline visibility.
- Verify access control, input validation, output escaping, CSRF protections for CRM actions, webhook authentication, and safe error logging.
- Run the narrowest applicable tests and static checks, then perform a documented end-to-end demo.

**Verified:** PHP syntax checks; repeatable module installation; local outbound send and duplicate-request suppression; `SENT → DELIVERED → READ` and `SENT → FAILED`; inbound unmatched handling and replayed-event deduplication; automatic UI refresh without manual reload; CSRF-protected status actions after refresh; and a concurrent conflicting-status test where only one transition succeeds. Matched and ambiguous cases still need CRM sample records for a browser-level demonstration; the CRM currently has no Leads or Contacts. A status validation edge case was fixed so a nonexistent or non-owned message is rejected before a status event is recorded. Non-admin profile permission behavior remains to be verified.

### 7. Package for redeployment

- Provide the custom module/project source, database schema or migrations, and local mock gateway.
- Document prerequisites, installation/configuration, webhook setup, demo steps, troubleshooting, and every dependency with its license and cost status.
- Ensure the local/self-hosted demo works without paid API keys, paid hosting, subscriptions, or services.
- If an approved free public host is viable, document its free-tier limitations, persistence/backup behavior, sleep/restart behavior, data reset expectations, and safe redeployment steps. Do not represent a limited free demo as production-grade always-on hosting.

## Proposed project layout

The project code will be kept in the separate `whatsapp-vtiger-integration` folder. Its eventual internal layout will be decided after inspecting the target Vtiger version; avoid generating scaffolding that assumes an unconfirmed version.

## Acceptance checklist

- [x] Runs with the agreed Vtiger version and free/open-source local components.
- [x] Has a documented repeatable local/self-hosted deployment path.
- [ ] Any optional free public demo host has been tested for runtime compatibility, persistent data, webhooks, and payment/usage limits; limitations are clearly disclosed.
- [x] A CRM user can create a locally simulated message and see its stored reference, phone, and timestamp.
- [x] Repeated outbound submission with the same request ID does not create duplicate mock messages.
- [x] An open WhatsApp module page refreshes messages and status changes automatically while visible.
- [x] Concurrent status updates cannot overwrite an already changed status.
- [x] The simulator can report Sent, Delivered, Read, and Failed outcomes.
- [ ] Incoming simulated events are validated, persisted, processed idempotently, and mapped to the correct Lead/Contact when exactly one match is visible. (Matching logic is implemented; matched/ambiguous cases need sample CRM records for end-to-end verification.)
- [ ] Official Meta transport is validated end-to-end with a test business account, signed public HTTPS webhook, provider delivery statuses, retry/recovery scenarios, and permission checks.
- [ ] Production privacy/retention, approved-template, opt-in/opt-out, backup, monitoring, and security-review requirements are implemented and tested before enabling live sends.
- [x] Unmatched/ambiguous phone matches are retained without arbitrary record assignment and documented.
- [ ] Conversation history and latest WhatsApp status are visible from the CRM record and pipeline.
- [ ] The complete demo can be repeated from the documentation without paid credentials or services.

## Working approach

Before each significant implementation step, state what is being changed, why it is needed, how it affects the CRM/project, and whether it is necessary. Pause for approval at consequential decision gates. Keep changes scoped, readable, tested, and documented; do not add speculative features or claim guaranteed failure-free delivery.
