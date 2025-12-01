// File Management Operations
document.addEventListener("DOMContentLoaded", function () {
  const fileManager = document.querySelector(".wpfm-file-manager");
  if (!fileManager) return;

  // Handle file uploads
  fileManager.addEventListener("fileUploaded", function (e) {
    const { fileName, filePath } = e.detail;
    logFileOperation("upload", fileName, filePath);
  });

  // Handle file downloads
  fileManager.addEventListener("fileDownloaded", function (e) {
    const { fileName, filePath } = e.detail;
    logFileOperation("download", fileName, filePath);
  });

  // Handle file edits
  fileManager.addEventListener("fileEdited", function (e) {
    const { fileName, filePath } = e.detail;
    logFileOperation("edit", fileName, filePath);
  });

  function logFileOperation(action, fileName, filePath) {
    const data = new FormData();
    data.append("action", "wpfm_log_operation");
    data.append("file_name", fileName);
    data.append("file_path", filePath);
    data.append("operation", action);
    data.append("nonce", wpfm_vars.nonce);

    fetch(ajaxurl, {
      method: "POST",
      body: data,
      credentials: "same-origin",
    })
      .then((response) => response.json())
      .catch((error) => console.error("Error logging file operation:", error));
  }
});
