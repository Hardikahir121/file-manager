<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Ensure WordPress functions are available
require_once(ABSPATH . 'wp-includes/pluggable.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/misc.php');

class WPFM_Security
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
        $this->init_security();
    }

    private function init_security()
    {
        // Add security headers
        add_action('send_headers', array($this, 'add_security_headers'));

        // Validate backup/restore operations
        add_filter('wpfm_validate_backup_operation', array($this, 'validate_backup_operation'), 10, 2);
        add_filter('wpfm_validate_restore_operation', array($this, 'validate_restore_operation'), 10, 2);

        // File upload security
        add_filter('wp_handle_upload_prefilter', [$this, 'validate_file_upload']);

        // SQL injection prevention
        add_filter('query', [$this, 'sanitize_query']);
    }

    /**
     * Validate backup operation - moved out of init_security to be a proper class method
     */
    public function validate_backup_operation($valid, $args)
    {
        // Check user capability
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            return false;
        }

        // Verify nonce
        if (!isset($args['nonce']) || !wp_verify_nonce($args['nonce'], 'wpfm_backup_nonce')) {
            return false;
        }

        // Check backup directory
        $backup_dir = defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/wpfm-backups' : wp_upload_dir()['basedir'] . '/wpfm-backups';
        if (!file_exists($backup_dir)) {
            if (!wp_mkdir_p($backup_dir) && !mkdir($backup_dir, 0755, true)) {
                return false;
            }
        }

        if (!is_writable($backup_dir)) {
            return false;
        }

        // Check disk space (50MB minimum)
        if (function_exists('disk_free_space')) {
            $free_space = @disk_free_space($backup_dir);
            if ($free_space !== false && $free_space < 52428800) {
                return false;
            }
        }

        return true;
    }

    /**
     * Validate restore operation - moved out of init_security to be a proper class method
     */
    public function validate_restore_operation($valid, $args)
    {
        // Check user capability
        if (!function_exists('current_user_can') || !current_user_can('manage_options')) {
            return false;
        }

        // Verify nonce
        if (!isset($args['nonce']) || !wp_verify_nonce($args['nonce'], 'wpfm_restore_nonce')) {
            return false;
        }

        // Validate backup file
        if (!isset($args['backup_file'])) {
            return false;
        }

        $backup_file = (defined('WP_CONTENT_DIR') ? WP_CONTENT_DIR . '/wpfm-backups/' : wp_upload_dir()['basedir'] . '/') . basename($args['backup_file']);

        if (!file_exists($backup_file) || !is_readable($backup_file)) {
            return false;
        }

        // Validate file extension
        if (!preg_match('/\.(zip|sql)$/', $backup_file)) {
            return false;
        }

        return true;
    }

    public function add_security_headers()
    {
        if (isset($_GET['page']) && strpos($_GET['page'], 'wp-file-manager') !== false) {
            header('X-Content-Type-Options: nosniff');
            header('X-Frame-Options: SAMEORIGIN');
            header('X-XSS-Protection: 1; mode=block');
        }
    }

    public function validate_file_upload($file)
    {
        // Check if this is a file manager upload
        if (!isset($_POST['action']) || $_POST['action'] !== 'wpfm_handle_request') {
            return $file;
        }

        $max_size = WPFM_Core::get_instance()->get_upload_limit();

        // Check file size
        if ($file['size'] > $max_size) {
            $file['error'] = sprintf(
                'File size exceeds maximum allowed size of %s',
                size_format($max_size)
            );
            return $file;
        }

        // Check file extension
        $allowed_extensions = $this->get_allowed_extensions();
        $file_extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($file_extension, $allowed_extensions)) {
            $file['error'] = 'File type not allowed';
            return $file;
        }

        // Check for PHP files in disguise
        if ($this->is_malicious_file($file['tmp_name'], $file_extension)) {
            $file['error'] = 'Malicious file detected';
            return $file;
        }

        return $file;
    }

    public function sanitize_query($query)
    {
        // Basic SQL injection prevention for file manager queries
        if (isset($_POST['action_type']) && $_POST['action_type'] === 'run_query') {
            // Remove common SQL injection patterns
            $patterns = [
                '/\/\*.*\*\//',
                '/--.*/',
                '/#.*/',
                '/;.*/',
                '/union.*select/i',
                '/insert.*into/i',
                '/drop.*table/i',
                '/delete.*from/i',
                '/update.*set/i'
            ];

            $query = preg_replace($patterns, '', $query);
        }

        return $query;
    }

    private function get_allowed_extensions()
    {
        return [
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
            'sql',
            // Other
            'epub',
            'mobi'
        ];
    }

    private function is_malicious_file($file_path, $extension)
    {
        // Check for PHP files disguised as other file types
        if ($extension !== 'php' && $extension !== 'phtml') {
            $content = file_get_contents($file_path, false, null, 0, 100);

            // Check for PHP tags
            if (strpos($content, '<?php') !== false) {
                return true;
            }

            // Check for PHP short tags
            if (strpos($content, '<?=') !== false) {
                return true;
            }
        }

        return false;
    }

    public function sanitize_path($path)
    {
        // Remove null bytes
        $path = str_replace(chr(0), '', $path);

        // Normalize path separators
        $path = str_replace('\\', '/', $path);

        // Remove directory traversal attempts
        $path = preg_replace('/\.\.\//', '', $path);
        $path = preg_replace('/\.\.\\\\/', '', $path);

        // Ensure path stays within upload directory
        $base_path = realpath(WPFM_UPLOAD_DIR);
        $full_path = realpath(WPFM_UPLOAD_DIR . ltrim($path, '/'));

        if ($full_path === false || strpos($full_path, $base_path) !== 0) {
            return false;
        }

        return $path;
    }

    public function validate_sql_query($query)
    {
        $restricted_patterns = [
            '/drop\s+table/i',
            '/truncate\s+table/i',
            '/alter\s+table/i',
            '/create\s+table/i',
            '/insert\s+into/i',
            '/update\s+.+set/i',
            '/delete\s+from/i',
            '/grant\s+/i',
            '/revoke\s+/i',
            '/flush\s+/i'
        ];

        foreach ($restricted_patterns as $pattern) {
            if (preg_match($pattern, $query)) {
                return false;
            }
        }

        return true;
    }

    public function log_security_event($event, $details = [])
    {
        $log_entry = [
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'event' => $event,
            'details' => $details,
            'ip' => $this->get_client_ip()
        ];

        $log_file = WPFM_UPLOAD_DIR . 'security.log';
        $log_line = json_encode($log_entry) . PHP_EOL;

        @file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);
    }

    private function get_client_ip()
    {
        $ip_keys = [
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];

        foreach ($ip_keys as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = $_SERVER[$key];
                if (strpos($ip, ',') !== false) {
                    $ips = explode(',', $ip);
                    $ip = trim($ips[0]);
                }
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }
}
