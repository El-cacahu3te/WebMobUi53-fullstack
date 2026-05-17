<script setup>
import { ref } from 'vue'
import { usePollStore } from '@/stores/usePollStore'

const { polls, deletePoll } = usePollStore()

function getPollStatus(poll) {
  if (poll.is_draft) return 'draft'
  if (poll.ends_at && new Date(poll.ends_at) < new Date()) return 'ended'
  return 'active'
}

const statusConfig = {
  draft:  { label: 'Brouillon', classes: 'bg-gray-100 text-gray-600' },
  active: { label: 'Actif',     classes: 'bg-green-100 text-green-700' },
  ended:  { label: 'Terminé',   classes: 'bg-red-100 text-red-600' },
}

function shareLink(token) {
  return `${window.location.origin}/polls/${token}/vote`
}

const copiedId = ref(null)
function copyLink(url, pollId) {
  navigator.clipboard?.writeText(url) ?? fallbackCopy(url)
  copiedId.value = pollId
  setTimeout(() => { copiedId.value = null }, 2000)
}
function fallbackCopy(url) {
  const input = Object.assign(document.createElement('input'), { value: url })
  document.body.appendChild(input)
  input.select()
  document.execCommand('copy')
  document.body.removeChild(input)
}

async function delPoll(id) {
  if (!confirm('Supprimer ce sondage ?')) return
  await deletePoll(id)
}
</script>

