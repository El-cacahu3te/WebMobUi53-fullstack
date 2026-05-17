<x-vue-app-layout>
    <x-slot:scripts>
        @vite(['resources/js/poll-edit.js'])
    </x-slot>
    <x-slot:title>Modifier le sondage</x-slot>
    <div id="app-poll-edit" data-props='@json(["poll" => $poll->load("options")])'></div>
</x-vue-app-layout>
