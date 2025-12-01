<div class="wrap wpfm-file-manager bg-gray-50 min-h-screen" data-theme="<?php echo esc_attr($theme); ?>">

    <div class="flex h-screen">
        <!-- Left Sidebar: File Explorer -->
        <div class="w-64 bg-white border-r border-gray-200 flex flex-col">
            <!-- Sidebar Header -->
            <div class="p-4 border-b border-gray-200">
                <h2 class="text-lg font-semibold text-gray-800 flex items-center">
                    <i class="bi bi-folder2-open mr-2 text-blue-500"></i>
                    File Explorer
                </h2>
            </div>

            <!-- Directory Tree -->
            <div class="flex-1 overflow-y-auto p-2">
                <div id="wpfm-directory-tree">
                    <div class="flex items-center p-2 text-gray-500">
                        <div class="animate-spin rounded-full h-4 w-4 border-b-2 border-blue-500 mr-2"></div>
                        Loading wp-content directory...
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col">
            <!-- Toolbar -->
            <div class="bg-white border-b border-gray-200 p-3">
                <div class="flex flex-wrap items-center gap-2">
                    <!-- Navigation -->
                    <div class="flex items-center space-x-1 mr-4">
                        <button id="wpfm-back" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Back">
                            <i class="bi bi-arrow-left"></i>
                        </button>
                        <button id="wpfm-forward" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Forward">
                            <i class="bi bi-arrow-right"></i>
                        </button>
                        <button id="wpfm-up" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Up">
                            <i class="bi bi-arrow-up"></i>
                        </button>
                    </div>

                    <!-- File Operations -->
                    <div class="flex items-center space-x-1 mr-4">
                        <button id="wpfm-new-folder" class="p-2 text-gray-600 hover:bg-gray-100 rounded flex items-center" title="New Folder">
                            <i class="bi bi-folder-plus mr-1"></i>
                        </button>
                        <button id="wpfm-new-file" class="p-2 text-gray-600 hover:bg-gray-100 rounded flex items-center" title="New File">
                            <i class="bi bi-file-plus mr-1"></i>
                        </button>
                        <button id="wpfm-upload-btn" class="p-2 text-gray-600 hover:bg-gray-100 rounded flex items-center" title="Upload">
                            <i class="bi bi-cloud-upload mr-1"></i>
                        </button>
                    </div>

                    <!-- Edit Operations -->
                    <div class="flex items-center space-x-1 mr-4">
                        <button id="wpfm-cut" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Cut">
                            <i class="bi bi-scissors"></i>
                        </button>
                        <button id="wpfm-copy" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Copy">
                            <i class="bi bi-files"></i>
                        </button>
                        <button id="wpfm-paste" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Paste">
                            <i class="bi bi-clipboard"></i>
                        </button>
                        <button id="wpfm-rename" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Rename">
                            <i class="bi bi-input-cursor-text"></i>
                        </button>
                        <button id="wpfm-delete" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Delete">
                            <i class="bi bi-trash"></i>
                        </button>
                        <button id="wpfm-download" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Download">
                            <i class="bi bi-download"></i>
                        </button>
                    </div>

                    <!-- View Modes -->
                    <div class="flex items-center space-x-1 mr-4">
                        <button id="wpfm-view-grid" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Grid View">
                            <i class="bi bi-grid-3x3-gap"></i>
                        </button>
                        <button id="wpfm-view-list" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="List View">
                            <i class="bi bi-list-ul"></i>
                        </button>
                    </div>

                    <!-- Sort -->
                    <div class="flex items-center space-x-1 mr-4">
                        <select id="wpfm-sort" class="border rounded px-2 py-1 text-sm">
                            <option value="name">Sort by Name</option>
                            <option value="date">Sort by Date</option>
                            <option value="size">Sort by Size</option>
                            <option value="type">Sort by Type</option>
                        </select>
                    </div>

                    <!-- Info & Settings -->
                    <div class="flex items-center space-x-1">
                        <button id="wpfm-info" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Info">
                            <i class="bi bi-info-circle"></i>
                        </button>
                        <a href="<?php echo admin_url('admin.php?page=wp-file-manager-settings'); ?>" class="p-2 text-gray-600 hover:bg-gray-100 rounded" title="Settings">
                            <i class="bi bi-gear"></i>
                        </a>
                    </div>

                    <!-- Search -->
                    <div class="flex-1 max-w-xs ml-auto">
                        <div class="relative">
                            <input type="text" id="wpfm-search" placeholder="Search files..."
                                class="w-full border rounded pl-8 pr-4 py-1 text-sm focus:ring-2 focus:ring-blue-500 focus:border-transparent">
                            <i class="bi bi-search absolute left-2 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                        </div>
                    </div>
                </div>
            </div>

            <!-- File Content Area -->
            <div class="flex-1 bg-white overflow-hidden">
                <!-- Breadcrumb -->
                <div class="border-b border-gray-200 px-4 py-2 bg-gray-50">
                    <nav id="wpfm-breadcrumb">
                        <ol class="flex items-center space-x-2 text-sm">
                            <li><a href="#" data-path="/" class="text-blue-500 hover:text-blue-700 font-medium">wp-content</a></li>
                        </ol>
                    </nav>
                </div>

                <!-- File Grid/List View -->
                <div class="h-full overflow-auto">
                    <!-- Loading -->
                    <div id="wpfm-loading" class="p-8 text-center">
                        <div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-500 mx-auto"></div>
                        <p class="text-gray-500 mt-2">Loading files...</p>
                    </div>

                    <!-- File Grid -->
                    <div id="wpfm-file-grid" class="p-4 hidden">
                        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 2xl:grid-cols-8 gap-4" id="wpfm-files-container">
                            <!-- Files will be loaded here -->
                        </div>
                    </div>

                    <!-- File List -->
                    <div id="wpfm-file-list" class="hidden">
                        <table class="w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Size</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Modified</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Permissions</th>
                                </tr>
                            </thead>
                            <tbody id="wpfm-files-list-container" class="bg-white divide-y divide-gray-200">
                                <!-- List items will be loaded here -->
                            </tbody>
                        </table>
                    </div>

                    <!-- Empty State -->
                    <div id="wpfm-empty" class="p-8 text-center hidden">
                        <i class="bi bi-folder-x text-4xl text-gray-300 mb-2"></i>
                        <p class="text-gray-500">No files found</p>
                    </div>
                </div>
            </div>

            <!-- Status Bar -->
            <div class="bg-gray-800 text-white px-4 py-2 flex justify-between items-center text-sm">
                <div>
                    <span id="wpfm-items-count">0</span> items
                </div>
                <div>
                    Total size: <span id="wpfm-total-size">0 B</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Upload Input -->
