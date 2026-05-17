<script setup>
import { ref, computed } from 'vue'
import { useFetchApi } from './composables/useFetchApi'
import { usePolling } from './composables/usePolling'

const props = defineProps({ token: String })

const { get } = useFetchApi('/api/v1')

const results = ref(null)
const loading = ref(true)
const error = ref('')
const privateError = ref(false)
// Indicateur visuel de rafraîchissement (clignote à chaque poll)
const refreshing = ref(false)

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

async function fetchResults() {
    // Ne pas re-fetcher si on sait déjà que c'est privé ou expiré
    if (privateError.value || isExpired.value) return

    // Pas de loading spinner après le premier chargement, juste un refreshing discret
    if (results.value) refreshing.value = true

    try {
        results.value = await get({ url: `/polls/${props.token}/results` })
        error.value = ''
    } catch (err) {
        if (err?.status === 403) {
            privateError.value = true
        } else {
            error.value = 'Impossible de charger les résultats.'
        }
    } finally {
        loading.value = false
        refreshing.value = false
    }
}

// usePolling remplace le setInterval/clearInterval manuel
// immediate=true → appel direct au mount, pas besoin de onMounted séparé
usePolling(fetchResults, 5000)
</script>

<template>
    <div class="max-w-xl mx-auto mt-8 px-4">

        <!-- Chargement initial -->
        <div v-if="loading" class="text-center text-gray-500 py-12">
            Chargement des résultats...
        </div>

        <!-- Résultats privés (403) -->
        <div v-else-if="privateError"
            class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded p-6 text-center">
            <p class="font-semibold text-lg mb-2">Résultats privés</p>
            <p class="text-sm">Le créateur n'a pas rendu les résultats publics.</p>
            <a href="/polls/dashboard" class="mt-4 inline-block text-yellow-800 underline text-sm">
                Retour au dashboard
            </a>
        </div>

        <div v-else-if="results" class="bg-white rounded-lg shadow p-6">

            <!-- En-tête avec indicateur de refresh -->
            <div class="flex items-start justify-between mb-1">
                <h1 class="text-2xl font-bold">{{ results.title }}</h1>
                <!-- Point vert animé = données en direct -->
                <span v-if="!isExpired" class="flex items-center gap-1 text-xs text-green-600 mt-1">
                    <span
                        class="inline-block w-2 h-2 rounded-full bg-green-500"
                        :class="refreshing ? 'opacity-50' : 'animate-pulse'"
                    ></span>
                    En direct
                </span>
            </div>

            <p class="text-gray-600 mb-2">{{ results.question }}</p>

            <!-- Badge statut -->
            <span v-if="isExpired"
                class="inline-block bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded mb-4">
                Terminé
            </span>
            <span v-else
                class="inline-block bg-green-100 text-green-700 text-xs font-semibold px-2 py-1 rounded mb-4">
                Actif
            </span>

            <!-- Total votes -->
            <p class="text-sm text-gray-500 mb-6">
                Total : <span class="font-semibold">{{ totalVotes }}</span> vote{{ totalVotes > 1 ? 's' : '' }}
            </p>

            <!-- Barres de résultats -->
            <div class="space-y-5">
                <div v-for="option in results.options" :key="option.id">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-medium">{{ option.label }}</span>
                        <span class="text-gray-500 tabular-nums">
                            {{ option.votes || 0 }} vote{{ (option.votes || 0) > 1 ? 's' : '' }}
                            — {{ getPercentage(option.votes || 0) }}%
                        </span>
                    </div>
                    <!-- Barre de progression CSS pure, pas de lib externe -->
                    <div class="w-full bg-gray-100 rounded-full h-5 overflow-hidden">
                        <div
                            class="h-full rounded-full transition-all duration-700 ease-out"
                            :class="getPercentage(option.votes || 0) > 0 ? 'bg-blue-500' : 'bg-gray-200'"
                            :style="{ width: getPercentage(option.votes || 0) + '%' }"
                        >
                        </div>
                    </div>
                </div>
            </div>

            <!-- Date de fin si active -->
            <p v-if="results.ends_at && !isExpired" class="text-xs text-gray-400 mt-6">
                Clôture le {{ new Date(results.ends_at).toLocaleString('fr-FR') }}
            </p>

            <!-- Actions -->
            <div class="mt-6 flex gap-3">
                <a href="/polls/dashboard"
                    class="flex-1 text-center bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition text-sm">
                    Dashboard
                </a>
                <!-- Suppression du bouton « Voter » : après un vote réussi on est redirigé ici.
                     Si le composant affiche encore un CTA, l'utilisateur peut revoter et/ou tomber sur une route 404. -->

            </div>
        </div>

        <!-- Erreur réseau -->
        <div v-else class="bg-red-50 text-red-700 rounded p-4 text-center">
            {{ error || 'Résultats introuvables.' }}
        </div>

    </div>
</template>
