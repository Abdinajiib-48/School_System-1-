/* ============================================================
   School System - shared responsive behaviour
   Loaded by every dashboard page.

   1. Wraps every table so it scrolls sideways instead of
      stretching the page (fixes all pages at once).
   2. Adds a dark overlay behind the sidebar on small screens.
   3. Takes over the hamburger button, replacing the old
      per-page handler so the two cannot fight each other.
   ============================================================ */
(function () {
    'use strict';

    function ready(fn) {
        if (document.readyState !== 'loading') fn();
        else document.addEventListener('DOMContentLoaded', fn);
    }

    ready(function () {

        /* ---------- 1. Make every table scrollable ---------- */
        var tables = document.querySelectorAll('table');
        for (var i = 0; i < tables.length; i++) {
            var t = tables[i];
            if (t.closest('.table-scroll') || t.closest('.tt-wrap')) continue;
            // printable documents lay themselves out already
            if (t.closest('.receipt') || t.closest('.slip')) continue;

            var wrap = document.createElement('div');
            wrap.className = 'table-scroll';
            t.parentNode.insertBefore(wrap, t);
            wrap.appendChild(t);
        }

        /* show the fade only where a table really does overflow */
        function markScrollable() {
            var wraps = document.querySelectorAll('.table-scroll, .tt-wrap');
            for (var i = 0; i < wraps.length; i++) {
                var w = wraps[i];
                if (w.scrollWidth > w.clientWidth + 2) w.classList.add('is-scrollable');
                else w.classList.remove('is-scrollable');
            }
        }
        markScrollable();
        window.addEventListener('resize', markScrollable);

        /* ---------- 2. Sidebar drawer + overlay ---------- */
        var sidebar = document.getElementById('sidebar');
        if (!sidebar) return;

        var overlay = document.createElement('div');
        overlay.className = 'sidebar-overlay';
        document.body.appendChild(overlay);

        function syncOverlay() {
            if (sidebar.classList.contains('active') && window.innerWidth <= 768) {
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            } else {
                overlay.classList.remove('show');
                document.body.style.overflow = '';
            }
        }

        /* keep the overlay right no matter who opens or closes the
           drawer - including the older inline scripts on each page */
        if (window.MutationObserver) {
            new MutationObserver(syncOverlay).observe(sidebar, {
                attributes: true, attributeFilter: ['class']
            });
        }

        function closeSidebar() { sidebar.classList.remove('active'); syncOverlay(); }
        function openSidebar()  { sidebar.classList.add('active');    syncOverlay(); }

        /* Take over the hamburger. Cloning the node drops the
           handler the page attached earlier, so the button is not
           toggled twice on one tap. */
        var oldToggle = document.getElementById('menuToggle');
        if (oldToggle) {
            var toggle = oldToggle.cloneNode(true);
            oldToggle.parentNode.replaceChild(toggle, oldToggle);
            toggle.addEventListener('click', function (e) {
                e.stopPropagation();
                if (sidebar.classList.contains('active')) closeSidebar();
                else openSidebar();
            });
        }

        overlay.addEventListener('click', closeSidebar);

        /* tapping a menu item closes the drawer on a phone */
        var links = sidebar.querySelectorAll('a, .sidebar-menu span[onclick]');
        for (var j = 0; j < links.length; j++) {
            links[j].addEventListener('click', function () {
                if (window.innerWidth <= 768) closeSidebar();
            });
        }

        window.addEventListener('resize', function () {
            if (window.innerWidth > 768) closeSidebar();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') closeSidebar();
        });
    });
})();
