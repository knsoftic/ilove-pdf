@extends('layouts.app')

@section('page', 'content')

@section('content')
    <section class="container-page py-12">
        <nav aria-label="Breadcrumb" class="text-sm text-slate-500">
            <a href="{{ route('home') }}" class="hover:text-brand-600">Home</a> <span class="mx-1">/</span> <span class="text-slate-700 dark:text-slate-300">{{ $title }}</span>
        </nav>
        <article class="card prose-page mx-auto mt-6 max-w-3xl p-6 sm:p-10">
            {!! $html !!}
        </article>
    </section>
@endsection
