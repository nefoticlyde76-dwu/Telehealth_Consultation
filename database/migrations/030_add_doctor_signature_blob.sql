ALTER TABLE doctor
    ADD COLUMN signature_mime VARCHAR(32) NULL AFTER signature_path,
    ADD COLUMN signature_blob MEDIUMBLOB NULL AFTER signature_mime;
