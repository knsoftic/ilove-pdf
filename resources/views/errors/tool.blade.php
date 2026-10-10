@extends('errors.layout', ['seo' => app(\App\Services\Seo\SeoManager::class)->noindex('Something went wrong')])

@section('code', 'Sorry')
@section('title', 'Something went wrong')
@section('message', $message ?? 'Please try again.')
