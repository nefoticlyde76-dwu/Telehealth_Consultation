CREATE TABLE IF NOT EXISTS profile_photos (
    user_id BIGINT PRIMARY KEY,
    mime VARCHAR(32) NOT NULL,
    photo_blob MEDIUMBLOB NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_profile_photos_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Persists profile photos across ephemeral deploys';
