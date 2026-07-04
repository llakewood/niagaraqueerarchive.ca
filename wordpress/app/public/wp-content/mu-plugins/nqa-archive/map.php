<?php
/**
 * NQA Map — reusable styled location map.
 *
 * Shortcode: [nqa_map]
 * Attributes:
 *   municipality  — comma-separated municipality slugs (default: all)
 *   collection    — comma-separated nqa_collection slugs (default: all)
 *   post_type     — comma-separated post types (default: nqa_place,nqa_org,nqa_event)
 *   height        — map height in pixels (default: 480)
 *   title         — optional heading above the map
 *
 * Auto-injected:
 *   • Collections page — "By Location" section appended after the wayfinding grid
 *   • Municipality archives — filtered map inserted after the listing results
 */

defined( 'ABSPATH' ) || exit;

// ── Style JSON (design spec — archival, minimal) ──────────────────────────

define(
	'NQA_MAP_STYLE_JSON',
	'[{"featureType":"administrative","elementType":"labels.text.fill","stylers":[{"color":"#6195a0"}]},{"featureType":"landscape","elementType":"all","stylers":[{"color":"#f2f2f2"}]},{"featureType":"landscape","elementType":"geometry.fill","stylers":[{"color":"#ffffff"}]},{"featureType":"poi","elementType":"all","stylers":[{"visibility":"off"}]},{"featureType":"poi.park","elementType":"geometry.fill","stylers":[{"color":"#e6f3d6"},{"visibility":"on"}]},{"featureType":"road","elementType":"all","stylers":[{"saturation":-100},{"lightness":45},{"visibility":"simplified"}]},{"featureType":"road.highway","elementType":"all","stylers":[{"visibility":"simplified"}]},{"featureType":"road.highway","elementType":"geometry.fill","stylers":[{"color":"#f4d2c5"},{"visibility":"simplified"}]},{"featureType":"road.highway","elementType":"labels.text","stylers":[{"color":"#4e4e4e"}]},{"featureType":"road.arterial","elementType":"geometry.fill","stylers":[{"color":"#f4f4f4"}]},{"featureType":"road.arterial","elementType":"labels.text.fill","stylers":[{"color":"#787878"}]},{"featureType":"road.arterial","elementType":"labels.icon","stylers":[{"visibility":"off"}]},{"featureType":"transit","elementType":"all","stylers":[{"visibility":"off"}]},{"featureType":"water","elementType":"all","stylers":[{"color":"#eaf6f8"},{"visibility":"on"}]},{"featureType":"water","elementType":"geometry.fill","stylers":[{"color":"#eaf6f8"}]}]'
);

// ── Shortcode ──────────────────────────────────────────────────────────────

add_shortcode( 'nqa_map', 'nqa_map_render' );

function nqa_map_render( $atts ) {
	$atts = shortcode_atts(
		array(
			'municipality' => '',
			'collection'   => '',
			'post_type'    => 'nqa_place,nqa_org,nqa_event',
			'height'       => '480',
			'title'        => '',
		),
		$atts,
		'nqa_map'
	);

	$types  = array_filter( array_map( 'trim', explode( ',', $atts['post_type'] ) ) );
	$munis  = array_filter( array_map( 'trim', explode( ',', $atts['municipality'] ) ) );
	$colls  = array_filter( array_map( 'trim', explode( ',', $atts['collection'] ) ) );
	$height = max( 200, absint( $atts['height'] ) );

	$markers = nqa_map_markers( $types, $munis, $colls );
	if ( empty( $markers ) ) {
		return '';
	}

	$map_id = 'nqa-map-' . uniqid();
	nqa_map_enqueue( $map_id, $markers );

	$type_labels   = array(
		'nqa_place' => 'Places',
		'nqa_org'   => 'Organizations',
		'nqa_event' => 'Events',
	);
	$present_types = array_unique( array_column( $markers, 'type' ) );
	$multi_type    = count( $present_types ) > 1;

	ob_start();
	?>
	<section class="nqa-map-block">
		<?php if ( $atts['title'] ) : ?>
		<h2 class="nqa-map-block__heading"><?php echo esc_html( $atts['title'] ); ?></h2>
		<?php endif; ?>
		<div class="nqa-map-wrap" data-map-target="<?php echo esc_attr( $map_id ); ?>">
			<div class="nqa-map-toolbar">
				<input
					class="nqa-map-search"
					type="search"
					placeholder="Search locations&hellip;"
					aria-label="Search locations"
				>
				<?php if ( $multi_type ) : ?>
				<div class="nqa-map-types" role="group" aria-label="Filter by type">
					<?php foreach ( $present_types as $t ) : ?>
					<label class="nqa-map-type-label">
						<input type="checkbox" data-filter-type="<?php echo esc_attr( $t ); ?>" checked>
						<?php echo esc_html( $type_labels[ $t ] ?? ucwords( str_replace( 'nqa_', '', $t ) ) ); ?>
					</label>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
			<div
				id="<?php echo esc_attr( $map_id ); ?>"
				class="nqa-map-canvas"
				style="height:<?php echo $height; ?>px"
				role="region"
				aria-label="Archive locations map"
			></div>
			<p class="nqa-map-empty" hidden>No locations match your search.</p>
		</div>
	</section>
	<?php
	return ob_get_clean();
}

