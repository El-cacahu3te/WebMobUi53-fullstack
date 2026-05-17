<template>
  <Transition
    enter-active-class="transition duration-200 ease-out"
    enter-from-class="opacity-0 -translate-y-1"
    enter-to-class="opacity-100 translate-y-0"
    leave-active-class="transition duration-150 ease-in"
    leave-from-class="opacity-100"
    leave-to-class="opacity-0"
  >
    <div
      v-if="message"
      :class="['flex items-start gap-3 px-4 py-3 rounded-xl text-sm font-medium border', variants[type]]"
      role="alert"
    >
      <span class="shrink-0 text-base">{{ icons[type] }}</span>
      <div class="flex-1">
        <ul v-if="errors.length" class="mt-0.5 space-y-0.5 list-disc list-inside">
          <li v-for="(e, i) in errors" :key="i">{{ e }}</li>
        </ul>
        <span v-else>{{ message }}</span>
      </div>
      <button v-if="dismissible" @click="$emit('dismiss')" class="shrink-0 opacity-50 hover:opacity-100">✕</button>
    </div>
  </Transition>
</template>

<script setup>
defineProps({
  type:        { type: String,  default: 'error' }, // error | success | warning | info
  message:     { type: String,  default: '' },
  errors:      { type: Array,   default: () => [] }, // tableau à plat de strings
  dismissible: { type: Boolean, default: false },
})
defineEmits(['dismiss'])

const variants = {
  error:   'bg-red-50 text-red-800 border-red-200',
  success: 'bg-green-50 text-green-800 border-green-200',
  warning: 'bg-amber-50 text-amber-800 border-amber-200',
  info:    'bg-blue-50 text-blue-800 border-blue-200',
}
const icons = { error: '⚠️', success: '✓', warning: '⚠️', info: 'ℹ️' }
</script>
