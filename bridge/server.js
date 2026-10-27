import makeWASocket, { DisconnectReason, useMultiFileAuthState, fetchLatestBaileysVersion } from '@whiskeysockets/baileys';
import express from 'express';
import QRCode from 'qrcode';
import pino from 'pino';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const app = express();
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

const PORT = 3333;
const CRM_WEBHOOK_URL = 'http://localhost/vtiger-whatsapp-crm/api/webhook.php';

let sock = null;
let qrCodeDataUrl = null;
let isConnected = false;
let userJid = null;
let lastError = null;

// Normalize phone to international format without non-digits
function normalizePhone(phone) {
    let clean = phone.replace(/[^0-9]/g, '');
    if (clean.length === 10) {
        clean = '91' + clean;
    }
    if (clean.length === 11 && clean.startsWith('0')) {
        clean = '91' + clean.slice(1);
    }
    return clean;
}

process.on('uncaughtException', (err) => {
    // Suppress transient Baileys Bad MAC or crypto session errors
    if (!err.message?.includes('Bad MAC')) {
        console.error('[WhatsApp Bridge] Caught Exception (prevented crash):', err.message);
    }
});
process.on('unhandledRejection', (reason) => {
    console.error('[WhatsApp Bridge] Unhandled Rejection (prevented crash):', reason?.message || reason);
});

async function connectToWhatsApp() {
    const { state, saveCreds } = await useMultiFileAuthState(path.join(__dirname, 'auth_info_baileys'));
    const { version, isLatest } = await fetchLatestBaileysVersion();
    
    console.log(`[WhatsApp Bridge] Using Baileys v${version.join('.')}, isLatest: ${isLatest}`);

    sock = makeWASocket({
        version,
        logger: pino({ level: 'silent' }),
        auth: state,
        browser: ['Vtiger CRM WhatsApp Integration', 'Chrome', '1.0.0']
    });

    sock.ev.on('creds.update', saveCreds);

    sock.ev.on('connection.update', async (update) => {
        const { connection, lastDisconnect, qr } = update;

        if (qr) {
            console.log('\n[WhatsApp Bridge] NEW QR CODE GENERATED! Open http://localhost:3333 to scan.\n');
            qrCodeDataUrl = await QRCode.toDataURL(qr, { scale: 8 });
            isConnected = false;
        }

        if (connection === 'close') {
            const statusCode = lastDisconnect?.error?.output?.statusCode;
            const shouldReconnect = statusCode !== DisconnectReason.loggedOut;
            console.log(`[WhatsApp Bridge] Connection closed. Reason code: ${statusCode}. Reconnecting: ${shouldReconnect}`);
            isConnected = false;
            qrCodeDataUrl = null;
            if (shouldReconnect) {
                setTimeout(connectToWhatsApp, 3000);
            }
        } else if (connection === 'open') {
            console.log('\n======================================================');
            console.log(' [WhatsApp Bridge] SUCCESSFULLY CONNECTED TO WHATSAPP!');
            userJid = sock.user?.id || 'Connected';
            console.log(` Connected Account: ${userJid}`);
            console.log('======================================================\n');
            isConnected = true;
            qrCodeDataUrl = null;
            lastError = null;
        }
    });

    // Handle Inbound Messages
    sock.ev.on('messages.upsert', async ({ messages, type }) => {
        if (type !== 'notify') return;

        for (const msg of messages) {
            if (!msg.message || msg.key.fromMe) continue;

            const fromJid = msg.key.remoteJid;
            if (!fromJid || !fromJid.endsWith('@s.whatsapp.net')) continue;

            const senderPhone = fromJid.replace('@s.whatsapp.net', '');
            const text = msg.message?.conversation || 
                         msg.message?.extendedTextMessage?.text || 
                         msg.message?.imageMessage?.caption || '';

            if (!text.trim()) continue;

            console.log(`[Inbound Message] From: +${senderPhone} | Text: "${text}"`);

            // Forward to Vtiger CRM Webhook
            try {
                const response = await fetch(CRM_WEBHOOK_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        event_type: 'incoming_message',
                        sender_number: senderPhone,
                        recipient_number: userJid ? userJid.split(':')[0] : 'CRM',
                        message_content: text,
                        message_id: msg.key.id || ('WAM-LIVE-' + Date.now()),
                        timestamp: new Date().toISOString().slice(0, 19).replace('T', ' ')
                    })
                });
                const resJson = await response.json();
                console.log(` -> Webhook response: ${JSON.stringify(resJson)}`);
            } catch (err) {
                console.error(` -> Failed to forward inbound message to CRM webhook: ${err.message}`);
            }
        }
    });
}

// -------------------------------------------------------------
// HTTP REST API ENDPOINTS
// -------------------------------------------------------------

