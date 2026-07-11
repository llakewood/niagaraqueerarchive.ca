/**
 * NQA Search — handles filter population and live search on /search/.
 *
 * Depends on `nqaSearch` global injected by search.php:
 *   { endpoint, muniUrl, collUrl }
 *
 * Flow:
 *  1. Load municipality + collection filter chips from WP REST API
 *  2. Run initial empty search (shows all records, newest first)
 *  3. Re-run on every input / chip / sort change (debounced on text input)
 */

( function () {
	'use strict';

	// ── State ──────────────────────────────────────────────────────────────────

	const state = {
		q:      '',
		type:   '',
		decade: '',
		muni:   '',
		coll:   '',
		sort:   'relevance',
	};

	let debounceTimer = null;

	// ── DOM refs (populated in init) ───────────────────────────────────────────

	let inputEl, formEl, countEl, listEl, sortEl;

	// ── Utilities ──────────────────────────────────────────────────────────────

	function esc( str ) {
		return String( str )
			.replace( /&/g, '&amp;' )
			.replace( /</g, '&lt;' )
			.replace( />/g, '&gt;' )
			.replace( /"/g, '&quot;' );
	}

	function buildUrl( base, params ) {
		const u = new URL( base );
		Object.entries( params ).forEach( ( [ k, v ] ) => {
			if ( v !== '' && v !== null && v !== undefined ) {
				u.searchParams.set( k, v );
			}
		} );
		return u.toString();
	}

	// ── Filter chip builder ────────────────────────────────────────────────────

	function buildChips( terms, containerId, filter ) {
		const container = document.getElementById( containerId );
		const group     = container ? container.closest( '.search-filter-group' ) : null;
		if ( ! container || ! terms.length ) {
			if ( group ) group.hidden = true;
			return;
		}
		terms.forEach( term => {
			const btn = document.createElement( 'button' );
			btn.type             = 'button';
			btn.className        = 'search-chip';
			btn.dataset.filter   = filter;
			btn.dataset.value    = term.slug;
			btn.textContent      = term.name + ( term.count ? ' (' + term.count + ')' : '' );
			btn.setAttribute( 'aria-pressed', 'false' );
			container.appendChild( btn );
		} );
		if ( group ) group.hidden = false;
	}

	// ── Chip click handler (delegated on .search-sidebar) ─────────────────────

	function onChipClick( e ) {
		const btn = e.target.closest( '.search-chip' );
		if ( ! btn ) return;

		const filter   = btn.dataset.filter;
		const value    = btn.dataset.value;
		const siblings = document.querySelectorAll( `.search-chip[data-filter="${ filter }"]` );

		siblings.forEach( s => {
			s.classList.remove( 'is-active' );
			s.setAttribute( 'aria-pressed', 'false' );
		} );

		// "All records" chip (value="") acts as a reset — always activate it on click.
		// Other chips toggle: clicking active chip returns to "all" if one exists.
		if ( value === '' ) {
			btn.classList.add( 'is-active' );
			btn.setAttribute( 'aria-pressed', 'true' );
			state[ filter ] = '';
		} else {
			const wasActive = state[ filter ] === value;
			if ( ! wasActive ) {
				btn.classList.add( 'is-active' );
				btn.setAttribute( 'aria-pressed', 'true' );
				state[ filter ] = value;
			} else {
				// Return to unfiltered — re-activate the "all" chip if present
				state[ filter ] = '';
				const allChip = document.querySelector( `.search-chip[data-filter="${ filter }"][data-value=""]` );
				if ( allChip ) {
					allChip.classList.add( 'is-active' );
					allChip.setAttribute( 'aria-pressed', 'true' );
				}
			}
		}

		runSearch();
	}

	// ── Search execution ───────────────────────────────────────────────────────

	function showLoading() {
		countEl.textContent = 'Searching…';
		listEl.innerHTML    = '<p class="search-loading">Loading&hellip;</p>';
	}

	function renderResults( data ) {
		const { total, posts } = data;

		countEl.textContent = total === 0
			? 'No results found'
			: total + ' record' + ( total !== 1 ? 's' : '' );

		if ( ! posts.length ) {
			listEl.innerHTML = '<p class="search-empty">No records match your search. Try different keywords or filters.</p>';
			return;
		}

		listEl.innerHTML = '';
		posts.forEach( post => {
			const a    = document.createElement( 'a' );
			a.href     = post.permalink;
			a.className = 'cat-card';

			const metaParts = [];
			if ( post.muni )       metaParts.push( post.muni );
			if ( post.collection ) metaParts.push( post.collection );
			( post.decades || [] ).forEach( d => metaParts.push( d ) );

			const metaHtml = metaParts.length
				? '<span class="cat-card__meta">' + esc( metaParts.join( ', ' ) ) + '</span>'
				: '';

			a.innerHTML =
				'<span class="cat-card__type">'    + esc( post.type_label ) + '</span>' +
				'<span class="cat-card__title">'   + esc( post.title )      + '</span>' +
				( post.excerpt ? '<span class="cat-card__excerpt">' + esc( post.excerpt ) + '</span>' : '' ) +
				metaHtml;

			listEl.appendChild( a );
		} );
	}

	async function runSearch() {
		showLoading();

		const params = {
			q:        state.q,
			type:     state.type,
			muni:     state.muni,
			coll:     state.coll,
			decade:   state.decade,
			sort:     state.sort,
			per_page: 48,
		};

		try {
			const res  = await fetch( buildUrl( nqaSearch.endpoint, params ) );
			const data = await res.json();
			renderResults( data );
		} catch ( err ) {
			countEl.textContent = '';
			listEl.innerHTML    = '<p class="search-empty">Could not load results. Please try again.</p>';
			console.error( '[nqa-search]', err );
		}
	}

	// ── Init ───────────────────────────────────────────────────────────────────

	async function init() {
		formEl  = document.getElementById( 'nqa-search-form' );
		inputEl = document.getElementById( 'nqa-search-input' );
		countEl = document.getElementById( 'nqa-search-count' );
		listEl  = document.getElementById( 'nqa-search-results' );
		sortEl  = document.getElementById( 'nqa-search-sort' );

		if ( ! formEl || ! inputEl || ! countEl || ! listEl ) return;

		// Pre-fill from a ?q= URL param (e.g. the homepage hero search bar)
		const initialQ = ( new URLSearchParams( window.location.search ).get( 'q' ) || '' ).trim();
		if ( initialQ ) {
			state.q       = initialQ;
			inputEl.value = initialQ;
		}

		// Sidebar chip delegation
		const sidebar = document.getElementById( 'nqa-search-sidebar' );
		if ( sidebar ) sidebar.addEventListener( 'click', onChipClick );

		// Form submit
		formEl.addEventListener( 'submit', e => {
			e.preventDefault();
			state.q = inputEl.value.trim();
			runSearch();
		} );

		// Debounced live input
		inputEl.addEventListener( 'input', () => {
			clearTimeout( debounceTimer );
			debounceTimer = setTimeout( () => {
				state.q = inputEl.value.trim();
				runSearch();
			}, 320 );
		} );

		// Sort select
		if ( sortEl ) {
			sortEl.addEventListener( 'change', () => {
				state.sort = sortEl.value;
				runSearch();
			} );
		}

		// Load dynamic filter chips in parallel
		try {
			const [ muniRes, collRes ] = await Promise.all( [
				fetch( nqaSearch.muniUrl + '?per_page=100&orderby=count&order=desc&hide_empty=1' ),
				fetch( nqaSearch.collUrl + '?per_page=100&hide_empty=1' ),
			] );

			const munis = await muniRes.json();
			const colls = await collRes.json();

			buildChips( munis, 'nqa-muni-chips', 'muni' );
			buildChips( colls, 'nqa-coll-chips', 'coll' );
		} catch ( err ) {
			console.error( '[nqa-search] filter load failed', err );
		}

		// Initial results — show all records newest-first
		runSearch();
	}

	document.addEventListener( 'DOMContentLoaded', init );
} )();
