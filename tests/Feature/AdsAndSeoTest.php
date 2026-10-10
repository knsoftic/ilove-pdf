<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdUnit;
use App\Models\NotFoundLog;
use App\Models\Redirect;
use App\Services\Seo\IndexNow;
use App\Services\Settings\SettingsRepository;
use App\Services\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class AdsAndSeoTest extends TestCase
{
    use RefreshDatabase;

    private const MOBILE = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

    private const DESKTOP = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Safari/537.36';

    private function admin(): Admin
    {
        return Admin::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'correct-horse-battery']);
    }

    private function settings(array $values): void
    {
        app(SettingsRepository::class)->set($values);
    }

    private function unit(array $attributes = []): AdUnit
    {
        return AdUnit::query()->create($attributes + [
            'name' => 'Test unit',
            'network' => 'adsterra',
            'format' => 'banner',
            'placement' => 'tool_top',
            'code' => '<div>AD</div>',
            'devices' => 'all',
            'page_scope' => 'all',
            'is_active' => true,
        ]);
    }

    // ---- Advertising --------------------------------------------------------

    public function test_admin_adds_an_adsterra_banner_that_appears_on_tool_pages_only(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.ads.store'), [
            'name' => 'Leaderboard',
            'network' => 'adsterra',
            'format' => 'banner',
            'size' => '728x90',
            'placement' => 'tool_top',
            'code' => '<script>atOptions = {"key":"abc"}</script><!--adsterra-728-->',
            'devices' => 'all',
            'page_scope' => 'tools',
            'is_active' => '1',
        ])->assertRedirect(route('admin.ads'))->assertSessionHasNoErrors();

        $this->get('/merge-pdf')->assertDontSee('<!--adsterra-728-->', false);

        $this->put(route('admin.ads.settings'), ['ads_enabled' => '1', 'ads_label' => '1', 'sw_filename' => 'sw.js'])->assertSessionHasNoErrors();

        $this->get('/merge-pdf')
            ->assertSee('<!--adsterra-728-->', false)
            ->assertSee('min-height: 90px', false)
            ->assertSee('Advertisement');
        $this->get('/')->assertDontSee('<!--adsterra-728-->', false);
        $this->get(route('admin.ads'))->assertOk()->assertSee('Leaderboard')->assertSee('Ads are live');
    }

    public function test_device_targeting(): void
    {
        $this->settings(['ads_enabled' => true]);
        $this->unit(['code' => '<!--mobile-ad-->', 'devices' => 'mobile']);
        $this->unit(['code' => '<!--desktop-ad-->', 'devices' => 'desktop']);

        $this->withHeader('User-Agent', self::MOBILE)->get('/merge-pdf')
            ->assertSee('<!--mobile-ad-->', false)
            ->assertDontSee('<!--desktop-ad-->', false);

        $this->withHeader('User-Agent', self::DESKTOP)->get('/merge-pdf')
            ->assertSee('<!--desktop-ad-->', false)
            ->assertDontSee('<!--mobile-ad-->', false);
    }

    public function test_script_placements_and_page_targeting(): void
    {
        $this->settings(['ads_enabled' => true]);
        $this->unit(['format' => 'popunder', 'placement' => 'head', 'code' => '<script src="//pl.example.test/popunder.js"></script>', 'page_scope' => 'except', 'tools' => ['pdf-editor']]);
        $this->unit(['network' => 'monetag', 'format' => 'social_bar', 'placement' => 'body_end', 'code' => '<!--in-page-push-->', 'page_scope' => 'only', 'tools' => ['merge-pdf']]);

        $html = $this->get('/merge-pdf')->assertOk()->getContent();
        $this->assertLessThan(strpos($html, '</head>'), strpos($html, 'popunder.js'));
        $this->assertGreaterThan(strpos($html, 'window.PDFTK'), strpos($html, '<!--in-page-push-->'));
        $this->assertLessThan(strpos($html, '</body>'), strpos($html, '<!--in-page-push-->'));

        $this->get('/pdf-editor')->assertDontSee('popunder.js', false)->assertDontSee('<!--in-page-push-->', false);
        $this->get('/')->assertSee('popunder.js', false)->assertDontSee('<!--in-page-push-->', false);

        $this->settings(['ads_enabled' => false]);
        $this->get('/merge-pdf')->assertDontSee('popunder.js', false)->assertDontSee('<!--in-page-push-->', false);
    }

    public function test_visible_ads_are_not_shown_on_error_pages(): void
    {
        $this->settings(['ads_enabled' => true]);
        $this->unit(['placement' => 'header', 'code' => '<!--header-ad-->']);

        $this->get('/')->assertSee('<!--header-ad-->', false);
        $this->get('/does-not-exist')->assertNotFound()->assertDontSee('<!--header-ad-->', false);
    }

    public function test_ads_can_be_hidden_for_the_signed_in_owner(): void
    {
        $this->settings(['ads_enabled' => true, 'ads_hide_for_admin' => true]);
        $this->unit(['code' => '<!--owner-hidden-->']);

        $this->get('/merge-pdf')->assertSee('<!--owner-hidden-->', false);
        $this->actingAs($this->admin(), 'admin')->get('/merge-pdf')->assertDontSee('<!--owner-hidden-->', false);
    }

    public function test_paused_units_disappear_immediately(): void
    {
        $this->settings(['ads_enabled' => true]);
        $unit = $this->unit(['code' => '<!--pausable-->']);

        $this->get('/merge-pdf')->assertSee('<!--pausable-->', false);

        $this->actingAs($this->admin(), 'admin')->patch(route('admin.ads.toggle', $unit))->assertRedirect();
        $this->assertFalse($unit->fresh()->is_active);

        $this->get('/merge-pdf')->assertDontSee('<!--pausable-->', false);

        $this->delete(route('admin.ads.destroy', $unit))->assertRedirect();
        $this->assertDatabaseMissing('ad_units', ['id' => $unit->id]);
    }

    public function test_direct_links(): void
    {
        $this->settings(['ads_enabled' => true]);
        $this->unit(['network' => 'monetag', 'format' => 'direct_link', 'placement' => 'download', 'code' => null, 'url' => 'https://example.test/smartlink', 'options' => ['frequency' => 15]]);
        $this->unit(['format' => 'direct_link', 'placement' => 'tool_bottom', 'code' => null, 'url' => 'https://example.test/offer', 'options' => ['label' => 'Try our partner']]);

        $this->get('/merge-pdf')
            ->assertSee('"downloadLinks":[{"id":', false)
            ->assertSee('https:\/\/example.test\/smartlink', false)
            ->assertSee('"every":15', false)
            ->assertSee('rel="sponsored nofollow noopener"', false)
            ->assertSee('Try our partner');
    }

    public function test_ad_unit_validation(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $base = ['name' => 'X', 'network' => 'adsterra', 'devices' => 'all', 'page_scope' => 'all', 'is_active' => '1'];

        $this->post(route('admin.ads.store'), $base + ['format' => 'popunder', 'placement' => 'tool_top', 'code' => '<script></script>'])->assertSessionHasErrors('placement');
        $this->post(route('admin.ads.store'), $base + ['format' => 'direct_link', 'placement' => 'download'])->assertSessionHasErrors('url');
        $this->post(route('admin.ads.store'), $base + ['format' => 'direct_link', 'placement' => 'download', 'url' => 'javascript:alert(1)'])->assertSessionHasErrors('url');
        $this->post(route('admin.ads.store'), ['network' => 'monetag'] + $base + ['format' => 'banner', 'placement' => 'tool_top', 'code' => '<div></div>'])->assertSessionHasErrors('format');
        $this->post(route('admin.ads.store'), $base + ['format' => 'banner', 'placement' => 'tool_top', 'code' => ''])->assertSessionHasErrors('code');
        $this->post(route('admin.ads.store'), ['page_scope' => 'only'] + $base + ['format' => 'banner', 'placement' => 'tool_top', 'code' => '<div></div>'])->assertSessionHasErrors('tools');

        $this->assertSame(0, AdUnit::query()->count());
    }

    public function test_ads_txt_and_service_worker_files(): void
    {
        $this->get('/ads.txt')->assertNotFound();
        $this->get('/sw.js')->assertNotFound();

        $this->actingAs($this->admin(), 'admin');
        $this->put(route('admin.ads.settings'), ['sw_filename' => '../evil.js'])->assertSessionHasErrors('sw_filename');
        $this->put(route('admin.ads.settings'), [
            'ads_txt' => 'google.com, pub-0000000000000000, DIRECT, f08c47fec0942fa0',
            'sw_filename' => 'sw.js',
            'sw_code' => "self.options = {\"zoneId\": 1}\nimportScripts('https://example.test/sw.js')",
        ])->assertSessionHasNoErrors();

        $this->get('/ads.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8')->assertSee('pub-0000000000000000');
        $this->get('/sw.js')->assertOk()
            ->assertHeader('Content-Type', 'application/javascript; charset=UTF-8')
            ->assertHeader('Service-Worker-Allowed', '/')
            ->assertSee('importScripts', false);
        $this->get('/other.js')->assertNotFound();
    }

    public function test_content_security_policy_is_relaxed_only_when_ads_are_active(): void
    {
        config(['pdftoolkit.security.csp' => true]);

        $strict = $this->get('/')->assertHeader('Cross-Origin-Opener-Policy', 'same-origin-allow-popups')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("script-src 'self' 'wasm-unsafe-eval' blob:;", $strict);

        $this->settings(['ads_enabled' => true]);
        $this->unit();

        $relaxed = $this->get('/')->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('https:', $relaxed);
        $this->assertStringContainsString('frame-src https:', $relaxed);
    }

    public function test_ad_position_preview_for_the_owner(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.ads.preview'))->assertRedirect(route('home'));
        $this->get('/merge-pdf')->assertSee('Ad position preview is on')->assertSee('<code>tool_top</code>', false);

        $this->post(route('admin.ads.preview'));
        $this->get('/merge-pdf')->assertDontSee('<code>tool_top</code>', false);
    }

    // ---- SEO --------------------------------------------------------------------

    public function test_tool_seo_is_saved_and_rendered(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->put(route('admin.seo.tool.update', 'merge-pdf'), [
            'focus' => 'merge pdf',
            'title' => 'Merge PDF Files Online for Free',
            'meta' => 'Merge PDF files in seconds. Combine several PDF documents into one file directly in your browser – free, private and without registration.',
            'heading' => 'Merge PDF files',
            'article' => "# Big heading\n\nMerge PDF <script>alert(1)</script> files **quickly**.",
            'faq' => [
                ['q' => 'Can I merge 50 PDFs at once?', 'a' => 'Yes, up to the upload limit.'],
                ['q' => '', 'a' => 'ignored'],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $page = $this->get('/merge-pdf')->assertOk();
        $page->assertSee('<title>Merge PDF Files Online for Free |', false)
            ->assertSee('Merge PDF files in seconds.', false)
            ->assertSee('Merge PDF files</h1>', false)
            ->assertSee('<h2>Big heading</h2>', false)
            ->assertSee('<strong>quickly</strong>', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('Can I merge 50 PDFs at once?')
            ->assertSee('"name":"Can I merge 50 PDFs at once?"', false);

        $this->get(route('admin.seo.tool', 'merge-pdf'))->assertOk()->assertSee('Google preview')->assertSee('Focus keyword appears in the SEO title');
    }

    public function test_noindex_tool_is_removed_from_the_sitemap(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $this->put(route('admin.seo.tool.update', 'split-pdf'), ['noindex' => '1'])->assertSessionHasNoErrors();

        $this->get('/split-pdf')->assertOk()->assertSee('<meta name="robots" content="noindex,follow">', false);
        $this->get('/sitemap.xml')->assertDontSee('/split-pdf')->assertSee('/merge-pdf');
    }

    public function test_discouraging_search_engines_blocks_the_whole_site(): void
    {
        $this->settings(['seo_noindex_site' => true]);

        $this->get('/robots.txt')->assertSee("User-agent: *\nDisallow: /\n", false);
        $this->get('/')->assertSee('<meta name="robots" content="noindex,nofollow">', false);
        $this->get('/sitemap.xml')->assertDontSee('<url>', false);
    }

    public function test_verification_codes_and_analytics(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->put(route('admin.seo.verification.update'), [
            'verification' => ['google' => '<meta name="google-site-verification" content="abc123XYZ_-" />', 'bing' => 'BING12345', 'monetag' => 'f00d1234'],
            'ga4_id' => 'g-test1234',
        ])->assertSessionHasNoErrors();

        $this->get('/')
            ->assertSee('<meta name="google-site-verification" content="abc123XYZ_-">', false)
            ->assertSee('<meta name="msvalidate.01" content="BING12345">', false)
            ->assertSee('<meta name="monetag" content="f00d1234">', false)
            ->assertSee('gtag/js?id=G-TEST1234', false);

        $this->put(route('admin.seo.verification.update'), ['verification' => ['google' => '"><script>alert(1)</script>']])->assertSessionHasErrors('verification.google');
        $this->put(route('admin.seo.verification.update'), ['ga4_id' => 'UA-12345'])->assertSessionHasErrors('ga4_id');
    }

    public function test_general_seo_settings(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->put(route('admin.seo.general.update'), [
            'title_separator' => '–',
            'title_append_site' => '1',
            'og_locale' => 'ur_PK',
            'social_profiles' => "https://x.com/pdftoolkit\nhttps://facebook.com/pdftoolkit",
        ])->assertSessionHasNoErrors();

        $this->get('/merge-pdf')->assertSee(' – '.setting('site_name').'</title>', false);
        $this->get('/')
            ->assertSee('<meta property="og:locale" content="ur_PK">', false)
            ->assertSee('"sameAs":["https://x.com/pdftoolkit","https://facebook.com/pdftoolkit"]', false);

        $this->put(route('admin.seo.general.update'), ['title_separator' => '|', 'og_locale' => 'en_US', 'social_profiles' => 'not a url'])->assertSessionHasErrors('social_profiles');
    }

    public function test_page_seo_overrides(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->put(route('admin.seo.pages.update'), [
            'seo_title' => 'Free PDF Tools Online – Edit, Convert and Compress PDFs',
            'seo_description' => 'Home description',
            'seo_tools_title' => 'Browse {count} PDF tools',
            'pages' => ['about' => ['title' => 'About our team', 'noindex' => '1']],
        ])->assertSessionHasNoErrors();

        $count = app(ToolRegistry::class)->enabled()->count();

        $this->get('/tools')->assertSee("<title>Browse {$count} PDF tools |", false);
        $this->get('/about')->assertSee('<title>About our team |', false)->assertSee('content="noindex,follow"', false);
        $this->get('/sitemap.xml')->assertDontSee('/about')->assertSee('/privacy-policy');
    }

    public function test_redirects(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.seo.redirects.store'), ['source' => '/old-merge/', 'target' => '/merge-pdf', 'status_code' => 301])->assertSessionHasNoErrors();
        $this->post(route('admin.seo.redirects.store'), ['source' => '/removed', 'status_code' => 410])->assertSessionHasNoErrors();

        $this->get('/old-merge')->assertStatus(301)->assertRedirect(url('/merge-pdf'));
        $this->get('/removed')->assertStatus(410);
        $this->assertSame(1, Redirect::query()->where('source', '/old-merge')->value('hits'));

        // A disabled tool can be redirected to another tool.
        $this->settings(['disabled_tools' => ['split-pdf']]);
        $this->post(route('admin.seo.redirects.store'), ['source' => '/split-pdf', 'target' => '/merge-pdf', 'status_code' => 302]);
        $this->get('/split-pdf')->assertStatus(302);

        $this->post(route('admin.seo.redirects.store'), ['source' => '/', 'target' => '/merge-pdf', 'status_code' => 301])->assertSessionHasErrors('source');
        $this->post(route('admin.seo.redirects.store'), ['source' => '/admin/x', 'target' => '/merge-pdf', 'status_code' => 301])->assertSessionHasErrors('source');
        $this->post(route('admin.seo.redirects.store'), ['source' => '/a', 'target' => 'javascript:alert(1)', 'status_code' => 301])->assertSessionHasErrors('target');
        $this->post(route('admin.seo.redirects.store'), ['source' => '/loop', 'target' => '/loop', 'status_code' => 301])->assertSessionHasErrors('target');

        $this->get(route('admin.seo.redirects'))->assertOk()->assertSee('/old-merge');
    }

    public function test_404_monitor(): void
    {
        $this->get('/missing-page')->assertNotFound();
        $this->get('/missing-page')->assertNotFound();
        $this->get('/wp-login.php')->assertNotFound();
        $this->get('/.env')->assertNotFound();

        $this->assertSame(2, NotFoundLog::query()->where('path', '/missing-page')->value('hits'));
        $this->assertSame(1, NotFoundLog::query()->count());

        $this->actingAs($this->admin(), 'admin');
        $this->get(route('admin.seo.redirects'))->assertSee('/missing-page');

        // Creating a redirect resolves the entry.
        $this->post(route('admin.seo.redirects.store'), ['source' => '/missing-page', 'target' => '/tools', 'status_code' => 301]);
        $this->assertSame(0, NotFoundLog::query()->count());

        $this->put(route('admin.seo.notfound.settings'), ['log_404' => '0']);
        $this->get('/another-missing-page')->assertNotFound();
        $this->assertSame(0, NotFoundLog::query()->count());
    }

    public function test_robots_txt_extra_rules(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->put(route('admin.seo.sitemap.update'), ['sitemap_pages' => '1', 'robots_extra' => "User-agent: GPTBot\nDisallow: /"])->assertSessionHasNoErrors();
        $this->get('/robots.txt')->assertSee("User-agent: GPTBot\nDisallow: /\n\nSitemap: ", false);

        $this->put(route('admin.seo.sitemap.update'), ['robots_extra' => 'hello world'])->assertSessionHasErrors('robots_extra');
    }

    public function test_indexnow(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->post(route('admin.seo.indexnow.key'))->assertRedirect();
        $key = setting('indexnow_key');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $key);
        $this->get("/{$key}.txt")->assertOk()->assertSeeText($key);
        $this->get('/'.str_repeat('0', 32).'.txt')->assertNotFound();

        // Local installations cannot use IndexNow.
        $this->post(route('admin.seo.indexnow.submit'))->assertSessionHasErrors('indexnow');

        URL::forceRootUrl('https://pdf.example.com');
        URL::forceScheme('https');
        Http::fake([IndexNow::ENDPOINT => Http::response('', 202)]);

        $this->post(route('admin.seo.indexnow.submit'))->assertSessionHasNoErrors();
        Http::assertSent(fn ($request) => $request['host'] === 'pdf.example.com'
            && $request['key'] === $key
            && $request['keyLocation'] === "https://pdf.example.com/{$key}.txt"
            && in_array('https://pdf.example.com/merge-pdf', $request['urlList'], true));
    }

    public function test_seo_overview_reports_problems(): void
    {
        $this->settings(['seo_noindex_site' => true]);

        $this->actingAs($this->admin(), 'admin')
            ->get(route('admin.seo'))
            ->assertOk()
            ->assertSee('Search engines are blocked')
            ->assertSee('APP_URL points to a local address')
            ->assertSee('Tool pages to improve first');

        $this->get(route('admin.dashboard'))->assertOk()->assertSee('SEO score')->assertSee('Switched off');
    }
}
