-- =============================================
-- Auth Migration
-- Adds role-based access control + login tracking
-- to the existing `users` table, and seeds a
-- default administrator account.
-- =============================================

USE sales_system;

ALTER TABLE users
    ADD COLUMN role        ENUM('admin','user') NOT NULL DEFAULT 'user' AFTER full_name,
    ADD COLUMN is_active   TINYINT(1) NOT NULL DEFAULT 1 AFTER role,
    ADD COLUMN last_login  TIMESTAMP NULL DEFAULT NULL AFTER is_active;

-- ---------------------------------------------
-- Seed default administrator
--   username: admin
--   password: Admin@123   <-- CHANGE THIS IMMEDIATELY AFTER FIRST LOGIN
-- The hash below is a real bcrypt hash for 'Admin@123',
-- compatible with PHP's password_verify().
-- ---------------------------------------------
INSERT INTO users (username, email, password_hash, full_name, role, is_active)
VALUES (
    'admin',
    'admin@example.com',
    '$2b$10$6PuKTgO2uzE0BpSI7IHekOBgOynUpayKCg55Q7BVTviomvtB/95P6',
    'System Administrator',
    'admin',
    1
);

-- ---------------------------------------------
-- Seed demo regular user (for testing role-based access)
--   username: jdoe
--   password: User@123   <-- CHANGE THIS IMMEDIATELY AFTER FIRST LOGIN
-- ---------------------------------------------
INSERT INTO users (username, email, password_hash, full_name, role, is_active)
VALUES (
    'jdoe',
    'jdoe@example.com',
    '$2b$10$7rJFwhzF0ZJsBESrYNaHQOvI.YJDCQhfHvfaG2SARO2pkOizXCAiS',
    'Jane Doe',
    'user',
    1
);
