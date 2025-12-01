<div class="wrap wpfm-backup">
    <h1 class="wp-heading-inline">WP File Manager - Backup/Restore</h1>
    <hr class="wp-header-end">

    <div class="postbox" style="padding:16px;">
        <h2 class="hndle" style="margin:0 0 12px 0;">Backup Options:</h2>
        <div class="inside">
            <div class="wpfm-bk-row" style="display:flex;align-items:center;gap:24px;flex-wrap:wrap;">
                <label class="wpfm-bk-check"><input id="wpfm-bk-db" type="checkbox" checked> <span>Database Backup</span></label>
                <div class="wpfm-bk-files-wrap">
                    <label class="wpfm-bk-check"><input id="wpfm-bk-files" type="checkbox" checked> <span>Files Backup</span></label>
                    <div class="wpfm-bk-popover">
                        <label class="wpfm-bk-check"><input id="wpfm-bk-plugins" type="checkbox" checked> <span>Plugins</span></label>
                        <label class="wpfm-bk-check"><input id="wpfm-bk-themes" type="checkbox" checked> <span>Themes</span></label>
                        <label class="wpfm-bk-check"><input id="wpfm-bk-uploads" type="checkbox" checked> <span>Uploads</span></label>
                        <label class="wpfm-bk-check"><input id="wpfm-bk-others" type="checkbox" checked> <span>Others (Any other directories found inside wp-content)</span></label>
                    </div>
                </div>
                <div class="wpfm-bk-actions" style="margin-left:auto;display:flex;align-items:center;gap:8px;">
                    <button id="wpfm-bk-start" class="button button-primary">Backup Now</button>
                </div>
            </div>
            <div class="wpfm-bk-meta" style="margin-top:12px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;">
                <div class="notice inline"><strong>Time now:</strong> <span id="current-time"></span></div>
                <div id="wpfm-bk-log" class="wpfm-bk-log">No log message</div>
            </div>
            <div id="wpfm-bk-progress" class="progress-bar" style="display:none;">
                <div id="wpfm-bk-progress-inner" class="progress-bar-inner"></div>
            </div>
        </div>
    </div>

    <div class="postbox" style="padding:16px;">
        <h2 class="hndle" style="margin:0 0 12px 0;">Last Log Message</h2>
        <div class="inside">
            <div class="notice inline" id="wpfm-bk-last-log" style="width:100%">No recent backup activity found.</div>
        </div>
    </div>

    <div class="postbox" style="padding:16px;">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:12px;">
            <h2 class="hndle" style="margin:0;">Existing Backup(s)</h2>
            <span id="backup-count" style="display:inline-block;background:#2271b1;color:#fff;border-radius:999px;padding:2px 8px;font-size:12px;line-height:18px;">0</span>
        </div>
        <div class="wp-list-table widefat striped" style="overflow-x:auto;">
            <table class="wp-list-table widefat striped" id="wpfm-bk-table">
                <thead>
                    <tr>
                        <td class="manage-column column-cb check-column"><input type="checkbox" id="wpfm-bk-select-all-table"></td>
                        <th scope="col" class="manage-column">Backup Date</th>
                        <th scope="col" class="manage-column">Backup data (click to download)</th>
                        <th scope="col" class="manage-column" style="width:180px;">Action</th>
                    </tr>
                </thead>
                <tbody id="wpfm-bk-tbody">
                    <tr id="wpfm-bk-empty">
                        <th scope="row" class="check-column"><input type="checkbox" disabled></th>
                        <td colspan="3" style="color:#d63638;font-weight:500;">Currently no backup(s) found.</td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div class="wpfm-bk-footer" style="margin-top:12px;display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
            <span>Actions upon selected backup(s)</span>
            <button id="wpfm-bk-delete" class="button" disabled>Delete</button>
            <button id="wpfm-bk-select-all" class="button">Select All</button>
            <button id="wpfm-bk-deselect" class="button">Deselect</button>
        </div>
    </div>
</div>