<input type="file" id="wpfm-file-input" multiple class="hidden">

<!-- Modals -->
<div id="wpfm-create-folder-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96">
        <h3 class="text-lg font-semibold mb-4">Create New Folder</h3>
        <input type="text" id="wpfm-folder-name" placeholder="Folder name" class="w-full border rounded px-3 py-2 mb-4">
        <div class="flex justify-end space-x-2">
            <button class="px-4 py-2 text-gray-600 hover:text-gray-800" onclick="wpfm.closeModal()">Cancel</button>
            <button id="wpfm-create-folder-confirm" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Create</button>
        </div>
    </div>
</div>

<div id="wpfm-create-file-modal" class="fixed inset-0 bg-black bg-opacity-50 hidden items-center justify-center z-50">
    <div class="bg-white rounded-lg p-6 w-96">
        <h3 class="text-lg font-semibold mb-4">Create New File</h3>
        <input type="text" id="wpfm-file-name" placeholder="File name" class="w-full border rounded px-3 py-2 mb-4">
        <div class="flex justify-end space-x-2">
            <button class="px-4 py-2 text-gray-600 hover:text-gray-800" onclick="wpfm.closeCreateFileModal()">Cancel</button>
            <button id="wpfm-create-file-confirm" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Create</button>
        </div>
    </div>
</div>

<script>
    function closeModal() {
        document.getElementById('wpfm-create-folder-modal').classList.add('hidden');
    }

    function closeCreateFileModal() {
        document.getElementById('wpfm-create-file-modal').classList.add('hidden');
    }
</script>