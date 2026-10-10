@extends('errors.layout', ['seo' => app(\App\Services\Seo\SeoManager::class)->noindex('We will be right back')])

@section('code', 'Maintenance')
@section('title', 'We will be right back')
@section('message', 'The site is being updated. Please try again in a few minutes.')
