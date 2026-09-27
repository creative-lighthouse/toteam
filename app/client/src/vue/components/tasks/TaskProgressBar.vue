<template>
  <div v-if="progress" class="task-progress" :class="{ 'task-progress--compact': !showLegend }">
    <!-- Farben allein reichen nicht: für Screenreader als Text zusammengefasst -->
    <div class="task-progress_bar" role="img" :aria-label="progress.summary">
      <div
        v-for="seg in progress.segments"
        :key="seg.state"
        class="task-progress_segment"
        :class="`task-progress_segment--${seg.state}`"
        :style="{ width: seg.percent + '%' }"
        :title="`${seg.label}: ${seg.count}`"
      ></div>
    </div>
    <div v-if="showLegend" class="task-progress_legend" aria-hidden="true">
      <span v-for="seg in progress.segments" v-show="seg.count > 0" :key="seg.state" class="task-progress_legend-item">
        <span class="task-progress_legend-dot" :class="`task-progress_segment--${seg.state}`"></span>
        {{ seg.label }} ({{ seg.count }})
      </span>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useTasksStore } from '@stores/tasks'

const props = defineProps({
  subtasks: { type: Array, default: () => [] },
  showLegend: { type: Boolean, default: true },
})
const store = useTasksStore()

const progress = computed(() => {
  const total = props.subtasks.length
  if (!total) return null

  const counts = { open: 0, in_progress: 0, feedback: 0, finished: 0 }
  props.subtasks.forEach(s => { counts[s.State || 'open']++ })

  return {
    total,
    segments: [...store.STATES].reverse().map(s => ({
      state: s.value,
      label: s.label,
      count: counts[s.value] || 0,
      percent: total ? ((counts[s.value] || 0) / total) * 100 : 0,
    })),
    // z.B. "Unteraufgaben: 2 von 5 Erledigt, 1 In Arbeit, 2 Offen"
    summary: `Unteraufgaben: ${[...store.STATES].reverse()
      .filter(s => counts[s.value])
      .map((s, i) => (i === 0 ? `${counts[s.value]} von ${total} ${s.label}` : `${counts[s.value]} ${s.label}`))
      .join(', ')}`,
  }
})
</script>
