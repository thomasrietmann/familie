window.FamilyManager = window.FamilyManager || {};

document.addEventListener('DOMContentLoaded', () => {
    const button = document.querySelector('[data-mobile-menu-button]');
    const menu = document.querySelector('[data-mobile-menu]');
    const openIcon = document.querySelector('[data-mobile-menu-open-icon]');
    const closeIcon = document.querySelector('[data-mobile-menu-close-icon]');

    if (button && menu) {
        button.addEventListener('click', () => {
            const isOpen = button.getAttribute('aria-expanded') === 'true';

            button.setAttribute('aria-expanded', String(! isOpen));
            menu.classList.toggle('hidden', isOpen);
            menu.classList.toggle('flex', ! isOpen);
            openIcon?.classList.toggle('hidden', ! isOpen);
            closeIcon?.classList.toggle('hidden', isOpen);
        });
    }

    document.querySelectorAll('[data-event-datetime]').forEach((wrapper) => {
        const startsAt = wrapper.querySelector('[data-starts-at]');
        const endsAt = wrapper.querySelector('[data-ends-at]');
        const startDate = wrapper.querySelector('[data-start-date]');
        const startTime = wrapper.querySelector('[data-start-time]');
        const endDate = wrapper.querySelector('[data-end-date]');
        const endTime = wrapper.querySelector('[data-end-time]');
        const allDay = wrapper.querySelector('[data-all-day]');

        const syncDateTimeFields = () => {
            const isAllDay = allDay?.checked;

            if (startTime) {
                startTime.disabled = isAllDay;
            }

            if (endTime) {
                endTime.disabled = isAllDay;
            }

            startsAt.value = startDate.value
                ? `${startDate.value}T${isAllDay ? '00:00' : (startTime.value || '08:00')}`
                : '';

            endsAt.value = endDate.value
                ? `${endDate.value}T${isAllDay ? '23:59' : (endTime.value || startTime.value || '08:00')}`
                : '';
        };

        [startDate, startTime, endDate, endTime, allDay].forEach((field) => {
            field?.addEventListener('change', syncDateTimeFields);
        });

        wrapper.closest('form')?.addEventListener('submit', syncDateTimeFields);
        syncDateTimeFields();
    });
});
