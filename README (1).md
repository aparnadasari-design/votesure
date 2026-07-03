# 🗳️ VoteSure — Secure Online Voting System

A full-stack digital voting platform built as my BCA final year major project. VoteSure lets registered voters authenticate with OTP verification and cast a single, tamper-checked vote in an active election, while admins manage elections, candidates, and monitor results from a dedicated dashboard.

## 🔑 Features

- **Voter registration & identity verification** — Aadhaar (12-digit) and Voter ID based registration with duplicate checks across Aadhaar, Voter ID, email, and mobile number
- **OTP-based login flow** — 6-digit OTP generated and verified before granting a voting session (demo mode displays OTP on screen; production-ready path verifies against a database-backed OTP store with expiry)
- **One-vote-per-voter enforcement** — enforced at the database level with a unique constraint on `votes.voter_id`, plus a DB transaction and a server-side re-check of `has_voted` on every vote submission to prevent race conditions
- **Brute-force protection** — failed login attempts are logged per IP address in an audit trail, and rate-limited within a rolling time window
- **Admin dashboard** — create/manage elections and candidates, monitor voter registrations, and view live results
- **Live results page** — real-time vote tally per candidate, grouped by election, using SQL aggregation (`COUNT` + `LEFT JOIN`)
- **Audit logging** — every login attempt and vote action is logged with IP address, result, and timestamp for traceability

## 🛠️ Tech Stack

| Layer | Technology |
|---|---|
| Frontend | HTML, CSS, Bootstrap 5, JavaScript |
| Backend | PHP (PDO for DB access) |
| Database | MySQL |
| Server | Apache (via XAMPP) |
| Security | bcrypt password hashing, prepared statements (SQL injection protection), session-based auth |

## 🗄️ Database Design

Six related tables: `voters`, `elections`, `candidates`, `votes`, `otp_store`, `audit_logs`, and `admins` — with foreign key constraints linking votes to voters/candidates/elections, and indexes on frequently queried columns (Aadhaar, Voter ID, mobile, email) for fast lookups during registration and login.

## 📸 Screenshots

**Home page**
![Home page](screenshots/01_home.png)

**Voter login / registration**
![Register and login](screenshots/02_register_login.png)

**Live results dashboard**
![Live results](screenshots/03_results.png)

**Admin login**
![Admin login](screenshots/04_admin_login.png)

**Admin dashboard**
![Admin dashboard](screenshots/05_admin_dashboard.png)

## ⚙️ Setup Instructions

1. **Clone the repo**
   ```bash
   git clone https://github.com/<your-username>/votesure.git
   ```
2. **Import the database**
   - Open phpMyAdmin → SQL tab
   - Run `schema.sql` — this creates the `new_voting` database with all tables and seed data
3. **Place files in XAMPP**
   ```
   C:\xampp\htdocs\votesure\
   ```
4. **Configure the DB connection** (only if your MySQL credentials differ from XAMPP defaults) in `db.php`:
   ```php
   $dbuser = "root";
   $dbpass = "";
   ```
5. **Start Apache & MySQL** from the XAMPP Control Panel
6. **Access the app**

   | Page | URL |
   |---|---|
   | Home | `http://localhost/votesure/index.php` |
   | Register / Login | `http://localhost/votesure/register.php` |
   | Cast Vote | `http://localhost/votesure/vote.php` |
   | Live Results | `http://localhost/votesure/results.php` |
   | Admin Login | `http://localhost/votesure/admin_login.php` |

**Demo admin login:** username `admin`, password `Admin@123` *(seeded for local testing only — change before any real deployment)*

## 🚧 What I'd Improve With More Time

- Move OTP delivery to an actual SMS/email gateway instead of on-screen demo mode
- Add HTTPS enforcement and CSRF tokens on all forms
- Move DB credentials to environment variables instead of a committed config file
- Add unit tests for the vote-casting transaction logic

## 📄 License

This project was built for academic purposes as part of my BCA curriculum.
