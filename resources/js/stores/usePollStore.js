import { ref } from 'vue';
import { useFetchApi } from '@/composables/useFetchApi';

const polls = ref([]);

export function usePollStore() {
  const { fetchApi } = useFetchApi();

  function setPolls(data) {
    polls.value = data;
  }

  async function deletePoll(id) {
    const result = await fetchApi({ url: 'polls/' + id, method: 'DELETE' }); //back
    if (result) {
      polls.value = polls.value.filter(p => p.id !== id); //front
    }
  }
    async function deletePolls(ids) {
        for (const id of ids) {
        const result = await fetchApi({ url: 'polls/' + id, method: 'DELETE' }); //back
        if (result) {
            polls.value = polls.value.filter(p => p.id !== id); //front
        }}
    }


  return { polls, setPolls, deletePoll };
}
