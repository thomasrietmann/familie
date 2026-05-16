window.FamilyManager = window.FamilyManager || {};

document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('[data-mobile-menu-button]');
    const menu = document.querySelector('[data-mobile-menu]');
    const openIcon = document.querySelector('[data-mobile-menu-open-icon]');
    const closeIcon = document.querySelector('[data-mobile-menu-close-icon]');

    if (! button || ! menu) {
        return;
    }

    button.addEventListener('click', () => {
        const isOpen = button.getAttribute('aria-expanded') === 'true';

        button.setAttribute('aria-expanded', String(! isOpen));
        menu.classList.toggle('hidden', isOpen);
        menu.classList.toggle('flex', ! isOpen);
        openIcon?.classList.toggle('hidden', ! isOpen);
        closeIcon?.classList.toggle('hidden', isOpen);
    });
});
