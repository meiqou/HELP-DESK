# USLS IT Support Helpdesk: Secure Login System

**Topic:** Secure Login System (Authentication, Authorization and Password Hashing)
**Members:** Parcon, Rayle · Paza, Ezra Joie · Punzalan, Khirk Justin · Plateros, Glory Mae

A helpdesk portal for the University of St. La Salle where students, faculty and staff file IT support
tickets, IT technicians resolve them, and administrators manage accounts. Security is built into every request,
and an automated test suite of 149 checks proves that every control works.

---

## 1. Setup (XAMPP)

1. Copy the `IAS NEW` folder into `C:\xampp\htdocs\` (for example as `C:\xampp\htdocs\helpdesk`).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open phpMyAdmin, choose **Import**, select `database/schema.sql` and press **Go**.
   This creates the `secure_system` database. If you already have the database from the old version,
   the script upgrades the tables in place without deleting any data.
4. Create the first administrator from a terminal (the script refuses to run in a browser):
   ```
   cd C:\xampp\htdocs\helpdesk
   C:\xampp\php\php.exe setup\create_admin.php
   ```
5. Visit `http://localhost/helpdesk/` and sign in.

Only the `public/` folder is served. The `.htaccess` in the project root routes every request into it, so
the source code, configuration and secret keys can never be downloaded. On a production server, point the
Apache `DocumentRoot` directly at `public/`.

On the first page load the app creates `config/secrets.php` with two random keys (the password pepper and
the encryption key). This file is excluded from Git. **Never commit or share it.** If it is lost, every
password must be reset.

Database credentials can be set with environment variables (`DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`,
`DB_PASS`) instead of editing `config/config.php`.

---

## 2. Running the tests

```
C:\xampp\php\php.exe tests\run.php
```

The suite creates its own throwaway database (`secure_system_test`), starts its own web server, and runs
149 checks: unit tests of the security classes plus end-to-end attacks over real HTTP (CSRF, SQL injection,
XSS, IDOR, session fixation, session hijacking, brute force, password spraying, replayed 2FA codes, parallel
2FA guessing, timing-based user enumeration, hostile input, open redirects, log tampering and more). It prints PASS or FAIL for each check and exits with an error code if
anything fails. Your real database is never touched. The latest results are saved to `tests/last-run.json`.

---

## 3. Architecture

The code is split into layers, each with one job:

```
Browser
   │
   ▼
public/            Page controllers: read the request, call services, pick a view. Nothing else is web-reachable.
   │
   ├── src/Auth/          Sign-in flow, two-factor, registration, role-based access control, admin actions
   ├── src/Security/      Password hashing and policy, CSRF, rate limiting, encryption, TOTP, audit log, headers
   ├── src/Domain/        Ticket rules (allowed categories, priorities, statuses, validation)
   ├── src/Repositories/  Every SQL query, always with bound parameters
   ├── src/Core/          Configuration, database connection, hardened session, view rendering
   ▼
views/             HTML templates only (no queries, no business rules). Every value is escaped with e().
```

Benefits for security: there is exactly one place for each rule (for example, all permissions live in
`Rbac::PERMISSIONS` and all SQL lives in the repositories), so a rule cannot be forgotten on one page, and
each security class can be tested on its own.

---

## 4. Security features

### A. Password hashing

| Control | How it works | Where |
|---|---|---|
| Argon2id hashing | Memory-hard algorithm (64 MB, 4 iterations) with a random salt per password. Falls back to bcrypt cost 12 if Argon2id is unavailable. | `Security/PasswordHasher` |
| Pepper | Before hashing, the password is combined with a secret key using HMAC-SHA256. The key is stored outside the database, so a stolen database alone cannot be cracked offline. | `PasswordHasher::pepper()` |
| Automatic rehash | Hashes from the old version of the system are accepted once and upgraded on the next sign-in. | `PasswordHasher::verify()` |
| Password policy | 12 to 128 characters, upper and lower case, a number and a symbol, no long repeats, not a common password, and not containing the username or e-mail name. Enforced on the server; the strength meter is only a hint. | `Security/PasswordPolicy` |
| Timing-safe checks | When a username does not exist, a dummy hash is still verified, so response time does not reveal which accounts exist. | `PasswordHasher::dummyVerify()` |