// ── Marker data query ──────────────────────────────────────────────────────

function nqa_map_markers( array $types, array $munis, array $colls ) : array {
	$tax_query = array();

	if ( $munis ) {
		$tax_query[] = array(
			'taxonomy' => 'municipality',
			'field'    => 'slug',
			'terms'    => $munis,
		);
	}
	if ( $colls ) {
		$tax_query[] = array(
			'taxonomy' => 'nqa_collection',
			'field'    => 'slug',
			'terms'    => $colls,
		);
	}
	if ( count( $tax_query ) > 1 ) {
		array_unshift( $tax_query, array( 'relation' => 'AND' ) );
	}

	$query = new WP_Query(
		array(
			'post_type'      => $types,
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'fields'         => 'ids',
			'meta_query'     => array(
				array( 'key' => 'location', 'compare' => 'EXISTS' ),
				array( 'key' => 'location', 'value' => '', 'compare' => '!=' ),
			),
			'tax_query'      => $tax_query ?: array(),
		)
	);

	$type_badge = array(
		'nqa_place' => 'Place',
		'nqa_org'   => 'Organization',
		'nqa_event' => 'Event',
	);

	$subscriber = current_user_can( 'read' );
	$markers    = array();
	foreach ( $query->posts as $id ) {
		// Active/living records: location visible to subscribers only.
		if ( ! $subscriber && ! nqa_is_historical( $id ) ) {
			continue;
		}
		$loc = get_field( 'location', $id );
		if ( empty( $loc['lat'] ) || empty( $loc['lng'] ) ) {
			continue;
		}
		$type      = get_post_type( $id );
		$markers[] = array(
			'id'      => $id,
			'title'   => get_the_title( $id ),
			'url'     => get_permalink( $id ),
			'lat'     => (float) $loc['lat'],
			'lng'     => (float) $loc['lng'],
			'address' => (string) ( $loc['address'] ?? '' ),
			'type'    => $type,
			'badge'   => $type_badge[ $type ] ?? $type,
		);
	}

	return $markers;
}

// ── Asset loading ──────────────────────────────────────────────────────────

function nqa_map_enqueue( string $map_id, array $markers ) : void {
	static $loaded = false;

	// Per-instance marker data — priority 1 so it lands before the controller at 5.
	add_action(
		'wp_footer',
		static function () use ( $map_id, $markers ) {
			printf(
				"<script>window.nqaMapData=window.nqaMapData||{};window.nqaMapData[%s]=%s;</script>\n",
				wp_json_encode( $map_id ),
				wp_json_encode( $markers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES )
			);
		},
		1
	);

	if ( $loaded ) {
		return;
	}
	$loaded = true;

	$api_key = defined( 'NQA_GOOGLE_MAPS_KEY' ) ? NQA_GOOGLE_MAPS_KEY : '';

	// Google Maps JS — async loading; calls nqaInitMaps when ready.
	wp_enqueue_script(
		'nqa-google-maps',
		add_query_arg(
			array(
				'key'      => $api_key,
				'callback' => 'nqaInitMaps',
				'loading'  => 'async',
			),
			'https://maps.googleapis.com/maps/api/js'
		),
		array(),
		null,
		true
	);

	add_action( 'wp_footer', 'nqa_map_print_controller', 5 );
}

// ── JS Controller ──────────────────────────────────────────────────────────