<template>
  <!-- État vide -->
  <div v-if="polls.length === 0" class="text-center py-16 text-gray-400">
    <p class="text-4xl mb-3">📊</p>
    <p class="text-lg font-medium text-gray-600">Aucun sondage pour l'instant</p>
    <a href="/polls/create" class="mt-4 inline-block bg-green-600 hover:bg-green-700 text-white font-semibold px-5 py-2.5 rounded-xl transition shadow-sm">
      Créer mon premier sondage
    </a>
  </div>

  <div v-else>
    <!-- ── MOBILE : cards (visible sous sm) ─────────────────────────── -->
    <div class="space-y-4 sm:hidden">
      <div
        v-for="poll in polls"
        :key="poll.id"
        class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 space-y-3"
      >
        <!-- En-tête card -->
        <div class="flex items-start justify-between gap-2">
          <div>
            <p class="font-semibold text-gray-800">{{ poll.title || '(sans titre)' }}</p>
            <p class="text-sm text-gray-500 mt-0.5 line-clamp-2">{{ poll.question }}</p>
          </div>
          <span :class="['shrink-0 text-xs font-semibold px-2 py-1 rounded-full', statusConfig[getPollStatus(poll)].classes]">
            {{ statusConfig[getPollStatus(poll)].label }}
          </span>
        </div>

        <!-- Date de fin -->
        <p v-if="poll.ends_at" class="text-xs text-gray-400">
          Clôture : {{ new Date(poll.ends_at).toLocaleDateString('fr-CH') }}
        </p>

        <!-- Lien de partage -->
        <div v-if="!poll.is_draft" class="flex items-center gap-2 bg-gray-50 rounded-lg px-3 py-2">
          <code class="text-xs text-gray-600 truncate flex-1">{{ shareLink(poll.secret_token) }}</code>
          <button
            @click="copyLink(shareLink(poll.secret_token), poll.id)"
            :class="['text-xs font-medium px-2 py-1 rounded-lg transition', copiedId === poll.id ? 'bg-green-100 text-green-700' : 'text-green-600 hover:bg-green-50']"
          >
            {{ copiedId === poll.id ? '✓ Copié' : 'Copier' }}
          </button>
        </div>
        <p v-else class="text-xs text-gray-400 italic">Lancez le sondage pour obtenir un lien</p>

        <!-- Actions -->
        <div class="flex flex-wrap gap-2 pt-1">
          <a v-if="getPollStatus(poll) === 'active'" :href="`/polls/${poll.secret_token}/vote`"
            class="text-xs bg-green-600 hover:bg-green-700 text-white px-3 py-1.5 rounded-lg transition">Voter</a>
          <a v-if="!poll.is_draft" :href="`/polls/${poll.secret_token}/results`"
            class="text-xs bg-green-100 hover:bg-green-200 text-green-800 px-3 py-1.5 rounded-lg transition">Résultats</a>
          <a :href="`/polls/${poll.id}/edit`"
            class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-3 py-1.5 rounded-lg transition">Éditer</a>
          <button @click="delPoll(poll.id)"
            class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1.5 rounded-lg transition">Supprimer</button>
        </div>
      </div>
    </div>

    <!-- ── DESKTOP : tableau (visible à partir de sm) ────────────────── -->
    <div class="hidden sm:block overflow-x-auto rounded-2xl border border-gray-100 shadow-sm">
      <table class="w-full text-sm text-left">
        <thead class="bg-green-50 text-green-800 text-xs uppercase tracking-wide">
          <tr>
            <th class="px-4 py-3 font-semibold">Titre</th>
            <th class="px-4 py-3 font-semibold">Question</th>
            <th class="px-4 py-3 font-semibold">Statut</th>
            <th class="px-4 py-3 font-semibold">Fin</th>
            <th class="px-4 py-3 font-semibold">Lien</th>
            <th class="px-4 py-3 font-semibold">Actions</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <tr v-for="poll in polls" :key="poll.id" class="bg-white hover:bg-gray-50 transition">
            <td class="px-4 py-3 font-medium text-gray-800">{{ poll.title || '-' }}</td>
            <td class="px-4 py-3 text-gray-500 max-w-xs truncate">{{ poll.question }}</td>
            <td class="px-4 py-3">
              <span :class="['text-xs font-semibold px-2 py-1 rounded-full', statusConfig[getPollStatus(poll)].classes]">
                {{ statusConfig[getPollStatus(poll)].label }}
              </span>
            </td>
            <td class="px-4 py-3 text-gray-400 text-xs">
              {{ poll.ends_at ? new Date(poll.ends_at).toLocaleDateString('fr-CH') : '—' }}
            </td>
            <td class="px-4 py-3">
              <span v-if="poll.is_draft" class="text-gray-300 text-xs italic">—</span>
              <div v-else class="flex items-center gap-1.5">
                <code class="text-xs bg-gray-50 px-2 py-0.5 rounded-lg truncate max-w-[160px] text-gray-600 block">
                  {{ shareLink(poll.secret_token) }}
                </code>
                <button
                  @click="copyLink(shareLink(poll.secret_token), poll.id)"
                  :class="['text-xs px-2 py-1 rounded-lg transition font-medium', copiedId === poll.id ? 'bg-green-100 text-green-700' : 'text-green-600 hover:bg-green-50']"
                >
                  {{ copiedId === poll.id ? '✓' : 'Copier' }}
                </button>
              </div>
            </td>
            <td class="px-4 py-3">
              <div class="flex gap-1.5 flex-wrap">
                <a v-if="getPollStatus(poll) === 'active'" :href="`/polls/${poll.secret_token}/vote`"
                  class="text-xs bg-green-600 hover:bg-green-700 text-white px-2.5 py-1 rounded-lg transition">Voter</a>
                <a v-if="!poll.is_draft" :href="`/polls/${poll.secret_token}/results`"
                  class="text-xs bg-green-100 hover:bg-green-200 text-green-800 px-2.5 py-1 rounded-lg transition">Résultats</a>
                <a :href="`/polls/${poll.id}/edit`"
                  class="text-xs bg-gray-100 hover:bg-gray-200 text-gray-700 px-2.5 py-1 rounded-lg transition">Éditer</a>
                <button @click="delPoll(poll.id)"
                  class="text-xs bg-red-50 hover:bg-red-100 text-red-600 px-2.5 py-1 rounded-lg transition">Supprimer</button>
              </div>
            </td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>
