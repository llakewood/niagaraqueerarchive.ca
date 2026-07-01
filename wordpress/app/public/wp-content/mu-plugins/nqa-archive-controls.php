<?php
/**
 * Plugin Name: NQA – Archive controls (search, filter, columns)
 * Description: Turns the archive/taxonomy listings (Municipality & Collection archives, CPT
 *              archives, category, tag) into a filterable catalogue: a live text search, tag
 *              and category facet filters (built from the records actually on the page), and a
 *              1 / 2 / 3-column grid picker (default 2, remembered via localStorage). All
 *              client-side over the rendered results — no reloads. Colour-blocked to match the
 *              archive; progressive enhancement (no JS = the normal branded listing).
 * Version:     1.0.0
 *
 * Tracked in git; contains no secrets. No child theme.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Show all of an archive's records on one page so the client-side search/filter
 * covers the whole set (the archive is small — a few dozen records per term).
 * Capped to stay safe.
 */
add_action( 'pre_get_posts', function ( $q ) {
	if ( is_admin() || ! $q->is_main_query() ) {
		return;
	}
	if ( $q->is_archive() ) {
		$q->set( 'posts_per_page', 200 );
	}
} );

/** Set the saved column count on <html> in <head> so the grid is right on first paint. */
add_action( 'wp_head', function () {
	if ( ! is_archive() ) {
		return;
	}
	echo "<script>try{var c=localStorage.getItem('nqaArchiveCols');c=(c==='1'||c==='3')?c:'2';"
		. "document.documentElement.classList.add('nqa-acols-'+c);}"
		. "catch(e){document.documentElement.classList.add('nqa-acols-2');}</script>\n";
}, 1 );

add_action( 'wp_enqueue_scripts', function () {
	if ( ! is_archive() ) {
		return;
	}
	wp_register_style( 'nqa-archive-controls', false );
	wp_enqueue_style( 'nqa-archive-controls' );

	$mono = 'var(--wp--preset--font-family--fira-code,ui-monospace,SFMono-Regular,Menlo,monospace)';
	$css = ''
		// --- Column grid (default 2; overridden by the html.nqa-acols-* class) ---
		. 'html[class*="nqa-acols"] body.archive .wp-block-post-template{display:grid;gap:1.35rem;'
			. 'align-items:start;grid-template-columns:repeat(2,minmax(0,1fr))}'
		. 'html.nqa-acols-1 body.archive .wp-block-post-template{grid-template-columns:1fr}'
		. 'html.nqa-acols-3 body.archive .wp-block-post-template{grid-template-columns:repeat(3,minmax(0,1fr))}'
		. '@media(max-width:900px){html.nqa-acols-3 body.archive .wp-block-post-template{grid-template-columns:repeat(2,minmax(0,1fr))}}'
		. '@media(max-width:600px){html[class*="nqa-acols"] body.archive .wp-block-post-template{grid-template-columns:1fr}}'
		. 'html[class*="nqa-acols"] body.archive .wp-block-query .wp-block-post{margin-block:0}'
		. 'body.archive .wp-block-post.nqa-hidden{display:none!important}'

		// --- Control bar shell ---
		. '.nqa-ctrls{background:#FBFAF3;border:2px solid #111;box-shadow:5px 5px 0 #111;'
			. 'padding:1rem 1.15rem;margin:0 0 1.6rem;font-family:' . $mono . ';'
			. 'display:flex;flex-direction:column;gap:.85rem}'
		. '.nqa-ctrls__row{display:flex;flex-wrap:wrap;align-items:center;gap:.7rem}'
		. '.nqa-ctrls__row--top{justify-content:space-between}'
		. '.nqa-ctrls__search{flex:1 1 16rem;display:flex;align-items:center;border:2px solid #111;background:#fff}'
		. '.nqa-ctrls__search input{flex:1;border:0;outline:0;font:inherit;font-size:.9rem;'
			. 'padding:.5rem .7rem;background:transparent;color:#111}'
		. '.nqa-ctrls__search input::placeholder{color:#777}'
		. '.nqa-ctrls__count{font-size:.7rem;font-weight:700;letter-spacing:.1em;text-transform:uppercase;'
			. 'color:#503AA8;white-space:nowrap}'
		. '.nqa-ctrls__cols{display:flex;align-items:center;gap:.5rem}'
		. '.nqa-ctrls__label{font-size:.66rem;font-weight:700;letter-spacing:.14em;text-transform:uppercase;color:#503AA8}'
		. '.nqa-ctrls__colbtns{display:inline-flex;border:2px solid #111;box-shadow:2px 2px 0 #111}'
		. '.nqa-colbtn{appearance:none;-webkit-appearance:none;cursor:pointer;font:inherit;font-size:.78rem;'
			. 'font-weight:700;width:2.1rem;padding:.35rem 0;background:#fff;color:#111;border:0;'
			. 'border-inline-start:2px solid #111;line-height:1}'
		. '.nqa-colbtn:first-child{border-inline-start:0}'
		. '.nqa-colbtn.is-active{background:#503AA8;color:#fff}'
		. '.nqa-colbtn:hover:not(.is-active){background:#F6CFF4}'
		. '.nqa-colbtn:focus-visible{outline:3px solid #FFEE58;outline-offset:2px}'

		// --- Facets ---
		. '.nqa-facet{display:flex;flex-wrap:wrap;align-items:center;gap:.4rem}'
		. '.nqa-facet__legend{font-size:.66rem;font-weight:700;letter-spacing:.12em;text-transform:uppercase;'
			. 'color:#111;margin-inline-end:.2rem}'
		. '.nqa-chip{appearance:none;cursor:pointer;font:inherit;font-size:.75rem;line-height:1.2;'
			. 'padding:.28rem .6rem;border:1px solid #503AA8;border-radius:999px;background:#F6CFF4;'
			. 'color:#503AA8;transition:background .12s,color .12s}'
		. '.nqa-chip:hover{background:#503AA8;color:#fff}'
		. '.nqa-chip[aria-pressed="true"]{background:#503AA8;color:#fff;font-weight:700}'
		. '.nqa-chip:focus-visible{outline:3px solid #FFEE58;outline-offset:2px}'
		. '.nqa-ctrls__clear{appearance:none;cursor:pointer;font:inherit;font-size:.7rem;font-weight:700;'
			. 'text-transform:uppercase;letter-spacing:.08em;background:none;border:0;color:#503AA8;'
			. 'text-decoration:underline;text-underline-offset:2px}'
		. '.nqa-ctrls__clear:focus-visible{outline:3px solid #FFEE58;outline-offset:2px}'
		. '.nqa-ctrls__empty{font-size:.85rem;color:#555;font-style:italic}';

	wp_add_inline_style( 'nqa-archive-controls', $css );
} );

