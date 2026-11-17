
document.addEventListener("DOMContentLoaded", function () {
    const searchForm = document.getElementById("searchForm");
    const searchInput = document.getElementById("productSearch");

    if (searchForm && searchInput) {
        searchForm.addEventListener("submit", function (event) {
            event.preventDefault();

            const query = searchInput.value.trim();

            if (!query) {
                searchInput.focus();
                return;
            }

            alert("Searching for: " + query);
        });
    }
});
