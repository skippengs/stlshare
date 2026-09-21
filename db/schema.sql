-- STL Sharer schema (phase 1-2)
-- Apply via phpMyAdmin / Plesk DB tool, or `mysql < schema.sql` locally.

CREATE TABLE IF NOT EXISTS users (
    id                  INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email               VARCHAR(255) NOT NULL UNIQUE,
    password_hash       VARCHAR(255) NOT NULL,
    display_name        VARCHAR(100) NOT NULL,
    role                ENUM('admin','member') NOT NULL DEFAULT 'member',
    status              ENUM('pending','approved','rejected','suspended') NOT NULL DEFAULT 'pending',
    approved_by         INT UNSIGNED NULL,
    approved_at         DATETIME NULL,
    storage_quota_bytes BIGINT UNSIGNED NULL,
    consented_at        DATETIME NOT NULL,
    created_at          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_approved_by FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS models (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id     INT UNSIGNED NOT NULL,
    title             VARCHAR(255) NOT NULL,
    description       TEXT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename   VARCHAR(255) NOT NULL UNIQUE,
    file_size_bytes   BIGINT UNSIGNED NOT NULL,
    thumbnail_filename VARCHAR(255) NULL,
    created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_models_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_models_title (title),
    INDEX idx_models_owner (owner_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS favorites (
    user_id    INT UNSIGNED NOT NULL,
    model_id   INT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, model_id),
    CONSTRAINT fk_fav_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_fav_model FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS collections (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    owner_user_id INT UNSIGNED NOT NULL,
    name          VARCHAR(150) NOT NULL,
    description   TEXT NULL,
    is_public     TINYINT(1) NOT NULL DEFAULT 0,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_coll_owner FOREIGN KEY (owner_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS collection_models (
    collection_id INT UNSIGNED NOT NULL,
    model_id      INT UNSIGNED NOT NULL,
    sort_order    INT NOT NULL DEFAULT 0,
    PRIMARY KEY (collection_id, model_id),
    CONSTRAINT fk_cm_collection FOREIGN KEY (collection_id) REFERENCES collections(id) ON DELETE CASCADE,
    CONSTRAINT fk_cm_model FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Forward-looking for phase 3 (not used by the app yet): each edit produces a
-- new version rather than mutating the original uploaded file.
CREATE TABLE IF NOT EXISTS model_versions (
    id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    model_id         INT UNSIGNED NOT NULL,
    parent_version_id INT UNSIGNED NULL,
    stored_filename  VARCHAR(255) NOT NULL,
    created_by_user_id INT UNSIGNED NOT NULL,
    edit_description TEXT NULL,
    created_at       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_mv_model FOREIGN KEY (model_id) REFERENCES models(id) ON DELETE CASCADE,
    CONSTRAINT fk_mv_parent FOREIGN KEY (parent_version_id) REFERENCES model_versions(id) ON DELETE SET NULL,
    CONSTRAINT fk_mv_user FOREIGN KEY (created_by_user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS settings (
    setting_key   VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tracks which db/migrations/*.sql files have been applied, via upgrade.php.
-- A fresh install.php run inserts every migration file that exists at
-- install time (their changes are already folded into this schema.sql), so
-- upgrade.php only ever needs to apply migrations added *after* install.
CREATE TABLE IF NOT EXISTS schema_migrations (
    migration   VARCHAR(255) PRIMARY KEY,
    applied_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default per-user quota: 2 GiB (see CLAUDE.md open item — picked 2GB as the
-- starting default; admin can override per-user via users.storage_quota_bytes).
INSERT INTO settings (setting_key, setting_value) VALUES ('default_quota_bytes', '2147483648')
    ON DUPLICATE KEY UPDATE setting_value = setting_value;
