import { createApp } from 'vue'
import AppPollResults from './AppPollResults.vue'

const el = document.getElementById('poll-results-app')
const token = el.dataset.token

createApp(AppPollResults, { token }).mount(el)
