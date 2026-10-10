@extends('layouts.app')

@section('page', 'error')
@section('noads', '1')

@section('content')
    <section class="container-page flex min-h-[60vh] flex-col items-center justify-center py-20 text-center">
        <p class="text-sm font-bold tracking-widest text-brand-600 uppercase">@yield('code')</p>
        <h1 class="mt-3 text-3xl font-extrabold tracking-tight text-slate-900 sm:text-4xl dark:text-white">@yield('title')</h1>
        <p class="mt-4 max-w-xl text-slate-600 dark:text-slate-400">@yield('message')</p>
        <div class="mt-8 flex flex-wrap justify-center gap-3">
            <a href="{{ route('home') }}" class="btn btn-primary"><x-icon name="home" class="size-4" /> Go to home page</a>
            <a href="{{ route('tools.index') }}" class="btn btn-secondary"><x-icon name="grid" class="size-4" /> All tools</a>
        </div>
    </section>
@endsection
