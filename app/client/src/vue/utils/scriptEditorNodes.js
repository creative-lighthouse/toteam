import Paragraph from '@tiptap/extension-paragraph'
import Heading from '@tiptap/extension-heading'
import BulletList from '@tiptap/extension-bullet-list'
import OrderedList from '@tiptap/extension-ordered-list'
import { DOMSerializer } from '@tiptap/pm/model'
import { VueNodeViewRenderer } from '@tiptap/vue-3'
import ScriptBlockView from '@components/skript/ScriptBlockView.vue'

// The node types that count as one "Absatz" (block): each becomes exactly one
// ScriptParagraph row, gets its own gutter number, and can be tagged with
// roles. A list counts as a single block (e.g. a stage direction list), not
// one block per list item.
const BLOCK_TYPES = ['paragraph', 'heading', 'bulletList', 'orderedList']
const BLOCK_TAGS = { paragraph: 'p', bulletList: 'ul', orderedList: 'ol' } // heading tag depends on level

// Extends a node type with two attributes so a single continuous script
// document can round-trip through the backend as discrete ScriptParagraph
// rows:
//  - pid: the ScriptParagraph ID once persisted (null for a not-yet-synced,
//    freshly typed block — e.g. right after pressing Enter)
//  - roleIds: the ScriptRole IDs assigned to this block, set via the inline
//    role dropdown rendered by ScriptBlockView (see addNodeView() below)
function withScriptAttributes(NodeType) {
  return NodeType.extend({
    addAttributes() {
      return {
        ...this.parent?.(),
        // keepOnSplit must be false for both: Tiptap defaults node attributes
        // to keepOnSplit: true, which — since pressing Enter mid-paragraph
        // splits that node into two — would otherwise copy this paragraph's
        // already-synced ID and role assignment onto the brand new one right
        // next to it, making every subsequently typed paragraph collide onto
        // the same backend row.
        pid: {
          default: null,
          keepOnSplit: false,
          parseHTML: el => {
            const value = el.getAttribute('data-pid')
            return value ? parseInt(value) : null
          },
          renderHTML: attrs => (attrs.pid ? { 'data-pid': attrs.pid } : {}),
        },
        roleIds: {
          default: [],
          keepOnSplit: false,
          parseHTML: el => (el.getAttribute('data-role-ids') || '')
            .split(',')
            .filter(Boolean)
            .map(Number),
          renderHTML: attrs => (attrs.roleIds?.length ? { 'data-role-ids': attrs.roleIds.join(',') } : {}),
        },
        // A stage direction ("Regieanweisung"): excluded from the running
        // line count and centered/width-limited instead of attributed to a
        // role — see ScriptBlockView.vue and ScriptRoleDropdown.vue.
        isDirection: {
          default: false,
          keepOnSplit: false,
          parseHTML: el => el.getAttribute('data-is-direction') === '1',
          renderHTML: attrs => (attrs.isDirection ? { 'data-is-direction': '1' } : {}),
        },
      }
    },
    addNodeView() {
      return VueNodeViewRenderer(ScriptBlockView)
    },
  })
}

export const ScriptParagraphNode = withScriptAttributes(Paragraph)
export const ScriptHeadingNode = withScriptAttributes(Heading)
export const ScriptBulletListNode = withScriptAttributes(BulletList)
export const ScriptOrderedListNode = withScriptAttributes(OrderedList)

// Builds the editor's initial HTML content from the script's persisted
// paragraphs, injecting each block's pid/roleIds as data-attributes on its
// top-level tag so the extensions above pick them up on parse.
export function buildEditorContent(paragraphs) {
  if (!paragraphs || paragraphs.length === 0) {
    return '<p></p>'
  }
  return paragraphs.map(p => {
    const html = (p.Content || '').trim() || '<p></p>'
    const attrs = ` data-pid="${p.ID}" data-role-ids="${(p.RoleIDs || []).join(',')}"` +
      (p.IsDirection ? ' data-is-direction="1"' : '')
    const match = html.match(/^<(p|h2|h3|h4|ul|ol)\b([^>]*)>/i)
    if (!match) {
      return `<p${attrs}>${html}</p>`
    }
    return html.replace(match[0], `<${match[1]}${match[2]}${attrs}>`)
  }).join('')
}

// Walks the current editor document and extracts one entry per top-level
// block (see BLOCK_TYPES), in document order, serializing each to real HTML
// via ProseMirror's DOM serializer — the shape the backend's paragraphSync
// endpoint expects.
export function extractParagraphs(editor) {
  const serializer = DOMSerializer.fromSchema(editor.schema)
  const entries = []

  editor.state.doc.forEach(node => {
    if (!BLOCK_TYPES.includes(node.type.name)) return

    const tag = node.type.name === 'heading' ? `h${node.attrs.level}` : BLOCK_TAGS[node.type.name]
    const fragment = serializer.serializeFragment(node.content)
    const wrapper = document.createElement(tag)
    wrapper.appendChild(fragment)

    entries.push({
      ID: node.attrs.pid || null,
      Content: wrapper.outerHTML,
      RoleIDs: node.attrs.roleIds || [],
      IsDirection: !!node.attrs.isDirection,
    })
  })

  return entries
}
