---
name: athletehub-audit
description: Audits the AthleteHub V1.3 and Admin portals for security vulnerabilities, query performance, and logical flaws.
---
# AthleteHub Security & Performance Audit

This skill allows agents to conduct complete audits on the AthleteHub codebase, identifying common vulnerabilities and performance bottlenecks.

## 🔍 Audit Checklist

### 1. Security Auditing (OWASP Top 10)
- **SQL Injection (SQLi)**: Scan for queries in `pages/` and `api/` that do not use prepared statements or dynamically interpolate variables inside `prepare()` calls.
- **Cross-Site Scripting (XSS)**:
  - Check JS files for `innerHTML`, `outerHTML`, or `document.write` referencing API/user inputs.
  - Verify that `escapeHtml()` is consistently applied.
  - In PHP, check for `<?=` outputs without `htmlspecialchars()`.
- **CSRF Vulnerabilities**: Ensure all destructive endpoints (POST/PUT/DELETE) enforce token validation.
- **File Upload Vulnerabilities**:
  - Check image/document uploads in `api/profile.php` and `api/role_application.php`.
  - Check file size limits, MIME validations, and secure filename generation.

### 2. Logic & Schema Integrity
- **Database Indexes**: Check if foreign keys (e.g., `sender_id`, `receiver_id` in `messages`) have appropriate indexes in `database/athletehub.sql`.
- **Session Security**: Confirm that `session_regenerate_id(true)` is called upon login.
- **Variable Alignment**: Search for database field changes (e.g., `message` vs `message_text`) to ensure backend code references correct columns.

### 3. Performance & N+1 Queries
- Look for loops executing SQL queries or subqueries inside loops (e.g., user lists, participant registration lists).
- Ensure resource-intensive requests (like polling in `messages.js`) use reasonable intervals.

## 🧪 Verification Guidelines
1. Verify the Apache and MySQL services are active in the XAMPP Control Panel.
2. Access the site locally via `http://localhost/dashboard/AthleteHub V1.3/`.
3. Check `logs/error.log` for any database connection failures or syntax notices.
