# Vtiger CRM WhatsApp Integration & Lead Pipeline

**Author:** PHP Developer  
**Position:** PHP Developer  
**Company:** NB Entrepreneurs  
**Date:** October 2026  

---

## Overview

This project is a technical implementation for integrating WhatsApp messaging with Vtiger CRM, along with a Lead/Contact management pipeline. 

Per the assignment requirements, the entire implementation is built using free and open-source tools (PHP, MySQL, Apache, vanilla JavaScript, and CSS). Because official WhatsApp Business API providers require commercial accounts or paid per-conversation fees, I built a local WhatsApp gateway simulator that replicates the full webhook lifecycle (sending, status callbacks, incoming replies, and contact mapping).

---

## System Workflow & Core Features

1. **Lead & Contact Management:**
   - Visual Kanban pipeline displaying records across stages (`New Lead`, `WhatsApp Contacted`, `Conversation Started`, `Qualified`, `Follow-up`, `Opportunity`, `Closed Won`, `Closed Lost`).
   - Cards display contact details, latest WhatsApp interaction, timestamp, and message status.
   - Stage changes can be made via drag-and-drop or status updates, persisted via REST API.

2. **WhatsApp Messaging from CRM:**
   - Users can dispatch messages directly from any lead or contact record.
   - Generates unique reference IDs (`WAM-YYYYMMDD...`).
   - Records outgoing messages in MySQL with an initial status of `sent`.
   - Updates the lead's latest interaction snapshot and advances new leads to `WhatsApp Contacted`.

3. **Inbound Webhook Handling:**
   - Dedicated endpoint (`/api/webhook.php`) processing inbound JSON payloads.
   - Checks message IDs for idempotency to handle potential webhook retries without duplicate records.
   - Normalizes incoming sender numbers to standard E.164 digits for database matching.

4. **Automatic Lead/Contact Mapping:**
   - Queries `leads_contacts` by indexed normalized phone numbers.
   - **Matched Records:** Automatically attaches incoming replies to the contact's conversation history and updates their pipeline stage to `Conversation Started`.
   - **Unmatched Numbers:** Automatically provisions a new record (`Inbound +[number]`) under `New Lead` so incoming queries are never dropped.

5. **Message Status Lifecycle:**
   - Tracks the 4 states: `sent` -> `delivered` -> `read` (and `failed`).
   - Ingests delivery receipts via webhook callbacks and updates both the message log and the lead's Kanban card.
   - Includes failure simulation with reason logging for testing exception paths.

6. **Conversation History:**
   - Two-way chat timeline inside each record displaying incoming and outgoing messages chronologically.
   - Shows timestamps, unique message IDs, and delivery ticks (single check for sent, double check for delivered, blue double check for read, warning badge for failed).
   - In-modal composer for sending replies.

7. **Local Gateway & Webhook Simulator:**
   - Developer/evaluator dashboard to test all required scenarios locally.
   - One-click triggers for delivery receipts (`delivered`, `read`, `failed`).
   - Form to simulate incoming customer messages from known or unknown numbers.
   - Live JSON inspector showing outgoing requests, webhook callbacks, and worker execution logs.

8. **Automated Lead Generation Outreach:**
   - **Inbound Inquiries:** When a prospective customer contacts the webhook from an unregistered number, the system automatically registers them and immediately dispatches a personalized welcome & qualification message, advancing them to `WhatsApp Contacted`.
   - **One-Click Welcome Outreach:** Dedicated `Send Welcome Outreach` button on New Leads allows sales agents to trigger automated introductory messaging with 1 click.
   - **CRM Lead Creation:** Option to automatically send a welcome WhatsApp message upon creating a new lead.

9. **Scheduled Message Sending (Cron Worker):**
   - Supports scheduling WhatsApp messages for a future delivery date & time (`scheduled_at`).
   - Provides quick scheduling presets (`+1 Hour`, `+3 Hours`, `Tomorrow 10 AM`, `Tomorrow 3 PM`).
   - Real-time status tracking with a distinct `Scheduled` indicator across Kanban cards, Table view, and Chat history.
   - `/api/scheduled_dispatch.php` worker simulates background cron execution to dispatch due messages.

10. **Interactive Click-to-Dialer:**
    - Every phone number across Kanban cards, Table rows, and the Chat modal header is a live `tel:` protocol link, allowing instant dialing from native phone dialers or desktop calling applications.

11. **Vtiger CRM Module Structure:**
    - Standalone Vtiger module files located in `vtiger-custom-module/` including `manifest.xml`, CRMEntity class `WhatsApp.php`, and action controller `Webhook.php` for integration with Vtiger 7.x/8.x installations.

---

## Tech Stack & Open-Source Compliance

| Component | Technology | Notes |
| :--- | :--- | :--- |
| Backend | PHP 8.2 | Object-oriented MVC structure, PDO prepared statements |
| Database | MySQL 8.0 / MariaDB | InnoDB engine, normalized tables, indexed lookups |
| Web Server | Apache 2.4 (XAMPP) | Local development server |
| Frontend | Vanilla JavaScript (ES6) | Native HTML5 Drag and Drop, Fetch API |
| Styling | Vanilla CSS3 | Custom flexbox/grid layout, Inter font |
| Dependencies | None | Built without paid APIs, third-party SaaS, or bloated libraries |

---

## Project Structure