// 1. Dashboard & QR Scanner View
app.get('/', (req, res) => {
    res.setHeader('Content-Type', 'text/html; charset=utf-8');
    if (isConnected) {
        res.send(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>WhatsApp Bridge - Connected</title>
                <meta http-equiv="refresh" content="10">
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; text-align: center; padding: 40px 20px; }
                    .card { background: #1e293b; max-width: 500px; margin: 0 auto; padding: 30px; border-radius: 12px; border: 1px solid #334155; box-shadow: 0 10px 25px rgba(0,0,0,0.4); }
                    .badge { background: #10b981; color: white; padding: 6px 14px; border-radius: 20px; font-weight: bold; font-size: 14px; display: inline-block; margin-bottom: 15px; }
                    h2 { margin: 10px 0; color: #38bdf8; }
                    p { color: #94a3b8; font-size: 14px; line-height: 1.6; }
                    .btn { display: inline-block; background: #2563eb; color: white; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: bold; margin-top: 15px; }
                </style>
            </head>
            <body>
                <div class="card">
                    <span class="badge">ONLINE & CONNECTED</span>
                    <h2>WhatsApp Bridge Active</h2>
                    <p>Connected Account: <strong>${userJid || 'Business WhatsApp'}</strong></p>
                    <p>The bridge is running! Messages sent from Vtiger CRM will be physically dispatched to real WhatsApp numbers automatically for FREE.</p>
                    <a href="http://localhost/vtiger-whatsapp-crm/" class="btn">Open CRM Pipeline</a>
                </div>
            </body>
            </html>
        `);
    } else if (qrCodeDataUrl) {
        res.send(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>Scan QR Code - WhatsApp Bridge</title>
                <meta http-equiv="refresh" content="5">
                <style>
                    body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; text-align: center; padding: 30px 20px; }
                    .card { background: #1e293b; max-width: 480px; margin: 0 auto; padding: 25px; border-radius: 12px; border: 1px solid #334155; }
                    h2 { color: #22c55e; margin-bottom: 8px; }
                    .qr-box { background: white; padding: 12px; border-radius: 8px; display: inline-block; margin: 15px 0; }
                    .steps { text-align: left; background: #0f172a; padding: 15px 20px; border-radius: 8px; font-size: 13px; color: #cbd5e1; margin-top: 15px; }
                    .steps ol { margin: 0; padding-left: 20px; }
                    .steps li { margin-bottom: 6px; }
                </style>
            </head>
            <body>
                <div class="card">
                    <h2>Link WhatsApp Account (100% Free)</h2>
                    <p style="color: #94a3b8; font-size: 13px;">Scan this QR code with your phone to link your WhatsApp to Vtiger CRM.</p>
                    <div class="qr-box">
                        <img src="${qrCodeDataUrl}" alt="Scan QR Code" style="width: 260px; height: 260px; display: block;" />
                    </div>
                    <div class="steps">
                        <strong>How to scan:</strong>
                        <ol>
                            <li>Open <strong>WhatsApp</strong> on your phone.</li>
                            <li>Tap <strong>Settings</strong> or <strong>3 dots (menu)</strong>.</li>
                            <li>Tap <strong>Linked Devices</strong> &rarr; <strong>Link a Device</strong>.</li>
                            <li>Point your camera at this QR code.</li>
                        </ol>
                    </div>
                </div>
            </body>
            </html>
        `);
    } else {
        res.send(`
            <!DOCTYPE html>
            <html>
            <head>
                <title>WhatsApp Bridge Starting...</title>
                <meta http-equiv="refresh" content="3">
                <style>
                    body { font-family: sans-serif; background: #0f172a; color: white; text-align: center; padding: 50px; }
                </style>
            </head>
            <body>
                <h3>Starting WhatsApp Bridge... Please wait.</h3>
                <p>Generating session credentials...</p>
            </body>
            </html>
        `);
    }
});

// 2. Health & Status JSON API
app.get('/status', (req, res) => {
    res.json({
        success: true,
        connected: isConnected,
        user: userJid,
        has_qr: !isConnected && !!qrCodeDataUrl
    });
});

// 3. Send Message API (Accepts both POST and GET)
app.all('/send', async (req, res) => {
    const phone = req.body?.phone || req.query?.phone;
    const message = req.body?.message || req.query?.message;

    if (!phone || !message) {
        return res.status(400).json({
            success: false,
            error: 'Missing required parameters: "phone" and "message" are mandatory.'
        });
    }

    if (!isConnected || !sock) {
        return res.status(503).json({
            success: false,
            error: 'WhatsApp Bridge is not connected. Open http://localhost:3333 to scan the QR code first.'
        });
    }

    try {
        const cleanPhone = normalizePhone(phone);
        const jid = `${cleanPhone}@s.whatsapp.net`;

        console.log(`[WhatsApp Bridge] Sending message to ${jid}...`);
        const sent = await sock.sendMessage(jid, { text: message });

        console.log(`[WhatsApp Bridge] Message sent successfully! Msg ID: ${sent.key.id}`);

        res.json({
            success: true,
            message_id: sent.key.id,
            recipient: cleanPhone,
            timestamp: new Date().toISOString()
        });
    } catch (err) {
        console.error(`[WhatsApp Bridge] Send Error: ${err.message}`);
        res.status(500).json({
            success: false,
            error: err.message
        });
    }
});

app.listen(PORT, () => {
    console.log(`\n======================================================`);
    console.log(` [WhatsApp Bridge] Listening on http://localhost:${PORT}`);
    console.log(` Open http://localhost:${PORT} in your browser to view the QR Code.`);
    console.log(`======================================================\n`);
    connectToWhatsApp();
});