### B. Authentication

| Control | How it works |
|---|---|
| Brute-force lockout | 5 failed attempts per account, or 50 per IP address (generous because a campus shares one public IP), within 15 minutes locks sign-in. It works the same for usernames that do not exist, and a wrong password and an unknown username take the same time to answer (no account enumeration). |
| Two-factor authentication (TOTP) | Optional 6-digit codes from Google or Microsoft Authenticator (RFC 6238, verified against the official test vectors). The secret is encrypted with AES-256-GCM; a code can never be used twice, even by two requests at the same moment. The lockout is checked before every code, so opening many sign-in sessions gives no extra guesses. Setting up 2FA requires the password, and an existing setup cannot be silently replaced. |
| Recovery codes | 8 single-use codes, stored hashed like passwords, shown only once. |
| Session fixation defence | The session ID is replaced on sign-in, on 2FA completion and every 10 minutes; IDs the server did not create are refused. |
| Hardened cookies | `HttpOnly`, `SameSite=Strict`, `Secure` on HTTPS, never placed in URLs. |
| Session timeouts | 15-minute idle timeout and 8-hour absolute timeout. |
| Session binding | A session is tied to the browser; a stolen cookie used from another browser is rejected and logged. |
| Global sign-out | Changing a password, or an admin changing a role or deactivating an account, ends that user's other sessions instantly. |
| Step-up authentication | Opening **Users** or **Audit log** requires the admin to re-enter their password if it was not confirmed in the last 10 minutes. |
| Rate-limited password checks | Every place that asks for the current password (step-up, password change, 2FA changes) counts wrong guesses; after 5 the session is ended. |
| Safe logout | Logout is a POST request with a CSRF token, so other websites cannot force a logout. |

### C. Authorization (role-based access control)

| Permission | Student / Staff | IT Technician | Administrator |
|---|:-:|:-:|:-:|
| File and view own tickets | ✓ | | |
| Manage ticket queue | | ✓ | ✓ |
| Overview dashboard | | ✓ | ✓ |
| Manage users and roles | | | ✓ |
| View audit log | | | ✓ |

- Every page calls `Auth::requirePermission()` before doing anything; violations return **403** and are logged.
- The role is re-read from the database on every request, so a demotion takes effect on the next click.
- **IDOR protection:** ownership is part of every query (`WHERE id = ? AND user_id = ?`).
- New accounts are always students; a forged `role` field in the registration form is ignored.
- Admins cannot demote, deactivate or delete themselves, and the last active admin cannot be removed.

### D. Other web-security controls

