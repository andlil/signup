/**
 * Adds a counter of selected days and buttons that select or clear a whole
 * week to the vacation planning calendars. Without JavaScript the calendars
 * still work as plain checkboxes.
 */
export default function initVacationCalendars(root = document) {
    root.querySelectorAll('[data-vacation-calendar]').forEach((calendar) => {
        const counter = calendar.querySelector('[data-vacation-count]');
        const boxes = () => [...calendar.querySelectorAll('input[type=checkbox]:not(:disabled)')];
        const updateCount = () => {
            counter.textContent = boxes().filter((box) => box.checked).length;
        };

        calendar.addEventListener('change', updateCount);

        calendar.querySelectorAll('[data-vacation-toggle-week]').forEach((button) => {
            button.addEventListener('click', () => {
                const weekBoxes = [...button.closest('[data-vacation-week]').querySelectorAll('input[type=checkbox]:not(:disabled)')];
                const selectAll = !weekBoxes.every((box) => box.checked);

                weekBoxes.forEach((box) => {
                    box.checked = selectAll;
                });
                updateCount();
            });
        });
    });
}
