@extends('errors.layout', ['seo' => app(\App\Services\Seo\SeoManager::class)->noindex('Your session has expired')])

@section('code', 'Session expired')
@section('title', 'Your session has expired')
@section('message', 'Please go back, reload the page and try again.')
