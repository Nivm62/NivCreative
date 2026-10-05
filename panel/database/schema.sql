-- NivCreative Panel schema (MySQL 5.7+/MariaDB 10.3+). All DATETIME values are stored in the site timezone.
-- Tenant isolation: every client-owned table carries client_id and is always queried with it.

CREATE TABLE IF NOT EXISTS clients (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  public_id     CHAR(20)        NOT NULL,                -- unique internal client_id (cl_xxxxxxxxxxxxxxxx)
  contact_name  VARCHAR(190)    NOT NULL,
  business_name VARCHAR(190)    NOT NULL,
  email         VARCHAR(190)    NOT NULL,
  phone         VARCHAR(40)     NOT NULL DEFAULT '',
  whatsapp_phone VARCHAR(20)    NOT NULL DEFAULT '',     -- international digits, e.g. 972501234567
  website_url   VARCHAR(500)    NOT NULL DEFAULT '',
  plan          VARCHAR(60)     NOT NULL DEFAULT '',
  status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
  notes         TEXT            NULL,
  created_at    DATETIME        NOT NULL,
  updated_at    DATETIME        NOT NULL,
  deleted_at    DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_clients_public_id (public_id),
  KEY idx_clients_status (status, deleted_at),
  KEY idx_clients_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS users (
  id            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id     BIGINT UNSIGNED NULL,
  role          ENUM('admin','client') NOT NULL,
  email         VARCHAR(190)    NOT NULL,
  password_hash VARCHAR(255)    NOT NULL,
  name          VARCHAR(190)    NOT NULL,
  locale        CHAR(2)         NOT NULL DEFAULT 'he',
  status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
  last_login_at DATETIME        NULL,
  created_at    DATETIME        NOT NULL,
  updated_at    DATETIME        NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_users_email (email),
  KEY idx_users_client (client_id),
  CONSTRAINT fk_users_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS remember_tokens (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  selector    CHAR(18)        NOT NULL,
  token_hash  CHAR(64)        NOT NULL,
  expires_at  DATETIME        NOT NULL,
  created_at  DATETIME        NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_remember_selector (selector),
  KEY idx_remember_user (user_id),
  CONSTRAINT fk_remember_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS password_resets (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  user_id     BIGINT UNSIGNED NOT NULL,
  token_hash  CHAR(64)        NOT NULL,
  expires_at  DATETIME        NOT NULL,
  created_at  DATETIME        NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_reset_token (token_hash),
  KEY idx_reset_user (user_id),
  CONSTRAINT fk_reset_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Subscription / billing history. The current subscription is the row with the latest end_date.
CREATE TABLE IF NOT EXISTS subscriptions (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id      BIGINT UNSIGNED NOT NULL,
  plan           VARCHAR(60)     NOT NULL DEFAULT '',
  amount         DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  currency       CHAR(3)         NOT NULL DEFAULT 'ILS',
  start_date     DATE            NOT NULL,
  end_date       DATE            NOT NULL,
  payment_status ENUM('paid','pending','overdue','free') NOT NULL DEFAULT 'paid',
  note           VARCHAR(255)    NOT NULL DEFAULT '',
  created_at     DATETIME        NOT NULL,
  PRIMARY KEY (id),
  KEY idx_sub_client_end (client_id, end_date),
  KEY idx_sub_start (start_date),
  CONSTRAINT fk_sub_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS websites (
  id                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id         BIGINT UNSIGNED NOT NULL,
  name              VARCHAR(190)    NOT NULL,
  domain            VARCHAR(190)    NOT NULL,
  url               VARCHAR(500)    NOT NULL,
  site_key          CHAR(24)        NOT NULL,            -- public identifier (safe in tracker snippet)
  token_hash        CHAR(64)        NOT NULL,            -- sha256 of the secret API token (shown once)
  token_prefix      VARCHAR(12)     NOT NULL DEFAULT '',
  wp_api_user       VARCHAR(190)    NULL,
  wp_api_secret_enc TEXT            NULL,                -- libsodium-encrypted application password
  connection_status ENUM('connected','disconnected','error','auth_required') NOT NULL DEFAULT 'disconnected',
  connection_message VARCHAR(255)   NOT NULL DEFAULT '',
  last_seen_at      DATETIME        NULL,
  connector_version VARCHAR(20)     NOT NULL DEFAULT '',
  wp_version        VARCHAR(20)     NOT NULL DEFAULT '',
  status            ENUM('active','disabled') NOT NULL DEFAULT 'active',
  created_at        DATETIME        NOT NULL,
  updated_at        DATETIME        NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_websites_site_key (site_key),
  KEY idx_websites_client (client_id),
  KEY idx_websites_domain (domain),
  CONSTRAINT fk_websites_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS landing_pages (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id   BIGINT UNSIGNED NOT NULL,
  website_id  BIGINT UNSIGNED NOT NULL,
  name        VARCHAR(190)    NOT NULL,
  url         VARCHAR(500)    NOT NULL,
  path_key    VARCHAR(190)    NOT NULL DEFAULT '/',      -- normalized path used to match tracking hits
  wp_page_id  BIGINT UNSIGNED NULL,
  status      ENUM('active','paused','archived') NOT NULL DEFAULT 'active',
  created_at  DATETIME        NOT NULL,
  updated_at  DATETIME        NOT NULL,
  PRIMARY KEY (id),
  KEY idx_lp_client (client_id),
  KEY idx_lp_site_path (website_id, path_key),
  CONSTRAINT fk_lp_client FOREIGN KEY (client_id)  REFERENCES clients (id)  ON DELETE CASCADE,
  CONSTRAINT fk_lp_site   FOREIGN KEY (website_id) REFERENCES websites (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS leads (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  client_id        BIGINT UNSIGNED NOT NULL,
  website_id       BIGINT UNSIGNED NULL,
  landing_page_id  BIGINT UNSIGNED NULL,
  external_id      VARCHAR(64)     NULL,                 -- idempotency key from the sending site
  name             VARCHAR(190)    NOT NULL DEFAULT '',
  phone            VARCHAR(40)     NOT NULL DEFAULT '',
  phone_intl       VARCHAR(20)     NOT NULL DEFAULT '',
  email            VARCHAR(190)    NOT NULL DEFAULT '',
  message          TEXT            NULL,
  status           ENUM('new','contacted','in_progress','meeting','proposal','closed','not_relevant') NOT NULL DEFAULT 'new',
  deal_value       DECIMAL(12,2)   NULL,
  source           VARCHAR(30)     NOT NULL DEFAULT 'direct',   -- normalized channel
  utm_source       VARCHAR(190)    NOT NULL DEFAULT '',
  utm_medium       VARCHAR(190)    NOT NULL DEFAULT '',
  utm_campaign     VARCHAR(190)    NOT NULL DEFAULT '',
  utm_content      VARCHAR(190)    NOT NULL DEFAULT '',
  utm_term         VARCHAR(190)    NOT NULL DEFAULT '',
  referrer         VARCHAR(500)    NOT NULL DEFAULT '',
  device           ENUM('desktop','mobile','tablet','unknown') NOT NULL DEFAULT 'unknown',
  created_at       DATETIME        NOT NULL,
  updated_at       DATETIME        NOT NULL,
  last_activity_at DATETIME        NOT NULL,
  first_contact_at DATETIME        NULL,
  closed_at        DATETIME        NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_leads_external (website_id, external_id),
  KEY idx_leads_client_created (client_id, created_at),
  KEY idx_leads_client_status (client_id, status),
  KEY idx_leads_status_created (status, created_at),
  KEY idx_leads_website (website_id),
  KEY idx_leads_landing (landing_page_id),
  KEY idx_leads_source (source),
  KEY idx_leads_utm_source (utm_source),
  KEY idx_leads_client_campaign (client_id, utm_campaign),
  CONSTRAINT fk_leads_client FOREIGN KEY (client_id) REFERENCES clients (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lead_notes (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id    BIGINT UNSIGNED NOT NULL,
  client_id  BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NULL,
  body       TEXT            NOT NULL,
  created_at DATETIME        NOT NULL,
  PRIMARY KEY (id),
  KEY idx_notes_lead (lead_id, created_at),
  KEY idx_notes_client (client_id),
  CONSTRAINT fk_notes_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS lead_activity (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  lead_id    BIGINT UNSIGNED NOT NULL,
  client_id  BIGINT UNSIGNED NOT NULL,
  user_id    BIGINT UNSIGNED NULL,
  type       VARCHAR(40)     NOT NULL,       -- created | status_changed | note_added | deal_value_set | contact_whatsapp | contact_call | contact_email
  meta       TEXT            NULL,           -- JSON
  created_at DATETIME        NOT NULL,
  PRIMARY KEY (id),
  KEY idx_activity_lead (lead_id, created_at),
  KEY idx_activity_client (client_id),
  CONSTRAINT fk_activity_lead FOREIGN KEY (lead_id) REFERENCES leads (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- One row per (landing page, day, anonymous visitor). `views` counts visits (refreshes within 30 minutes are not new visits).
CREATE TABLE IF NOT EXISTS page_views (
  landing_page_id BIGINT UNSIGNED NOT NULL,
  day             DATE            NOT NULL,
  visitor_hash    CHAR(32)        NOT NULL,
  client_id       BIGINT UNSIGNED NOT NULL,
  website_id      BIGINT UNSIGNED NOT NULL,
  views           INT UNSIGNED    NOT NULL DEFAULT 1,
  source          VARCHAR(30)     NOT NULL DEFAULT 'direct',
  utm_source      VARCHAR(190)    NOT NULL DEFAULT '',
  utm_campaign    VARCHAR(190)    NOT NULL DEFAULT '',
  device          ENUM('desktop','mobile','tablet','unknown') NOT NULL DEFAULT 'unknown',
  first_seen      DATETIME        NOT NULL,
  last_seen       DATETIME        NOT NULL,
  PRIMARY KEY (landing_page_id, day, visitor_hash),
  KEY idx_pv_day (day),
  KEY idx_pv_client_day (client_id, day),
  KEY idx_pv_site_day (website_id, day),
  KEY idx_pv_source (source),
  CONSTRAINT fk_pv_lp FOREIGN KEY (landing_page_id) REFERENCES landing_pages (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS notifications (
  id         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  audience   ENUM('admin','client') NOT NULL,
  client_id  BIGINT UNSIGNED NULL,
  type       VARCHAR(40)     NOT NULL,       -- new_lead | lead_waiting | subscription_expiring | subscription_expired | website_disconnected | leads_spike
  severity   ENUM('info','success','warning','danger') NOT NULL DEFAULT 'info',
  params     TEXT            NULL,           -- JSON, interpolated into the translated message
  link       VARCHAR(255)    NOT NULL DEFAULT '',
  dedupe_key VARCHAR(120)    NULL,
  read_at    DATETIME        NULL,
  created_at DATETIME        NOT NULL,
  PRIMARY KEY (id),
  UNIQUE KEY uq_notif_dedupe (dedupe_key),
  KEY idx_notif_aud (audience, client_id, read_at, created_at),
  KEY idx_notif_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
  k          VARCHAR(80)  NOT NULL,
  v          TEXT         NULL,
  PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS api_logs (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  website_id  BIGINT UNSIGNED NULL,
  endpoint    VARCHAR(60)     NOT NULL,
  status      SMALLINT        NOT NULL,
  message     VARCHAR(255)    NOT NULL DEFAULT '',
  ip          VARCHAR(45)     NOT NULL DEFAULT '',
  created_at  DATETIME        NOT NULL,
  PRIMARY KEY (id),
  KEY idx_apilog_site (website_id, created_at),
  KEY idx_apilog_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rate_limits (
  k          CHAR(64)     NOT NULL,
  bucket     INT UNSIGNED NOT NULL,
  hits       INT UNSIGNED NOT NULL DEFAULT 1,
  expires_at DATETIME     NOT NULL,
  PRIMARY KEY (k, bucket),
  KEY idx_rl_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
