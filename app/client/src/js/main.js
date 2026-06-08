import Swiper, {Autoplay, EffectCoverflow, EffectFade, Navigation, Pagination} from 'swiper';
import GLightbox from "glightbox";
import { requestNotificationPermission, onMessageListener } from './notifications';
import { initNotificationInbox, loadNotifications, openNotificationsDialog } from './notificationInbox';
// import './maprenderer.js'; // Complex version - disabled for now
import './maprenderer.js'; // Simplified version for testing

// Make openNotificationsDialog globally available for onclick handlers
window.openNotificationsDialog = openNotificationsDialog;

document.addEventListener("DOMContentLoaded", function () {
    // Initialize notification inbox
    initNotificationInbox();

    // Load notifications if on notifications page
    if (document.getElementById('notificationList')) {
        loadNotifications();
    }

    // Initialize push notifications
    if ('serviceWorker' in navigator && 'Notification' in window) {
        // Request permission after a delay (better UX)
        setTimeout(() => {
            console.log('Notification permission:', Notification.permission);
            if (Notification.permission === 'default') {
                console.log('Requesting notification permission...');
                requestNotificationPermission();
            } else if (Notification.permission === 'granted') {
                console.log('Permission already granted, getting token...');
                requestNotificationPermission();
            }
        }, 3000);

        // Listen for foreground messages and show notifications
        console.log('Setting up foreground message listener...');
        onMessageListener().then(payload => {
            console.log('Received foreground message:', payload);
            // Show notification in foreground (when tab is active)
            if (Notification.permission === 'granted' && payload.data) {
                console.log('Showing foreground notification:', payload.data.title);
                new Notification(payload.data.title || 'Neue Benachrichtigung', {
                    body: payload.data.body || '',
                    icon: '/_resources/app/client/icons/icon_192.png',
                    badge: '/_resources/app/client/icons/ToTeam-Favicon-x64.png',
                    data: { url: payload.data.url || '/' }
                });
            }
        }).catch(err => {
            console.error('Error in foreground message listener:', err);
        });
    }


        // Workaround: PWA-Session-Reload bei /registration
        if (window.matchMedia('(display-mode: standalone)').matches && window.location.pathname === '/registration') {
            setTimeout(() => {
                window.location.reload();
            }, 300);
        }
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


});
