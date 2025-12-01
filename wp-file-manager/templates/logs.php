<h1 class="wp-heading-inline">Logs</h1>
<p style="color:#50575e;">File operations (upload, download, edit, delete) are automatically logged here.</p>

<div class="postbox" style="padding:0;">
    <div style="border-bottom:1px solid #e5e7eb;display:flex;gap:4px;padding:8px 12px;background:#f8fafc;">
        <button class="button wpfm-tab-btn" data-tab="edited" style="background:#075985;color:#fff;border-color:#075985;">Edited Files</button>
        <button class="button wpfm-tab-btn" data-tab="downloaded">Downloaded Files</button>
        <button class="button wpfm-tab-btn" data-tab="uploaded">Uploaded Files</button>
        <button class="button wpfm-tab-btn" data-tab="deleted">Deleted Files</button>
    </div>
    <div class="wpfm-tab-panels">
        <!-- Edited Files -->
        <div class="wpfm-tab-panel" data-tab="edited" style="display:block;">
            <div class="wpfm-log-header" style="background:#075985;color:#fff;padding:16px;display:flex;align-items:center;gap:12px;">
                <span class="dashicons dashicons-edit" style="font-size:22px;"></span>
                <strong>Edited Files</strong>
            </div>
            <div class="inside" style="padding:16px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                    <label>Search Logs</label>
                    <input type="text" id="wpfm-log-search-edited" class="regular-text" placeholder="Search File Name or User" />
                    <button class="button" id="wpfm-log-search-btn-edited">Search</button>
                    
                    <!-- Bulk Actions -->
                    <select id="wpfm-bulk-action-edited" style="margin-left:auto; margin-right:8px;">
                        <option value="">Bulk Actions</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button class="button" id="wpfm-bulk-apply-edited">Apply</button>
                    <button class="button" id="wpfm-clear-logs-edited">Clear All Logs</button>
                </div>
                <div id="wpfm-logs-container-edited">
                    <div class="notice inline">Loading logs...</div>
                </div>
            </div>
        </div>

        <!-- Downloaded Files -->
        <div class="wpfm-tab-panel" data-tab="downloaded" style="display:none;">
            <div class="wpfm-log-header" style="background:#0369a1;color:#fff;padding:16px;display:flex;align-items:center;gap:12px;">
                <span class="dashicons dashicons-download" style="font-size:22px;"></span>
                <strong>Downloaded Files</strong>
            </div>
            <div class="inside" style="padding:16px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                    <label>Search Logs</label>
                    <input type="text" id="wpfm-log-search-downloaded" class="regular-text" placeholder="Search File Name or User" />
                    <button class="button" id="wpfm-log-search-btn-downloaded">Search</button>
                    
                    <!-- Bulk Actions -->
                    <select id="wpfm-bulk-action-downloaded" style="margin-left:auto; margin-right:8px;">
                        <option value="">Bulk Actions</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button class="button" id="wpfm-bulk-apply-downloaded">Apply</button>
                    <button class="button" id="wpfm-clear-logs-downloaded">Clear All Logs</button>
                </div>
                <div id="wpfm-logs-container-downloaded">
                    <div class="notice inline">Loading logs...</div>
                </div>
            </div>
        </div>

        <!-- Uploaded Files -->
        <div class="wpfm-tab-panel" data-tab="uploaded" style="display:none;">
            <div class="wpfm-log-header" style="background:#059669;color:#fff;padding:16px;display:flex;align-items:center;gap:12px;">
                <span class="dashicons dashicons-upload" style="font-size:22px;"></span>
                <strong>Uploaded Files</strong>
            </div>
            <div class="inside" style="padding:16px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                    <label>Search Logs</label>
                    <input type="text" id="wpfm-log-search-uploaded" class="regular-text" placeholder="Search File Name or User" />
                    <button class="button" id="wpfm-log-search-btn-uploaded">Search</button>
                    
                    <!-- Bulk Actions -->
                    <select id="wpfm-bulk-action-uploaded" style="margin-left:auto; margin-right:8px;">
                        <option value="">Bulk Actions</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button class="button" id="wpfm-bulk-apply-uploaded">Apply</button>
                    <button class="button" id="wpfm-clear-logs-uploaded">Clear All Logs</button>
                </div>
                <div id="wpfm-logs-container-uploaded">
                    <div class="notice inline">Loading logs...</div>
                </div>
            </div>
        </div>

        <!-- Deleted Files -->
        <div class="wpfm-tab-panel" data-tab="deleted" style="display:none;">
            <div class="wpfm-log-header" style="background:#dc2626;color:#fff;padding:16px;display:flex;align-items:center;gap:12px;">
                <span class="dashicons dashicons-trash" style="font-size:22px;"></span>
                <strong>Deleted Files</strong>
            </div>
            <div class="inside" style="padding:16px;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
                    <label>Search Logs</label>
                    <input type="text" id="wpfm-log-search-deleted" class="regular-text" placeholder="Search File Name or User" />
                    <button class="button" id="wpfm-log-search-btn-deleted">Search</button>
                    
                    <!-- Bulk Actions -->
                    <select id="wpfm-bulk-action-deleted" style="margin-left:auto; margin-right:8px;">
                        <option value="">Bulk Actions</option>
                        <option value="delete">Delete</option>
                    </select>
                    <button class="button" id="wpfm-bulk-apply-deleted">Apply</button>
                    <button class="button" id="wpfm-clear-logs-deleted">Clear All Logs</button>
                </div>
                <div id="wpfm-logs-container-deleted">
                    <div class="notice inline">Loading logs...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    jQuery(document).ready(function($) {
        // Tabs
        function activateTab(tab){
            $('.wpfm-tab-btn').removeAttr('style');
            $('.wpfm-tab-panel').hide();
            $('.wpfm-tab-btn[data-tab="'+tab+'"]').attr('style','background:#075985;color:#fff;border-color:#075985;');
            $('.wpfm-tab-panel[data-tab="'+tab+'"]').show();
        }

        $('.wpfm-tab-btn').on('click', function(){
            var tab = $(this).data('tab');
            activateTab(tab);
            loadLogs(tab);
        });

        // Initialize with Edited tab
        activateTab('edited');
        loadLogs('edited');

        // Search functionality
        $('#wpfm-log-search-btn-edited').on('click', function() {
            loadLogs('edited', $('#wpfm-log-search-edited').val());
        });
        $('#wpfm-log-search-btn-downloaded').on('click', function() {
            loadLogs('downloaded', $('#wpfm-log-search-downloaded').val());
        });
        $('#wpfm-log-search-btn-uploaded').on('click', function() {
            loadLogs('uploaded', $('#wpfm-log-search-uploaded').val());
        });
        $('#wpfm-log-search-btn-deleted').on('click', function() {
            loadLogs('deleted', $('#wpfm-log-search-deleted').val());
        });

        // Clear logs functionality
        $('#wpfm-clear-logs-edited').on('click', function() {
            clearLogs('edited');
        });
        $('#wpfm-clear-logs-downloaded').on('click', function() {
            clearLogs('downloaded');
        });
        $('#wpfm-clear-logs-uploaded').on('click', function() {
            clearLogs('uploaded');
        });
        $('#wpfm-clear-logs-deleted').on('click', function() {
            clearLogs('deleted');
        });

        // Bulk actions
        $('#wpfm-bulk-apply-edited').on('click', function() {
            bulkAction('edited');
        });
        $('#wpfm-bulk-apply-downloaded').on('click', function() {
            bulkAction('downloaded');
        });
        $('#wpfm-bulk-apply-uploaded').on('click', function() {
            bulkAction('uploaded');
        });
        $('#wpfm-bulk-apply-deleted').on('click', function() {
            bulkAction('deleted');
        });

        // Select all checkboxes
        $(document).on('click', '.wpfm-select-all', function() {
            var isChecked = $(this).is(':checked');
            $(this).closest('table').find('.wpfm-log-checkbox').prop('checked', isChecked);
        });

        function loadLogs(logType, search = '') {
            const container = $('#wpfm-logs-container-' + logType);
            container.html('<div class="notice inline">Loading logs...</div>');

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpfm_get_logs',
                    log_type: logType,
                    search: search,
                    nonce: wpfm_vars.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        displayLogs(logType, response.data.logs);
                    } else {
                        container.html('<div class="notice inline notice-error">Error: ' + response.data + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    var msg = error || 'Request failed';
                    if (xhr && typeof xhr.responseText === 'string' && xhr.responseText.trim().charAt(0) === '<') {
                        msg = 'Server returned HTML. Check PHP errors.';
                    }
                    container.html('<div class="notice inline notice-error">AJAX Error: ' + msg + '</div>');
                }
            });
        }

        function displayLogs(logType, logs) {
            const container = $('#wpfm-logs-container-' + logType);

            if (!logs || logs.length === 0) {
                container.html('<div class="notice inline">No logs found!</div>');
                return;
            }

            let html = '<table class="wp-list-table widefat striped">';
            html += '<thead><tr>';
            html += '<th class="check-column"><input type="checkbox" class="wpfm-select-all"></th>';
            html += '<th>Sr No.</th>';
            html += '<th>User ID</th>';
            html += '<th>User Name</th>';
            html += '<th>Files</th>';
            html += '<th>Date</th>';
            html += '<th>Action</th>';
            html += '<th>IP Address</th>';
            html += '</tr></thead>';
            html += '<tbody>';

            logs.forEach(function(log, index) {
                const date = new Date(log.timestamp * 1000);
                const formattedDate = date.toLocaleDateString() + ' ' + date.toLocaleTimeString();
                
                // Get action display text
                let actionText = log.action;
                let actionClass = '';
                switch(log.action) {
                    case 'upload':
                        actionText = '<span class="dashicons dashicons-upload" style="color:#059669;"></span> Uploaded';
                        actionClass = 'wpfm-action-upload';
                        break;
                    case 'download':
                        actionText = '<span class="dashicons dashicons-download" style="color:#0369a1;"></span> Downloaded';
                        actionClass = 'wpfm-action-download';
                        break;
                    case 'edit':
                        actionText = '<span class="dashicons dashicons-edit" style="color:#075985;"></span> Edited';
                        actionClass = 'wpfm-action-edit';
                        break;
                    case 'delete':
                        actionText = '<span class="dashicons dashicons-trash" style="color:#dc2626;"></span> Deleted';
                        actionClass = 'wpfm-action-delete';
                        break;
                }

                html += '<tr>';
                html += '<th scope="row" class="check-column"><input type="checkbox" class="wpfm-log-checkbox" name="log_ids[]" value="' + log.id + '"></th>';
                html += '<td>' + (index + 1) + '</td>';
                html += '<td>' + (log.user_id || 'N/A') + '</td>';
                html += '<td>' + (log.user_name || 'Guest') + '</td>';
                html += '<td><strong>' + log.file_name + '</strong><br><small style="color:#666;">' + log.file_path + '</small></td>';
                html += '<td>' + formattedDate + '</td>';
                html += '<td><span class="' + actionClass + '">' + actionText + '</span></td>';
                html += '<td><small>' + log.ip_address + '</small></td>';
                html += '</tr>';
            });

            html += '</tbody></table>';
            container.html(html);
        }

        function clearLogs(logType) {
            if (!confirm('Are you sure you want to clear all ' + logType + ' logs? This action cannot be undone.')) {
                return;
            }

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'wpfm_clear_logs',
                    log_type: logType,
                    nonce: wpfm_vars.nonce
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        loadLogs(logType);
                        alert('Logs cleared successfully');
                    } else {
                        alert('Error clearing logs: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Error clearing logs: ' + (error || 'Request failed'));
                }
            });
        }

        function bulkAction(logType) {
            const bulkAction = $('#wpfm-bulk-action-' + logType).val();
            const checkedBoxes = $('#wpfm-logs-container-' + logType + ' .wpfm-log-checkbox:checked');
            
            if (!bulkAction) {
                alert('Please select a bulk action.');
                return;
            }

            if (checkedBoxes.length === 0) {
                alert('Please select at least one log to perform bulk action.');
                return;
            }

            const logIds = checkedBoxes.map(function() {
                return $(this).val();
            }).get();

            if (bulkAction === 'delete') {
                if (!confirm('Are you sure you want to delete ' + logIds.length + ' selected log(s)? This action cannot be undone.')) {
                    return;
                }

                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'wpfm_bulk_delete_logs',
                        log_type: logType,
                        log_ids: logIds,
                        nonce: wpfm_vars.nonce
                    },
                    dataType: 'json',
                    success: function(response) {
                        if (response.success) {
                            loadLogs(logType);
                            alert(response.data.message);
                        } else {
                            alert('Error deleting logs: ' + response.data);
                        }
                    },
                    error: function(xhr, status, error) {
                        alert('Error deleting logs: ' + (error || 'Request failed'));
                    }
                });
            }
        }
    });
</script>

<style>
.wpfm-action-upload { color: #059669; font-weight: 600; }
.wpfm-action-download { color: #0369a1; font-weight: 600; }
.wpfm-action-edit { color: #075985; font-weight: 600; }
.wpfm-action-delete { color: #dc2626; font-weight: 600; }
.check-column { width: 2.2em; }
</style>