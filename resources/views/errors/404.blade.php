@extends('errors.layout', ['seo' => app(\App\Services\Seo\SeoManager::class)->noindex('Page not found')])

@section('code', 'Error 404')
@section('title', 'Page not found')
@section('message', 'The page you are looking for does not exist or has been moved.')
