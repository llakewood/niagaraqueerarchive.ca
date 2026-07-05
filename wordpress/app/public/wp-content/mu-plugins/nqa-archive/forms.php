<?php
/**
 * CF7 integration — adds the nqa-form class to the archive submission form
 * so the NQA form styles (assets/nqa.css §8) are applied.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'wpcf7_form_class_attr',
	function ( $class ) {
		return $class . ' nqa-form';
	}
);

// CF7 runs its own autop pass on form tags — disable it so our layout divs
// don't get wrapped in <p> elements.
add_filter( 'wpcf7_autop_or_not', '__return_false' );
