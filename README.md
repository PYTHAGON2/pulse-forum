# Pulse Forum & Chat Community Platform

A clean, modern, lightweight hybrid forum and live chat community application designed to run on **InfinityFree free hosting** or any basic PHP/MySQL shared hosting plan.

---

## 🌟 Key Features

- **Hybrid Forum + Real-Time Chat**: Reddit/HackerNews-style threads combined with a Discord-like live community chat widget.
- **InfinityFree Optimized**: Pure PHP, Vanilla JS, and CSS3. Zero Node.js, WebSockets, or background server process requirements.
- **Session Authentication**: Secure user registration & login using `password_hash()` and `password_verify()`.
- **Prepared Statements**: PDO prepared queries across all database interactions for robust security against SQL injection.
- **XSS & CSRF Protection**: Strict CSRF token validation on state-changing POST requests and HTML escaping.
- **Modern UI/UX**: Dark mode aesthetic, responsive grid layout, micro-interactions, CSS smooth transitions, toast alerts, modal dialogs, and SVG initial avatars.
- **Reputation & Karma**: Karma-style scoring (+5 for new threads, +2 for replies, +1 for upvotes).

---

## 🚀 Local Development Setup

### Requirements
- **PHP 8.0+**
- **MySQL / MariaDB** (or SQLite for quick zero-config local testing)

### Quick Start with PHP Built-in Server

1. Clone or extract the application files into your working directory.
2. Configure database credentials in `includes/config.local.php` or `includes/config.example.php`.
3. Start PHP's built-in web server:

```bash
php -S localhost:8000
```

4. Open your browser and navigate to: `http://localhost:8000`

### Automated Re-deployment to InfinityFree

To re-upload any updated files to InfinityFree at any time, run:

```bash
php deploy.php
```


---

## 🌐 InfinityFree Deployment Guide

Follow these steps to deploy to **InfinityFree**:

1. **Create Account & Hosting Control Panel**:
   - Log in to your InfinityFree Account Dashboard.
   - Access **Control Panel (cPanel)**.

2. **Create MySQL Database**:
   - Navigate to **MySQL Databases** under the Databases section.
   - Create a new database (e.g., `epiz_12345678_pulse`).
   - Note down your **Database Host**, **Database Name**, **Database Username**, and **Database Password**.

3. **Import Database Schema**:
   - Open **phpMyAdmin** from your InfinityFree control panel.
   - Select your newly created database.
   - Click the **Import** tab and upload `database/schema.sql`.
   - Click **Go** to execute and create all tables and default categories.

4. **Configure Database Credentials**:
   - Rename `includes/config.example.php` to `includes/config.php` (or edit `includes/config.php`).
   - Fill in your InfinityFree MySQL details:

```php
define('DB_DRIVER', 'mysql');
define('DB_HOST', 'sqlxxx.epizy.com'); // Your InfinityFree DB Host
define('DB_NAME', 'epiz_12345678_pulse'); // Your DB Name
define('DB_USER', 'epiz_12345678');      // Your DB Username
define('DB_PASS', 'your_password_here'); // Your DB Password
```

5. **Upload via FTP**:
   - Connect to your InfinityFree server via FileZilla or FTP client.
   - Navigate into the `htdocs` (or `public_html`) folder.
   - Upload all application files (`index.php`, `login.php`, `register.php`, `thread.php`, `profile.php`, `chat.php`, `api/`, `assets/`, `includes/`, `database/`).

6. **Verify Deployment**:
   - Visit your InfinityFree web domain.
   - Register a new member account and start creating topics and sending chat messages!

---

## 📁 Directory Structure

```text
├── index.php                 # Main Forum Homepage & Discussions List
├── login.php                 # Standalone Login Page
├── register.php              # Standalone Registration Page
├── thread.php                 # Discussion Topic & Replies Page
├── profile.php                # Member Profile & Reputation Page
├── chat.php                  # Fullscreen Live Chatroom Page
├── api/                      # Light REST-style API Endpoints
│   ├── auth.php              # Login, Register, Session management
│   ├── threads.php           # Discussion topics listing & creation
│   ├── posts.php             # Replies, Upvoting & Deletion
│   ├── chat.php              # Polling & sending chat messages
│   └── users.php             # Profile metadata endpoint
├── assets/
│   ├── css/style.css         # Modern CSS Design System
│   └── js/app.js             # Vanilla JS Frontend Logic & Polling
├── includes/
│   ├── config.example.php    # DB & Application Settings Example
│   ├── config.php            # Master Config Entrypoint
│   ├── database.php          # PDO Database Connection
│   ├── auth.php              # Auth & CSRF Validation Helpers
│   └── functions.php         # Formatting, Sanitization & Helpers
└── database/
    └── schema.sql            # Full MySQL / MariaDB Schema File
```

---

## 🔒 Security Summary

- Prepared SQL statements for 100% of queries.
- Password hashing with `password_hash($password, PASSWORD_DEFAULT)`.
- Anti-CSRF token verification on all POST forms and AJAX endpoints.
- XSS prevention with HTML output escaping (`htmlspecialchars`).
- Robust permission checking (authors can edit/delete only their own content).
