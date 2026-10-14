/*
 * Bionic Uptime dashboard widget: keeps the status fresh while the dashboard
 * is open.
 *
 * A dashboard is often left open for hours, or brought back with Back. The
 * widget is drawn once by PHP, so without this it would go on saying "Online,
 * checked 30 secs ago" for as long as the tab stayed open. So:
 *
 * - every 30 seconds while the widget is in view, ask for it again (the
 *   plugin's own 60-second cache means Bionic Uptime is asked at most once a
 *   minute);
 * - once the answer on screen is over two minutes old, by the clock and not
 *   only when a request fails (a hung request, or a laptop asleep, fires no
 *   error), stop showing it and say why;
 * - coming back into view, or out of the Back cache, check at once;
 * - announce a status only when it changes. A refresh that finds the same
 *   status only redraws "Checked ... ago", so nothing is re-read aloud and
 *   keyboard focus is left alone.
 */
( function () {
	'use strict';

	var cfg = window.bionicUptimeWidget;
	var box = document.getElementById( 'bionic-uptime-widget' );
	if ( ! cfg || ! box || '1' !== box.getAttribute( 'data-refresh' ) ) {
		return;
	}

	var i18n = window.wp && window.wp.i18n;
	var POLL_MS = 30000;
	var TIMEOUT_MS = 20000;
	var STALE_MS = 120000;
	var TICK_MS = 5000;

	// When Bionic Uptime gave the answer on screen, by this browser's clock.
	var answeredAt = Date.now() - ageMs( box.getAttribute( 'data-age' ) );
	var said = box.getAttribute( 'data-said' ) || '';
	var stale = false;
	var mustReload = false;
	var inFlight = false;
	var lastPoll = Date.now();
	var lastNow = Date.now();

	function ageMs( seconds ) {
		var n = parseInt( seconds, 10 );
		return isFinite( n ) && n > 0 ? n * 1000 : 0;
	}

	function t( text ) {
		return i18n ? i18n.__( text, 'bionic-uptime' ) : text;
	}

	function speak( text ) {
		if ( text && window.wp && window.wp.a11y && window.wp.a11y.speak ) {
			window.wp.a11y.speak( text, 'polite' );
		}
	}

	function staleNote( age ) {
		if ( mustReload ) {
			return t( 'This page has been open too long to refresh itself. Reload it to see the current status.' );
		}
		var n = Math.max( 2, Math.floor( age / 60000 ) );
		var one = 'We have not been able to refresh this status for %d minute, so we are not showing one that may be out of date. Still trying.';
		var many = 'We have not been able to refresh this status for %d minutes, so we are not showing one that may be out of date. Still trying.';
		if ( i18n ) {
			/* translators: %d: a number of minutes, always 2 or more. */
			return i18n.sprintf( i18n._n( 'We have not been able to refresh this status for %d minute, so we are not showing one that may be out of date. Still trying.', 'We have not been able to refresh this status for %d minutes, so we are not showing one that may be out of date. Still trying.', n, 'bionic-uptime' ), n );
		}
		return ( 1 === n ? one : many ).replace( '%d', String( n ) );
	}

	// Replace the status with a grey "could not refresh" and the reason. Said
	// once; later ticks only keep the number of minutes true.
	function showStale( age ) {
		var note = staleNote( age );
		if ( stale ) {
			var meta = box.querySelector( '.bionic-uptime-meta' );
			if ( meta && meta.textContent !== note ) {
				meta.textContent = note;
			}
			return;
		}
		stale = true;
		said = '';
		var hadFocus = box.contains( document.activeElement );

		var status = document.createElement( 'p' );
		status.className = 'bionic-uptime-status bionic-uptime-look-neutral';
		var shape = document.createElement( 'span' );
		shape.className = 'bionic-uptime-shape';
		shape.setAttribute( 'aria-hidden', 'true' );
		var label = document.createElement( 'span' );
		label.className = 'bionic-uptime-label';
		label.textContent = t( 'Could not refresh' );
		status.appendChild( shape );
		status.appendChild( label );

		var meta2 = document.createElement( 'p' );
		meta2.className = 'bionic-uptime-meta';
		meta2.textContent = note;

		box.textContent = '';
		box.appendChild( status );
		box.appendChild( meta2 );
		// The link that had focus is gone; keep focus in the widget rather
		// than dropping it to the top of the page.
		if ( hadFocus ) {
			box.setAttribute( 'tabindex', '-1' );
			box.focus();
		}
		speak( label.textContent + '. ' + note );
	}

	// A clock set backwards would otherwise hold off the next poll and make the
	// answer on screen look younger than it is. Move both back with it, so the
	// answer keeps its real age.
	function followClock() {
		var now = Date.now();
		if ( now < lastNow ) {
			answeredAt -= lastNow - now;
			lastPoll -= lastNow - now;
		}
		lastNow = now;
	}

	function check() {
		followClock();
		var age = Date.now() - answeredAt;
		if ( age > STALE_MS ) {
			showStale( age );
		}
	}

	// The same status again: redraw only the "Checked ... ago" words, which are
	// the first text in the meta line. Returns false when anything else
	// differs, and then the whole widget is replaced.
	function redrawTimeOnly( html ) {
		var tpl = document.createElement( 'template' );
		if ( ! ( 'content' in tpl ) ) {
			return false;
		}
		tpl.innerHTML = html;
		var nm = tpl.content.querySelector( '.bionic-uptime-meta' );
		var om = box.querySelector( '.bionic-uptime-meta' );
		if ( ! nm || ! om || ! nm.firstChild || ! om.firstChild || 3 !== nm.firstChild.nodeType || 3 !== om.firstChild.nodeType ) {
			return tpl.innerHTML === box.innerHTML;
		}
		var newText = nm.firstChild.data;
		var oldText = om.firstChild.data;
		nm.firstChild.data = '';
		om.firstChild.data = '';
		var same = tpl.innerHTML === box.innerHTML;
		om.firstChild.data = same ? newText : oldText;
		return same;
	}

	function refresh() {
		if ( inFlight || ! window.fetch ) {
			return;
		}
		inFlight = true;
		lastPoll = Date.now();

		var ctrl = window.AbortController ? new window.AbortController() : null;
		var timer = setTimeout( function () {
			if ( ctrl ) {
				ctrl.abort();
			}
		}, TIMEOUT_MS );
		var body = new window.URLSearchParams();
		body.set( 'action', 'bionic_uptime_widget' );
		body.set( 'nonce', cfg.nonce );

		window.fetch( cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			cache: 'no-store',
			body: body,
			signal: ctrl ? ctrl.signal : undefined
		} ).then( function ( res ) {
			// Signed out, or the page is older than its nonce: no refresh can
			// work until the page is loaded again, so say that instead.
			if ( 400 === res.status || 403 === res.status ) {
				mustReload = true;
				return null;
			}
			return res.ok ? res.json() : null;
		} ).then( function ( d ) {
			if ( ! d || ! d.success || ! d.data || 'string' !== typeof d.data.html ) {
				return;
			}
			var at = Date.now() - ageMs( d.data.age );
			// An answer already past the bound is no better than none.
			if ( Date.now() - at > STALE_MS ) {
				return;
			}
			var now = 'string' === typeof d.data.said ? d.data.said : '';
			var changed = stale || now !== said;
			answeredAt = at;
			mustReload = false;
			if ( changed || ! redrawTimeOnly( d.data.html ) ) {
				box.innerHTML = d.data.html;
			}
			if ( changed ) {
				speak( now );
			}
			stale = false;
			said = now;
		} ).catch( function () {} ).then( function () {
			clearTimeout( timer );
			inFlight = false;
			check();
		} );
	}

	// Not polled while nobody can see it: a background tab, or the widget
	// collapsed or turned off in Screen Options. The age check still runs, and
	// the first tick it is back in view asks again.
	function inView() {
		return ! document.hidden && null !== box.offsetParent;
	}

	setInterval( function () {
		check();
		if ( inView() && Date.now() - lastPoll >= POLL_MS ) {
			refresh();
		}
	}, TICK_MS );

	document.addEventListener( 'visibilitychange', function () {
		if ( ! document.hidden ) {
			check();
			refresh();
		}
	} );

	window.addEventListener( 'pageshow', function ( e ) {
		if ( e.persisted ) {
			check();
			refresh();
		}
	} );

	check();
}() );
