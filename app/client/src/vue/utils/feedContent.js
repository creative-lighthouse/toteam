import { linkifyParts } from '@utils/linkify'

/**
 * Inhalt eines Feed-Beitrags (FeedPost) für die Anzeige: leicht formatiertes HTML
 * (p, br, strong, em, u — bereinigt schon das Backend, siehe FeedPost::sanitizeContent()),
 * darin @-Markierungen und Links als <a>. Alte Beiträge in reinem Text werden in Absätze
 * umgewandelt.
 */

// @benutzername — wie FeedPost::MENTION_PATTERN: nicht mit Punkt/Bindestrich am Ende ("@max.")
const MENTION_PATTERN = /@([A-Za-z0-9_](?:[A-Za-z0-9._-]*[A-Za-z0-9_])?)/g

const ALLOWED_TAGS = new Set(['P', 'BR', 'STRONG', 'EM', 'U'])

function escapeHtml(text) {
  const el = document.createElement('div')
  el.textContent = text
  return el.innerHTML
}

function plainTextToHtml(text) {
  return text.split(/\n{2,}/).map(p => `<p>${escapeHtml(p).replace(/\n/g, '<br>')}</p>`).join('')
}

// Zweite Sicherung nach dem Backend: fremde Elemente werden zu Text, Attribute fallen weg
function sanitize(root) {
  for (const el of [...root.querySelectorAll('*')]) {
    if (!ALLOWED_TAGS.has(el.tagName)) {
      el.replaceWith(document.createTextNode(el.textContent))
      continue
    }
    for (const attr of [...el.attributes]) el.removeAttribute(attr.name)
  }
}

// Ein Textknoten → Fragment mit Links für @-Markierungen (nur bekannte Personen) und URLs
function enrichText(text, mentions) {
  const fragment = document.createDocumentFragment()
  const appendLinkified = chunk => {
    for (const part of linkifyParts(chunk)) {
      if (!part.href) {
        fragment.append(part.text)
        continue
      }
      const a = document.createElement('a')
      a.href = part.href
      a.target = '_blank'
      a.rel = 'noopener noreferrer'
      a.className = 'linkified-text_link'
      a.textContent = part.text
      fragment.append(a)
    }
  }
  let last = 0
  for (const match of text.matchAll(MENTION_PATTERN)) {
    const member = mentions[match[1].toLowerCase()]
    if (!member) continue
    appendLinkified(text.slice(last, match.index))
    const a = document.createElement('a')
    a.href = `/app/profile/${encodeURIComponent(member.Username)}`
    a.className = 'feed-post-card_mention'
    a.dataset.username = member.Username
    a.title = member.Name
    a.textContent = match[0]
    fragment.append(a)
    last = match.index + match[0].length
  }
  appendLinkified(text.slice(last))
  return fragment
}

/** HTML-String für v-html */
export function renderFeedContent(content, mentions = {}) {
  const raw = content ?? ''
  const template = document.createElement('template')
  template.innerHTML = raw.trimStart().startsWith('<') ? raw : plainTextToHtml(raw)
  const root = template.content
  sanitize(root)

  const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
  const textNodes = []
  while (walker.nextNode()) textNodes.push(walker.currentNode)
  for (const node of textNodes) node.replaceWith(enrichText(node.textContent, mentions))

  const wrapper = document.createElement('div')
  wrapper.append(root)
  return wrapper.innerHTML
}
