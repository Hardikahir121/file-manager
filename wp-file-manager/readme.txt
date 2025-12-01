=== FileDB Manager Pro ===
Contributors: hardikahir  
Tags: file manager, database manager, backups, logs, smtp, admin tools  
Requires at least: 5.8  
Tested up to: 6.8  
Stable tag: 1.0.0  
Requires PHP: 7.4  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Lightweight WordPress file and database manager with backups, logs, role-based access, and optional SMTP notifications — all in one secure plugin.

== Description ==

**FileDB Manager Pro** provides an advanced yet lightweight admin file manager and database manager with detailed logs, backup/restore tools, role-based permissions, and optional SMTP notifications.  
It includes a frontend file manager shortcode for logged-in users, developer hooks, and a secure AJAX API.

### ✨ Key Features

#### 🔧 File Manager (Admin + Shortcode)

- Browse `wp-content` and upload directories.
- Perform file operations: upload, download, create, rename, move, delete.
- Grid and List view modes, sorting and search.
- Built-in code editor with syntax checking (PHP lint, JS/JSON/CSS/HTML).
- Find & Replace, Prettier formatting, SQL beautifier.
- Configurable upload limits and file extension whitelisting.

#### 🗄️ Database Manager

- Browse tables, run SQL queries (with sanitization and safety restrictions).
- Export/import tables or the full database.
- Optimize, empty, or drop tables (permission-restricted).
- Query result viewer and table quick actions.

#### 💾 Backup & Restore

- Create file + database backups under `wp-content/uploads/wpfm-backups/`.
- Restore, download, or delete backups.
- Automatic size limit checks to prevent timeouts on large sites.

#### 🧾 Logs & Notifications

- Tracks all file actions (upload, edit, delete, download) with IP and timestamp.
- Search, filter, or bulk-delete logs in the admin.
- Optional email notifications (per event).
- SMTP configuration: host, port, user, password, encryption, from name/email.

#### 🔒 Security & Permissions

- Role-based access control for File Manager & DB Manager.
- Nonce and capability checks on every action.
- Path sanitization and directory traversal protection.
- File content validation (detects malicious PHP code in uploads).
- Security headers: X-Frame-Options, X-Content-Type-Options, X-XSS-Protection.

#### 🧩 Developer Hooks

- Shortcode: `[wp_file_manager view="grid" theme="light" upload="yes"]`
- Actions:
  - `wpfm_file_uploaded`, `wpfm_file_downloaded`, `wpfm_file_edited`, `wpfm_file_deleted`
- Filters:
  - `wpfm_validate_backup_operation`, `wpfm_validate_restore_operation`
- AJAX Endpoints: `wpfm_list_files`, `wpfm_upload_files`, `wpfm_run_query`, etc.
- SMTP integration via `phpmailer_init`.

---

### 🧰 Admin Menu

Adds a top-level menu **File Manager** with subpages:

- File Manager
- Database Manager
- Backup & Restore
- Logs
- Settings

---

### ⚙️ Settings

Configurable options:

- Allowed roles for File Manager & Database Manager.
- Max upload size (MB).
- Default view, theme, and language.
- Email notifications (per event toggle).
- SMTP credentials & encryption type.
- PHP lint toggle for file editor.

Settings stored as WordPress options like:
`wpfm_max_upload`, `wpfm_theme`, `wpfm_smtp_enable`, etc.

---

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/wp-file-manager/`.
2. Activate it via the **Plugins** menu.
3. Go to **File Manager → Settings** and configure access roles, limits, and SMTP (optional).
4. Ensure the following directories exist and are writable:
   - `wp-content/uploads/wpfm-files/`
   - `wp-content/uploads/wpfm-backups/`

---

== Usage ==

- Open **File Manager** in admin to manage files.
- Use **Database Manager** for safe SQL queries and table operations.
- Manage **Backups** under **Backup/Restore**.
- Review **Logs** for file events.
- Embed frontend file manager for logged-in users:
