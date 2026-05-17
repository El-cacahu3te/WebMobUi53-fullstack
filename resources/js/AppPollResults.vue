<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useFetchApi } from './composables/useFetchApi'

const props = defineProps({ token: String })

const { get } = useFetchApi('/api/v1')

const results = ref(null)
const loading = ref(true)
const message = ref('')
let pollingInterval = null

// Total des votes pour calculer les pourcentages
const totalVotes = computed(() =>
    results.value?.options?.reduce((sum, o) => sum + (o.votes || 0), 0) || 0
)


function getPercentage(count) {
    if (totalVotes.value === 0) return 0
    return Math.round((count / totalVotes.value) * 100)
}
const privateError = ref(false)
async function fetchResults() {
    try {
        results.value = await get({ url: `/polls/${props.token}/results` })
        privateError.value = false
    } catch (err) {
        // Distingue 403 (résultats privés) des autres erreurs
        if (err?.status === 403) {
            privateError.value = true
        } else {
            message.value = 'Impossible de charger les résultats.'
        }
    } finally {
        loading.value = false
    }
}

const isExpired = computed(() => {
    if (!results.value?.ends_at) return false
    return new Date(results.value.ends_at) < new Date()
})

onMounted(() => {
    fetchResults()
    pollingInterval = setInterval(() => {
        // Arrêt si expiré OU si résultats privés
        if (!isExpired.value && !privateError.value) fetchResults()
    }, 5000)
})


// Nettoyage de l'intervalle quand le composant est détruit
onUnmounted(() => {
    clearInterval(pollingInterval)
})
</script>

<template>
    <div class="max-w-xl mx-auto mt-8 px-4">

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
            <!-- En-tête -->
            <h1 class="text-2xl font-bold mb-1">{{ results.title }}</h1>
            <p class="text-gray-600 mb-2">{{ results.question }}</p>

            <!-- Badge statut -->
            <span v-if="isExpired"
                class="inline-block bg-red-100 text-red-700 text-xs font-semibold px-2 py-1 rounded mb-6">
                Terminé
            </span>
            <span v-else class="inline-block bg-green-100 text-green-700 text-xs font-semibold px-2 py-1 rounded mb-6">
                Actif — résultats en direct
            </span>

            <!-- Total votes -->
            <p class="text-sm text-gray-500 mb-4">
                Total : {{ totalVotes }} vote{{ totalVotes > 1 ? 's' : '' }}
            </p>

            <!-- Barres de résultats -->
            <div class="space-y-4">
                <div v-for="option in results.options" :key="option.id">
                    <div class="flex justify-between text-sm mb-1">
                        <span class="font-medium">{{ option.label }}</span>
                        <span class="text-gray-500">
                            {{ option.votes || 0 }} ({{ getPercentage(option.votes || 0) }}%)
                        </span>
                    </div>
                    <div class="w-full bg-gray-200 rounded-full h-4 overflow-hidden">
                        <div class="bg-blue-600 h-full rounded-full transition-all duration-500"
                            :style="{ width: getPercentage(option.votes || 0) + '%' }"></div>
                    </div>
                </div>
            </div>

            <!-- Retour dashboard -->
            <div class="mt-8">
                <a href="/polls/dashboard"
                    class="block text-center bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition">
                    Retour au dashboard
                </a>
            </div>
        </div>

        <!-- Erreur -->
        <div v-else class="bg-red-50 text-red-700 rounded p-4">
            {{ message || 'Résultats introuvables.' }}
        </div>

    </div>
</template>
