<?php
/**
 * CF7 integration — adds the nqa-form class to the archive submission form
 * so the NQA form styles (assets/nqa.css §8) are applied.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'wpcf7_form_class_attr',
	function ( $class, $form = null ) {
		if ( $form instanceof WPCF7_ContactForm && in_array( (int) $form->id(), array( 60, 61 ), true ) ) {
			$class .= ' nqa-form';
		}
		return $class;
	},
	10,
	2
);
