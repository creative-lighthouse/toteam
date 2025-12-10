import { messaging } from './firebase';
import { getToken, onMessage } from 'firebase/messaging';

/**
 * Request permission for notifications and get FCM token
 */
export async function requestNotificationPermission() {
  try {
    const permission = await Notification.requestPermission();

    if (permission === 'granted') {
      console.log('Notification permission granted.');

      // Use existing service worker
      const registration = await navigator.serviceWorker.ready;

      // Get FCM token
      const currentToken = await getToken(messaging, {
        vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
        serviceWorkerRegistration: registration
      });

      if (currentToken) {
        console.log('FCM Token:', currentToken);
        // Send token to backend
        await saveFCMToken(currentToken);
        return currentToken;
      } else {
        console.log('No registration token available. Request permission to generate one.');
        return null;
      }
    } else {
      console.log('Unable to get permission to notify.');
      return null;
    }
  } catch (err) {
    console.error('An error occurred while retrieving token. ', err);
    return null;
  }
}

/**
 * Save FCM token to backend
 */
async function saveFCMToken(token) {
  try {
    const response = await fetch('/api/notifications/save-token', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({ token })
    });

    if (response.ok) {
      console.log('Token saved to backend');
    } else {
      console.error('Failed to save token to backend');
    }
  } catch (error) {
    console.error('Error saving token:', error);
  }
}

/**
 * Handle foreground messages
 */
export function onMessageListener() {
  return new Promise((resolve) => {
    onMessage(messaging, (payload) => {
      console.log('Message received in foreground: ', payload);
      resolve(payload);
    });
  });
}

/**
 * Show browser notification
 */
export function showNotification(title, options = {}) {
  if ('Notification' in window && Notification.permission === 'granted') {
    new Notification(title, {
      icon: '/_resources/app/client/icons/icon_192.png',
      badge: '/_resources/app/client/icons/ToTeam-Favicon-x64.png',
      ...options
    });
  }
}
