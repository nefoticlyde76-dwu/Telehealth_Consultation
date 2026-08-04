ALTER TABLE consultation_requests
    ADD COLUMN specialization VARCHAR(255) NOT NULL AFTER reason,
    ADD COLUMN attachment_path VARCHAR(255) NULL AFTER specialization,
    ADD COLUMN attachment_original_name VARCHAR(255) NULL AFTER attachment_path,
    ADD COLUMN attachment_mime VARCHAR(120) NULL AFTER attachment_original_name,
    ADD COLUMN attachment_size INT UNSIGNED NULL AFTER attachment_mime;

CREATE INDEX idx_consultation_requests_specialization ON consultation_requests (specialization);
