# Security Reference — AthleteHub Audit Skill

## SQL Injection Patterns to Detect

### Vulnerable patterns (flag immediately):
```php
// ❌ String concat
$sql = "SELECT * FROM users WHERE id=" . $_GET['id'];
$sql = "SELECT * FROM posts WHERE user_id=" . $userId;

// ❌ Direct interpolation
$pdo->query("DELETE FROM posts WHERE id=$id");

// ❌ LIKE with unescaped input
$sql = "SELECT * FROM users WHERE name LIKE '%" . $_GET['q'] . "%'";
```

### Correct patterns:
```php
// ✅ Prepared statement
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_GET['id']]);

// ✅ Named params
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute([':email' => $email]);

// ✅ LIKE with prepared statement
$stmt = $pdo->prepare("SELECT * FROM users WHERE name LIKE ?");
$stmt->execute(['%' . $search . '%']);
```

---

## XSS Patterns to Detect

### Vulnerable:
```php
// ❌ Raw echo
echo $_POST['content'];
echo $row['description'];
echo $user['name'];

// ❌ In attribute
echo '<input value="' . $_GET['q'] . '">';
```

### Correct:
```php
// ✅ Always escape output
echo htmlspecialchars($row['description'], ENT_QUOTES, 'UTF-8');

// ✅ Shorthand helper if defined
function e($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
echo e($user['name']);
```

### JS DOM XSS:
```javascript
// ❌ Dangerous
element.innerHTML = serverData.title;

// ✅ Safe
element.textContent = serverData.title;

// ✅ If HTML needed, sanitize first
element.innerHTML = DOMPurify.sanitize(serverData.html);
```

---

## Authentication Guard Pattern

Every `pages/*.php` must start with:
```php
<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit; // ← MUST have exit after header()
}
```

Every `api/*.php` must start with:
```php
<?php
require_once '../config/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in.']);
    exit;
}
```

---

## Role Guard Patterns

### Single role:
```php
if ($_SESSION['role'] !== 'club') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}
```

### Multiple roles:
```php
$allowed = ['club', 'coach'];
if (!in_array($_SESSION['role'], $allowed, true)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Unauthorized.']);
    exit;
}
```

### Admin only:
```php
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../pages/feed.php');
    exit;
}
```

---

## File Upload Security Checklist

```php
function validateUpload(array $file, array $allowedMimes, int $maxBytes): array {
    // 1. Check upload error
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload failed.'];
    }

    // 2. Check file size server-side
    if ($file['size'] > $maxBytes) {
        return ['ok' => false, 'error' => 'File too large.'];
    }

    // 3. Validate MIME type server-side (not just extension)
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowedMimes, true)) {
        return ['ok' => false, 'error' => 'Invalid file type.'];
    }

    // 4. Sanitize filename — never use original name
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeName = uniqid('upload_', true) . '.' . $ext;

    return ['ok' => true, 'filename' => $safeName, 'mime' => $mime];
}

// Usage:
$result = validateUpload(
    $_FILES['document'],
    ['application/pdf', 'image/jpeg', 'image/png'],
    5 * 1024 * 1024 // 5MB
);
```

### Upload directory .htaccess (must exist):
```apache
# Prevent execution of any script in upload directories
Options -ExecCGI
AddHandler cgi-script .php .php3 .php4 .php5 .phtml .pl .py .jsp .asp .htm .html .shtml .sh .cgi
php_flag engine off
```

---

## Password Security

```php
// ✅ Storing password
$hash = password_hash($password, PASSWORD_BCRYPT);

// ✅ Verifying password
if (!password_verify($inputPassword, $storedHash)) {
    // wrong password
}

// ✅ Session regeneration after login
session_regenerate_id(true);
$_SESSION['user_id'] = $user['id'];
$_SESSION['role']    = $user['role'];
```

### Red flags to find:
```php
// ❌ Never use these
md5($password)
sha1($password)
base64_encode($password)
// plain text storage
```

---

## CSRF Token Implementation

If CSRF is required, here is the correct pattern:

```php
// In session.php or a helper:
function generate_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token(): bool {
    $token = $_POST['csrf_token']
          ?? (json_decode(file_get_contents('php://input'), true)['csrf_token'] ?? '');
    return isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}
```

**Important audit note:** If `validate_csrf_token()` is called in any API file,
verify that `session.php` (or wherever it's defined) ACTUALLY EXISTS and is
included BEFORE the call. A missing `session.php` causes a fatal PHP error that
breaks the JSON response and causes "Network error" on the frontend.

---

## Sensitive Data Exposure

### Check PHP error reporting:
```php
// Should NOT be in production pages:
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Should be:
ini_set('display_errors', 0);
error_reporting(0);
```

### API responses must never include:
- `password` or `password_hash` fields
- DB connection strings
- File system paths
- Stack trace details

```php
// ❌
catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]); // exposes internals
}

// ✅
catch (Exception $e) {
    error_log($e->getMessage()); // log privately
    echo json_encode(['error' => 'A server error occurred.']);
}
```
