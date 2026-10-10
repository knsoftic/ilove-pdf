@extends('errors.layout', ['seo' => app(\App\Services\Seo\SeoManager::class)->noindex('Access denied')])

@section('code', 'Error 403')
@section('title', 'Access denied')
@section('message', 'You do not have permission to view this page.')
