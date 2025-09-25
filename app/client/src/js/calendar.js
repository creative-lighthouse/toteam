import Calendar from '@toast-ui/calendar';

// Create a container for the calendar
const calendarContainer = document.querySelector('#calendar');
if(calendarContainer != null) {
    let calendarData = JSON.parse(calendarContainer.getAttribute('data-calendardata'));

    // Initialize the calendar
    const calendar = new Calendar('#calendar', {
        defaultView: 'month',
        usageStatistics: false,
        useDetailPopup: true,
        template: {
            time(event) {
                return `<span style="color: black;">${title}</span>`;
            },
            allday(event) {
                return `<span style="color: black;">${title}</span>`;
            },
            popupDetailDate({start, end}) {
                const startDate = formatDate(start);
                const endDate = formatDate(end);
                return `${startDate} - ${endDate}`;
            },
            popupDetailBody({body}) {
                return body[0];
            }
        },
        month: {
            dayNames: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
            startDayOfWeek: 1,
            narrowWeekend: false
        },
        week: {
            dayNames: ['So', 'Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa'],
            startDayOfWeek: 1,
            narrowWeekend: false
        },
    });

    calendar.createEvents(calendarData);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    const day = `${date.getDate()}`.padStart(2, '0');
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const year = date.getFullYear();
    const time = formatTime(date);
    return `${day}.${month}.${year} ${time}`;
}

function formatTime(time) {
    const hours = `${time.getHours()}`.padStart(2, '0');
    const minutes = `${time.getMinutes()}`.padStart(2, '0');

    return `${hours}:${minutes}`;
}
