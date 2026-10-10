<?php

namespace Tests\Feature;

use App\Services\Tools\ToolRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_with_seo_tags(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('All Your PDF Tools', false)
            ->assertSee('Drag &amp; Drop Your PDF Here', false)
            ->assertSee('<link rel="canonical"', false)
            ->assertSee('"@type":"WebSite"', false)
            ->assertSee('og:title', false)
            ->assertSee('twitter:card', false);
    }

    public function test_every_enabled_tool_has_its_own_page(): void
    {
        foreach (app(ToolRegistry::class)->enabled() as $tool) {
            $this->get('/'.$tool->slug)
                ->assertOk()
                ->assertSee('<h1', false)
                ->assertSee(e($tool->name()), false)
                ->assertSee('"@type":"BreadcrumbList"', false)
                ->assertSee('"@type":"FAQPage"', false);
        }
    }

    public function test_tools_directory_lists_tools_with_search(): void
    {
        $this->get('/tools')
            ->assertOk()
            ->assertSee('Search PDF tools...', false)
            ->assertSee('Merge PDF')
            ->assertSee('PDF Translation');
    }

    public function test_aliases_redirect_to_the_canonical_tool(): void
    {
        $this->get('/translate-pdf')->assertRedirect(route('tools.show', 'pdf-translator'))->assertStatus(301);
    }

    public function test_unknown_pages_return_a_friendly_404(): void
    {
        $this->get('/no-such-tool')->assertNotFound()->assertSee('Page not found');
    }

    public function test_sitemap_and_robots(): void
    {
        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('tools.show', 'merge-pdf'), false)
            ->assertSee(route('pages.privacy'), false);

        $this->get('/robots.txt')
            ->assertOk()
            ->assertSee('Disallow: /api/')
            ->assertSee('Sitemap: '.route('sitemap'));
    }

    public function test_static_pages_render_markdown(): void
    {
        $this->get('/privacy-policy')->assertOk()->assertSee('<h1>Privacy Policy</h1>', false);
        $this->get('/terms')->assertOk()->assertSee('Terms of Use');
        $this->get('/about')->assertOk()->assertSee('Built on open source');
    }

    public function test_security_headers_are_sent(): void
    {
        $this->get('/')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_trusted_proxies_come_from_config(): void
    {
        // Untrusted proxy first: the test client reuses the previous URL scheme.
        config(['trustedproxy.proxies' => '10.0.0.1']);
        $this->withHeader('X-Forwarded-Proto', 'https')->get('/')->assertSee('<link rel="canonical" href="http://', false);

        config(['trustedproxy.proxies' => '127.0.0.1']);
        $this->withHeader('X-Forwarded-Proto', 'https')->get('/')->assertSee('<link rel="canonical" href="https://', false);
    }
}
