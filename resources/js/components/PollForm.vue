<script setup>
import { ref, reactive, computed } from 'vue'
import { useFetchApi } from '../composables/useFetchApi'
import AlertMessage from './AlertMessage.vue'

const props = defineProps({
  mode: { type: String, required: true },
  initialPoll: { type: Object, default: null }
})

const { fetchApi } = useFetchApi()

// Règle métier : à la création, sondage ACTIF par défaut (is_draft = false)
// À l'édition, respecte l'état existant
const isDraftInitial = props.mode === 'create'
  ? false  // ← Sondage actif immédiatement
  : (props.initialPoll?.is_draft ?? false)

const form = reactive({
  title:                  props.initialPoll?.title ?? '',
  question:               props.initialPoll?.question ?? '',
  is_draft:               isDraftInitial,
  allow_multiple_choices: props.initialPoll?.allow_multiple_choices ?? false,
  allow_vote_change:      props.initialPoll?.allow_vote_change ?? false,
  results_public:         props.initialPoll?.results_public ?? false,
  duration:               props.initialPoll?.duration ? Math.round(props.initialPoll.duration / 86400) : '',
})

const options    = ref(props.initialPoll?.options?.map(o => o.label) ?? ['', ''])
const loading    = ref(false)
const alertMsg   = ref('')
const alertType  = ref('error')
const alertErrors = ref([])
const fieldErrors = ref({})

// Configuration des toggles (labels + descriptions)
const toggles = computed(() => ({
  allow_multiple_choices: {
    label: 'Choix multiples',
    hint: 'Les utilisateurs peuvent cocher plusieurs options'
  },
  allow_vote_change: {
    label: 'Modification du vote',
    hint: 'Les utilisateurs peuvent modifier leur vote'
  },
  results_public: {
    label: 'Résultats publics',
    hint: 'Afficher les résultats à tous (sinon réservés au créateur)'
  },
  is_draft: {
    label: 'Brouillon',
    hint: 'Si coché : sondage non votable. Décochée : sondage actif.'
  },
}))

function addOption() {
  options.value.push('')
}

function removeOption(i) {
  if (options.value.length > 2) options.value.splice(i, 1)
}

// Helper : classes conditionnelles sur un champ selon s'il a une erreur
function fieldClass(hasError) {
  return [
    'w-full rounded-xl px-3 py-2 border text-sm transition focus:outline-none focus:ring-2',
    hasError
      ? 'border-red-400 focus:ring-red-300'
      : 'border-gray-200 focus:ring-green-300 focus:border-green-400',
  ]
}

