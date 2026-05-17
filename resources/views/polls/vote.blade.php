<x-vue-app-layout>
    <x-slot:title>Voter</x-slot:title>
    <x-slot:scripts>
        @vite(['resources/js/poll-vote.js'])
    </x-slot>

    <div id="poll-vote-app" data-token="{{ $token }}"></div>
</x-vue-app-layout>
