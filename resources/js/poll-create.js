import { createApp } from 'vue';
import AppPollCreate from './AppPollCreate.vue';

const el = document.getElementById('app-poll-create');
if (el) {
    createApp(AppPollCreate).mount(el);
}
