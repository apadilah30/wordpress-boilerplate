/**
 * Navigation JavaScript
 *
 * Handles mobile menu toggle and dropdown interactions.
 *
 * @package Boilerplate_Starter
 */

(function () {
    'use strict';

    const menuToggle = document.querySelector('.menu-toggle');
    const mainNavigation = document.querySelector('.main-navigation');
    const menuIcon = document.querySelector('.menu-icon');
    const closeIcon = document.querySelector('.close-icon');
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

    // Mobile menu toggle.
    if (menuToggle && mainNavigation) {
        menuToggle.addEventListener('click', function () {
            const isOpen = mainNavigation.classList.toggle('is-open');
            menuToggle.setAttribute('aria-expanded', isOpen);
            
            if (menuIcon && closeIcon) {
                menuIcon.style.display = isOpen ? 'none' : 'inline';
                closeIcon.style.display = isOpen ? 'inline' : 'none';
            }
        });

        // Close menu on escape key.
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && mainNavigation.classList.contains('is-open')) {
                mainNavigation.classList.remove('is-open');
                menuToggle.setAttribute('aria-expanded', 'false');
                if (menuIcon && closeIcon) {
                    menuIcon.style.display = 'inline';
                    closeIcon.style.display = 'none';
                }
            }
        });
    }

    // Dropdown toggle for mobile.
    dropdownToggles.forEach(function (toggle) {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
            const parent = toggle.closest('.dropdown');
            
            if (parent) {
                const isOpen = parent.classList.toggle('is-open');
                toggle.setAttribute('aria-expanded', isOpen);
            }
        });
    });
})();
