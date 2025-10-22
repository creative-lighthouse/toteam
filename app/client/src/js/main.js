import Swiper, {Autoplay, EffectCoverflow, EffectFade, Navigation, Pagination} from 'swiper';
import GLightbox from "glightbox";

document.addEventListener("DOMContentLoaded", function () {
    const mainnavButton = document.querySelector('[data-action="toggle-secnav"]');

    if (mainnavButton) {
        mainnavButton.addEventListener('click', function () {
            document.body.classList.toggle('secnav--open');
        });
    }

    // INIT LIGHTBOX
    const lightbox = GLightbox({
        selector: '[data-gallery="gallery"]',
        touchNavigation: true,
        loop: true,
    });

    // INIT SWIPER
    const sliders = document.querySelectorAll('.swiper');
    sliders.forEach(function (slider) {
        const autoSwiper = slider.classList.contains('swiper--auto');
        const swiper = new Swiper(slider, {
            // configure Swiper to use modules
            modules: [Pagination, Navigation, Autoplay, EffectFade],
            effect: 'slide',
            fadeEffect: {
                crossFade: true
            },
            direction: 'vertical',
            loop: true,

            autoplay: autoSwiper ? {
                delay: 5000,
            } : false,

            // Navigation arrows
            navigation: {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },

            pagination: {
                el: '.swiper-pagination',
                type: 'bullets',
                clickable: true,
            },
        });
    });

    document.querySelectorAll('dialog[data-autoopen="true"]').forEach(dialog => {
        dialog.showModal();
        const statusMessage = document.createElement('div');
        statusMessage.className = `status-message status-message--success`;
        statusMessage.textContent = "Gespeichert";
        dialog.appendChild(statusMessage);
        setTimeout(() => {
            statusMessage.classList.add('status-message--hide');
            //Delete eventID from URL
            const url = new URL(window.location);
            url.searchParams.delete('eventID');
            window.history.replaceState({}, document.title, url.toString());
        }, 3000);
        setTimeout(() => {
            statusMessage.remove();
        }, 6000);
    });

    //Close dialog on outside click
    document.querySelectorAll('dialog').forEach(dialog => {
        dialog.addEventListener('click', (event) => {
            const rect = dialog.getBoundingClientRect();
            const isInDialog = (rect.top <= event.clientY && event.clientY <= rect.top + rect.height
                && rect.left <= event.clientX && event.clientX <= rect.left + rect.width);
            if (!isInDialog) {
                dialog.close();
            }
        });
    });

    const copyButtons = document.querySelectorAll('.copy-btn');

    copyButtons.forEach(button => {
        button.addEventListener('click', () => {
            const targetText = button.getAttribute('data-copy-target');

            if (!targetText) {
                return;
            }

            navigator.clipboard.writeText(targetText)
            .then(() => {
                const feedback = button.parentElement.querySelector('.copy-feedback');
                if (feedback) {
                    feedback.style.display = 'inline';
                    setTimeout(() => feedback.style.display = 'none', 2000);
                }
            })
            .catch(err => {
                console.error("Fehler beim Kopieren:", err);
            });
        });
    });

    //== CALENDAR PARTICIPATION HANDLING ==//

    // AJAX-Handling für Teilnahme-Buttons
    document.querySelectorAll('.js-participation-form').forEach(form => {
        form.querySelectorAll('button[name="response"]').forEach(btn => {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                const formData = new FormData(form);
                formData.set('response', btn.value); // Wert des gedrückten Buttons setzen

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.ok ? response.text() : Promise.reject(response))
                .then(data => {
                    // UI aktualisieren
                    form.querySelectorAll('button[name="response"]').forEach(b => {
                        b.classList.remove('selected', 'unselected');
                        if (b.value === btn.value) {
                            b.classList.add('selected');
                        } else {
                            b.classList.add('unselected');
                        }
                    });
                    // Erfolgsmeldung anzeigen wie beim Dialog-Open
                    const dialog = form.closest('dialog');
                    if (dialog) {
                        const statusMessage = document.createElement('div');
                        statusMessage.className = `status-message status-message--success`;
                        statusMessage.textContent = "Gespeichert";
                        dialog.appendChild(statusMessage);
                        setTimeout(() => {
                            statusMessage.classList.add('status-message--hide');
                        }, 3000);
                        setTimeout(() => {
                            statusMessage.remove();
                        }, 6000);
                        // Zeitformular ein-/ausblenden je nach Status
                        const container = dialog.querySelector(`#participation-time-form-container-${form.getAttribute('action').split('/').pop()}`);
                        if (container) {
                            if (btn.value === 'Decline') {
                                container.style.display = 'none';
                            } else {
                                container.style.display = 'block';
                            }
                        }
                        // Teilnehmerliste aktualisieren: eigenen Eintrag verschieben
                        const myName = dialog.querySelector('.participant-name[data-me]');
                        if (myName) {
                            const participantDiv = myName.closest('.participant');
                            if (participantDiv) {
                                // Neue Klasse setzen
                                participantDiv.classList.remove('participant--status-Accept', 'participant--status-Maybe', 'participant--status-Decline');
                                participantDiv.classList.add('participant--status-' + btn.value);
                                // Neue Gruppe suchen oder anlegen
                                const participantsList = dialog.querySelector('.participants-list');
                                if (participantsList) {
                                    // Zielgruppe suchen
                                    let group = Array.from(participantsList.querySelectorAll('.participant-group_title')).find(h5 => h5.textContent.trim() === (btn.value === 'Accept' ? 'Zugesagt' : btn.value === 'Maybe' ? 'Vielleicht' : 'Abgesagt'));
                                    if (!group) {
                                        // Gruppe anlegen
                                        group = document.createElement('h5');
                                        group.className = 'participant-group_title';
                                        group.textContent = (btn.value === 'Accept' ? 'Zugesagt' : btn.value === 'Maybe' ? 'Vielleicht' : 'Abgesagt');
                                        participantsList.appendChild(group);
                                        participantsList.appendChild(participantDiv);
                                    } else {
                                        // Nach der Gruppe einfügen
                                        let next = group.nextElementSibling;
                                        // Finde die nächste Gruppe oder das Ende
                                        while (next && next.classList.contains('participant')) {
                                            next = next.nextElementSibling;
                                        }
                                        participantsList.insertBefore(participantDiv, next);
                                    }
                                }
                            }
                        }
                    }
                })
                .catch(error => {
                    alert('Fehler beim Speichern!');
                });
            });
        });
    });

    // AJAX-Handling für Zeitfelder (Uhrzeit)
    document.querySelectorAll('form[action*="changeParticipationTime"]').forEach(form => {
        const timeInputs = form.querySelectorAll('input[type="time"]');
        timeInputs.forEach(input => {
            input.addEventListener('blur', function () {
                // Nur absenden, wenn beide Felder ausgefüllt sind
                const timestart = form.querySelector('input[name="timestart"]').value;
                const timeend = form.querySelector('input[name="timeend"]').value;
                if (!timestart || !timeend) return;

                const formData = new FormData(form);
                formData.set('timestart', timestart);
                formData.set('timeend', timeend);
                formData.set('response', 'UpdateTime');

                fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.ok ? response.text() : Promise.reject(response))
                .then(data => {
                    // Erfolgsmeldung anzeigen
                    const dialog = form.closest('dialog');
                    if (dialog) {
                        const statusMessage = document.createElement('div');
                        statusMessage.className = `status-message status-message--success`;
                        statusMessage.textContent = "Zeiten gespeichert";
                        dialog.appendChild(statusMessage);
                        setTimeout(() => {
                            statusMessage.classList.add('status-message--hide');
                        }, 3000);
                        setTimeout(() => {
                            statusMessage.remove();
                        }, 6000);
                    }
                    // Teilnehmerliste im Dialog aktualisieren (nur eigene Zeit)
                    if (dialog) {
                        // Finde alle eigenen Teilnehmer-Elemente
                        const myName = dialog.querySelector('.participant-name[data-me]');
                        if (myName) {
                            // Finde das Zeit-Element daneben und aktualisiere es
                            const statusSpan = myName.parentElement.querySelector('.participant-status');
                            if (statusSpan) {
                                statusSpan.textContent = `(${timestart} - ${timeend})`;
                            }
                        }
                    }
                })
                .catch(error => {
                    alert('Fehler beim Speichern der Zeit!');
                });
            });
        });
    });
});
