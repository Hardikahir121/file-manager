<div class="wrap wpfm-db-manager bg-gray-50 min-h-screen">
    <div class="flex h-screen">
        <!-- Left Sidebar: Database Explorer -->
        <div class="w-64 bg-white border-r border-gray-200 flex flex-col">
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="bi bi-database-fill mr-2 text-green-500"></i>
                    Database Explorer
                </h2>
            </div>

            <!-- Database Info -->
            <div class="p-3 border-b border-gray-200 bg-gray-50">
                <div class="text-xs text-gray-600 space-y-1">
                    <div class="flex justify-between">
                        <span>Host:</span>
                        <span class="font-mono"><?php echo DB_HOST; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Database:</span>
                        <span class="font-mono"><?php echo DB_NAME; ?></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Tables:</span>
                        <span id="wpfm-db-table-count" class="font-mono">0</span>
                    </div>
                </div>
            </div>

            <!-- Tables List -->
            <div class="flex-1 overflow-y-auto p-2">
                <div id="wpfm-db-sidebar" class="space-y-1">
                    <div class="flex items-center p-2 text-gray-500">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-green-500 mr-2"></div>
                        Loading tables...
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col" style="width: calc(100% - 16rem);">
            <!-- Toolbar -->
            <div class="bg-white border-b border-gray-200 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Navigation -->
                    <div class="flex items-center space-x-1 mr-4">
                        <button id="wpfm-db-back" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Back">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <button id="wpfm-db-forward" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Forward">
                            <i class="bi bi-arrow-right"></i>
                        </button>
                    </div>

                    <!-- Table Operations -->
                    <div class="flex items-center space-x-1 mr-4">
                        <button id="wpfm-new-table" class="p-2 text-gray-600 hover:bg-gray-100 rounded flex items-center" title="New Table">
                            <i class="bi bi-plus-circle mr-1"></i>New Table
                        </button>
                        <button id="wpfm-import-db" class="p-2 text-gray-600 hover:bg-gray-100 rounded flex items-center" title="Import SQL">
                            <i class="bi bi-upload mr-1"></i>Import
                        </button>
                        <button id="wpfm-export-db" class="p-2 text-gray-600 hover:bg-gray-100 rounded flex items-center" title="Export Database">
                            <i class="bi bi-download mr-1"></i>Export
                        </button>
                    </div>

                    <!-- Query Operations -->
                    <div class="flex items-center space-x-1 mr-4">
                        <button id="wpfm-run-query" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded flex items-center">
                            <i class="bi bi-play-fill mr-1"></i>Run Query
                        </button>
                        <button id="wpfm-clear-query" class="px-3 py-2 border rounded hover:bg-gray-100 flex items-center">
                            <i class="bi bi-x-circle mr-1"></i>Clear
                        </button>
                    </div>

                    <!-- View Toggle -->
                    <div class="flex items-center space-x-1">
                        <button id="wpfm-view-structure" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Structure">
                            <i class="bi bi-diagram-3"></i>
                        </button>
                        <button id="wpfm-view-content" class="p-2 text-gray-600 hover:bg-gray-100 rounded bg-gray-200" title="Content">
                            <i class="bi bi-table"></i>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Breadcrumb -->
            <div class="border-b border-gray-200 px-4 py-2 bg-gray-50">
                <nav id="wpfm-db-breadcrumb">
                    <ol class="flex items-center space-x-2 text-sm">
                        <li><span class="text-blue-500 font-medium">Host: <?php echo DB_HOST; ?></span></li>
                        <li><span class="mx-2 text-gray-400">/</span></li>
                        <li><span class="text-blue-500 font-medium">Database: <?php echo DB_NAME; ?></span></li>
                    </ol>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="flex-1 bg-white">
                <!-- Query Editor (Visible when no table selected) -->
                <div id="wpfm-query-section" class="h-full p-4">
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">SQL Query</label>
                        <textarea id="wpfm-sql-query" rows="8" placeholder="Enter your SQL query here..."
                            class="w-full border rounded p-4 font-mono text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent"></textarea>
                        <div class="mt-2 flex justify-between items-center text-sm text-gray-500">
                            <span>Press Ctrl+Enter to execute</span>
                            <div class="flex space-x-2">
                                <button class="wpfm-quick-query text-blue-500 hover:text-blue-700" data-query="SHOW TABLES">SHOW TABLES</button>
                                <button class="wpfm-quick-query text-blue-500 hover:text-blue-700" data-query="SELECT NOW()">SELECT NOW()</button>
                                <button class="wpfm-quick-query text-blue-500 hover:text-blue-700" data-query="SELECT VERSION()">SELECT VERSION()</button>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions Grid -->
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
                        <div class="bg-gray-50 border rounded-lg p-4 text-center cursor-pointer hover:bg-gray-100 wpfm-db-quick-action" data-action="show_tables">
                            <i class="bi bi-table text-2xl text-blue-500 mb-2"></i>
                            <div class="text-sm font-medium">Show Tables</div>
                        </div>
                        <div class="bg-gray-50 border rounded-lg p-4 text-center cursor-pointer hover:bg-gray-100 wpfm-db-quick-action" data-action="db_info">
                            <i class="bi bi-info-circle text-2xl text-green-500 mb-2"></i>
                            <div class="text-sm font-medium">Database Info</div>
                        </div>
                        <div class="bg-gray-50 border rounded-lg p-4 text-center cursor-pointer hover:bg-gray-100 wpfm-db-quick-action" data-action="optimize">
                            <i class="bi bi-lightning text-2xl text-yellow-500 mb-2"></i>
                            <div class="text-sm font-medium">Optimize DB</div>
                        </div>
                        <div class="bg-gray-50 border rounded-lg p-4 text-center cursor-pointer hover:bg-gray-100 wpfm-db-quick-action" data-action="export_all">
                            <i class="bi bi-file-earmark-zip text-2xl text-purple-500 mb-2"></i>
                            <div class="text-sm font-medium">Export All</div>
                        </div>
                    </div>
                </div>

                <!-- Table Content (Visible when table selected) -->
                <div id="wpfm-table-section" class="h-full hidden">
                    <!-- Table Toolbar -->
                    <div class="border-b border-gray-200 p-3 bg-gray-50">
                        <div class="flex flex-col md:flex-row md:items-center gap-2">
                            <div class="flex-1 min-w-0">
                                <h3 id="wpfm-current-table" class="text-lg font-semibold"></h3>
                                <p id="wpfm-table-info" class="text-sm text-gray-600"></p>
                            </div>
                            <div class="flex flex-row flex-wrap gap-2 md:ml-4">
                                <button id="wpfm-browse-table" class="bg-blue-500 hover:bg-blue-600 text-white px-3 py-2 rounded flex items-center">
                                    <i class="bi bi-eye mr-1"></i>Browse
                                </button>
                                <button id="wpfm-export-table" class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded flex items-center">
                                    <i class="bi bi-download mr-1"></i>Export
                                </button>
                                <button id="wpfm-import-table" class="bg-yellow-500 hover:bg-yellow-600 text-white px-3 py-2 rounded flex items-center">
                                    <i class="bi bi-upload mr-1"></i>Import
                                </button>
                                <button id="wpfm-drop-table" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded flex items-center">
                                    <i class="bi bi-trash mr-1"></i>Drop
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Table Actions -->
                    <div class="border-b border-gray-200 p-3 bg-white">
                        <div class="flex space-x-2">
                            <button id="wpfm-add-row" class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded flex items-center text-sm">
                                <i class="bi bi-plus-circle mr-1"></i>Add New
                            </button>
                            <button id="wpfm-empty-table" class="bg-orange-500 hover:bg-orange-600 text-white px-3 py-2 rounded flex items-center text-sm">
                                <i class="bi bi-eraser mr-1"></i>Empty Table
                            </button>
                            <button id="wpfm-save-table" class="bg-green-500 hover:bg-green-600 text-white px-3 py-2 rounded flex items-center text-sm">
                                <i class="bi bi-check2 mr-1"></i>Save Changes
                            </button>
                        </div>
                    </div>

                    <!-- Table Content -->
                    <!-- <div class="flex-1 overflow-hidden p-4"> -->
                    <div id="wpfm-table-content" class="w-full">
                        <!-- Table data will be loaded here -->
                    </div>
                    <!-- </div> -->
                </div>

                <!-- Query Results -->
                <div id="wpfm-query-results" class="hidden">
                    <div class="border-t border-gray-200">
                        <div class="border-b p-3 bg-gray-50 flex justify-between items-center">
                            <h3 class="font-semibold">Query Results</h3>
                            <button id="wpfm-close-results" class="text-gray-500 hover:text-gray-700">
                                <i class="bi bi-x-lg"></i>
                            </button>
                        </div>
                        <div class="p-4">
                            <div id="wpfm-results-content" class="overflow-x-auto"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Status Bar -->
            <div class="bg-gray-800 text-white px-4 py-2 flex justify-between items-center text-sm">
                <div>
                    <span id="wpfm-db-status">Ready</span>
                </div>
                <div>
                    <span id="wpfm-query-info"></span>
                </div>
            </div>
        </div>
    </div>
</div>