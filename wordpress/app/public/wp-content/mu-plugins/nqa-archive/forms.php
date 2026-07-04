<?php
/**
 * CF7 integration — adds the nqa-form class to the archive submission form
 * so the NQA form styles (assets/nqa.css §8) are applied.
 */

defined( 'ABSPATH' ) || exit;

add_filter(
	'wpcf7_form_class_attr',
	function ( $class, $form ) {
		if ( 61 === (int) $form->id() ) {
			$class .= ' nqa-form';
		}
		return $class;
	},
	10,
	2
);
