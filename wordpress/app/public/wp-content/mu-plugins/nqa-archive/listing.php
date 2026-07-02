<?php
/**
 * Archive & taxonomy listing styling. Brings the archive's colour-blocked visual
 * language to the listing views — the CPT archives (Person / Organization /
 * Event / Place), the Collections + Municipality taxonomy archives, and
 * category / tag / date archives. The query title becomes a colour-blocked
 * header and each result renders as a catalogue card. Pure CSS scoped to archive
 * views (body.archive), using the shared :root palette variables.
 */

defined( 'ABSPATH' ) || exit;

add_action(
	'wp_enqueue_scripts',
	function () {
		// Only on listing/archive views: CPT archives, taxonomy (collection,
		// municipality), category, tag, date, author. Not singles, not pages.
		if ( ! is_archive() ) {
			return;
		}

		$css = ''
			// --- Colour-blocked query-title header ---
			. 'body.archive .wp-block-query-title{position:relative;display:flex;align-items:center;'
				. 'gap:.85rem;margin-block-end:1.4rem;padding:.85rem 1.15rem;background:var(--nqa-violet);'
				. 'color:var(--nqa-base);border:2px solid var(--nqa-ink);box-shadow:6px 6px 0 var(--nqa-ink);'
				. 'font-family:var(--nqa-mono);font-size:1.35rem;font-weight:700;letter-spacing:.02em;line-height:1.15}'
			. 'body.archive .wp-block-query-title::before{content:"";flex:0 0 auto;width:1.9rem;height:.7rem;'
				. 'background:linear-gradient(90deg,var(--nqa-yellow) 0 33.34%,var(--nqa-pink) 0 66.67%,var(--nqa-base) 0)}'
			// The "Collection:" / "Municipality:" prefix that WP prepends reads as a kicker.
			. 'body.archive .wp-block-query-title span{display:block;font-size:.72rem;letter-spacing:.16em;'
				. 'text-transform:uppercase;color:var(--nqa-yellow);font-weight:700}'
			. 'body.archive .wp-block-term-description{max-width:60ch;margin-block-end:2rem;'
				. 'font-size:1rem;line-height:1.6;color:var(--nqa-ink)}'

			// --- Each result becomes a catalogue card ---
			. 'body.archive .wp-block-query .wp-block-post{position:relative;background:var(--nqa-cream);'
				. 'border:2px solid var(--nqa-ink);box-shadow:5px 5px 0 var(--nqa-ink);'
				. 'padding:1.15rem 1.35rem 1.2rem 1.65rem;margin-block:1.35rem;overflow:hidden;'
				. 'transition:transform .12s ease,box-shadow .12s ease}'
			// Left accent bar (rotates via :nth-child for rhythm).
			. 'body.archive .wp-block-query .wp-block-post::before{content:"";position:absolute;'
				. 'inset-block:0;inset-inline-start:0;width:.55rem;background:var(--nqa-violet)}'
			. 'body.archive .wp-block-query .wp-block-post:nth-child(3n+2)::before{background:var(--nqa-pink)}'
			. 'body.archive .wp-block-query .wp-block-post:nth-child(3n+3)::before{background:var(--nqa-yellow)}'
			. 'body.archive .wp-block-query .wp-block-post:hover{transform:translate(-2px,-2px);'
				. 'box-shadow:8px 8px 0 var(--nqa-ink)}'

			// Title
			. 'body.archive .wp-block-post-title{margin:0 0 .35rem;font-size:1.5rem;line-height:1.2;font-weight:700}'
			. 'body.archive .wp-block-post-title a{color:var(--nqa-ink);text-decoration:none;'
				. 'text-underline-offset:3px}'
			. 'body.archive .wp-block-post-title a:hover{text-decoration:underline;'
				. 'text-decoration-color:var(--nqa-violet);text-decoration-thickness:2px}'
			// Date as a monospaced catalogue tag
			. 'body.archive .wp-block-post-date{margin:0 0 .5rem;font-family:var(--nqa-mono);'
				. 'font-size:.7rem;font-weight:600;letter-spacing:.12em;text-transform:uppercase;color:var(--nqa-violet)}'
			. 'body.archive .wp-block-post-date a{color:inherit;text-decoration:none}'
			// Body/excerpt: clamp so cards stay scannable
			. 'body.archive .wp-block-post-content,body.archive .wp-block-post-excerpt{'
				. 'font-size:.92rem;line-height:1.55;color:#2a2a2a;display:-webkit-box;-webkit-line-clamp:4;'
				. '-webkit-box-orient:vertical;overflow:hidden}'
			. 'body.archive .wp-block-post-content a,body.archive .wp-block-post-excerpt a{color:var(--nqa-violet)}'

			// Keyboard focus (WCAG 2.1 SC 2.4.7)
			. 'body.archive .wp-block-query .wp-block-post a:focus-visible{outline:2px solid var(--nqa-ink);'
				. 'outline-offset:2px;border-radius:2px}'

			// Pagination -> tactile controls
			. 'body.archive .wp-block-query-pagination a,body.archive .wp-block-query-pagination .current{'
				. 'font-family:var(--nqa-mono);font-size:.8rem;font-weight:600;text-decoration:none}'
			. 'body.archive .wp-block-query-pagination a:hover{color:var(--nqa-violet)}'

			. '@media(max-width:480px){body.archive .wp-block-query-title{box-shadow:4px 4px 0 var(--nqa-ink)}'
				. 'body.archive .wp-block-query .wp-block-post{box-shadow:4px 4px 0 var(--nqa-ink)}}';

		nqa_add_style( $css );
	}
);
