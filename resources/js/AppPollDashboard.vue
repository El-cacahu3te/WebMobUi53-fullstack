<script setup>
import PollTable from './components/PollTable.vue';
import { usePollStore } from '@/stores/usePollStore';

const props = defineProps({
  polls:    { type: Array,  default: () => [] },
  loginUrl: { type: String, default: null },
  username: { type: String, default: null },
});

const { setPolls } = usePollStore();
setPolls(props.polls);
</script>

<template>
  <div class="max-w-5xl mx-auto px-4 py-8">

    <!-- En-tête -->
    <div class="mb-6">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 p-4 sm:p-5 rounded-2xl bg-green-50 border border-green-100 shadow-sm">
        <div class="min-w-0">
          <h1 class="text-2xl font-bold text-gray-900">Mes sondages</h1>
          <p class="text-sm text-green-800/70 mt-0.5">Créez, gérez et suivez vos sondages en temps réel.</p>
        </div>
        <div class="flex gap-2">
          <a
            href="/"
            class="bg-white text-gray-700 px-4 py-2 rounded-xl border border-gray-200 hover:bg-gray-50 text-sm transition"
          >
            Accueil
          </a>
          <a
            href="/polls/create"
            class="bg-green-600 text-white px-4 py-2 rounded-xl hover:bg-green-700 text-sm transition shadow-sm"
          >
            + Nouveau sondage
          </a>
        </div>
      </div>
    </div>

    <PollTable />
  </div>
</template>
