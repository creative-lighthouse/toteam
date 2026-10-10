/**
 * Filterlogik der Aufgaben — gemeinsam für die Übersicht (Filter im tasks-Store,
 * per Cookie gemerkt) und die Unteraufgaben auf der Detailseite (lokale Filter).
 */

export const TASK_STATES = [
  { value: 'open',        label: 'Offen' },
  { value: 'in_progress', label: 'In Bearbeitung' },
  { value: 'feedback',    label: 'Feedback' },
  { value: 'finished',    label: 'Abgeschlossen' },
]

// A task is "mine" if I'm its owner/supporter, or if I'm assigned to one of
// its subtasks — a subtask-only assignment should still surface the parent
// card, since subtasks aren't shown as their own entries in the list/kanban.
function isTaskAssignedToMember(task, memberId) {
  if (task.Owner?.ID === memberId) return true
  if (task.Supporters?.some(s => s.ID === memberId)) return true
  return false
}

function isTaskMine(task, memberId) {
  if (isTaskAssignedToMember(task, memberId)) return true
  return task.SubTasks?.some(sub => isTaskAssignedToMember(sub, memberId)) ?? false
}

/**
 * @param {Array} tasks
 * @param {{ organizationId?: number|null, personId?: number|null, state?: string|null, deadline?: string|null, search?: string }} filters
 */
export function filterTasks(tasks, { organizationId = null, personId = null, state = null, deadline = null, search = '' } = {}) {
  let result = tasks

  if (organizationId) {
    result = result.filter(t => t.Organization?.ID === organizationId)
  }

  if (personId) {
    result = result.filter(t => isTaskMine(t, personId))
  }

  if (state) {
    result = result.filter(t => (t.State || 'open') === state)
  }

  if (deadline) {
    result = result.filter(t => t.Deadline && t.Deadline <= deadline + 'T23:59:59')
  }

  if (search?.trim()) {
    const q = search.toLowerCase()
    result = result.filter(t =>
      t.Title?.toLowerCase().includes(q) ||
      t.Description?.toLowerCase().includes(q)
    )
  }

  return result
}

/** { open: [...], in_progress: [...], … } in der Reihenfolge von TASK_STATES */
export function groupTasksByState(tasks) {
  const grouped = Object.fromEntries(TASK_STATES.map(s => [s.value, []]))
  for (const task of tasks) {
    const state = task.State || 'open'
    if (grouped[state]) grouped[state].push(task)
  }
  return grouped
}
