import { createApp } from 'vue';
import AppPollEdit from './AppPollEdit.vue';

const el = document.getElementById('app-poll-edit');
if (el) {
    const props = JSON.parse(el.dataset.props || '{}');
    createApp(AppPollEdit, props).mount(el);
}
