if(use_xmlhttprequest == "1")
{
	MyBB.select2();
	$("#playername").select2({
		placeholder: "Namen suchen",
		minimumInputLength: 2,
		multiple: false,
		allowClear: true,
		ajax: { // instead of writing the function to execute the request we use Select2's convenient helper
			url: "xmlhttp.php?action=get_users",
			dataType: 'json',
			data: function (term, page) {
				return {
					query: term, // search term
				};
			},
			results: function (data, page) { // parse the results into the format expected by Select2.
				// since we are using custom formatting functions we do not need to alter remote JSON data
				return {results: data};
			}
		},
		initSelection: function(element, callback) {
			var value = $(element).val();
			if (value !== "") {
				callback({
					id: value,
					text: value
				});
			}
		},
		// Allow the user entered text to be selected as well
		createSearchChoice:function(term, data) {
			if ( $(data).filter( function() {
				return this.text.localeCompare(term)===0;
			}).length===0) {
				return {id:term, text:term};
			}
		},
	});

	$('[for=username]').on('click', function(){
		$("#playername").select2('open');
		return false;
	});
}