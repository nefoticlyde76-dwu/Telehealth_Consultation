USE telehealth_db;

ALTER TABLE audit_logs
    ADD COLUMN actor_role VARCHAR(50) NULL AFTER actor_name,
    ADD COLUMN event_type VARCHAR(100) NULL AFTER action,
    ADD COLUMN event_label VARCHAR(255) NULL AFTER event_type,
    ADD COLUMN event_category VARCHAR(100) NULL AFTER event_label,
    ADD COLUMN severity VARCHAR(20) NULL AFTER event_category,
    ADD COLUMN entity_type VARCHAR(100) NULL AFTER subject_role,
    ADD COLUMN entity_id BIGINT NULL AFTER entity_type,
    ADD COLUMN outcome VARCHAR(20) NULL AFTER entity_id;

UPDATE audit_logs
SET
    event_type = COALESCE(NULLIF(action, ''), 'legacy_event'),
    event_label = CASE
        WHEN action = 'user_deleted' THEN 'User Account Deleted'
        ELSE 'Legacy Audit Event'
    END,
    event_category = CASE
        WHEN action = 'user_deleted' THEN 'administration'
        ELSE 'system'
    END,
    severity = CASE
        WHEN action = 'user_deleted' THEN 'warning'
        ELSE 'info'
    END,
    outcome = 'success'
WHERE event_type IS NULL;

ALTER TABLE audit_logs
    MODIFY COLUMN event_type VARCHAR(100) NOT NULL,
    MODIFY COLUMN event_label VARCHAR(255) NOT NULL,
    MODIFY COLUMN event_category VARCHAR(100) NOT NULL,
    MODIFY COLUMN severity VARCHAR(20) NOT NULL,
    MODIFY COLUMN outcome VARCHAR(20) NOT NULL;

CREATE INDEX idx_audit_logs_event_type ON audit_logs(event_type);
CREATE INDEX idx_audit_logs_actor_role ON audit_logs(actor_role);
CREATE INDEX idx_audit_logs_entity ON audit_logs(entity_type, entity_id);
CREATE INDEX idx_audit_logs_outcome ON audit_logs(outcome);
