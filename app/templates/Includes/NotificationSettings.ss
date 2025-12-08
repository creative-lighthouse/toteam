<div class="notification-settings">
    <h2>Benachrichtigungs-Einstellungen</h2>
    <p>Wähle aus, welche Benachrichtigungen du erhalten möchtest:</p>

    <form id="notification-preferences-form" class="preferences-form">
        <div class="preference-item">
            <label class="preference-label">
                <input type="checkbox" name="events" id="notify-events" checked>
                <span class="preference-text">
                    <strong>Neue Termine</strong>
                    <small>Benachrichtigungen bei neuen Veranstaltungen</small>
                </span>
            </label>
        </div>

        <div class="preference-item">
            <label class="preference-label">
                <input type="checkbox" name="notices" id="notify-notices" checked>
                <span class="preference-text">
                    <strong>Nachrichten</strong>
                    <small>Benachrichtigungen bei neuen Nachrichten</small>
                </span>
            </label>
        </div>

        <div class="preference-item">
            <label class="preference-label">
                <input type="checkbox" name="meals" id="notify-meals" checked>
                <span class="preference-text">
                    <strong>Essensvorschläge</strong>
                    <small>Benachrichtigungen bei neuen Essensvorschlägen</small>
                </span>
            </label>
        </div>

        <button type="submit" class="btn btn-primary">Einstellungen speichern</button>
    </form>

    <div class="notification-status" style="display: none;">
        <p class="status-message"></p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', async () => {
        const form = document.getElementById('notification-preferences-form');
        const statusDiv = document.querySelector('.notification-status');
        const statusMsg = document.querySelector('.status-message');

        // Load current preferences
        try {
            const response = await fetch('/api/notifications/preferences');
            if (response.ok) {
                const prefs = await response.json();
                document.getElementById('notify-events').checked = prefs.events;
                document.getElementById('notify-notices').checked = prefs.notices;
                document.getElementById('notify-meals').checked = prefs.meals;
            }
        } catch (error) {
            console.error('Error loading preferences:', error);
        }

        // Handle form submission
        form.addEventListener('submit', async (e) => {
            e.preventDefault();

            const data = {
                events: document.getElementById('notify-events').checked,
                notices: document.getElementById('notify-notices').checked,
                meals: document.getElementById('notify-meals').checked
            };

            try {
                const response = await fetch('/api/notifications/update-preferences', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(data)
                });

                if (response.ok) {
                    statusMsg.textContent = 'Einstellungen gespeichert!';
                    statusMsg.className = 'status-message success';
                    statusDiv.style.display = 'block';

                    // Notification permission is now handled by main.js
                    if (Notification.permission === 'default') {
                        alert('Bitte erlaube Benachrichtigungen, wenn du dazu aufgefordert wirst.');
                    }
                } else {
                    throw new Error('Failed to save');
                }
            } catch (error) {
                statusMsg.textContent = 'Fehler beim Speichern der Einstellungen';
                statusMsg.className = 'status-message error';
                statusDiv.style.display = 'block';
            }

            setTimeout(() => {
                statusDiv.style.display = 'none';
            }, 3000);
        });
    });
</script>

<style>
    .notification-settings {
        max-width: 600px;
        margin: 2rem auto;
        padding: 2rem;
    }

    .preferences-form {
        margin-top: 2rem;
    }

    .preference-item {
        margin-bottom: 1.5rem;
        padding: 1rem;
        border: 1px solid #ddd;
        border-radius: 8px;
    }

    .preference-label {
        display: flex;
        align-items: flex-start;
        cursor: pointer;
    }

    .preference-label input[type="checkbox"] {
        margin-right: 1rem;
        margin-top: 0.25rem;
    }

    .preference-text {
        flex: 1;
    }

    .preference-text strong {
        display: block;
        margin-bottom: 0.25rem;
    }

    .preference-text small {
        color: #666;
    }

    .notification-status {
        margin-top: 1rem;
        padding: 1rem;
        border-radius: 4px;
    }

    .status-message.success {
        background: #d4edda;
        color: #155724;
    }

    .status-message.error {
        background: #f8d7da;
        color: #721c24;
    }
</style>
