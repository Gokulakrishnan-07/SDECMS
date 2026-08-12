-- Incremental schema update for existing SECMS installations.
-- Fresh installs receive the same table from schema.sql.
USE secms;

CREATE TABLE IF NOT EXISTS sanction_attachments (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sanction_id       INT UNSIGNED NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    stored_filename   VARCHAR(100) NOT NULL,
    file_path         VARCHAR(500) NOT NULL,
    file_extension    VARCHAR(10)  NOT NULL,
    mime_type         VARCHAR(100) NOT NULL,
    file_size         BIGINT UNSIGNED NOT NULL,
    uploaded_by       INT UNSIGNED NULL,
    created_at        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_sanction_attachment_path UNIQUE (file_path),
    CONSTRAINT fk_sa_sanction FOREIGN KEY (sanction_id) REFERENCES sanctions(id) ON DELETE CASCADE,
    CONSTRAINT fk_sa_uploader FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_sa_sanction (sanction_id),
    INDEX idx_sa_uploaded_by (uploaded_by)
) ENGINE=InnoDB;
