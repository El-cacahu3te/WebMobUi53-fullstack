// resources/js/composables/usePolling.js
import { onMounted, onUnmounted } from 'vue';

/**
 * Polling composable
 * @param {Function} fn - Fonction appelée à chaque intervalle
 * @param {number} [interval=5000] - Intervalle en ms
 * @param {boolean} [immediate=true] - Appel immédiat au mount (évite d'attendre le premier tick)
 */
export function usePolling(fn, interval = 5000, immediate = true) {
    let timer;

    onMounted(() => {
        // Appel immédiat optionnel : évite un délai à l'affichage initial
        if (immediate) fn();
        timer = setInterval(fn, interval);
    });

    onUnmounted(() => clearInterval(timer));
}
