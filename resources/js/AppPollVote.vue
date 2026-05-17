<script setup>
import { ref, onMounted, computed } from "vue";
import { useFetchApi } from "./composables/useFetchApi";

const props = defineProps({ token: String });

const { get, post } = useFetchApi("/api/v1");

const poll = ref(null);
const selectedOptions = ref([]);
const loading = ref(true);
const voting = ref(false);
const message = ref("");
const messageType = ref("");

onMounted(async () => {
    try {
        poll.value = await get(`/polls/${props.token}`);
    } catch {
        message.value = "Impossible de charger le sondage.";
        messageType.value = "error";
    } finally {
        loading.value = false;
    }
});

// Sondage terminé si ends_at est dépassé
const isExpired = computed(() => {
    if (!poll.value?.ends_at) return false;
    return new Date(poll.value.ends_at) < new Date();
});

function toggleOption(optionId) {
    if (poll.value.allow_multiple_choices) {
        // Checkbox : toggle dans le tableau
        const idx = selectedOptions.value.indexOf(optionId);
        if (idx === -1) selectedOptions.value.push(optionId);
        else selectedOptions.value.splice(idx, 1);
    } else {
        // Radio : un seul choix
        selectedOptions.value = [optionId];
    }
}

async function submitVote() {
    if (selectedOptions.value.length === 0) {
        message.value = "Veuillez sélectionner au moins une option.";
        messageType.value = "error";
        return;
    }

    voting.value = true;
    message.value = "";

    try {
        await post({
            url: `/polls/${props.token}/vote`,
            data: {
                option_ids: selectedOptions.value,
            },
        });
        message.value = "✓ Vote enregistré ! Redirection vers les résultats...";
        messageType.value = "success";
        // Redirection vers résultats après 2s
        setTimeout(() => {
            window.location.href = `/polls/${props.token}/results`;
        }, 2000);
    } catch (err) {
        const apiMessage = err?.data?.message || err?.statusText || "";

        // Déjà voté (API retourne actuellement "Already voted." en anglais)
        if (
            apiMessage === "Already voted." ||
            apiMessage.toLowerCase().includes("already voted") ||
            apiMessage.toLowerCase().includes("déjà voté")
        ) {
            message.value = "Vous avez déjà voté pour ce sondage.";
        } else if (
            apiMessage.toLowerCase().includes("closed") ||
            apiMessage.toLowerCase().includes("terminé")
        ) {
            message.value = "Ce sondage est terminé.";
        } else if (
            apiMessage.toLowerCase().includes("draft") ||
            apiMessage.toLowerCase().includes("brouillon") ||
            apiMessage.toLowerCase().includes("not available") ||
            apiMessage.toLowerCase().includes("poll has")
        ) {
            message.value = "Ce sondage n'est pas encore lancé.";
        } else {
            message.value =
                apiMessage || "Erreur lors du vote. Veuillez réessayer.";
        }
        messageType.value = "error";
    }
}
</script>
<template>
    <div class="max-w-xl mx-auto mt-8 px-4">
        <!-- Chargement -->
        <div v-if="loading" class="text-center text-gray-500 py-12">
            Chargement du sondage...
        </div>

        <div v-else-if="poll" class="bg-white rounded-lg shadow p-6">
            <h1 class="text-2xl font-bold mb-2">{{ poll.title }}</h1>
            <p class="text-gray-600 mb-6">{{ poll.question }}</p>

            <!-- NOUVEAU : sondage brouillon -->
            <div v-if="poll.is_draft" class="bg-yellow-50 border border-yellow-200 text-yellow-800 rounded p-4 mb-4">
                Ce sondage n'est pas encore lancé.
            </div>

            <!-- Sondage terminé -->
            <div v-else-if="isExpired" class="bg-red-50 border border-red-200 text-red-700 rounded p-4 mb-4">
                Ce sondage est terminé. Vous ne pouvez plus voter.
                <a :href="`/polls/${props.token}/results`" class="block mt-2 text-red-700 underline font-medium">
                    Voir les résultats →
                </a>
            </div>
            <!-- Formulaire de vote -->
            <div v-else class="space-y-3">
                <p class="text-sm text-gray-500 mb-3">
                    {{
                        poll.allow_multiple_choices
                            ? "Plusieurs choix possibles"
                            : "Un seul choix possible"
                    }}
                </p>

                <div v-for="option in poll.options" :key="option.id" @click="toggleOption(option.id)"
                    class="flex items-center gap-3 p-3 border rounded-lg cursor-pointer hover:bg-gray-50 transition"
                    :class="selectedOptions.includes(option.id)
                            ? 'border-blue-500 bg-blue-50'
                            : 'border-gray-200'
                        ">
                    <!-- Radio ou Checkbox selon le type -->
                    <input :type="poll.allow_multiple_choices ? 'checkbox' : 'radio'
                        " :checked="selectedOptions.includes(option.id)" class="accent-blue-600" readonly />
                    <span>{{ option.label }}</span>
                </div>

                <!-- Bouton voter -->
                <button @click="submitVote" :disabled="voting || selectedOptions.length === 0"
                    class="w-full mt-4 bg-blue-600 text-white py-2 rounded-lg font-semibold hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed transition">
                    {{ voting ? "Envoi..." : "Voter" }}
                </button>
                <!-- Navigation -->
                <div class="mt-6 flex gap-3">
                    <!-- Retour dashboard -->
                    <a href="/polls/dashboard"
                        class="flex-1 text-center bg-gray-500 text-white py-2 rounded-lg hover:bg-gray-600 transition text-sm">
                        Dashboard
                    </a>

                    <!-- Résultats -->
                    <a v-if="!poll.is_draft" :href="`/polls/${props.token}/results`"
                        class="flex-1 text-center bg-purple-500 text-white py-2 rounded-lg hover:bg-purple-600 transition text-sm">
                        Voir les résultats →
                    </a>
                </div>
            </div>

            <!-- Message feedback -->
            <div v-if="message" class="mt-4 p-3 rounded text-sm" :class="messageType === 'success'
                    ? 'bg-green-100 text-green-700'
                    : 'bg-red-100 text-red-700'
                ">
                {{ message }}
            </div>
        </div>

        <!-- Erreur chargement -->
        <div v-else class="bg-red-50 text-red-700 rounded p-4">
            {{ message || "Sondage introuvable." }}
        </div>
    </div>
</template>
