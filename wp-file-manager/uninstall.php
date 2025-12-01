<?php
// Prevent direct access
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Clean up options
$options = [
    'wpfm_file_roles',
    'wpfm_db_roles',
    'wpfm_max_upload',
    'wpfm_language',
    'wpfm_theme',
    'wpfm_view',
    'wpfm_email_notify'
];

foreach ($options as $option) {
    delete_option($option);
}

// Remove upload directory
$upload_dir = WP_CONTENT_DIR . '/uploads/wpfm/';
if (file_exists($upload_dir)) {
    // Recursive directory removal function
    function wpfm_remove_directory($dir)
    {
        if (!is_dir($dir)) return false;
        $items = array_diff(scandir($dir), ['.', '..']);
        foreach ($items as $item) {
            $path = $dir . '/' . $item;
            is_dir($path) ? wpfm_remove_directory($path) : unlink($path);
        }
        return rmdir($dir);
    }

    wpfm_remove_directory($upload_dir);
}
