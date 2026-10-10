<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Admin
    {
        return Admin::query()->create(['name' => 'Owner', 'email' => 'owner@example.test', 'password' => 'correct-horse-battery']);
    }

    public function test_guests_are_redirected_to_the_admin_login(): void
    {
        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->get('/admin/login')->assertOk()->assertSee('Admin sign in');
    }

    public function test_admin_can_sign_in_and_out(): void
    {
        $this->admin();

        $this->post(route('admin.login.attempt'), ['email' => 'owner@example.test', 'password' => 'wrong-password'])->assertSessionHasErrors('email');
        $this->assertGuest('admin');

        $this->post(route('admin.login.attempt'), ['email' => 'owner@example.test', 'password' => 'correct-horse-battery'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticated('admin');

        $this->post(route('admin.logout'))->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_every_admin_page_renders(): void
    {
        $this->actingAs($this->admin(), 'admin');

        foreach (['admin.dashboard' => 'Completed tasks per day', 'admin.settings' => 'Website name', 'admin.tools' => 'Merge PDF', 'admin.content' => 'Privacy policy', 'admin.ads' => 'Show ads on the website', 'admin.ads.create' => 'Ad code', 'admin.seo' => 'Overall SEO score', 'admin.seo.general' => 'Page titles', 'admin.seo.verification' => 'Google Search Console', 'admin.seo.pages' => 'All tools page', 'admin.seo.tools' => 'Merge PDF', 'admin.seo.redirects' => '404 monitor', 'admin.seo.sitemap' => 'IndexNow', 'admin.system' => 'Open-source binaries', 'admin.account' => 'Current password'] as $route => $text) {
            $this->get(route($route))->assertOk()->assertSee($text)->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        }
    }

    public function test_settings_are_saved_and_used_by_the_site(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->put(route('admin.settings.update'), [
            'site_name' => 'My PDF Site',
            'privacy_notice' => 'Files are deleted automatically.',
            'engine_preference' => 'browser',
            'stats_enabled' => '1',
            'max_upload_mb' => 50,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->put(route('admin.seo.pages.update'), [
            'seo_title' => 'Custom title for search engines',
            'seo_description' => 'Custom description for the home page of the site.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->get('/')->assertSee('<title>Custom title for search engines</title>', false)->assertSee('My PDF Site');
    }

    public function test_tools_can_be_disabled_and_customised(): void
    {
        $this->actingAs($this->admin(), 'admin');
        $all = array_keys(config('tools.tools'));

        $this->put(route('admin.tools.update'), [
            'enabled' => array_values(array_diff($all, ['split-pdf'])),
            'names' => ['merge-pdf' => 'Combine PDFs'],
        ])->assertRedirect();

        $this->put(route('admin.seo.tool.update', 'merge-pdf'), ['title' => 'Combine PDF files'])->assertRedirect()->assertSessionHasNoErrors();

        // Renaming on the Tools page keeps the SEO texts.
        $this->put(route('admin.tools.update'), ['enabled' => $all, 'names' => ['merge-pdf' => 'Combine PDFs']])->assertRedirect();
        $this->put(route('admin.tools.update'), ['enabled' => array_values(array_diff($all, ['split-pdf'])), 'names' => ['merge-pdf' => 'Combine PDFs']])->assertRedirect();

        $this->get('/split-pdf')->assertNotFound();
        $this->get('/sitemap.xml')->assertDontSee('/split-pdf');
        $this->get('/merge-pdf')->assertSee('Combine PDFs')->assertSee('<title>Combine PDF files', false);
    }

    public function test_legal_pages_can_be_edited_with_safe_markdown(): void
    {
        $this->actingAs($this->admin(), 'admin');

        $this->put(route('admin.content.update'), ['page_privacy' => "# Our privacy\n\n<script>alert(1)</script>Plain text"])->assertRedirect();

        $this->get('/privacy-policy')->assertSee('<h1>Our privacy</h1>', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_admin_login_is_rate_limited(): void
    {
        foreach (range(1, 5) as $ignored) {
            $this->post(route('admin.login.attempt'), ['email' => 'x@example.test', 'password' => 'nope']);
        }

        $this->post(route('admin.login.attempt'), ['email' => 'x@example.test', 'password' => 'nope'])->assertStatus(429);
    }

    public function test_make_admin_command(): void
    {
        $this->artisan('pdf:admin', ['--email' => 'new@example.test', '--name' => 'New', '--password' => 'a-long-password-123'])->assertSuccessful();
        $this->assertDatabaseHas('admins', ['email' => 'new@example.test']);
    }
}
