---
name: athletehub-audit
description: >
  Full system diagnostic and code audit skill for the AthleteHub LAMP platform
  (PHP 8+/PDO, MariaDB, ES6 vanilla JS, Glassmorphism CSS). Use this skill
  whenever the user asks to: audit the codebase, find bugs, check for errors,
  scan for security issues, review code quality, find dead code, check database
  health, review file dependencies, diagnose why something is broken, or perform
  any kind of system health check on AthleteHub. Also trigger for phrases like
  "what's wrong with my code", "find all issues", "clean up the project",
  "check my project", "something is broken", "why isn't this working",
  "review everything", or "is my code secure". Always use this skill for any
  AthleteHub diagnostic task — never attempt a partial audit without it.
---

# AthleteHub — Full System Audit Skill

## Overview

This skill performs a **complete, structured, multi-phase diagnostic** of the
AthleteHub codebase. It covers security, logic, database, JavaScript, CSS,
file dependencies, performance, and dead code — then produces a prioritised,
actionable audit report followed by auto-fixes.

**Stack reference:**
- Server: PHP 8+ OOP/PDO, XAMPP, `/dashboard/AthleteHub V1.3/`
- DB: MariaDB / MySQL InnoDB, 12 core tables
- Frontend: HTML5, Glassmorphism CSS (`glass.css`, `main.css`), ES6 vanilla JS
- Roles: `athlete`, `coach`, `recruiter`, `club`, `admin`
- Key dirs: `pages/`, `api/`, `assets/`, `includes/`, `config/`

---

## STEP 0 — Codebase Scan (Always First)

Before any analysis, map the full project tree:

```bash
find /path/to/AthleteHub -type f \( -name "*.php" -o -name "*.js" \
  -o -name "*.css" -o -name "*.sql" \) | sort
```

Then read **every** PHP and JS file. Do not skip any file.
Build an internal index:
- All PHP files → list of: session checks, role checks, DB queries, includes
- All JS files → list of: fetch() calls, API URLs referenced, setInterval calls
- All CSS files → list of: class definitions used vs defined
- `config/db.php` → connection credentials pattern

---

## PHASE 1 — SECURITY AUDIT 🔴 CRITICAL

Read reference: `references/security.md` for full checklist details.

Quick checklist per file:

### 1.1 SQL Injection
- [ ] Every query uses PDO prepared statements with `?` or named params
- [ ] Zero raw `$_GET`, `$_POST`, `$_SESSION` inside SQL strings
- [ ] No `mysqli_query()` with string concat anywhere

### 1.2 XSS (Cross-Site Scripting)
- [ ] Every `echo`/`print` of user-sourced data wrapped in `htmlspecialchars()`
- [ ] No raw DB field output in HTML without escaping
- [ ] JS: no `innerHTML =` with unescaped server data (use `textContent`)

### 1.3 Authentication Gaps
- [ ] Every `pages/*.php` file has `session_start()` at top
- [ ] Every `pages/*.php` file redirects if `$_SESSION['user_id']` not set
- [ ] Every `api/*.php` file validates session before processing
- [ ] `user_id` is ALWAYS taken from `$_SESSION` — never from `$_GET`/`$_POST`

### 1.4 Authorization / Role Enforcement
- [ ] Map every role-restricted action → verify server-side role check exists
- [ ] Check: only `club` can create tournaments, stream, host
- [ ] Check: only `recruiter` can post jobs
- [ ] Check: only `admin` can approve role applications
- [ ] No role check that only exists on frontend without a backend duplicate

### 1.5 CSRF Protection
- [ ] State-changing POST endpoints have CSRF token validation
- [ ] If `validate_csrf_token()` is used, verify `session.php` exists and defines it
- [ ] If CSRF is missing, flag each unprotected endpoint

### 1.6 File Upload Security
- [ ] MIME type validated server-side (not just extension)
- [ ] File size limited server-side (not just client)
- [ ] Uploaded filename sanitised — `uniqid()` + extension only
- [ ] Upload directory is NOT executable (`.htaccess` denies PHP execution)
- [ ] PHP file uploads are blocked

