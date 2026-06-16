import './bootstrap';
import { createApp } from 'vue'
import AppPollVote from './AppPollVote.vue'

const el = document.getElementById('poll-vote-app')
if (el) {
  const token = el.dataset.token
  createApp(AppPollVote, { token }).mount(el)
}
