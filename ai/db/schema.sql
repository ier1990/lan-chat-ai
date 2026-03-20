-- AI Chat Schema
-- Run via install.php or manually in phpMyAdmin.

CREATE TABLE IF NOT EXISTS users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(60)  NOT NULL UNIQUE,
    display_name  VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    is_active     TINYINT(1)   NOT NULL DEFAULT 1,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS roles (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_key    VARCHAR(60)  NOT NULL UNIQUE,
    name        VARCHAR(100) NOT NULL,
    description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS user_roles (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    role_id    INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_role (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category      VARCHAR(60)  NOT NULL,
    section       VARCHAR(60)  NOT NULL DEFAULT '',
    setting_key   VARCHAR(120) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type  VARCHAR(30)  NOT NULL DEFAULT 'string',
    description   TEXT,
    is_public     TINYINT(1)   NOT NULL DEFAULT 0,
    is_editable   TINYINT(1)   NOT NULL DEFAULT 1,
    is_sensitive  TINYINT(1)   NOT NULL DEFAULT 0,
    updated_by    INT UNSIGNED,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings_meta (
    id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    setting_key     VARCHAR(120) NOT NULL UNIQUE,
    label           VARCHAR(150) NOT NULL,
    input_type      VARCHAR(30)  NOT NULL DEFAULT 'text',
    options_json    TEXT,
    validation_json TEXT,
    help_text       TEXT,
    sort_order      INT          NOT NULL DEFAULT 0,
    tab_name        VARCHAR(60)  NOT NULL DEFAULT 'general'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rooms (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_key      VARCHAR(120) NOT NULL UNIQUE,
    room_type     ENUM('channel','dm','group','log') NOT NULL DEFAULT 'channel',
    name          VARCHAR(100) NOT NULL,
    slug          VARCHAR(100) NOT NULL UNIQUE,
    is_private    TINYINT(1)   NOT NULL DEFAULT 0,
    created_by    INT UNSIGNED,
    created_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    settings_json TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS room_participants (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id          INT UNSIGNED NOT NULL,
    participant_type ENUM('user','persona','webhook') NOT NULL,
    participant_id   INT UNSIGNED NOT NULL,
    can_post         TINYINT(1)   NOT NULL DEFAULT 1,
    can_invite       TINYINT(1)   NOT NULL DEFAULT 0,
    joined_at        DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_participant (room_id, participant_type, participant_id),
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS messages (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    room_id      INT UNSIGNED NOT NULL,
    sender_type  ENUM('user','persona','webhook','system') NOT NULL,
    sender_id    INT UNSIGNED NOT NULL DEFAULT 0,
    message_text TEXT NOT NULL,
    message_type ENUM('text','notice','log','ai_reply') NOT NULL DEFAULT 'text',
    reply_to_id  INT UNSIGNED,
    status       VARCHAR(20)  NOT NULL DEFAULT 'sent',
    meta_json    TEXT,
    created_at   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_room_created (room_id, created_at),
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS personas (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    persona_key   VARCHAR(60)  NOT NULL UNIQUE,
    name          VARCHAR(100) NOT NULL,
    system_prompt TEXT,
    style_notes   TEXT,
    is_enabled    TINYINT(1)   NOT NULL DEFAULT 1,
    is_default    TINYINT(1)   NOT NULL DEFAULT 0,
    settings_json TEXT,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_user_configs (
    user_id       INT UNSIGNED PRIMARY KEY,
    provider_key  VARCHAR(60)  NOT NULL DEFAULT 'openai_compat',
    base_url      VARCHAR(255) NOT NULL,
    api_key       TEXT,
    model_default VARCHAR(120) NOT NULL,
    persona_id    INT UNSIGNED,
    headers_json  TEXT,
    is_enabled    TINYINT(1)   NOT NULL DEFAULT 1,
    settings_json TEXT,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (persona_id) REFERENCES personas(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_providers (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_key  VARCHAR(60)  NOT NULL UNIQUE,
    name          VARCHAR(100) NOT NULL,
    driver        VARCHAR(60)  NOT NULL DEFAULT 'openai_compat',
    base_url      VARCHAR(255) NOT NULL,
    api_key       TEXT,
    model_default VARCHAR(120),
    is_enabled    TINYINT(1)   NOT NULL DEFAULT 1,
    priority      INT          NOT NULL DEFAULT 0,
    settings_json TEXT,
    updated_at    DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS ai_models (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id         INT UNSIGNED    NOT NULL,
    model_key           VARCHAR(120)    NOT NULL,
    label               VARCHAR(150)    NOT NULL,
    context_window      INT UNSIGNED,
    max_tokens          INT UNSIGNED,
    temperature_default DECIMAL(3,2)    DEFAULT 0.70,
    is_enabled          TINYINT(1)      NOT NULL DEFAULT 1,
    supports_tools      TINYINT(1)      NOT NULL DEFAULT 0,
    supports_images     TINYINT(1)      NOT NULL DEFAULT 0,
    supports_reasoning  TINYINT(1)      NOT NULL DEFAULT 0,
    settings_json       TEXT,
    UNIQUE KEY uq_provider_model (provider_id, model_key),
    FOREIGN KEY (provider_id) REFERENCES ai_providers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS webhook_sources (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name           VARCHAR(100) NOT NULL,
    webhook_key    VARCHAR(64)  NOT NULL UNIQUE,
    target_room_id INT UNSIGNED NOT NULL,
    is_enabled     TINYINT(1)   NOT NULL DEFAULT 1,
    source_type    VARCHAR(60)  NOT NULL DEFAULT 'generic',
    created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (target_room_id) REFERENCES rooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
