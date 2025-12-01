<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure WordPress functions are available
if (!function_exists('add_action')) {
    require_once ABSPATH . 'wp-includes/plugin.php';
}

if (!function_exists('wp_send_json_success')) {
    require_once ABSPATH . 'wp-includes/functions.php';
}

class WPFM_Ajax
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
        require_once WPFM_PLUGIN_PATH . 'includes/class-logs.php';
        $this->logs = WPFM_Logs::get_instance();

        // Add AJAX endpoints
        add_action('wp_ajax_wpfm_get_logs', [$this, 'get_logs']);
        add_action('wp_ajax_wpfm_clear_logs', [$this, 'clear_logs']);
        add_action('wp_ajax_wpfm_log_operation', [$this, 'log_operation']);
        add_action('wp_ajax_wpfm_bulk_action', [$this, 'handle_bulk_action']);

        $this->register_ajax_handlers();
    }

    public function handle_bulk_action()
    {
        // Verify nonce and permissions
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $action = sanitize_text_field($_POST['bulk_action'] ?? '');
        $ids = array_map('intval', (array)($_POST['ids'] ?? []));

        if (empty($action) || empty($ids)) {
            wp_send_json_error('Invalid request');
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'wpfm_logs';

        switch ($action) {
            case 'delete':
                // Build placeholders for prepare
                $placeholders = implode(',', array_fill(0, count($ids), '%d'));
                $sql = "DELETE FROM {$table_name} WHERE id IN ({$placeholders})";
                $result = $wpdb->query($wpdb->prepare($sql, $ids));

                if ($result !== false) {
                    wp_send_json_success('Logs deleted successfully');
                } else {
                    wp_send_json_error('Failed to delete logs');
                }
                break;

            default:
                wp_send_json_error('Invalid action');
                break;
        }
    }

    public function log_operation()
    {
        // Use unified nonce verification
        $this->verify_nonce();

        $file_name = sanitize_text_field($_POST['file_name'] ?? '');
        $file_path = sanitize_text_field($_POST['file_path'] ?? '');
        $operation = sanitize_text_field($_POST['operation'] ?? '');

        if (empty($file_name) || empty($file_path) || empty($operation)) {
            wp_send_json_error('Missing required parameters');
        }

        // Add log entry to DB if available and also keep legacy option storage
        $db_result = null;
        if (isset($this->logs) && method_exists($this->logs, 'add_log')) {
            $db_result = $this->logs->add_log($file_name, $file_path, $operation);
        }

        // Also append to option-based logs for backwards compatibility
        $this->append_log($operation === 'upload' ? 'uploaded' : ($operation === 'download' ? 'downloaded' : 'edited'), $file_name, $file_path);

        if ($db_result === false) {
            wp_send_json_error('Failed to log operation');
        }

        wp_send_json_success('Operation logged successfully');
    }
    private function register_ajax_handlers()
    {
        // File operations
        add_action('wp_ajax_wpfm_list_files', [$this, 'list_files']);
        add_action('wp_ajax_wpfm_upload_files', [$this, 'upload_files']);
        add_action('wp_ajax_wpfm_create_folder', [$this, 'create_folder']);
        add_action('wp_ajax_wpfm_delete_item', [$this, 'delete_item']);
        add_action('wp_ajax_wpfm_rename_item', [$this, 'rename_item']);
        add_action('wp_ajax_wpfm_move_item', [$this, 'move_item']);
        add_action('wp_ajax_wpfm_download_item', [$this, 'download_item']);
        add_action('wp_ajax_wpfm_test_smtp', [$this, 'test_smtp']);

        // Note: 'wpfm_file_manager' is handled centrally in WP_File_Manager::handle_ajax_request

        // Database operations
        add_action('wp_ajax_wpfm_run_query', [$this, 'run_query']);
        add_action('wp_ajax_wpfm_get_tables', [$this, 'get_tables']);
        add_action('wp_ajax_wpfm_export_table', [$this, 'export_table']);
        add_action('wp_ajax_wpfm_export_database', [$this, 'export_database']);
        add_action('wp_ajax_wpfm_browse_table', [$this, 'browse_table']);
        add_action('wp_ajax_wpfm_empty_table', [$this, 'empty_table']);
        add_action('wp_ajax_wpfm_drop_table', [$this, 'drop_table']);
        add_action('wp_ajax_wpfm_optimize_tables', [$this, 'optimize_tables']);

        // Settings operations
        add_action('wp_ajax_wpfm_save_settings', [$this, 'save_settings']);
        add_action('wp_ajax_wpfm_test_email', [$this, 'send_test_email']);
    }

    public function handle_file_manager_actions()
    {
        $this->verify_nonce();
        $this->check_file_access();

        $action_type = sanitize_text_field($_POST['action_type'] ?? '');

        switch ($action_type) {
            case 'get_directory_tree':
                $this->get_directory_tree();
                break;
            default:
                wp_send_json_error('Invalid action type');
        }
    }

    private function get_directory_tree()
    {
        $wp_content_dir = rtrim(WP_CONTENT_DIR, '/');
        $tree = $this->scan_directory($wp_content_dir, $wp_content_dir);
        wp_send_json_success($tree);
    }

    private function scan_directory($path, $base_path)
    {
        if (!is_dir($path)) {
            return [];
        }

        $result = [];
        $items = @scandir($path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $full_path = $path . '/' . $item;
            // Skip hidden files/directories
            if (substr($item, 0, 1) === '.') {
                continue;
            }

            // Skip some WP core/system directories at root level only
            $skip_root_dirs = ['wp-admin', 'wp-includes', 'backup', 'upgrade'];
            if (in_array($item, $skip_root_dirs, true) && dirname($full_path) === $base_path) {
                continue;
            }

            $relative_path = substr($full_path, strlen($base_path));
            $relative_path = str_replace('\\', '/', $relative_path);
            $relative_path = '/' . ltrim($relative_path, '/');

            if (is_dir($full_path)) {
                // Only include readable directories
                if (is_readable($full_path)) {
                    $result[] = [
                        'type' => 'folder',
                        'name' => $item,
                        'path' => $relative_path,
                        'children' => $this->scan_directory($full_path, $base_path)
                    ];
                }
            }
        }

        // Sort alphabetically
        usort($result, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $result;
    }

    public function test_smtp()
    {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        // Get SMTP settings from POST data
        $smtp_settings = [
            'host' => sanitize_text_field($_POST['smtp_host'] ?? ''),
            'port' => sanitize_text_field($_POST['smtp_port'] ?? '587'),
            'username' => sanitize_text_field($_POST['smtp_username'] ?? ''),
            'password' => sanitize_text_field($_POST['smtp_password'] ?? ''),
            'encryption' => sanitize_text_field($_POST['smtp_encryption'] ?? 'tls'),
            'from_email' => sanitize_email($_POST['smtp_from_email'] ?? ''),
            'from_name' => sanitize_text_field($_POST['smtp_from_name'] ?? '')
        ];

        // Test email configuration
        $result = $this->send_test_email($smtp_settings);

        if ($result['success']) {
            wp_send_json_success(['message' => $result['message']]);
        } else {
            wp_send_json_error($result['message']);
        }
    }

    private function send_test_email($smtp_settings)
    {
        // Configure PHPMailer via WordPress hook
        add_action('phpmailer_init', function ($phpmailer) use ($smtp_settings) {
            $phpmailer->isSMTP();
            $phpmailer->Host = $smtp_settings['host'];
            $phpmailer->SMTPAuth = true;
            $phpmailer->Port = $smtp_settings['port'];
            $phpmailer->Username = $smtp_settings['username'];
            $phpmailer->Password = $smtp_settings['password'];

            if (!empty($smtp_settings['encryption'])) {
                $phpmailer->SMTPSecure = $smtp_settings['encryption'];
            }

            // Debug logging
            $phpmailer->SMTPDebug = 2;
            $debug_output = '';
            $phpmailer->Debugoutput = function ($str, $level) use (&$debug_output) {
                $debug_output .= "$str\n";
                error_log("WP File Manager SMTP: $str");
            };

            return $debug_output;
        });

        // Use a valid email address for testing
        $to_email = !empty($smtp_settings['from_email']) && is_email($smtp_settings['from_email'])
            ? $smtp_settings['from_email']
            : get_option('admin_email'); // Fallback to admin email

        $subject = 'WP File Manager - SMTP Test';
        $message = "This is a test email from WP File Manager.\n\n";
        $message .= "If you're reading this, your SMTP settings with Mailtrap are working correctly!\n\n";
        $message .= "SMTP Settings used:\n";
        $message .= "- Host: " . $smtp_settings['host'] . "\n";
        $message .= "- Port: " . $smtp_settings['port'] . "\n";
        $message .= "- Encryption: " . ($smtp_settings['encryption'] ?: 'None') . "\n";

        $headers = array('Content-Type: text/plain; charset=UTF-8');

        // Set from address if provided
        if (!empty($smtp_settings['from_email']) && is_email($smtp_settings['from_email'])) {
            $headers[] = 'From: ' . ($smtp_settings['from_name'] ?: 'WP File Manager') . ' <' . $smtp_settings['from_email'] . '>';
        }

        try {
            $sent = wp_mail($to_email, $subject, $message, $headers);

            if ($sent) {
                return [
                    'success' => true,
                    'message' => 'Test email sent successfully to: ' . $to_email . '. Check your Mailtrap inbox.'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Email failed to send. Check your server error logs for SMTP debug information.'
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'SMTP Error: ' . $e->getMessage()
            ];
        }
    }

    public function list_files()
    {
        $this->verify_nonce();
        $this->check_file_access();

        $path = sanitize_text_field($_POST['path'] ?? '/');
        $base_path = WPFM_UPLOAD_DIR . ltrim($path, '/');

        // Security check - prevent directory traversal
        if (strpos(realpath($base_path), realpath(WPFM_UPLOAD_DIR)) !== 0) {
            wp_send_json_error('Invalid path');
        }

        if (!is_dir($base_path)) {
            wp_send_json_error('Directory not found');
        }

        $files = [];
        $items = scandir($base_path);

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $item_path = $base_path . '/' . $item;
            $relative_path = $path . '/' . $item;

            // Skip hidden files
            if (substr($item, 0, 1) === '.') continue;

            $files[] = [
                'name' => $item,
                'path' => $relative_path,
                'is_dir' => is_dir($item_path),
                'size' => is_dir($item_path) ? 0 : filesize($item_path),
                'modified' => filemtime($item_path),
                'permissions' => substr(sprintf('%o', fileperms($item_path)), -4),
                'icon' => WPFM_Core::get_instance()->get_file_icon($item)
            ];
        }

        // Sort files: folders first, then by name
        usort($files, function ($a, $b) {
            if ($a['is_dir'] && !$b['is_dir']) return -1;
            if (!$a['is_dir'] && $b['is_dir']) return 1;
            return strcasecmp($a['name'], $b['name']);
        });

        wp_send_json_success($files);
    }

    public function upload_files()
    {
        $this->verify_nonce();
        $this->check_file_access();

        if (empty($_FILES['files'])) {
            wp_send_json_error('No files uploaded');
        }

        $path = sanitize_text_field($_POST['path'] ?? '');
        $upload_path = WPFM_UPLOAD_DIR . ltrim($path, '/');

        // Create directory if it doesn't exist
        if (!is_dir($upload_path)) {
            wp_mkdir_p($upload_path);
        }

        $uploaded = [];
        $errors = [];
        $max_size = WPFM_Core::get_instance()->get_upload_limit();

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
            $clean_name = WPFM_Core::get_instance()->sanitize_filename($name);
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

            // Use WordPress's wp_handle_upload function
            $upload_overrides = array('test_form' => false, 'test_type' => false);
            $movefile = wp_handle_upload($file_array, $upload_overrides);

            if ($movefile && !isset($movefile['error'])) {
                $uploaded[] = $clean_name;

                // LOG THE UPLOAD - ALWAYS LOG, not just when notifications are enabled
                $core = WPFM_Core::get_instance();
                $core->log_file_action($clean_name, $path . '/' . $clean_name, 'upload');

                // Send email notification if enabled
                if (get_option('wpfm_notify_upload', 'no') === 'yes') {
                    $this->send_upload_notification($clean_name, $path);
                }
            } else {
                $errors[] = "Failed to move uploaded file {$name}";
            }
        }

        if (!empty($errors)) {
            wp_send_json_success([
                'uploaded' => $uploaded,
                'errors' => $errors
            ]);
        } else {
            wp_send_json_success(['uploaded' => $uploaded]);
        }
    }

    public function create_folder()
    {
        $this->verify_nonce();
        $this->check_file_access();

        $name = sanitize_text_field($_POST['name'] ?? '');
        $path = sanitize_text_field($_POST['path'] ?? '');

        if (empty($name)) {
            wp_send_json_error('Folder name is required');
        }

        // Sanitize folder name
        $clean_name = WPFM_Core::get_instance()->sanitize_filename($name);
        $folder_path = WPFM_UPLOAD_DIR . ltrim($path, '/') . '/' . $clean_name;

        if (file_exists($folder_path)) {
            wp_send_json_error('Folder already exists');
        }

        if (wp_mkdir_p($folder_path)) {
            wp_send_json_success('Folder created successfully');
        } else {
            wp_send_json_error('Failed to create folder');
        }
    }

    public function delete_item()
    {
        $this->verify_nonce();
        $this->check_file_access();

        $path = sanitize_text_field($_POST['path'] ?? '');
        $item_path = WPFM_UPLOAD_DIR . ltrim($path, '/');

        if (!file_exists($item_path)) {
            wp_send_json_error('Item not found');
        }

        // Prevent deletion of root directory
        if ($item_path === WPFM_UPLOAD_DIR) {
            wp_send_json_error('Cannot delete root directory');
        }

        if (is_dir($item_path)) {
            if ($this->delete_directory($item_path)) {
                // LOG FOLDER DELETION
                $core = WPFM_Core::get_instance();
                $core->log_file_action(basename($path), $path, 'delete');

                wp_send_json_success('Folder deleted successfully');
            } else {
                wp_send_json_error('Failed to delete folder');
            }
        } else {
            if (unlink($item_path)) {
                // LOG FILE DELETION
                $core = WPFM_Core::get_instance();
                $core->log_file_action(basename($path), $path, 'delete');

                wp_send_json_success('File deleted successfully');
            } else {
                wp_send_json_error('Failed to delete file');
            }
        }
    }

    public function rename_item()
    {
        $this->verify_nonce();
        $this->check_file_access();

        $path = sanitize_text_field($_POST['path'] ?? '');
        $new_name = sanitize_text_field($_POST['new_name'] ?? '');

        if (empty($new_name)) {
            wp_send_json_error('New name is required');
        }

        $old_path = WPFM_UPLOAD_DIR . ltrim($path, '/');
        $directory = dirname($old_path);
        $clean_name = WPFM_Core::get_instance()->sanitize_filename($new_name);
        $new_path = $directory . '/' . $clean_name;

        if (!file_exists($old_path)) {
            wp_send_json_error('Item not found');
        }

        if (file_exists($new_path)) {
            wp_send_json_error('Target name already exists');
        }

        if (rename($old_path, $new_path)) {
            wp_send_json_success('Item renamed successfully');
        } else {
            wp_send_json_error('Failed to rename item');
        }
    }

    public function download_item()
    {
        $this->verify_nonce();
        $this->check_file_access();

        $path = sanitize_text_field($_GET['path'] ?? '');
        $item_path = WPFM_UPLOAD_DIR . ltrim($path, '/');

        if (!file_exists($item_path) || is_dir($item_path)) {
            wp_die('File not found');
        }

        $filename = basename($item_path);

        // LOG THE DOWNLOAD - ALWAYS LOG, not just when notifications are enabled
        $core = WPFM_Core::get_instance();
        $core->log_file_action($filename, $path, 'download');

        // Defer email notification until after output is sent to avoid header issues
        if (get_option('wpfm_notify_download', 'no') === 'yes') {
            $deferred_filename = $filename;
            $deferred_path = $path;
            register_shutdown_function(function () use ($deferred_filename, $deferred_path) {
                // Silence any accidental output during mail send
                $level = ob_get_level();
                while ($level-- > 0) {
                    @ob_end_clean();
                }
                try {
                    $this->send_download_notification($deferred_filename, $deferred_path);
                } catch (\Throwable $e) {
                    error_log('WP File Manager: download notification failed - ' . $e->getMessage());
                }
            });
        }

        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($item_path));
        header('Pragma: no-cache');
        header('Expires: 0');

        readfile($item_path);
        exit;
    }

    public function run_query()
    {
        $this->verify_nonce();
        $this->check_db_access();

        global $wpdb;

        $query = stripslashes($_POST['query'] ?? '');

        if (empty(trim($query))) {
            wp_send_json_error('Query is empty');
        }

        // Security checks for destructive operations
        $restricted_keywords = ['DROP', 'DELETE', 'TRUNCATE', 'ALTER', 'CREATE', 'INSERT', 'UPDATE'];
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
            // For result-set queries, get results
            $resultset_keywords = ['SELECT', 'SHOW', 'DESCRIBE', 'EXPLAIN'];
            $is_resultset = false;
            foreach ($resultset_keywords as $kw) {
                if (strpos($query_upper, $kw) === 0) {
                    $is_resultset = true;
                    break;
                }
            }

            if ($is_resultset) {
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $results = $wpdb->get_results($query, ARRAY_A);
                $affected_rows = $wpdb->rows_affected;
            } else {
                // For other queries, execute and get affected rows
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
                $results = $wpdb->query($query);
                $affected_rows = $wpdb->rows_affected;
            }

            if ($wpdb->last_error) {
                wp_send_json_error(esc_html($wpdb->last_error));
            }

            wp_send_json_success([
                'results' => $this->escape_output($results),
                'affected_rows' => intval($affected_rows),
                'count' => is_array($results) ? count($results) : 0
            ]);
        } catch (Exception $e) {
            wp_send_json_error(esc_html($e->getMessage()));
        }
    }

    public function get_tables()
    {
        $this->verify_nonce();
        $this->check_db_access();

        global $wpdb;

        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        $table_list = [];

        foreach ($tables as $table) {
            $table_name = $table[0];
            $table_info = $wpdb->get_row("SHOW TABLE STATUS LIKE '$table_name'", ARRAY_A);

            $table_list[] = [
                'name' => $table_name,
                'rows' => $table_info['Rows'] ?? 0,
                'size' => $table_info['Data_length'] ?? 0,
                'engine' => $table_info['Engine'] ?? '',
                'collation' => $table_info['Collation'] ?? ''
            ];
        }

        wp_send_json_success($this->escape_output($table_list));
    }

    public function browse_table()
    {
        $this->verify_nonce();
        $this->check_db_access();

        global $wpdb;
        $table = sanitize_text_field($_POST['table'] ?? '');
        $limit = intval($_POST['limit'] ?? 50);
        $offset = intval($_POST['offset'] ?? 0);

        if ($table === '') {
            wp_send_json_error('Table is required');
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results("SELECT * FROM `$table` LIMIT $limit OFFSET $offset", ARRAY_A);
        if ($wpdb->last_error) {
            wp_send_json_error(esc_html($wpdb->last_error));
        }

        wp_send_json_success([
            'rows' => $this->escape_output($rows),
            'count' => is_array($rows) ? count($rows) : 0,
        ]);
    }

    public function empty_table()
    {
        $this->verify_nonce();
        $this->check_db_access();

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;
        $table = sanitize_text_field($_POST['table'] ?? '');
        if ($table === '') {
            wp_send_json_error('Table is required');
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->query("TRUNCATE TABLE `$table`");
        if ($result === false) {
            wp_send_json_error(esc_html($wpdb->last_error ?: 'Failed to empty table'));
        }

        wp_send_json_success('Table emptied');
    }

    public function drop_table()
    {
        $this->verify_nonce();
        $this->check_db_access();

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;
        $table = sanitize_text_field($_POST['table'] ?? '');
        if ($table === '') {
            wp_send_json_error('Table is required');
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $result = $wpdb->query("DROP TABLE IF EXISTS `$table`");
        if ($result === false) {
            wp_send_json_error(esc_html($wpdb->last_error ?: 'Failed to drop table'));
        }

        wp_send_json_success('Table dropped');
    }

    public function export_table()
    {
        $this->verify_nonce();
        $this->check_db_access();

        global $wpdb;
        $table = sanitize_text_field($_GET['table'] ?? '');
        if ($table === '') {
            wp_die('Table is required');
        }

        // Fetch rows
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $rows = $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A);
        if ($wpdb->last_error) {
            wp_die(esc_html($wpdb->last_error));
        }

        // Send CSV
        $safe_table = sanitize_file_name($table);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . rawurlencode($safe_table . '.csv') . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $out = fopen('php://output', 'w');
        if (!empty($rows)) {
            fputcsv($out, array_keys($rows[0]));
            foreach ($rows as $row) {
                fputcsv($out, $row);
            }
        }
        fclose($out);
        exit;
    }

    public function export_database()
    {
        $this->verify_nonce();
        $this->check_db_access();

        if (!class_exists('ZipArchive')) {
            wp_die('ZipArchive not available');
        }

        global $wpdb;
        $tables = $wpdb->get_results('SHOW TABLES', ARRAY_N);

        $zip = new ZipArchive();
        $zip_filename = tempnam(sys_get_temp_dir(), 'wpfm_db_') . '.zip';
        if ($zip->open($zip_filename, ZipArchive::CREATE) !== true) {
            wp_die('Failed to create zip');
        }

        foreach ($tables as $t) {
            $table = $t[0];
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
            $rows = $wpdb->get_results("SELECT * FROM `$table`", ARRAY_A);
            if ($wpdb->last_error) {
                continue;
            }

            $csv = '';
            if (!empty($rows)) {
                $csv .= implode(',', array_map(function ($h) {
                    return '"' . str_replace('"', '""', $h) . '"';
                }, array_keys($rows[0]))) . "\n";
                foreach ($rows as $row) {
                    $csv .= implode(',', array_map(function ($v) {
                        return '"' . str_replace('"', '""', (string)$v) . '"';
                    }, array_values($row))) . "\n";
                }
            }

            $safe_table = sanitize_file_name($table);
            $zip->addFromString($safe_table . '.csv', $csv);
        }

        $zip->close();

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="database_export.zip"');
        header('Content-Length: ' . filesize($zip_filename));
        if (ob_get_level()) {
            ob_end_clean();
        }
        readfile($zip_filename);
        unlink($zip_filename);
        exit;
    }

    public function optimize_tables()
    {
        $this->verify_nonce();
        $this->check_db_access();

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Insufficient permissions');
        }

        global $wpdb;

        $tables = $wpdb->get_results("SHOW TABLES", ARRAY_N);
        $optimized = [];

        foreach ($tables as $table) {
            $table_name = $table[0];
            $result = $wpdb->query("OPTIMIZE TABLE $table_name");

            if ($result !== false) {
                $optimized[] = esc_html($table_name);
            }
        }

        wp_send_json_success([
            'optimized' => $optimized,
            'count' => count($optimized)
        ]);
    }

    private function delete_directory($dir)
    {
        if (!is_dir($dir)) return false;

        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->delete_directory($path) : unlink($path);
        }

        return rmdir($dir);
    }

    /**
     * Recursively escape data for safe JSON output.
     * Strings are run through esc_html(), numbers and booleans left intact.
     */
    private function escape_output($data)
    {
        if (is_array($data)) {
            $out = [];
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

        // ints, floats, bools, null
        return $data;
    }

    private function verify_nonce()
    {
        if (!wp_verify_nonce($_REQUEST['nonce'] ?? '', 'wpfm_nonce')) {
            wp_send_json_error('Security verification failed');
        }
    }

    private function check_file_access()
    {
        $user = wp_get_current_user();
        $allowed_roles = get_option('wpfm_file_roles', ['administrator']);

        if (!array_intersect($user->roles, $allowed_roles)) {
            wp_send_json_error('Access denied');
        }
    }

    private function check_db_access()
    {
        $user = wp_get_current_user();
        $allowed_roles = get_option('wpfm_db_roles', ['administrator']);

        if (!array_intersect($user->roles, $allowed_roles)) {
            wp_send_json_error('Access denied');
        }
    }

    private function send_upload_notification($filename, $path)
    {
        // Delegate to core HTML email sender
        $core = WPFM_Core::get_instance();
        $core_ref = $core; // avoid static analyzer warnings
        $send = function () use ($core_ref, $filename, $path) {
            // Core handles options, headers, and template
            $core_ref->send_notification('upload', $filename, $path);
        };
        $send();
    }

    private function send_download_notification($filename, $path)
    {
        // Delegate to core HTML email sender
        $core = WPFM_Core::get_instance();
        $core_ref = $core;
        $send = function () use ($core_ref, $filename, $path) {
            $core_ref->send_notification('download', $filename, $path);
        };
        $send();
    }

    public function get_logs()
    {
        // Verify nonce and permissions
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $log_type = sanitize_text_field($_POST['log_type'] ?? '');
        $search = sanitize_text_field($_POST['search'] ?? '');

        // Map frontend types to database action types
        $action_map = [
            'uploaded' => 'upload',
            'downloaded' => 'download',
            'edited' => 'edit',
            'deleted' => 'delete'
        ];

        if (!isset($action_map[$log_type])) {
            wp_send_json_error('Invalid log type');
        }

        $db_action = $action_map[$log_type];

        try {
            // Use the core class to get logs
            $core = WPFM_Core::get_instance();
            $logs = $core->get_logs($db_action, $search, 100);

            wp_send_json_success([
                'logs' => $logs,
                'count' => count($logs)
            ]);
        } catch (Exception $e) {
            wp_send_json_error('Error retrieving logs: ' . $e->getMessage());
        }
    }

    private function get_logs_by_type($type, $search = '')
    {
        // Prefer DB logs (WPFM_Logs) if available, otherwise fallback to option-based storage
        $results = array();

        if (isset($this->logs) && method_exists($this->logs, 'get_logs')) {
            // Map types
            $map = array(
                'uploaded' => 'upload',
                'downloaded' => 'download',
                'edited' => 'edit'
            );
            $action = isset($map[$type]) ? $map[$type] : $type;

            $db_rows = $this->logs->get_logs($action, $search);
            if (!empty($db_rows)) {
                foreach ($db_rows as $r) {
                    $results[] = array(
                        'name' => $r->file_name ?? '',
                        'path' => $r->file_path ?? '',
                        'user' => $r->user_name ?? '',
                        'user_id' => $r->user_id ?? 0,
                        'user_name' => $r->user_name ?? '',
                        'time' => isset($r->created_at) ? strtotime($r->created_at) : (isset($r->timestamp) ? intval($r->timestamp) : 0),
                        'timestamp' => isset($r->created_at) ? strtotime($r->created_at) : (isset($r->timestamp) ? intval($r->timestamp) : 0),
                    );
                }
                // newest first
                usort($results, function ($a, $b) {
                    return ($b['timestamp'] ?? 0) <=> ($a['timestamp'] ?? 0);
                });

                return $results;
            }
        }

        // Fallback to option-based logs
        $option_key = 'wpfm_logs_' . $type;
        $logs = get_option($option_key, array());

        // Reverse to show latest first
        $logs = array_reverse($logs);

        // Filter by search term if provided
        if (!empty($search)) {
            $logs = array_filter($logs, function ($log) use ($search) {
                return stripos($log['name'] ?? '', $search) !== false;
            });
        }

        return $logs;
    }

    public function clear_logs()
    {
        $this->verify_nonce();

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $log_type = sanitize_text_field($_POST['log_type'] ?? '');

        // Map frontend types to database action types
        $action_map = [
            'uploaded' => 'upload',
            'downloaded' => 'download',
            'edited' => 'edit',
            'deleted' => 'delete'
        ];

        if (!isset($action_map[$log_type])) {
            wp_send_json_error('Invalid log type');
        }

        $db_action = $action_map[$log_type];

        try {
            // Use the core class to clear logs
            $core = WPFM_Core::get_instance();
            $result = $core->clear_logs($db_action);

            if ($result !== false) {
                wp_send_json_success('Logs cleared successfully');
            } else {
                wp_send_json_error('Failed to clear logs');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error clearing logs: ' . $e->getMessage());
        }
    }

    private function append_log($type, $name, $path)
    {
        $option_key = 'wpfm_logs_' . $type;
        $logs = get_option($option_key, array());
        $user = wp_get_current_user();

        $log_entry = array(
            'name' => $name,
            'path' => $path,
            'user' => $user->user_login,
            'user_id' => $user->ID,
            'user_name' => $user->display_name ?: $user->user_login,
            'time' => current_time('mysql'),
            'timestamp' => current_time('timestamp'),
        );

        array_push($logs, $log_entry);

        // Keep only last 200 entries
        if (count($logs) > 200) {
            $logs = array_slice($logs, -200);
        }

        update_option($option_key, $logs, false);

        // Also persist to DB if logging class is available
        if (isset($this->logs) && method_exists($this->logs, 'add_log')) {
            // Map type names
            $map = array(
                'uploaded' => 'upload',
                'downloaded' => 'download',
                'edited' => 'edit'
            );
            $action = isset($map[$type]) ? $map[$type] : $type;
            $this->logs->add_log($name, $path, $action);
        }

        return true;
    }
}
