<?php
/**
 * Plugin Name: NQA – Grid / List view toggle
 * Description: A grid-or-list view preference control for the Collections wayfinding page —
 *              toggles the By Place / By Theme card grids between a grid and a single-column
 *              list, remembered via an <html> class + localStorage. Archive/taxonomy listings
 *              have their own richer controls in nqa-archive-controls.php. Colour-blocked to
 *              match the archive; progressive enhancement (no JS = the normal grid, no dead
 *              control).
 * Version:     1.0.0
 *
 * Tracked in git; contains no secrets. No child theme.
 */

defined( 'ABSPATH' ) || exit;

/** The Collections wayfinding page (grid/list of doorways). Archive listings
 *  have their own richer controls in nqa-archive-controls.php. */
function nqa_viewtoggle_active() {
	return is_page( 'collections' );
}

/**
 * Set the html class as early as possible (in <head>) from the saved preference,
 * so grid/list is correct on first paint (no flash of the wrong layout).
 */
add_action( 'wp_head', function () {
	if ( ! nqa_viewtoggle_active() ) {
		return;
	}
	echo "<script>try{document.documentElement.classList.add('nqa-view-'+(localStorage.getItem('nqaView')==='list'?'list':'grid'));}catch(e){document.documentElement.classList.add('nqa-view-grid');}</script>\n";
}, 1 );

/** Toggle + layout CSS (pinned palette, WCAG 2.1 AA). */
add_action( 'wp_enqueue_scripts', function () {
	if ( ! nqa_viewtoggle_active() ) {
		return;
	}
	wp_register_style( 'nqa-viewtoggle', false );
	wp_enqueue_style( 'nqa-viewtoggle' );

	$mono = 'var(--wp--preset--font-family--fira-code,ui-monospace,SFMono-Regular,Menlo,monospace)';
	$css = ''
		// --- The toggle control ---
		. '.nqa-viewtoggle{display:flex;justify-content:flex-end;align-items:center;gap:.6rem;'
			. 'margin:0 0 1.4rem;font-family:' . $mono . '}'
		. '.nqa-viewtoggle__label{font-size:.66rem;font-weight:700;letter-spacing:.14em;'
			. 'text-transform:uppercase;color:#503AA8}'
		. '.nqa-viewtoggle__btns{display:inline-flex;border:2px solid #111;box-shadow:3px 3px 0 #111}'
		. '.nqa-viewbtn{appearance:none;-webkit-appearance:none;cursor:pointer;font:inherit;'
			. 'font-size:.72rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;'
			. 'padding:.42rem .85rem;background:#fff;color:#111;border:0;border-inline-start:2px solid #111;'
			. 'line-height:1}'
		. '.nqa-viewbtn:first-child{border-inline-start:0}'
		. '.nqa-viewbtn.is-active{background:#503AA8;color:#fff}'
		. '.nqa-viewbtn:hover:not(.is-active){background:#F6CFF4}'
		. '.nqa-viewbtn:focus-visible{outline:3px solid #FFEE58;outline-offset:2px}'

		// --- Collections page: List view collapses the grids to rows ---
		. 'html.nqa-view-list .nqa-col-grid{grid-template-columns:1fr}'
		. 'html.nqa-view-list .nqa-col-card{flex-direction:row;align-items:stretch}'
		. 'html.nqa-view-list .nqa-col-card__block{height:auto;width:12px;border-bottom:0;'
			. 'border-inline-end:2px solid #111}'
		. 'html.nqa-view-list .nqa-col-card__body{flex-direction:row;align-items:center;'
			. 'gap:1.1rem;flex-wrap:wrap;padding:.7rem 1.1rem}'
		. 'html.nqa-view-list .nqa-col-card__kicker{flex-basis:100%}'
		. 'html.nqa-view-list .nqa-col-card__title{flex:0 0 auto;min-width:11rem;font-size:1.05rem}'
		. 'html.nqa-view-list .nqa-col-card__desc{flex:1 1 12rem;margin:0}'
		. 'html.nqa-view-list .nqa-col-card__count{align-self:center;flex:0 0 auto;margin-inline-start:auto}'
		// The featured banner card is already a row; keep it full-width in both views.
		. 'html.nqa-view-list .nqa-col-card--featured .nqa-col-card__body{flex-wrap:wrap}';

	wp_add_inline_style( 'nqa-viewtoggle', $css );
} );

/**
 * Inject the toggle control and wire it. Runs in the footer so the listing
 * markup already exists; the control is created by JS (progressive enhancement).
 */
add_action( 'wp_footer', function () {
	if ( ! nqa_viewtoggle_active() ) {
		return;
	}
	?>
<script>
(function(){
	var KEY='nqaView', el=document.documentElement;
	if(!el.classList.contains('nqa-view-grid')&&!el.classList.contains('nqa-view-list')){el.classList.add('nqa-view-grid');}
	function cur(){return el.classList.contains('nqa-view-list')?'list':'grid';}
	function sync(){var v=cur();document.querySelectorAll('.nqa-viewbtn').forEach(function(b){var on=b.dataset.view===v;b.classList.toggle('is-active',on);b.setAttribute('aria-pressed',on?'true':'false');});}
	function set(v){el.classList.remove('nqa-view-grid','nqa-view-list');el.classList.add('nqa-view-'+v);try{localStorage.setItem(KEY,v);}catch(e){}sync();}
	var tb=document.createElement('div');
	tb.className='nqa-viewtoggle';
	tb.innerHTML='<span class="nqa-viewtoggle__label">View</span>'+
		'<div class="nqa-viewtoggle__btns" role="group" aria-label="Card view">'+
		'<button type="button" class="nqa-viewbtn" data-view="grid" aria-pressed="false">Grid</button>'+
		'<button type="button" class="nqa-viewbtn" data-view="list" aria-pressed="false">List</button>'+
		'</div>';
	var col=document.querySelector('.nqa-collections');
	if(!col){return;}
	col.insertBefore(tb,col.firstChild);
	tb.addEventListener('click',function(e){var b=e.target.closest('.nqa-viewbtn');if(b){set(b.dataset.view);}});
	sync();
})();
</script>
	<?php
} );
