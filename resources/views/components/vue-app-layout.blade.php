@props([
    'bodyClass' => 'min-h-screen bg-slate-50 text-slate-900 antialiased',
])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    @isset($description)
        <meta name="description" content="{{ $description }}">
    @endisset
    <meta name="viewport" content="width=device-width, initial-scale=1">

    @isset($title)
        <title>{{ $title }} - {{ config('app.name') }}</title>
    @else
        <title>{{ config('app.name') }}</title>
    @endisset

    @vite(['resources/css/app.css'])
    @isset($scripts)
        {{ $scripts }}
    @endisset

</head>

<body {{ $attributes->class([$bodyClass]) }}>
    {{ $slot }}
</body>

</html>
