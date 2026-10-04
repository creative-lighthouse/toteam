/**
 * Links in Freitext erkennen (AppLinkifiedText, Feed-Beiträge): http(s)://… und www.…
 */

// Kandidaten: alles ab http(s):// bzw. www. bis zum nächsten Leerraum
const URL_PATTERN = /\b(?:https?:\/\/|www\.)[^\s<>"]+/gi
// Satzzeichen am Ende gehören meist zum Satz, nicht zur URL ("Siehe https://x.de.")
const TRAILING_CHARS = '.,;:!?\'"»“”)]'

function count(str, char) {
  return str.split(char).length - 1
}

function splitUrl(raw) {
  let url = raw
  let trailing = ''
  while (url.length && TRAILING_CHARS.includes(url[url.length - 1])) {
    const ch = url[url.length - 1]
    // Schließende Klammer behalten, wenn sie zu einer Klammer in der URL gehört (z. B. Wikipedia)
    if (ch === ')' && count(url, '(') >= count(url, ')')) break
    trailing = ch + trailing
    url = url.slice(0, -1)
  }
  return [url, trailing]
}

/** Text in Stücke zerlegen: [{ text }] bzw. [{ text, href }] für Links */
export function linkifyParts(text) {
  text = text || ''
  const result = []
  let last = 0
  for (const match of text.matchAll(URL_PATTERN)) {
    const [url, trailing] = splitUrl(match[0])
    if (match.index > last) result.push({ text: text.slice(last, match.index) })
    const href = /^https?:\/\//i.test(url) ? url : `https://${url}`
    result.push({ text: url, href })
    if (trailing) result.push({ text: trailing })
    last = match.index + match[0].length
  }
  if (last < text.length) result.push({ text: text.slice(last) })
  return result
}
