-- ──────────────────────────────────────────────────────────────────────────────
-- 033_create_complaint_images_table.sql
--
-- Persists optional patient complaint/symptom photos as MEDIUMBLOB, matching
-- profile_photos (mime + photo_blob). consultation_requests.complaint_image_path
-- remains until the disk backfill is confirmed.
-- Does not drop complaint_image_path or existing request rows.
-- ──────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS complaint_images (
    request_id BIGINT PRIMARY KEY,
    mime VARCHAR(32) NOT NULL,
    photo_blob MEDIUMBLOB NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_complaint_images_request
        FOREIGN KEY (request_id) REFERENCES consultation_requests(id)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Persists complaint images across ephemeral deploys';
