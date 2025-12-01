<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPFM_Logs
{
    private static $instance = null;
    private $table_name;

    public static function get_instance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    private function __construct()
    {
        global $wpdb;
        $this->table_name = $wpdb->prefix . 'wpfm_logs';
    }

    public function create_table()
    {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS {$this->table_name} (
            id bigint(20) NOT NULL AUTO_INCREMENT,
            file_name varchar(255) NOT NULL,
            file_path text NOT NULL,
            action varchar(50) NOT NULL,
            user_id bigint(20) NOT NULL,
            ip_address varchar(100) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id)
        ) $charset_collate;";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    public function add_log($file_name, $file_path, $action)
    {
        global $wpdb;

        return $wpdb->insert(
            $this->table_name,
            array(
                'file_name' => $file_name,
                'file_path' => $file_path,
                'action' => $action,
                'user_id' => get_current_user_id(),
                'ip_address' => $this->get_client_ip()
            ),
            array('%s', '%s', '%s', '%d', '%s')
        );
    }

    public function get_logs($action = '', $search = '')
    {
        global $wpdb;

        $sql = "SELECT l.*, u.display_name as user_name 
                FROM {$this->table_name} l 
                LEFT JOIN {$wpdb->users} u ON l.user_id = u.ID 
                WHERE 1=1";

        if ($action) {
            $sql .= $wpdb->prepare(" AND l.action = %s", $action);
        }

        if ($search) {
            $sql .= $wpdb->prepare(" AND l.file_name LIKE %s", '%' . $wpdb->esc_like($search) . '%');
        }

        $sql .= " ORDER BY l.created_at DESC LIMIT 100";

        return $wpdb->get_results($sql);
    }

    private function get_client_ip()
    {
        $ip = '';
        if (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return sanitize_text_field($ip);
    }
}
