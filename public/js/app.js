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

    const calendarModal = document.querySelector('[data-calendar-modal]');

    if (calendarModal) {
        const fields = {
            title: calendarModal.querySelector('[data-calendar-modal-title]'),
            date: calendarModal.querySelector('[data-calendar-modal-date]'),
            time: calendarModal.querySelector('[data-calendar-modal-time]'),
            person: calendarModal.querySelector('[data-calendar-modal-person]'),
            category: calendarModal.querySelector('[data-calendar-modal-category]'),
            status: calendarModal.querySelector('[data-calendar-modal-status]'),
            location: calendarModal.querySelector('[data-calendar-modal-location]'),
            description: calendarModal.querySelector('[data-calendar-modal-description]'),
            notes: calendarModal.querySelector('[data-calendar-modal-notes]'),
        };

        const setText = (name, value, fallback = '-') => {
            if (fields[name]) {
                fields[name].textContent = value || fallback;
            }
        };

        const closeModal = () => {
            calendarModal.classList.add('hidden');
            calendarModal.classList.remove('flex');
        };

        document.querySelectorAll('[data-calendar-event]').forEach((button) => {
            button.addEventListener('click', () => {
                const event = JSON.parse(button.dataset.calendarEvent || '{}');

                setText('title', event.title);
                setText('date', event.date);
                setText('time', event.time);
                setText('person', event.person);
                setText('category', event.category);
                setText('status', event.status);
                setText('location', event.location, 'Kein Ort hinterlegt.');
                setText('description', event.description, 'Keine Beschreibung.');
                setText('notes', event.notes, 'Keine Notizen.');

                calendarModal.classList.remove('hidden');
                calendarModal.classList.add('flex');
            });
        });

        calendarModal.querySelector('[data-calendar-modal-close]')?.addEventListener('click', closeModal);
        calendarModal.addEventListener('click', (event) => {
            if (event.target === calendarModal) {
                closeModal();
            }
        });
        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeModal();
            }
        });
    }
});