- **SQL injection:** prepared statements everywhere with emulation disabled; filter values are whitelisted.
- **XSS:** all output passes through `e()`; a strict **Content-Security-Policy** blocks inline and third-party scripts.
- **CSRF:** a random token is required on every POST form and compared in constant time.
- **Security headers:** CSP, `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `Cache-Control: no-store`, HSTS on HTTPS.
- **Tamper-evident audit log:** every security event is stored with an HMAC chained to the previous entry.
  *Audit log, Verify integrity* detects any row edited or deleted directly in the database.
- **Rate limits per network address** for sign-in (password spraying) and registration (mass sign-ups).
- **No information leaks:** PHP errors are hidden; database errors are logged, never displayed.
- **Removed from the old version:** `debug_login.php`, `create_admin.php` and `setup_admin.php`. Anyone could
  open them in a browser to reset the admin password to `admin123` and see password hashes.

---

## 5. Suggested demo for the presentation

1. Run `php tests\run.php` and show 149 of 149 checks passing.
2. Register with `password123`: the policy rejects it. Register with a strong password: the meter turns green.
3. Enter a wrong password 5 times: the account locks. Try a non-existent username: same message.
4. In phpMyAdmin, show the `users.password` column: `$argon2id$...` hashes, no plain text.
5. Account, Security settings: set up two-factor with Google Authenticator, then sign out and back in with the code.
6. As a student, open `admin_tickets.php` directly: **403 Access denied**, then show it in the audit log.
7. As admin, change a student to technician while they are signed in: their next click signs them out.
8. Submit a ticket with `<script>alert(1)</script>` as the subject: it is displayed as text, not executed.
9. Edit any row of `audit_log` in phpMyAdmin: *Verify integrity* reports the tampered entry.

---

## 6. Suggestions for further improvement

1. **Serve over HTTPS only.** Enable SSL in XAMPP (or use a real certificate in production) and uncomment the
   redirect in `public/.htaccess`. Also set `ServerTokens Prod` in `httpd.conf` and `expose_php = Off` in `php.ini`.
2. **E-mail verification and password reset** with one-time, expiring, hashed tokens.
3. **Breached-password check** using the *Have I Been Pwned* range API (only the first 5 characters of the hash are sent).
4. **Passkeys / WebAuthn** for phishing-resistant sign-in with fingerprint, Windows Hello or a security key.
5. **QR code for 2FA setup**, generated locally, so users can scan instead of typing the key.
6. **Single sign-on** with the university's Microsoft 365 accounts through OAuth 2.0 / OpenID Connect.
7. **Least-privilege database user**: use the `helpdesk_app` account in `database/schema.sql` instead of `root`.
8. **CAPTCHA after repeated failures** to slow automated attacks without locking out real users.
9. **Off-site log shipping** to a separate log server so even a full server compromise cannot erase the trail.
10. **Dependency-free CI**: run `tests/run.php` automatically on every push (for example with GitHub Actions).
11. **Ticket attachments** with strict file-type checks, random names and storage outside the web root.
12. **Scheduled cleanup** of old `auth_attempts` rows and a retention policy for the audit log.

---

## 7. Project structure

```
IAS NEW/
├── public/                    Web root (the only folder the browser can reach)
│   ├── index.php              Landing page
│   ├── login.php              Sign in
│   ├── verify_2fa.php         Second factor (authenticator code or recovery code)
│   ├── register.php           Account creation
│   ├── logout.php             POST-only sign out
│   ├── dashboard.php          Student: file a ticket
│   ├── my_tickets.php         Student: ticket history
│   ├── account.php            Password change, 2FA, sessions, recent activity
│   ├── confirm_password.php   Step-up authentication
│   ├── admin_dashboard.php    Technician/admin overview
│   ├── admin_tickets.php      Ticket queue
│   ├── manage_users.php       Roles, deactivation, lockouts, 2FA reset (admin)
│   ├── audit_log.php          Audit trail and integrity check (admin)
│   └── assets/                CSS, JavaScript, images
├── src/
│   ├── bootstrap.php          Autoloader, configuration, headers, session
│   ├── helpers.php            e(), view(), icon() and other small helpers
│   ├── Core/                  Config, Database, Session, View
│   ├── Security/              PasswordHasher, PasswordPolicy, Csrf, RateLimiter, Encryptor, Totp, AuditLog, SecurityHeaders, Secrets
│   ├── Auth/                  Auth, Rbac, Registration, RecoveryCodes, UserAdministration
│   ├── Domain/                Ticket
│   └── Repositories/          UserRepository, TicketRepository
├── views/                     layout.php and one template per page
├── config/                    config.php (settings) and secrets.php (generated, never committed)
├── database/schema.sql        Tables and upgrade script
├── setup/create_admin.php     Command-line admin creation
└── tests/run.php              Automated security and feature tests
```
