@extends('layouts.app')

@section('page', 'tool')

@section('content')
    @include('tools.partials.hero')

    <section class="container-page py-8 sm:py-10">
        <x-ad position="tool_top" />

        <div id="tool-app" class="mx-auto max-w-4xl" data-config='@json($config)' style="--tool-color: {{ $color }}">
            @if ($engines === [])
                @include('tools.partials.unavailable')
            @else
                @include('tools.partials.workspace')
            @endif
        </div>

        @if ($engines !== [])
            <x-privacy-note class="mt-6" :browser="$engines[0] === 'browser'" data-privacy-note />
        @endif

        <x-ad position="tool_bottom" />
    </section>

    @include('tools.partials.info')
@endsection
