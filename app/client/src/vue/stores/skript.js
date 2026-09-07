import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { apiGet, apiPost, apiPut, apiDelete, clearCacheForEndpoint } from '@utils/api'

export const useSkriptStore = defineStore('skript', () => {
  const scripts = ref([])
  const organizations = ref([])
  const currentScript = ref(null)
  const loading = ref(false)
  const error = ref(null)

  // 'view' | 'edit' | 'focus' | 'learn'
  const activeMode = ref('view')
  const focusMemberId = ref(null)

  const tocEntries = computed(() => {
    if (!currentScript.value) return []
    const entries = []
    for (const paragraph of currentScript.value.Paragraphs || []) {
      const match = paragraph.Content?.match(/^\s*<h([234])[^>]*>(.*?)<\/h\1>/i)
      if (match) {
        entries.push({
          paragraphId: paragraph.ID,
          level: parseInt(match[1]),
          text: match[2].replace(/<[^>]+>/g, ''),
        })
      }
    }
    return entries
  })

  // RoleIDs assigned to the given member within the current script, used by
  // Fokus-/Lernmodus to decide which paragraphs highlight/blur for them.
  function roleIdsForMember(memberId) {
    if (!currentScript.value || !memberId) return new Set()
    const ids = (currentScript.value.Roles || [])
      .filter(r => r.MemberIDs?.includes(memberId))
      .map(r => r.ID)
    return new Set(ids)
  }

  function isParagraphForMember(paragraph, memberId) {
    const roleIds = roleIdsForMember(memberId)
    if (roleIds.size === 0) return false
    return paragraph.RoleIDs?.some(id => roleIds.has(id))
  }

  async function fetchScripts(forceRefresh = false) {
    try {
      loading.value = scripts.value.length === 0
      error.value = null
      if (forceRefresh) {
        await clearCacheForEndpoint('/skript')
      }
      const response = await apiGet('/skript', !forceRefresh)
      scripts.value = response.scripts || []
      organizations.value = response.organizations || []
    } catch (err) {
      console.error('Failed to fetch scripts:', err)
      error.value = err.message
    } finally {
      loading.value = false
    }
  }

  async function fetchScriptByHash(hash) {
    try {
      const response = await apiGet(`/skript/detail?hash=${hash}`, false)
      currentScript.value = response.script || null
      return currentScript.value
    } catch (err) {
      console.error('Failed to fetch script by hash:', err)
      return null
    }
  }

  async function fetchOrgMembers(orgId) {
    try {
      const response = await apiGet(`/skript/orgMembers/${orgId}`, false)
      return response.members || []
    } catch (err) {
      console.error('Failed to fetch organization members:', err)
      return []
    }
  }

  async function createScript(data) {
    const response = await apiPost('/skript/store', data)
    if (response.success && response.data?.script) {
      scripts.value.unshift(response.data.script)
      await clearCacheForEndpoint('/skript')
    }
    return response
  }

  async function updateScript(id, data) {
    const response = await apiPut(`/skript/update/${id}`, data)
    if (response.success && response.data?.script) {
      if (currentScript.value?.ID === id) {
        currentScript.value = { ...currentScript.value, ...response.data.script }
      }
      await clearCacheForEndpoint('/skript')
    }
    return response
  }

  async function deleteScript(id) {
    const response = await apiDelete(`/skript/remove/${id}`)
    if (response.success) {
      scripts.value = scripts.value.filter(s => s.ID !== id)
      await clearCacheForEndpoint('/skript')
    }
    return response
  }

  async function createRole(scriptId, title) {
    const response = await apiPost('/skript/roleStore', { ScriptID: scriptId, Title: title })
    if (response.success && response.data?.role && currentScript.value) {
      currentScript.value.Roles = [...(currentScript.value.Roles || []), response.data.role]
    }
    return response
  }

  async function updateRole(id, title) {
    const response = await apiPut(`/skript/roleUpdate/${id}`, { Title: title })
    if (response.success && response.data?.role && currentScript.value) {
      const idx = currentScript.value.Roles.findIndex(r => r.ID === id)
      if (idx !== -1) currentScript.value.Roles[idx] = response.data.role
    }
    return response
  }

  async function deleteRole(id) {
    const response = await apiDelete(`/skript/roleRemove/${id}`)
    if (response.success && currentScript.value) {
      currentScript.value.Roles = currentScript.value.Roles.filter(r => r.ID !== id)
      currentScript.value.Paragraphs = currentScript.value.Paragraphs.map(p => ({
        ...p,
        RoleIDs: p.RoleIDs.filter(rid => rid !== id),
      }))
    }
    return response
  }

  async function assignRoleMembers(roleId, memberIds) {
    const response = await apiPut(`/skript/roleAssignMembers/${roleId}`, { MemberIDs: memberIds })
    if (response.success && response.data?.role && currentScript.value) {
      const idx = currentScript.value.Roles.findIndex(r => r.ID === roleId)
      if (idx !== -1) currentScript.value.Roles[idx] = response.data.role
    }
    return response
  }

  // Reconciles the full, ordered list of paragraphs extracted from the
  // continuous script editor against the backend in one call: known IDs are
  // updated, entries without an ID are created, and paragraphs that no longer
  // appear in the document are deleted. Returns the canonical, ID-complete
  // list (same order) so the editor can write real IDs back onto its nodes.
  async function syncParagraphs(scriptId, paragraphs) {
    const response = await apiPut('/skript/paragraphSync', { ScriptID: scriptId, Paragraphs: paragraphs })
    if (response.success && response.data?.paragraphs && currentScript.value) {
      currentScript.value.Paragraphs = response.data.paragraphs
    }
    return response
  }

  function setMode(mode) {
    activeMode.value = mode
  }

  function setFocusMember(memberId) {
    focusMemberId.value = memberId
  }

  return {
    scripts,
    organizations,
    currentScript,
    loading,
    error,
    activeMode,
    focusMemberId,
    tocEntries,
    roleIdsForMember,
    isParagraphForMember,
    fetchScripts,
    fetchScriptByHash,
    fetchOrgMembers,
    createScript,
    updateScript,
    deleteScript,
    createRole,
    updateRole,
    deleteRole,
    assignRoleMembers,
    syncParagraphs,
    setMode,
    setFocusMember,
  }
})
