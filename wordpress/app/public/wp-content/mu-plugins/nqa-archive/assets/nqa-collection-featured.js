( function () {
	document.addEventListener( 'DOMContentLoaded', function () {
		var checkbox = document.querySelector( 'input[name="acf[field_nqa_col_featured]"]' );
		if ( ! checkbox || ! nqaFeatured.currentFeatured ) {
			return;
		}

		checkbox.addEventListener( 'change', function () {
			if ( ! this.checked ) {
				return;
			}
			var msg =
				'“' + nqaFeatured.currentFeatured + '” is currently the featured collection.\n\n' +
				'Remove it and feature this collection instead?';
			if ( ! window.confirm( msg ) ) {
				this.checked = false;
			}
		} );
	} );
}() );
