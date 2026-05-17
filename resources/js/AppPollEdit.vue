<script setup>
import PollForm from './components/PollForm.vue';

const props = defineProps({
  poll: { type: Object, required: true }
});
</script>

<template>
  <PollForm mode="edit" :initial-poll="props.poll" />
</template>
