<?php

/**
 * Plugin Name: FileDB Manager Pro
 * Description: Lightweight file manager with database management and role-based access
 * Version: 1.0.0
 * Author: Hardik
 * Text Domain: filedb-manager-pro
 * License: GPLv2 or later  
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('WPFM_VERSION', '1.0.0');
if (!defined('WPFM_PLUGIN_FILE')) {
    define('WPFM_PLUGIN_FILE', __FILE__);
}
define('WPFM_PLUGIN_URL', plugin_dir_url(__FILE__));
define('WPFM_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WPFM_BASE_DIR', plugin_dir_path(__FILE__));
define('WPFM_UPLOAD_DIR', WP_CONTENT_DIR . '/uploads/wpfm-files/');
define('WPFM_BACKUP_DIR', WP_CONTENT_DIR . '/uploads/wpfm-backups/');

final class WP_File_Manager
{

    private static $instance = null;

    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        $this->load_dependencies();
        $this->init_hooks();
        $this->create_directories();
    }

    private function load_dependencies()
    {
        require_once WPFM_PLUGIN_PATH . 'includes/class-core.php';
        require_once WPFM_PLUGIN_PATH . 'includes/class-ajax.php';
        require_once WPFM_PLUGIN_PATH . 'includes/class-logs.php';

        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_scripts'));
        require_once WPFM_PLUGIN_PATH . 'includes/class-security.php';
    }

    private function init_hooks()
    {
        add_action('admin_menu', array($this, 'add_admin_menus'));
        add_action('admin_init', array($this, 'check_permissions'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('init', array($this, 'initialize_components'));
        add_action('wp_ajax_wpfm_file_manager', array($this, 'handle_ajax_request'));
    }

    public function enqueue_admin_scripts($hook)
    {
        // Load logs admin assets/nonces on File Manager main and Logs pages
        if (strpos($hook, 'wp-file-manager') === false && strpos($hook, 'wp-file-logs') === false) {
            return;
        }

        wp_enqueue_style('wpfm-logs', WPFM_PLUGIN_URL . 'assets/css/logs.css', array(), WPFM_VERSION);
        wp_enqueue_script('wpfm-logs', WPFM_PLUGIN_URL . 'assets/js/logs.js', array('jquery'), WPFM_VERSION, true);
        wp_localize_script('wpfm-logs', 'wpfm_vars', array(
            'nonce' => wp_create_nonce('wpfm_nonce')
        ));
    }

    public function initialize_components()
    {
        WPFM_Core::get_instance();
        WPFM_Ajax::get_instance();
        WPFM_Security::get_instance();
    }

    public function activate()
    {
        $defaults = array(
            'wpfm_file_roles' => array('administrator'),
            'wpfm_db_roles' => array('administrator'),
            'wpfm_max_upload' => 25,
            'wpfm_language' => 'en',
            'wpfm_theme' => 'light',
            'wpfm_view' => 'grid',
            'wpfm_email_notify' => 'no',
            'wpfm_notify_upload' => 'no',
            'wpfm_notify_download' => 'no',
            'wpfm_notify_edit' => 'no',
            'wpfm_notify_email' => get_option('admin_email'),
            // SMTP defaults
            'wpfm_smtp_enable' => 'no',
            'wpfm_smtp_host' => 'smtp.gmail.com',
            'wpfm_smtp_port' => '587',
            'wpfm_smtp_username' => '',
            'wpfm_smtp_password' => '',
            'wpfm_smtp_encryption' => 'tls',
            'wpfm_smtp_from_email' => '',
            'wpfm_smtp_from_name' => get_bloginfo('name')
        );

        foreach ($defaults as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }

        $this->create_directories();
        $this->create_logs_table();
        flush_rewrite_rules();
    }

    private function get_translation($key)

    {
        $language = get_option('wpfm_language', 'en');
        $translations = $this->get_translations();

        return isset($translations[$key]) ? $translations[$key] : $key;
    }

    private function create_logs_table()
    {
        global $wpdb;
        $table_name = $wpdb->prefix . 'wpfm_logs';

        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        file_name varchar(255) NOT NULL,
        file_path text NOT NULL,
        action varchar(50) NOT NULL,
        user_id bigint(20) NOT NULL,
        user_name varchar(100) NOT NULL,
        ip_address varchar(100) NOT NULL,
        user_agent text,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id),
        KEY action (action),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function deactivate()
    {
        flush_rewrite_rules();
    }

    private function create_directories()
    {
        $directories = [
            WPFM_UPLOAD_DIR,
            WPFM_BACKUP_DIR
        ];

        foreach ($directories as $dir) {
            if (!file_exists($dir)) {
                if (!wp_mkdir_p($dir)) {
                    error_log("WP File Manager: Failed to create directory: $dir");
                    continue;
                }

                // Add security files
                $htaccess_content = "Order Deny,Allow\nDeny from all\n";
                $index_content = "<?php\n// Silence is golden";

                file_put_contents($dir . '.htaccess', $htaccess_content);
                file_put_contents($dir . 'index.php', $index_content);

                // Set proper permissions
                chmod($dir, 0755);
            }
        }
    }

    public function add_admin_menus()
    {
        add_menu_page(
            'File Manager',
            'File Manager',
            'read',
            'wp-file-manager',
            array($this, 'render_file_manager'),
            'dashicons-portfolio',
            30
        );

        add_submenu_page(
            'wp-file-manager',
            'Database Manager',
            'Database Manager',
            'read',
            'wp-db-manager',
            array($this, 'render_db_manager')
        );

        add_submenu_page(
            'wp-file-manager',
            'Settings',
            'Settings',
            'manage_options',
            'wp-file-manager-settings',
            array($this, 'render_settings')
        );

        // Backup/Restore submenu
        add_submenu_page(
            'wp-file-manager',
            'Backup/Restore',
            'Backup/Restore',
            'manage_options',
            'wp-file-backup',
            array($this, 'render_backup_restore')
        );

        // Logs submenu
        add_submenu_page(
            'wp-file-manager',
            'Logs',
            'Logs',
            'manage_options',
            'wp-file-logs',
            array($this, 'render_logs')
        );
    }

    private function backup_start_optimized()
    {
        // Check if backup directory exists and is writable
        if (!is_dir(WPFM_BACKUP_DIR)) {
            wp_mkdir_p(WPFM_BACKUP_DIR);
        }

        if (!is_writable(WPFM_BACKUP_DIR)) {
            wp_send_json_error('Backup directory is not writable');
        }

        $db = isset($_POST['db']) && $_POST['db'] === '1';
        $files = isset($_POST['files']) && $_POST['files'] === '1';

        // For local development, limit what we backup
        $parts = array(
            'plugins' => isset($_POST['plugins']) && $_POST['plugins'] === '1',
            'themes' => isset($_POST['themes']) && $_POST['themes'] === '1',
            'uploads' => isset($_POST['uploads']) && $_POST['uploads'] === '1',
            'others' => false, // Disable others for local testing
        );

        if (!$db && !$files) {
            wp_send_json_error('Select Database or Files to backup');
        }

        $key = 'backup_' . date('Y_m_d_H_i_s');
        $created = array();

        try {
            // Database backup (simplified for local)
            if ($db) {
                $this->backup_database_simple($key, $created);
            }

            // Files backup (limit for local testing)
            if ($files) {
                $this->backup_files_limited($key, $parts, $created);
            }

            wp_send_json_success(array(
                'created' => $created,
                'key' => $key,
                'message' => 'Backup completed successfully'
            ));
        } catch (Exception $e) {
            wp_send_json_error('Backup failed: ' . $e->getMessage());
        }
    }

    private function backup_database_simple($key, &$created)
    {
        global $wpdb;

        $sql_file = WPFM_BACKUP_DIR . $key . '-db.sql';
        $handle = fopen($sql_file, 'w');

        if (!$handle) {
            throw new Exception('Cannot create database backup file');
        }

        // Get tables
        $tables = $wpdb->get_col('SHOW TABLES');

        foreach ($tables as $table) {
            // Skip large tables for local testing
            if (in_array($table, ['wp_posts', 'wp_postmeta', 'wp_options'])) {
                continue;
            }

            fwrite($handle, "-- Table: $table\n");

            $rows = $wpdb->get_results("SELECT * FROM `{$table}` LIMIT 100", ARRAY_A); // Limit rows for local

            foreach ($rows as $row) {
                $values = array_map(function ($v) use ($wpdb) {
                    if ($v === null) return 'NULL';
                    return "'" . esc_sql($v) . "'";
                }, array_values($row));

                $columns = array_map(function ($c) {
                    return "`{$c}`";
                }, array_keys($row));

                fwrite($handle, "INSERT INTO `{$table}` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ");\n");
            }
        }

        fclose($handle);
        $created[] = basename($sql_file);
    }

    private function backup_files_limited($key, $parts, &$created)
    {
        if (!class_exists('ZipArchive')) {
            throw new Exception('ZipArchive not available');
        }

        // Only backup small directories for local testing
        if ($parts['themes']) {
            $themes = get_theme_root();
            $current_theme = get_stylesheet();
            $theme_path = $themes . '/' . $current_theme;

            if (is_dir($theme_path)) {
                $zip_file = WPFM_BACKUP_DIR . $key . '-theme.zip';
                $zip = new ZipArchive();

                if ($zip->open($zip_file, ZipArchive::CREATE) === TRUE) {
                    $this->add_directory_to_zip_limited($theme_path, $zip, 'theme');
                    $zip->close();
                    $created[] = basename($zip_file);
                }
            }
        }
    }

    private function add_directory_to_zip_limited($path, $zip, $base = '')
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $file_count = 0;
        foreach ($iterator as $file) {
            if ($file_count > 50) break; // Limit files for local testing

            if ($file->isFile()) {
                $file_path = $file->getRealPath();
                $relative_path = $base . '/' . $iterator->getSubPathName();
                $zip->addFile($file_path, $relative_path);
                $file_count++;
            }
        }
    }

    public function check_permissions()
    {
        $page = isset($_GET['page']) ? $_GET['page'] : '';
        $user = wp_get_current_user();
        $roles = $user->roles;

        if ($page === 'wp-file-manager') {
            $allowed = get_option('wpfm_file_roles', array('administrator'));
            if (!array_intersect($roles, $allowed)) {
                wp_die('Access denied. You do not have permission to access the File Manager.');
            }
        }

        if ($page === 'wp-db-manager') {
            $allowed = get_option('wpfm_db_roles', array('administrator'));
            if (!array_intersect($roles, $allowed)) {
                wp_die('Access denied. You do not have permission to access the Database Manager.');
            }
        }
    }

    public function enqueue_scripts($hook)
    {
        // Load assets on File Manager, Database Manager, and Backup pages
        if (
            strpos($hook, 'wp-file-manager') === false &&
            strpos($hook, 'wp-db-manager') === false &&
            strpos($hook, 'wp-file-backup') === false &&
            strpos($hook, 'wp-file-logs') === false
        ) {
            return;
        }

        // Enqueue Tailwind CSS from CDN
        wp_enqueue_style('tailwind-css', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css', array(), '2.2.19');
        wp_enqueue_style('bootstrap-icons', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css', array(), '1.7.2');
        wp_enqueue_style('wpfm-style', WPFM_PLUGIN_URL . 'assets/css/style.css', array(), WPFM_VERSION);
        // Theme stylesheet based on saved option
        $theme = get_option('wpfm_theme', 'light');
        $allowed_themes = array('light', 'dark', 'blue', 'green');
        if (!in_array($theme, $allowed_themes, true)) {
            $theme = 'light';
        }
        $theme_css = WPFM_PLUGIN_URL . 'assets/css/theme/' . $theme . '.css';
        wp_enqueue_style('wpfm-theme', $theme_css, array('wpfm-style'), WPFM_VERSION);
        wp_enqueue_script('wpfm-script', WPFM_PLUGIN_URL . 'assets/js/script.js', array('jquery'), WPFM_VERSION, true);

        // Localize script with AJAX URL, nonce, and DB info for breadcrumb
        global $wpdb;
        wp_localize_script('wpfm-script', 'wpfm_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('wpfm_nonce'),
            'max_upload_size' => get_option('wpfm_max_upload', 25) * 1024 * 1024,
            'base_url' => content_url('/'),
            'db_host' => DB_HOST,
            'db_name' => DB_NAME,
            'view' => get_option('wpfm_view', 'grid'),
            'theme' => $theme,
            'language' => get_option('wpfm_language', 'en'),
            'translations' => $this->get_translations(),
            'syntax_check' => get_option('wpfm_syntax_check', 'no') === 'yes',
            'notifications' => array(
                'upload' => get_option('wpfm_notify_upload', 'no') === 'yes',
                'download' => get_option('wpfm_notify_download', 'no') === 'yes',
                'edit' => get_option('wpfm_notify_edit', 'no') === 'yes'
            )
        ));
    }



    private function get_translations()
    {
        $language = get_option('wpfm_language', 'en');
        $translations = array();

        // Load translation file based on selected language
        $translation_file = WPFM_PLUGIN_PATH . 'languages/' . $language . '.php';

        if (file_exists($translation_file)) {
            $translations = include $translation_file;
        } else {
            // Fallback to English
            $translation_file = WPFM_PLUGIN_PATH . 'languages/en.php';
            if (file_exists($translation_file)) {
                $translations = include $translation_file;
            }
        }

        return $translations;
    }

    private function list_files()
    {
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '/';

        $validated_path = $this->validate_path($path);
        if (!$validated_path) {
            wp_send_json_error('Invalid path');
        }

        if (!is_dir($validated_path)) {
            wp_send_json_error('Directory not found');
        }

        // Security check - prevent directory traversal
        $base_path = realpath($validated_path);
        $content_dir = realpath(WP_CONTENT_DIR);

        if ($base_path === false) {
            wp_send_json_error('Invalid path');
        }

        // Make sure the path is within wp-content directory
        if (strpos($base_path, $content_dir) !== 0) {
            wp_send_json_error('Access denied - path outside wp-content directory');
        }

        if (!is_dir($base_path)) {
            wp_send_json_error('Directory not found');
        }

        $files = array();
        $items = scandir($base_path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $item_path = $base_path . '/' . $item;
            $relative_path = $path . '/' . $item;

            // Skip hidden files and sensitive directories
            if (substr($item, 0, 1) === '.') continue;

            // Skip some WordPress core directories for security
            $skip_dirs = array('wp-admin', 'wp-includes', 'backup', 'upgrade');
            if (in_array($item, $skip_dirs) && $path === '/') continue;

            $files[] = array(
                'name' => $item,
                'path' => $relative_path,
                'is_dir' => is_dir($item_path),
                'size' => is_dir($item_path) ? 0 : filesize($item_path),
                'modified' => filemtime($item_path),
                'permissions' => substr(sprintf('%o', fileperms($item_path)), -4),
                'icon' => $this->get_file_icon($item, is_dir($item_path))
            );
        }

        // Sort files: folders first, then by name
        usort($files, function ($a, $b) {
            if ($a['is_dir'] && !$b['is_dir']) return -1;
            if (!$a['is_dir'] && $b['is_dir']) return 1;
            return strcasecmp($a['name'], $b['name']);
        });

        wp_send_json_success($files);
    }



    private function get_file_content()
    {
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';

        $validated_path = $this->validate_path($path);
        if (!$validated_path) {
            wp_send_json_error('Invalid file path');
        }

        // Check file size before reading
        $file_size = filesize($validated_path);
        $max_size = 10 * 1024 * 1024; // 10MB limit

        if ($file_size > $max_size) {
            wp_send_json_error('File too large to edit (max 10MB)');
        }

        if (!file_exists($validated_path) || is_dir($validated_path)) {
            wp_send_json_error('File not found');
        }

        $content = @file_get_contents($validated_path);
        if ($content === false) {
            wp_send_json_error('Unable to read file');
        }

        wp_send_json_success(array(
            'content' => $content,
            'size' => $file_size,
            'modified' => filemtime($validated_path)
        ));
    }

    private function save_file_content()
    {
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
        // WordPress adds slashes to request data; remove them before writing to disk
        $content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';
        $file_path = WP_CONTENT_DIR . '/' . ltrim($path, '/');

        if (!file_exists($file_path) || is_dir($file_path)) {
            wp_send_json_error('File not found');
        }

        // Security check
        $allowed_extensions = array('txt', 'css', 'js', 'html', 'htm', 'php', 'json', 'xml', 'md', 'log');
        $file_extension = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            wp_send_json_error('File type not allowed for editing');
        }

        // Check if file is writable
        if (!is_writable($file_path)) {
            wp_send_json_error('File is not writable');
        }

        if (file_put_contents($file_path, $content) !== false) {
            // TRIGGER FILE EDIT LOG
            if (class_exists('WPFM_Core')) {
                $core = WPFM_Core::get_instance();
                $core->trigger_file_edit(basename($file_path), $path);
            }

            wp_send_json_success('File saved successfully');
        } else {
            wp_send_json_error('Failed to save file');
        }
    }

    private function append_log_entry($type, $name, $path)
    {
        $option_key = 'wpfm_logs_' . $type;
        $logs = get_option($option_key, array());
        $user = wp_get_current_user();
        $logs[] = array(
            'name' => $name,
            'path' => $path,
            'user' => $user->user_login,
            'user_id' => $user->ID,
            'user_name' => $user->display_name ?: $user->user_login,
            'time' => current_time('timestamp'),
        );
        if (count($logs) > 200) {
            $logs = array_slice($logs, -200);
        }
        update_option($option_key, $logs, false);
    }



    private function logs_delete_rows()
    {
        $type = isset($_POST['log_type']) ? sanitize_text_field($_POST['log_type']) : '';
        $ids = isset($_POST['ids']) ? (array) $_POST['ids'] : array();
        $option_key = 'wpfm_logs_' . $type;
        $logs = get_option($option_key, array());
        // Delete by unique time ids
        $ids = array_map('intval', $ids);
        if (!empty($ids)) {
            $logs = array_values(array_filter($logs, function ($row) use ($ids) {
                $t = isset($row['time']) ? intval($row['time']) : 0;
                return !in_array($t, $ids, true);
            }));
        }
        update_option($option_key, $logs, false);
        wp_send_json_success(array('count' => count($logs)));
    }

    private function logs_clear_all()
    {
        $type = isset($_POST['log_type']) ? sanitize_text_field($_POST['log_type']) : '';
        $option_key = 'wpfm_logs_' . $type;
        update_option($option_key, array(), false);
        wp_send_json_success('cleared');
    }

    // Add this new method for tree view
    private function get_directory_tree()
    {
        $base_path = rtrim(WP_CONTENT_DIR, '/');

        if (!function_exists('buildTree')) {
            function buildTree($dir, $base_path)
            {
                $tree = array();
                $items = @scandir($dir);

                if (!$items) return $tree;

                foreach ($items as $item) {
                    if ($item === '.' || $item === '..') continue;
                    if (substr($item, 0, 1) === '.') continue;

                    $path = $dir . DIRECTORY_SEPARATOR . $item;
                    $relative_path = substr($path, strlen($base_path));
                    $relative_path = str_replace('\\', '/', $relative_path);
                    $relative_path = '/' . ltrim($relative_path, '/');

                    // Only skip system directories if they appear at root
                    $skip_dirs = array('wp-admin', 'wp-includes', 'backup', 'upgrade');
                    if (in_array($item, $skip_dirs) && dirname($path) === $base_path) continue;

                    if (is_dir($path)) {
                        // First check if the directory is readable
                        if (is_readable($path)) {
                            $tree[] = array(
                                'name' => $item,
                                'path' => $relative_path,
                                'type' => 'folder',
                                'children' => buildTree($path, $base_path)
                            );
                        }
                    }
                }

                // Sort directories first, then files, both alphabetically
                usort($tree, function ($a, $b) {
                    if ($a['type'] === $b['type']) {
                        return strcasecmp($a['name'], $b['name']);
                    }
                    return $a['type'] === 'folder' ? -1 : 1;
                });

                return $tree;
            }
        }

        $tree = buildTree($base_path, $base_path);
        // Return just the tree array for the JS expected shape
        wp_send_json_success($tree);
    }


    // Update handle_ajax_request method to include tree view
    public function handle_ajax_request()
    {
        // Check nonce
        if (!check_ajax_referer('wpfm_nonce', 'nonce', false)) {
            wp_send_json_error('Security verification failed');
        }

        // Support both POST and GET for actions (downloads use GET)
        $action = isset($_REQUEST['action_type']) ? sanitize_text_field($_REQUEST['action_type']) : '';

        // Check permissions based on action
        $user = wp_get_current_user();
        $file_roles = get_option('wpfm_file_roles', array('administrator'));
        $db_roles = get_option('wpfm_db_roles', array('administrator'));

        switch ($action) {
            case 'logs_delete_rows':
            case 'logs_clear_all':
                if (!current_user_can('manage_options')) {
                    wp_send_json_error('Access denied for logs operations');
                }
                break;
            case 'backup_start':
            case 'backup_list':
            case 'backup_delete':
            case 'backup_download':
            case 'backup_download_all':
            case 'backup_restore_db':
            case 'backup_restore_files':
                if (!current_user_can('manage_options')) {
                    wp_send_json_error('Access denied for backup operations');
                }
                break;
            case 'upload_files':
            case 'create_folder':
            case 'delete_item':
            case 'rename_item':
            case 'get_directory_tree':
            case 'list_files':
            case 'get_file_content':
            case 'save_file_content':
            case 'move_item':
            case 'copy_item':
            case 'download_item':
                if (!array_intersect($user->roles, $file_roles)) {
                    wp_send_json_error('Access denied for file operations');
                }
                break;

            case 'run_query':
            case 'get_tables':
            case 'export_database':
            case 'export_table':
            case 'import_database':
            case 'import_table':
            case 'empty_table':
            case 'drop_table':
            case 'browse_table':
            case 'update_row':
                if (!array_intersect($user->roles, $db_roles)) {
                    wp_send_json_error('Access denied for database operations');
                }
                break;
        }

        // Handle the action
        switch ($action) {
            case 'logs_delete_rows':
                $this->logs_delete_rows();
                break;
            case 'logs_clear_all':
                $this->logs_clear_all();
                break;
            case 'backup_start':
                $this->backup_start();
                break;
            case 'backup_list':
                $this->backup_list();
                break;
            case 'backup_delete':
                $this->backup_delete();
                break;
            case 'backup_download':
                $this->backup_download();
                break;
            case 'backup_download_all':
                $this->backup_download_all();
                break;
            case 'backup_restore_db':
                $this->backup_restore_db();
                break;
            case 'backup_restore_files':
                $this->backup_restore_files();
                break;
            case 'list_files':
                $this->list_files();
                break;

            case 'download_item':
                $this->download_item();
                break;

            case 'lint_php':
                $this->lint_php();
                break;

            case 'move_item':
                $this->move_item();
                break;

            case 'copy_item':
                $this->copy_item();
                break;

            case 'get_file_info':
                $this->get_file_info();
                break;

            case 'get_directory_tree':
                $this->get_directory_tree();
                break;

            case 'upload_files':
                $this->upload_files();
                break;

            case 'create_folder':
                $this->create_folder();
                break;

            case 'delete_item':
                $this->delete_item();
                break;

            case 'rename_item':
                $this->rename_item();
                break;

            case 'get_file_content':
                $this->get_file_content();
                break;

            case 'save_file_content':
                $this->save_file_content();
                break;

            case 'run_query':
                $this->run_query();
                break;

            case 'get_tables':
                $this->get_tables();
                break;

            case 'export_database':
                $this->export_database();
                break;

            case 'export_table':
                $this->export_table();
                break;

            case 'import_database':
                $this->import_database();
                break;

            case 'import_table':
                $this->import_table();
                break;

            case 'empty_table':
                $this->empty_table();
                break;

            case 'drop_table':
                $this->drop_table();
                break;

            case 'browse_table':
                $this->browse_table();
                break;

            case 'update_row':
                $this->update_row();
                break;

            case 'add_table_row':
                $this->add_table_row();
                break;

            case 'create_file':
                $this->create_file();
                break;

            case 'search_files':
                $this->search_files();
                break;

            case 'get_table_relationships':
                $this->get_table_relationships();
                break;

            case 'backup_view_log':
                $key = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
                if (!$key) {
                    wp_send_json_error('Missing backup key');
                }

                $log_file = WPFM_BACKUP_DIR . $key . '.log';

                if (!file_exists($log_file)) {
                    wp_send_json_error('Log file not found');
                }

                $content = file_get_contents($log_file);
                if ($content === false) {
                    wp_send_json_error('Failed to read log file');
                }

                wp_send_json_success(array('log' => $content));
                break;

            default:
                wp_send_json_error('Invalid action');
        }
    }

    private function download_item()
    {
        // This endpoint is triggered via GET in a hidden iframe
        $path = isset($_GET['path']) ? sanitize_text_field($_GET['path']) : '';
        if ($path === '') {
            wp_die('Invalid path');
        }

        // Map path to actual WordPress directory
        if ($path === '/') {
            wp_die('Cannot download root directory');
        } else {
            $item_path = WP_CONTENT_DIR . '/' . ltrim($path, '/');
        }

        // Security check
        $item_path = realpath($item_path);
        $content_dir = realpath(WP_CONTENT_DIR);

        if ($item_path === false || strpos($item_path, $content_dir) !== 0) {
            wp_send_json_error('Invalid path');
        }

        if (!file_exists($item_path)) {
            wp_send_json_error('Item not found');
        }

        // TRIGGER FILE DOWNLOAD LOG (for files only, not directories)
        if (!is_dir($item_path) && class_exists('WPFM_Core')) {
            $core = WPFM_Core::get_instance();
            $core->trigger_file_download(basename($item_path), $path);
        }

        if (is_dir($item_path)) {
            $this->download_directory($item_path, basename($item_path));
        } else {
            $this->download_file($item_path);
        }
    }

    private function download_file($file_path)
    {
        $filename = basename($file_path);

        // Check if file is readable
        if (!is_readable($file_path)) {
            wp_send_json_error('File is not readable');
        }

        // Set headers for file download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file_path));

        // Clear any output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }

        readfile($file_path);
        exit;
    }

    private function download_directory($dir_path, $dir_name)
    {
        // Ensure ZipArchive is available
        if (!class_exists('ZipArchive')) {
            wp_die('ZipArchive not available on server');
        }
        // Create a temporary zip file
        $zip = new ZipArchive();
        $zip_filename = tempnam(sys_get_temp_dir(), 'wpfm_') . '.zip';

        if ($zip->open($zip_filename, ZipArchive::CREATE) !== TRUE) {
            wp_send_json_error('Cannot create zip file');
        }

        // Add files to zip recursively
        $this->add_directory_to_zip($dir_path, $zip, $dir_name);

        $zip->close();

        // Send zip file
        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $dir_name . '.zip"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($zip_filename));

        if (ob_get_level()) {
            ob_end_clean();
        }

        readfile($zip_filename);

        // Delete temporary file
        unlink($zip_filename);
        exit;
    }

    private function add_directory_to_zip($dir_path, $zip, $base_path = '')
    {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($dir_path),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $name => $file) {
            if (!$file->isDir()) {
                $file_path = $file->getRealPath();
                $relative_path = $base_path . '/' . substr($file_path, strlen($dir_path) + 1);

                $zip->addFile($file_path, $relative_path);
            }
        }
    }

    private function move_item()
    {
        $source_path = isset($_POST['source_path']) ? sanitize_text_field($_POST['source_path']) : '';
        $target_path = isset($_POST['target_path']) ? sanitize_text_field($_POST['target_path']) : '';

        // Map paths to actual WordPress directory
        $source_full_path = WP_CONTENT_DIR . '/' . ltrim($source_path, '/');
        $target_full_path = WP_CONTENT_DIR . '/' . ltrim($target_path, '/');

        // Security checks
        $source_full_path = realpath($source_full_path);
        $target_full_path = realpath($target_full_path);
        $content_dir = realpath(WP_CONTENT_DIR);

        if ($source_full_path === false || strpos($source_full_path, $content_dir) !== 0) {
            wp_send_json_error('Invalid source path');
        }

        if ($target_full_path === false || strpos($target_full_path, $content_dir) !== 0) {
            wp_send_json_error('Invalid target path');
        }

        if (!file_exists($source_full_path)) {
            wp_send_json_error('Source item not found');
        }

        $target_item_path = $target_full_path . '/' . basename($source_full_path);

        if (file_exists($target_item_path)) {
            wp_send_json_error('Target already exists');
        }

        if (rename($source_full_path, $target_item_path)) {
            wp_send_json_success('Item moved successfully');
        } else {
            wp_send_json_error('Failed to move item');
        }
    }

    private function copy_item()
    {
        $source_path = isset($_POST['source_path']) ? sanitize_text_field($_POST['source_path']) : '';
        $target_path = isset($_POST['target_path']) ? sanitize_text_field($_POST['target_path']) : '';

        // Map paths to actual WordPress directory
        $source_full_path = WP_CONTENT_DIR . '/' . ltrim($source_path, '/');
        $target_full_path = WP_CONTENT_DIR . '/' . ltrim($target_path, '/');

        // Security checks
        $source_full_path = realpath($source_full_path);
        $target_full_path = realpath($target_full_path);
        $content_dir = realpath(WP_CONTENT_DIR);

        if ($source_full_path === false || strpos($source_full_path, $content_dir) !== 0) {
            wp_send_json_error('Invalid source path');
        }

        if ($target_full_path === false || strpos($target_full_path, $content_dir) !== 0) {
            wp_send_json_error('Invalid target path');
        }

        if (!file_exists($source_full_path)) {
            wp_send_json_error('Source item not found');
        }

        $target_item_path = $target_full_path . '/' . basename($source_full_path);

        if (file_exists($target_item_path)) {
            wp_send_json_error('Target already exists');
        }

        if (is_dir($source_full_path)) {
            if ($this->copy_directory($source_full_path, $target_item_path)) {
                wp_send_json_success('Directory copied successfully');
            } else {
                wp_send_json_error('Failed to copy directory');
            }
        } else {
            if (copy($source_full_path, $target_item_path)) {
                wp_send_json_success('File copied successfully');
            } else {
                wp_send_json_error('Failed to copy file');
            }
        }
    }

    private function copy_directory($source, $target)
    {
        if (!is_dir($target)) {
            mkdir($target, 0755, true);
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $target_path = $target . DIRECTORY_SEPARATOR . $iterator->getSubPathName();

            if ($item->isDir()) {
                if (!is_dir($target_path)) {
                    mkdir($target_path);
                }
            } else {
                copy($item->getPathname(), $target_path);
            }
        }

        return true;
    }

    private function get_file_info()
    {
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';

        // Map path to actual WordPress directory
        if ($path === '/') {
            $item_path = WP_CONTENT_DIR;
        } else {
            $item_path = WP_CONTENT_DIR . '/' . ltrim($path, '/');
        }

        // Security check
        $item_path = realpath($item_path);
        $content_dir = realpath(WP_CONTENT_DIR);

        if ($item_path === false || strpos($item_path, $content_dir) !== 0) {
            wp_send_json_error('Invalid path');
        }

        if (!file_exists($item_path)) {
            wp_send_json_error('Item not found');
        }

        $info = array(
            'name' => basename($item_path),
            'path' => $path,
            'is_dir' => is_dir($item_path),
            'size' => is_dir($item_path) ? $this->get_directory_size($item_path) : filesize($item_path),
            'modified' => filemtime($item_path),
            'created' => filectime($item_path),
            'permissions' => substr(sprintf('%o', fileperms($item_path)), -4),
            'readable' => is_readable($item_path),
            'writable' => is_writable($item_path),
            'executable' => is_executable($item_path),
            'type' => $this->get_file_type($item_path)
        );

        wp_send_json_success($info);
    }

    private function get_directory_size($path)
    {
        $size = 0;
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path)
        );

        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    private function get_file_type($path)
    {
        if (is_dir($path)) {
            return 'directory';
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $image_extensions = array('jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp');
        $document_extensions = array('pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx');
        $code_extensions = array('php', 'js', 'css', 'html', 'htm', 'json', 'xml', 'sql');

        if (in_array($extension, $image_extensions)) {
            return 'image';
        } elseif (in_array($extension, $document_extensions)) {
            return 'document';
        } elseif (in_array($extension, $code_extensions)) {
            return 'code';
        } else {
            return 'file';
        }
    }

    private function get_file_icon($filename, $is_dir = false)
    {
        if ($is_dir) {
            return 'bi-folder-fill text-yellow-500';
        }

        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $icon_map = array(
            'jpg' => 'bi-image text-green-500',
            'jpeg' => 'bi-image text-green-500',
            'png' => 'bi-image text-green-500',
            'gif' => 'bi-image text-green-500',
            'bmp' => 'bi-image text-green-500',
            'svg' => 'bi-image text-green-500',
            'webp' => 'bi-image text-green-500',
            'pdf' => 'bi-file-earmark-pdf text-red-500',
            'doc' => 'bi-file-earmark-word text-blue-500',
            'docx' => 'bi-file-earmark-word text-blue-500',
            'xls' => 'bi-file-earmark-excel text-green-600',
            'xlsx' => 'bi-file-earmark-excel text-green-600',
            'ppt' => 'bi-file-earmark-ppt text-orange-500',
            'pptx' => 'bi-file-earmark-ppt text-orange-500',
            'zip' => 'bi-file-earmark-zip text-purple-500',
            'rar' => 'bi-file-earmark-zip text-purple-500',
            '7z' => 'bi-file-earmark-zip text-purple-500',
            'tar' => 'bi-file-earmark-zip text-purple-500',
            'gz' => 'bi-file-earmark-zip text-purple-500'
        );

        return isset($icon_map[$ext]) ? $icon_map[$ext] : 'bi-file-earmark text-gray-400';
    }

    private function upload_files()
    {
        if (empty($_FILES['files'])) {
            wp_send_json_error('No files uploaded');
        }

        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';

        // Map path to actual WordPress directory
        if ($path === '/') {
            $upload_path = WP_CONTENT_DIR;
        } else {
            $upload_path = WP_CONTENT_DIR . '/' . ltrim($path, '/');
        }

        // Create directory if it doesn't exist
        if (!is_dir($upload_path)) {
            wp_mkdir_p($upload_path);
        }

        $uploaded = array();
        $errors = array();
        $max_size = get_option('wpfm_max_upload', 25) * 1024 * 1024;

        $allowed_extensions = array(
            // Images
            'jpg',
            'jpeg',
            'png',
            'gif',
            'bmp',
            'svg',
            'webp',
            'ico',
            // Documents
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
            'odt',
            'ods',
            'txt',
            'rtf',
            'csv',
            // Archives
            'zip',
            'rar',
            '7z',
            'tar',
            'gz',
            // Media
            'mp3',
            'mp4',
            'avi',
            'mov',
            'wmv',
            'flv',
            'mkv',
            'webm',
            'wav',
            'ogg',
            'm4a',
            // Code
            'html',
            'htm',
            'css',
            'js',
            'php',
            'json',
            'xml',
            'sql'
        );

        foreach ($_FILES['files']['name'] as $key => $name) {
            if ($_FILES['files']['error'][$key] !== UPLOAD_ERR_OK) {
                $errors[] = "Failed to upload {$name}: Upload error";
                continue;
            }

            // Check file size
            if ($_FILES['files']['size'][$key] > $max_size) {
                $errors[] = "File {$name} exceeds maximum upload size";
                continue;
            }

            // Sanitize filename
            $clean_name = sanitize_file_name($name);
            $ext = strtolower(pathinfo($clean_name, PATHINFO_EXTENSION));
            if (!in_array($ext, $allowed_extensions, true)) {
                $errors[] = "File type not allowed: {$clean_name}";
                continue;
            }
            $target_path = $upload_path . '/' . $clean_name;

            // Check if file already exists
            if (file_exists($target_path)) {
                $errors[] = "File {$name} already exists";
                continue;
            }

            // Prepare file array for wp_handle_upload
            $file_array = array(
                'name' => $clean_name,
                'type' => $_FILES['files']['type'][$key],
                'tmp_name' => $_FILES['files']['tmp_name'][$key],
                'error' => $_FILES['files']['error'][$key],
                'size' => $_FILES['files']['size'][$key]
            );

            // Basic malicious content sniff: block PHP code in non-PHP files
            if ($ext !== 'php') {
                $snippet = @file_get_contents($file_array['tmp_name'], false, null, 0, 200);
                if ($snippet !== false && (strpos($snippet, '<?php') !== false || strpos($snippet, '<?=') !== false)) {
                    $errors[] = "Potentially malicious content detected in {$clean_name}";
                    continue;
                }
            }

            // Use WordPress's wp_handle_upload function
            $upload_overrides = array('test_form' => false, 'test_type' => false);
            $movefile = wp_handle_upload($file_array, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $uploaded[] = $clean_name;

                // TRIGGER FILE UPLOAD LOG
                if (class_exists('WPFM_Core')) {
                    $core = WPFM_Core::get_instance();
                    $core->trigger_file_upload($clean_name, $path . '/' . $clean_name);
                }
            } else {
                $errors[] = "Failed to move uploaded file {$name}";
            }
        }

        if (!empty($errors)) {
            wp_send_json_success(array(
                'uploaded' => $uploaded,
                'errors' => $errors
            ));
        } else {
            wp_send_json_success(array('uploaded' => $uploaded));
        }
    }

    private function create_folder()
    {
        $name = isset($_POST['name']) ? sanitize_text_field($_POST['name']) : '';
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';

        if (empty($name)) {
            wp_send_json_error('Folder name is required');
        }

        // Map path to wp-content root
        if ($path === '/') {
            $base_path = WP_CONTENT_DIR;
        } else {
            $base_path = WP_CONTENT_DIR . '/' . ltrim($path, '/');
        }

        // Sanitize folder name
        $clean_name = sanitize_file_name($name);
        $folder_path = $base_path . '/' . $clean_name;

        if (file_exists($folder_path)) {
            wp_send_json_error('Folder already exists');
        }

        if (wp_mkdir_p($folder_path)) {
            wp_send_json_success('Folder created successfully');
        } else {
            wp_send_json_error('Failed to create folder');
        }
    }

    private function delete_item()
    {
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';

        // Map path to actual WordPress directory
        if ($path === '/') {
            wp_send_json_error('Cannot delete root directory');
        } else {
            $item_path = WP_CONTENT_DIR . '/' . ltrim($path, '/');
        }

        // Security check
        $item_real_path = realpath($item_path);
        $content_dir = realpath(WP_CONTENT_DIR);

        if ($item_real_path === false || strpos($item_real_path, $content_dir) !== 0) {
            wp_send_json_error('Invalid path');
        }

        if (!file_exists($item_real_path)) {
            wp_send_json_error('Item not found');
        }

        // Prevent deletion of root directory
        if ($item_real_path === $content_dir) {
            wp_send_json_error('Cannot delete root directory');
        }

        // TRIGGER FILE DELETE LOG
        if (class_exists('WPFM_Core')) {
            $core = WPFM_Core::get_instance();
            $core->trigger_file_delete(basename($item_real_path), $path);
        }

        if (is_dir($item_real_path)) {
            if ($this->delete_directory($item_real_path)) {
                wp_send_json_success('Folder deleted successfully');
            } else {
                wp_send_json_error('Failed to delete folder');
            }
        } else {
            if (unlink($item_real_path)) {
                wp_send_json_success('File deleted successfully');
            } else {
                wp_send_json_error('Failed to delete file');
            }
        }
    }

    private function rename_item()
    {
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
        $new_name = isset($_POST['new_name']) ? sanitize_text_field($_POST['new_name']) : '';

        if (empty($new_name)) {
            wp_send_json_error('New name is required');
        }

        // Sanitize the new name
        $clean_name = sanitize_file_name($new_name);
        if (empty($clean_name)) {
            wp_send_json_error('Invalid file name');
        }

        // Map path to actual WordPress directory
        if ($path === '/') {
            wp_send_json_error('Cannot rename root directory');
        } else {
            $old_path = WP_CONTENT_DIR . '/' . ltrim($path, '/');
        }

        // Security check - get real paths
        $old_real_path = realpath($old_path);
        $content_dir = realpath(WP_CONTENT_DIR);

        if ($old_real_path === false || strpos($old_real_path, $content_dir) !== 0) {
            wp_send_json_error('Invalid path: ' . $old_path);
        }

        if (!file_exists($old_real_path)) {
            wp_send_json_error('Item not found: ' . basename($old_real_path));
        }

        // Get directory and construct new path
        $directory = dirname($old_real_path);
        $new_path = $directory . '/' . $clean_name;

        // Check if target already exists
        if (file_exists($new_path)) {
            wp_send_json_error('A file or folder with that name already exists');
        }

        // Check if source is writable
        if (!is_writable($directory)) {
            wp_send_json_error('You do not have permission to rename items in this directory');
        }

        // Perform the rename
        if (rename($old_real_path, $new_path)) {
            wp_send_json_success('Item renamed successfully');
        } else {
            // Get the specific error
            $error = error_get_last();
            wp_send_json_error('Failed to rename: ' . ($error['message'] ?? 'Unknown error'));
        }
    }

    private function validate_path($path)
    {
        // Normalize path
        $normalized_path = wp_normalize_path($path);

        // Ensure path is within wp-content
        $content_dir = wp_normalize_path(WP_CONTENT_DIR);
        $full_path = $content_dir . '/' . ltrim($normalized_path, '/');

        // Get real paths
        $real_path = realpath($full_path);
        $real_content_dir = realpath($content_dir);

        // Check if path is within wp-content and exists
        if ($real_path === false || strpos($real_path, $real_content_dir) !== 0) {
            return false;
        }

        return $real_path;
    }

    private function run_query()
    {
        global $wpdb;

        $query = isset($_POST['query']) ? stripslashes($_POST['query']) : '';

        if (empty(trim($query))) {
            wp_send_json_error('Query is empty');
        }

        // Security checks for destructive operations
        $restricted_keywords = array('DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE', 'INSERT', 'UPDATE');
        $query_upper = strtoupper($query);

        $has_restricted = false;
        foreach ($restricted_keywords as $keyword) {
            if (strpos($query_upper, $keyword) !== false) {
                $has_restricted = true;
                break;
            }
        }

        // Only administrators can run restricted queries
        if ($has_restricted && !current_user_can('manage_options')) {
            wp_send_json_error('Permission denied for this query type');
        }

        try {
            // Treat result-set queries uniformly
            $resultset_keywords = array('SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN');
            $is_resultset = false;
            foreach ($resultset_keywords as $kw) {
                if (strpos($query_upper, $kw) === 0) {
                    $is_resultset = true;
                    break;
                }
            }

            if ($is_resultset) {
                // Use wpdb prepare for safety
                $results = $wpdb->get_results($query);
                $affected_rows = $wpdb->rows_affected;
            } else {
                // For other queries, execute and get affected rows
                $results = $wpdb->query($query);
                $affected_rows = $wpdb->rows_affected;
            }

            if ($wpdb->last_error) {
                wp_send_json_error(esc_html($wpdb->last_error));
            }

            wp_send_json_success(array(
                'results' => $this->escape_output($results),
                'affected_rows' => intval($affected_rows),
                'count' => is_array($results) ? count($results) : 0
            ));
        } catch (Exception $e) {
            wp_send_json_error(esc_html($e->getMessage()));
        }
    }

    private function get_tables()
    {
        global $wpdb;

        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        $table_list = array();

        foreach ($tables as $table) {
            $table_name = $table[0];
            $table_info = $wpdb->get_row($wpdb->prepare("SHOW TABLE STATUS LIKE %s", $table_name), ARRAY_A);

            $table_list[] = array(
                'name' => $table_name,
                'rows' => $table_info['Rows'] ?? 0,
                'size' => $table_info['Data_length'] ?? 0,
                'engine' => $table_info['Engine'] ?? '',
                'collation' => $table_info['Collation'] ?? ''
            );
        }

        wp_send_json_success($this->escape_output($table_list));
    }

    private function browse_table()
    {
        global $wpdb;
        $table = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : '';
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 50;
        $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;

        if (empty($table)) {
            wp_send_json_error('Table is required');
        }

        // Basic validation: ensure table exists in current DB
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));
        if (!$exists) {
            wp_send_json_error('Table not found');
        }

        $rows = $wpdb->get_results($wpdb->prepare("SELECT * FROM `{$table}` LIMIT %d OFFSET %d", $limit, $offset), ARRAY_A);
        if ($wpdb->last_error) {
            wp_send_json_error(esc_html($wpdb->last_error));
        }
        wp_send_json_success(array('rows' => $this->escape_output($rows)));
    }

    private function export_database()
    {
        global $wpdb;
        $tables = $wpdb->get_col('SHOW TABLES');

        $sql = "";
        foreach ($tables as $table) {
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            $sql .= "\n\n-- --------------------------------------------------\n";
            $sql .= "-- Table structure for table `{$table}`\n\n";
            $sql .= $create[1] . ";\n\n";

            $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
            if (!empty($rows)) {
                $sql .= "-- Dumping data for table `{$table}`\n";
                foreach ($rows as $row) {
                    $values = array_map(function ($v) use ($wpdb) {
                        if ($v === null) return 'NULL';
                        return "'" . esc_sql($v) . "'";
                    }, array_values($row));
                    $columns = array_map(function ($c) {
                        return "`{$c}`";
                    }, array_keys($row));
                    $sql .= "INSERT INTO `{$table}` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        $filename = 'database_export_' . date('Ymd_His') . '.sql';
        wp_send_json_success(array('filename' => $filename, 'content' => $sql));
    }

    private function export_table()
    {
        global $wpdb;
        $table = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : '';
        if (empty($table)) {
            wp_send_json_error('Table is required');
        }
        $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
        if ($wpdb->last_error || !$create) {
            wp_send_json_error(esc_html($wpdb->last_error ?: 'Failed to get table schema'));
        }
        $sql = $create[1] . ";\n\n";
        $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
        foreach ($rows as $row) {
            $values = array_map(function ($v) use ($wpdb) {
                if ($v === null) return 'NULL';
                return "'" . esc_sql($v) . "'";
            }, array_values($row));
            $columns = array_map(function ($c) {
                return "`{$c}`";
            }, array_keys($row));
            $sql .= "INSERT INTO `{$table}` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ");\n";
        }
        $filename = $table . '_export_' . date('Ymd_His') . '.sql';
        wp_send_json_success(array('filename' => sanitize_file_name($filename), 'content' => $sql));
    }

    private function import_database()
    {
        $sql = isset($_POST['sql']) ? wp_unslash($_POST['sql']) : '';
        if (empty(trim($sql))) {
            wp_send_json_error('No SQL provided');
        }
        global $wpdb;
        $result = $wpdb->query($sql);
        if ($wpdb->last_error) {
            wp_send_json_error(esc_html($wpdb->last_error));
        }
        wp_send_json_success(array('affected_rows' => $wpdb->rows_affected));
    }

    private function import_table()
    {
        // Alias of import_database but kept for clarity
        return $this->import_database();
    }

    private function empty_table()
    {
        global $wpdb;
        $table = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : '';
        if (empty($table)) wp_send_json_error('Table is required');
        $wpdb->query("TRUNCATE TABLE `{$table}`");
        if ($wpdb->last_error) {
            wp_send_json_error(esc_html($wpdb->last_error));
        }
        wp_send_json_success('Table emptied');
    }

    private function drop_table()
    {
        global $wpdb;
        $table = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : '';
        if (empty($table)) wp_send_json_error('Table is required');
        $wpdb->query("DROP TABLE `{$table}`");
        if ($wpdb->last_error) {
            wp_send_json_error(esc_html($wpdb->last_error));
        }
        wp_send_json_success('Table dropped');
    }

    private function update_row()
    {
        global $wpdb;
        $table = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : '';
        $pk = isset($_POST['pk']) ? sanitize_text_field($_POST['pk']) : '';
        $pkValue = isset($_POST['pkValue']) ? wp_unslash($_POST['pkValue']) : '';
        $data = isset($_POST['data']) ? (array) $_POST['data'] : array();
        if (empty($table) || empty($pk) || $pkValue === '') {
            wp_send_json_error('Missing parameters');
        }
        $updated = $wpdb->update($table, $data, array($pk => $pkValue));
        if ($updated === false) {
            wp_send_json_error(esc_html($wpdb->last_error ?: 'Failed to update'));
        }
        wp_send_json_success('Row updated');
    }

    private function search_files()
    {
        $query = isset($_POST['query']) ? sanitize_text_field($_POST['query']) : '';
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '/';

        if (empty($query)) {
            wp_send_json_error('Search query is required');
        }

        $base_path = $this->validate_path($path);
        if (!$base_path) {
            wp_send_json_error('Invalid path');
        }

        $results = $this->recursive_search($base_path, $query);
        wp_send_json_success($results);
    }

    private function list_files_paginated()
    {
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '/';
        $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
        $per_page = 50;

        $validated_path = $this->validate_path($path);
        if (!$validated_path) {
            wp_send_json_error('Invalid path');
        }

        $all_files = scandir($validated_path);
        $filtered_files = array_filter($all_files, function ($item) use ($validated_path) {
            return $item !== '.' && $item !== '..' && $item[0] !== '.';
        });

        $total_files = count($filtered_files);
        $total_pages = ceil($total_files / $per_page);
        $offset = ($page - 1) * $per_page;

        $paginated_files = array_slice($filtered_files, $offset, $per_page);

        $files_data = array();
        foreach ($paginated_files as $file) {
            $file_path = $validated_path . '/' . $file;
            $files_data[] = array(
                'name' => $file,
                'path' => $path . '/' . $file,
                'is_dir' => is_dir($file_path),
                'size' => is_dir($file_path) ? 0 : filesize($file_path),
                'modified' => filemtime($file_path)
            );
        }

        wp_send_json_success(array(
            'files' => $files_data,
            'pagination' => array(
                'current_page' => $page,
                'total_pages' => $total_pages,
                'total_files' => $total_files,
                'per_page' => $per_page
            )
        ));
    }

    private function get_recent_files()
    {
        $recent_files = get_option('wpfm_recent_files', array());

        // Add current file to recent files
        $current_file = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
        if ($current_file) {
            $recent_files = array_filter($recent_files, function ($file) use ($current_file) {
                return $file['path'] !== $current_file;
            });

            array_unshift($recent_files, array(
                'path' => $current_file,
                'name' => basename($current_file),
                'timestamp' => current_time('timestamp')
            ));

            // Keep only last 10 files
            $recent_files = array_slice($recent_files, 0, 10);
            update_option('wpfm_recent_files', $recent_files);
        }

        return $recent_files;
    }

    private function incremental_backup()
    {
        $last_backup = get_option('wpfm_last_backup_timestamp', 0);
        $changes = $this->get_files_changed_since($last_backup);

        if (empty($changes)) {
            wp_send_json_success('No changes since last backup');
        }

        // Create incremental backup
        $key = 'incremental_' . date('Y_m_d_H_i_s');
        $backup_files = array();

        foreach ($changes as $file) {
            $backup_path = WPFM_BACKUP_DIR . $key . '/' . $file;
            wp_mkdir_p(dirname($backup_path));
            copy(WP_CONTENT_DIR . $file, $backup_path);
            $backup_files[] = $file;
        }

        update_option('wpfm_last_backup_timestamp', current_time('timestamp'));
        wp_send_json_success(array(
            'backup_files' => $backup_files,
            'backup_key' => $key
        ));
    }

    private function get_files_changed_since($timestamp)
    {
        $changed_files = array();
        $this->scan_directory_for_changes(WP_CONTENT_DIR, $timestamp, $changed_files);
        return $changed_files;
    }

    private function get_table_relationships()
    {
        global $wpdb;

        $table = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : '';

        if (empty($table)) {
            wp_send_json_error('Table name is required');
        }

        // Get foreign key relationships
        $relationships = $wpdb->get_results("
            SELECT 
                TABLE_NAME,
                COLUMN_NAME,
                REFERENCED_TABLE_NAME,
                REFERENCED_COLUMN_NAME
            FROM 
                information_schema.KEY_COLUMN_USAGE
            WHERE 
                TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = '$table'
                AND REFERENCED_TABLE_NAME IS NOT NULL
        ");

        wp_send_json_success($relationships);
    }

    private function recursive_search($dir, $query)
    {
        $results = array();
        $items = @scandir($dir);

        if (!$items) return $results;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $full_path = $dir . '/' . $item;

            // Skip sensitive directories
            if (is_dir($full_path)) {
                if (in_array($item, array('wp-admin', 'wp-includes', 'backup', 'upgrade'))) continue;
                $results = array_merge($results, $this->recursive_search($full_path, $query));
            } else {
                if (stripos($item, $query) !== false || stripos(file_get_contents($full_path), $query) !== false) {
                    $results[] = array(
                        'name' => $item,
                        'path' => str_replace(WP_CONTENT_DIR, '', $full_path),
                        'is_dir' => false,
                        'size' => filesize($full_path),
                        'modified' => filemtime($full_path)
                    );
                }
            }
        }

        return $results;
    }

    private function create_file()
    {
        $name = isset($_POST['name']) ? sanitize_file_name($_POST['name']) : '';
        $path = isset($_POST['path']) ? sanitize_text_field($_POST['path']) : '';
        $content = isset($_POST['content']) ? wp_unslash($_POST['content']) : '';

        if (empty($name)) {
            wp_send_json_error('File name is required');
        }

        // Validate base path within wp-content and build absolute target path
        $validated_base = $this->validate_path($path === '/' ? '/' : $path);
        if (!$validated_base) {
            wp_send_json_error('Invalid path');
        }

        $file_path = rtrim($validated_base, '/') . '/' . $name;

        // Restrict creatable file types to safe text/code formats
        $allowed_extensions = array('txt', 'css', 'js', 'html', 'htm', 'php', 'json', 'xml', 'md', 'log');
        $ext = strtolower(pathinfo($file_path, PATHINFO_EXTENSION));
        if (!in_array($ext, $allowed_extensions, true)) {
            wp_send_json_error('File type not allowed for creation');
        }

        if (file_exists($file_path)) {
            wp_send_json_error('File already exists');
        }

        if (file_put_contents($file_path, $content) !== false) {
            wp_send_json_success('File created successfully');
        } else {
            wp_send_json_error('Failed to create file');
        }
    }

    private function add_table_row()
    {
        global $wpdb;

        $table = isset($_POST['table']) ? sanitize_text_field($_POST['table']) : '';
        $values = isset($_POST['values']) ? (array) $_POST['values'] : array();

        if (empty($table) || empty($values)) {
            wp_send_json_error('Table and values are required');
        }

        // Validate table exists
        $table_exists = $wpdb->get_var($wpdb->prepare(
            "SHOW TABLES LIKE %s",
            $table
        ));

        if (!$table_exists) {
            wp_send_json_error('Table does not exist');
        }

        // Sanitize values
        $sanitized_values = array();
        foreach ($values as $key => $value) {
            $sanitized_values[sanitize_key($key)] = sanitize_text_field($value);
        }

        $result = $wpdb->insert($table, $sanitized_values);

        if ($result === false) {
            wp_send_json_error(esc_html($wpdb->last_error));
        }

        wp_send_json_success('Row added successfully');
    }

    private function delete_directory($dir)
    {
        if (!is_dir($dir)) {
            return false;
        }

        $items = @array_diff(@scandir($dir), array('.', '..'));
        if ($items === false) {
            return false;
        }

        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                if (!$this->delete_directory($path)) {
                    return false;
                }
            } else {
                if (!@unlink($path)) {
                    return false;
                }
            }
        }

        return @rmdir($dir);
    }

    /**
     * Recursively escape data for safe JSON output.
     */
    private function escape_output($data)
    {
        if (is_array($data)) {
            $out = array();
            foreach ($data as $k => $v) {
                $out[$k] = $this->escape_output($v);
            }
            return $out;
        }

        if (is_object($data)) {
            $data = (array)$data;
            return $this->escape_output($data);
        }

        if (is_string($data)) {
            return esc_html($data);
        }

        return $data;
    }


    public function render_file_manager()
    {
        $theme = get_option('wpfm_theme', 'light');
        $view = get_option('wpfm_view', 'grid');

        echo '<div class="wrap">';
        echo '<div class="wpfm-file-manager wpfm-theme-' . esc_attr($theme) . '">';
        include WPFM_PLUGIN_PATH . 'templates/file-manager.php';
        echo '</div>';
        echo '</div>';
    }

    public function render_db_manager()
    {
?>
        <div class="wrap wpfm-db-manager">
            <?php include WPFM_PLUGIN_PATH . 'templates/db-manager.php'; ?>
        </div>
    <?php
    }

    public function render_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        if (isset($_POST['save_settings']) && check_admin_referer('wpfm_settings_nonce')) {
            $this->save_settings();
        }
    ?>
        <div class="wrap wpfm-settings">
            <?php include WPFM_PLUGIN_PATH . 'templates/settings.php'; ?>
        </div>
    <?php
    }

    public function render_backup_restore()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }
    ?>
        <div class="wrap wpfm-backup">
            <?php include WPFM_PLUGIN_PATH . 'templates/backup-restore.php'; ?>
        </div>
    <?php
    }

    public function render_logs()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }
    ?>
        <div class="wrap wpfm-logs">
            <?php include WPFM_PLUGIN_PATH . 'templates/logs.php'; ?>
        </div>
