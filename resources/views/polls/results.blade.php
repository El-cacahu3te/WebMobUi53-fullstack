<x-vue-app-layout>
    <x-slot:title>Résultats du sondage</x-slot:title>
    <x-slot:scripts>
        @vite(['resources/js/poll-results.js'])
    </x-slot>

    <div id="poll-results-app" data-token="{{ $token }}"></div>
</x-vue-app-layout>
