-- =========================================================================
-- USLS IT Helpdesk: database schema
--
-- Safe to run on a new server AND on the database from the previous version
-- of the project: existing tables are upgraded in place, nothing is dropped.
--
--   phpMyAdmin: Import -> choose this file -> Go
--   CLI:        mysql -u root < database/schema.sql
-- =========================================================================

CREATE DATABASE IF NOT EXISTS secure_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE secure_system;

-- -------------------------------------------------------------------------
-- Users
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id                  INT NOT NULL AUTO_INCREMENT,
    username            VARCHAR(50)  NOT NULL,
    email               VARCHAR(254) NOT NULL,
    password            VARCHAR(255) NOT NULL,            -- Argon2id hash (never plain text)
    role                ENUM('user', 'technician', 'admin') NOT NULL DEFAULT 'user',
    is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    session_version     INT UNSIGNED NOT NULL DEFAULT 1,  -- bump to sign out all sessions
    totp_secret         VARCHAR(255) NULL,                -- AES-256-GCM encrypted
    totp_enabled        TINYINT(1)   NOT NULL DEFAULT 0,
    totp_last_step      BIGINT       NULL,                -- blocks one-time-code replay
    password_changed_at DATETIME     NULL,
    last_login_at       DATETIME     NULL,
    last_login_ip       VARCHAR(45)  NULL,
    created_at          TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade a users table created by the previous version
ALTER TABLE users
    MODIFY password VARCHAR(255) NOT NULL,
    MODIFY role ENUM('user', 'technician', 'admin') NOT NULL DEFAULT 'user',
    ADD COLUMN IF NOT EXISTS is_active           TINYINT(1)   NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS session_version     INT UNSIGNED NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS totp_secret         VARCHAR(255) NULL,
    ADD COLUMN IF NOT EXISTS totp_enabled        TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS totp_last_step      BIGINT       NULL,
    ADD COLUMN IF NOT EXISTS password_changed_at DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS last_login_at       DATETIME     NULL,
    ADD COLUMN IF NOT EXISTS last_login_ip       VARCHAR(45)  NULL;

-- -------------------------------------------------------------------------
-- Tickets
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    id          INT NOT NULL AUTO_INCREMENT,
    user_id     INT NOT NULL,
    category    VARCHAR(50)  NOT NULL,
    priority    ENUM('Low', 'Normal', 'High', 'Critical') NOT NULL DEFAULT 'Normal',
    subject     VARCHAR(150) NOT NULL,
    description TEXT         NOT NULL,
    status      ENUM('Open', 'In Progress', 'Resolved') NOT NULL DEFAULT 'Open',
    assigned_to INT NULL,
    created_at  TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_tickets_user (user_id),
    KEY idx_tickets_status (status, priority),
    CONSTRAINT fk_tickets_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Upgrade a tickets table created by the previous version
ALTER TABLE tickets
    MODIFY status ENUM('Open', 'In Progress', 'Resolved') NOT NULL DEFAULT 'Open',
    ADD COLUMN IF NOT EXISTS priority    ENUM('Low', 'Normal', 'High', 'Critical') NOT NULL DEFAULT 'Normal' AFTER category,
    ADD COLUMN IF NOT EXISTS assigned_to INT NULL,
    ADD COLUMN IF NOT EXISTS updated_at  TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE tickets
    ADD CONSTRAINT fk_tickets_assignee FOREIGN KEY IF NOT EXISTS (assigned_to) REFERENCES users (id) ON DELETE SET NULL;

-- -------------------------------------------------------------------------
-- Authentication attempts (brute-force protection and rate limiting)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS auth_attempts (
    id         BIGINT NOT NULL AUTO_INCREMENT,
    action     VARCHAR(20)  NOT NULL,          -- login, 2fa, reauth, register
    identifier VARCHAR(254) NOT NULL,          -- username the attempt was for
    ip         VARCHAR(45)  NOT NULL,
    success    TINYINT(1)   NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_attempts_identifier (action, identifier, created_at),
    KEY idx_attempts_ip (action, ip, created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Two-factor recovery codes (stored hashed, single use)
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS recovery_codes (
    id        INT NOT NULL AUTO_INCREMENT,
    user_id   INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    used_at   DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_recovery_user (user_id),
    CONSTRAINT fk_recovery_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Audit log (append-only, HMAC hash chain for tamper detection)
-- No foreign key on user_id: the history must survive account deletion.
-- -------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_log (
    id         BIGINT NOT NULL AUTO_INCREMENT,
    user_id    INT NULL,
    username   VARCHAR(254) NULL,
    event      VARCHAR(50)  NOT NULL,
    details    VARCHAR(500) NOT NULL DEFAULT '',
    ip         VARCHAR(45)  NOT NULL,
    user_agent VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME     NOT NULL,
    row_hash   CHAR(64)     NOT NULL,
    PRIMARY KEY (id),
    KEY idx_audit_event (event, created_at),
    KEY idx_audit_user (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------------------
-- Least-privilege database account for the application (recommended).
-- The app only needs to read and write rows; it never needs DROP, ALTER or GRANT,
-- and it can only INSERT into the audit log (it can never edit or delete history).
-- Uncomment, choose a strong password, then set DB_USER / DB_PASS.
-- -------------------------------------------------------------------------
-- CREATE USER IF NOT EXISTS 'helpdesk_app'@'localhost' IDENTIFIED BY 'change-this-strong-password';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON secure_system.users          TO 'helpdesk_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON secure_system.tickets        TO 'helpdesk_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON secure_system.auth_attempts  TO 'helpdesk_app'@'localhost';
-- GRANT SELECT, INSERT, UPDATE, DELETE ON secure_system.recovery_codes TO 'helpdesk_app'@'localhost';
-- GRANT SELECT, INSERT                 ON secure_system.audit_log      TO 'helpdesk_app'@'localhost';
-- GRANT LOCK TABLES                    ON secure_system.*              TO 'helpdesk_app'@'localhost';
-- FLUSH PRIVILEGES;
