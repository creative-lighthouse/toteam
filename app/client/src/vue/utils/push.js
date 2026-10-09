import { apiPost } from '@utils/api'

// Push-Benachrichtigungen über Firebase Cloud Messaging. Firebase wird erst geladen,
// wenn der Browser Push überhaupt kann — und ohne Analytics (keine Tracking-Requests).

const TOKEN_KEY = 'toteam_push_token'
const ICON = '/_resources/app/client/icons/icon_192.png'
const BADGE = '/_resources/app/client/icons/ToTeam-Favicon-x64.png'

let messagingPromise = null

function getMessagingInstance() {
    if (!messagingPromise) {
        messagingPromise = (async () => {
            const [{ initializeApp, getApps }, messagingSdk] = await Promise.all([
                import('firebase/app'),
                import('firebase/messaging')
            ])
            if (!(await messagingSdk.isSupported())) return null

            const app = getApps()[0] || initializeApp({
                apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
                authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
                projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
                storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
                messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
                appId: import.meta.env.VITE_FIREBASE_APP_ID
            })
            return { sdk: messagingSdk, messaging: messagingSdk.getMessaging(app) }
        })().catch((err) => {
            console.error('[Push] Firebase konnte nicht geladen werden:', err)
            return null
        })
    }
    return messagingPromise
}

function readStoredToken() {
    try { return localStorage.getItem(TOKEN_KEY) } catch { return null }
}

function writeStoredToken(token) {
    try {
        if (token) localStorage.setItem(TOKEN_KEY, token)
        else localStorage.removeItem(TOKEN_KEY)
    } catch { /* privater Modus o.ä. */ }
}

/** Kann dieser Browser überhaupt Push? (iOS: nur als installierte App) */
export function isPushSupported() {
    return 'Notification' in window && 'serviceWorker' in navigator && 'PushManager' in window
}

/** 'unsupported' | 'default' | 'granted' | 'denied' */
export function getPushPermission() {
    return isPushSupported() ? Notification.permission : 'unsupported'
}

/**
 * Holt den FCM-Token dieses Geräts und meldet ihn dem Backend. Nur bei bereits
 * erteilter Berechtigung — fragt selbst nie nach. Gibt true zurück, wenn das
 * Gerät registriert ist.
 */
export async function registerPushToken() {
    if (getPushPermission() !== 'granted') return false

    const instance = await getMessagingInstance()
    if (!instance) return false

    try {
        const registration = await navigator.serviceWorker.ready
        const token = await instance.sdk.getToken(instance.messaging, {
            vapidKey: import.meta.env.VITE_FIREBASE_VAPID_KEY,
            serviceWorkerRegistration: registration
        })
        if (!token) return false

        const response = await apiPost('/notifications/save-token', { token })
        if (!response?.success) {
            console.error('[Push] Token konnte nicht gespeichert werden:', response?.error)
            return false
        }
        writeStoredToken(token)
        return true
    } catch (err) {
        console.error('[Push] Registrierung fehlgeschlagen:', err)
        return false
    }
}

/**
 * Fragt nach der Berechtigung (muss aus einem Klick heraus aufgerufen werden —
 * Safari/Firefox ignorieren die Anfrage sonst) und registriert das Gerät.
 */
export async function enablePush() {
    if (!isPushSupported()) return false
    const permission = await Notification.requestPermission()
    if (permission !== 'granted') return false
    return registerPushToken()
}

/** Beim Abmelden: Gerät beim Backend abmelden (solange der Access-Token noch gilt) */
export async function unregisterPushToken() {
    const token = readStoredToken()
    if (!token) return
    try {
        await apiPost('/notifications/remove-token', { token })
    } catch (err) {
        console.error('[Push] Abmelden fehlgeschlagen:', err)
    }
    writeStoredToken(null)
}

/**
 * Nachrichten, während die App im Vordergrund ist: FCM reicht sie dann nicht an den
 * Service Worker weiter, also hier selbst anzeigen und `onReceive` aufrufen
 * (z.B. um die Benachrichtigungs-Liste neu zu laden).
 */
export async function listenForForegroundMessages(onReceive = () => {}) {
    const instance = await getMessagingInstance()
    if (!instance) return

    instance.sdk.onMessage(instance.messaging, async (payload) => {
        onReceive(payload)

        const data = payload.data || {}
        if (Notification.permission !== 'granted') return
        try {
            const registration = await navigator.serviceWorker.ready
            await registration.showNotification(data.title || 'Neue Benachrichtigung', {
                body: data.body || '',
                icon: ICON,
                badge: BADGE,
                data: { url: data.url || '/app/dashboard' }
            })
        } catch (err) {
            console.error('[Push] Anzeige fehlgeschlagen:', err)
        }
    })
}
