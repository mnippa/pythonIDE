-- Add first_name and last_name to users
-- Created: 2026-02-08

ALTER TABLE users
  ADD COLUMN first_name VARCHAR(80) NOT NULL DEFAULT '' AFTER email,
  ADD COLUMN last_name VARCHAR(80) NOT NULL DEFAULT '' AFTER first_name;
