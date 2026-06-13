# Evolution API → ARIA Webhook Setup

Connect an Evolution API instance (e.g. **Amec2**) to ARIA so incoming WhatsApp messages trigger the agent.

## Prerequisites

- Instance connected in Evolution API (e.g. at https://api.promithic.com.br/manager/)
- ARIA deployed at https://app.tenazcrm.com.br
- `EVOLUTION_API_URL` and `EVOLUTION_API_KEY` set in ARIA `.env`

---

## Step 1: Register Instance in ARIA

The webhook uses the instance name to resolve which user (tenant) owns the conversation. **You must register the instance in ARIA first.**

1. Log in to **ARIA** → **WhatsApp** (sidebar)
2. Click **"+ Nova instância"**
3. Fill in:
   - **Nome da instância:** `Amec2` (must match Evolution API exactly, case-sensitive)
   - **Apelido (opcional):** e.g. "Vendas" or leave empty
4. Click **Criar instância**

The API URL is taken from config (`EVOLUTION_API_URL`), so you don't need to type it.

---

## Step 2: Configure Webhook in Evolution API

Point Evolution API to ARIA's webhook so incoming messages are forwarded.

**Important:** Use a **different URL per instance** (instance name in the path). If all instances use the same URL, ARIA cannot tell which instance received the message and the wrong WhatsApp number may reply.

| Instance | Webhook URL |
|----------|-------------|
| Amec1 | `https://app.tenazcrm.com.br/api/webhook/whatsapp/Amec1` |
| Amec6 | `https://app.tenazcrm.com.br/api/webhook/whatsapp/Amec6` |
| (any) | `https://app.tenazcrm.com.br/api/webhook/whatsapp/{InstanceName}` |

### Option A: Evolution Manager UI

For **each** instance, in the Webhook section:

- **URL:** `https://app.tenazcrm.com.br/api/webhook/whatsapp/<InstanceName>` (e.g. `.../Amec1` or `.../Amec6`)
- **Events:** Enable `MESSAGES_UPSERT`
- **Enabled:** Yes

### Option B: Evolution API (curl)

**Amec1:**
```bash
curl -X POST "https://api.promithic.com.br/webhook/set/Amec1" \
  -H "Content-Type: application/json" \
  -H "apikey: YOUR_EVOLUTION_API_KEY" \
  -d '{
    "webhook": {
      "url": "https://app.tenazcrm.com.br/api/webhook/whatsapp/Amec1",
      "events": ["MESSAGES_UPSERT"],
      "enabled": true,
      "webhookByEvents": true,
      "webhookBase64": true
    }
  }'
```

**Amec6:**
```bash
curl -X POST "https://api.promithic.com.br/webhook/set/Amec6" \
  -H "Content-Type: application/json" \
  -H "apikey: YOUR_EVOLUTION_API_KEY" \
  -d '{
    "webhook": {
      "url": "https://app.tenazcrm.com.br/api/webhook/whatsapp/Amec6",
      "events": ["MESSAGES_UPSERT"],
      "enabled": true,
      "webhookByEvents": true,
      "webhookBase64": true
    }
  }'
```

Replace `YOUR_EVOLUTION_API_KEY` with your Evolution API key. Use the same base URL and add `/{InstanceName}` so each instance has a unique webhook URL.

---

## Step 3: Verify

1. Send a WhatsApp message to the number linked to **Amec2**
2. ARIA should receive it, create/find the lead, and reply via the agent
3. Check ARIA **Conversas** for the new conversation

---

## Troubleshooting

| Issue | Check |
|-------|------|
| No reply from agent | Instance name in ARIA must match Evolution exactly (`Amec2`) |
| 404 on webhook | Ensure ARIA is deployed and route `/api/webhook/whatsapp` exists |
| Wrong tenant | Instance must be registered in ARIA under the correct user |
| Wrong number answers (e.g. Amec2 replies when you message Amec6) | Use a **different webhook URL per instance** with the instance name in the path: `.../api/webhook/whatsapp/Amec6` |
| Evolution API errors | Verify `apikey` header and instance name in webhook/set request |

---

## Instance Name Reference

- Evolution API instance names (e.g. **Amec1**, **Amec6**) must match ARIA and the path in the webhook URL.
- Webhook URL per instance: `https://app.tenazcrm.com.br/api/webhook/whatsapp/{InstanceName}`
- Required event: `MESSAGES_UPSERT`