function nqa_map_print_controller() : void {
	static $done = false;
	if ( $done ) { return; }
	$done = true;

	$style = NQA_MAP_STYLE_JSON;

	// phpcs:disable WordPress.Security.EscapeOutput.OutputNotEscaped
	echo <<<JS
<script>
window.nqaInitMaps = function () {
	'use strict';

	var STYLE = {$style};

	// NQA palette: violet for places, distinct tones for orgs + events
	var PIN = {
		nqa_place: { fill: '#503AA8', stroke: '#2b1e6e' },
		nqa_org:   { fill: '#b44fb4', stroke: '#6d2a6d' },
		nqa_event: { fill: '#e8b800', stroke: '#7a6200' }
	};

	function makePinIcon(type) {
		var c = PIN[type] || PIN.nqa_place;
		return {
			path: 'M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z',
			fillColor:    c.fill,
			fillOpacity:  1,
			strokeColor:  c.stroke,
			strokeWeight: 1.5,
			scale:        1.55,
			anchor:       new google.maps.Point(12, 22)
		};
	}

	Object.keys(window.nqaMapData || {}).forEach(function (mapId) {
		var container = document.getElementById(mapId);
		if (!container) { return; }

		var wrap      = container.closest('.nqa-map-wrap');
		var searchEl  = wrap && wrap.querySelector('.nqa-map-search');
		var filterEls = wrap ? [].slice.call(wrap.querySelectorAll('[data-filter-type]')) : [];
		var emptyEl   = wrap && wrap.querySelector('.nqa-map-empty');
		var markers   = window.nqaMapData[mapId];

		var map = new google.maps.Map(container, {
			styles:            STYLE,
			mapTypeControl:    false,
			streetViewControl: false,
			fullscreenControl: true
		});

		var bounds  = new google.maps.LatLngBounds();
		var infoWin = new google.maps.InfoWindow();
		var items   = [];

		markers.forEach(function (m) {
			var pos    = { lat: m.lat, lng: m.lng };
			var marker = new google.maps.Marker({
				position: pos,
				map:      map,
				title:    m.title,
				icon:     makePinIcon(m.type)
			});
			bounds.extend(pos);

			marker.addListener('click', function () {
				infoWin.setContent(
					'<div class="nqa-iw">' +
					'<span class="nqa-iw-badge">' + m.badge + '</span>' +
					'<div class="nqa-iw-title"><a href="' + m.url + '">' + m.title + '</a></div>' +
					(m.address ? '<div class="nqa-iw-addr">' + m.address + '</div>' : '') +
					'</div>'
				);
				infoWin.open(map, marker);
			});

			items.push({ marker: marker, data: m });
		});

		if (markers.length > 0) {
			map.fitBounds(bounds);
			// Cap zoom for single/clustered pins so we don't over-zoom
			google.maps.event.addListenerOnce(map, 'idle', function () {
				if (map.getZoom() > 16) { map.setZoom(16); }
			});
		}

		function applyFilters() {
			var q = searchEl ? searchEl.value.toLowerCase() : '';
			var activeTypes = {};
			filterEls.forEach(function (cb) {
				activeTypes[cb.dataset.filterType] = cb.checked;
			});

			var visible = 0;
			items.forEach(function (item) {
				var d  = item.data;
				var ok = (filterEls.length === 0 || activeTypes[d.type]) &&
				         (!q || d.title.toLowerCase().indexOf(q) !== -1 ||
				               d.address.toLowerCase().indexOf(q) !== -1);
				item.marker.setVisible(ok);
				if (ok) { visible++; }
			});

			if (emptyEl) { emptyEl.hidden = visible > 0; }
		}

		if (searchEl) { searchEl.addEventListener('input', applyFilters); }
		filterEls.forEach(function (cb) { cb.addEventListener('change', applyFilters); });
	});
};
</script>
JS;
	// phpcs:enable
}

// ── Auto-inject: Collections page ─────────────────────────────────────────

add_filter(
	'the_content',
	function ( $content ) {
		static $done = false;
		if ( $done || ! is_page( 'collections' ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$done = true;
		$map  = do_shortcode( '[nqa_map title="By Location" height="520"]' );
		return $map ? $content . $map : $content;
	},
	15  // after collections.php at 9 and do_shortcode at 11
);

// ── Auto-inject: Municipality archive pages ────────────────────────────────

add_action(
	'wp_footer',
	function () {
		if ( ! is_tax( 'municipality' ) ) {
			return;
		}
		$term = get_queried_object();
		if ( ! ( $term instanceof WP_Term ) ) {
			return;
		}

		$map = do_shortcode(
			'[nqa_map municipality="' . esc_attr( $term->slug ) . '" height="420"]'
		);
		if ( ! $map ) {
			return; // No published locations for this municipality — omit the section.
		}

		// Hidden initially; the inline script moves it after the query block.
		echo '<div id="nqa-muni-map" style="display:none">' . $map . '</div>';
		echo '<script>(function(){'
			. 'var m=document.getElementById("nqa-muni-map");'
			. 'if(!m){return;}'
			. 'var q=document.querySelector(".wp-block-query")||document.querySelector(".entry-content");'
			. 'if(q&&q.parentNode){q.parentNode.insertBefore(m,q.nextSibling);}'
			. 'else{document.body.appendChild(m);}'
			. 'm.style.display="";'
			. '})();</script>' . "\n";
	},
	25
);
