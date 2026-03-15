document.addEventListener("DOMContentLoaded", function() {
	const typeSelect = document.getElementById("type");
	const genderField = document.getElementById("genderFieldWrapper");
	const wantedUrlField = document.getElementById("wantedUrlFieldWrapper");
	const typeDescription = document.getElementById("typeDescription");

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

			// Description anzeigen
			if (selectedOption.dataset.description) {
				typeDescription.innerHTML = selectedOption.dataset.description;
			} else {
				typeDescription.innerHTML = "";
			}
		}
	}

	typeSelect.addEventListener("change", toggleFields);
	toggleFields();
});
