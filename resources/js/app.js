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
                const event = button.dataset;

                setText('title', event.calendarTitle);
                setText('date', event.calendarDate);
                setText('time', event.calendarTime);
                setText('person', event.calendarPerson);
                setText('category', event.calendarCategory);
                setText('status', event.calendarStatus);
                setText('location', event.calendarLocation, 'Kein Ort hinterlegt.');
                setText('description', event.calendarDescription, 'Keine Beschreibung.');
                setText('notes', event.calendarNotes, 'Keine Notizen.');

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


document.querySelectorAll('[data-print]').forEach(button => button.addEventListener('click', () => window.print()));
const registrationForm = document.querySelector('[data-registration-form]');
if (registrationForm?.dataset.recaptchaEnabled === 'true') {
    let submitting = false;
    registrationForm.addEventListener('submit', event => {
        event.preventDefault();
        if (submitting) return;
        const error = registrationForm.querySelector('[data-recaptcha-error]');
        if (!window.grecaptcha) { error.hidden = false; return; }
        submitting = true;
        window.grecaptcha.ready(() => {
            window.grecaptcha.execute(registrationForm.dataset.recaptchaSiteKey, {action: 'register'})
                .then(token => {
                    if (!token) throw new Error('Missing token');
                    registrationForm.querySelector('[name="recaptcha_token"]').value = token;
                    registrationForm.submit();
                }).catch(() => { submitting = false; error.hidden = false; });
        });
    });
}