### 1.7 Password & Session Security
- [ ] All passwords use `password_hash()` with `PASSWORD_BCRYPT`
- [ ] Login uses `password_verify()` — no MD5/SHA1/plain
- [ ] `session_regenerate_id(true)` called after successful login
- [ ] No credentials or DB names exposed in error messages

### 1.8 Sensitive File Exposure
- [ ] `config/db.php` not directly browser-accessible
- [ ] `display_errors` is OFF in PHP config
- [ ] No stack traces exposed to users in API responses

---

## PHASE 2 — PHP LOGIC & BUG AUDIT 🟠 HIGH

### 2.1 Fatal Logic Errors
- [ ] Every `header('Location:...')` is immediately followed by `exit;`
- [ ] No `die()` that outputs raw PHP errors inside API endpoints
- [ ] No `require_once` pointing to a file that does not exist
- [ ] No circular `require_once` chains

### 2.2 Null & Type Safety
- [ ] All `$_GET`/`$_POST`/`$_SESSION` access uses `?? ''` or `isset()` guard
- [ ] All array key access null-checked before use
- [ ] PDO `fetch()` results checked for `false` before property access
- [ ] Integer casts applied to all ID fields: `(int)$_POST['id']`

### 2.3 Error Handling
- [ ] All PDO queries wrapped in `try/catch`
- [ ] All `api/*.php` files return `Content-Type: application/json` on errors
- [ ] No bare `catch` blocks that silently swallow exceptions
- [ ] File operations (`fopen`, `move_uploaded_file`) have error checks

### 2.4 Duplicate / Dead PHP Code
- [ ] Functions defined more than once across files
- [ ] `require_once` / `include` files that are included but never used
- [ ] PHP variables declared but never read
- [ ] Commented-out code blocks older than one change cycle

### 2.5 Role Logic Consistency
Build a table:

| File | Expected Role | Actual Check Present | Match? |
|------|--------------|---------------------|--------|

Flag every mismatch or missing check.

### 2.6 Session State Consistency
- [ ] `$_SESSION['role']`, `$_SESSION['user_id']`, `$_SESSION['name']`
  are set consistently at login and used consistently everywhere
- [ ] No page assumes a session key that may not be set

---

## PHASE 3 — JAVASCRIPT AUDIT 🟡 MEDIUM

### 3.1 API Call Correctness
- [ ] Every `fetch()` call has `.catch()` or `try/catch`
- [ ] Every `fetch()` response checks `response.ok` before `.json()`
- [ ] `Content-Type: application/json` header included on all POST requests
- [ ] Action params sent correctly (JSON body vs query string)

### 3.2 Hardcoded Paths
- [ ] No hardcoded `/dashboard/AthleteHub V1.3/` in any JS file
- [ ] All API paths built from `BASE_URL` or equivalent variable

### 3.3 DOM Safety
- [ ] `innerHTML` used with unescaped server data → flag each instance
- [ ] `document.getElementById()` calls on elements that may not exist
  → guard with `?.` or null check

### 3.4 Memory Leaks
- [ ] `setInterval()` calls are stored and can be cleared
- [ ] Event listeners added inside loops
- [ ] Polling intervals under 3 seconds (too aggressive)

### 3.5 Console Pollution
- [ ] `console.log()` left in production code → list all occurrences
- [ ] `console.error()` used for user-facing errors (should be toast/UI)

### 3.6 Missing Input Validation
- [ ] Forms with no client-side validation before submit
- [ ] File inputs with no size/type check before upload begins

---

## PHASE 4 — DATABASE AUDIT 🟢

Read reference: `references/database.md` for full schema analysis details.

### 4.1 Missing Indexes
Check these commonly queried columns:
```sql
-- Should all have indexes:
messages.receiver_id, messages.sender_id, messages.is_read
posts.user_id, post_likes.post_id, post_likes.user_id
recruitment.is_active, tournament_registrations.tournament_id
live_streams.status, role_applications.user_id, role_applications.status
```

### 4.2 Schema Integrity
- [ ] All FK columns have matching `ON DELETE` rules defined
- [ ] Composite unique keys exist on all join tables
- [ ] Boolean flags use `TINYINT(1)` not `VARCHAR`
- [ ] Date columns use `DATETIME` not `VARCHAR`
- [ ] Text fields use `TEXT` not `VARCHAR(255)` where content can be long

