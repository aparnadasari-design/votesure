-- ════════════════════════════════════════════════
-- VoteSure — MySQL Database Schema  (FIXED)
-- Run this ONCE in phpMyAdmin to set up the DB
-- ════════════════════════════════════════════════
-- FIX 1: The original INSERT for admin had a wrong
--         plaintext password and was immediately
--         DELETED in the same script. Now fixed.
-- FIX 2: Admin password is Admin@123 (bcrypt hash)
-- FIX 3: OTP INSERT had wrong param order (fixed)
-- ════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS new_voting
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE new_voting;

-- ── VOTERS TABLE ──────────────────────────────
CREATE TABLE IF NOT EXISTS voters (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    voter_ref    VARCHAR(20)  UNIQUE NOT NULL,
    first_name   VARCHAR(80)  NOT NULL,
    last_name    VARCHAR(80)  NOT NULL,
    aadhaar      VARCHAR(12)  UNIQUE NOT NULL,
    voter_id     VARCHAR(30)  UNIQUE NOT NULL,
    dob          DATE         NOT NULL,
    gender       ENUM('Male','Female','Other') NOT NULL,
    mobile       VARCHAR(10)  UNIQUE NOT NULL,
    email        VARCHAR(150) UNIQUE NOT NULL,
    password     VARCHAR(255) NOT NULL,
    address      TEXT         NOT NULL,
    city         VARCHAR(80)  NOT NULL,
    pin          VARCHAR(6)   NOT NULL,
    status       ENUM('pending','verified','blocked') DEFAULT 'pending',
    has_voted    TINYINT(1)   DEFAULT 0,
    constituency VARCHAR(100),
    created_at   DATETIME     DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_aadhaar  (aadhaar),
    INDEX idx_voter_id (voter_id),
    INDEX idx_mobile   (mobile),
    INDEX idx_email    (email)
);

-- ── ELECTIONS TABLE ───────────────────────────
CREATE TABLE IF NOT EXISTS elections (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(200) NOT NULL,
    description TEXT,
    start_time  DATETIME     NOT NULL,
    end_time    DATETIME     NOT NULL,
    is_active   TINYINT(1)   DEFAULT 1,
    created_at  DATETIME     DEFAULT CURRENT_TIMESTAMP
);

-- ── CANDIDATES TABLE ──────────────────────────
CREATE TABLE IF NOT EXISTS candidates (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    election_id  INT          NOT NULL,
    name         VARCHAR(150) NOT NULL,
    party        VARCHAR(150),
    symbol       VARCHAR(10),
    constituency VARCHAR(100),
    is_active    TINYINT(1)   DEFAULT 1,
    FOREIGN KEY (election_id) REFERENCES elections(id) ON DELETE CASCADE
);

-- ── VOTES TABLE ───────────────────────────────
CREATE TABLE IF NOT EXISTS votes (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    voter_id     INT      NOT NULL UNIQUE,
    candidate_id INT      NOT NULL,
    election_id  INT      NOT NULL,
    ip_address   VARCHAR(45),
    voted_at     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (voter_id)     REFERENCES voters(id),
    FOREIGN KEY (candidate_id) REFERENCES candidates(id),
    FOREIGN KEY (election_id)  REFERENCES elections(id)
);

-- ── OTP TABLE ─────────────────────────────────
CREATE TABLE IF NOT EXISTS otp_store (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    mobile     VARCHAR(10) NOT NULL,
    otp        VARCHAR(6)  NOT NULL,
    type       ENUM('register','login') DEFAULT 'login',
    expires_at DATETIME    NOT NULL,
    used       TINYINT(1)  DEFAULT 0,
    created_at DATETIME    DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_mobile_otp (mobile, otp)
);

-- ── AUDIT LOGS ────────────────────────────────
CREATE TABLE IF NOT EXISTS audit_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    voter_id   INT,
    action     VARCHAR(30)       NOT NULL,
    ip_address VARCHAR(45),
    result     ENUM('ok','fail') DEFAULT 'ok',
    details    TEXT,
    created_at DATETIME          DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_action (ip_address, action, created_at)
);

-- ── ADMINS TABLE ──────────────────────────────
CREATE TABLE IF NOT EXISTS admins (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  UNIQUE NOT NULL,
    password   VARCHAR(255) NOT NULL,
    email      VARCHAR(150),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ── SEED: Sample Election ─────────────────────
-- FIX: Changed dates to be actually ACTIVE right now
INSERT INTO elections (title, description, start_time, end_time, is_active) VALUES
('2026 Municipal Election',
 'Ward 14 – Shivaji Nagar Municipal Corporation Election',
 NOW(),
 DATE_ADD(NOW(), INTERVAL 7 DAY),
 1);

-- ── SEED: Sample Candidates ───────────────────
INSERT INTO candidates (election_id, name, party, symbol, constituency) VALUES
(1, 'Rajiv Sharma',  'National Progress Party',  '🌟', 'Ward 14'),
(1, 'Priya Nair',    'United Democratic Front',   '🌿', 'Ward 14'),
(1, 'Arjun Mehta',   'People''s Rights Alliance', '⚖️',  'Ward 14');

-- ── ADMIN ACCOUNT ─────────────────────────────
-- Username : admin
-- Password : Admin@123
-- Hash generated and verified with PHP password_hash() cost=10
INSERT INTO admins (username, password, email) VALUES
('admin',
 '$2y$10$PYk5aEp8H1A8KaPe5BBLHu2Tm9aSK9IE3nmbYvTcXp0HZUvS5SjP2',
 'admin@votesure.com');
