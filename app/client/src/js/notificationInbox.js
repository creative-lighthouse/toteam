/**
 * Notification Inbox for IntroBar
 * Handles loading, displaying, and managing notifications with infinite scroll
 */

let currentPage = 1;
let isLoading = false;
let hasMoreNotifications = true;
const ITEMS_PER_PAGE = 20;

/**
 * Open the notifications dialog and load notifications
 */
export function openNotificationsDialog() {
    const dialog = document.getElementById('introbar-notifications-dialog');
    if (!dialog) {
        return;
    }

    dialog.showModal();

    // Load notifications if not already loaded
    const notificationsList = dialog.querySelector('[data-behaviour="notifications-list"]');
    if (notificationsList && !notificationsList.hasAttribute('data-loaded')) {
        loadNotifications(notificationsList);
        notificationsList.setAttribute('data-loaded', 'true');
    }
}

/**
 * Initialize the notification inbox in IntroBar
 */
export function initNotificationInbox() {
    const dialog = document.getElementById('introbar-notifications-dialog');
    if (!dialog) return;

    const notificationsList = dialog.querySelector('[data-behaviour="notifications-list"]');
    if (!notificationsList) return;

    // Attach click handler to notification button
    const notificationButton = document.querySelector('[data-action="open-notifications"]');
    if (notificationButton) {
        notificationButton.addEventListener('click', (e) => {
            e.preventDefault();
            openNotificationsDialog();
        });
    }

    // Add "Mark all as read" button
    addMarkAllAsReadButton(dialog);

    // Setup infinite scroll
    setupInfiniteScroll(notificationsList);
}

/**
 * Add "Mark all as read" button to dialog header
 */
function addMarkAllAsReadButton(dialog) {
    const header = dialog.querySelector('.dialog-header');
    if (!header || header.querySelector('.mark-all-read-btn')) return;

    const button = document.createElement('button');
    button.className = 'mark-all-read-btn';
    button.textContent = 'Alle als gelesen markieren';
    button.style.cssText = 'margin-left: auto; padding: 8px 16px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 14px;';

    button.addEventListener('click', async () => {
        await markAllAsRead();
        // Reload notifications
        const notificationsList = dialog.querySelector('[data-behaviour="notifications-list"]');
        if (notificationsList) {
            currentPage = 1;
            hasMoreNotifications = true;
            notificationsList.innerHTML = '';
            notificationsList.removeAttribute('data-loaded');
            await loadNotifications(notificationsList);
            notificationsList.setAttribute('data-loaded', 'true');
        }
    });

    // Make header flexbox to align button
    header.style.display = 'flex';
    header.style.alignItems = 'center';
    header.style.gap = '16px';
    header.appendChild(button);
}

/**
 * Setup infinite scroll for notifications list
 */
function setupInfiniteScroll(container) {
    container.addEventListener('scroll', async () => {
        if (isLoading || !hasMoreNotifications) return;

        const scrollPosition = container.scrollTop + container.clientHeight;
        const scrollHeight = container.scrollHeight;

        // Load more when scrolled to bottom (with 100px threshold)
        if (scrollPosition >= scrollHeight - 100) {
            currentPage++;
            await loadNotifications(container, true);
        }
    });
}

/**
 * Load notifications from API
 * @param {HTMLElement} container - Container to append notifications to
 * @param {boolean} append - Whether to append or replace content
 */
export async function loadNotifications(container = null, append = false) {
    if (!container) {
        container = document.querySelector('[data-behaviour="notifications-list"]');
    }
    if (!container) return;

    if (isLoading) return;
    isLoading = true;

    try {
        const response = await fetch(`/api/notifications/inbox?page=${currentPage}&limit=${ITEMS_PER_PAGE}`);
        if (!response.ok) {
            throw new Error('Failed to load notifications');
        }

        const data = await response.json();

        if (data.notifications && data.notifications.length > 0) {
            if (!append) {
                container.innerHTML = '';
            }

            data.notifications.forEach(notification => {
                const notificationEl = createNotificationElement(notification);
                container.appendChild(notificationEl);
            });

            hasMoreNotifications = data.notifications.length === ITEMS_PER_PAGE;
        } else {
            hasMoreNotifications = false;

            if (!append && container.children.length === 0) {
                container.innerHTML = '<p style="text-align: center; padding: 24px; color: #666;">Keine Benachrichtigungen vorhanden</p>';
            }
        }
    } catch (error) {
        console.error('Error loading notifications:', error);
        if (!append) {
            container.innerHTML = '<p style="text-align: center; padding: 24px; color: #d32f2f;">Fehler beim Laden der Benachrichtigungen</p>';
        }
    } finally {
        isLoading = false;
    }
}

/**
 * Create notification element
 * @param {Object} notification - Notification data
 * @returns {HTMLElement}
 */