### 4.3 Orphaned Record Check
```sql
-- Run all of these and report counts:
SELECT COUNT(*) FROM posts p
  LEFT JOIN users u ON p.user_id = u.id WHERE u.id IS NULL;

SELECT COUNT(*) FROM messages m
  LEFT JOIN users u ON m.sender_id = u.id WHERE u.id IS NULL;

SELECT COUNT(*) FROM recruitment_applications ra
  LEFT JOIN recruitment r ON ra.recruitment_id = r.id WHERE r.id IS NULL;

SELECT COUNT(*) FROM tournament_registrations tr
  LEFT JOIN tournaments t ON tr.tournament_id = t.id WHERE t.id IS NULL;

SELECT COUNT(*) FROM live_streams ls
  LEFT JOIN users u ON ls.host_user_id = u.id WHERE u.id IS NULL;

SELECT COUNT(*) FROM role_applications rla
  LEFT JOIN users u ON rla.user_id = u.id WHERE u.id IS NULL;
```

### 4.4 Data Consistency
- [ ] `users.role` ENUM contains all roles in use: `athlete`, `coach`,
  `recruiter`, `club`, `admin`
- [ ] `live_streams.status` ENUM matches values used in queries
- [ ] `role_applications.status` ENUM matches all states used in code

---

## PHASE 5 — FILE DEPENDENCY AUDIT 🔵

### 5.1 Dead Files
- [ ] Files in `pages/`, `api/`, `assets/` that are never linked or included
- [ ] JS files loaded in HTML but never called
- [ ] CSS files loaded but contain only rules that match no HTML elements

### 5.2 Broken Includes
- [ ] Every `require_once` / `include_once` target exists on disk
- [ ] Every `<script src="...">` and `<link href="...">` target exists
- [ ] Every `fetch()` API URL in JS maps to a real `api/*.php` file

### 5.3 Circular / Redundant Includes
- [ ] `header.php` and `footer.php` not included multiple times per page
- [ ] `config/db.php` not included more than once per request chain
- [ ] No file that includes itself or creates a loop

### 5.4 Asset Orphans
- [ ] Images referenced in HTML/CSS/PHP that don't exist in `assets/`
- [ ] Uploaded file directories exist and have correct permissions (755)
- [ ] `.htaccess` present in upload directories

---

## PHASE 6 — PERFORMANCE AUDIT ⚪

### 6.1 N+1 Query Detection
- [ ] No DB query inside a `foreach` or `while` loop
- [ ] All list pages use JOINs to fetch related data in one query
- [ ] Feed/listing queries use `LIMIT` and `OFFSET` for pagination

### 6.2 Frontend Performance
- [ ] No synchronous `<script>` in `<head>` without `defer`/`async`
- [ ] Large images without `width`/`height` attributes (causes layout shift)
- [ ] Polling intervals documented and justified

### 6.3 API Response Size
- [ ] No `SELECT *` returning unused columns in feed/listing APIs
- [ ] JSON responses don't include sensitive fields (passwords, tokens)

---

## PHASE 7 — UI/UX & ACCESSIBILITY AUDIT 🎨

### 7.1 Broken Navigation
- [ ] All nav `href` values point to existing pages
- [ ] Active page highlighted in nav correctly
- [ ] Back/redirect flows don't leave users stranded

### 7.2 Empty States
- [ ] Every list/feed/table has a message when empty
- [ ] Loading states shown during AJAX requests

### 7.3 Form Completeness
- [ ] Every `required` field validated both client-side AND server-side
- [ ] Error messages shown inline (not just alerts)
- [ ] File inputs restrict type and size on the frontend

### 7.4 Mobile Responsiveness
- [ ] `<meta name="viewport">` present on all pages
- [ ] No fixed-pixel widths that break on small screens
- [ ] Modals scrollable on small screens

---

## AUDIT REPORT FORMAT

After all phases, produce this structured report:

