document.addEventListener("DOMContentLoaded", function() {
	const typeSelect = document.getElementById("type");
	const genderField = document.getElementById("gender");
	const wantedUrlField = document.getElementById("wantedUrl");

	function toggleFields() {
		const selectedOption = typeSelect.options[typeSelect.selectedIndex];

		if (selectedOption) {
			// Gender-Feld
			if (selectedOption.dataset.hasGender === "1") {
				genderField.style.display = "";
			} else {
				genderField.style.display = "none";
			}

			// Wanted-Feld
			if (selectedOption.dataset.hasWantedUrl === "1") {
				wantedUrlField.style.display = "";
			} else {
				wantedUrlField.style.display = "none";
			}
		}
	}

	typeSelect.addEventListener("change", toggleFields);
	toggleFields();
});