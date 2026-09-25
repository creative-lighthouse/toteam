// NFC-Tags lesen (Web NFC — nur Chrome auf Android, nur über HTTPS).
// Schreiben siehe components/ui/WriteNFCModal.vue.

import { ref, onBeforeUnmount } from 'vue'

export function isNfcSupported() {
  return typeof window !== 'undefined' && 'NDEFReader' in window && window.isSecureContext
}

/**
 * Erkennt ToTeam-Teilen-Links (Objekt oder Raum) dieser Installation.
 * @returns {{ kind: 'item'|'room', token: string } | null}
 */
export function parseShareLink(value) {
  let url
  try {
    url = new URL(value)
  } catch {
    return null
  }
  if (url.origin !== window.location.origin) return null
  const item = url.pathname.match(/^\/app\/inventory\/share\/([a-f0-9]+)\/?$/i)
  if (item) return { kind: 'item', token: item[1] }
  const room = url.pathname.match(/^\/app\/rooms\/share\/([a-f0-9]+)\/?$/i)
  if (room) return { kind: 'room', token: room[1] }
  return null
}

function decodeRecord(record) {
  if (record.recordType !== 'url' && record.recordType !== 'absolute-url' && record.recordType !== 'text') return null
  try {
    return new TextDecoder(record.encoding || 'utf-8').decode(record.data)
  } catch {
    return null
  }
}

/**
 * Scannt NFC-Tags zwischen `start()` und `stop()` (spätestens beim Unmount) und
 * meldet den ersten Link/Text eines Tags (null, wenn keiner drauf ist).
 * `start()` muss aus einem Klick heraus aufgerufen werden (Browser fragt nach Erlaubnis).
 *
 * @param {(url: string) => void} onLink
 */
export function useNfcScanner(onLink) {
  const supported = isNfcSupported()
  const active = ref(false)
  const error = ref(null)
  let controller = null

  async function start() {
    if (!supported || active.value) return
    error.value = null
    controller = new AbortController()
    try {
      // eslint-disable-next-line no-undef
      const reader = new NDEFReader()
      reader.addEventListener('reading', event => {
        for (const record of event.message.records) {
          const text = decodeRecord(record)
          if (text) {
            onLink(text)
            return
          }
        }
        onLink(null)
      }, { signal: controller.signal })
      reader.addEventListener('readingerror', () => {
        error.value = 'Der Tag konnte nicht gelesen werden. Bitte noch einmal ans Handy halten.'
      }, { signal: controller.signal })
      await reader.scan({ signal: controller.signal })
      active.value = true
    } catch (err) {
      controller = null
      active.value = false
      if (err?.name === 'AbortError') return
      error.value = err?.name === 'NotAllowedError'
        ? 'NFC-Zugriff wurde nicht erlaubt. Bitte die Berechtigung erteilen und NFC einschalten.'
        : 'NFC-Scan konnte nicht gestartet werden.'
    }
  }

  function stop() {
    controller?.abort()
    controller = null
    active.value = false
  }

  onBeforeUnmount(stop)

  return { supported, active, error, start, stop }
}
