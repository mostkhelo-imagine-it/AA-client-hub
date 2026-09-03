-- Client Hub — database schema
-- Target: MySQL 5.7+ / MariaDB 10.3+ (cPanel default)
-- Charset: utf8mb4 throughout for full Unicode (Arabic names, emoji-safe notes, etc.)

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------
-- users — staff accounts (AA, Staff)
--
-- Just two roles. Staff have the same access to client data as AA
-- (add/edit/delete clients, contracts, sessions, courses, import,
-- activity log) — the only thing reserved for AA is managing staff
-- accounts themselves (creating, disabling, or removing one).
-- See Access.php for exactly where each of these is enforced.
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  name          VARCHAR(150) NOT NULL,
  email         VARCHAR(190) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role          ENUM('aa','staff') NOT NULL,
  status        ENUM('active','disabled') NOT NULL DEFAULT 'active',
  must_reset_password TINYINT(1) NOT NULL DEFAULT 1,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- clients — contact records, tiered
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS clients (
  id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  full_name           VARCHAR(190) NOT NULL,
  email               VARCHAR(190) NULL,
  phone               VARCHAR(40)  NULL,
  address             VARCHAR(255) NULL,
  source              VARCHAR(120) NULL,
  tier                ENUM('basic','premium','reality_creator') NOT NULL DEFAULT 'basic',
  subscription_status ENUM('active','paused','cancelled') NULL,
  notes               TEXT NULL,
  created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_clients_tier (tier),
  -- MySQL's UNIQUE index treats every NULL as distinct, so clients with
  -- no email at all are unaffected — only an actual duplicate address
  -- is rejected. The app checks this too (with a friendlier message),
  -- but the index is what makes it true even for a path that skips the
  -- app, or a race between two people saving at once.
  UNIQUE KEY uq_clients_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- contracts — Reality Creator clients only
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS contracts (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id     INT UNSIGNED NOT NULL,
  start_date    DATE NOT NULL,
  end_date      DATE NOT NULL,
  status        ENUM('active','pending_decision','renewed','ended') NOT NULL DEFAULT 'active',
  renewed_from  INT UNSIGNED NULL,
  created_by    INT UNSIGNED NOT NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_contracts_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_contracts_renewed_from FOREIGN KEY (renewed_from) REFERENCES contracts(id) ON DELETE SET NULL,
  CONSTRAINT fk_contracts_created_by FOREIGN KEY (created_by) REFERENCES users(id),
  KEY idx_contracts_client (client_id),
  KEY idx_contracts_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- courses — catalog
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS courses (
  id        INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title     VARCHAR(190) NOT NULL,
  type      ENUM('live','online') NOT NULL DEFAULT 'online',
  price     DECIMAL(10,2) NULL,
  platform  VARCHAR(120) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- course_records — attendance + purchases per client
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS course_records (
  id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id     INT UNSIGNED NOT NULL,
  course_id     INT UNSIGNED NOT NULL,
  type          ENUM('attended','purchased') NOT NULL,
  record_date   DATE NOT NULL,
  amount_paid   DECIMAL(10,2) NULL,
  completion    VARCHAR(60) NULL,
  source        ENUM('manual','fluentcommunity') NOT NULL DEFAULT 'manual',
  created_by    INT UNSIGNED NULL,
  created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_cr_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_cr_course FOREIGN KEY (course_id) REFERENCES courses(id),
  CONSTRAINT fk_cr_created_by FOREIGN KEY (created_by) REFERENCES users(id),
  KEY idx_cr_client (client_id),
  KEY idx_cr_course (course_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- session_logs — 1-on-1 progress, Reality Creator clients only
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS session_logs (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_id        INT UNSIGNED NOT NULL,
  session_date     DATE NOT NULL,
  logged_by        INT UNSIGNED NOT NULL,
  summary          TEXT NOT NULL,
  goals_next       TEXT NULL,
  progress_rating  TINYINT UNSIGNED NULL, -- 1..5
  created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_sl_client FOREIGN KEY (client_id) REFERENCES clients(id) ON DELETE CASCADE,
  CONSTRAINT fk_sl_logged_by FOREIGN KEY (logged_by) REFERENCES users(id),
  KEY idx_sl_client (client_id),
  CONSTRAINT chk_sl_rating CHECK (progress_rating IS NULL OR (progress_rating BETWEEN 1 AND 5))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ---------------------------------------------------------------
-- activity_log — accountability trail
-- ---------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
  id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  user_id     INT UNSIGNED NULL,
  action      VARCHAR(120) NOT NULL,      -- e.g. 'client.view', 'session_log.delete'
  entity_type VARCHAR(60)  NULL,          -- e.g. 'client', 'session_log'
  entity_id   INT UNSIGNED NULL,
  details     TEXT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
  KEY idx_activity_user (user_id),
  KEY idx_activity_entity (entity_type, entity_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------------------------------------------------------------
-- Seed: first AA account.
-- Password below is a PLACEHOLDER hash for "changeme123" — the app forces
-- a reset on first login (must_reset_password = 1), so this is safe to
-- ship, but change the email before running this in production.
-- ---------------------------------------------------------------
INSERT INTO users (name, email, password_hash, role, status, must_reset_password)
VALUES (
  'AA',
  'aa@example.com',
  '$2b$10$FoIGC.u/GYybkaH/QojfpehynVaFbcAd7GV/EH7mURQV3iXht0jRC', -- changeme123
  'aa',
  'active',
  1
);
