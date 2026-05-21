# ♊ Gemini Agent Instructions (GEMINI.md)

This file contains instructions specifically tailored for Gemini models to ensure high-quality contributions to the **AthleteHub** codebase.

## ⚙️ Environment Configuration
- **Operating System**: Windows (using XAMPP Apache + MariaDB / MySQL).
- **Web Base Path**: `/dashboard/AthleteHub V1.3/` maps directly to the `AthleteHub V1.3/` directory.
- **Database Details**: Default connection uses host `localhost`, username `root`, empty password, and database `athletehub`.

---

## 🛠️ Implementation Guidance for Gemini
1. **Windows Path Handling**: Always use forward slashes `/` in code templates or relative imports to prevent Windows path backslash escape issues in PHP/JS.
2. **AJAX vs HTML Handlers**: Be mindful of pages (e.g., `messages.php`) that serve both HTML and JSON responses. Ensure proper termination (`exit` or `die`) is called after JSON output to avoid appending HTML and breaking API parsing.
3. **Vanilla JS Debouncing**: Ensure event listeners (e.g., searches, scrolls) use standard debouncing with correct `this` bindings. Avoid capturing lexical scope incorrectly in utility functions.
4. **Style Alignment**: Maintain the "Glassmorphism + Light Blue" theme. When adding elements, use the CSS variables defined in `assets/css/main.css` and classes from `assets/css/glass.css`.
5. **No Placeholders**: Do not insert placeholder images. Generate assets using available system tools or use whitelisted static icons.

---

## 📊 Reference Checklist
- Centralized Auth Check: Always require `includes/session.php` instead of performing ad-hoc session validations in pages.
- Database Connection: Ensure `$pdo` is fetched from `config/db.php`.
- Error Tracking: Check `logs/error.log` for runtime notices or database query errors.
