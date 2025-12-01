<!-- FileDB Manager Pro - GitHub README -->

<p align="center">
  <img src="https://img.shields.io/badge/WordPress-Plugin-blue.svg" alt="WordPress Plugin" />
  <img src="https://img.shields.io/badge/Version-1.0.0-success.svg" alt="Version" />
  <img src="https://img.shields.io/badge/Tested%20up%20to-6.8-brightgreen.svg" alt="WP Tested Up To" />
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb3.svg" alt="PHP Version" />
  <img src="https://img.shields.io/badge/License-GPLv2%2B-lightgrey.svg" alt="License" />
</p>

<p align="center">
  <a href="#-installation">
    <img src="https://img.shields.io/badge/%E2%AC%87%EF%B8%8F-Installation-orange.svg" alt="Installation" />
  </a>
  <a href="#-features">
    <img src="https://img.shields.io/badge/%F0%9F%94%A7-Features-blueviolet.svg" alt="Features" />
  </a>
  <a href="#-usage">
    <img src="https://img.shields.io/badge/%F0%9F%93%9D-Usage-informational.svg" alt="Usage" />
  </a>
  <a href="#-contributing">
    <img src="https://img.shields.io/badge/%F0%9F%91%8B-Contributions-Welcome-success.svg" alt="Contributing" />
  </a>
</p>

---

# FileDB Manager Pro

**Lightweight WordPress file and database manager with backups, logs, role-based access, and optional SMTP notifications — all in one secure plugin.**

> ⚠️ **Note:** This plugin is designed for self-hosted environments where you have full control over access and security. Always limit access to trusted admin users.

---

## ✨ Overview

**FileDB Manager Pro** provides:

- A powerful **file manager** for `wp-content` and upload directories  
- A convenient **database manager** for safe SQL operations  
- **Backup & restore** for files and database  
- Detailed **logs** of every critical action  
- **Role-based permissions** and hard security checks  
- Optional **SMTP notifications** for key events  

All wrapped in a clean, lightweight UI that fits naturally into the WordPress admin.

---

## 🔧 Features

### 📁 File Manager (Admin + Shortcode)

- Browse and manage files under `wp-content` and upload paths
- Perform file operations:
  - Upload, download, create, rename, move, delete
- Grid and list view modes, sorting and search
- Built-in code editor with:
  - Syntax highlighting
  - PHP lint check
  - Support for JS / JSON / CSS / HTML
- Find & Replace, formatting helpers:
  - Prettier-style formatting
  - SQL beautifier
- Configurable:
  - Max upload size
  - Allowed file extensions (whitelist)

---

### 🗄️ Database Manager

- Browse tables and view structure & data
- Run SQL queries (with sanitization and safety checks)
- Export/import:
  - Single tables
  - Full database
- Table operations (permission-protected):
  - Optimize
  - Empty
  - Drop
- Query result viewer with pagination and quick actions

---

### 💾 Backup & Restore

- Create compressed backups of:
  - Files
  - Database
  - Or both together
- Backups are stored in:
  - `wp-content/uploads/wpfm-backups/`
- Manage backups from admin:
  - Restore
  - Download
  - Delete
- Automatic size checks to help prevent:
  - Timeouts
  - Memory issues on large sites

---

### 🧾 Logs & Notifications

- Track all important actions:
  - Upload
  - Edit
  - Delete
  - Download
- Logged data includes:
  - User ID
  - IP address
  - Timestamp
  - Operation type
  - File path or table name
- Admin logs screen:
  - Filter by user, action, date
  - Search by file/table
  - Bulk delete
- Optional email notifications:
  - Enable per event type
- SMTP configuration:
  - Host, port, username, password
  - Encryption (None / SSL / TLS)
  - From name / From email

---

### 🔒 Security & Permissions

- Role-based access control:
  - Separate roles list for File Manager and DB Manager
- Nonce and capability checks on every request
- Path sanitization to avoid directory traversal
- Restrict file access to allowed roots
- File content validation:
  - Detect and block suspicious PHP code in uploads
- Optional security headers:
  - `X-Frame-Options`
  - `X-Content-Type-Options`
  - `X-XSS-Protection`

---

### 🧩 Developer Hooks & Shortcodes

**Shortcode:**

```text
[wp_file_manager view="grid" theme="light" upload="yes"]
