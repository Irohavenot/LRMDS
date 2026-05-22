# DepEd LRMDS Portal

**DepEd Learning Resource Management and Development System** — a PHP web portal for browsing, submitting, and managing K–12 learning resources. Runs on **XAMPP** (Apache + MySQL + PHP) with optional **Google OAuth**, **SMTP email**, and **Microsoft OneDrive** uploads via Graph API.

---

## What you need installed

| Software | Version / notes |
|----------|-----------------|
| [XAMPP](https://www.apachefriends.org/) | Apache + MySQL + PHP **7.4+** (PHP 8.x recommended) |
| [Composer](https://getcomposer.org/download/) | For **PHPMailer** (`composer install`) |
| Web browser | For local testing at `http://localhost/...` |

### PHP extensions (enable in `php.ini` if missing)

XAMPP usually enables these by default. Confirm these are **uncommented** (`extension=...`):

- `curl` — Google OAuth, Microsoft Graph / OneDrive
- `pdo_mysql` — main app database
- `pdo_sqlite` — OneDrive activity tracker (`onedrive/tracker.php`)
- `openssl` — HTTPS/TLS for SMTP and APIs
- `fileinfo` — avatar uploads
- `mbstring`, `json` — general use

Restart Apache after changing `php.ini`.

### Recommended `php.ini` values (large uploads)

Resource uploads allow up to **100 MB** (`api/upload-resource.php`). In `php.ini`:

```ini
upload_max_filesize = 100M
post_max_size = 105M
max_execution_time = 300
```

Restart Apache after editing.

---

## Quick start (XAMPP)

### 1. Clone or copy the project

Place the folder here (default layout used in this project):

```
C:\xampp\htdocs\deped-lrmds-portal\
```

URL: **http://localhost/deped-lrmds-portal/**

If you use a different path, update `APP_BASE_URL` in `.env` and Google OAuth redirect URIs (see below).

### 2. Start XAMPP

1. Open **XAMPP Control Panel**
2. Start **Apache**
3. Start **MySQL**

### 3. Install PHP dependencies (Composer)

Open a terminal in the project root:

```bash
cd C:\xampp\htdocs\deped-lrmds-portal
composer install
```

This creates `vendor/` with **PHPMailer** (required for registration verification and password OTP emails).  
`vendor/` is gitignored — every teammate must run `composer install` after cloning.

### 4. Configure environment (`.env`)

```bash
copy .env.example .env
```

Edit `.env` with your local values. **Never commit `.env`** — it is listed in `.gitignore`.

See [Environment variables](#environment-variables) for what each key does and which accounts you can swap.

### 5. Database (MySQL)

1. Open **http://localhost/phpmyadmin**
2. Create database: `lrmds` (collation `utf8mb4_unicode_ci` recommended)
3. Import a dump from the project maintainer:
   - **Export (maintainer):** phpMyAdmin → database `lrmds` → **Export** → SQL
   - **Import (teammate):** phpMyAdmin → **Import** → choose `lrmds.sql`

There is **no** `schema.sql` in the repo. The app expects an existing MySQL schema (at minimum a `users` table and related auth tables). Some features create tables automatically on first use (`email_verifications`, `password_otps`, `support_tickets`, site stats, OneDrive tracker SQLite DB).

**Default XAMPP MySQL credentials** (match `.env.example`):

| Setting | Value |
|---------|--------|
| Host | `localhost` |
| User | `root` |
| Password | *(empty)* |
| Database | `lrmds` |

> **Note:** Several auth files (`auth/signin_handler.php`, `auth/google_callback.php`, `auth/totp_*.php`, etc.) still use **hardcoded** `localhost` / `root` / empty password. They work out of the box on default XAMPP. If you change MySQL credentials, update those files **or** align MySQL with the defaults.

### 6. Writable folders

Create if missing (Apache/PHP user must be able to write):

| Path | Purpose |
|------|---------|
| `uploads/avatars/` | Profile photos |
| `onedrive/data/` | SQLite tracker DB (auto-created by `onedrive/tracker.php`) |
| `data/` | Optional JSON logs (e.g. resource submissions) |

### 7. Open the site

**http://localhost/deped-lrmds-portal/**

---

## Environment variables

Copy `.env.example` → `.env`, then set your own accounts and keys.

### Database

| Variable | Description |
|----------|-------------|
| `DB_HOST` | Usually `localhost` |
| `DB_NAME` | Database name (`lrmds`) |
| `DB_USER` | MySQL user (`root` on XAMPP) |
| `DB_PASS` | MySQL password (empty on default XAMPP) |

### Application URL

| Variable | Description |
|----------|-------------|
| `APP_BASE_URL` | Public base URL, **no trailing slash**. Used in verification email links. Example: `http://localhost/deped-lrmds-portal` |

### Google OAuth (Sign in with Google)

| Variable | Description |
|----------|-------------|
| `GOOGLE_CLIENT_ID` | OAuth 2.0 Client ID |
| `GOOGLE_CLIENT_SECRET` | OAuth 2.0 Client secret |

**Setup:**

1. [Google Cloud Console](https://console.cloud.google.com/) → create/select project  
2. **APIs & Services → Credentials → Create credentials → OAuth client ID**  
3. Type: **Web application**  
4. **Authorized redirect URIs** (add all you use):

   ```
   http://localhost/deped-lrmds-portal/auth/google_callback.php
   ```

   For mobile/LAN testing, also add your PC’s LAN IP (see comment in `auth/google_oauth.php`).

5. Paste Client ID and Secret into `.env`.

Used by: `auth/google_oauth.php`, `auth/google_callback.php` (raw `curl`, no Google SDK).

### Mail (SMTP / PHPMailer)

| Variable | Description |
|----------|-------------|
| `MAIL_HOST` | SMTP host |
| `MAIL_PORT` | SMTP port (`587` for TLS, `2525` for Mailtrap sandbox) |
| `MAIL_USERNAME` | SMTP username |
| `MAIL_PASSWORD` | SMTP password |
| `MAIL_FROM_ADDR` | From email address |
| `MAIL_FROM_NAME` | From display name |
| `VERIFY_TOKEN_TTL_HOURS` | Email verification link lifetime (hours) |

**Development:** [Mailtrap](https://mailtrap.io) → Email Testing → inbox → SMTP settings (PHPMailer).  
**Production:** Use your org relay (e.g. Brevo, institutional SMTP). Sender must be allowed by your provider.

Used by: `lib/send_verification_email.php`, `lib/send_password_otp.php`.

### Microsoft OneDrive (Azure AD app)

| Variable | Description |
|----------|-------------|
| `AZURE_CLIENT_ID` | Application (client) ID |
| `AZURE_CLIENT_SECRET` | Client secret **value** (not Secret ID) |
| `AZURE_TENANT_ID` | Directory (tenant) ID — must be your tenant, not `common` |
| `ONEDRIVE_FOLDER_PATH` | Base folder on OneDrive, e.g. `Documents/deped` |
| `ONEDRIVE_USER_UPN` | Email/UPN of the M365 user whose OneDrive receives files |

**Setup:**

1. [Azure Portal](https://portal.azure.com/) → **Microsoft Entra ID → App registrations → New registration**  
2. Note **Application (client) ID** and **Directory (tenant) ID**  
3. **Certificates & secrets** → New client secret → copy the **Value**  
4. **API permissions** → Microsoft Graph → **Application** permissions (e.g. `Files.ReadWrite.All`) → **Grant admin consent**  
5. Set `ONEDRIVE_USER_UPN` to the mailbox/OneDrive account that should own uploaded files.

Used by: `api/onedrive-helper.php`, `api/upload-resource.php`, `api/submit-news.php`, OneDrive admin pages under `onedrive/`.

Folder layout on OneDrive (created under `ONEDRIVE_FOLDER_PATH`):

```
Documents/deped/
├── News/
│   ├── Announcements/{year}/
│   ├── Memorandums/{year}/
│   └── ...
└── Resources/
    └── {Subject}/{Grade}/{Quarter}/
```

---

## External services summary

| Service | Required for | Free tier / local |
|---------|----------------|-------------------|
| XAMPP MySQL | Core app, users, auth | Local |
| Composer + PHPMailer | Email verification, password OTP | Local vendor install |
| Google Cloud OAuth | “Sign in with Google” | Free with Google account |
| SMTP (Mailtrap / Brevo / etc.) | Registration & forgot-password email | Mailtrap sandbox for dev |
| Azure app + OneDrive | Resource/news uploads to cloud | Requires M365 / Entra admin |

---

## Project structure (high level)

```
deped-lrmds-portal/
├── index.php              # Landing / search
├── auth/                  # Sign-in, Google OAuth, TOTP, forgot password
├── registration/          # Register, verify email, resend
├── api/                   # OneDrive helper, uploads, news API
├── onedrive/              # Admin dashboard, analytics tracker
├── includes/              # header.php, footer.php, profile_panel.php
├── lib/                   # env.php, email helpers, bundled 2FA (TOTP) library
├── assets/                # CSS, JS, icons
├── uploads/avatars/       # User avatars (runtime)
├── vendor/                # Composer (PHPMailer) — run composer install
├── .env.example           # Template — copy to .env
└── composer.json
```

---

## Features & dependencies

| Feature | Dependency |
|---------|------------|
| Pages & APIs | PHP, Apache, MySQL |
| Email verification / OTP | Composer → PHPMailer, SMTP in `.env` |
| Google sign-in | Google OAuth credentials, PHP `curl` |
| TOTP 2FA | Bundled `lib/TwoFactorAuth.php` (no extra Composer package) |
| OneDrive uploads | Azure app + Graph API, PHP `curl` |
| OneDrive analytics | SQLite in `onedrive/data/` (auto-created) |

---

## Troubleshooting

| Problem | Check |
|---------|--------|
| Blank page / 500 | Apache error log, `php.ini` display_errors (dev only) |
| Database connection failed | MySQL running in XAMPP; database `lrmds` exists; credentials |
| `Class "PHPMailer\..." not found` | Run `composer install` in project root |
| Google redirect mismatch | Redirect URI in Google Console **exactly** matches `auth/google_callback.php` URL |
| Emails not sent | `.env` SMTP values; Mailtrap inbox; PHP `openssl` / port 587 |
| OneDrive upload fails | Azure permissions + admin consent; `ONEDRIVE_USER_UPN`; tenant ID not `common` |
| Sign-in works but register email fails | `.env` mail settings (register uses `lib/env.php`) |

---

## Sharing the project safely

**Include in Git / zip:**

- All source code, `assets/`, `.env.example`, `composer.json`, `composer.lock` (if present), this `README.md`

**Do NOT share:**

- `.env` (secrets)
- `vendor/` (teammates run `composer install`)
- `onedrive/data/*.db`, `uploads/`, personal `data/*.json` logs

**Provide separately (secure channel):**

- MySQL dump (`lrmds.sql`)
- Optional: a redacted checklist of which Google/Azure/SMTP accounts the team should create (not the actual secrets)

---

## License / attribution

DepEd LRMDS — internal / institutional use. Update this section if your team adds a formal license.
