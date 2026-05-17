<script setup>
import { ref, computed } from 'vue'
import { useFetchApi } from './composables/useFetchApi'

import { usePolling } from './composables/usePolling'


import AlertMessage from './components/AlertMessage.vue'


const props = defineProps({ token: String })
const { get } = useFetchApi('/api/v1')

const results      = ref(null)
const loading      = ref(true)
const alertMsg     = ref('')
const alertType    = ref('error')
const privateError = ref(false)
const refreshing   = ref(false)

const totalVotes = computed(() =>
  results.value?.options?.reduce((sum, o) => sum + (o.votes || 0), 0) || 0
)

function getPercentage(count) {
  if (totalVotes.value === 0) return 0
  return Math.round((count / totalVotes.value) * 100)
}

const isExpired = computed(() => {
  if (!results.value?.ends_at) return false
  return new Date(results.value.ends_at) < new Date()
})

const barColors = ['bg-green-500', 'bg-teal-500', 'bg-emerald-400', 'bg-green-400', 'bg-teal-400']

async function fetchResults() {
  if (privateError.value) return

  if (results.value) refreshing.value = true

  try {
    results.value  = await get(`/polls/${props.token}/results`)
    alertMsg.value = ''
  } catch (err) {
    if (err?.status === 403) {
      privateError.value = true
    } else {
      alertMsg.value  = 'Impossible de charger les résultats.'
      alertType.value = 'error'
    }
  } finally {
    loading.value    = false
    refreshing.value = false
  }
}

usePolling(fetchResults, 5000, true)
</script>

<template>
  <div class="max-w-lg mx-auto px-4 py-8">

    <!-- 🔄 Chargement initial -->
    <div v-if="loading" class="flex flex-col items-center py-16 text-gray-400 gap-3">
      <span class="text-3xl animate-spin">⟳</span>
      <p>Chargement des résultats...</p>
    </div>

    <!-- 🔒 Résultats privés (403) -->
    <div v-else-if="privateError" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-8 text-center space-y-3">
      <p class="text-4xl">🔒</p>
      <p class="font-semibold text-gray-800">Résultats privés</p>
      <p class="text-sm text-gray-500">Le créateur n'a pas rendu ces résultats publics.</p>
      <a href="/polls/dashboard" class="inline-block mt-2 text-sm text-green-600 hover:underline font-medium">
        ← Retour au dashboard
      </a>
    </div>

    <!-- 📊 Résultats normaux -->
    <div v-else-if="results" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-5">

      <!-- En-tête + badge statut -->
      <div class="flex items-start justify-between gap-2">
        <div>
          <h1 class="text-xl font-bold text-gray-800">{{ results.title }}</h1>
          <p class="text-gray-500 text-sm mt-0.5">{{ results.question }}</p>
        </div>
        <!-- Badge statut (live/terminé) -->
        <span v-if="isExpired" class="shrink-0 text-xs font-semibold px-2.5 py-1 rounded-full bg-red-100 text-red-600">
          Terminé
        </span>
        <span v-else class="shrink-0 flex items-center gap-1.5 text-xs font-semibold text-green-600 bg-green-50 px-2.5 py-1 rounded-full">
          <span
            class="w-1.5 h-1.5 rounded-full bg-green-500 transition-opacity"
            :class="refreshing ? 'opacity-30' : 'animate-pulse'"
          />
          {{ refreshing ? 'Actualisation...' : 'En direct' }}
        </span>
      </div>

      <!-- Total votes -->
      <div class="text-sm text-gray-400 bg-gray-50 rounded-lg p-3">
        <span class="font-semibold text-gray-700">{{ totalVotes }}</span>
        vote{{ totalVotes > 1 ? 's' : '' }} au total
      </div>

      <!-- 📊 Graphique barres -->
      <div class="space-y-4">
        <div v-for="(option, i) in results.options" :key="option.id">
          <div class="flex justify-between items-baseline text-sm mb-2">
            <span class="font-medium text-gray-700">{{ option.label }}</span>
            <div class="text-right text-xs text-gray-500">
              <span class="font-semibold text-gray-700">{{ option.votes || 0 }}</span>
              <span class="mx-1">·</span>
              <span class="font-semibold text-gray-700">{{ getPercentage(option.votes || 0) }}%</span>
            </div>
          </div>

          <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
            <div
              :class="[
                'h-full rounded-full transition-all duration-500 ease-out',
                getPercentage(option.votes || 0) > 0
                  ? barColors[i % barColors.length]
                  : 'bg-gray-200'
              ]"
              :style="{ width: getPercentage(option.votes || 0) + '%' }"
            />
          </div>
        </div>
      </div>

      <!-- Date de fin -->
      <div v-if="results.ends_at" class="text-xs text-gray-400 pt-3 border-t border-gray-100">
        <span v-if="isExpired" class="text-red-500">
          ⏱️ Clôturé le {{ new Date(results.ends_at).toLocaleString('fr-FR') }}
        </span>
        <span v-else class="text-green-600">
          ⏱️ Se clôturera le {{ new Date(results.ends_at).toLocaleString('fr-FR') }}
        </span>
      </div>

      <!-- Navigation -->
      <div class="pt-3 border-t border-gray-100">
        <a
          href="/polls/dashboard"
          class="inline-flex items-center justify-center w-full text-sm font-semibold text-green-700 bg-green-50 hover:bg-green-100 transition rounded-xl px-4 py-2.5 border border-green-100"
        >
          ← Retour au dashboard
        </a>
      </div>
    </div>

    <!-- ❌ Erreur réseau -->
    <AlertMessage v-else :type="alertType" :message="alertMsg || 'Résultats introuvables.'" />
  </div>
</template>
