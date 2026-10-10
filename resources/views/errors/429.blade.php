@extends('errors.layout', ['seo' => app(\App\Services\Seo\SeoManager::class)->noindex('Please slow down')])

@section('code', 'Too many requests')
@section('title', 'Please slow down')
@section('message', 'You have made too many requests in a short time. Please wait a minute and try again.')