```
vtiger-whatsapp-crm/
├── api/
│   ├── send.php               # Endpoint for sending WhatsApp messages
│   ├── webhook.php            # Webhook receiver for inbound messages & receipts
│   ├── chat.php               # Fetches conversation history by lead ID
│   ├── leads.php              # CRUD operations for pipeline leads
│   ├── update_stage.php       # Updates lead pipeline stage
│   ├── scheduled_dispatch.php # Cron dispatcher worker for scheduled messages
│   └── simulator_helper.php   # Supplies active messages & logs to simulator
├── assets/
│   ├── css/
│   │   ├── style.css          # Kanban board layout and core styles
│   │   ├── chat.css           # Chat timeline and message bubble styles
│   │   └── simulator.css      # Simulator dashboard styles
│   └── js/
│       ├── pipeline.js        # Drag-and-drop and board interactions
│       ├── chat.js            # Chat modal loader and message rendering
│       └── simulator.js       # Webhook trigger handlers and log viewer
├── includes/
│   └── db.php                 # Database connection singleton & phone normalizer
├── models/
│   ├── Lead.php               # Data model for leads, contacts, and pipeline logic
│   └── Message.php            # Data model for WhatsApp messages and status updates
├── services/
│   └── WhatsAppGateway.php    # Gateway abstraction layer for messaging workflows
├── vtiger-custom-module/      # Vtiger standard extension files
│   ├── manifest.xml           # Module manifest for Vtiger Module Manager
│   └── modules/
│       └── WhatsApp/
│           ├── WhatsApp.php   # Vtiger CRMEntity class
│           └── actions/
│               └── Webhook.php# Vtiger action controller for webhooks
├── bridge/                    # Free Open-Source WhatsApp Web Multi-Device Bridge (Baileys)
│   ├── server.js              # Express REST API, QR Code generator & Webhook forwarder
│   └── package.json           # Node.js dependencies (@whiskeysockets/baileys, express, qrcode)
├── package.json               # Root npm scripts ("npm start" launches bridge in VS Code)
├── .vscode/                   # VS Code configuration
│   └── tasks.json             # 1-click VS Code build and test tasks
├── schema.sql                 # Database creation and seed data script
├── ARCHITECTURE.md            # Technical details, database design, and API specs
└── index.php                  # Main application dashboard (Board & Table views)
```

---

## Installation & Setup

### Requirements:
- PHP 8.0 or higher
- MySQL 5.7+ or MariaDB 10.3+
- Apache web server (XAMPP / Laragon / standard LAMP stack)

### Step 1: Database Setup
Import `schema.sql` into MySQL:
```bash
mysql -u root -p < schema.sql
```
This creates the database `vtiger_whatsapp_crm` with the required tables and initial seed leads.

Database credentials can be adjusted in `includes/db.php`:
```php
private const DB_HOST = '127.0.0.1';
private const DB_NAME = 'vtiger_whatsapp_crm';
private const DB_USER = 'root';
private const DB_PASS = '';
```

### Step 2: Configure Web Server
Place the project in your Apache document root:
- Windows (XAMPP): `C:\xampp\htdocs\vtiger-whatsapp-crm`
- Linux: `/var/www/html/vtiger-whatsapp-crm`

Ensure Apache and MySQL services are running.

### Step 3: Access the Application
Open your browser and navigate to:
```
http://localhost/vtiger-whatsapp-crm/
```

---

## Verifying the 13 Test Cases

The application can be verified step-by-step:

1. **Pipeline Display:** The home screen displays the 8 pipeline stages with seed leads categorized by status.
2. **Send Outgoing Message:**
   - Click "Send Msg" on *Rahul Sharma* (`+91 98230 12345`).
   - Enter message text and submit.
   - The card updates with the new message preview and status `Sent`.
3. **Simulate Delivery Receipt (`delivered`):**
   - Click "Webhook & Gateway Simulator" in the top bar.
   - Select the message reference from the dropdown and click "Trigger Delivered".
   - The card status updates to `Delivered` (`✓✓`).
4. **Simulate Read Receipt (`read`):**
   - In the simulator, click "Trigger Read".
   - The card status updates to `Read` with blue double checkmarks (`✓✓`).
5. **Simulate Inbound Customer Reply:**
   - In the simulator under "Simulate Inbound Customer Reply", enter `+91 98230 12345` with message: *"Yes, please share the proposal."*
   - Click "Dispatch Incoming Webhook".
   - The webhook matches Rahul Sharma, records the incoming message, and moves the card to `Conversation Started`.
6. **Simulate Unmatched Inbound Number:**
   - In the simulator, enter a new number: `+91 70000 11111` with message: *"Need quotation for bulk order."*
   - Click "Dispatch Incoming Webhook".
   - The webhook creates a new lead under the `New Lead` stage with the message attached.
7. **View Conversation History:**
   - Click "Chat History" on any lead to inspect the full two-way chat transcript with message IDs, timestamps, and delivery indicators.
8. **Simulate Message Failure:**
   - In the "Send Msg" modal, check "Simulate Delivery Failure scenario" and submit.
   - The message is logged as `failed`, and the card displays a red warning badge with the failure reason.
9. **Pipeline Stage Transition:**
   - Drag a lead card from one column to another (e.g., from `Qualified` to `Opportunity`).
   - The position is updated and saved asynchronously via `/api/update_stage.php`.
10. **Inspect Raw Webhook Payloads:**
    - The bottom of the simulator displays the raw JSON payloads and response codes for debugging and verification.
