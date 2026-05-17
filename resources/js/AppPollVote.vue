<script setup>
import { ref, onMounted, computed } from 'vue'
import { useFetchApi } from './composables/useFetchApi'
import AlertMessage from './components/AlertMessage.vue'

const props = defineProps({ token: String })
const { get, post } = useFetchApi('/api/v1')

const poll            = ref(null)
const selectedOptions = ref([])
const loading         = ref(true)
const voting          = ref(false)
const alertMsg        = ref('')
const alertType       = ref('error')

onMounted(async () => {
  try {
    poll.value = await get(`/polls/${props.token}`)
  } catch {
    alertMsg.value  = 'Impossible de charger le sondage.'
    alertType.value = 'error'
  } finally {
    loading.value = false
  }
})

const isExpired = computed(() => {
  if (!poll.value?.ends_at) return false
  return new Date(poll.value.ends_at) < new Date()
})

function toggleOption(optionId) {
  if (poll.value.allow_multiple_choices) {
    const idx = selectedOptions.value.indexOf(optionId)
    if (idx === -1) selectedOptions.value.push(optionId)
    else selectedOptions.value.splice(idx, 1)
  } else {
    selectedOptions.value = [optionId]
  }
}

async function submitVote() {
  if (selectedOptions.value.length === 0) {
    alertMsg.value  = 'Veuillez sélectionner au moins une option.'
    alertType.value = 'warning'
    return
  }
  voting.value   = true
  alertMsg.value = ''

  try {
    await post({ url: `/polls/${props.token}/vote`, data: { option_ids: selectedOptions.value } })
    alertMsg.value  = '✓ Vote enregistré ! Redirection vers les résultats...'
    alertType.value = 'success'
    setTimeout(() => { window.location.href = `/polls/${props.token}/results` }, 2000)
  } catch (err) {
    const msg = err?.data?.message ?? ''
    // Mapping des messages API vers messages FR
    if (msg.toLowerCase().includes('already voted') || msg.toLowerCase().includes('déjà voté')) {
      alertMsg.value = 'Vous avez déjà voté pour ce sondage.'
    } else if (msg.toLowerCase().includes('closed') || msg.toLowerCase().includes('terminé')) {
      alertMsg.value = 'Ce sondage est terminé.'
    } else if (msg.toLowerCase().includes('draft') || msg.toLowerCase().includes('not available')) {
      alertMsg.value = "Ce sondage n'est pas encore lancé."
    } else {
      alertMsg.value = msg || 'Erreur lors du vote. Veuillez réessayer.'
    }
    alertType.value = 'error'
  } finally {
    voting.value = false
  }
}
</script>

<template>
  <div class="max-w-lg mx-auto px-4 py-8">

    <!-- Chargement -->
    <div v-if="loading" class="flex flex-col items-center py-16 text-gray-400 gap-3">
      <span class="text-3xl animate-spin">⟳</span>
      <p>Chargement du sondage...</p>
    </div>

    <div v-else-if="poll" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">

      <!-- En-tête -->
      <div>
        <h1 class="text-xl font-bold text-gray-800">{{ poll.title }}</h1>
        <p class="text-gray-500 mt-1">{{ poll.question }}</p>
      </div>

      <!-- États bloquants -->
      <AlertMessage v-if="poll.is_draft" type="info" message="Ce sondage n'est pas encore lancé." />
      <AlertMessage v-else-if="isExpired" type="error" message="Ce sondage est terminé. Vous ne pouvez plus voter." />

      <!-- Formulaire de vote -->
      <template v-else>
        <p class="text-xs text-gray-400 font-medium uppercase tracking-wide">
          {{ poll.allow_multiple_choices ? 'Plusieurs choix possibles' : 'Un seul choix' }}
        </p>

        <div class="space-y-2">
          <div
            v-for="option in poll.options"
            :key="option.id"
            @click="toggleOption(option.id)"
            :class="[
              'flex items-center gap-3 p-3.5 rounded-xl border-2 cursor-pointer transition',
              selectedOptions.includes(option.id)
                ? 'border-green-500 bg-green-50'
                : 'border-gray-100 hover:border-gray-200 hover:bg-gray-50'
            ]"
          >
            <input
              :type="poll.allow_multiple_choices ? 'checkbox' : 'radio'"
              :checked="selectedOptions.includes(option.id)"
              class="accent-green-600 shrink-0"
              readonly
            />
            <span class="text-sm font-medium text-gray-700">{{ option.label }}</span>
          </div>
        </div>

        <button
          @click="submitVote"
          :disabled="voting || selectedOptions.length === 0"
          class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-semibold py-3 rounded-xl transition flex items-center justify-center gap-2"
        >
          <span v-if="voting" class="animate-spin">⟳</span>
          {{ voting ? 'Envoi...' : 'Voter' }}
        </button>
      </template>

      <!-- Feedback -->
      <AlertMessage :type="alertType" :message="alertMsg" />

      <!-- Navigation -->
      <div class="flex gap-3 pt-2 border-t border-gray-50">
        <a href="/polls/dashboard"
          class="flex-1 text-center text-sm font-medium text-gray-500 hover:text-gray-700 py-2 rounded-xl hover:bg-gray-50 transition">
          ← Dashboard
        </a>
        <a v-if="!poll.is_draft" :href="`/polls/${props.token}/results`"
          class="flex-1 text-center text-sm font-medium text-green-600 hover:text-green-700 py-2 rounded-xl hover:bg-green-50 transition">
          Voir les résultats →
        </a>
      </div>
    </div>

    <!-- Erreur chargement -->
    <AlertMessage v-else :type="alertType" :message="alertMsg || 'Sondage introuvable.'" />
  </div>
</template>
