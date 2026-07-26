<?php
/**
 * Calendar page — a month grid of events (2fr) beside a chronological list of
 * upcoming events (1fr). Rendered by the [nqa_calendar] shortcode, placed on the
 * Calendar page; the header nav's "Calendar" item points there.
 *
 * Event dates are read via get_field() (ACF normalizes the inconsistently-stored
 * start_date/end_date meta to Y-m-d), then compared/placed in PHP — the event set
 * is small, so we never rely on fragile raw-meta string comparisons.
 *
 * Month navigation is server-side via ?ym=YYYY-MM (no JS); the side list always
 * shows what's upcoming from today regardless of the month on screen.
 */

defined( 'ABSPATH' ) || exit;

/** All published events as normalized rows: id,title,url,start,end (timestamps). */
function nqa_calendar_events() : array {
	$posts = get_posts(
		array(
			'post_type'      => 'nqa_event',
			'post_status'    => 'publish',
			'posts_per_page' => -1,
			'no_found_rows'  => true,
		)
	);
	$rows = array();
	foreach ( $posts as $p ) {
		$start = (string) get_field( 'start_date', $p->ID ); // Y-m-d (or '')
		if ( '' === $start ) {
			continue; // undated events can't sit on a calendar
		}
		$start_ts = strtotime( $start );
		$end_raw  = (string) get_field( 'end_date', $p->ID );
		$end_ts   = $end_raw ? strtotime( $end_raw ) : $start_ts;
		if ( $end_ts < $start_ts ) {
			$end_ts = $start_ts;
		}
		$muni_terms = get_the_terms( $p->ID, 'municipality' );
		$rows[] = array(
			'id'    => $p->ID,
			'title' => nqa_decode_entities( get_the_title( $p->ID ) ),
			'url'   => get_permalink( $p->ID ),
			'start' => $start_ts,
			'end'   => $end_ts,
			'muni'  => ( $muni_terms && ! is_wp_error( $muni_terms ) ) ? $muni_terms[0]->name : '',
		);
	}
	return $rows;
}

add_shortcode( 'nqa_calendar', 'nqa_calendar_shortcode' );

function nqa_calendar_shortcode() : string {
	// Which month to show: ?ym=YYYY-MM (validated), else the current month.
	$ym = isset( $_GET['ym'] ) ? sanitize_text_field( wp_unslash( $_GET['ym'] ) ) : '';
	if ( preg_match( '/^(\d{4})-(\d{2})$/', $ym, $m ) && checkdate( (int) $m[2], 1, (int) $m[1] ) ) {
		$year  = (int) $m[1];
		$month = (int) $m[2];
	} else {
		$year  = (int) current_time( 'Y' );
		$month = (int) current_time( 'n' );
	}

	$events      = nqa_calendar_events();
	$grid        = nqa_calendar_grid( $year, $month, $events );
	$list        = nqa_calendar_upcoming_list( $events );

	return '<div class="nqa-calendar">'
		. '<div class="nqa-calendar__main">' . $grid . '</div>'
		. '<aside class="nqa-calendar__side">'
			. '<h2 class="nqa-calendar__side-title">Upcoming</h2>' . $list
		. '</aside>'
		. '</div>';
}

