/**
 * Social Media Showcase - JavaScript
 *
 * Tab switching is pure client-side (every platform's panel is already
 * rendered in the page) — no AJAX needed. Clicking a tab:
 *   1. Shows that platform's panel, hides the others.
 *   2. Updates the "Open Account" button's href to that platform's URL.
 *   3. Fires a window "resize" event so feed plugins re-calculate layout.
 */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.wpss-wrapper').forEach(initShowcase);
    });

    function initShowcase(wrapper) {
        const tabs = wrapper.querySelectorAll('.wpss-tab');
        const panels = wrapper.querySelectorAll('.wpss-panel');
        const openBtn = wrapper.querySelector('.wpss-open-btn');

        tabs.forEach(function (tab) {
            tab.addEventListener('click', function () {
                const index = tab.dataset.panelIndex;

                tabs.forEach(function (t) {
                    const isActive = t === tab;
                    t.classList.toggle('active', isActive);
                    t.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });

                panels.forEach(function (panel) {
                    panel.classList.toggle('active', panel.dataset.panelIndex === index);
                });

                if (openBtn && tab.dataset.accountUrl) {
                    openBtn.setAttribute('href', tab.dataset.accountUrl);
                }

                // Panel non-aktif itu display:none, jadi lebarnya 0 saat
                // plugin feed (masonry/carousel/grid) selesai load dan
                // menghitung layout -> hasilnya rusak. Event resize memaksa
                // plugin-plugin itu menghitung ulang sekarang, saat panel
                // sudah terlihat. Aman untuk mode galeri manual (tidak ada
                // yang mendengarkan selain script lain di halaman).
                window.dispatchEvent(new Event('resize'));
            });
        });
    }
})();