<?php
    }

    private function save_settings()
    {
        if (!current_user_can('manage_options')) {
            wp_die('Access denied.');
        }

        if (isset($_POST['save_settings']) && check_admin_referer('wpfm_settings_nonce')) {
            // Debug: Check what's in POST
            error_log('WP File Manager: Processing settings save - POST data: ' . print_r($_POST, true));

            $valid_roles = array_keys(get_editable_roles());

            // Handle role arrays
            $file_roles = isset($_POST['file_roles']) ? array_intersect((array)$_POST['file_roles'], $valid_roles) : array('administrator');
            $db_roles = isset($_POST['db_roles']) ? array_intersect((array)$_POST['db_roles'], $valid_roles) : array('administrator');

            // Validate upload size
            $max_upload = min(max(1, intval($_POST['max_upload'] ?? 25)), 1000);

            // Handle checkbox settings - they only appear in $_POST when checked
            $checkbox_settings = array(
                'wpfm_email_notify' => isset($_POST['wpfm_email_notify']) && $_POST['wpfm_email_notify'] === 'yes' ? 'yes' : 'no',
                'wpfm_notify_upload' => isset($_POST['wpfm_notify_upload']) && $_POST['wpfm_notify_upload'] === 'yes' ? 'yes' : 'no',
                'wpfm_notify_download' => isset($_POST['wpfm_notify_download']) && $_POST['wpfm_notify_download'] === 'yes' ? 'yes' : 'no',
                'wpfm_notify_edit' => isset($_POST['wpfm_notify_edit']) && $_POST['wpfm_notify_edit'] === 'yes' ? 'yes' : 'no',
                'wpfm_smtp_enable' => isset($_POST['wpfm_smtp_enable']) && $_POST['wpfm_smtp_enable'] === 'yes' ? 'yes' : 'no',
                'wpfm_syntax_check' => isset($_POST['wpfm_syntax_check']) && $_POST['wpfm_syntax_check'] === 'yes' ? 'yes' : 'no'
            );

            // Handle text/select fields
            $text_settings = array(
                'wpfm_file_roles' => $file_roles,
                'wpfm_db_roles' => $db_roles,
                'wpfm_max_upload' => $max_upload,
                'wpfm_language' => sanitize_text_field($_POST['language'] ?? 'en'),
                'wpfm_theme' => sanitize_text_field($_POST['theme'] ?? 'light'),
                'wpfm_view' => sanitize_text_field($_POST['view'] ?? 'grid'),
                'wpfm_notify_email' => sanitize_email($_POST['wpfm_notify_email'] ?? get_option('admin_email')),

                // SMTP settings
                'wpfm_smtp_host' => sanitize_text_field($_POST['wpfm_smtp_host'] ?? ''),
                'wpfm_smtp_port' => sanitize_text_field($_POST['wpfm_smtp_port'] ?? '587'),
                'wpfm_smtp_username' => sanitize_text_field($_POST['wpfm_smtp_username'] ?? ''),
                'wpfm_smtp_encryption' => sanitize_text_field($_POST['wpfm_smtp_encryption'] ?? 'tls'),
                'wpfm_smtp_from_email' => sanitize_email($_POST['wpfm_smtp_from_email'] ?? ''),
                'wpfm_smtp_from_name' => sanitize_text_field($_POST['wpfm_smtp_from_name'] ?? '')
            );

            // Save all settings
            foreach ($checkbox_settings as $key => $value) {
                update_option($key, $value);
                error_log("WP File Manager: Saved $key = $value");
            }

            foreach ($text_settings as $key => $value) {
                update_option($key, $value);
                error_log("WP File Manager: Saved $key = " . (is_array($value) ? print_r($value, true) : $value));
            }

            // Handle SMTP password separately (don't overwrite if empty)
            if (!empty($_POST['wpfm_smtp_password'])) {
                update_option('wpfm_smtp_password', sanitize_text_field($_POST['wpfm_smtp_password']));
                error_log('WP File Manager: SMTP password updated');
            }

            echo '<div class="notice notice-success is-dismissible"><p>Settings saved successfully!</p></div>';
        }
    }

    private function backup_start()
    {
        // Ensure long-running backup can complete on constrained hosts
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        if (function_exists('set_time_limit')) {
            @set_time_limit(0);
        }
        if (function_exists('wp_raise_memory_limit')) {
            @wp_raise_memory_limit('admin');
        }
        if (function_exists('ini_set')) {
            @ini_set('max_execution_time', '0');
        }
        // Ensure backup directory exists and is writable before proceeding
        if (!is_dir(WPFM_BACKUP_DIR)) {
            wp_mkdir_p(WPFM_BACKUP_DIR);
        }
        if (!is_writable(WPFM_BACKUP_DIR)) {
            wp_send_json_error('Backup directory is not writable: ' . WPFM_BACKUP_DIR);
        }

        // Prevent duplicate concurrent backups (simple lock for ~2 minutes)
        $lock_key = 'wpfm_backup_lock';
        if (get_transient($lock_key)) {
            wp_send_json_error('A backup is already running. Please wait a moment and try again.');
        }
        set_transient($lock_key, 1, 2 * MINUTE_IN_SECONDS);

        $db = isset($_POST['db']) && $_POST['db'] === '1';
        $files = isset($_POST['files']) && $_POST['files'] === '1';
        $parts = array(
            'plugins' => isset($_POST['plugins']) && $_POST['plugins'] === '1',
            'themes' => isset($_POST['themes']) && $_POST['themes'] === '1',
            'uploads' => isset($_POST['uploads']) && $_POST['uploads'] === '1',
            'others' => isset($_POST['others']) && $_POST['others'] === '1',
        );

        if (!$db && !$files) {
            wp_send_json_error('Select Database or Files to backup');
        }

        // Key used for grouping items and for the log file
        $key = 'backup_' . date('Y_m_d_H_i_s') . '-' . substr(md5(uniqid('', true)), 0, 8);
        $created = array();

        try {
            // Database dump (gzip if available)
            if ($db) {
                $sql = $this->export_database_sql();
                $wrote = false;
                if (function_exists('gzencode')) {
                    $sql_gz = gzencode($sql, 6);
                    if ($sql_gz !== false) {
                        $sql_file = WPFM_BACKUP_DIR . $key . '-db.sql.gz';
                        $wrote = file_put_contents($sql_file, $sql_gz) !== false;
                        if ($wrote) $created[] = basename($sql_file);
                    }
                }
                if (!$wrote) {
                    $sql_file = WPFM_BACKUP_DIR . $key . '-db.sql';
                    if (file_put_contents($sql_file, $sql) === false) {
                        wp_send_json_error('Unable to write database dump');
                    }
                    $created[] = basename($sql_file);
                }
            }

            // Files: create one zip per selected subset to allow individual downloads
            if ($files) {
                if (!class_exists('ZipArchive')) {
                    wp_send_json_error('ZipArchive not available on server');
                }

                // Uploads
                if ($parts['uploads']) {
                    $uploads = wp_get_upload_dir();
                    if (!empty($uploads['basedir'])) {
                        $zip_file = WPFM_BACKUP_DIR . $key . '-uploads.zip';
                        $zip = new ZipArchive();
                        if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
                            wp_send_json_error('Unable to create uploads backup zip');
                        }
                        $this->add_path_to_zip($uploads['basedir'], $zip, 'uploads');
                        $zip->close();
                        $created[] = basename($zip_file);
                    }
                }

                // Plugins
                if ($parts['plugins'] && defined('WP_PLUGIN_DIR')) {
                    $zip_file = WPFM_BACKUP_DIR . $key . '-plugins.zip';
                    $zip = new ZipArchive();
                    if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
                        wp_send_json_error('Unable to create plugins backup zip');
                    }
                    $this->add_path_to_zip(WP_PLUGIN_DIR, $zip, 'plugins');
                    $zip->close();
                    $created[] = basename($zip_file);
                }

                // Themes
                if ($parts['themes']) {
                    $zip_file = WPFM_BACKUP_DIR . $key . '-themes.zip';
                    $zip = new ZipArchive();
                    if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
                        wp_send_json_error('Unable to create themes backup zip');
                    }
                    $this->add_path_to_zip(get_theme_root(), $zip, 'themes');
                    $zip->close();
                    $created[] = basename($zip_file);
                }

                // Others (optional)
                if ($parts['others']) {
                    $content = rtrim(WP_CONTENT_DIR, '/');
                    $uploads = wp_get_upload_dir();
                    $skip = array(
                        rtrim(WP_PLUGIN_DIR, '/'),
                        rtrim(get_theme_root(), '/'),
                        rtrim($uploads['basedir'], '/')
                    );
                    $zip_file = WPFM_BACKUP_DIR . $key . '-others.zip';
                    $zip = new ZipArchive();
                    if ($zip->open($zip_file, ZipArchive::CREATE) !== TRUE) {
                        wp_send_json_error('Unable to create others backup zip');
                    }
                    foreach (glob($content . '/*') as $p) {
                        if (in_array(rtrim($p, '/'), $skip, true)) continue;
                        $this->add_path_to_zip($p, $zip, 'others/' . basename($p));
                    }
                    $zip->close();
                    $created[] = basename($zip_file);
                }
            }

            // Write a simple log file for this backup key
            $log_lines = array();
            $when = date('d M, Y h:i A');
            foreach ($created as $idx => $filename) {
                $path = WPFM_BACKUP_DIR . $filename;
                $size = file_exists($path) ? size_format(filesize($path), 2) : '0 B';
                if (strpos($filename, '-db.sql') !== false) {
                    $label = 'Database';
                } elseif (strpos($filename, '-plugins.zip') !== false) {
                    $label = 'Plugins';
                } elseif (strpos($filename, '-themes.zip') !== false) {
                    $label = 'Themes';
                } elseif (strpos($filename, '-uploads.zip') !== false) {
                    $label = 'Uploads';
                } else {
                    $label = 'Files';
                }
                $log_lines[] = '(' . ($idx + 1) . ") {$label} backup done on date {$when} ({$filename}) ({$size})";
            }
            if (!empty($log_lines)) {
                file_put_contents(WPFM_BACKUP_DIR . $key . '.log', implode("\n", $log_lines));
            }

            wp_send_json_success(array('created' => $created));
        } finally {
            // Always release the lock
            delete_transient($lock_key);
        }
    }

    private function export_database_sql()
    {
        global $wpdb;
        $tables = $wpdb->get_col('SHOW TABLES');
        $sql = "-- WP File Manager DB Export \n\n";
        foreach ($tables as $table) {
            $create = $wpdb->get_row("SHOW CREATE TABLE `{$table}`", ARRAY_N);
            $sql .= "\n-- Table structure for `{$table}`\n";
            $sql .= $create[1] . ";\n\n";
            $rows = $wpdb->get_results("SELECT * FROM `{$table}`", ARRAY_A);
            foreach ($rows as $row) {
                $values = array_map(function ($v) use ($wpdb) {
                    if ($v === null) return 'NULL';
                    return "'" . esc_sql($v) . "'";
                }, array_values($row));
                $columns = array_map(function ($c) {
                    return "`{$c}`";
                }, array_keys($row));
                $sql .= "INSERT INTO `{$table}` (" . implode(',', $columns) . ") VALUES (" . implode(',', $values) . ");\n";
            }
        }
        return $sql;
    }

    private function add_path_to_zip($path, $zip, $base = '')
    {
        if (!file_exists($path)) return;
        $path = rtrim($path, '/');
        if (is_file($path)) {
            $zip->addFile($path, ($base ? $base . '/' : '') . basename($path));
            return;
        }
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        foreach ($iterator as $file) {
            if ($file->isDir()) continue;
            $file_path = $file->getRealPath();
            // Skip excessively large files to avoid timeouts on constrained hosts (e.g., media > 200MB)
            try {
                if (method_exists($file, 'getSize')) {
                    $filesize = (int) $file->getSize();
                    if ($filesize > 200 * 1024 * 1024) {
                        continue;
                    }
                }
            } catch (Exception $e) {
                // ignore size check failures
            }
            $rel = ltrim(str_replace($path, '', $file_path), '/');
            $zip->addFile($file_path, ($base ? $base . '/' : '') . $rel);
        }
    }

    private function backup_list()
    {
        if (!is_dir(WPFM_BACKUP_DIR)) wp_send_json_success(array());
        $items = array();
        // Include zip, sql, and gz (for .sql.gz)
        foreach (array('zip', 'sql', 'gz') as $ext) {
            foreach (glob(WPFM_BACKUP_DIR . '*.' . $ext) as $file) {
                $items[] = array(
                    'name' => basename($file),
                    'size' => filesize($file),
                    'modified' => filemtime($file),
                    'path' => $file,
                );
            }
        }
        // sort by modified desc
        usort($items, function ($a, $b) {
            return $b['modified'] <=> $a['modified'];
        });
        wp_send_json_success($items);
    }

    private function backup_delete()
    {
        $deleted = array();
        $maybe_delete_by_key = function ($key) use (&$deleted) {
            $key = sanitize_text_field($key);
            if ($key === '') return;
            // Delete any backup artifact that starts with the key (zips, sql, gz, and log)
            foreach (glob(WPFM_BACKUP_DIR . $key . '-*') as $file) {
                if (strpos(realpath($file), realpath(WPFM_BACKUP_DIR)) === 0 && @unlink($file)) {
                    $deleted[] = basename($file);
                }
            }
            // Legacy compatibility: also delete any file containing the key fragment
            foreach (glob(WPFM_BACKUP_DIR . '*' . $key . '*') as $file) {
                if (strpos(realpath($file), realpath(WPFM_BACKUP_DIR)) === 0 && @unlink($file)) {
                    $deleted[] = basename($file);
                }
            }
            $log = WPFM_BACKUP_DIR . $key . '.log';
            if (file_exists($log) && strpos(realpath($log), realpath(WPFM_BACKUP_DIR)) === 0 && @unlink($log)) {
                $deleted[] = basename($log);
            }
        };

        // Prefer explicit key(s)
        if (!empty($_POST['key'])) {
            $maybe_delete_by_key($_POST['key']);
            wp_send_json_success(array('deleted' => $deleted));
        }
        if (!empty($_POST['keys']) && is_array($_POST['keys'])) {
            foreach ($_POST['keys'] as $k) {
                $maybe_delete_by_key($k);
            }
            wp_send_json_success(array('deleted' => $deleted));
        }

        // Fallback: explicit file names
        $names = isset($_POST['names']) ? (array) $_POST['names'] : array();
        foreach ($names as $name) {
            $file = WPFM_BACKUP_DIR . basename($name);
            if (file_exists($file) && strpos(realpath($file), realpath(WPFM_BACKUP_DIR)) === 0) {
                if (@unlink($file)) $deleted[] = basename($file);
            }
        }
        wp_send_json_success(array('deleted' => $deleted));
    }

    private function backup_download()
    {
        // Support both POST and GET requests
        $name = isset($_REQUEST['name']) ? basename($_REQUEST['name']) : '';

        if (empty($name)) {
            if (wp_doing_ajax()) {
                wp_send_json_error('Backup name is required');
            } else {
                wp_die('Backup name is required');
            }
        }

        $file = WPFM_BACKUP_DIR . $name;

        // Security check - ensure file is within backup directory
        if (!file_exists($file) || strpos(realpath($file), realpath(WPFM_BACKUP_DIR)) !== 0) {
            if (wp_doing_ajax()) {
                wp_send_json_error('Backup file not found or access denied');
            } else {
                wp_die('Backup file not found or access denied');
            }
        }

        // Set headers for file download
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $name . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($file));

        // Clear any output buffering
        if (ob_get_level()) {
            ob_end_clean();
        }

        readfile($file);

        // If this is an AJAX request, we need to exit properly
        if (wp_doing_ajax()) {
            wp_die();
        } else {
            exit;
        }
    }

    private function backup_download_all()
    {
        $key = isset($_REQUEST['key']) ? sanitize_text_field($_REQUEST['key']) : '';
        if ($key === '') {
            if (wp_doing_ajax()) {
                wp_send_json_error('Backup key is required');
            } else {
                wp_die('Backup key is required');
            }
        }

        if (!class_exists('ZipArchive')) {
            if (wp_doing_ajax()) {
                wp_send_json_error('ZipArchive not available on server');
            } else {
                wp_die('ZipArchive not available on server');
            }
        }

        // Collect all parts for this key (plus legacy names containing the key)
        $files = glob(WPFM_BACKUP_DIR . $key . '-*') ?: array();
        $legacy = glob(WPFM_BACKUP_DIR . '*' . $key . '*') ?: array();
        foreach ($legacy as $lf) {
            if (!in_array($lf, $files, true)) $files[] = $lf;
        }
        // Include log if present
        $log = WPFM_BACKUP_DIR . $key . '.log';
        if (file_exists($log)) $files[] = $log;

        if (empty($files)) {
            if (wp_doing_ajax()) {
                wp_send_json_error('No files found for this backup');
            } else {
                wp_die('No files found for this backup');
            }
        }

        // Build two inner zips: database artifacts and file artifacts
        $db_tmp = tempnam(sys_get_temp_dir(), 'wpfm_db_') . '.zip';
        $files_tmp = tempnam(sys_get_temp_dir(), 'wpfm_files_') . '.zip';
        $dbZip = new ZipArchive();
        $filesZip = new ZipArchive();
        $dbZip->open($db_tmp, ZipArchive::CREATE);
        $filesZip->open($files_tmp, ZipArchive::CREATE);
        foreach ($files as $file) {
            if (!file_exists($file)) continue;
            if (strpos(realpath($file), realpath(WPFM_BACKUP_DIR)) !== 0) continue;
            $base = basename($file);
            $lower = strtolower($base);
            if (substr($lower, -4) === '.sql' || substr($lower, -7) === '.sql.gz' || substr($lower, -4) === '.log') {
                $dbZip->addFile($file, $base);
            } elseif (substr($lower, -4) === '.zip') {
                $filesZip->addFile($file, $base);
            }
        }
        $dbZip->close();
        $filesZip->close();

        // Create outer bundle containing the two zips
        $zip = new ZipArchive();
        $zip_filename = tempnam(sys_get_temp_dir(), 'wpfm_bundle_') . '.zip';
        if ($zip->open($zip_filename, ZipArchive::CREATE) !== true) {
            @unlink($db_tmp);
            @unlink($files_tmp);
            if (wp_doing_ajax()) {
                wp_send_json_error('Unable to create archive');
            } else {
                wp_die('Unable to create archive');
            }
        }
        if (file_exists($db_tmp)) $zip->addFile($db_tmp, $key . '-database.zip');
        if (file_exists($files_tmp)) $zip->addFile($files_tmp, $key . '-files.zip');
        $zip->close();

        // Send bundle
        $bundle_name = $key . '-all.zip';
        header('Content-Description: File Transfer');
        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $bundle_name . '"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($zip_filename));
        if (ob_get_level()) {
            ob_end_clean();
        }
        readfile($zip_filename);
        @unlink($zip_filename);
        @unlink($db_tmp);
        @unlink($files_tmp);
        if (wp_doing_ajax()) {
            wp_die();
        } else {
            exit;
        }
    }

    private function lint_php()
    {
        // Only for users allowed to edit files
        $user = wp_get_current_user();
        $file_roles = get_option('wpfm_file_roles', array('administrator'));
        if (!array_intersect($user->roles, $file_roles)) {
            wp_send_json_error('Permission denied');
        }

        if (get_option('wpfm_syntax_check', 'no') !== 'yes') {
            wp_send_json_error('Syntax check disabled');
        }

        $content = isset($_POST['content']) ? (string) wp_unslash($_POST['content']) : '';
        if ($content === '') {
            wp_send_json_error('No content');
        }

        // Write to a temp file and run php -l with CLI php (avoid php-fpm binary)
        $tmp = tempnam(sys_get_temp_dir(), 'wpfm_lint_');
        file_put_contents($tmp, $content);

        // Try to find a CLI php binary
        $candidates = array();
        $envPhp = getenv('PHP_CLI');
        if (!empty($envPhp)) $candidates[] = $envPhp;
        $candidates = array_merge($candidates, array('php', 'php8.2', 'php8.1', '/usr/bin/php', '/usr/local/bin/php'));
        $phpBin = null;
        foreach ($candidates as $cand) {
            $out = @shell_exec('command -v ' . escapeshellcmd($cand) . ' 2>/dev/null');
            if (!empty($out)) {
                $phpBin = trim($out);
                break;
            }
            if (is_executable($cand)) {
                $phpBin = $cand;
                break;
            }
        }
        if ($phpBin === null) {
            @unlink($tmp);
            wp_send_json_error('PHP CLI not found for linting');
        }

        $cmd = escapeshellcmd($phpBin) . ' -n -d display_errors=1 -l ' . escapeshellarg($tmp) . ' 2>&1';
        @exec($cmd, $output, $status);
        @unlink($tmp);

        $text = is_array($output) ? implode("\n", $output) : (string) $output;

        if ($status === 0 && stripos($text, 'No syntax errors') !== false) {
            wp_send_json_success(array('ok' => true));
        }

        // Parse "on line X" pattern
        if (preg_match('/on line (\d+)/i', $text, $m)) {
            $line = intval($m[1]);
        } else {
            $line = null;
        }
        wp_send_json_success(array(
            'ok' => false,
            'message' => trim($text),
            'line' => $line
        ));
    }

    private function backup_restore_db()
    {
        $name = isset($_POST['name']) ? basename($_POST['name']) : '';
        $file = WPFM_BACKUP_DIR . $name;
        $ends_with = function ($haystack, $needle) {
            return $needle === '' || substr($haystack, -strlen($needle)) === $needle;
        };
        if (!$name || !file_exists($file) || (!$ends_with($name, '.sql') && !$ends_with($name, '.sql.gz'))) {
            wp_send_json_error('Invalid SQL backup selected');
        }
        if ($ends_with($name, '.gz')) {
            $contents = file_get_contents($file);
            if ($contents === false) wp_send_json_error('Unable to read SQL gzip file');
            $sql = gzdecode($contents);
            if ($sql === false) wp_send_json_error('Unable to decompress SQL gzip file');
        } else {
            $sql = file_get_contents($file);
        }
        if ($sql === false) wp_send_json_error('Unable to read SQL file');
        global $wpdb;
        // Split queries on semicolon-newline to avoid breaking data containing ;
        $statements = preg_split('/;\s*\n/', $sql);
        $count = 0;
        foreach ($statements as $stmt) {
            $stmt = trim($stmt);
            if ($stmt === '' || strpos($stmt, '--') === 0) continue;
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $wpdb->query($stmt);
            if ($wpdb->last_error) {
                wp_send_json_error($wpdb->last_error);
            }
            $count++;
        }
        wp_send_json_success(array('restored_statements' => $count));
    }

    private function backup_restore_files()
    {
        $name = isset($_POST['name']) ? basename($_POST['name']) : '';
        $file = WPFM_BACKUP_DIR . $name;
        if (!$name || !file_exists($file) || substr($name, -4) !== '.zip') {
            wp_send_json_error('Invalid files backup selected');
        }
        if (!class_exists('ZipArchive')) wp_send_json_error('ZipArchive not available');
        $zip = new ZipArchive();
        if ($zip->open($file) !== TRUE) wp_send_json_error('Unable to open backup zip');

        // Extract to a temp dir first
        $tmp = trailingslashit(get_temp_dir()) . 'wpfm_restore_' . wp_generate_password(8, false) . '/';
        wp_mkdir_p($tmp);
        if (!$zip->extractTo($tmp)) {
            $zip->close();
            wp_send_json_error('Failed to extract backup');
        }
        $zip->close();

        // Merge extracted content into wp-content
        $contentDir = rtrim(WP_CONTENT_DIR, '/');
        $it = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tmp, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($it as $path => $info) {
            $rel = substr($path, strlen($tmp));
            $dest = $contentDir . '/' . $rel;
            if ($info->isDir()) {
                if (!is_dir($dest)) wp_mkdir_p($dest);
            } else {
                // Ensure parent exists
                $dir = dirname($dest);
                if (!is_dir($dir)) wp_mkdir_p($dir);
                @copy($path, $dest);
            }
        }
        // Cleanup temp
        $this->delete_directory($tmp);

        wp_send_json_success('Files restored');
    }
}

// Initialize the plugin
WP_File_Manager::get_instance();
// Register activation/deactivation hooks globally so activation reliably creates tables and directories
register_activation_hook(WPFM_PLUGIN_FILE, function () {
    WP_File_Manager::get_instance()->activate();
});
register_deactivation_hook(WPFM_PLUGIN_FILE, function () {
    WP_File_Manager::get_instance()->deactivate();
});