add_action( 'wp_footer', function () {
	if ( ! is_archive() ) {
		return;
	}
	?>
<script>
(function(){
	var tpl=document.querySelector('.wp-block-post-template');
	if(!tpl){return;}
	var items=[].slice.call(tpl.querySelectorAll('.wp-block-post'));
	if(!items.length){return;}
	var el=document.documentElement;

	function pick(item,prefix){var out=[];[].forEach.call(item.classList,function(c){if(c.indexOf(prefix)===0){out.push(c.slice(prefix.length));}});return out;}
	function pretty(s){return s.replace(/-/g,' ').replace(/\b\w/g,function(m){return m.toUpperCase();});}

	var cats={},tags={};
	items.forEach(function(it){
		it._cats=pick(it,'category-');
		it._tags=pick(it,'tag-');
		it._text=(it.textContent||'').toLowerCase();
		it._cats.forEach(function(s){cats[s]=1;});
		it._tags.forEach(function(s){tags[s]=1;});
	});

	var selCat={},selTag={},q='';

	// --- Build control bar ---
	var bar=document.createElement('div');
	bar.className='nqa-ctrls';

	var top=document.createElement('div');
	top.className='nqa-ctrls__row nqa-ctrls__row--top';
	var search=document.createElement('div');
	search.className='nqa-ctrls__search';
	search.innerHTML='<input type="search" placeholder="Search these records…" aria-label="Search records on this page">';
	var cols=document.createElement('div');
	cols.className='nqa-ctrls__cols';
	cols.innerHTML='<span class="nqa-ctrls__label">Columns</span>'+
		'<div class="nqa-ctrls__colbtns" role="group" aria-label="Grid columns">'+
		'<button type="button" class="nqa-colbtn" data-cols="1" aria-pressed="false">1</button>'+
		'<button type="button" class="nqa-colbtn" data-cols="2" aria-pressed="false">2</button>'+
		'<button type="button" class="nqa-colbtn" data-cols="3" aria-pressed="false">3</button>'+
		'</div>';
	top.appendChild(search);
	top.appendChild(cols);
	bar.appendChild(top);

	function facetRow(label,map,sel,kind){
		var keys=Object.keys(map);
		if(keys.length<2){return null;} // only show a facet that can actually partition
		keys.sort();
		var row=document.createElement('div');
		row.className='nqa-ctrls__row nqa-facet';
		var lg=document.createElement('span');
		lg.className='nqa-facet__legend';lg.textContent=label;
		row.appendChild(lg);
		keys.forEach(function(k){
			var b=document.createElement('button');
			b.type='button';b.className='nqa-chip';b.setAttribute('aria-pressed','false');
			b.dataset.kind=kind;b.dataset.val=k;b.textContent=pretty(k);
			row.appendChild(b);
		});
		return row;
	}
	var catRow=facetRow('Type',cats,selCat,'cat');
	var tagRow=facetRow('Tags',tags,selTag,'tag');
	if(catRow){bar.appendChild(catRow);}
	if(tagRow){bar.appendChild(tagRow);}

	var foot=document.createElement('div');
	foot.className='nqa-ctrls__row';
	foot.innerHTML='<span class="nqa-ctrls__count" aria-live="polite"></span>'+
		'<button type="button" class="nqa-ctrls__clear">Clear filters</button>';
	bar.appendChild(foot);
	var countEl=foot.querySelector('.nqa-ctrls__count');

	// insert before the query block (fallback: before the template)
	var anchor=document.querySelector('.wp-block-query')||tpl;
	anchor.parentNode.insertBefore(bar,anchor);

	// --- Behaviour ---
	function apply(){
		var sc=Object.keys(selCat).filter(function(k){return selCat[k];});
		var st=Object.keys(selTag).filter(function(k){return selTag[k];});
		var shown=0;
		items.forEach(function(it){
			var ok=true;
			if(q && it._text.indexOf(q)===-1){ok=false;}
			if(ok && sc.length){ok=sc.some(function(c){return it._cats.indexOf(c)>-1;});}
			if(ok && st.length){ok=st.some(function(t){return it._tags.indexOf(t)>-1;});}
			it.classList.toggle('nqa-hidden',!ok);
			if(ok){shown++;}
		});
		countEl.textContent=shown+(shown===1?' record':' records');
		var empty=bar.parentNode.querySelector('.nqa-ctrls__empty');
		if(shown===0 && !empty){empty=document.createElement('p');empty.className='nqa-ctrls__empty';empty.textContent='No records match these filters.';anchor.parentNode.insertBefore(empty,anchor.nextSibling);}
		else if(shown>0 && empty){empty.remove();}
	}

	search.querySelector('input').addEventListener('input',function(e){q=e.target.value.trim().toLowerCase();apply();});

	bar.addEventListener('click',function(e){
		var chip=e.target.closest('.nqa-chip');
		if(chip){
			var store=chip.dataset.kind==='cat'?selCat:selTag, v=chip.dataset.val;
			store[v]=!store[v];
			chip.setAttribute('aria-pressed',store[v]?'true':'false');
			apply();return;
		}
		var cb=e.target.closest('.nqa-colbtn');
		if(cb){setCols(cb.dataset.cols);return;}
		if(e.target.closest('.nqa-ctrls__clear')){
			selCat={};selTag={};q='';
			search.querySelector('input').value='';
			[].forEach.call(bar.querySelectorAll('.nqa-chip'),function(c){c.setAttribute('aria-pressed','false');});
			apply();
		}
	});

	// --- Columns ---
	function curCols(){var m=(el.className.match(/nqa-acols-(\d)/)||[])[1];return m||'2';}
	function syncCols(){var n=curCols();[].forEach.call(cols.querySelectorAll('.nqa-colbtn'),function(b){var on=b.dataset.cols===n;b.classList.toggle('is-active',on);b.setAttribute('aria-pressed',on?'true':'false');});}
	function setCols(n){el.className=el.className.replace(/\s*nqa-acols-\d/g,'');el.classList.add('nqa-acols-'+n);try{localStorage.setItem('nqaArchiveCols',n);}catch(e){}syncCols();}
	if(!/nqa-acols-\d/.test(el.className)){el.classList.add('nqa-acols-2');}
	syncCols();
	apply();
})();
</script>
	<?php
} );
