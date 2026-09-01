# Changelog

All notable changes to the **Ethraa Platform** project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

---

## [1.0.0] - 2026-09-01 - Final Graduation Release

### Added
- **Peer-to-Peer Service Exchange System:** Categorized main services and sub-skills directory with instant search and provider availability inspection.
- **Points-Based Escrow Wallet:** Conditional atomic points deduction, escrow holding during active requests, atomic refund on rejection/cancellation, and completion rewards.
- **Interactive Booking Engine:** Provider schedule verification, daily quota enforcement, and active request concurrency locks.
- **Private Messaging System:** AJAX polling-based real-time chat with typing indicators, GD-sanitized image sharing, and one-click report triggers.
- **Automated Content Moderation:** Rule-based regex moderation engine for offensive language, phone numbers, and external contact attempts with automated penalty escalation.
- **Reviews & Ratings Engine:** 5-star rating system with qualitative feedback, restricted to completed requests with database-enforced single-review constraints.
- **Notification Center:** Real-time in-app alerts for request milestones, chat messages, availability subscriptions, and administrative announcements.
- **Administrative Portal (RBAC):** Centralized analytics dashboard, user moderation, service/domain manager, appeals adjudication, audit logging, and maintenance mode toggle.

### Security
- **100% Prepared Statements:** All database queries utilize MySQLi parameterized bindings (`$stmt->bind_param()`), mitigating SQL Injection.
- **Comprehensive CSRF Defense:** Cryptographic token verification via `hash_equals()` across all POST and AJAX mutation endpoints.
- **Session Hardening:** `HttpOnly`, `SameSite=Lax`, conditional `Secure` cookie parameters, `session_regenerate_id(true)` upon authentication, and `session_version` forced invalidation.
- **Brute-Force Lockout:** Automated 15-minute account lock after 5 consecutive failed login attempts.
- **Strict Anti-IDOR Authorization:** Server-side ownership verification for chats, requests, reviews, notifications, and balance operations.
- **File Upload Protection:** MIME inspection, extension whitelist (`.jpg`, `.jpeg`, `.png`, `.webp`), GD re-rendering to strip EXIF payloads, random hex naming, and `.htaccess` execution denial.
- **HTTP Security Headers:** Implemented `Content-Security-Policy`, `X-Frame-Options: SAMEORIGIN`, `X-Content-Type-Options: nosniff`, and `Referrer-Policy: strict-origin-when-cross-origin`.

### Documentation
- Full bilingual English and Arabic README documentation (`README.md` and `README.ar.md`).
- Step-by-step local deployment guide with example SMTP configuration.
- Detailed architecture and sequence diagrams.