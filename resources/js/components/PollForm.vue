<script setup>
import { ref, reactive } from 'vue';
import { useFetchApi } from '../composables/useFetchApi';

const props = defineProps({
  mode: { type: String, required: true },
  initialPoll: { type: Object, default: null }
});

const { fetchApi } = useFetchApi('/api/v1');

const form = reactive({
  title: props.initialPoll?.title ?? '',
  question: props.initialPoll?.question ?? '',
  is_draft: props.initialPoll?.is_draft ?? false,
  allow_multiple_choices: props.initialPoll?.allow_multiple_choices ?? false,
  allow_vote_change: props.initialPoll?.allow_vote_change ?? false,
  results_public: props.initialPoll?.results_public ?? false,
  duration: props.initialPoll?.duration ? Math.round(props.initialPoll.duration / 60) : '',
});

const options = ref(
  props.initialPoll?.options?.map(o => o.label) ?? ['', '']
);

const error = ref(null);
const fieldErrors = ref({}); // erreurs par champ venant du 422 Laravel

function addOption() {
  options.value.push('');
}

function removeOption(index) {
  if (options.value.length > 2) options.value.splice(index, 1);
}

async function submit() {
  error.value = null;
  fieldErrors.value = {};

  const payload = {
    ...form,
    options: options.value.filter(o => o.trim() !== ''),
    duration: form.duration ? parseInt(form.duration) * 60 : null,
  };

  try {
    if (props.mode === 'create') {
      await fetchApi({ url: '/polls', data: payload, method: 'POST' });
    } else {
      await fetchApi({ url: `/polls/${props.initialPoll.id}`, data: payload, method: 'PUT' });
    }
    window.location.href = '/polls/dashboard';
  } catch (err) {
    if (err.status === 422 && err.data?.errors) {
      // Laravel retourne { errors: { question: ['Le champ est requis.'], ... } }
      fieldErrors.value = err.data.errors;
      error.value = 'Veuillez corriger les erreurs ci-dessous.';
    } else {
      error.value = err.data?.message ?? 'Une erreur est survenue.';
    }
  }
}
</script>

<template>
  <div class="max-w-xl mx-auto p-6">
    <h1 class="text-2xl font-bold mb-6">
      {{ mode === 'create' ? 'Créer un sondage' : 'Modifier le sondage' }}
    </h1>

    <div v-if="error" class="bg-red-100 text-red-700 p-3 rounded mb-4">
      {{ error }}
    </div>

    <form @submit.prevent="submit" class="space-y-4">

      <div>
        <label class="block text-sm font-medium mb-1">Titre</label>
        <input v-model="form.title" type="text"
          class="w-full border rounded px-3 py-2" />
        <!-- Affiche le premier message d'erreur du champ si présent -->
        <p v-if="fieldErrors.title" class="text-red-600 text-sm mt-1">
          {{ fieldErrors.title[0] }}
        </p>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1">Question</label>
        <input v-model="form.question" type="text"
          class="w-full border rounded px-3 py-2" />
        <p v-if="fieldErrors.question" class="text-red-600 text-sm mt-1">
          {{ fieldErrors.question[0] }}
        </p>
      </div>

      <!-- Options dynamiques -->
      <div>
        <label class="block text-sm font-medium mb-1">Options</label>
        <div v-for="(option, index) in options" :key="index" class="flex gap-2 mb-2">
          <input v-model="options[index]" type="text" :placeholder="`Option ${index + 1}`"
            class="flex-1 border rounded px-3 py-2" />
          <button type="button" @click="removeOption(index)"
            :disabled="options.length <= 2"
            class="text-red-500 disabled:opacity-30">✕</button>
        </div>
        <!-- Erreur sur le tableau options (ex: "minimum 2 options requises") -->
        <p v-if="fieldErrors.options" class="text-red-600 text-sm mt-1">
          {{ fieldErrors.options[0] }}
        </p>
        <button type="button" @click="addOption"
          class="text-sm text-blue-600 hover:underline">+ Ajouter une option</button>
      </div>

      <!-- Paramètres -->
      <div class="space-y-2">
        <label class="flex items-center gap-2">
          <input type="checkbox" v-model="form.allow_multiple_choices" />
          Choix multiples autorisés
        </label>
        <label class="flex items-center gap-2">
          <input type="checkbox" v-model="form.allow_vote_change" />
          Modification du vote autorisée
        </label>
        <label class="flex items-center gap-2">
          <input type="checkbox" v-model="form.results_public" />
          Résultats publics
        </label>
        <label class="flex items-center gap-2">
          <input type="checkbox" v-model="form.is_draft" />
          Brouillon (coché = brouillon, décoché = actif)
        </label>
      </div>

      <div v-if="!form.is_draft">
        <label class="block text-sm font-medium mb-1">Durée (minutes, optionnel)</label>
        <input v-model="form.duration" type="number" min="0"
          class="w-full border rounded px-3 py-2" />
        <p v-if="fieldErrors.duration" class="text-red-600 text-sm mt-1">
          {{ fieldErrors.duration[0] }}
        </p>
      </div>
      <div v-else class="text-sm text-gray-500">
        La durée ne s’applique que si le sondage est actif.
      </div>

      <button type="submit"
        class="w-full bg-blue-600 text-white py-2 rounded hover:bg-blue-700">
        {{ mode === 'create' ? 'Créer' : 'Enregistrer' }}
      </button>

    </form>
  </div>
</template>
