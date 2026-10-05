# Integrating external WordPress / Elementor sites

Every client website is registered in **Websites** (or created together with the client). Registration returns:

| Value | Where it goes | Secret? |
|---|---|---|
| `site_key` (`ws_…`) | tracker tag, `X-Nivc-Site` header | public |
| API token (`nvc_…`) | `Authorization: Bearer` header, **server-to-server only** | **secret – shown once, stored only as a SHA-256 hash** |

The token can be rotated at any time (old one stops working immediately).

## 1. NivCreative Connector plugin (recommended)

`connector/nivcreative-connector/` — install on the client's WordPress, then *Settings → NivCreative*:
panel URL (`https://nivcreative.com/panel`), site key, token → **Test connection**.

* Hooks `elementor_pro/forms/new_record`; maps fields `name / phone / email / message` (by field id or type; everything else is
  appended to the message), form name and page URL.
* Reads the first-party `nc_attr` cookie (set by the tracker) to attach UTM + referrer.
* Failed deliveries (network / 5xx / 429) are queued and retried every 5 minutes for 7 days; the `external_id` makes retries idempotent.
* Prints the cookie-less tracker tag on the front-end (skipped for logged-in admins).
* Sends an hourly heartbeat (`/api/v1/ping`) → "Connected" status, connector + WordPress version in the panel.
* Any other form plugin: `do_action('nivcreative_send_lead', ['name'=>…,'phone'=>…,'email'=>…,'message'=>…], ['landing_url'=>…]);`

## 1b. Several clients on one WordPress site (e.g. their landing pages live on nivcreative.com)

A *website* in the panel belongs to exactly **one client**. When different clients' landing pages share a domain, create **one website per
client** (same URL, own site key + token) and register that client's landing page(s) on it. Then:

* **Panel side:** *Add client* with the shared site URL **and the landing-page URL** — the website is created in **strict mode**
  (*Websites → edit → "Accept data from: only this website's registered landing pages"*). In strict mode a lead whose `landing_url` is not an
  active registered page of that website is rejected with `422 landing_url`, and the tracker ignores other paths. Client A's credentials can
  therefore never deliver a lead for client B's page, and each client's dashboard contains data from her own page only.
* **Connector side (one install on the shared site):** *Settings → NivCreative → Routes* — one row per page: path (`/client-c`, or `/folder/*`),
  site key, token. Elementor forms submitted on `/client-c` are sent with that row's credentials; the tracker tag printed on that page carries
  that client's site key. Pages without a row send nothing and print no tracker.
* Without the connector: paste the client's own tracker tag (from *Websites → Keys & installation*) into that page only and use the
  Elementor *Webhook*/your own code to call the lead API with that client's token.

## 2. Direct API

### `POST /api/v1/leads`

Headers: `X-Nivc-Site: <site_key>`, `Authorization: Bearer <token>`, `Content-Type: application/json`.

```json
{
  "name": "Israel Israeli", "phone": "050-123-4567", "email": "a@b.co", "message": "…",
  "utm_source": "instagram", "utm_medium": "cpc", "utm_campaign": "summer", "utm_content": "", "utm_term": "",
  "referrer": "https://l.instagram.com/", "landing_url": "https://client.co.il/landing", "landing_page_id": 12,
  "device": "mobile", "external_id": "uuid-for-idempotency", "form_name": "Hero form"
}
```

* At least one of `name`, `phone`, `email`. Phones are normalised to Israeli format + WhatsApp international digits.
* `client_id` is **ignored** (resolved from the site); if present and different the call is rejected with 403.
* `landing_page_id` must belong to the site; otherwise the page is matched by the path of `landing_url`.
* Responses: `201 {"ok":true,"id":123,"duplicate":false}`, `200` when `external_id` was already received, `401` bad credentials,
  `403` site/account disabled or client mismatch, `413` > 64 KB, `415` not JSON, `422 {"error":{"fields":{…}}}`, `429` rate limit.
* Limits: 60 requests/min/IP, 300/min/site; 20 failed authentications/10 min/IP. Every call is written to `api_logs` (30 days).

### `GET /api/v1/ping?connector=1.0.0&wp=6.8` — heartbeat (same auth).

### `POST /api/v1/track` — browser beacon

`text/plain` JSON body `{ "site", "path", "vid", "utm_source", "utm_medium", "utm_campaign", "referrer" }`. No secret: the request is
accepted only if the `Origin` host matches the website's registered domain, the path belongs to an active landing page, the user agent
is not a bot, and rate limits allow it. Always answers `204`.

Tracker tag (paste into the landing page or let the connector print it):

```html
<script async src="https://nivcreative.com/panel/assets/js/tracker.js" data-site="ws_xxxxxxxxxxxxxxxxxxxxx"></script>
```

The tracker (≈2 KB, async, no render blocking) sends one beacon per visit window, stores UTM/referrer in the `nc_attr` cookie for 30
days (UTM in the URL always wins; otherwise first touch is kept), honours Do-Not-Track and never throws into the host page.

## 3. REST connection test

*Websites → Test connection* requests `{site}/wp-json/` from the panel server. Requests go through `UrlGuard` (SSRF protection:
http/https only, ports 80/443, private/reserved IP ranges blocked). Optional WordPress REST credentials (application password) are
stored with libsodium secretbox encryption and are never returned by any endpoint; they are reserved for future page sync.
