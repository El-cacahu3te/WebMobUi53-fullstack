<script setup>
import { computed, ref } from 'vue';
import { usePollStore } from '@/stores/usePollStore';

const { polls, deletePoll } = usePollStore();

// Calcule le statut lisible d'un sondage
function getPollStatus(poll) {
    if (poll.is_draft) return 'draft';
    if (poll.ends_at && new Date(poll.ends_at) < new Date()) return 'ended';
    return 'active';
}

const statusLabels = {
    draft: { label: 'Brouillon', classes: 'bg-gray-100 text-gray-600' },
    active: { label: 'Actif', classes: 'bg-green-100 text-green-700' },
    ended: { label: 'Terminé', classes: 'bg-red-100 text-red-600' },
};

// Construit le lien de partage à partir du token
function shareLink(token) {
    return `${window.location.origin}/polls/${token}/vote`;
}

// Stocke l'id du sondage dont le lien vient d'être copié
const copiedId = ref(null);

function copyLink(url, pollId) {
    if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(url);
    } else {
        const input = document.createElement('input');
        input.value = url;
        document.body.appendChild(input);
        input.select();
        document.execCommand('copy');
        document.body.removeChild(input);
    }

    // Affiche "Copié !" pendant 2 secondes pour ce sondage précis
    copiedId.value = pollId;
    setTimeout(() => { copiedId.value = null; }, 2000);
}

function goToResults(token) {
    window.location.href = `/polls/${token}/results`;
}

function goToVote(token) {
    window.location.href = `/polls/${token}/vote`;
}

async function delPoll(id) {
    if (!confirm('Supprimer ce sondage ?')) return;
    await deletePoll(id);
}
</script>

<template>
    <!-- État vide -->
    <div v-if="polls.length === 0" class="text-center py-16 text-gray-500">
        <p class="text-lg">Aucun sondage pour l'instant.</p>
        <a href="/polls/create" class="mt-4 inline-block bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">
            Créer mon premier sondage
        </a>
    </div>

    <!-- Tableau -->
    <div v-else class="overflow-x-auto">
        <table class="w-full border-collapse text-left text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="border px-3 py-2">Titre</th>
                    <th class="border px-3 py-2">Question</th>
                    <th class="border px-3 py-2">Statut</th>
                    <th class="border px-3 py-2">Fin</th>
                    <th class="border px-3 py-2">Lien de partage</th>
                    <th class="border px-3 py-2">Actions</th>
                </tr>
            </thead>
            <tbody>
                <tr v-for="poll in polls" :key="poll.id" class="hover:bg-gray-50">

                    <!-- Titre -->
                    <td class="border px-3 py-2 font-medium">{{ poll.title || '-' }}</td>

                    <!-- Question (tronquée) -->
                    <td class="border px-3 py-2 text-gray-600 max-w-xs truncate">{{ poll.question }}</td>

                    <!-- Badge statut -->
                    <td class="border px-3 py-2">
                        <span
                            :class="['text-xs font-semibold px-2 py-1 rounded-full', statusLabels[getPollStatus(poll)].classes]">
                            {{ statusLabels[getPollStatus(poll)].label }}
                        </span>
                    </td>

                    <!-- Date de fin -->
                    <td class="border px-3 py-2 text-gray-500">
                        {{ poll.ends_at ? new Date(poll.ends_at).toLocaleDateString('fr-CH') : '-' }}
                    </td>

                    <!-- Lien de partage : seulement si pas brouillon -->
                    <td class="border px-3 py-2">
                        <span v-if="poll.is_draft" class="text-gray-400 text-xs italic">
                            Lancez le sondage pour obtenir un lien
                        </span>
                        <div v-else class="flex items-center gap-2">
                            <code class="text-xs bg-gray-100 px-1 rounded truncate max-w-[180px] block">
                {{ shareLink(poll.secret_token) }}
              </code>
                            <!-- Copie dans le presse-papier -->
                            <button @click="copyLink(shareLink(poll.secret_token), poll.id)"
                                class="text-xs px-2 py-1 rounded transition-colors" :class="copiedId === poll.id
                                    ? 'bg-green-100 text-green-700'
                                    : 'text-blue-600 hover:underline'">
                                {{ copiedId === poll.id ? '✓ Copié !' : 'Copier' }}
                            </button>

                        </div>
                    </td>
                    <!-- Actions -->
                    <td class="border px-3 py-2">
                        <div class="flex gap-2 flex-wrap">
                            <!-- Voter : seulement si actif -->
                            <button v-if="getPollStatus(poll) === 'active'" @click="goToVote(poll.secret_token)"
                                class="text-xs bg-green-500 text-white px-2 py-1 rounded hover:bg-green-600">
                                Voter
                            </button>

                            <!-- Résultats : seulement si non brouillon -->
                            <a v-if="!poll.is_draft" :href="`/polls/${poll.secret_token}/results`"
                                class="text-xs bg-purple-500 text-white px-2 py-1 rounded hover:bg-purple-600">
                                Résultats
                            </a>

                            <!-- Éditer et Supprimer : inchangés -->
                            <a :href="`/polls/${poll.id}/edit`"
                                class="text-xs bg-blue-500 text-white px-2 py-1 rounded hover:bg-blue-600">
                                Éditer
                            </a>
                            <button @click="delPoll(poll.id)"
                                class="text-xs bg-red-500 text-white px-2 py-1 rounded hover:bg-red-600">
                                Supprimer
                            </button>
                        </div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</template>
