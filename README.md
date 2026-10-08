# Witty Voting System

An online voting management system built with PHP, MySQL, and Cloudinary. It provides secure election management, voter registration, candidate applications, and real-time results — with a themeable UI (light/dark mode) and email-based password recovery.

---

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [Project Structure](#project-structure)
- [Requirements](#requirements)
- [Local Setup](#local-setup)
- [Deployment on Render](#deployment-on-render)
- [Environment Variables](#environment-variables)
- [Database](#database)
- [Cloudinary Integration](#cloudinary-integration)
- [Email Integration (EmailJS)](#email-integration-emailjs)
- [Admin Panel](#admin-panel)
- [Voter Panel](#voter-panel)
- [Theme System](#theme-system)
- [Security Notes](#security-notes)
- [Troubleshooting](#troubleshooting)
- [License](#license)

---

## Features

### Voter Features
- Register with a profile photo, personal details, and election subscriptions
- Login / Logout with hashed passwords
- Vote in active elections — one vote per position, one vote per election
- Apply for candidacy with a profile photo, bio, and manifesto
- Withdraw candidacy applications before election close
- View live results with vote counts, percentages, and winner badges
- Browse contestants for every election and position
- View registered voters directory
- Manage profile — update name, email, password, and photo
- Forgot password — reset via email link
- Help center — accordion-style guide with FAQ

### Admin Features
- Secure login with a separate admin table
- Dashboard with summary stats (elections, users, votes, candidates)
- Manage voters — view, edit, delete, and register voters to elections
- Manage candidates — add, view, delete with photo uploads
- Manage elections — create, update status, set dates
- Manage posts — define positions per election
- Voting settings — control voting/registration toggles
- View detailed results per election and per position
- Admin profile — update name, email, password, and photo
- Audit log — every login and major action is recorded

### System Features
- Cloudinary image hosting — all images served from CDN
- EmailJS password reset — transactional email without exposing SMTP credentials
- Light/Dark theme — persisted in localStorage, applies across every page
- Responsive design — mobile-first, works down to 360px width
- Audit trail — event_log table tracks admin sessions
- Prepared statements — every DB query uses PDO parameter binding
- Password hashing — password_hash() / password_verify() (bcrypt)

---

## Tech Stack

| Layer | Technology |
|---|---|
| Backend | PHP 8.3+ |
| Database | MySQL 8 / MariaDB 10.4+ |
| Web server | Apache (with mod_rewrite) |
| Container | Docker (php:8.3-apache base) |
| Hosting | Render.com |
| Image storage | Cloudinary |
| Transactional email | EmailJS (SMTP relay via Gmail App Password) |
| Frontend | Vanilla HTML/CSS/JS, Font Awesome 6 |
| Package manager | Composer |

---

## Project Structure

```
witty-voting-system/
├── composer.json
├── composer.lock
├── Dockerfile
├── schema.sql
├── README.md
├── cloudinary.php
├── conn.php
├── header.php
├── footer.php
│
├── index.php
├── login.php
├── register.php
├── logout.php
├── forgot_password.php
├── test_reset.php
├── profile.php
├── vote.php
├── apply.php
├── contest.php
├── my_applications.php
├── members.php
├── help.php
├── get_posts.php
│
├── admin_login.php
├── reg.php
├── main.php
│
├── includes/
│   ├── admin.php
│   ├── admin_settings.php
│   ├── admin_manage_posts.php
│   ├── manage_candidates.php
│   ├── manage_elections.php
│   ├── manage_users.php
│   ├── manage_votes.php
│   ├── refreshdb.php
│   ├── view_results.php
│   └── voting_settings.php
│
├── vendor/
└── .env
```

---

## Requirements

- PHP 8.3 or higher with extensions: pdo_mysql, mysqli, gd, zip, mbstring, xml, curl
- Composer 2.x
- MySQL 8.0+ or MariaDB 10.4+
- Cloudinary account (free tier works)
- EmailJS account (free tier: 200 emails/month)
- Gmail account with 2-Step Verification enabled

---

## Local Setup

### 1. Clone and install dependencies

```bash
git clone <your-repo-url>
cd Voting-System
composer install
```

### 2. Create the database

```bash
mysql -u root -p < schema.sql
```

### 3. Configure the connection

Edit conn.php:

```php
<?php
$host = 'localhost';
$dbname = 'witty_voting';
$username = 'root';
$password = 'your_password';

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("DB connection failed: " . $e->getMessage());
    die("Database connection error.");
}
```

### 4. Set environment variables

```
CLOUDINARY_CLOUD_NAME=your_cloud_name
CLOUDINARY_API_KEY=your_api_key
CLOUDINARY_API_SECRET=your_api_secret
DEFAULT_AVATAR_URL=https://res.cloudinary.com/your_cloud/image/upload/v.../admin.webp

EMAILJS_SERVICE_ID=service_xxxxxxx
EMAILJS_TEMPLATE_ID=template_xxxxxxx
EMAILJS_PUBLIC_KEY=your_public_key
EMAILJS_PRIVATE_KEY=your_private_key

ADMIN_INVITE_CODE=some_long_random_string
```

### 5. Create your first admin

Visit includes/reg.php and enter your ADMIN_INVITE_CODE, or insert directly:

```sql
INSERT INTO admin (username, password, name, email)
VALUES ('admin', '$2y$10$...', 'Administrator', 'admin@example.com');
```

### 6. Run

```bash
php -S localhost:8000
```

Open http://localhost:8000/login.php

---

## Deployment on Render

### 1. Create the service

1. Push your code to GitHub
2. In Render, create a Web Service and connect your repo
3. Environment: Docker
4. Instance type: Free or Starter

### 2. Build

The Dockerfile handles the build automatically:

```dockerfile
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY . /var/www/html/
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction
```

### 3. Add environment variables

Add all keys from the Environment Variables section.

### 4. Create the database

Use a managed MySQL host like Aiven, PlanetScale, or Railway. Update conn.php to read from env vars:

```php
$host = getenv('DB_HOST');
$dbname = getenv('DB_NAME');
$username = getenv('DB_USER');
$password = getenv('DB_PASS');
```

### 5. Run the schema on production

```bash
mysql -h <host> -u <user> -p<pass> <db> < schema.sql
```

---

## Environment Variables

| Variable | Purpose | Where to get it |
|---|---|---|
| CLOUDINARY_CLOUD_NAME | Cloudinary account identifier | Cloudinary console |
| CLOUDINARY_API_KEY | Cloudinary API key | Same dashboard |
| CLOUDINARY_API_SECRET | Cloudinary API secret | Same dashboard |
| DEFAULT_AVATAR_URL | System-wide fallback avatar | Upload once, copy secure URL |
| EMAILJS_SERVICE_ID | EmailJS service identifier | EmailJS dashboard |
| EMAILJS_TEMPLATE_ID | EmailJS template identifier | EmailJS dashboard |
| EMAILJS_PUBLIC_KEY | EmailJS public key | EmailJS Account |
| EMAILJS_PRIVATE_KEY | EmailJS private key | EmailJS Security |
| ADMIN_INVITE_CODE | Gate for admin registration | Generate with openssl rand -hex 32 |

---

## Database

 Key tables:

| Table | Purpose |
|---|---|
| admin | Admin accounts |
| users | Voter accounts |
| elections | Election records |
| election_posts | Positions per election |
| user_elections | Voter to election registrations |
| contesters | Candidate applications |
| votes | Individual vote records |
| password_reset_tokens | One-time password reset tokens |
| event_log | Audit trail |

### Relationships

```
users --< user_elections >-- elections
      --< contesters >------ elections
      --< votes >----------- elections
                            --< election_posts
```

All foreign keys are ON DELETE CASCADE.

---

## Cloudinary Integration

Every image in the system is stored on Cloudinary.

### Helpers

cloudinary.php exposes:

- uploadToCloudinary($tmpPath, $folder) — returns the secure URL
- extractPublicIdFromUrl($url) — for deletion
- defaultAvatarUrl() — reads DEFAULT_AVATAR_URL env var

### Image folders

| Folder | Used by |
|---|---|
| voters/ | Voter profile photos |
| candidates/ | Candidate photos |
| admins/ | Admin profile photos |
| defaults/ | System fallback avatar |

### Auto-transformations

Uploads are auto-cropped to 500x500 with face-centered gravity.

---

## Email Integration (EmailJS)

Password resets are sent via EmailJS's HTTP API.

### Setup

1. Sign up at emailjs.com
2. Connect your email service (Gmail SMTP + App Password)
3. Create a template with variables: user_name, user_email, reset_link, expiry
4. Set the To Email field to {{user_email}}
5. Enable "Allow EmailJS API for non-browser applications"
6. Enable "Use Private Key" and copy the key
7. Add the four EMAILJS_* env vars to Render

### Testing

Visit /reset_password.php (no token) to see a status panel and a test email form.

---

## Admin Panel

Location: /admin_login.php to /main.php

| Page | Purpose |
|---|---|
| Dashboard | Summary stats + recent activity |
| Manage Voters | View/edit/delete users |
| Manage Votes | See all vote records |
| Manage Candidates | Add/view/delete candidates |
| Manage Elections | Create/update elections |
| Manage Posts | Define positions per election |
| Voting Settings | Control voting/registration toggles |
| View Results | Detailed per-position results |
| Profile Settings | Update admin info |
| Refresh Vote | Reset votes for an election |

---

## Voter Panel

Location: /login.php to /profile.php

| Page | Purpose |
|---|---|
| Profile | Update personal info, password, photo |
| Vote | Cast votes in active elections |
| Candidacy | Apply to run for a position |
| Contesters | Browse all candidates |
| My Apps | Manage registrations, withdraw candidacy |
| Results | Public results view |
| Members | Directory of registered voters |
| Help | User guide + FAQ |

---

## Theme System

Every page supports light and dark themes. Preference stored in localStorage under voting_theme.

### Light theme

- Page background: #f4f7f9
- Cards: #ffffff with #e5e7eb borders
- Navbar: white with subtle shadow
- Accent: #2c7a7b

### Dark theme

- Page background: #1e293b
- Cards: #1e1e2e
- Navbar: #0f172a
- Accent: #2c7a7b

### Input visibility

All form inputs include:

```css
input {
    color: #1f2937;
    -webkit-text-fill-color: #1f2937;
}
body.dark-theme input {
    color: #f3f4f6;
    -webkit-text-fill-color: #f3f4f6;
}
input:-webkit-autofill { /* autofill override */ }
```

---

## Security Notes

### What is protected

- Passwords hashed with password_hash(PASSWORD_DEFAULT)
- All DB queries use PDO prepared statements
- Sessions destroyed on logout
- Admin registration requires ADMIN_INVITE_CODE
- Password reset tokens are 64-char hex with 1-hour expiry
- Reset tokens are single-use
- SQL errors logged, not displayed
- File uploads validate extension, MIME type, size (2MB max)

### What to watch

- Never commit .env files
- Rotate Cloudinary / EmailJS keys if exposed
- Delete test_reset.php from production if unused
- Set ADMIN_INVITE_CODE to empty after creating admins
- Use HTTPS in production
- Enable session.cookie_secure and session.cookie_httponly

---

## Troubleshooting

### Error fetching admin details

Missing profile_photo column on admin. Run:

```sql
ALTER TABLE admin CHANGE COLUMN photo profile_photo VARCHAR(500);
```

### Images not loading / 404

- Verify DEFAULT_AVATAR_URL on Render
- Check Cloudinary env vars
- Confirm vendor/autoload.php exists

### Failed opening required vendor/autoload.php

Composer install didn't run. Check Dockerfile includes:

```dockerfile
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction
```

### EmailJS returns HTTP 422

Template To Email field references a variable PHP doesn't send. Match both sides.

### Typed text invisible in inputs

Add -webkit-text-fill-color and -webkit-autofill overrides. See Theme System.

### Headers already sent

Whitespace before <?php or an include that outputs. Remove blank lines.

---

## License

This project is proprietary. All rights reserved by Jacob Witty.

For questions or licensing inquiries: wittyhighbrowtechnologies@gmail.com

---

## Credits

- Font Awesome 6
- Inter font
- Cloudinary
- EmailJS
- Render
- Aiven / PlanetScale / Railway

---

## Changelog

| Version | Date | Notes |
|---|---|---|
| 1.0.0 | 2026-10-08 | Initial release |
---

BUILT BY Witty Highbrow Technologies
---
