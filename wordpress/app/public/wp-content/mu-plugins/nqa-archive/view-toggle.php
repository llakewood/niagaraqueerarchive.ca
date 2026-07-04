<?php
/**
 * Grid / list view toggle for the Collections wayfinding page — toggles the
 * By Place / By Theme card grids between a grid and a single-column list,
 * remembered via an <html> class + localStorage. (Archive/taxonomy listings
 * have their own richer controls in listing-controls.php.) Progressive
 * enhancement: no JS = the normal grid, no dead control.
 */

defined( 'ABSPATH' ) || exit;

/** The Collections wayfinding page (grid/list of doorways). */
function nqa_viewtoggle_active() {
	return is_page( 'collections' );
}

/**
 * Set the html class as early as possible (in <head>) from the saved preference,
 * so grid/list is correct on first paint (no flash of the wrong layout).
 */
add_action(
	'wp_head',
	function () {
		if ( ! nqa_viewtoggle_active() ) {
			return;
		}
		echo "<script>try{document.documentElement.classList.add('nqa-view-'+(localStorage.getItem('nqaView')==='list'?'list':'grid'));}catch(e){document.documentElement.classList.add('nqa-view-grid');}</script>\n";
	},
	1
);

/**
 * Inject the toggle control and wire it. Runs in the footer so the listing
 * markup already exists; the control is created by JS (progressive enhancement).
 */
add_action(
	'wp_footer',
	function () {
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
	}
);
