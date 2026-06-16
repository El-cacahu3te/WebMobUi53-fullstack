import './bootstrap';
import { createApp } from 'vue'
import AppPollResults from './AppPollResults.vue'

const el = document.getElementById('poll-results-app')
if (el) {
  const token = el.dataset.token
  createApp(AppPollResults, { token }).mount(el)
}