function createNotificationElement(notification) {
    const item = document.createElement('div');
    item.className = `notification-item ${notification.isRead ? 'read' : 'unread'}`;
    item.setAttribute('data-id', notification.id);

    item.style.cssText = `
        padding: 16px;
        border-bottom: 1px solid #e0e0e0;
        display: flex;
        gap: 12px;
        align-items: flex-start;
        background: ${notification.isRead ? '#f9f9f9' : '#fff'};
        transition: background 0.2s;
    `;

    // Notification content wrapper
    const contentWrapper = document.createElement('div');
    contentWrapper.style.cssText = 'flex: 1; min-width: 0;';

    // Title
    const title = document.createElement('h4');
    title.textContent = notification.title || 'Keine Titel';
    title.style.cssText = `
        margin: 0 0 4px 0;
        font-size: 16px;
        font-weight: ${notification.isRead ? 'normal' : 'bold'};
        color: ${notification.isRead ? '#666' : '#333'};
    `;

    // Body
    const body = document.createElement('p');
    body.textContent = notification.body || 'Kein Inhalt';
    body.style.cssText = `
        margin: 0 0 8px 0;
        font-size: 14px;
        color: #666;
        line-height: 1.4;
    `;

    // Timestamp
    const timestamp = document.createElement('span');
    timestamp.textContent = formatTimestamp(notification.created);
    timestamp.style.cssText = 'font-size: 12px; color: #999;';

    contentWrapper.appendChild(title);
    contentWrapper.appendChild(body);
    contentWrapper.appendChild(timestamp);

    // Mark as read button
    if (!notification.isRead) {
        const markReadBtn = document.createElement('button');
        markReadBtn.className = 'mark-read-btn';
        markReadBtn.textContent = '✓';
        markReadBtn.title = 'Als gelesen markieren';
        markReadBtn.style.cssText = `
            padding: 8px 12px;
            background: #4caf50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            font-weight: bold;
            flex-shrink: 0;
            transition: background 0.2s;
        `;

        markReadBtn.addEventListener('mouseenter', () => {
            markReadBtn.style.background = '#45a049';
        });

        markReadBtn.addEventListener('mouseleave', () => {
            markReadBtn.style.background = '#4caf50';
        });

        markReadBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            e.stopPropagation();
            await markAsRead(notification.id, item);
        });

        item.appendChild(contentWrapper);
        item.appendChild(markReadBtn);
    } else {
        item.appendChild(contentWrapper);
    }

    // Make entire notification clickable (if URL exists)
    if (notification.url) {
        item.style.cursor = 'pointer';
        item.addEventListener('click', () => {
            window.location.href = notification.url;
        });
    }

    return item;
}

/**
 * Mark a single notification as read
 * @param {number} notificationId
 * @param {HTMLElement} element
 */
async function markAsRead(notificationId, element) {
    try {
        const response = await fetch(`/api/notifications/${notificationId}/mark-read`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Failed to mark as read');
        }

        // Update UI
        element.classList.remove('unread');
        element.classList.add('read');
        element.style.background = '#f9f9f9';

        // Update title weight
        const title = element.querySelector('h4');
        if (title) {
            title.style.fontWeight = 'normal';
            title.style.color = '#666';
        }

        // Remove mark as read button
        const markReadBtn = element.querySelector('.mark-read-btn');
        if (markReadBtn) {
            markReadBtn.remove();
        }
    } catch (error) {
        console.error('Error marking notification as read:', error);
        alert('Fehler beim Markieren der Benachrichtigung');
    }
}

/**
 * Mark all notifications as read
 */
async function markAllAsRead() {
    try {
        const response = await fetch('/api/notifications/mark-all-read', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            }
        });

        if (!response.ok) {
            throw new Error('Failed to mark all as read');
        }
    } catch (error) {
        console.error('Error marking all notifications as read:', error);
        alert('Fehler beim Markieren aller Benachrichtigungen');
    }
}

/**
 * Format timestamp for display
 * @param {string} timestamp - ISO timestamp
 * @returns {string}
 */
function formatTimestamp(timestamp) {
    if (!timestamp) {
        return '';
    }

    const date = new Date(timestamp);

    // Check if date is valid
    if (isNaN(date.getTime())) {
        return timestamp; // Return original if can't parse
    }    const now = new Date();
    const diffMs = now - date;
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 1) return 'Gerade eben';
    if (diffMins < 60) return `vor ${diffMins} Min.`;
    if (diffHours < 24) return `vor ${diffHours} Std.`;
    if (diffDays < 7) return `vor ${diffDays} Tag${diffDays > 1 ? 'en' : ''}`;

    // Format as date
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();
    const hours = date.getHours().toString().padStart(2, '0');
    const minutes = date.getMinutes().toString().padStart(2, '0');

    return `${day}.${month}.${year} ${hours}:${minutes}`;
}
