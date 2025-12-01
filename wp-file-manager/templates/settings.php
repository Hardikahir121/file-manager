<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">File Manager Settings</h1>

    <form method="post" class="space-y-6">
        <?php wp_nonce_field('wpfm_settings_nonce'); ?>

        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Access Control</h2>

            <!-- File Manager Roles -->
            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    User Roles with File Manager Access
                </label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <?php
                    $file_roles = get_option('wpfm_file_roles', ['administrator']);
                    $roles = get_editable_roles();

                    foreach ($roles as $role_key => $role_info) {
                        $checked = in_array($role_key, $file_roles) ? 'checked' : '';
                        echo '
                        <label class="flex items-center space-x-2 p-2 hover:bg-gray-50 rounded">
                            <input type="checkbox" name="file_roles[]" value="' . esc_attr($role_key) . '" ' . $checked . ' 
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm">' . esc_html($role_info['name']) . '</span>
                        </label>';
                    }
                    ?>
                </div>
            </div>

            <!-- DB Manager Roles -->
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    User Roles with Database Manager Access
                </label>
                <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
                    <?php
                    $db_roles = get_option('wpfm_db_roles', ['administrator']);

                    foreach ($roles as $role_key => $role_info) {
                        $checked = in_array($role_key, $db_roles) ? 'checked' : '';
                        echo '
                        <label class="flex items-center space-x-2 p-2 hover:bg-gray-50 rounded">
                            <input type="checkbox" name="db_roles[]" value="' . esc_attr($role_key) . '" ' . $checked . '
                                class="rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                            <span class="text-sm">' . esc_html($role_info['name']) . '</span>
                        </label>';
                    }
                    ?>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">File Manager Settings</h2>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Max Upload Size -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Maximum Upload Size (MB)
                    </label>
                    <input type="number" name="max_upload" value="<?php echo esc_attr(get_option('wpfm_max_upload', 25)); ?>"
                        min="1" max="1000" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
                </div>

                <!-- Language -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        File Manager Language
                    </label>
                    <select name="language" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <?php
                        $languages = [
                            'en' => 'English',
                            'es' => 'Spanish',
                            'fr' => 'French',
                            'de' => 'German',
                            'it' => 'Italian',
                            'pt' => 'Portuguese'
                        ];
                        $current_lang = get_option('wpfm_language', 'en');

                        foreach ($languages as $key => $label) {
                            $selected = $current_lang === $key ? 'selected' : '';
                            echo "<option value='$key' $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Theme -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        File Manager Theme
                    </label>
                    <select name="theme" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <?php
                        $themes = [
                            'light' => 'Light',
                            'dark' => 'Dark',
                            'blue' => 'Blue',
                            'green' => 'Green'
                        ];
                        $current_theme = get_option('wpfm_theme', 'light');

                        foreach ($themes as $key => $label) {
                            $selected = $current_theme === $key ? 'selected' : '';
                            echo "<option value='$key' $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>

                <!-- Files View -->
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">
                        Default Files View
                    </label>
                    <select name="view" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
                        <?php
                        $views = [
                            'grid' => 'Grid View',
                            'list' => 'List View'
                        ];
                        $current_view = get_option('wpfm_view', 'grid');

                        foreach ($views as $key => $label) {
                            $selected = $current_view === $key ? 'selected' : '';
                            echo "<option value='$key' $selected>$label</option>";
                        }
                        ?>
                    </select>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Notifications & Logs</h2>

            <div class="mb-6">
                <label class="block text-sm font-medium text-gray-700 mb-2">
                    Enable Email Notifications
                </label>
                <div class="flex space-x-4">
                    <?php
                    $email_notify = get_option('wpfm_email_notify', 'no');
                    ?>
                    <label class="flex items-center">
                        <input type="radio" name="wpfm_email_notify" value="yes"
                            <?php checked($email_notify, 'yes'); ?> class="mr-2">
                        <span class="text-sm">Enable All Notifications</span>
                    </label>
                    <label class="flex items-center">
                        <input type="radio" name="wpfm_email_notify" value="no"
                            <?php checked($email_notify, 'no'); ?> class="mr-2">
                        <span class="text-sm">Disable All Notifications</span>
                    </label>
                </div>
                <p class="text-sm text-gray-500 mt-1">Master switch for all email notifications</p>
            </div>

            <div class="grid grid-cols-1 gap-6">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-2">Notification Email</label>
                    <input type="email" name="wpfm_notify_email" value="<?php echo esc_attr(get_option('wpfm_notify_email', get_option('admin_email'))); ?>" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="admin@example.com" />
                    <p class="text-xs text-gray-500 mt-1">Emails will be sent using wp_mail(). Ensure wp_mail() is configured.</p>
                </div>

                <div class="space-y-4">
                    <label class="flex items-start space-x-3">
                        <input type="checkbox" name="wpfm_notify_upload" value="yes" <?php checked(get_option('wpfm_notify_upload', 'no'), 'yes'); ?> class="mt-1">
                        <span>
                            <span class="font-medium">Send Notifications to admin and save logs on file upload?</span>
                            <span class="block text-sm text-gray-500">Check to allow file upload notifications. Mail will be sent to admin. Note: This feature is using wp_mail(), Make sure wp_mail is working.</span>
                        </span>
                    </label>

                    <label class="flex items-start space-x-3">
                        <input type="checkbox" name="wpfm_notify_download" value="yes" <?php checked(get_option('wpfm_notify_download', 'no'), 'yes'); ?> class="mt-1">
                        <span>
                            <span class="font-medium">Send Notifications to admin and save logs on file download?</span>
                            <span class="block text-sm text-gray-500">Check to allow file download notifications. Mail will be sent to admin. Note: This feature is using wp_mail(), Make sure wp_mail is working.</span>
                        </span>
                    </label>

                    <label class="flex items-start space-x-3">
                        <input type="checkbox" name="wpfm_syntax_check" value="yes" <?php checked(get_option('wpfm_syntax_check', 'no'), 'yes'); ?> class="mt-1">
                        <span>
                            <span class="font-medium">Check PHP syntax while editing</span>
                            <span class="block text-sm text-gray-500">When enabled, the editor will lint PHP content and highlight syntax errors.</span>
                        </span>
                    </label>

                    <label class="flex items-start space-x-3">
                        <input type="checkbox" name="wpfm_notify_edit" value="yes" <?php checked(get_option('wpfm_notify_edit', 'no'), 'yes'); ?> class="mt-1">
                        <span>
                            <span class="font-medium">Send Notifications to admin and save logs on file edit?</span>
                            <span class="block text-sm text-gray-500">Check to allow file edit notifications. Mail will be sent to admin. Note: This feature is using wp_mail(), Make sure wp_mail is working.</span>
                        </span>
                    </label>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow-sm border p-6">
            <h2 class="text-xl font-semibold mb-4 text-gray-800">Email SMTP Settings</h2>
            
            <div class="mb-4">
                <label class="flex items-start space-x-3">
                    <input type="checkbox" name="wpfm_smtp_enable" value="yes" 
                        <?php checked(get_option('wpfm_smtp_enable', 'no'), 'yes'); ?> 
                        class="mt-1" id="smtp_enable">
                    <span>
                        <span class="font-medium">Enable Custom SMTP Configuration</span>
                        <span class="block text-sm text-gray-500">Use custom SMTP settings instead of server default mail configuration</span>
                    </span>
                </label>
            </div>

            <div id="smtp_settings" class="space-y-4 <?php echo get_option('wpfm_smtp_enable', 'no') === 'yes' ? '' : 'hidden'; ?>">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Host</label>
                        <input type="text" name="wpfm_smtp_host" 
                            value="<?php echo esc_attr(get_option('wpfm_smtp_host', 'smtp.gmail.com')); ?>"
                            class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
                            placeholder="smtp.gmail.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Port</label>
                        <input type="number" name="wpfm_smtp_port" 
                            value="<?php echo esc_attr(get_option('wpfm_smtp_port', '587')); ?>"
                            class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
                            placeholder="587">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Username</label>
                        <input type="text" name="wpfm_smtp_username" 
                            value="<?php echo esc_attr(get_option('wpfm_smtp_username', '')); ?>"
                            class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
                            placeholder="your-email@gmail.com">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">SMTP Password</label>
                        <input type="password" name="wpfm_smtp_password" 
                            value="<?php echo esc_attr(get_option('wpfm_smtp_password', '')); ?>"
                            class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
                            placeholder="Your SMTP password">
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Encryption</label>
                        <select name="wpfm_smtp_encryption" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
                            <?php
                            $encryption = get_option('wpfm_smtp_encryption', 'tls');
                            $options = [
                                '' => 'None',
                                'ssl' => 'SSL',
                                'tls' => 'TLS'
                            ];
                            foreach ($options as $value => $label) {
                                $selected = $encryption === $value ? 'selected' : '';
                                echo "<option value='$value' $selected>$label</option>";
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">From Email</label>
                        <input type="email" name="wpfm_smtp_from_email" 
                            value="<?php echo esc_attr(get_option('wpfm_smtp_from_email', get_option('admin_email'))); ?>"
                            class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
                            placeholder="from@example.com">
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From Name</label>
                    <input type="text" name="wpfm_smtp_from_name" 
                        value="<?php echo esc_attr(get_option('wpfm_smtp_from_name', get_bloginfo('name'))); ?>"
                        class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500"
                        placeholder="Your Site Name">
                </div>

                <!-- Test SMTP Button -->
                <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                    <h3 class="font-medium text-gray-800 mb-2">Test SMTP Configuration</h3>
                    <p class="text-sm text-gray-600 mb-3">Send a test email to verify your SMTP settings are working correctly.</p>
                    <button type="button" id="test_smtp" 
                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded text-sm font-medium">
                        Send Test Email
                    </button>
                    <div id="smtp_test_result" class="mt-2 text-sm hidden"></div>
                </div>
            </div>
        </div>

        <div class="flex justify-end space-x-3">
            <button type="submit" name="save_settings" class="bg-blue-500 hover:bg-blue-600 text-white px-6 py-2 rounded-lg font-medium">
                Save Settings
            </button>
        </div>
    </form>
</div>

<script>
jQuery(document).ready(function($) {
    // Toggle SMTP settings visibility
    $('#smtp_enable').change(function() {
        if ($(this).is(':checked')) {
            $('#smtp_settings').slideDown();
        } else {
            $('#smtp_settings').slideUp();
        }
    });

    // Notification toggle functionality - ONLY target notification checkboxes
    const enableAll = $('input[value="Enable All Notifications"]');
    const disableAll = $('input[value="Disable All Notifications"]');
    
    // More specific selector - only target the 3 notification checkboxes
    const notificationCheckboxes = $('input[type="checkbox"]').filter(function() {
        return $(this).closest('li').length > 0 || 
               $(this).parent().text().includes('Send Notifications');
    });
    
    enableAll.on('click', function() {
        notificationCheckboxes.prop('checked', true);
    });
    
    disableAll.on('click', function() {
        notificationCheckboxes.prop('checked', false);
    });

    // Test SMTP configuration
    $('#test_smtp').click(function() {
        const $button = $(this);
        const $result = $('#smtp_test_result');
        
        $button.prop('disabled', true).text('Sending Test...');
        $result.removeClass('hidden').html('<div class="text-blue-600">Sending test email...</div>');

        // Get the current form data
        const formData = new FormData();
        formData.append('action', 'wpfm_test_smtp');
        formData.append('nonce', '<?php echo wp_create_nonce('wpfm_nonce'); ?>');
        formData.append('smtp_host', $('input[name="wpfm_smtp_host"]').val());
        formData.append('smtp_port', $('input[name="wpfm_smtp_port"]').val());
        formData.append('smtp_username', $('input[name="wpfm_smtp_username"]').val());
        formData.append('smtp_password', $('input[name="wpfm_smtp_password"]').val());
        formData.append('smtp_encryption', $('select[name="wpfm_smtp_encryption"]').val());
        formData.append('smtp_from_email', $('input[name="wpfm_smtp_from_email"]').val());
        formData.append('smtp_from_name', $('input[name="wpfm_smtp_from_name"]').val());

        $.ajax({
            url: ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                if (response.success) {
                    $result.html('<div class="text-green-600">✓ ' + response.data.message + '</div>');
                } else {
                    $result.html('<div class="text-red-600">✗ ' + response.data + '</div>');
                }
            },
            error: function() {
                $result.html('<div class="text-red-600">✗ AJAX request failed</div>');
            },
            complete: function() {
                $button.prop('disabled', false).text('Send Test Email');
            }
        });
    });
});
</script>