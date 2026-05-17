import { createApp } from 'vue'
import AppPollVote from './AppPollVote.vue'

const el = document.getElementById('poll-vote-app')
const token = el.dataset.token

createApp(AppPollVote, { token }).mount(el)