/** The month grid (a table) with events placed on the days they cover. */
function nqa_calendar_grid( int $year, int $month, array $events ) : string {
	$first_ts   = mktime( 0, 0, 0, $month, 1, $year );
	$days       = (int) gmdate( 't', $first_ts );
	$lead       = (int) gmdate( 'w', $first_ts ); // 0 (Sun) … 6 (Sat)
	$today      = current_time( 'Ymd' );
	$month_name = date_i18n( 'F Y', $first_ts );

	// Bucket events by Ymd for each covered day within this month.
	$by_day = array();
	$m_start = $first_ts;
	$m_end   = mktime( 23, 59, 59, $month, $days, $year );
	foreach ( $events as $e ) {
		if ( $e['end'] < $m_start || $e['start'] > $m_end ) {
			continue;
		}
		$cur = max( $e['start'], $m_start );
		$stop = min( $e['end'], $m_end );
		for ( $t = $cur; $t <= $stop; $t += DAY_IN_SECONDS ) {
			$by_day[ (int) gmdate( 'j', $t ) ][] = $e;
		}
	}

	// Prev / next month links (preserve the calendar's own page URL).
	$prev = sprintf( '%04d-%02d', $month === 1 ? $year - 1 : $year, $month === 1 ? 12 : $month - 1 );
	$next = sprintf( '%04d-%02d', $month === 12 ? $year + 1 : $year, $month === 12 ? 1 : $month + 1 );
	$base = get_permalink();
	$nav  = '<div class="nqa-calendar__nav">'
		. '<a class="nqa-calendar__navlink" href="' . esc_url( add_query_arg( 'ym', $prev, $base ) ) . '" rel="prev" aria-label="Previous month">←</a>'
		. '<span class="nqa-calendar__month">' . esc_html( $month_name ) . '</span>'
		. '<a class="nqa-calendar__navlink" href="' . esc_url( add_query_arg( 'ym', $next, $base ) ) . '" rel="next" aria-label="Next month">→</a>'
		. '</div>';

	$dows = array( 'Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat' );
	$head = '';
	foreach ( $dows as $d ) {
		$head .= '<th scope="col"><abbr title="' . esc_attr( $d ) . '">' . esc_html( substr( $d, 0, 1 ) ) . '</abbr></th>';
	}

	$cells = str_repeat( '<td class="nqa-calendar__pad"></td>', $lead );
	for ( $day = 1; $day <= $days; $day++ ) {
		$ymd    = sprintf( '%04d%02d%02d', $year, $month, $day );
		$is_today = ( $ymd === $today );
		$chips  = '';
		foreach ( $by_day[ $day ] ?? array() as $e ) {
			$chips .= '<a class="nqa-calendar__event" href="' . esc_url( $e['url'] ) . '">' . esc_html( $e['title'] ) . '</a>';
		}
		$cells .= '<td class="nqa-calendar__day' . ( $is_today ? ' is-today' : '' ) . ( $chips ? ' has-events' : '' ) . '">'
			. '<span class="nqa-calendar__daynum">' . $day . '</span>'
			. ( $chips ? '<div class="nqa-calendar__events">' . $chips . '</div>' : '' )
			. '</td>';
		if ( ( $lead + $day ) % 7 === 0 && $day !== $days ) {
			$cells .= '</tr><tr>';
		}
	}
	// Trailing pad to complete the last week.
	$trail = ( 7 - ( ( $lead + $days ) % 7 ) ) % 7;
	$cells .= str_repeat( '<td class="nqa-calendar__pad"></td>', $trail );

	return $nav
		. '<table class="nqa-calendar__grid"><thead><tr>' . $head . '</tr></thead>'
		. '<tbody><tr>' . $cells . '</tr></tbody></table>';
}

/** Chronological list of upcoming events (today onward). */
function nqa_calendar_upcoming_list( array $events ) : string {
	$today_ts = strtotime( current_time( 'Y-m-d' ) );
	$upcoming = array_filter( $events, fn( $e ) => $e['end'] >= $today_ts );
	usort( $upcoming, fn( $a, $b ) => $a['start'] <=> $b['start'] );

	if ( ! $upcoming ) {
		return '<p class="nqa-calendar__empty">No upcoming events scheduled just yet. '
			. '<a class="home-hero__events-link" href="' . esc_url( home_url( '/list-an-event/' ) ) . '">Add an event.</a></p>';
	}

	$out = '<ul class="nqa-calendar__list">';
	foreach ( $upcoming as $e ) {
		$range = date_i18n( 'M j', $e['start'] );
		if ( gmdate( 'Ymd', $e['end'] ) !== gmdate( 'Ymd', $e['start'] ) ) {
			$range .= ' – ' . date_i18n( 'M j', $e['end'] );
		}
		$out .= '<li class="nqa-calendar__item"><a href="' . esc_url( $e['url'] ) . '">'
			. '<span class="nqa-calendar__item-date">' . esc_html( $range ) . '</span>'
			. '<span class="nqa-calendar__item-title">' . esc_html( $e['title'] ) . '</span>'
			. ( $e['muni'] ? '<span class="nqa-calendar__item-meta">' . esc_html( $e['muni'] ) . '</span>' : '' )
			. '</a></li>';
	}
	return $out . '</ul>';
}
