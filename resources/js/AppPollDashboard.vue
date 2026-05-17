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
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Mes sondages</h1>
      <a
      href="/"
      class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 text-sm"
    >
      Accueil
    </a>
      <a
        href="/polls/create"
        class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700 text-sm"
      >
        + Nouveau sondage
      </a>
    </div>

    <PollTable />
  </div>
</template>
