jQuery(document).ready(function ($) {
  // Handle select all checkboxes
  $(".select-all").on("change", function () {
    var isChecked = $(this).prop("checked");
    var table = $(this).closest("table");
    table.find('tbody input[type="checkbox"]').prop("checked", isChecked);
  });

  // Handle search
  $(".wpfm-search-btn").on("click", function () {
    var action = $(this).data("action");
    var searchValue = $(this).prev("input").val();
    var currentUrl = new URL(window.location.href);

    currentUrl.searchParams.set("action", action);
    currentUrl.searchParams.set("search", searchValue);
    currentUrl.searchParams.delete("paged"); // Reset pagination when searching

    window.location.href = currentUrl.toString();
  });

  // Handle bulk actions
  $(".apply-bulk-action").on("click", function () {
    var section = $(this).closest(".wpfm-section");
    var action = section.find(".bulk-action-select").val();
    var selectedIds = [];

    if (!action) {
      alert("Please select an action");
      return;
    }

    section.find('tbody input[type="checkbox"]:checked').each(function () {
      selectedIds.push($(this).val());
    });

    if (selectedIds.length === 0) {
      alert("Please select at least one item");
      return;
    }

    if (
      confirm(
        "Are you sure you want to perform this action on the selected items?"
      )
    ) {
      $.ajax({
        url: ajaxurl,
        type: "POST",
        data: {
          action: "wpfm_bulk_action",
          bulk_action: action,
          ids: selectedIds,
          nonce: wpfm_vars.nonce,
        },
        success: function (response) {
          if (response.success) {
            location.reload();
          } else {
            alert(response.data);
          }
        },
        error: function () {
          alert("Error performing bulk action");
        },
      });
    }
  });

  // Handle enter key in search
  $(".wpfm-search input").on("keypress", function (e) {
    if (e.which === 13) {
      $(this).next(".wpfm-search-btn").click();
    }
  });
});
