-- ──────────────────────────────────────────────────────────────────────────────
-- 017_drop_consultation_ai_reviews_table.sql
--
-- Removes the AI-assisted consultation review table. That table existed only
-- for the withdrawn OpenRouter/Nemotron review feature.
--
-- Safe to run on databases that never created the table (DROP IF EXISTS).
-- Does not modify consultation_requests, appointments, users, patients,
-- doctors, doctor_availability, consultation records, prescriptions,
-- Daily consultation tables, or audit logs.
-- ──────────────────────────────────────────────────────────────────────────────

DROP TABLE IF EXISTS consultation_ai_reviews;
