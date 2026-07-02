-- ════════════════════════════════════════════════
-- fix_admin.sql
-- Run this in phpMyAdmin → new_voting database
-- if you already imported schema.sql before.
-- ════════════════════════════════════════════════
USE new_voting;

-- Delete any broken admin rows first
DELETE FROM admins WHERE username = 'admin';
DELETE FROM admins WHERE username = 'admin2';

-- Insert admin with VERIFIED correct bcrypt hash
-- Username : admin
-- Password : Admin@123
INSERT INTO admins (username, password, email) VALUES
('admin',
 '$2y$10$PYk5aEp8H1A8KaPe5BBLHu2Tm9aSK9IE3nmbYvTcXp0HZUvS5SjP2',
 'admin@votesure.com');

-- Confirm it was inserted
SELECT id, username, email, created_at FROM admins;
