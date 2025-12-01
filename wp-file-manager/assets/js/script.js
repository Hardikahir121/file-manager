(function ($) {
  "use strict";

  class WPFileManager {
    async ensureEsprima() {
      if (window.esprima) return;
      await $.getScript('https://cdn.jsdelivr.net/npm/esprima@4.0.1/dist/esprima.min.js');
    }

    async ensureCssTree() {
      if (window.csstree) return;
      await $.getScript('https://cdn.jsdelivr.net/npm/css-tree@2.3.1/dist/csstree.min.js');
    }
    async lintPhp(content) {
      try {
        const res = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "lint_php",
            content,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });
        const data = (res && res.data) || {};
        return { ok: !!data.ok, line: data.line || null, message: data.message || "" };
      } catch (e) {
        return { ok: true, line: null, message: "" }; // fail-open on network issues
      }
    }
    async maybeLintPhp(fileName) {
      try {
        const enabled = $("#wpfm-editor-syntax-toggle").is(":checked") && !!wpfm_ajax.syntax_check;
        const ext = (fileName.split(".").pop() || "").toLowerCase();
        if (!enabled || ext !== "php") {
          this._syntaxErrors = new Set();
          this.updateLineNumbers();
          this._lastLint = { ok: true, line: null, message: "" };
          return;
        }
        const content = $("#wpfm-file-editor").val();
        // Debounce using a simple timer
        clearTimeout(this._lintTimer);
        this._lintTimer = setTimeout(async () => {
          try {
            const result = await this.lintPhp(content);
            if (!result.ok) {
              const line = parseInt(result.line || 0, 10);
              this._syntaxErrors = new Set();
              if (line > 0) this._syntaxErrors.add(line);
              this._lastLint = { ok: false, line: line || null, message: result.message || "PHP syntax error" };
              $("#wpfm-editor-status")
                .text((result.message || "PHP syntax error") + (line ? ` (line ${line})` : ""))
                .removeClass("text-green-600 text-yellow-600")
                .addClass("text-red-600");
            } else {
              this._syntaxErrors = new Set();
              this._lastLint = { ok: true, line: null, message: "" };
              $("#wpfm-editor-status")
                .text("Ready")
                .removeClass("text-red-600 text-yellow-600")
                .addClass("text-green-600");
            }
            this.updateLineNumbers();
          } catch (_) {
            // ignore lint network errors
          }
        }, 400);
      } catch (_) {}
    }

    async maybeLint(fileName) {
      try {
        const enabled = $("#wpfm-editor-syntax-toggle").is(":checked");
        const ext = (fileName.split(".").pop() || "").toLowerCase();
        if (!enabled) {
          this._syntaxErrors = new Set();
          this.updateLineNumbers();
          this._lastLint = { ok: true, line: null, message: "" };
          return;
        }
        const content = $("#wpfm-file-editor").val();
        clearTimeout(this._lintTimer);
        this._lintTimer = setTimeout(async () => {
          try {
            let result = { ok: true, line: null, message: '' };
            if (ext === 'php' && !!wpfm_ajax.syntax_check) {
              result = await this.lintPhp(content);
            } else if (ext === 'js') {
              await this.ensureEsprima();
              try { window.esprima.parseScript(content, { tolerant: true }); }
              catch (e) { result = { ok: false, line: e.lineNumber || null, message: e.description || String(e) }; }
            } else if (ext === 'json') {
              try { JSON.parse(content); } catch (e) { result = { ok: false, line: null, message: e.message || 'Invalid JSON' }; }
            } else if (ext === 'css') {
              await this.ensureCssTree();
              try { window.csstree.parse(content, { positions: true }); }
              catch (e) { result = { ok: false, line: (e && e.loc && e.loc.start && e.loc.start.line) || null, message: e.message || 'Invalid CSS' }; }
            } else if (ext === 'html' || ext === 'htm') {
              const parser = new DOMParser();
              const doc = parser.parseFromString(content, 'text/html');
              const errs = doc.querySelectorAll('parsererror');
              if (errs.length) { result = { ok: false, line: null, message: 'HTML parse error' }; }
            }
            if (!result.ok) {
              const line = parseInt(result.line || 0, 10);
              this._syntaxErrors = new Set();
              if (line > 0) this._syntaxErrors.add(line);
              this._lastLint = { ok: false, line: line || null, message: result.message || 'Syntax error' };
              $("#wpfm-editor-status").text((result.message || 'Syntax error') + (line ? ` (line ${line})` : '')).removeClass("text-green-600 text-yellow-600").addClass("text-red-600");
            } else {
              this._syntaxErrors = new Set();
              this._lastLint = { ok: true, line: null, message: '' };
              $("#wpfm-editor-status").text("Ready").removeClass("text-red-600 text-yellow-600").addClass("text-green-600");
            }
            this.updateLineNumbers();
          } catch (_) {}
        }, 400);
      } catch (_) {}
    }

    scrollEditorToLine(line) {
      const ta = document.getElementById("wpfm-file-editor");
      if (!ta || !line || line < 1) return;
      const value = ta.value || "";
      const parts = value.split("\n");
      let pos = 0;
      for (let i = 0; i < Math.min(line - 1, parts.length); i++) {
        pos += parts[i].length + 1; // +1 for newline
      }
      try {
        ta.setSelectionRange(pos, pos);
        ta.focus();
      } catch (_) {}
    }

    showFindReplaceModal() {
      const modal = $(`
        <div id="wpfm-find-replace-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4">
          <div class="bg-white rounded-lg w-full max-w-md shadow-2xl">
            <div class="border-b p-4 bg-gray-50 rounded-t-lg">
              <h3 class="text-lg font-semibold">Find & Replace</h3>
            </div>
            <div class="p-4 space-y-3">
              <div>
                <label class="block text-sm text-gray-600 mb-1">Find</label>
                <input id="wpfm-find-input" type="text" class="w-full border rounded px-3 py-2" />
              </div>
              <div>
                <label class="block text-sm text-gray-600 mb-1">Replace with</label>
                <input id="wpfm-replace-input" type="text" class="w-full border rounded px-3 py-2" />
              </div>
              <label class="inline-flex items-center space-x-2 text-sm text-gray-700"><input id="wpfm-find-case" type="checkbox"/> <span>Match case</span></label>
              <label class="inline-flex items-center space-x-2 text-sm text-gray-700 ml-4"><input id="wpfm-find-regex" type="checkbox"/> <span>Use regex</span></label>
            </div>
            <div class="border-t p-3 bg-gray-50 rounded-b-lg flex justify-end space-x-2">
              <button id="wpfm-find-cancel" class="px-4 py-2 bg-gray-500 text-white rounded">Close</button>
              <button id="wpfm-replace-all" class="px-4 py-2 bg-blue-600 text-white rounded">Replace All</button>
            </div>
          </div>
        </div>`);
      $("body").append(modal);
      $("#wpfm-find-cancel").on("click", () => $("#wpfm-find-replace-modal").remove());
      $("#wpfm-replace-all").on("click", () => {
        const findTerm = $("#wpfm-find-input").val();
        const replaceTerm = $("#wpfm-replace-input").val();
        const isCase = $("#wpfm-find-case").is(":checked");
        const isRegex = $("#wpfm-find-regex").is(":checked");
        const $ta = $("#wpfm-file-editor");
        const content = $ta.val();
        let re;
        if (isRegex) {
          re = new RegExp(findTerm, isCase ? 'g' : 'gi');
        } else {
          const escapeRegExp = (s) => s.replace(/[.*+?^${}()|[\]\\]/g, "\\$&");
          re = new RegExp(escapeRegExp(findTerm), isCase ? 'g' : 'gi');
        }
        const replaced = content.replace(re, replaceTerm);
        if (replaced !== content) {
          $ta.val(replaced).trigger("input");
          $("#wpfm-find-replace-modal").remove();
          this.showInfo("Replaced all occurrences");
        } else {
          this.showInfo("No matches found");
        }
      });
    }
    constructor() {
      this.currentPath = "/";
      this.history = [];
      this.historyIndex = -1;
      this.selectedFiles = new Set();
      this.clipboard = null;
      this.clipboardAction = null;
      this.viewMode = (window.wpfm_ajax && wpfm_ajax.view) || "grid"; // 'grid' or 'list'
      this.currentFiles = [];

      // Database Manager properties
      this.currentTable = null;
      this.currentTableView = "content"; // 'content' or 'structure'
      this.dbHistory = [];
      this.dbHistoryIndex = -1;

      this.init();
    }

    // --- Formatter loading helpers ---
    async loadScriptOnce(url) {
      if (!this._loadedScripts) this._loadedScripts = new Set();
      if (this._loadedScripts.has(url)) return;
      await new Promise((resolve, reject) => {
        const s = document.createElement("script");
        s.src = url;
        s.async = true;
        s.onload = () => resolve();
        s.onerror = () => reject(new Error("Failed to load " + url));
        document.head.appendChild(s);
      });
      this._loadedScripts.add(url);
    }

    async ensurePrettier() {
      // Prettier v2 for broad compatibility
      const base = "https://unpkg.com/prettier@2.8.8";
      await this.loadScriptOnce(base + "/standalone.js");
      await Promise.all([
        this.loadScriptOnce(base + "/parser-babel.js"),
        this.loadScriptOnce(base + "/parser-html.js"),
        this.loadScriptOnce(base + "/parser-postcss.js"),
        this.loadScriptOnce(base + "/parser-typescript.js"),
        this.loadScriptOnce(base + "/parser-json.js"),
      ]);
      if (!window.prettier || !window.prettierPlugins) {
        throw new Error("Prettier failed to load");
      }
    }

    async ensureSqlFormatter() {
      const url =
        "https://cdn.jsdelivr.net/npm/sql-formatter@12.2.4/dist/sql-formatter.min.js";
      await this.loadScriptOnce(url);
      if (!window.sqlFormatter) throw new Error("SQL formatter failed to load");
    }

    formatWithPrettier(content, ext) {
      const prettier = window.prettier;
      const plugins = window.prettierPlugins;
      const map = {
        js: "babel",
        jsx: "babel",
        mjs: "babel",
        cjs: "babel",
        ts: "typescript",
        tsx: "typescript",
        json: "json",
        css: "postcss",
        scss: "postcss",
        less: "postcss",
        html: "html",
        htm: "html",
      };
      const parser = map[ext];
      if (!parser) return null;
      return prettier.format(content, { parser, plugins });
    }

    async saveTableRow(e) {
      const $btn = $(e.currentTarget);
      const $tr = $btn.closest("tr");
      const table = $btn.data("table") || this.currentTable;
      if (!table) return;

      // Heuristic: use first column as PK if DESCRIBE reveals a PRI key later; fallback to first
      // Collect row data
      const cells = {};
      $tr.find(".wpfm-cell-input").each((_, el) => {
        const col = $(el).data("col");
        cells[col] = $(el).val();
      });

      // Determine primary key via a DESCRIBE call
      let pk = null;
      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "run_query",
            query: `DESCRIBE \`${table}\``,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });
        if (response.success) {
          const row = (response.data.results || []).find(
            (r) => r.Key === "PRI"
          );
          pk = row ? row.Field : Object.keys(cells)[0];
        } else {
          pk = Object.keys(cells)[0];
        }
      } catch (_) {
        pk = Object.keys(cells)[0];
      }

      const pkValue = cells[pk];
      if (pkValue === undefined) {
        this.showDbError("Cannot determine primary key for update");
        return;
      }

      try {
        const res = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "update_row",
            table,
            pk,
            pkValue,
            data: cells,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });
        if (!res.success) throw new Error(res.data);
        this.showDbSuccess("Row saved");
      } catch (err) {
        const msg = err?.responseJSON?.data || err?.message || err;
        this.showDbError("Failed to save row: " + msg);
      }
    }

    async deleteBackupByKey(key) {
      if (!key) return;
      if (
        !confirm(
          `Delete backup from ${key}? This will remove both DB and files if present.`
        )
      )
        return;

      $("#wpfm-bk-log").text(`Deleting backup ${key}...`);
      try {
        const res = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "backup_delete",
            nonce: wpfm_ajax.nonce,
            key,
          },
          dataType: "json",
        });
        if (!res.success) throw new Error(res.data);
        await this.refreshBackupList();
        $("#wpfm-bk-log").text(`Backup ${key} deleted.`);
      } catch (err) {
        const msg = err?.responseJSON?.data || err?.message || err;
        $("#wpfm-bk-log").text("Delete failed: " + msg);
      }
    }

    async viewBackupLog(key) {
      if (!key) return;

      $("#wpfm-bk-log").text(`Loading log for ${key}...`);
      try {
        const res = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "backup_view_log",
            key: key,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });
        if (!res.success) throw new Error(res.data);
        const logContent = res.data.log || "No log available.";

        // Build structured entries by discovering files belonging to this key
        let entriesHtml = "";
        try {
          const list = await $.ajax({
            url: wpfm_ajax.ajax_url,
            type: "POST",
            data: {
              action: "wpfm_file_manager",
              action_type: "backup_list",
              nonce: wpfm_ajax.nonce,
            },
            dataType: "json",
          });
          if (list.success) {
            const items = (list.data || []).filter((x) => String(x.name).toLowerCase().includes(String(key).toLowerCase()));
            // Build map by kind label
            const byKind = {};
            let ts = 0;
            items.forEach((it) => {
              const name = String(it.name).toLowerCase();
              let kind = "";
              if (name.includes("plugins")) kind = "Plugins";
              else if (name.includes("themes")) kind = "Themes";
              else if (name.includes("uploads") || name.endsWith(".zip")) kind = "Uploads";
              else if (name.endsWith(".sql") || name.endsWith(".sql.gz")) kind = "Database";
              if (!kind) return;
              byKind[kind] = {
                kind,
                filename: it.name,
                size: this.formatFileSize(it.size || 0),
              };
              ts = Math.max(ts, it.modified || 0);
            });
            const order = ["Database", "Plugins", "Themes", "Uploads"];
            const lines = [];
            const when = ts ? new Date(ts * 1000) : new Date();
            const whenDate = when.toLocaleDateString();
            const whenTime = when.toLocaleTimeString();
            order.forEach((k, idx) => {
              if (!byKind[k]) return;
              const entry = byKind[k];
              lines.push(
                `<li style=\"margin-bottom:6px;\">(${idx + 1}) ${k} backup done on date ${this.escapeHtml(whenDate)} ${this.escapeHtml(whenTime)} (${this.escapeHtml(entry.filename)}) (${this.escapeHtml(entry.size)})</li>`
              );
            });
            if (lines.length) {
              entriesHtml = `<ol style=\"padding-left:16px;\">${lines.join("")}</ol>`;
            }
          }
        } catch (_) {}

        const modal = $(`
          <div id=\"wpfm-log-modal\" class=\"fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 p-4\">
            <div class=\"bg-white rounded-lg w-full max-w-4xl h-5/6 flex flex-col shadow-2xl\">
              <div class=\"border-b p-4 bg-gray-50 flex justify-between items-center\">
                <h3 class=\"text-lg font-semibold\">Backup Log: ${this.escapeHtml(key)}</h3>
                <button onclick=\"wpfm.closeLogModal()\" class=\"text-gray-500 hover:text-gray-700\"><i class=\"bi bi-x-lg\"></i></button>
              </div>
              <div class=\"flex-1 p-4 overflow-auto\">
                ${entriesHtml || '<div class=\"text-gray-500\">No structured entries found for this backup.</div>'}
                <div class=\"mt-4 font-mono text-xs bg-gray-900 text-green-400 rounded p-3\">
                  <pre class=\"whitespace-pre-wrap\">${this.escapeHtml(logContent)}</pre>
                </div>
              </div>
              <div class=\"border-t p-3 bg-gray-50 text-right\">
                <button onclick=\"wpfm.closeLogModal()\" class=\"px-4 py-2 bg-gray-500 text-white rounded hover:bg-gray-600\">Close</button>
              </div>
            </div>
          </div>
        `);
        $("body").append(modal);
        $("#wpfm-bk-log").text("Log loaded.");
      } catch (err) {
        const msg = err?.responseJSON?.data || err?.message || err;
        $("#wpfm-bk-log").text("Failed to load log: " + msg);
      }
    }

    

    closeLogModal() {
      $("#wpfm-log-modal").remove();
    }

    async saveEditedTable() {
      const table = this.currentTable;
      if (!table) {
        this.showDbError("Please select a table first");
        return;
      }

      // Collect all rows from the current table view
      const rows = [];
      $("#wpfm-table-content tbody tr").each((_, tr) => {
        const cells = {};
        $(tr)
          .find(".wpfm-cell-input")
          .each((__, el) => {
            const col = $(el).data("col");
            cells[col] = $(el).val();
          });
        if (Object.keys(cells).length > 0) rows.push(cells);
      });

      if (rows.length === 0) {
        this.showDbInfo("No edits to save");
        return;
      }

      // Determine primary key from DESCRIBE
      let pk = null;
      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "run_query",
            query: `DESCRIBE \`${table}\``,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });
        if (response.success) {
          const row = (response.data.results || []).find(
            (r) => r.Key === "PRI"
          );
          pk = row ? row.Field : null;
        }
      } catch (_) {}

      if (!pk) {
        this.showDbError("Cannot determine primary key for bulk save");
        return;
      }

      try {
        for (const cells of rows) {
          const pkValue = cells[pk];
          if (pkValue === undefined) continue;
          const res = await $.ajax({
            url: wpfm_ajax.ajax_url,
            type: "POST",
            data: {
              action: "wpfm_file_manager",
              action_type: "update_row",
              table,
              pk,
              pkValue,
              data: cells,
              nonce: wpfm_ajax.nonce,
            },
            dataType: "json",
          });
          if (!res.success) throw new Error(res.data);
        }
        this.showDbSuccess("All changes saved");
      } catch (err) {
        const msg = err?.responseJSON?.data || err?.message || err;
        this.showDbError("Failed to save: " + msg);
      }
    }

    init() {
      this.bindEvents();
      this.loadDirectoryTree();
      this.loadFiles(this.currentPath);
      this.updateNavigationButtons();
      this.initDbManagerIfPresent();
      // Apply theme and initial view button state
      if (window.wpfm_ajax && wpfm_ajax.theme) {
        $(".wpfm-file-manager, .wpfm-db-manager")
          .removeClass("theme-light theme-dark theme-blue theme-green")
          .addClass(`theme-${wpfm_ajax.theme}`);
      }
      $("#wpfm-view-grid").toggleClass("bg-gray-200", this.viewMode === "grid");
      $("#wpfm-view-list").toggleClass("bg-gray-200", this.viewMode === "list");
      // Initialize backup UI if present
      this.initBackupIfPresent();
    }

    bindEvents() {
      // Navigation
      $(document).on("click", "#wpfm-back", () => this.goBack());
      $(document).on("click", "#wpfm-forward", () => this.goForward());
      $(document).on("click", "#wpfm-up", () => this.goUp());
      $(document).on("click", "#wpfm-info", () => this.showFileInfo());
      // Close modals via cancel buttons
      $(document).on(
        "click",
        "#wpfm-create-folder-modal button:contains('Cancel')",
        () => this.closeModal()
      );
      $(document).on(
        "click",
        "#wpfm-create-file-modal button:contains('Cancel')",
        () => this.closeCreateFileModal()
      );

      // File Operations
      $(document).on("click", "#wpfm-new-folder", () =>
        this.showCreateFolderModal()
      );
      $(document).on("click", "#wpfm-new-file", () =>
        this.showCreateFileModal()
      );
      $(document).on("click", "#wpfm-upload-btn", () => this.openFileUpload());
      $(document).on("change", "#wpfm-file-input", (e) =>
        this.handleFileUpload(e)
      );
      $(document).on("click", "#wpfm-create-folder-confirm", () =>
        this.createFolder()
      );
      $(document).on("click", "#wpfm-create-file-confirm", () =>
        this.createFile()
      );
      $(document).on("click", "#wpfm-refresh", () =>
        this.loadFiles(this.currentPath)
      );

      $(document).on("click", ".wpfm-edit-file", (e) => {
        e.stopPropagation();
        const path = $(e.currentTarget).data("path");
        const name = $(e.currentTarget).data("name");
        this.showFileEditor(path, name);
      });

      // Edit Operations
      $(document).on("click", "#wpfm-cut", () => this.cutFiles());
      $(document).on("click", "#wpfm-copy", () => this.copyFiles());
      $(document).on("click", "#wpfm-paste", () => this.pasteFiles());
      $(document).on("click", "#wpfm-rename", () => this.renameFile());
      $(document).on("click", "#wpfm-delete", () => this.deleteFiles());
      $(document).on("click", "#wpfm-download", () => this.downloadFiles());
      $(document).on("click", "#wpfm-db-back", () => this.goDbBack());
      $(document).on("click", "#wpfm-db-forward", () => this.goDbForward());

      // Database Operations
      $(document).on("click", "#wpfm-new-table", () =>
        this.showNewTableModal()
      );
      $(document).on("click", "#wpfm-import-db", () => this.showImportModal());
      $(document).on("click", "#wpfm-export-db", () => this.exportDatabase());

      // Table Actions
      $(document).on("click", "#wpfm-browse-table", () =>
        this.browseCurrentTable()
      );
      $(document).on("click", "#wpfm-export-table", () =>
        this.exportCurrentTable()
      );
      $(document).on("click", "#wpfm-import-table", () =>
        this.showImportTableModal()
      );
      $(document).on("click", "#wpfm-drop-table", () =>
        this.dropCurrentTable()
      );
      $(document).on("click", "#wpfm-add-row", () => this.showAddRowModal());
      $(document).on("click", "#wpfm-empty-table", () =>
        this.emptyCurrentTable()
      );
      $(document).on("click", "#wpfm-structure-table", () =>
        this.showTableStructure()
      );

      // View Toggle
      $(document).on("click", "#wpfm-view-content", () =>
        this.setTableView("content")
      );
      $(document).on("click", "#wpfm-view-structure", () =>
        this.setTableView("structure")
      );

      // Table Selection from Sidebar
      $(document).on("click", ".wpfm-db-table-item", (e) => {
        const tableName = $(e.currentTarget).data("table");
        this.selectTable(tableName);
      });

      // Quick Actions
      $(document).on("click", ".wpfm-db-quick-action", (e) => {
        const action = $(e.currentTarget).data("action");
        this.handleQuickAction(action);
      });

      // View Modes
      $(document).on("click", "#wpfm-view-grid", () =>
        this.setViewMode("grid")
      );
      $(document).on("click", "#wpfm-view-list", () =>
        this.setViewMode("list")
      );

      // Sort
      $(document).on("change", "#wpfm-sort", (e) =>
        this.sortFiles(e.target.value)
      );

      // Search
      $(document).on("input", "#wpfm-search", (e) =>
        this.searchFiles(e.target.value)
      );

      // File item events
      $(document).on("dblclick", ".wpfm-folder-item", (e) => {
        const path = $(e.currentTarget).data("path");
        this.navigateTo(path);
      });

      $(document).on("click", ".wpfm-file-item, .wpfm-folder-item", (e) => {
        this.selectFile(e.currentTarget);
      });

      // Context menu (right-click)
      $(document).on(
        "contextmenu",
        ".wpfm-file-item, .wpfm-folder-item, #wpfm-files-list-container tr",
        (e) => {
          e.preventDefault();
          const $target = $(e.currentTarget);
          const path = $target.data("path");
          const name = $target.data("name");
          const type = $target.data("type");
          if (!path) return;
          const isDir = type === "folder";
          this.showContextMenu(e.pageX, e.pageY, { path, name, isDir });
        }
      );

      // Context menu global handlers
      $(document).on("click contextmenu", (e) => {
        if (!$(e.target).closest("#wpfm-context-menu").length) {
          this.hideContextMenu();
        }
      });
      $(document).on("keydown", (e) => {
        if (e.key === "Escape") this.hideContextMenu();
      });

      // Breadcrumb events
      $(document).on("click", "#wpfm-breadcrumb a", (e) => {
        e.preventDefault();
        const path = $(e.target).data("path");
        this.navigateTo(path);
      });

      // Directory tree events
      $(document).on("click", ".wpfm-tree-folder", (e) => {
        e.stopPropagation();
        const path = $(e.currentTarget).data("path");
        this.navigateTo(path);
      });

      $(document).on("click", ".wpfm-tree-toggle", (e) => {
        e.stopPropagation();
        this.toggleTreeFolder(e.currentTarget);
      });

      // DB Manager Events
      $(document).on("click", "#wpfm-run-query", () => this.runQuery());
      $(document).on("click", "#wpfm-clear-query", () => this.clearQuery());
      $(document).on("click", ".wpfm-quick-query", (e) =>
        this.useQuickQuery(e)
      );
      $(document).on("click", "#wpfm-close-results", () => {
        $("#wpfm-query-results").addClass("hidden");
      });
      $(document).on("click", ".wpfm-save-row", (e) => this.saveTableRow(e));
      $(document).on("click", "#wpfm-export-db", () => this.exportDatabase());
      $(document).on("click", "#wpfm-export-table", () =>
        this.exportCurrentTable()
      );
      $(document).on("click", "#wpfm-import-db", () => this.showImportModal());
      $(document).on("click", "#wpfm-import-table", () =>
        this.showImportTableModal()
      );
      $(document).on("click", "#wpfm-save-table", () => this.saveEditedTable());
      // Delegated cancel handlers for import modals
      $(document).on("click", "#wpfm-cancel-import-db", () =>
        $("#wpfm-import-db-modal").remove()
      );
      $(document).on("click", "#wpfm-cancel-import-table", () =>
        $("#wpfm-import-table-modal").remove()
      );

      $(document).on("click", ".wpfm-bk-delete-row", (e) => {
        e.preventDefault();
        const key = $(e.currentTarget).data("key");
        this.deleteSingleBackup(key);
      });
      
      $(document).on("click", ".wpfm-bk-view-log", (e) => {
        e.preventDefault();
        const key = $(e.currentTarget).data("key");
        this.viewBackupLog(key);
      });

      // Keyboard shortcuts
      $(document).on("keydown", (e) => this.handleKeyboard(e));
    }

    escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
    }
    
    formatFileSize(bytes) {
      if (bytes === 0) return '0 B';
      const k = 1024;
      const sizes = ['B', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    showDbError(message) {
      alert(message); // Or use a better UI notification
    }
    
    initDbManagerIfPresent() {
      if (!$("#wpfm-db-sidebar").length) return;

      this.loadTables();
      this.updateDbNavigationButtons();
    }

    async loadTables() {
      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "get_tables",
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.displayTables(response.data);
          this.updateTableCount(response.data.length);
        } else {
          this.showError("Failed to load tables: " + response.data);
        }
      } catch (error) {
        this.showError("Error loading tables: " + error);
      }
    }

    displayTables(tables) {
      const sidebar = $("#wpfm-db-sidebar");
      sidebar.empty();

      if (!tables || tables.length === 0) {
        sidebar.html(
          '<div class="text-gray-500 p-2 text-center">No tables found</div>'
        );
        return;
      }

      tables.forEach((table) => {
        const tableItem = `
          <div class="wpfm-db-table-item flex items-center justify-between p-2 hover:bg-gray-50 rounded cursor-pointer border border-transparent hover:border-gray-200" 
               data-table="${table.name}">
            <div class="flex items-center flex-1 min-w-0">
              <i class="bi bi-table mr-2 text-blue-500 flex-shrink-0"></i>
              <span class="text-sm font-medium truncate" title="${
                table.name
              }">${table.name}</span>
            </div>
            <div class="text-xs text-gray-500 flex-shrink-0 ml-2">
              ${table.rows || 0}
            </div>
          </div>
        `;
        sidebar.append(tableItem);
      });
    }

    selectTable(tableName) {
      this.addToDbHistory(this.currentTable);
      this.currentTable = tableName;

      // Update UI
      $(".wpfm-db-table-item").removeClass("bg-blue-50 border-blue-200");
      $(`.wpfm-db-table-item[data-table="${tableName}"]`).addClass(
        "bg-blue-50 border-blue-200"
      );

      // Update breadcrumb
      this.updateDbBreadcrumb(tableName);

      // Show table section and hide query section
      $("#wpfm-query-section").addClass("hidden");
      $("#wpfm-table-section").removeClass("hidden");

      // Load table data based on current view
      if (this.currentTableView === "content") {
        this.loadTableData(tableName);
      } else {
        this.loadTableStructure(tableName);
      }

      this.updateDbNavigationButtons();
    }

    updateDbBreadcrumb(tableName = null) {
      const breadcrumb = $("#wpfm-db-breadcrumb ol");
      let html = `
        <li><span class="text-blue-500 font-medium">Host: ${
          wpfm_ajax.db_host || "localhost"
        }</span></li>
        <li><span class="mx-2 text-gray-400">/</span></li>
        <li><span class="text-blue-500 font-medium">Database: ${
          wpfm_ajax.db_name || "wordpress"
        }</span></li>
      `;

      if (tableName) {
        html += `
          <li><span class="mx-2 text-gray-400">/</span></li>
          <li><span class="text-green-600 font-medium">Table: ${tableName}</span></li>
        `;
      }

      breadcrumb.html(html);
    }

    async loadTableData(tableName, limit = 100, offset = 0) {
      this.showDbStatus("Loading table data...");

      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "run_query",
            query: `SELECT * FROM \`${tableName}\` LIMIT ${limit} OFFSET ${offset}`,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.displayTableData(response.data, tableName);
          this.showDbSuccess(
            `Loaded ${response.data.count} rows from ${tableName}`
          );
        } else {
          throw new Error(response.data);
        }
      } catch (error) {
        this.showDbError("Failed to load table data: " + error);
      }
    }

    displayTableData(data, tableName) {
      const content = $("#wpfm-table-content");
      $("#wpfm-current-table").text(tableName);
      $("#wpfm-table-info").text(`${data.count} rows`);

      if (!data.results || data.results.length === 0) {
        content.html(`
          <div class="text-center py-8 text-gray-500">
            <i class="bi bi-inbox text-4xl mb-2 opacity-50"></i>
            <p>No data found in table</p>
          </div>
        `);
        return;
      }

      let html =
        '<div class="overflow-x-auto"><table class="wpfm-results-table min-w-max">';

      // Table header
      html += "<thead><tr>";
      Object.keys(data.results[0]).forEach((key) => {
        html += `<th class="px-4 py-2 text-left bg-gray-50 border-b font-medium text-gray-700 whitespace-nowrap">${this.escapeHtml(
          key
        )}</th>`;
      });
      html += "</tr></thead>";

      // Table body with inline edit
      html += "<tbody>";
      data.results.forEach((row, rowIndex) => {
        html += `<tr class=\"hover:bg-gray-50\" data-row-index=\"${rowIndex}\">`;
        Object.entries(row).forEach(([key, value]) => {
          const valStr =
            value === null ? "" : this.escapeHtml(value.toString());
          html +=
            `<td class=\"px-4 py-2 border-b text-sm whitespace-nowrap\">` +
            `<input class=\"wpfm-cell-input w-full border rounded px-2 py-1 text-sm\" data-col=\"${this.escapeHtml(
              key
            )}\" value=\"${valStr}\" />` +
            `</td>`;
        });
        html += "</tr>";
      });
      html += "</tbody></table></div>";

      content.html(html);
    }

    async loadTableStructure(tableName) {
      this.showDbStatus("Loading table structure...");

      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "run_query",
            query: `DESCRIBE \`${tableName}\``,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.displayTableStructure(response.data, tableName);
          this.showDbSuccess(`Loaded structure of ${tableName}`);
        } else {
          throw new Error(response.data);
        }
      } catch (error) {
        this.showDbError("Failed to load table structure: " + error);
      }
    }

    displayTableStructure(data, tableName) {
      const content = $("#wpfm-table-content");
      $("#wpfm-current-table").text(tableName);
      $("#wpfm-table-info").text("Table Structure");

      if (!data.results || data.results.length === 0) {
        content.html(
          '<div class="text-center py-8 text-gray-500">No structure information available</div>'
        );
        return;
      }

      let html = `
        <div class="overflow-x-auto">
          <table class="wpfm-results-table min-w-full">
            <thead>
              <tr>
                <th class="px-4 py-2 text-left bg-gray-50 border-b font-medium text-gray-700">Field</th>
                <th class="px-4 py-2 text-left bg-gray-50 border-b font-medium text-gray-700">Type</th>
                <th class="px-4 py-2 text-left bg-gray-50 border-b font-medium text-gray-700">Null</th>
                <th class="px-4 py-2 text-left bg-gray-50 border-b font-medium text-gray-700">Key</th>
                <th class="px-4 py-2 text-left bg-gray-50 border-b font-medium text-gray-700">Default</th>
                <th class="px-4 py-2 text-left bg-gray-50 border-b font-medium text-gray-700">Extra</th>
              </tr>
            </thead>
            <tbody>
      `;

      data.results.forEach((row) => {
        html += '<tr class="hover:bg-gray-50">';
        ["Field", "Type", "Null", "Key", "Default", "Extra"].forEach(
          (field) => {
            const value = row[field] || "";
            html += `<td class="px-4 py-2 border-b text-sm">${this.escapeHtml(
              value.toString()
            )}</td>`;
          }
        );
        html += "</tr>";
      });

      html += "</tbody></table></div>";
      content.html(html);
    }

    setTableView(view) {
      this.currentTableView = view;

      // Update button states
      $("#wpfm-view-content").toggleClass("bg-gray-200", view === "content");
      $("#wpfm-view-structure").toggleClass(
        "bg-gray-200",
        view === "structure"
      );

      if (this.currentTable) {
        if (view === "content") {
          this.loadTableData(this.currentTable);
        } else {
          this.loadTableStructure(this.currentTable);
        }
      }
    }

    // Database Navigation
    addToDbHistory(tableName) {
      this.dbHistory = this.dbHistory.slice(0, this.dbHistoryIndex + 1);
      this.dbHistory.push(tableName);
      this.dbHistoryIndex = this.dbHistory.length - 1;
    }

    goDbBack() {
      if (this.dbHistoryIndex > 0) {
        this.dbHistoryIndex--;
        const tableName = this.dbHistory[this.dbHistoryIndex];
        this.selectTable(tableName);
      }
    }

    goDbForward() {
      if (this.dbHistoryIndex < this.dbHistory.length - 1) {
        this.dbHistoryIndex++;
        const tableName = this.dbHistory[this.dbHistoryIndex];
        this.selectTable(tableName);
      }
    }

    updateDbNavigationButtons() {
      const backBtn = $("#wpfm-db-back");
      const forwardBtn = $("#wpfm-db-forward");

      backBtn.prop("disabled", this.dbHistoryIndex <= 0);
      forwardBtn.prop(
        "disabled",
        this.dbHistoryIndex >= this.dbHistory.length - 1
      );

      backBtn.toggleClass(
        "opacity-50 cursor-not-allowed",
        this.dbHistoryIndex <= 0
      );
      forwardBtn.toggleClass(
        "opacity-50 cursor-not-allowed",
        this.dbHistoryIndex >= this.dbHistory.length - 1
      );
    }

    // Table Operations
    browseCurrentTable() {
      if (!this.currentTable) {
        this.showDbError("Please select a table first");
        return;
      }
      this.setTableView("content");
      this.loadTableData(this.currentTable);
    }

    async exportCurrentTable() {
      if (!this.currentTable) {
        this.showDbError("Please select a table first");
        return;
      }

      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "export_table",
            table: this.currentTable,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.downloadFile(response.data.filename, response.data.content);
          this.showDbSuccess(
            `Table ${this.currentTable} exported successfully`
          );
        } else {
          throw new Error(response.data);
        }
      } catch (error) {
        this.showDbError("Export failed: " + error);
      }
    }

    async emptyCurrentTable() {
      if (!this.currentTable) {
        this.showDbError("Please select a table first");
        return;
      }

      if (
        !confirm(
          `Are you sure you want to empty all data from ${this.currentTable}? This cannot be undone.`
        )
      ) {
        return;
      }

      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "empty_table",
            table: this.currentTable,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.showDbSuccess(`Table ${this.currentTable} emptied successfully`);
          this.loadTableData(this.currentTable);
        } else {
          throw new Error(response.data);
        }
      } catch (error) {
        const msg = error?.responseJSON?.data || error?.message || error;
        this.showDbError("Failed to empty table: " + msg);
      }
    }

    async dropCurrentTable() {
      if (!this.currentTable) {
        this.showDbError("Please select a table first");
        return;
      }

      if (
        !confirm(
          `Are you sure you want to DROP table ${this.currentTable}? This will permanently delete the table and all its data. This cannot be undone.`
        )
      ) {
        return;
      }

      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "drop_table",
            table: this.currentTable,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.showDbSuccess(`Table ${this.currentTable} dropped successfully`);
          this.currentTable = null;
          $("#wpfm-table-section").addClass("hidden");
          $("#wpfm-query-section").removeClass("hidden");
          this.loadTables();
        } else {
          throw new Error(response.data);
        }
      } catch (error) {
        const msg = error?.responseJSON?.data || error?.message || error;
        this.showDbError("Failed to drop table: " + msg);
      }
    }

    showTableStructure() {
      if (!this.currentTable) {
        this.showDbError("Please select a table first");
        return;
      }
      this.setTableView("structure");
    }

    showAddRowModal() {
      if (!this.currentTable) {
        this.showDbError("Please select a table first");
        return;
      }
      const table = this.currentTable;
      // Fetch columns via DESCRIBE and render a form
      $.ajax({
        url: wpfm_ajax.ajax_url,
        type: "POST",
        data: {
          action: "wpfm_file_manager",
          action_type: "run_query",
          query: `DESCRIBE \`${table}\``,
          nonce: wpfm_ajax.nonce,
        },
        dataType: "json",
      })
        .done((resp) => {
          if (!resp.success) throw new Error(resp.data);
          const cols = resp.data.results || [];
          const formFields = cols
            .filter((c) => c.Extra !== "auto_increment")
            .map((c) => {
              const name = this.escapeHtml(c.Field);
              const type = (c.Type || "").toLowerCase();
              const inputType = /int|decimal|float|double/.test(type)
                ? "number"
                : /date|time|year/.test(type)
                ? "text"
                : "text";
              return `
                <div>
                  <label class=\"block text-sm font-medium text-gray-700 mb-1\">${name}</label>
                  <input type=\"${inputType}\" data-col=\"${name}\" class=\"wpfm-add-col w-full border rounded px-3 py-2\" placeholder=\"${this.escapeHtml(
                c.Type
              )}\">
                </div>`;
            })
            .join("");

          const modal = $(`
            <div id=\"wpfm-add-row-modal\" class=\"fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50\">\n              <div class=\"bg-white rounded-lg p-6 w-96\">\n                <h3 class=\"text-lg font-semibold mb-4\">Add Row to <span class=\"font-mono\">${this.escapeHtml(
              table
            )}</span></h3>\n                <div class=\"space-y-3\">${formFields}</div>\n                <div class=\"flex justify-end space-x-2 mt-4\">\n                  <button class=\"px-4 py-2 text-gray-600 hover:text-gray-800\" id=\"wpfm-cancel-add-row\">Cancel</button>\n                  <button class=\"bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600\" id=\"wpfm-confirm-add-row\">Save</button>\n                </div>\n              </div>\n            </div>`);

          $("body").append(modal);

          $("#wpfm-cancel-add-row").on("click", () =>
            $("#wpfm-add-row-modal").remove()
          );
          $("#wpfm-confirm-add-row").on("click", async () => {
            const values = {};
            $("#wpfm-add-row-modal .wpfm-add-col").each((_, el) => {
              const col = $(el).data("col");
              values[col] = $(el).val();
            });

            const columns = Object.keys(values)
              .map((c) => `\`${c}\``)
              .join(",");
            const escapedValues = Object.values(values).map((v) =>
              v === null || v === "" ? "NULL" : `'${this.escapeSql(v)}'`
            );
            const sql = `INSERT INTO \`${table}\` (${columns}) VALUES (${escapedValues.join(
              ","
            )})`;

            try {
              const res = await $.ajax({
                url: wpfm_ajax.ajax_url,
                type: "POST",
                data: {
                  action: "wpfm_file_manager",
                  action_type: "run_query",
                  query: sql,
                  nonce: wpfm_ajax.nonce,
                },
                dataType: "json",
              });
              if (!res.success) throw new Error(res.data);
              this.showDbSuccess("Row added");
              $("#wpfm-add-row-modal").remove();
              this.loadTableData(this.currentTable);
            } catch (err) {
              const msg = err?.responseJSON?.data || err?.message || err;
              this.showDbError("Failed to add row: " + msg);
            }
          });
        })
        .fail((err) => {
          const msg = err?.responseJSON?.data || err?.message || err;
          this.showDbError("Failed to load columns: " + msg);
        });
    }

    showNewTableModal() {
      const modal = $(`
        <div id="wpfm-new-table-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div class="bg-white rounded-lg p-6 w-96">
            <h3 class="text-lg font-semibold mb-4">Create New Table</h3>
            <div class="space-y-4">
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Table Name</label>
                <input type="text" id="wpfm-new-table-name" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500" placeholder="Enter table name">
              </div>
              <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Columns</label>
                <textarea id="wpfm-new-table-columns" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500 font-mono text-sm" rows="4" placeholder="id INT AUTO_INCREMENT PRIMARY KEY,\nname VARCHAR(255),\ncreated_at TIMESTAMP"></textarea>
              </div>
            </div>
            <div class="flex justify-end space-x-2 mt-4">
              <button class="px-4 py-2 text-gray-600 hover:text-gray-800" onclick="wpfm.closeNewTableModal()">Cancel</button>
              <button id="wpfm-create-table-confirm" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Create Table</button>
            </div>
          </div>
        </div>
      `);

      $("body").append(modal);

      $("#wpfm-create-table-confirm").on("click", () => {
        this.createNewTable();
      });
    }

    closeNewTableModal() {
      $("#wpfm-new-table-modal").remove();
    }

    async createNewTable() {
      const tableName = $("#wpfm-new-table-name").val().trim();
      const columns = $("#wpfm-new-table-columns").val().trim();

      if (!tableName) {
        this.showDbError("Please enter a table name");
        return;
      }

      if (!columns) {
        this.showDbError("Please define at least one column");
        return;
      }

      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "run_query",
            query: `CREATE TABLE \`${tableName}\` (${columns})`,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.showDbSuccess(`Table ${tableName} created successfully`);
          this.closeNewTableModal();
          this.loadTables();
        } else {
          throw new Error(response.data);
        }
      } catch (error) {
        this.showDbError("Failed to create table: " + error);
      }
    }

    showImportModal() {
      const modal = $(`
        <div id="wpfm-import-db-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
          <div class="bg-white rounded-lg p-6 w-96">
            <h3 class="text-lg font-semibold mb-4">Import SQL to Database</h3>
            <div class="space-y-3">
              <input type="file" id="wpfm-import-db-file" accept=".sql,text/sql,.txt" class="w-full text-sm" />
              <div class="text-xs text-gray-500">Or paste SQL below</div>
              <textarea id="wpfm-import-sql" rows="8" class="w-full border rounded p-3 font-mono text-sm" placeholder="Paste SQL here..."></textarea>
            </div>
            <div class="flex justify-end space-x-2 mt-4">
              <button class="px-4 py-2 text-gray-600 hover:text-gray-800" id="wpfm-cancel-import-db">Cancel</button>
              <button class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600" id="wpfm-confirm-import-db">Import</button>
            </div>
          </div>
        </div>
      `);

      $("body").append(modal);

      $("#wpfm-cancel-import-db").on("click", () => {
        $("#wpfm-import-db-modal").remove();
      });

      $("#wpfm-import-db-file").on("change", (e) => {
        const file = e.target.files && e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = () => {
          $("#wpfm-import-sql").val(reader.result || "");
        };
        reader.readAsText(file);
      });

      $("#wpfm-confirm-import-db").on("click", async () => {
        const sql = $("#wpfm-import-sql").val();
        if (!sql || !sql.trim()) {
          this.showDbError("Please paste SQL to import");
          return;
        }
        try {
          const response = await $.ajax({
            url: wpfm_ajax.ajax_url,
            type: "POST",
            data: {
              action: "wpfm_file_manager",
              action_type: "import_database",
              sql: sql,
              nonce: wpfm_ajax.nonce,
            },
            dataType: "json",
          });
          if (response.success) {
            this.showDbSuccess("SQL imported successfully");
            $("#wpfm-import-db-modal").remove();
            this.loadTables();
          } else {
            throw new Error(response.data);
          }
        } catch (error) {
          const msg = error?.responseJSON?.data || error?.message || error;
          this.showDbError("Import failed: " + msg);
        }
      });
    }

    showImportTableModal() {
      if (!this.currentTable) {
        this.showDbError("Please select a table first");
        return;
      }

      const modal = $(`
        <div id=\"wpfm-import-table-modal\" class=\"fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50\">\n          <div class=\"bg-white rounded-lg p-6 w-96\">\n            <h3 class=\"text-lg font-semibold mb-4\">Import SQL into <span class=\"font-mono\">${this.escapeHtml(
          this.currentTable
        )}</span></h3>\n            <div class=\"space-y-3\">\n              <input type=\"file\" id=\"wpfm-import-table-file\" accept=\".sql,text/sql,.txt\" class=\"w-full text-sm\" />\n              <div class=\"text-xs text-gray-500\">Or paste SQL below</div>\n              <textarea id=\"wpfm-import-table-sql\" rows=\"8\" class=\"w-full border rounded p-3 font-mono text-sm\" placeholder=\"Paste INSERT/REPLACE statements here...\"></textarea>\n            </div>\n            <div class=\"flex justify-end space-x-2 mt-4\">\n              <button class=\"px-4 py-2 text-gray-600 hover:text-gray-800\" id=\"wpfm-cancel-import-table\">Cancel</button>\n              <button class=\"bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600\" id=\"wpfm-confirm-import-table\">Import</button>\n            </div>\n          </div>\n        </div>
      `);
      $("#wpfm-import-table-file").on("change", (e) => {
        const file = e.target.files && e.target.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = () => {
          $("#wpfm-import-table-sql").val(reader.result || "");
        };
        reader.readAsText(file);
      });

      $("body").append(modal);

      $("#wpfm-cancel-import-table").on("click", () => {
        $("#wpfm-import-table-modal").remove();
      });

      $("#wpfm-confirm-import-table").on("click", async () => {
        const sql = $("#wpfm-import-table-sql").val();
        if (!sql || !sql.trim()) {
          this.showDbError("Please paste SQL to import");
          return;
        }
        try {
          const response = await $.ajax({
            url: wpfm_ajax.ajax_url,
            type: "POST",
            data: {
              action: "wpfm_file_manager",
              action_type: "import_table",
              sql: sql,
              nonce: wpfm_ajax.nonce,
            },
            dataType: "json",
          });
          if (response.success) {
            this.showDbSuccess("Table data imported successfully");
            $("#wpfm-import-table-modal").remove();
            this.loadTableData(this.currentTable);
          } else {
            throw new Error(response.data);
          }
        } catch (error) {
          const msg = error?.responseJSON?.data || error?.message || error;
          this.showDbError("Import failed: " + msg);
        }
      });
    }

    async exportDatabase() {
      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "export_database",
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.downloadFile(response.data.filename, response.data.content);
          this.showDbSuccess("Database exported successfully");
        } else {
          throw new Error(response.data);
        }
      } catch (error) {
        this.showDbError("Export failed: " + error);
      }
    }

    handleQuickAction(action) {
      const actions = {
        show_tables: "SHOW TABLES",
        db_info:
          "SELECT @@version as version, NOW() as current_time, DATABASE() as current_database",
        optimize: "SHOW TABLES",
        export_all: () => this.exportDatabase(),
      };

      if (typeof actions[action] === "function") {
        actions[action]();
      } else {
        $("#wpfm-sql-query").val(actions[action]);
        this.runQuery();
      }
    }

    // --- Backup/Restore ---
    initBackupIfPresent() {
      if (!$("#wpfm-bk-start").length) return;
      // Load initial list
      this.refreshBackupList();

      $(document).on("click", "#wpfm-bk-start", () => this.startBackup());
      $(document).on("click", "#wpfm-bk-select-all", () => {
        $(".wpfm-bk-row .wpfm-bk-cb").prop("checked", true);
        this.updateBackupSelectionUI();
      });
      $(document).on("click", "#wpfm-bk-deselect", () => {
        $(".wpfm-bk-row .wpfm-bk-cb").prop("checked", false);
        this.updateBackupSelectionUI();
      });
      $(document).on("change", ".wpfm-bk-cb", () =>
        this.updateBackupSelectionUI()
      );
      $(document).on("click", "#wpfm-bk-delete", () =>
        this.deleteSelectedBackups()
      );
      $(document).on("click", ".wpfm-bk-download", (e) => {
        e.preventDefault();
        const name = $(e.currentTarget).data("name");
        console.log("Download clicked, name:", name); // Debug log
        this.downloadBackup(name);
      });
      $(document).on("click", ".wpfm-bk-download-all", (e) => {
        e.preventDefault();
        const key = String($(e.currentTarget).data("key") || "");
        if (!key) return;
        const url =
          wpfm_ajax.ajax_url +
          `?action=wpfm_file_manager&action_type=backup_download_all&key=${encodeURIComponent(key)}&nonce=${wpfm_ajax.nonce}`;
        let iframe = document.getElementById("wpfm-download-frame");
        if (!iframe) {
          iframe = document.createElement("iframe");
          iframe.id = "wpfm-download-frame";
          iframe.style.display = "none";
          document.body.appendChild(iframe);
        }
        iframe.src = url;
      });
      $(document).on("click", ".wpfm-bk-restore", (e) => {
        e.preventDefault();
        const name = $(e.currentTarget).data("name");
        const type = $(e.currentTarget).data("type");
        this.restoreBackup(type, name);
      });

      // Header select-all checkbox
      $(document).on("change", "#wpfm-bk-select-all-table", (e) => {
        const checked = $(e.currentTarget).is(":checked");
        $(".wpfm-bk-row .wpfm-bk-cb").prop("checked", checked);
        this.updateBackupSelectionUI();
      });

      // Popover behavior & sub-options enabling
      $(document).on("mouseenter", ".wpfm-bk-files-wrap", () => {
        if ($("#wpfm-bk-files").is(":checked")) {
          $(".wpfm-bk-popover").addClass("show");
        }
      });
      $(document).on("mouseleave", ".wpfm-bk-files-wrap", () => {
        $(".wpfm-bk-popover").removeClass("show");
      });
      $(document).on("change", "#wpfm-bk-files", () => {
        const enabled = $("#wpfm-bk-files").is(":checked");
        $(".wpfm-bk-popover input[type='checkbox']").prop("disabled", !enabled);
        if (!enabled) $(".wpfm-bk-popover").removeClass("show");
      });
      $(".wpfm-bk-popover input[type='checkbox']").prop(
        "disabled",
        !$("#wpfm-bk-files").is(":checked")
      );

      // Update current time every minute
      const updateTime = () => {
        const el = $("#current-time");
        if (!el.length) return;
        const now = new Date();
        const options = {
          day: "numeric",
          month: "short",
          year: "numeric",
          hour: "numeric",
          minute: "numeric",
          hour12: true,
        };
        el.text(now.toLocaleDateString("en-US", options));
      };
      updateTime();
      setInterval(updateTime, 60000);
    }

    getBackupSelections() {
      return {
        db: $("#wpfm-bk-db").is(":checked") ? 1 : 0,
        files: $("#wpfm-bk-files").is(":checked") ? 1 : 0,
        plugins: $("#wpfm-bk-plugins").is(":checked") ? 1 : 0,
        themes: $("#wpfm-bk-themes").is(":checked") ? 1 : 0,
        uploads: $("#wpfm-bk-uploads").is(":checked") ? 1 : 0,
        others: $("#wpfm-bk-others").is(":checked") ? 1 : 0,
      };
    }

    async startBackup() {
      const sel = this.getBackupSelections();
      if (!sel.db && !sel.files) {
        this.showDbError("Select Database or Files to backup");
        return;
      }
      $("#wpfm-bk-start").prop("disabled", true);
      $("#wpfm-bk-log")
        .removeClass("status-completed")
        .addClass("status-processing")
        .text("Starting backup...");
      const $bar = $("#wpfm-bk-progress");
      const $inner = $("#wpfm-bk-progress-inner");
      $bar.show();
      let prog = 0;
      $inner.css("width", "0%");
      const tick = setInterval(() => {
        prog = Math.min(prog + 5, 90);
        $inner.css("width", prog + "%");
      }, 200);
      try {
        // Retry on transient server outages (HTTP 503)
        const doRequest = () =>
          $.ajax({
            url: wpfm_ajax.ajax_url,
            type: "POST",
            data: {
              action: "wpfm_file_manager",
              action_type: "backup_start",
              nonce: wpfm_ajax.nonce,
              ...sel,
            },
            dataType: "json",
          });
        let res = null;
        let attempt = 0;
        // Exponential backoff: 500ms, 1000ms
        while (attempt < 3) {
          try {
            res = await doRequest();
            break;
          } catch (e) {
            const status = e?.status;
            if (status === 503 && attempt < 2) {
              const delay = 500 * Math.pow(2, attempt);
              await new Promise((r) => setTimeout(r, delay));
              attempt++;
              continue;
            }
            throw e;
          }
        }
        if (!res || !res.success) throw new Error(res?.data || "Backup failed");
        $("#wpfm-bk-log")
          .removeClass("status-processing")
          .addClass("status-completed")
          .text("Backup completed");
        $inner.css("width", "100%");
        await this.refreshBackupList();
      } catch (err) {
        const raw = (err && err.responseJSON && err.responseJSON.data) ?? err?.message ?? err;
        const msg = typeof raw === "string" ? raw : JSON.stringify(raw);
        $("#wpfm-bk-log")
          .removeClass("status-completed")
          .addClass("status-processing")
          .text("Backup failed: " + msg);
      } finally {
        $("#wpfm-bk-start").prop("disabled", false);
        setTimeout(() => {
          clearInterval(tick);
          $bar.hide();
        }, 800);
      }
    }

    async refreshBackupList() {
      try {
        const res = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "backup_list",
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });
        if (!res.success) throw new Error(res.data);
        const items = res.data || [];
        const $tbody = $("#wpfm-bk-table tbody");
        $tbody.empty();
        if (items.length === 0) {
          $tbody.append(
            '<tr id="wpfm-bk-empty"><th scope="row" class="check-column"><input type="checkbox" disabled></th><td colspan="3" style="color:#d63638;font-weight:500;">Currently no backup(s) found.</td></tr>'
          );
          this.updateBackupSelectionUI();
          return;
        }
        // Group backups by logical key and categorize parts
        const groups = {};
        const parseName = (name) => {
          const lower = (name || "").toLowerCase();
          // New-style names: backup_YYYY_MM_DD_HH_MM_SS-<hash>-<part>.(zip|sql|sql.gz)
          // Accept singular/plural variants and 'files' alias
          let m = lower.match(
            /(backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}-[a-z0-9]+)-(db|database|plugins|plugin|themes|theme|uploads|files)\.(zip|sql|sql\.gz)$/
          );
          if (m) {
            const rawPart = m[2];
            const part = rawPart === "database" || rawPart === "db" ? "db"
              : rawPart === "plugin" || rawPart === "plugins" ? "plugins"
              : rawPart === "theme" || rawPart === "themes" ? "themes"
              : "uploads";
            return { key: m[1], part };
          }
          // Legacy names: db_YYYYMMDD_HHMMSS.sql, files_YYYYMMDD_HHMMSS.zip
          m = lower.match(/^(db|files)_(\d{8}_\d{6})\.(sql|zip)$/);
          if (m) {
            return { key: m[2], part: m[1] === "db" ? "db" : "uploads" };
          }
          // Fallbacks for new-style without part regex mismatches (e.g., '-theme.zip')
          m = lower.match(/^(backup_\d{4}_\d{2}_\d{2}_\d{2}_\d{2}_\d{2}-[a-z0-9]+)-([a-z0-9]+)\.(zip|sql|sql\.gz)$/);
          if (m) {
            const rawPart = m[2];
            const part = rawPart === "database" || rawPart === "db" ? "db"
              : rawPart === "plugin" || rawPart === "plugins" ? "plugins"
              : rawPart === "theme" || rawPart === "themes" ? "themes"
              : rawPart === "uploads" || rawPart === "files" ? "uploads" : "uploads";
            return { key: m[1], part };
          }
          // Generic fallback: group by timestamp fragment if present
          m = lower.match(/_(\d{8}_\d{6})\.(sql|zip|gz)$/);
          return { key: m ? m[1] : lower, part: lower.endsWith(".sql") || lower.endsWith(".sql.gz") ? "db" : "uploads" };
        };
        items.forEach((it) => {
          const { key, part } = parseName(it.name);
          if (!groups[key]) groups[key] = { date: 0, db: null, uploads: null, plugins: null, themes: null };
          if (part === "db") groups[key].db = it;
          if (part === "uploads") groups[key].uploads = it;
          if (part === "plugins") groups[key].plugins = it;
          if (part === "themes") groups[key].themes = it;
          groups[key].date = Math.max(groups[key].date, it.modified || 0);
        });

        const keys = Object.keys(groups).sort(
          (a, b) => groups[b].date - groups[a].date
        );
        $("#backup-count").text(keys.length);
        // Update Last Log Message section with the most recent backup time
        if (keys.length > 0) {
          const latest = groups[keys[0]];
          const when = this.formatAdminDate(latest.date);
          $("#wpfm-bk-last-log").text(
            `The backup apparently succeeded and is now complete. (${when})`
          );
        }
        keys.forEach((k) => {
          const g = groups[k];
          const date = this.formatAdminDate(g.date);
          const chips = [];
          if (g.uploads) {
            chips.push(
              `<a href=\"#\" class=\"button button-small wpfm-bk-download\" data-name=\"${this.escapeHtml(
                g.uploads.name
              )}\">Uploads (${this.formatFileSize(g.uploads.size || 0)})</a>`
            );
          }
          if (g.plugins) {
            chips.push(
              `<a href=\"#\" class=\"button button-small wpfm-bk-download\" data-name=\"${this.escapeHtml(
                g.plugins.name
              )}\">Plugins (${this.formatFileSize(g.plugins.size || 0)})</a>`
            );
          }
          if (g.themes) {
            chips.push(
              `<a href=\"#\" class=\"button button-small wpfm-bk-download\" data-name=\"${this.escapeHtml(
                g.themes.name
              )}\">Themes (${this.formatFileSize(g.themes.size || 0)})</a>`
            );
          }
          if (g.db) {
            chips.push(
              `<a href=\"#\" class=\"button button-small wpfm-bk-download\" data-name=\"${this.escapeHtml(
                g.db.name
              )}\">Database (${this.formatFileSize(g.db.size || 0)})</a>`
            );
          }
          if (chips.length >= 2) {
            chips.push(
              `<a href=\"#\" class=\"button button-small wpfm-bk-download-all\" data-key=\"${this.escapeHtml(k)}\">Download All</a>`
            );
          }

          const actions = [
            g.uploads
              ? `<a href=\"#\" class=\"button button-small wpfm-bk-restore\" data-type=\"files\" data-name=\"${this.escapeHtml(
                  g.uploads.name
                )}\">Restore Files</a>`
              : "",
            g.db
              ? `<a href=\"#\" class=\"button button-small wpfm-bk-restore\" data-type=\"db\" data-name=\"${this.escapeHtml(
                  g.db.name
                )}\">Restore Database</a>`
              : "",
            `<a href=\"#\" class=\"button button-small wpfm-bk-delete-row\" data-key=\"${this.escapeHtml(
              k
            )}\">Delete</a>`,
            `<a href=\"#\" class=\"button button-small wpfm-bk-view-log\" data-key=\"${this.escapeHtml(
              k
            )}\">View Log</a>`,
          ]
            .filter(Boolean)
            .join(" ");

          const row = `
            <tr class=\"wpfm-bk-row\">\n              <th scope=\"row\" class=\"check-column\"><input type=\"checkbox\" class=\"wpfm-bk-cb\" data-key=\"${this.escapeHtml(
              k
            )}\"></th>\n              <td>${this.escapeHtml(
            date
          )}</td>\n              <td>${chips.join(
            " "
          )}</td>\n              <td>${actions}</td>\n            </tr>`;
          $tbody.append(row);
        });
        this.updateBackupSelectionUI();
      } catch (err) {
        const msg = err?.responseJSON?.data || err?.message || err;
        $("#wpfm-bk-log").text("Failed to load backups: " + msg);
      }
    }

    // Format like: 29 Oct, 2025 13:08 PM
    formatAdminDate(tsSeconds) {
      if (!tsSeconds) return "";
      const d = new Date((tsSeconds || 0) * 1000);
      const day = d.getDate();
      const months = [
        "Jan",
        "Feb",
        "Mar",
        "Apr",
        "May",
        "Jun",
        "Jul",
        "Aug",
        "Sep",
        "Oct",
        "Nov",
        "Dec",
      ];
      const mon = months[d.getMonth()];
      const year = d.getFullYear();
      let hours = d.getHours();
      const minutes = d.getMinutes();
      const ampm = hours >= 12 ? "PM" : "AM";
      const hh = hours.toString().padStart(2, "0");
      const mm = minutes.toString().padStart(2, "0");
      return `${day} ${mon}, ${year} ${hh}:${mm} ${ampm}`;
    }

    updateBackupSelectionUI() {
      const $checks = $(".wpfm-bk-row .wpfm-bk-cb");
      const checkedCount = $checks.filter(":checked").length;
      $("#wpfm-bk-delete").prop("disabled", checkedCount === 0);
      const $master = $("#wpfm-bk-select-all-table");
      if ($master.length) {
        if (checkedCount === 0) {
          $master.prop({ checked: false, indeterminate: false });
        } else if (checkedCount === $checks.length) {
          $master.prop({ checked: true, indeterminate: false });
        } else {
          $master.prop({ checked: false, indeterminate: true });
        }
      }
    }

    async deleteSelectedBackups() {
      // Collect selected backup keys
      const keys = $(".wpfm-bk-row .wpfm-bk-cb:checked")
        .map((_, el) => $(el).data("key"))
        .get();
      if (keys.length === 0) return;
      if (!confirm(`Delete ${keys.length} selected backup(s)?`)) return;
      const $btn = $("#wpfm-bk-delete");
      $btn.prop("disabled", true).text("Deleting...");
      $("#wpfm-bk-log").text(`Deleting ${keys.length} backup(s)...`);
      try {
        const res = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "backup_delete",
            nonce: wpfm_ajax.nonce,
            keys,
          },
          dataType: "json",
        });
        if (!res.success) throw new Error(res.data);
        await this.refreshBackupList();
        $("#wpfm-bk-log").text("Deleted selected backup(s).");
      } catch (err) {
        const msg = err?.responseJSON?.data || err?.message || err;
        $("#wpfm-bk-log").text("Delete failed: " + msg);
      }
      $btn.text("Delete");
      this.updateBackupSelectionUI();
    }

    downloadBackup(name) {
      console.log("downloadBackup called with:", name); // Debug log
      if (!name) {
        console.error("No backup name provided");
        return;
      }
      const url =
        wpfm_ajax.ajax_url +
        `?action=wpfm_file_manager&action_type=backup_download&name=${encodeURIComponent(
          name
        )}&nonce=${wpfm_ajax.nonce}`;
      console.log("Download URL:", url); // Debug log

      let iframe = document.getElementById("wpfm-download-frame");
      if (!iframe) {
        iframe = document.createElement("iframe");
        iframe.id = "wpfm-download-frame";
        iframe.style.display = "none";
        document.body.appendChild(iframe);
      }
      iframe.src = url;
    }

    async restoreBackup(type, name) {
      if (!name) return;
      const label = type === "db" ? "database" : "files";
      if (
        !confirm(
          `Restore ${label} from ${name}? This may overwrite existing data.`
        )
      )
        return;
      $("#wpfm-bk-log").text(`Restoring ${label}...`);
      try {
        const res = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type:
              type === "db" ? "backup_restore_db" : "backup_restore_files",
            nonce: wpfm_ajax.nonce,
            name,
          },
          dataType: "json",
        });
        if (!res.success) throw new Error(res.data);
        $("#wpfm-bk-log").text("Restore completed");
      } catch (err) {
        const msg = err?.responseJSON?.data || err?.message || err;
        $("#wpfm-bk-log").text("Restore failed: " + msg);
      }
    }

    // Utility Methods for Database Manager
    updateTableCount(count) {
      $("#wpfm-db-table-count").text(count);
    }

    showDbStatus(message) {
      $("#wpfm-db-status").text(message);
    }

    showDbSuccess(message) {
      this.showDbStatus(message);
      this.showNotification(message, "success");
    }

    showDbError(message) {
      this.showDbStatus("Error: " + message);
      this.showNotification(message, "error");
    }

    showDbInfo(message) {
      this.showNotification(message, "info");
    }

    downloadFile(filename, content) {
      const blob = new Blob([content], { type: "application/octet-stream" });
      const url = URL.createObjectURL(blob);
      const a = document.createElement("a");
      a.href = url;
      a.download = filename;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }

    async loadDirectoryTree() {
      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "get_directory_tree",
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.displayDirectoryTree(response.data);
        } else {
          console.error("Failed to load directory tree:", response.data);
        }
      } catch (error) {
        console.error("Directory tree error:", error);
      }
    }

    displayDirectoryTree(tree) {
      const container = $("#wpfm-directory-tree");

      if (tree.length === 0) {
        container.html(
          '<div class="text-gray-500 p-2">No directories found</div>'
        );
        return;
      }

      const html = this.buildTreeHTML(tree);
      container.html(html);
    }

    buildTreeHTML(items, level = 0) {
      let html = "";

      items.forEach((item) => {
        const hasChildren = item.children && item.children.length > 0;
        const paddingLeft = level * 16 + 8;

        html += `
                    <div class="wpfm-tree-item" data-path="${item.path}">
                        <div class="wpfm-tree-folder flex items-center p-1 hover:bg-gray-100 rounded cursor-pointer"
                             data-path="${item.path}"
                             style="padding-left: ${paddingLeft}px">
                            ${
                              hasChildren
                                ? `
                                <span class="wpfm-tree-toggle mr-1 transform transition-transform">
                                    <i class="bi bi-chevron-right text-xs text-gray-400"></i>
                                </span>
                            `
                                : '<span class="w-3 mr-1"></span>'
                            }
                            <i class="bi bi-folder-fill text-yellow-500 mr-2"></i>
                            <span class="text-sm truncate">${item.name}</span>
                        </div>
                        ${
                          hasChildren
                            ? `
                            <div class="wpfm-tree-children hidden">
                                ${this.buildTreeHTML(item.children, level + 1)}
                            </div>
                        `
                            : ""
                        }
                    </div>
                `;
      });

      return html;
    }

    toggleTreeFolder(element) {
      const $toggle = $(element);
      const $children = $toggle
        .closest(".wpfm-tree-item")
        .find(".wpfm-tree-children");
      const isExpanded = !$children.hasClass("hidden");

      if (isExpanded) {
        $children.addClass("hidden");
        $toggle
          .find("i")
          .removeClass("rotate-90 bi-chevron-down")
          .addClass("bi-chevron-right");
      } else {
        $children.removeClass("hidden");
        $toggle
          .find("i")
          .addClass("rotate-90 bi-chevron-down")
          .removeClass("bi-chevron-right");
      }
    }

    navigateTo(path) {
      this.addToHistory(this.currentPath);
      this.currentPath = path;
      this.loadFiles(path);
      this.updateBreadcrumb(path);
      this.updateNavigationButtons();
      this.highlightCurrentPathInTree(path);
    }

    highlightCurrentPathInTree(path) {
      $(".wpfm-tree-folder").removeClass("bg-blue-100 text-blue-700");
      $(`.wpfm-tree-folder[data-path="${path}"]`).addClass(
        "bg-blue-100 text-blue-700"
      );
    }

    addToHistory(path) {
      // Remove any future history if we're not at the end
      this.history = this.history.slice(0, this.historyIndex + 1);
      this.history.push(path);
      this.historyIndex = this.history.length - 1;
    }

    goBack() {
      if (this.historyIndex > 0) {
        this.historyIndex--;
        const path = this.history[this.historyIndex];
        this.currentPath = path;
        this.loadFiles(path);
        this.updateBreadcrumb(path);
        this.updateNavigationButtons();
      }
    }

    goForward() {
      if (this.historyIndex < this.history.length - 1) {
        this.historyIndex++;
        const path = this.history[this.historyIndex];
        this.currentPath = path;
        this.loadFiles(path);
        this.updateBreadcrumb(path);
        this.updateNavigationButtons();
      }
    }

    goUp() {
      if (this.currentPath !== "/") {
        const parentPath =
          this.currentPath.split("/").slice(0, -1).join("/") || "/";
        this.navigateTo(parentPath);
      }
    }

    updateNavigationButtons() {
      const backBtn = $("#wpfm-back");
      const forwardBtn = $("#wpfm-forward");
      const upBtn = $("#wpfm-up");

      backBtn.prop("disabled", this.historyIndex <= 0);
      forwardBtn.prop("disabled", this.historyIndex >= this.history.length - 1);
      upBtn.prop("disabled", this.currentPath === "/");

      // Visual feedback for disabled buttons
      backBtn.toggleClass(
        "opacity-50 cursor-not-allowed",
        this.historyIndex <= 0
      );
      forwardBtn.toggleClass(
        "opacity-50 cursor-not-allowed",
        this.historyIndex >= this.history.length - 1
      );
      upBtn.toggleClass(
        "opacity-50 cursor-not-allowed",
        this.currentPath === "/"
      );
    }

    async loadFiles(path = "/") {
      this.showLoading();
      this.showStatus("Loading files...");
      this.selectedFiles.clear();
      this.updateSelectionUI();

      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "list_files",
            path: path,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.displayFiles(response.data);
          this.updateStats(response.data);
          this.hideLoading();
          this.showStatus("Ready");
        } else {
          this.showError(response.data);
        }
      } catch (error) {
        console.error("Load files error:", error);
        this.showError(
          "Failed to load files: " +
            (error.responseJSON?.data || error.statusText)
        );
      }
    }

    displayFiles(files) {
      // Preserve a copy for client-side sorting/searching
      this.currentFiles = Array.isArray(files) ? [].concat(files) : [];
      const gridContainer = $("#wpfm-files-container");
      const listContainer = $("#wpfm-files-list-container");
      const emptyState = $("#wpfm-empty");
      const fileGrid = $("#wpfm-file-grid");
      const fileList = $("#wpfm-file-list");

      gridContainer.empty();
      listContainer.empty();

      if (files.length === 0) {
        fileGrid.addClass("hidden");
        fileList.addClass("hidden");
        emptyState.removeClass("hidden");
        return;
      }

      emptyState.addClass("hidden");

      if (this.viewMode === "grid") {
        fileList.addClass("hidden");
        fileGrid.removeClass("hidden");

        files.forEach((file) => {
          const item = this.createGridFileItem(file);
          gridContainer.append(item);
        });
      } else {
        fileGrid.addClass("hidden");
        fileList.removeClass("hidden");

        files.forEach((file) => {
          const item = this.createListFileItem(file);
          listContainer.append(item);
        });
      }
    }

    hideContextMenu() {
      $("#wpfm-context-menu").remove();
    }

    showContextMenu(x, y, item) {
      this.hideContextMenu();

      // Ensure the item is selected for downstream actions
      this.selectedFiles = new Set([item.path]);
      this.updateSelectionUI();

      const actions = [
        {
          id: "open",
          label: item.isDir ? "Open" : "Open (Preview)",
          show: true,
        },
        {
          id: "edit",
          label: "Edit",
          show:
            !item.isDir &&
            !/\.(zip|rar|7z|tar|gz|bz2|xz)$/i.test(item.name || ""),
        },
        { id: "download", label: "Download", show: !item.isDir },
        { id: "rename", label: "Rename", show: true },
        { id: "delete", label: "Delete", show: true },
        { id: "cut", label: "Cut", show: true },
        { id: "copy", label: "Copy", show: true },
        {
          id: "paste",
          label: "Paste",
          show: !!(this.clipboard && this.clipboard.length) && item.isDir,
        },
        { id: "info", label: "Info", show: true },
      ].filter((a) => a.show);

      const menu = $(`
        <div id="wpfm-context-menu" class="fixed z-50 bg-white border rounded shadow-lg text-sm">
          <ul class="py-1">
            ${actions
              .map(
                (a) =>
                  `<li><button class="wpfm-cm-item w-full text-left px-4 py-2 hover:bg-gray-100" data-action="${a.id}" data-path="${item.path}" data-name="${item.name}" data-isdir="${item.isDir}">${a.label}</button></li>`
              )
              .join("")}
          </ul>
        </div>
      `);

      $("body").append(menu);

      // Position within viewport
      const $menu = $("#wpfm-context-menu");
      const winW = $(window).width() || 0;
      const winH = $(window).height() || 0;
      const mW = $menu.outerWidth() || 160;
      const mH = $menu.outerHeight() || 200;
      const left = Math.min(x, winW - mW - 8);
      const top = Math.min(y, winH - mH - 8);
      $menu.css({ left: left + "px", top: top + "px" });

      // Bind item actions
      $menu.find(".wpfm-cm-item").on("click", (e) => {
        const $btn = $(e.currentTarget);
        const action = $btn.data("action");
        const path = $btn.data("path");
        const name = $btn.data("name");
        const isDir =
          String($btn.data("isdir")) === "true" || $btn.data("isdir") === true;
        this.handleContextMenuAction(action, { path, name, isDir });
        this.hideContextMenu();
      });
    }

    handleContextMenuAction(action, item) {
      // Ensure selection reflects the target
      this.selectedFiles = new Set([item.path]);
      this.updateSelectionUI();

      switch (action) {
        case "open":
          if (item.isDir) {
            this.navigateTo(item.path);
          } else {
            this.showFileEditor(item.path, item.name);
          }
          break;
        case "edit":
          if (!item.isDir) this.showFileEditor(item.path, item.name);
          break;
        case "download":
          if (!item.isDir) this.downloadSingleFile(item.path);
          break;
        case "rename":
          this.renameFile();
          break;
        case "delete":
          this.deleteFiles();
          break;
        case "cut":
          this.cutFiles();
          break;
        case "copy":
          this.copyFiles();
          break;
        case "paste":
          // Paste into this folder if dir, else into currentPath
          if (item.isDir) this.currentPath = item.path;
          this.pasteFiles();
          break;
        case "info":
          this.getFileInfo(item.path).then((data) =>
            this.displayFileInfo(data)
          );
          break;
      }
    }

    createGridFileItem(file) {
      const iconClass = file.icon;
      const size = file.is_dir ? "" : this.formatFileSize(file.size);
      const date = this.formatDate(file.modified);
      const itemClass = file.is_dir
        ? "wpfm-folder-item cursor-pointer"
        : "wpfm-file-item cursor-pointer";
      const isSelected = this.selectedFiles.has(file.path);
      const nameLower = (file.name || "").toLowerCase();
      const isArchive = /\.(zip|rar|7z|tar|gz|bz2|xz)$/i.test(nameLower);
      const showEdit = !file.is_dir && !isArchive;

      const fileActions = showEdit
        ? `
        <div class="absolute top-2 right-2 opacity-0 group-hover:opacity-100 transition-opacity">
            <button class="wpfm-edit-file bg-blue-500 text-white p-1 rounded text-xs" 
                    data-path="${file.path}" 
                    data-name="${file.name}"
                    title="Edit ${file.name}">
                <i class="bi bi-pencil"></i>
            </button>
        </div>
    `
        : "";

      return `
        <div class="${itemClass} group bg-white rounded-lg shadow-sm border p-4 hover:shadow-md transition-all duration-200 relative ${
        isSelected ? "border-blue-500 bg-blue-50" : "border-gray-200"
      }"
             data-path="${file.path}" 
             data-name="${file.name}" 
             data-type="${file.is_dir ? "folder" : "file"}"
             data-size="${file.size}"
             title="${file.is_dir ? "Folder" : "File"}: ${file.name}">
            ${fileActions}
            <div class="text-center">
                <i class="bi ${iconClass} text-3xl mb-3 ${
        isSelected ? "text-blue-500" : ""
      }"></i>
                <div class="file-name text-sm font-medium text-gray-800 truncate">
                    ${file.name}
                </div>
                <div class="file-info text-xs text-gray-500 mt-2 space-y-1">
                    <div>${size}</div>
                    <div>${date}</div>
                </div>
            </div>
        </div>
    `;
    }

    createListFileItem(file) {
      const iconClass = file.icon;
      const size = file.is_dir ? "-" : this.formatFileSize(file.size);
      const date = this.formatDate(file.modified);
      const isSelected = this.selectedFiles.has(file.path);
      const nameLower = (file.name || "").toLowerCase();
      const isArchive = /\.(zip|rar|7z|tar|gz|bz2|xz)$/i.test(nameLower);
      const showEdit = !file.is_dir && !isArchive;

      return `
                <tr class="hover:bg-gray-50 ${isSelected ? "bg-blue-50" : ""} ${
        file.is_dir
          ? "wpfm-folder-item cursor-pointer"
          : "wpfm-file-item cursor-pointer"
      }" 
                    data-path="${file.path}" 
                    data-name="${file.name}" 
                    data-type="${file.is_dir ? "folder" : "file"}">
                    <td class="px-4 py-3 whitespace-nowrap">
                        <div class="flex items-center">
                            <i class="bi ${iconClass} mr-3 ${
        isSelected ? "text-blue-500" : ""
      }"></i>
                            <span class="text-sm font-medium text-gray-900">${
                              file.name
                            }</span>
                            ${
                              showEdit
                                ? `<button class=\"ml-3 wpfm-edit-file text-xs px-2 py-1 border rounded\" data-path=\"${file.path}\" data-name=\"${file.name}\" title=\"Edit ${file.name}\"><i class=\"bi bi-pencil\"></i></button>`
                                : ""
                            }
                        </div>
                    </td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${size}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${date}</td>
                    <td class="px-4 py-3 whitespace-nowrap text-sm text-gray-500">${
                      file.permissions
                    }</td>
                </tr>
            `;
    }

    selectFile(element) {
      const $element = $(element);
      const path = $element.data("path");
      if (!path) return;
      const isSelected = this.selectedFiles.has(path);

      if (isSelected) {
        this.selectedFiles.delete(path);
      } else {
        this.selectedFiles.add(path);
      }

      this.updateSelectionUI();
    }

    updateSelectionUI() {
      // Update grid items
      $(".wpfm-file-item, .wpfm-folder-item").each((index, element) => {
        const $element = $(element);
        const path = $element.data("path");
        const isSelected = this.selectedFiles.has(path);

        $element.toggleClass("border-blue-500 bg-blue-50", isSelected);
        $element.find("i").toggleClass("text-blue-500", isSelected);
      });

      // Update list items
      $("#wpfm-files-list-container tr").each((index, element) => {
        const $element = $(element);
        const path = $element.data("path");
        const isSelected = this.selectedFiles.has(path);

        $element.toggleClass("bg-blue-50", isSelected);
        $element.find("i").toggleClass("text-blue-500", isSelected);
      });

      // Update toolbar buttons state
      const hasSelection = this.selectedFiles.size > 0;
      $("#wpfm-cut, #wpfm-copy, #wpfm-rename, #wpfm-delete, #wpfm-download")
        .prop("disabled", !hasSelection)
        .toggleClass("opacity-50 cursor-not-allowed", !hasSelection);
    }

    updateBreadcrumb(path) {
      const breadcrumb = $("#wpfm-breadcrumb ol");
      const parts = path.split("/").filter((part) => part !== "");

      let html =
        '<li><a href="#" data-path="/" class="text-blue-500 hover:text-blue-700 font-medium">wp-content</a></li>';
      let currentPath = "";

      parts.forEach((part) => {
        currentPath += "/" + part;
        html += `
                    <li class="flex items-center">
                        <span class="mx-2 text-gray-400">/</span>
                        <a href="#" data-path="${currentPath}" class="text-blue-500 hover:text-blue-700">${part}</a>
                    </li>
                `;
      });

      breadcrumb.html(html);
    }

    setViewMode(mode) {
      this.viewMode = mode;

      // Update button states
      $("#wpfm-view-grid").toggleClass("bg-gray-200", mode === "grid");
      $("#wpfm-view-list").toggleClass("bg-gray-200", mode === "list");

      // Re-render current files without refetching
      this.displayFiles(this.currentFiles);
    }

    updateStats(files) {
      const totalItems = files.length;
      const totalSize = files.reduce((sum, file) => sum + file.size, 0);

      $("#wpfm-items-count").text(totalItems);
      $("#wpfm-total-size").text(this.formatFileSize(totalSize));
    }

    openFileUpload() {
      $("#wpfm-file-input").click();
    }

    async handleFileUpload(e) {
      const files = e.target.files;
      if (!files.length) return;

      const formData = new FormData();
      for (let i = 0; i < files.length; i++) {
        formData.append("files[]", files[i]);
      }
      formData.append("action", "wpfm_file_manager");
      formData.append("action_type", "upload_files");
      formData.append("path", this.currentPath);
      formData.append("nonce", wpfm_ajax.nonce);

      this.showStatus("Uploading files...");

      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: formData,
          processData: false,
          contentType: false,
          dataType: "json",
        });

        if (response.success) {
          const uploadedCount = response.data.uploaded?.length || 0;
          const errorCount = response.data.errors?.length || 0;

          if (errorCount > 0) {
            this.showWarning(
              `${uploadedCount} files uploaded, ${errorCount} errors`
            );
          } else {
            this.showSuccess(`${uploadedCount} files uploaded successfully`);
          }

          this.loadFiles(this.currentPath);
        } else {
          this.showError("Upload failed: " + response.data);
        }
      } catch (error) {
        console.error("Upload error:", error);
        this.showError(
          "Upload error: " + (error.responseJSON?.data || error.statusText)
        );
      }

      // Reset file input
      e.target.value = "";
    }

    showCreateFolderModal() {
      $("#wpfm-create-folder-modal")
        .removeClass("hidden")
        .addClass("flex")
        .css({ display: "flex" });
      $("#wpfm-folder-name").val("").focus();
    }

    showCreateFileModal() {
      $("#wpfm-create-file-modal")
        .removeClass("hidden")
        .addClass("flex")
        .css({ display: "flex" });
      $("#wpfm-file-name").val("").focus();
    }

    closeModal() {
      $("#wpfm-create-folder-modal")
        .addClass("hidden")
        .removeClass("flex")
        .css({ display: "" });
    }

    closeCreateFileModal() {
      $("#wpfm-create-file-modal")
        .addClass("hidden")
        .removeClass("flex")
        .css({ display: "" });
    }

    async createFolder() {
      const name = $("#wpfm-folder-name").val().trim();
      if (!name) {
        this.showError("Please enter a folder name");
        return;
      }

      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "create_folder",
            name: name,
            path: this.currentPath,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.showSuccess("Folder created successfully");
          this.loadFiles(this.currentPath);
          this.closeModal();
        } else {
          this.showError("Failed to create folder: " + response.data);
        }
      } catch (error) {
        console.error("Create folder error:", error);
        this.showError(
          "Error creating folder: " +
            (error.responseJSON?.data || error.statusText)
        );
      }
    }

    async createFile() {
      const name = $("#wpfm-file-name").val().trim();
      if (!name) {
        this.showError("Please enter a file name");
        return;
      }

      try {
        // This would need server-side implementation
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "create_file",
            name: name,
            path: this.currentPath,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.showSuccess("File created successfully");
          this.loadFiles(this.currentPath);
          this.closeCreateFileModal();
        } else {
          this.showError("Failed to create file: " + response.data);
        }
      } catch (error) {
        console.error("Create file error:", error);
        this.showError(
          "Error creating file: " +
            (error.responseJSON?.data || error.statusText)
        );
      }
    }

    cutFiles() {
      if (this.selectedFiles.size === 0) {
        this.showError("Please select files to cut");
        return;
      }

      this.clipboard = Array.from(this.selectedFiles);
      this.clipboardAction = "cut";
      this.showSuccess(`${this.selectedFiles.size} items marked for cutting`);
      this.updateClipboardUI();
    }

    copyFiles() {
      if (this.selectedFiles.size === 0) {
        this.showError("Please select files to copy");
        return;
      }

      this.clipboard = Array.from(this.selectedFiles);
      this.clipboardAction = "copy";
      this.showSuccess(`${this.selectedFiles.size} items marked for copying`);
      this.updateClipboardUI();
    }

    async pasteFiles() {
      if (!this.clipboard || this.clipboard.length === 0) {
        this.showError("No items in clipboard");
        return;
      }

      this.showStatus("Pasting files...");

      try {
        for (const sourcePath of this.clipboard) {
          const response = await $.ajax({
            url: wpfm_ajax.ajax_url,
            type: "POST",
            data: {
              action: "wpfm_file_manager",
              action_type:
                this.clipboardAction === "cut" ? "move_item" : "copy_item",
              source_path: sourcePath,
              target_path: this.currentPath,
              nonce: wpfm_ajax.nonce,
            },
            dataType: "json",
          });

          if (!response.success) {
            throw new Error(response.data);
          }
        }

        this.showSuccess(
          `Successfully ${
            this.clipboardAction === "cut" ? "moved" : "copied"
          } ${this.clipboard.length} items`
        );

        // Clear clipboard after cut operation
        if (this.clipboardAction === "cut") {
          this.clipboard = null;
          this.clipboardAction = null;
          this.updateClipboardUI();
        }

        // Refresh file list
        this.loadFiles(this.currentPath);
      } catch (error) {
        this.showError(
          `Failed to ${this.clipboardAction} files: ${
            error.responseJSON?.data || error.message
          }`
        );
      }
    }

    renameFile() {
      if (this.selectedFiles.size === 0) {
        this.showError("Please select a file to rename");
        return;
      }

      if (this.selectedFiles.size > 1) {
        this.showError("Please select only one file to rename");
        return;
      }

      const oldPath = Array.from(this.selectedFiles)[0];
      const oldName = oldPath.split("/").pop();

      this.showRenameModal(oldPath, oldName);
    }

    showRenameModal(oldPath, oldName) {
      const modal = $(`
        <div id="wpfm-rename-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-96">
                <h3 class="text-lg font-semibold mb-4">Rename Item</h3>
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">New Name</label>
                    <input type="text" id="wpfm-new-name" value="${oldName}" class="w-full border rounded px-3 py-2 focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-500 mt-1">Current location: ${oldPath}</p>
                </div>
                <div class="flex justify-end space-x-2">
                    <button class="px-4 py-2 text-gray-600 hover:text-gray-800" onclick="wpfm.closeRenameModal()">Cancel</button>
                    <button id="wpfm-rename-confirm" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Rename</button>
                </div>
            </div>
        </div>
    `);

      $("body").append(modal);

      $("#wpfm-rename-confirm").on("click", () => {
        this.performRename(oldPath);
      });

      $("#wpfm-new-name").on("keypress", (e) => {
        if (e.which === 13) {
          // Enter key
          this.performRename(oldPath);
        }
      });

      $("#wpfm-new-name").focus().select();
    }

    closeRenameModal() {
      $("#wpfm-rename-modal").remove();
    }

    async performRename(oldPath) {
      const newName = $("#wpfm-new-name").val().trim();

      if (!newName) {
        this.showError("Please enter a new name");
        return;
      }

      const oldName = oldPath.split("/").pop();
      if (newName === oldName) {
        this.closeRenameModal();
        return;
      }

      // Validate filename
      if (!this.isValidFileName(newName)) {
        this.showError("Invalid file name. Please avoid special characters.");
        return;
      }

      this.showStatus("Renaming...");

      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "rename_item",
            path: oldPath,
            new_name: newName,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.showSuccess('"' + oldName + '" renamed to "' + newName + '"');
          this.closeRenameModal();
          this.loadFiles(this.currentPath);
        } else {
          throw new Error(response.data);
        }
      } catch (error) {
        console.error("Rename error:", error);
        let errorMessage = "Failed to rename";
        if (error.responseJSON && error.responseJSON.data) {
          errorMessage = error.responseJSON.data;
        } else if (error.statusText) {
          errorMessage = error.statusText;
        }
        this.showError(errorMessage);
      }
    }

    isValidFileName(name) {
      // Basic filename validation
      const invalidChars = /[<>:"/\\|?*\x00-\x1F]/g;
      const reservedNames = /^(con|prn|aux|nul|com[0-9]|lpt[0-9])$/i;

      if (invalidChars.test(name)) {
        return false;
      }

      if (reservedNames.test(name)) {
        return false;
      }

      if (name.trim() === "") {
        return false;
      }

      return true;
    }

    deleteFiles() {
      if (this.selectedFiles.size === 0) {
        this.showError("Please select files to delete");
        return;
      }

      const fileList = Array.from(this.selectedFiles)
        .map((path) => {
          const name = path.split("/").pop();
          return `<li class="text-sm">${name}</li>`;
        })
        .join("");

      const modal = $(`
        <div id="wpfm-delete-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg p-6 w-96">
                <h3 class="text-lg font-semibold mb-4 text-red-600 flex items-center">
                    <i class="bi bi-exclamation-triangle mr-2"></i>
                    Confirm Delete
                </h3>
                <div class="mb-4">
                    <p class="text-gray-700 mb-2">Are you sure you want to delete the following ${this.selectedFiles.size} item(s)?</p>
                    <ul class="bg-red-50 border border-red-200 rounded p-3 max-h-32 overflow-y-auto">
                        ${fileList}
                    </ul>
                    <p class="text-sm text-red-600 mt-2">This action cannot be undone.</p>
                </div>
                <div class="flex justify-end space-x-2">
                    <button class="px-4 py-2 text-gray-600 hover:text-gray-800" onclick="wpfm.closeDeleteModal()">Cancel</button>
                    <button id="wpfm-delete-confirm" class="bg-red-500 text-white px-4 py-2 rounded hover:bg-red-600">Delete</button>
                </div>
            </div>
        </div>
    `);

      $("body").append(modal);

      $("#wpfm-delete-confirm").on("click", () => {
        this.performDelete();
      });
    }

    closeDeleteModal() {
      $("#wpfm-delete-modal").remove();
    }

    async performDelete() {
      this.showStatus("Deleting files...");
      this.closeDeleteModal();

      try {
        const deletePromises = Array.from(this.selectedFiles).map((filePath) =>
          $.ajax({
            url: wpfm_ajax.ajax_url,
            type: "POST",
            data: {
              action: "wpfm_file_manager",
              action_type: "delete_item",
              path: filePath,
              nonce: wpfm_ajax.nonce,
            },
            dataType: "json",
          })
        );

        const results = await Promise.allSettled(deletePromises);

        let successCount = 0;
        let errorCount = 0;

        results.forEach((result, index) => {
          if (result.status === "fulfilled" && result.value.success) {
            successCount++;
          } else {
            errorCount++;
            console.error(
              "Delete failed for:",
              Array.from(this.selectedFiles)[index]
            );
          }
        });

        if (errorCount === 0) {
          this.showSuccess(`Successfully deleted ${successCount} items`);
        } else if (successCount > 0) {
          this.showWarning(
            `Deleted ${successCount} items, ${errorCount} failed`
          );
        } else {
          this.showError("Failed to delete all items");
        }

        this.selectedFiles.clear();
        this.updateSelectionUI();
        this.loadFiles(this.currentPath);
      } catch (error) {
        this.showError("Delete operation failed: " + error);
      }
    }

    showFileInfo() {
      if (this.selectedFiles.size === 0) {
        this.showError("Please select a file to view info");
        return;
      }

      if (this.selectedFiles.size > 1) {
        this.showError("Please select only one file to view info");
        return;
      }

      const filePath = Array.from(this.selectedFiles)[0];
      this.getFileInfo(filePath);
    }

    async getFileInfo(filePath) {
      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "get_file_info",
            path: filePath,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.displayFileInfo(response.data);
        } else {
          throw new Error(response.data);
        }
      } catch (error) {
        this.showError(
          "Failed to get file info: " +
            (error.responseJSON?.data || error.message)
        );
      }
    }

    displayFileInfo(info) {
      const modal = $(`
        <div id="wpfm-info-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
            <div class="bg-white rounded-lg w-96 max-h-96 overflow-hidden">
                <div class="border-b p-4 bg-gray-50">
                    <h3 class="text-lg font-semibold flex items-center">
                        <i class="bi bi-info-circle mr-2 text-blue-500"></i>
                        File Information
                    </h3>
                </div>
                <div class="p-4 overflow-y-auto max-h-64">
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Name:</span>
                            <span class="text-gray-900">${info.name}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Type:</span>
                            <span class="text-gray-900 capitalize">${
                              info.type
                            }</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Size:</span>
                            <span class="text-gray-900">${this.formatFileSize(
                              info.size
                            )}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Modified:</span>
                            <span class="text-gray-900">${new Date(
                              info.modified * 1000
                            ).toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Created:</span>
                            <span class="text-gray-900">${new Date(
                              info.created * 1000
                            ).toLocaleString()}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Permissions:</span>
                            <span class="font-mono text-gray-900">${
                              info.permissions
                            }</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Readable:</span>
                            <span class="text-gray-900 ${
                              info.readable ? "text-green-600" : "text-red-600"
                            }">${info.readable ? "Yes" : "No"}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Writable:</span>
                            <span class="text-gray-900 ${
                              info.writable ? "text-green-600" : "text-red-600"
                            }">${info.writable ? "Yes" : "No"}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="font-medium text-gray-700">Path:</span>
                            <span class="text-gray-900 text-xs font-mono truncate" title="${
                              info.path
                            }">${info.path}</span>
                        </div>
                    </div>
                </div>
                <div class="border-t p-4 bg-gray-50 flex justify-end">
                    <button onclick="wpfm.closeInfoModal()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600">Close</button>
                </div>
            </div>
        </div>
    `);

      $("body").append(modal);
    }

    closeInfoModal() {
      $("#wpfm-info-modal").remove();
    }

    updateClipboardUI() {
      const pasteBtn = $("#wpfm-paste");
      const hasClipboard = this.clipboard && this.clipboard.length > 0;

      pasteBtn.prop("disabled", !hasClipboard);
      pasteBtn.toggleClass("opacity-50 cursor-not-allowed", !hasClipboard);

      if (hasClipboard) {
        pasteBtn.attr(
          "title",
          `Paste ${this.clipboard.length} items (${this.clipboardAction})`
        );
      } else {
        pasteBtn.attr("title", "Paste");
      }
    }

    downloadFiles() {
      if (this.selectedFiles.size === 0) {
        this.showError("Please select files to download");
        return;
      }

      // For single file, download directly
      if (this.selectedFiles.size === 1) {
        const filePath = Array.from(this.selectedFiles)[0];
        this.downloadSingleFile(filePath);
      } else {
        // For multiple files, create zip
        this.downloadMultipleFiles();
      }
    }

    downloadSingleFile(filePath) {
      // Navigate to the download URL to trigger browser download
      const url =
        wpfm_ajax.ajax_url +
        "?action=wpfm_file_manager&action_type=download_item&path=" +
        encodeURIComponent(filePath) +
        "&nonce=" +
        wpfm_ajax.nonce;

      // Prefer anchor click to avoid iframe/csp blockers
      const anchor = document.createElement("a");
      anchor.href = url;
      anchor.download = "";
      document.body.appendChild(anchor);
      anchor.click();
      document.body.removeChild(anchor);

      this.showSuccess("Download started...");
    }

    async downloadMultipleFiles() {
      this.showStatus("Preparing download...");

      try {
        // This would need server-side implementation for multiple file zip
        // For now, we'll download files one by one
        const files = Array.from(this.selectedFiles);
        for (const filePath of files) {
          await this.downloadSingleFile(filePath);
          // Small delay between downloads
          await new Promise((resolve) => setTimeout(resolve, 500));
        }
        this.showSuccess("All downloads started");
      } catch (error) {
        this.showError("Download failed: " + error);
      }
    }

    sortFiles(criteria) {
      if (!Array.isArray(this.currentFiles) || this.currentFiles.length === 0)
        return;

      const files = this.currentFiles.slice();

      // Folders first, then by selected criteria
      files.sort((a, b) => {
        if (a.is_dir && !b.is_dir) return -1;
        if (!a.is_dir && b.is_dir) return 1;

        switch (criteria) {
          case "name":
            return a.name.localeCompare(b.name, undefined, {
              sensitivity: "base",
            });
          case "date":
            return (a.modified || 0) - (b.modified || 0);
          case "size":
            return (a.size || 0) - (b.size || 0);
          case "type":
            return (a.icon || "").localeCompare(b.icon || "", undefined, {
              sensitivity: "base",
            });
          default:
            return a.name.localeCompare(b.name, undefined, {
              sensitivity: "base",
            });
        }
      });

      this.displayFiles(files);
    }

    searchFiles(query) {
      // Implementation for searching files
      if (query.length > 2) {
        this.showInfo(`Searching for: ${query}`);
      }
    }

    // (removed legacy duplicate DB Manager methods to align with current template)
    async runQuery() {
      const query = $("#wpfm-sql-query").val().trim();
      if (!query) {
        this.showError("Please enter a SQL query");
        return;
      }

      this.showStatus("Executing query...");

      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "run_query",
            query: query,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.displayQueryResults(response.data);
          this.showSuccess(
            `Query executed successfully. ${response.data.count} rows returned.`
          );
          // Ensure results panel is visible even if user previously had a table selected
          $("#wpfm-query-results").removeClass("hidden");
          $("#wpfm-table-section").addClass("hidden");
          $("#wpfm-query-section").removeClass("hidden");
        } else {
          this.showError("Query failed: " + response.data);
        }
      } catch (error) {
        console.error("Query error:", error);
        this.showError(
          "Query error: " + (error.responseJSON?.data || error.statusText)
        );
      }
    }

    displayQueryResults(data) {
      const container = $("#wpfm-results-content");
      const resultsDiv = $("#wpfm-query-results");

      if (!Array.isArray(data.results) || data.results.length === 0) {
        container.html(
          '<p class="text-gray-500 py-4 text-center">No results returned.</p>'
        );
        resultsDiv.removeClass("hidden");
        return;
      }

      let html =
        '<div class="overflow-x-auto"><table class="wpfm-results-table min-w-max bg-white">';

      // Table header
      html += '<thead class="bg-gray-50"><tr>';
      Object.keys(data.results[0]).forEach((key) => {
        html += `<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border">${key}</th>`;
      });
      html += "</tr></thead>";

      // Table body
      html += '<tbody class="bg-white divide-y divide-gray-200">';
      data.results.forEach((row) => {
        html += "<tr>";
        Object.values(row).forEach((value) => {
          const displayValue =
            value === null
              ? "<em>null</em>"
              : this.escapeHtml(value.toString());
          html += `<td class="px-4 py-2 text-sm text-gray-900 border">${displayValue}</td>`;
        });
        html += "</tr>";
      });
      html += "</tbody></table></div>";

      container.html(html);
      resultsDiv.removeClass("hidden");
      // Ensure we stay on DB Manager view
      $("#wpfm-query-section").removeClass("hidden");
      $("#wpfm-table-section").addClass("hidden");
    }

    useQuickQuery(e) {
      const query =
        $(e.target).closest("button, .wpfm-db-quick-action").data("query") ||
        $(e.target).closest(".wpfm-db-quick-action").data("action");

      // If a pre-canned SQL string exists, run it
      const action = $(e.target)
        .closest(".wpfm-db-quick-action")
        .data("action");
      if (action === "export_all") {
        this.exportDatabase();
        return;
      }
      if (action === "optimize") {
        $("#wpfm-sql-query").val("SHOW TABLES");
        this.runQuery();
        return;
      }
      if (action === "db_info") {
        // Use version(), now(), database() for broader compatibility
        $("#wpfm-sql-query").val(
          "SELECT VERSION() AS version, NOW() AS current_time, DATABASE() AS current_database"
        );
        this.runQuery();
        return;
      }
      if (action === "show_tables") {
        $("#wpfm-sql-query").val("SHOW TABLES");
        this.runQuery();
        return;
      }

      if (query) {
        $("#wpfm-sql-query").val(query);
        this.runQuery();
      }
    }

    clearQuery() {
      $("#wpfm-sql-query").val("");
      $("#wpfm-query-results").addClass("hidden");
    }

    // Utility Methods
    formatFileSize(bytes) {
      if (bytes === 0) return "0 B";
      const k = 1024;
      const sizes = ["B", "KB", "MB", "GB"];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + " " + sizes[i];
    }

    formatDate(timestamp) {
      return new Date(timestamp * 1000).toLocaleDateString();
    }

    escapeHtml(unsafe) {
      if (!unsafe) return "";
      return unsafe
        .toString()
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    handleKeyboard(e) {
      // Ctrl+A to select all
      if (e.ctrlKey && e.key === "a") {
        e.preventDefault();
        this.selectAllFiles();
      }

      // Delete key to delete selected files
      if (e.key === "Delete" && this.selectedFiles.size > 0) {
        e.preventDefault();
        this.deleteFiles();
      }

      // Ctrl+Enter to run query in DB Manager
      if (e.ctrlKey && e.key === "Enter" && $("#wpfm-sql-query").is(":focus")) {
        this.runQuery();
      }
    }

    selectAllFiles() {
      $(".wpfm-file-item, .wpfm-folder-item").each((index, element) => {
        const path = $(element).data("path");
        this.selectedFiles.add(path);
      });
      this.updateSelectionUI();
    }

    showFileEditor(filePath, fileName) {
      this.getFileContent(filePath)
        .then((content) => {
          this.displayFileEditor(filePath, fileName, content);
        })
        .catch((error) => {
          console.error("File loading error:", error);
          let errorMessage = "Failed to load file";
          if (error.responseJSON && error.responseJSON.data) {
            errorMessage = error.responseJSON.data;
          } else if (error.statusText) {
            errorMessage = error.statusText;
          }
          this.showError(errorMessage);
        });
    }

    displayFileEditor(filePath, fileName, fileData) {
      // Close any existing editor
      this.closeFileEditor();

      const fileExtension = fileName.split(".").pop().toLowerCase();
      const languageClass = this.getLanguageClass(fileExtension);

      const editorModal = $(`
        <div id="wpfm-file-editor-modal" class="fixed inset-0 bg-black bg-opacity-75 flex items-center justify-center z-50 p-4">
            <div class="bg-white rounded-lg w-full max-w-6xl h-5/6 flex flex-col shadow-2xl">
                <!-- Header -->
                <div class="border-b border-gray-200 p-4 flex justify-between items-center bg-gray-50 rounded-t-lg">
                    <div>
                        <h3 class="text-lg font-semibold text-gray-800 flex items-center">
                            <i class="bi bi-file-earmark-edit mr-2 text-blue-500"></i>
                            Editing: <span class="font-mono ml-1">${fileName}</span>
                        </h3>
                        <p class="text-sm text-gray-600 mt-1">${filePath}</p>
                    </div>
                    <div class="flex space-x-2">
                        <button id="wpfm-editor-save" class="bg-green-500 text-white px-4 py-2 rounded hover:bg-green-600 flex items-center transition-colors">
                            <i class="bi bi-check-lg mr-2"></i>Save
                        </button>
                        <button onclick="wpfm.closeFileEditor()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 flex items-center transition-colors">
                            <i class="bi bi-x-lg mr-2"></i>Close
                        </button>
                    </div>
                </div>
                
                <!-- Editor Area -->
                <div class="flex-1 flex flex-col p-1">
                    <div class="flex items-center justify-between px-4 py-2 bg-gray-100 border-b">
                        <div class="text-sm text-gray-600">
                            <span class="font-medium">Size:</span> ${this.formatFileSize(
                              fileData.size
                            )} | 
                            <span class="font-medium">Modified:</span> ${this.formatDate(
                              fileData.modified
                            )} |
                            <span class="font-medium">Language:</span> ${languageClass}
                        </div>
                        <div class="flex space-x-2">
                            <button id="wpfm-editor-format" class="text-xs bg-blue-100 text-blue-700 px-2 py-1 rounded hover:bg-blue-200">
                                Format Code
                            </button>
                            <button id="wpfm-editor-search" class="text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded hover:bg-gray-200">
                                Find & Replace
                            </button>
                            <label class="ml-2 text-xs flex items-center space-x-1">
                              <input id="wpfm-editor-syntax-toggle" type="checkbox" class="rounded" ${wpfm_ajax.syntax_check ? 'checked' : ''}>
                              <span>Check PHP syntax</span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="flex-1" style="position: relative;">
                        <style>
                          #wpfm-editor-line-numbers .wpfm-line-error { background:#fee2e2; color:#b91c1c; }
                        </style>
                        <textarea 
                            id="wpfm-file-editor" 
                            class="w-full h-full font-mono text-sm border-0 p-4 resize-none focus:outline-none"
                            spellcheck="false"
                            placeholder="File content will appear here..." style="padding-left: 64px;"
                        >${this.escapeHtml(fileData.content)}</textarea>
                        
                        <!-- Line numbers -->
                        <div id="wpfm-editor-line-numbers" class="absolute left-0 top-0 bottom-0 w-12 bg-gray-50 border-r border-gray-200 overflow-hidden text-right py-4 text-gray-500 text-xs font-mono select-none"></div>
                    </div>
                </div>
                
                <!-- Footer -->
                <div class="border-t border-gray-200 p-3 bg-gray-50 text-sm text-gray-600 rounded-b-lg">
                    <div class="flex justify-between items-center">
                        <div>
                            <span class="font-medium">Tips:</span> 
                            <kbd class="bg-gray-200 px-1 rounded text-xs mx-1">Ctrl+S</kbd> to Save, 
                            <kbd class="bg-gray-200 px-1 rounded text-xs mx-1">Esc</kbd> to Close
                        </div>
                        <div id="wpfm-editor-status" class="text-green-600 font-medium">
                            Ready
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `);

      $("body").append(editorModal);

      // Initialize line numbers
      this.updateLineNumbers();

      // Bind events
      $("#wpfm-editor-save").on("click", () => {
        this.saveFileContent(filePath);
      });

      $("#wpfm-file-editor").on("input", () => {
        this.updateLineNumbers();
        $("#wpfm-editor-status")
          .text("Unsaved changes")
          .removeClass("text-green-600")
          .addClass("text-yellow-600");
        this.maybeLint(fileName);
      });
      $("#wpfm-editor-syntax-toggle").on("change", () => this.maybeLint(fileName));

      // Format Code button (VS Code-like using Prettier/sql-formatter)
      $("#wpfm-editor-format").on("click", async () => {
        const $ta = $("#wpfm-file-editor");
        const content = $ta.val();
        const ext = (fileName.split(".").pop() || "").toLowerCase();
        try {
          let formatted = null;

          if (ext === "sql") {
            await this.ensureSqlFormatter();
            formatted = window.sqlFormatter.format(content, {
              uppercase: true,
            });
          } else {
            await this.ensurePrettier();
            formatted = this.formatWithPrettier(content, ext);
          }

          if (!formatted) {
            // Fallback: normalize newlines and trim trailing spaces
            formatted =
              content
                .replace(/\r\n?/g, "\n")
                .split("\n")
                .map((line) => line.replace(/\s+$/g, ""))
                .join("\n")
                .trimEnd() + "\n";
          }

          $ta.val(formatted).trigger("input");
          this.showInfo("Formatted");
        } catch (err) {
          const msg = err?.message || err;
          this.showWarning("Format failed: " + msg);
        }
      });

      // Find & Replace modal
      $("#wpfm-editor-search").on("click", () => this.showFindReplaceModal());

      // Keyboard shortcuts
      $("#wpfm-file-editor").on("keydown", (e) => {
        if (e.ctrlKey && e.key === "s") {
          e.preventDefault();
          this.saveFileContent(filePath);
        }

        if (e.key === "Escape") {
          e.preventDefault();
          this.closeFileEditor();
        }
      });

      // Focus the editor
      $("#wpfm-file-editor").focus();
      // Initial lint pass if enabled
      this.maybeLint(fileName);
    }

    getLanguageClass(extension) {
      const languages = {
        php: "PHP",
        js: "JavaScript",
        css: "CSS",
        html: "HTML",
        htm: "HTML",
        json: "JSON",
        xml: "XML",
        md: "Markdown",
        sql: "SQL",
        txt: "Plain Text",
        log: "Log File",
        csv: "CSV",
        yml: "YAML",
        yaml: "YAML",
        ini: "INI",
        conf: "Config",
        config: "Config",
      };

      return languages[extension] || extension.toUpperCase();
    }

    updateLineNumbers() {
      const editor = $("#wpfm-file-editor");
      const lineNumbers = $("#wpfm-editor-line-numbers");
      const lines = editor.val().split("\n").length;

      let numbersHtml = "";
      for (let i = 1; i <= lines; i++) {
        const hasErr = this._syntaxErrors && this._syntaxErrors.has(i);
        numbersHtml += `<div class="px-2 ${hasErr ? 'wpfm-line-error' : ''}">${i}</div>`;
      }

      lineNumbers.html(numbersHtml);

      // Sync scrolling
      editor.on("scroll", () => {
        lineNumbers.scrollTop(editor.scrollTop());
      });
    }

    async saveFileContent(filePath) {
      const content = $("#wpfm-file-editor").val();
      const fileName = (String(filePath).split("/").pop() || "");
      const ext = (fileName.split(".").pop() || "").toLowerCase();

      // If PHP syntax checking is enabled, lint before save and block on errors
      try {
        const enabled = $("#wpfm-editor-syntax-toggle").is(":checked") && !!wpfm_ajax.syntax_check;
        if (enabled && ext === "php") {
          // Use last lint if available and content unchanged, otherwise lint now
          const lintNow = await this.lintPhp(content);
          this._lastLint = lintNow;
          if (!lintNow.ok) {
            const line = lintNow.line || null;
            this._syntaxErrors = new Set();
            if (line) this._syntaxErrors.add(line);
            this.updateLineNumbers();
            this.scrollEditorToLine(line);
            $("#wpfm-editor-status")
              .text((lintNow.message || "PHP syntax error") + (line ? ` (line ${line})` : ""))
              .removeClass("text-green-600 text-yellow-600")
              .addClass("text-red-600");
            this.showError("Cannot save: PHP syntax error" + (line ? ` on line ${line}` : ""));
            return; // Block save
          }
        }
      } catch (_) {}

      this.showStatus("Saving file...");

      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "save_file_content",
            path: filePath,
            content: content,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          this.showSuccess("File saved successfully");
          $("#wpfm-editor-status")
            .text("Saved")
            .removeClass("text-yellow-600")
            .addClass("text-green-600");
        } else {
          throw new Error(response.data);
        }
      } catch (error) {
        console.error("Save error:", error);
        let errorMessage = "Failed to save file";
        if (error.responseJSON && error.responseJSON.data) {
          errorMessage = error.responseJSON.data;
        } else if (error.statusText) {
          errorMessage = error.statusText;
        }
        this.showError(errorMessage);
      }
    }

    closeFileEditor() {
      const editor = $("#wpfm-file-editor-modal");
      if (editor.length) {
        const hasUnsavedChanges = $("#wpfm-editor-status").hasClass(
          "text-yellow-600"
        );

        if (hasUnsavedChanges) {
          // If status is 'Saved', do not block close
          const statusText = $("#wpfm-editor-status").text();
          if (statusText !== "Saved") {
            if (
              !confirm(
                "You have unsaved changes. Are you sure you want to close?"
              )
            ) {
              return;
            }
          }
        }
        editor.remove();
      }
    }

    async getFileContent(filePath) {
      try {
        const response = await $.ajax({
          url: wpfm_ajax.ajax_url,
          type: "POST",
          data: {
            action: "wpfm_file_manager",
            action_type: "get_file_content",
            path: filePath,
            nonce: wpfm_ajax.nonce,
          },
          dataType: "json",
        });

        if (response.success) {
          return response.data;
        } else {
          throw new Error(response.data);
        }
      } catch (error) {
        console.error("AJAX Error:", error);
        throw error;
      }
    }

    // UI Feedback Methods
    showLoading() {
      $("#wpfm-loading").removeClass("hidden");
      $("#wpfm-file-grid").addClass("hidden");
      $("#wpfm-file-list").addClass("hidden");
      $("#wpfm-empty").addClass("hidden");
    }

    hideLoading() {
      $("#wpfm-loading").addClass("hidden");
    }

    showStatus(message) {
      $("#wpfm-status").text(message);
    }

    showSuccess(message) {
      this.showStatus(message);
      this.showNotification(message, "success");
    }

    showError(message) {
      this.showStatus("Error: " + message);
      this.showNotification(message, "error");
    }

    showWarning(message) {
      this.showStatus("Warning: " + message);
      this.showNotification(message, "warning");
    }

    showInfo(message) {
      this.showNotification(message, "info");
    }

    showNotification(message, type = "info") {
      // Remove existing notifications
      $(".wpfm-notification").remove();

      const bgColor =
        {
          success: "bg-green-500",
          error: "bg-red-500",
          warning: "bg-yellow-500",
          info: "bg-blue-500",
        }[type] || "bg-blue-500";

      const notification = $(`
                <div class="wpfm-notification fixed top-4 right-4 ${bgColor} text-white px-6 py-3 rounded-lg shadow-lg z-50">
                    <div class="flex items-center justify-between">
                        <span>${message}</span>
                        <button class="ml-4 text-white hover:text-gray-200">&times;</button>
                    </div>
                </div>
            `);

      $("body").append(notification);

      notification.find("button").on("click", function () {
        notification.fadeOut(300, function () {
          $(this).remove();
        });
      });

      setTimeout(() => {
        notification.fadeOut(300, function () {
          $(this).remove();
        });
      }, 5000);
    }
  }

  function getTranslation(key) {
    return wpfm_ajax.translations[key] || key;
  }

  // Initialize when document is ready
  $(document).ready(() => {
    if (
      $("#wpfm-files-container").length ||
      $("#wpfm-sql-query").length ||
      $("#wpfm-bk-start").length ||
      $("#wpfm-bk-table").length
    ) {
      window.wpfm = new WPFileManager();
    }
  });
})(jQuery);
