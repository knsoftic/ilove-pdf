{{-- Search engine / ad network verification (admin → SEO → Verification) --}}
@foreach ((array) setting('verification', []) as $service => $code)
    @if (isset(\App\Http\Controllers\Admin\SeoController::VERIFICATION[$service]))
        <meta name="{{ \App\Http\Controllers\Admin\SeoController::VERIFICATION[$service][1] }}" content="{{ $code }}">
    @endif
@endforeach

@if ($gtm = setting('gtm_id'))
    <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer',@json($gtm));</script>
@endif

@if ($ga4 = setting('ga4_id'))
    <script async src="https://www.googletagmanager.com/gtag/js?id={{ $ga4 }}"></script>
    <script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config',@json($ga4));</script>
@endif

@if ($site['analytics'] ?? null)
    {!! $site['analytics'] !!}
@endif
@if ($site['head_code'] ?? null)
    {!! $site['head_code'] !!}
@endif
