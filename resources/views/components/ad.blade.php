@props(['position'])
{!! app(\App\Services\Ads\AdManager::class)->render($position) !!}