async function submit() {
  alertMsg.value    = ''
  alertErrors.value = []
  fieldErrors.value = {}
  loading.value     = true

  const payload = {
    ...form,
    options:  options.value.filter(o => o.trim() !== ''),
    duration: form.duration ? form.duration * 86400 : null,
  }

  try {
    if (props.mode === 'create') {
      await fetchApi({ url: '/polls', data: payload, method: 'POST' })
    } else {
      await fetchApi({ url: `/polls/${props.initialPoll.id}`, data: payload, method: 'PUT' })
    }
    window.location.href = '/polls/dashboard'
  } catch (err) {
    if (err.status === 422 && err.data?.errors) {
      fieldErrors.value = err.data.errors
      alertErrors.value = Object.values(err.data.errors).flat()
      alertMsg.value    = 'Veuillez corriger les erreurs ci-dessous.'
    } else {
      alertMsg.value = err.data?.message ?? 'Une erreur est survenue.'
    }
    alertType.value = 'error'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="max-w-xl mx-auto px-4 py-8">
    <!-- Carte principale -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 space-y-6">

      <div class="p-4 sm:p-5 rounded-2xl bg-green-50 border border-green-100 shadow-sm">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h1 class="text-xl font-bold text-gray-900">
              {{ mode === 'create' ? '✦ Créer un sondage' : '✎ Modifier le sondage' }}
            </h1>
            <p class="text-sm text-green-800/70 mt-0.5">
              Paramétrez vos options et contrôlez l’accès aux résultats.
            </p>
          </div>
          <span
            v-if="form.is_draft"
            class="shrink-0 inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold bg-green-100 text-green-800"
          >
            Brouillon
          </span>
        </div>
      </div>


      <!-- Feedback erreur global -->
      <AlertMessage
        v-if="alertMsg || alertErrors.length"
        :type="alertType"
        :message="alertErrors.length ? 'Veuillez corriger les erreurs suivantes :' : alertMsg"
        :errors="alertErrors"
      />

      <form @submit.prevent="submit" class="space-y-5">

        <!-- Titre -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Titre</label>
          <input
            v-model="form.title"
            type="text"
            :class="fieldClass(fieldErrors.title)"
            placeholder="Mon sondage"
          />
          <p v-if="fieldErrors.title" class="text-red-500 text-xs mt-1">
            {{ fieldErrors.title[0] }}
          </p>
        </div>

        <!-- Question -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Question</label>
          <input
            v-model="form.question"
            type="text"
            :class="fieldClass(fieldErrors.question)"
            placeholder="Quelle est votre question ?"
          />
          <p v-if="fieldErrors.question" class="text-red-500 text-xs mt-1">
            {{ fieldErrors.question[0] }}
          </p>
        </div>

        <!-- Options -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-2">Options de réponse</label>
          <div class="space-y-2">
            <div v-for="(option, i) in options" :key="i" class="flex gap-2 items-center">
              <input
                v-model="options[i]"
                type="text"
                :placeholder="`Option ${i + 1}`"
                :class="fieldClass(false)"
              />
              <button
                type="button"
                @click="removeOption(i)"
                :disabled="options.length <= 2"
                class="shrink-0 w-8 h-8 flex items-center justify-center rounded-lg text-red-400 hover:bg-red-50 disabled:opacity-20 transition"
              >
                ✕
              </button>
            </div>
          </div>
          <p v-if="fieldErrors.options" class="text-red-500 text-xs mt-1">
            {{ fieldErrors.options[0] }}
          </p>
          <button
            type="button"
            @click="addOption"
            class="mt-2 text-sm text-green-600 hover:text-green-700 font-medium flex items-center gap-1"
          >
            <span class="text-lg leading-none">+</span> Ajouter une option
          </button>
        </div>

        <!-- Paramètres — section grisée -->
        <div class="bg-gray-50 rounded-xl p-4 space-y-3">
          <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-3">
            Paramètres
          </p>

          <div v-for="(cfg, key) in toggles" :key="key" class="space-y-1">
            <label class="flex items-center justify-between gap-4 cursor-pointer">
              <span class="text-sm text-gray-700 font-medium">{{ cfg.label }}</span>
              <!-- Toggle switch CSS pur -->
              <div
                @click="form[key] = !form[key]"
                :class="[
                  'relative w-10 h-5 rounded-full transition-colors shrink-0',
                  form[key] ? 'bg-green-500' : 'bg-gray-300'
                ]"
              >
                <span
                  :class="[
                    'absolute top-0.5 left-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform',
                    form[key] ? 'translate-x-5' : ''
                  ]"
                ></span>
              </div>
            </label>
            <p class="text-xs text-gray-500 ml-0">{{ cfg.hint }}</p>
          </div>
        </div>

        <!-- Durée -->
        <div>
          <label class="block text-sm font-medium text-gray-700 mb-1">Durée (jours, optionnel)</label>
          <input
            v-model.number="form.duration"
            type="number"
            min="1"
            :disabled="form.is_draft"
            :class="[fieldClass(fieldErrors.duration), form.is_draft ? 'opacity-50 cursor-not-allowed' : '']"
            placeholder="Ex : 7"
          />
          <p class="text-xs text-gray-500 mt-1">
            {{ form.is_draft
              ? '⚠️ Sera appliquée uniquement quand le sondage sera ACTIF (débloquer en décochant Brouillon).'
              : '✓ Le sondage se clôturera automatiquement après ce délai.'
            }}
          </p>
          <p v-if="fieldErrors.duration" class="text-red-500 text-xs mt-1">
            {{ fieldErrors.duration[0] }}
          </p>
        </div>

        <!-- Submit -->
        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-green-600 hover:bg-green-700 disabled:opacity-60 disabled:cursor-not-allowed text-white font-semibold py-3 rounded-xl transition shadow-sm flex items-center justify-center gap-2"
        >
          <span v-if="loading" class="animate-spin inline-block">⟳</span>
          {{ loading
            ? 'Enregistrement...'
            : (mode === 'create' ? 'Créer le sondage' : 'Enregistrer')
          }}
        </button>

      </form>
    </div>
  </div>
</template>
