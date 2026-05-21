# 🤖 AthleteHub Agent Guidelines (AGENTS.md)

Welcome! This document outlines the development rules, architecture, and coding standards for all AI agents working on the **AthleteHub** platform.

## 🏗️ Project Architecture
AthleteHub is a sports networking platform consisting of two main components:
1. **AthleteHub V1.3**: The main user-facing application (Athletes, Coaches, Recruiters, Clubs).
2. **AthleteHub-Admin**: The administrative portal for content moderation, verification reviews, and user management.

Both components share the same database (`athletehub`) but run as independent web applications.

---

## 🚫 Critical Development Rules
To maintain code safety, consistency, and performance, adhere to the following rules:

1. **Database Queries**:
   - **MUST** use PDO prepared statements with parameter binding for all database operations containing user inputs.
   - **NEVER** concatenate user inputs directly into SQL queries.
   - Ensure transaction management for multi-step updates (e.g., follow counters).

2. **Security & Sanitization**:
   - **XSS Prevention**: Escape all user-generated content before rendering. Use `htmlspecialchars()` in PHP and safe DOM APIs or `escapeHtml()` in JS. Avoid raw `innerHTML` writes of unescaped variables.
   - **CSRF Protection**: All state-modifying requests (POST, PUT, DELETE) must validate a CSRF token.
   - **Upload Safety**: Validate file sizes, whitelisted extensions, and MIME types (using `finfo_file`). Do not store files in web-accessible directories without `.htaccess` protection. Filenames must be randomized using cryptographically secure methods (e.g., `random_bytes`).

3. **Routing & Frameworks**:
   - AthleteHub is built using Vanilla PHP and JavaScript. Avoid introducing third-party framework dependencies unless explicitly requested.

4. **Code Quality**:
   - Preserve existing comments and docstrings.
   - Reuse existing utility functions (like `escapeHtml()` in JS or centralized database connections in `config/db.php`).

---

## 📂 Key Directories
- `AthleteHub V1.3/pages/` - Core PHP templates for user modules (feed, profiles, messages).
- `AthleteHub V1.3/api/` - Backend API endpoints.
- `AthleteHub V1.3/assets/` - Custom CSS stylesheets and client-side JavaScript.
- `AthleteHub V1.3/config/` - Database connectivity settings.
- `AthleteHub V1.3/includes/` - Header navigation, footers, and authorization guards.

Please verify code functionality against the local environment before concluding tasks.