```
╔══════════════════════════════════════════════════╗
║     ATHLETEHUB SYSTEM AUDIT REPORT               ║
║     Generated: [date] | Files Scanned: [N]       ║
╚══════════════════════════════════════════════════╝

📊 EXECUTIVE SUMMARY
┌─────────────────┬───────┐
│ Severity        │ Count │
├─────────────────┼───────┤
│ 🔴 Critical     │   X   │
│ 🟠 High         │   X   │
│ 🟡 Medium       │   X   │
│ 🟢 Low          │   X   │
│ 🔵 Info         │   X   │
│ TOTAL           │   X   │
└─────────────────┴───────┘

🔴 CRITICAL ISSUES (Fix Immediately)
────────────────────────────────────
[C-01] FILE: api/post.php | LINE: 34 | PHASE: Security
  ISSUE: Raw $_POST['user_id'] used directly in SQL query
  RISK:  SQL Injection — attacker can read/delete any data
  FIX:   Use PDO prepared statement with bound parameter
  CODE:  $stmt = $pdo->prepare("... WHERE id = ?");
         $stmt->execute([$_POST['user_id']]);

[C-02] ...

🟠 HIGH ISSUES
──────────────
[H-01] FILE: pages/feed.php | LINE: 1 | PHASE: Auth
  ISSUE: No session check at top of file
  RISK:  Any visitor can access the feed without logging in
  FIX:   Add session_start() + redirect check at line 1

🟡 MEDIUM ISSUES
────────────────
[M-01] ...

🟢 LOW ISSUES
─────────────
[L-01] ...

🔵 DATABASE FINDINGS
────────────────────
[DB-01] Missing index on messages.receiver_id
  FIX: ALTER TABLE messages ADD INDEX idx_receiver (receiver_id);

[DB-02] Orphaned records found: posts without valid user — 3 rows
  FIX: DELETE FROM posts WHERE user_id NOT IN (SELECT id FROM users);

📦 DEAD CODE & DEPENDENCIES
────────────────────────────
[DC-01] File: assets/js/old_feed.js — loaded in feed.php but never called
[DC-02] Function: formatDateOld() in main.js — defined but never called
[DC-03] CSS class: .legacy-card in glass.css — no matching HTML found

🗂️ FILES SCANNED
────────────────
pages/  : X files
api/    : X files
assets/ : X files
config/ : X files
includes: X files

══════════════════════════════════════════════════
END OF AUDIT REPORT
══════════════════════════════════════════════════
```

---

## PHASE 8 — AUTO-FIX SEQUENCE

After presenting the full report and getting user confirmation:

1. Fix **Critical** issues first, one file at a time
2. Fix **High**, then **Medium**, then **Low**
3. For each file fixed:
   - Show a diff of what changed
   - Add comment above each fix: `// AUDIT FIX [C-01]: description`
   - Re-scan the file to confirm no new issues introduced
4. For **Database** fixes: output ready-to-run SQL statements
5. For **Dead Code**: list files/functions safe to delete — confirm with user before deleting
6. Final summary:

```
✅ FIXES APPLIED
────────────────
api/post.php       — 2 issues fixed (C-01, H-03)
pages/feed.php     — 1 issue fixed (H-01)
api/livestream.php — 1 issue fixed (C-02)

SQL to run in phpMyAdmin:
  ALTER TABLE messages ADD INDEX idx_receiver (receiver_id);
  ALTER TABLE messages ADD INDEX idx_sender   (sender_id);

Safe to delete:
  assets/js/old_feed.js
  assets/css/legacy.css
```

---

## RULES FOR ALL FIXES

- Never change UI design — fix logic and security only
- Never break existing working functionality
- All SQL must use PDO prepared statements
- All user output must use `htmlspecialchars()`
- Match existing code style in each file
- `header('Location:...')` must always be followed by `exit;`
- Sensitive config must never be output in JSON responses

---

## QUICK REFERENCE — SEVERITY LEVELS

| Level    | Examples |
|----------|---------|
| 🔴 Critical | SQL injection, no auth on page, plain-text passwords, RCE risk |
| 🟠 High | Missing session check, missing role guard, CSRF unprotected, broken redirect |
| 🟡 Medium | XSS risk, missing null checks, N+1 queries, hardcoded paths |
| 🟢 Low | console.log left in code, dead variables, minor UX gaps |
| 🔵 Info | Missing indexes, orphaned records, dead files, style inconsistencies |

---

## REFERENCE FILES

- `references/security.md` — Full security checklist with code examples
- `references/database.md` — Schema analysis guide and index recommendations
