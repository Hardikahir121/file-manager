<?php
// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class WPFM_Core
{

    private static $instance = null;
    private $logs;

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
        $this->configure_smtp();
        add_action('init', [$this, 'init']);

        // Register logging hooks
        add_action('wpfm_file_downloaded', [$this, 'handle_file_download_log'], 10, 2);
        add_action('wpfm_file_uploaded', [$this, 'handle_file_upload_log'], 10, 2);
        add_action('wpfm_file_edited', [$this, 'handle_file_edit_log'], 10, 2);
        add_action('wpfm_file_deleted', [$this, 'handle_file_delete_log'], 10, 2);
        
        // Register AJAX handlers for logs
        add_action('wp_ajax_wpfm_get_logs', [$this, 'get_logs_ajax']);
        add_action('wp_ajax_wpfm_clear_logs', [$this, 'clear_logs_ajax']);
        add_action('wp_ajax_wpfm_bulk_delete_logs', [$this, 'bulk_delete_logs_ajax']);
    }

    // Add these methods to trigger the logging actions:
    public function trigger_file_download($filename, $path) {
        if (get_option('wpfm_notify_download') === 'yes') {
            $this->send_notification('download', $filename, $path);
        }
        $this->log_action('download', $filename, $path);
    }

    public function trigger_file_upload($filename, $path) {
        if (get_option('wpfm_notify_upload') === 'yes') {
            $this->send_notification('upload', $filename, $path);
        }
        $this->log_action('upload', $filename, $path);
    }

    public function trigger_file_edit($filename, $path) {
        if (get_option('wpfm_notify_edit') === 'yes') {
            $this->send_notification('edit', $filename, $path);
        }
        $this->log_action('edit', $filename, $path);
    }

    public function trigger_file_delete($filename, $path) {
        if (get_option('wpfm_notify_delete') === 'yes') {
            $this->send_notification('delete', $filename, $path);
        }
        $this->log_action('delete', $filename, $path);
    }

    // Update the log handler methods:
    public function handle_file_download_log($file_name, $file_path) {
        $this->log_file_action($file_name, $file_path, 'download');
    }

    public function handle_file_upload_log($file_name, $file_path) {
        $this->log_file_action($file_name, $file_path, 'upload');
    }

    public function handle_file_edit_log($file_name, $file_path) {
        $this->log_file_action($file_name, $file_path, 'edit');
    }

    public function handle_file_delete_log($file_name, $file_path) {
        $this->log_file_action($file_name, $file_path, 'delete');
    }

    public function init()
    {
        // Initialize core functionality
        $this->register_hooks();
    }

    private function configure_smtp() {
        // Only configure SMTP if enabled
        if (get_option('wpfm_smtp_enable', 'no') !== 'yes') {
            return;
        }
    
        add_action('phpmailer_init', function($phpmailer) {
            $host = get_option('wpfm_smtp_host', '');
            $username = get_option('wpfm_smtp_username', '');
            $password = get_option('wpfm_smtp_password', '');
            
            // Only configure if we have the required settings
            if (empty($host) || empty($username) || empty($password)) {
                error_log('WP File Manager: SMTP enabled but missing required settings');
                return;
            }
    
            $phpmailer->isSMTP();
            $phpmailer->Host = $host;
            $phpmailer->SMTPAuth = true;
            $phpmailer->Port = get_option('wpfm_smtp_port', 587);
            $phpmailer->Username = $username;
            $phpmailer->Password = $password;
            
            $encryption = get_option('wpfm_smtp_encryption', 'tls');
            if (!empty($encryption)) {
                $phpmailer->SMTPSecure = $encryption;
            }
            
            $from_email = get_option('wpfm_smtp_from_email', '');
            $from_name = get_option('wpfm_smtp_from_name', '');
            
            if (!empty($from_email) && is_email($from_email)) {
                $phpmailer->From = $from_email;
                $phpmailer->FromName = $from_name ?: get_bloginfo('name');
            }
        });
    }

    public function send_notification($action, $filename, $path) {
        // Check if email notifications are enabled globally (MASTER SWITCH)
        if (get_option('wpfm_email_notify', 'no') !== 'yes') {
            return; // Exit if master switch is off
        }
        
        // Check if specific notification type is enabled
        $option_name = 'wpfm_notify_' . $action;
        if (get_option($option_name, 'no') !== 'yes') {
            return; // Exit if this specific notification is disabled
        }
    
        $user = wp_get_current_user();
        $admin_email = get_option('wpfm_notify_email', get_option('admin_email'));
        
        // Validate email
        if (empty($admin_email) || !is_email($admin_email)) {
            error_log('WP File Manager: Invalid notification email address: ' . $admin_email);
            return;
        }
    
        $site_name = get_bloginfo('name');
        $action_labels = array(
            'upload' => 'uploaded',
            'download' => 'downloaded', 
            'edit' => 'edited',
            'delete' => 'deleted'
        );
    
        $label = isset($action_labels[$action]) ? $action_labels[$action] : $action;
        $subject = sprintf('[%s] File %s', $site_name, $label);
        
        $message = $this->build_action_email_html($action, $filename, $path, $user);
        $headers = array('Content-Type: text/html; charset=UTF-8');
    
        // Configure SMTP if enabled
        $this->configure_smtp();
    
        // Send email
        $result = wp_mail($admin_email, $subject, $message, $headers);
        
        if ($result) {
            error_log('WP File Manager: Notification email sent for ' . $action . ' - ' . $filename);
        } else {
            error_log('WP File Manager: Failed to send notification email for ' . $action);
        }
    }

    // Public HTML template builder for email notifications
    public function build_action_email_html($action, $filename, $path, $user) {
        $site_name = esc_html(get_bloginfo('name'));
        $site_url = esc_url(get_site_url());
        $user_name = esc_html($user->display_name ?: $user->user_login);
        $user_email = esc_html($user->user_email);
        $ip = esc_html($_SERVER['REMOTE_ADDR'] ?? 'Unknown');
        $ua = esc_html($_SERVER['HTTP_USER_AGENT'] ?? '');
        $when = esc_html(date('Y-m-d H:i:s'));
        $safe_file = esc_html($filename);
        $safe_path = esc_html($path);

        $action_titles = array(
            'upload' => 'File Uploaded',
            'download' => 'File Downloaded',
            'edit' => 'File Edited',
            'delete' => 'File Deleted'
        );
        $title = isset($action_titles[$action]) ? $action_titles[$action] : ucfirst($action);

        $html = '<!doctype html>
<html>
<head>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
  <title>' . $title . ' - ' . $site_name . '</title>
  <style>
    body { background:#f5f7fb; margin:0; padding:0; font-family: -apple-system,BlinkMacSystemFont,Segoe UI,Roboto,Helvetica,Arial,sans-serif; }
    .container { max-width:640px; margin:0 auto; padding:24px 16px; }
    .card { background:#ffffff; border-radius:10px; box-shadow:0 2px 8px rgba(16,24,40,0.08); overflow:hidden; }
    .header { background:#1e40af; color:#fff; padding:16px 20px; }
    .header h1 { margin:0; font-size:18px; }
    .content { padding:20px; color:#111827; }
    .meta { width:100%; border-collapse:collapse; }
    .meta th, .meta td { text-align:left; padding:10px 0; border-bottom:1px solid #f0f2f5; font-size:14px; }
    .meta th { color:#6b7280; width:140px; font-weight:600; padding-right:12px; }
    .footer { color:#6b7280; font-size:12px; padding:16px 20px; background:#f9fafb; }
    a { color:#1e40af; text-decoration:none; }
  </style>
  <!--[if mso]><style>.meta { font-family: Arial, sans-serif !important; }</style><![endif]-->
  </head>
<body>
  <div class="container">
    <div class="card">
      <div class="header">
        <h1>' . $title . '</h1>
      </div>
      <div class="content">
        <p>This is to inform you that a file action occurred on <strong>' . $site_name . '</strong>.</p>
        <table class="meta" role="presentation" cellpadding="0" cellspacing="0">
          <tr><th>File</th><td>' . $safe_file . '</td></tr>
          <tr><th>Location</th><td>' . $safe_path . '</td></tr>
          <tr><th>Action</th><td>' . esc_html(ucfirst($action)) . '</td></tr>
          <tr><th>User</th><td>' . $user_name . ' (' . $user_email . ')</td></tr>
          <tr><th>Date</th><td>' . $when . '</td></tr>
          <tr><th>IP</th><td>' . $ip . '</td></tr>
          <tr><th>User Agent</th><td>' . $ua . '</td></tr>
        </table>
        <p style="margin-top:16px; color:#374151;">You are receiving this email because notifications are enabled in WP File Manager settings.</p>
      </div>
      <div class="footer">
        <div>
          <a href="' . $site_url . '">' . $site_name . '</a> • WP File Manager
        </div>
      </div>
    </div>
  </div>
</body>
</html>';

        return $html;
    }

    private function log_action($action, $filename, $path) {
        global $wpdb;
        
        $user = wp_get_current_user();
        
        $wpdb->insert(
            $wpdb->prefix . 'wpfm_logs',
            array(
                'file_name' => $filename,
                'file_path' => $path,
                'action' => $action,
                'user_id' => $user->ID,
                'user_name' => $user->display_name ?: $user->user_login,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => current_time('mysql')
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s')
        );
    }

    public function create_logs_table()
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

    private function register_hooks()
    {
        // Shortcode for frontend file manager
        add_shortcode('wp_file_manager', [$this, 'file_manager_shortcode']);

        // Custom capabilities
        add_filter('user_has_cap', [$this, 'add_custom_capabilities'], 10, 4);

        // Admin bar menu
        add_action('admin_bar_menu', [$this, 'add_admin_bar_menu'], 100);
    }

    public function file_manager_shortcode($atts)
    {
        if (!is_user_logged_in()) {
            return '<p>Please log in to access the file manager.</p>';
        }

        $atts = shortcode_atts([
            'view' => 'grid',
            'theme' => 'light',
            'upload' => 'yes'
        ], $atts);

        // Check if user has access
        $user = wp_get_current_user();
        $allowed_roles = get_option('wpfm_file_roles', ['administrator']);

        if (!array_intersect($user->roles, $allowed_roles)) {
            return '<p>You do not have permission to access the file manager.</p>';
        }

        ob_start();
?>
        <div class="wpfm-frontend" data-view="<?php echo esc_attr($atts['view']); ?>" data-theme="<?php echo esc_attr($atts['theme']); ?>">
            <div class="wpfm-frontend-container">
                <!-- Frontend file manager interface would go here -->
                <p>File Manager Frontend Interface</p>
            </div>
        </div>
<?php
        return ob_get_clean();
    }

    public function test_email_delivery() {
        $to = get_option('wpfm_notify_email', get_option('admin_email'));
        $subject = 'WP File Manager - Test Email';
        $message = 'This is a test email from WP File Manager to verify email delivery.';
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        $result = wp_mail($to, $subject, $message, $headers);
        
        if ($result) {
            error_log('WP File Manager: Test email sent successfully to ' . $to);
            return true;
        } else {
            error_log('WP File Manager: Test email FAILED to send to ' . $to);
            return false;
        }
    }

    public function add_custom_capabilities($allcaps, $caps, $args, $user)
    {
        $file_roles = get_option('wpfm_file_roles', ['administrator']);
        $db_roles = get_option('wpfm_db_roles', ['administrator']);

        // Add file manager capability
        if (in_array('access_file_manager', $caps)) {
            if (array_intersect($user->roles, $file_roles)) {
                $allcaps['access_file_manager'] = true;
            }
        }

        // Add database manager capability
        if (in_array('access_db_manager', $caps)) {
            if (array_intersect($user->roles, $db_roles)) {
                $allcaps['access_db_manager'] = true;
            }
        }

        return $allcaps;
    }

    public function log_file_action($file_name, $file_path, $action)
    {
        global $wpdb;

        $user_id = get_current_user_id();
        $user_info = get_userdata($user_id);
        $user_name = $user_info ? $user_info->display_name : 'Guest';

        $table_name = $wpdb->prefix . 'wpfm_logs';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return false;
        }

        $result = $wpdb->insert(
            $table_name,
            array(
                'file_name' => $file_name,
                'file_path' => $file_path,
                'action' => $action,
                'user_id' => $user_id,
                'user_name' => $user_name,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ),
            array('%s', '%s', '%s', '%d', '%s', '%s', '%s')
        );

        return $result !== false;
    }

    public function get_logs_ajax()
    {
        // Verify nonce and permissions
        if (!check_ajax_referer('wpfm_nonce', 'nonce', false)) {
            wp_send_json_error('Security verification failed');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $log_type = sanitize_text_field($_POST['log_type'] ?? '');
        $search = sanitize_text_field($_POST['search'] ?? '');

        // Map frontend tab names to database action types
        $action_map = [
            'edited' => 'edit',
            'downloaded' => 'download',
            'uploaded' => 'upload',
            'deleted' => 'delete'
        ];

        if (!isset($action_map[$log_type])) {
            wp_send_json_error('Invalid log type: ' . $log_type);
        }

        $db_action = $action_map[$log_type];

        try {
            $logs = $this->get_logs_from_db($db_action, $search);

            wp_send_json_success([
                'logs' => $logs,
                'count' => count($logs)
            ]);
        } catch (Exception $e) {
            wp_send_json_error('Error retrieving logs: ' . $e->getMessage());
        }
    }

    // Add this method to get logs for display
    public function get_logs($action_type = '', $search = '', $limit = 100)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpfm_logs';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        // Choose order column based on existing schema (fallback to id if created_at missing)
        $order_col = 'created_at';
        $col_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table_name` LIKE %s", $order_col));
        if (!$col_exists) {
            $order_col = 'id';
        }

        $where_clause = "WHERE 1=1";
        $params = array();

        if (!empty($action_type)) {
            $where_clause .= " AND action = %s";
            $params[] = $action_type;
        }

        if (!empty($search)) {
            $where_clause .= " AND (file_name LIKE %s OR user_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $query = "SELECT * FROM $table_name 
                $where_clause 
                ORDER BY $order_col DESC 
                LIMIT %d";

        $params[] = $limit;

        $query = $wpdb->prepare($query, $params);
        $results = $wpdb->get_results($query);

        $logs = array();
        foreach ($results as $result) {
            $logs[] = array(
                'id' => $result->id,
                'user_id' => $result->user_id,
                'user_name' => $result->user_name,
                'file_name' => $result->file_name,
                'file_path' => $result->file_path,
                'action' => $result->action,
                'ip_address' => $result->ip_address,
                'user_agent' => $result->user_agent,
                'created_at' => isset($result->created_at) ? $result->created_at : null,
                'timestamp' => isset($result->created_at) ? strtotime($result->created_at) : null
            );
        }

        return $logs;
    }

    private function get_logs_from_db($action_type = '', $search = '', $limit = 100)
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpfm_logs';

        // Check if table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return array();
        }
        
        // Determine order column
        $order_col = 'created_at';
        $col_exists = $wpdb->get_var($wpdb->prepare("SHOW COLUMNS FROM `$table_name` LIKE %s", $order_col));
        if (!$col_exists) {
            $order_col = 'id';
        }

        $where_clause = "WHERE 1=1";
        $params = array();

        if (!empty($action_type)) {
            $where_clause .= " AND action = %s";
            $params[] = $action_type;
        }

        if (!empty($search)) {
            $where_clause .= " AND (file_name LIKE %s OR user_name LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $params[] = $search_term;
            $params[] = $search_term;
        }

        $query = "SELECT * FROM $table_name 
                $where_clause 
                ORDER BY $order_col DESC 
                LIMIT %d";

        $params[] = $limit;

        $query = $wpdb->prepare($query, $params);
        $results = $wpdb->get_results($query);

        $logs = array();
        foreach ($results as $result) {
            $logs[] = array(
                'id' => $result->id,
                'user_id' => $result->user_id,
                'user_name' => $result->user_name,
                'file_name' => $result->file_name,
                'file_path' => $result->file_path,
                'action' => $result->action,
                'ip_address' => $result->ip_address,
                'user_agent' => $result->user_agent,
                'created_at' => isset($result->created_at) ? $result->created_at : null,
                'timestamp' => isset($result->created_at) ? strtotime($result->created_at) : null
            );
        }

        return $logs;
    }

    public function bulk_delete_logs_ajax()
    {
        // Verify nonce and permissions
        if (!check_ajax_referer('wpfm_nonce', 'nonce', false)) {
            wp_send_json_error('Security verification failed');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $log_ids = isset($_POST['log_ids']) ? array_map('intval', (array)$_POST['log_ids']) : array();
        $log_type = sanitize_text_field($_POST['log_type'] ?? '');

        if (empty($log_ids)) {
            wp_send_json_error('No logs selected');
        }

        try {
            $result = $this->bulk_delete_logs($log_ids, $log_type);

            if ($result !== false) {
                wp_send_json_success(array(
                    'message' => 'Logs deleted successfully',
                    'deleted_count' => $result
                ));
            } else {
                wp_send_json_error('Failed to delete logs');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error deleting logs: ' . $e->getMessage());
        }
    }

    private function bulk_delete_logs($log_ids, $log_type = '')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpfm_logs';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return false;
        }

        // Convert IDs to comma-separated string for IN clause
        $ids_placeholder = implode(',', array_fill(0, count($log_ids), '%d'));
        
        if (!empty($log_type)) {
            $query = $wpdb->prepare(
                "DELETE FROM $table_name WHERE id IN ($ids_placeholder) AND action = %s",
                array_merge($log_ids, [$log_type])
            );
        } else {
            $query = $wpdb->prepare(
                "DELETE FROM $table_name WHERE id IN ($ids_placeholder)",
                $log_ids
            );
        }

        return $wpdb->query($query);
    }

    public function clear_logs_ajax()
    {
        // Verify nonce and permissions
        if (!check_ajax_referer('wpfm_nonce', 'nonce', false)) {
            wp_send_json_error('Security verification failed');
        }

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Permission denied');
        }

        $log_type = sanitize_text_field($_POST['log_type'] ?? '');

        // Map frontend tab names to database action types
        $action_map = [
            'edited' => 'edit',
            'downloaded' => 'download',
            'uploaded' => 'upload',
            'deleted' => 'delete'
        ];

        if (!isset($action_map[$log_type])) {
            wp_send_json_error('Invalid log type: ' . $log_type);
        }

        $db_action = $action_map[$log_type];

        try {
            $result = $this->clear_logs_from_db($db_action);

            if ($result !== false) {
                wp_send_json_success('Logs cleared successfully');
            } else {
                wp_send_json_error('Failed to clear logs');
            }
        } catch (Exception $e) {
            wp_send_json_error('Error clearing logs: ' . $e->getMessage());
        }
    }

    private function clear_logs_from_db($action_type = '')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpfm_logs';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return false;
        }

        if (!empty($action_type)) {
            return $wpdb->delete(
                $table_name,
                array('action' => $action_type),
                array('%s')
            );
        } else {
            return $wpdb->query("TRUNCATE TABLE $table_name");
        }
    }

    // Add this method to clear logs
    public function clear_logs($action_type = '')
    {
        global $wpdb;

        $table_name = $wpdb->prefix . 'wpfm_logs';

        if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
            return false;
        }

        if (!empty($action_type)) {
            return $wpdb->delete(
                $table_name,
                array('action' => $action_type),
                array('%s')
            );
        } else {
            return $wpdb->query("TRUNCATE TABLE $table_name");
        }
    }

    public function add_admin_bar_menu($admin_bar)
    {
        $user = wp_get_current_user();
        $file_roles = get_option('wpfm_file_roles', ['administrator']);
        $db_roles = get_option('wpfm_db_roles', ['administrator']);

        if (array_intersect($user->roles, $file_roles) || array_intersect($user->roles, $db_roles)) {
            $admin_bar->add_menu([
                'id'    => 'wp-file-manager',
                'title' => 'File Manager',
                'href'  => admin_url('admin.php?page=wp-file-manager'),
                'meta'  => [
                    'title' => 'File Manager',
                ],
            ]);

            if (array_intersect($user->roles, $file_roles)) {
                $admin_bar->add_menu([
                    'id'     => 'wp-file-manager-files',
                    'parent' => 'wp-file-manager',
                    'title'  => 'File Manager',
                    'href'   => admin_url('admin.php?page=wp-file-manager'),
                    'meta'   => [
                        'title' => 'Manage Files',
                    ],
                ]);
            }

            if (array_intersect($user->roles, $db_roles)) {
                $admin_bar->add_menu([
                    'id'     => 'wp-file-manager-db',
                    'parent' => 'wp-file-manager',
                    'title'  => 'Database Manager',
                    'href'   => admin_url('admin.php?page=wp-db-manager'),
                    'meta'   => [
                        'title' => 'Manage Database',
                    ],
                ]);
            }
        }
    }

    public function get_file_icon($filename)
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $icons = [
            'folder' => 'bi-folder-fill text-yellow-500',
            'image' => 'bi-image text-green-500',
            'pdf' => 'bi-file-earmark-pdf text-red-500',
            'word' => 'bi-file-earmark-word text-blue-500',
            'excel' => 'bi-file-earmark-excel text-green-600',
            'powerpoint' => 'bi-file-earmark-ppt text-orange-500',
            'archive' => 'bi-file-earmark-zip text-purple-500',
            'code' => 'bi-file-earmark-code text-gray-600',
            'text' => 'bi-file-earmark-text text-gray-500',
            'audio' => 'bi-file-earmark-music text-pink-500',
            'video' => 'bi-file-earmark-play text-indigo-500',
            'default' => 'bi-file-earmark text-gray-400'
        ];

        $image_exts = ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp'];
        $doc_exts = ['doc', 'docx'];
        $excel_exts = ['xls', 'xlsx'];
        $ppt_exts = ['ppt', 'pptx'];
        $archive_exts = ['zip', 'rar', 'tar', 'gz', '7z'];
        $code_exts = ['php', 'js', 'css', 'html', 'xml', 'json', 'sql'];
        $text_exts = ['txt', 'rtf', 'md'];
        $audio_exts = ['mp3', 'wav', 'ogg', 'm4a'];
        $video_exts = ['mp4', 'avi', 'mov', 'wmv', 'flv'];

        if (is_dir(WPFM_UPLOAD_DIR . $filename)) {
            return $icons['folder'];
        }

        if (in_array($ext, $image_exts)) return $icons['image'];
        if ($ext === 'pdf') return $icons['pdf'];
        if (in_array($ext, $doc_exts)) return $icons['word'];
        if (in_array($ext, $excel_exts)) return $icons['excel'];
        if (in_array($ext, $ppt_exts)) return $icons['powerpoint'];
        if (in_array($ext, $archive_exts)) return $icons['archive'];
        if (in_array($ext, $code_exts)) return $icons['code'];
        if (in_array($ext, $text_exts)) return $icons['text'];
        if (in_array($ext, $audio_exts)) return $icons['audio'];
        if (in_array($ext, $video_exts)) return $icons['video'];

        return $icons['default'];
    }

    public function format_file_size($bytes)
    {
        if ($bytes == 0) return '0 B';

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        $size = round($bytes / pow(1024, $i), 2);

        return $size . ' ' . $units[$i];
    }

    public function sanitize_filename($filename)
    {
        $filename = preg_replace('/[^a-zA-Z0-9\.\-\_]/', '_', $filename);
        return sanitize_file_name($filename);
    }

    public function get_upload_limit()
    {
        $max_upload = get_option('wpfm_max_upload', 25);
        $wp_max_upload = wp_max_upload_size();
        $custom_max_upload = $max_upload * 1024 * 1024;

        return min($wp_max_upload, $custom_max_upload);
    }
}
