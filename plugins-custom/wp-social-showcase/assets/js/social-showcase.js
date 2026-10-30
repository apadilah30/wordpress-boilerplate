/**
 * Social Media Showcase - JavaScript
 *
 * Tab switching is pure client-side (every platform's gallery is already
 * rendered in the page) — no AJAX needed. Clicking a tab:
 *   1. Shows that platform's gallery panel, hides the others.
 *   2. Updates the "Open Account" button's href to that platform's URL.
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
            });
        });
    }
})();
