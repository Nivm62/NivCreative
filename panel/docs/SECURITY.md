# Security notes

| Area | Implementation |
|---|---|
| Passwords | Argon2id (bcrypt fallback), automatic rehash, min 8 chars with a letter + number |
| Sessions | HttpOnly, SameSite=Lax, Secure on HTTPS, strict mode, ID regenerated on login, 8 h idle timeout, cookie path limited to the panel |
| Remember me | selector + SHA-256-hashed validator, rotated on every use, all tokens revoked on suspected theft / password reset |
| CSRF | synchronizer token on every state-changing request (header or form field) |
| Authorization | route middleware **and** in-controller checks; tenant scoping inside services (see ARCHITECTURE.md); foreign IDs → 404 |
| SQL injection | PDO prepared statements everywhere (emulation off); identifiers whitelisted; sort columns come from fixed maps |
| XSS | PHP views escape with `e()`; the UI builds DOM with `textContent` only; `href`s are scheme-checked; CSP `script-src 'self'`, no inline scripts |
| Brute force | DB-backed rate limiter: 6 failures/15 min per e-mail, 20 per IP; forgot-password throttled and non-enumerating |
| Ingest API | Bearer token (hash stored), per-IP + per-site rate limits, size limits, validation, idempotency keys, audit log |
| Secrets | API tokens shown once; WP credentials encrypted with libsodium or, if that extension is missing, OpenSSL AES-256-GCM (key = SHA-256 of `app_key`); never returned by any endpoint or exposed to JS |
| SSRF | `UrlGuard` for server-side requests (scheme/port allow-list, private + reserved ranges blocked) |
| CSV | formula-injection neutralised (`=`, `+`, `-`, `@` prefixes) |
| Headers | CSP, X-Frame-Options DENY, nosniff, Referrer-Policy, Permissions-Policy, HSTS (HTTPS), `no-store`, `noindex` |
| Files | `src/ config/ storage/ …` denied by `.htaccess` (root rules + per-folder `Require all denied`); `config.php` chmod 640 |
| Privacy | tracker is cookie-less on the server side, stores only a salted hash of a random id, honours DNT |

Known limits: the lead API authenticates with a shared bearer secret (rotate it if exposed); `track` is origin-checked but cannot hold a
secret, so it is a counting endpoint only (it never returns data and ignores unknown pages). No 2FA yet.
