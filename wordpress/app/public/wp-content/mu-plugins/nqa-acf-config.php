<?php
/**
 * Plugin Name: NQA – ACF Runtime Config
 * Description: Wires environment-provided settings into ACF. Contains NO secrets;
 *              reads constants defined per-environment by 0-nqa-runtime-config.php
 *              (injected by the deploy pipeline on production, set locally for dev).
 * Version:     1.0.0
 *
 * This file IS tracked in git, using git environemtn variables. Never put key values here — only references.
 */

defined( 'ABSPATH' ) || exit;

add_action( 'acf/init', function () {
	if ( defined( 'NQA_GOOGLE_MAPS_KEY' ) && NQA_GOOGLE_MAPS_KEY ) {
		acf_update_setting( 'google_api_key', NQA_GOOGLE_MAPS_KEY );
	}
} );

// Default the ACF Google Map "location" field to centre on the Niagara region
// (approx. regional centroid) at a zoom that frames the 12 municipalities.
add_filter( 'acf/load_field/name=location', function ( $field ) {
	if ( ( $field['type'] ?? '' ) === 'google_map' ) {
		$field['center_lat'] = '43.10';
		$field['center_lng'] = '-79.20';
		$field['zoom']       = '10';
	}
	return $field;
} );
