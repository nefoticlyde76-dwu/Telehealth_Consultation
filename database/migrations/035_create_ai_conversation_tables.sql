-- ──────────────────────────────────────────────────────────────────────────────
-- 035_create_ai_conversation_tables.sql
--
-- Persistent MediMate AI chat history for patients.
-- Separate from consultations, clinical records, prescriptions, and notes.
-- Does not alter users, patient clinical columns, or Gemini config.
-- ──────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS ai_conversations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT NOT NULL COMMENT 'Authenticated patient users.id / patient.user_id',
    title VARCHAR(80) NOT NULL DEFAULT '',
    summary TEXT NULL COMMENT 'Reserved for later conversation summarization',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_ai_conversations_patient
        FOREIGN KEY (patient_id) REFERENCES patient(user_id)
        ON DELETE CASCADE,
    INDEX idx_ai_conversations_patient_updated (patient_id, updated_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='Patient MediMate conversations. Not clinical records.';

CREATE TABLE IF NOT EXISTS ai_messages (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    conversation_id BIGINT NOT NULL,
    role ENUM('user', 'assistant') NOT NULL,
    message TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ai_messages_conversation
        FOREIGN KEY (conversation_id) REFERENCES ai_conversations(id)
        ON DELETE CASCADE,
    INDEX idx_ai_messages_conversation_created (conversation_id, created_at)
) ENGINE=InnoDB CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci
  COMMENT='MediMate chat turns. Cascades when a conversation is deleted.';
