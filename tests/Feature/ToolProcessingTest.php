<?php

namespace Tests\Feature;

use App\Services\Settings\SettingsRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ToolProcessingTest extends TestCase
{
    use RefreshDatabase;

    private function process(string $tool, array $files = [], array $options = [])
    {
        return $this->withSession(['pdftk_owner' => 'owner-token'])
            ->post(route('api.jobs.store', $tool), ['files' => $files, 'options' => json_encode($options)], ['Accept' => 'application/json']);
    }

    public function test_pdf_to_text_runs_and_result_can_be_downloaded(): void
    {
        $response = $this->process('pdf-to-text', [$this->upload($this->pdfFixture(), 'report.pdf')]);

        $response->assertOk()->assertJsonPath('status', 'done')->assertJsonPath('result.filename', 'report.txt');

        $download = $response->json('result.download_url');
        $this->assertStringContainsString('signature=', $download);

        $file = $this->withSession(['pdftk_owner' => 'owner-token'])->get($download);
        $file->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->assertStringContainsString('Hello PDF', file_get_contents($file->baseResponse->getFile()->getPathname()));

        $this->assertSame(1, (int) DB::table('tool_usages')->where('tool', 'pdf-to-text')->sum('count'));
    }

    public function test_status_url_requires_a_valid_signature(): void
    {
        $response = $this->process('pdf-to-text', [$this->upload($this->pdfFixture())]);
        $status = $response->json('status_url');

        $this->withSession(['pdftk_owner' => 'owner-token'])->getJson($status)->assertOk()->assertJsonPath('status', 'done');
        $this->withSession(['pdftk_owner' => 'owner-token'])->getJson(preg_replace('/signature=\w/', 'signature=x', $status))
            ->assertNotFound()->assertJsonPath('error.code', 'job_not_found');
    }

    public function test_jobs_are_bound_to_the_visitor_session(): void
    {
        $response = $this->process('pdf-to-text', [$this->upload($this->pdfFixture())]);

        $this->withSession(['pdftk_owner' => 'someone-else'])
            ->getJson($response->json('status_url'))
            ->assertNotFound();
    }

    public function test_files_can_be_deleted_immediately(): void
    {
        $response = $this->process('pdf-to-text', [$this->upload($this->pdfFixture())]);
        $id = $response->json('id');

        $this->withSession(['pdftk_owner' => 'owner-token'])->deleteJson($response->json('status_url'))->assertOk();
        $this->assertDirectoryDoesNotExist(config('pdftoolkit.temp_path').'/'.$id);
    }

    public function test_input_files_are_removed_after_processing(): void
    {
        $response = $this->process('pdf-to-text', [$this->upload($this->pdfFixture())]);

        $this->assertDirectoryDoesNotExist(config('pdftoolkit.temp_path').'/'.$response->json('id').'/input');
    }

    public function test_invalid_pdf_is_rejected(): void
    {
        $this->process('pdf-to-text', [$this->fakeFile('fake.pdf', 'this is not a pdf')])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_pdf');
    }

    public function test_script_uploads_are_rejected(): void
    {
        $this->process('pdf-to-text', [$this->fakeFile('shell.php.pdf', '%PDF-1.4 <?php system($_GET[1]); ?>')])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'unsupported_format');

        $this->process('pdf-to-text', [$this->fakeFile('shell.php', '<?php echo 1;')])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'unsupported_format');
    }

    public function test_wrong_file_type_is_rejected(): void
    {
        $this->process('pdf-to-word', [$this->fakeFile('notes.txt', 'plain text')])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'unsupported_format');
    }

    public function test_file_size_limit_is_enforced(): void
    {
        config(['pdftoolkit.limits.max_upload_mb' => 0]);
        app(SettingsRepository::class)->set(['max_upload_mb' => 1]);

        $big = $this->fixtures.'/big.pdf';
        file_put_contents($big, '%PDF-1.4'.str_repeat(' ', 1024 * 1024 + 10));

        $this->process('pdf-to-text', [$this->upload($big)])
            ->assertStatus(413)
            ->assertJsonPath('error.code', 'file_too_large');
    }

    public function test_invalid_options_are_rejected(): void
    {
        $this->process('pdf-to-word', [$this->upload($this->pdfFixture())], ['mode' => 'invalid'])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'invalid_options');
    }

    public function test_pdf_to_word_creates_a_docx(): void
    {
        $this->process('pdf-to-word', [$this->upload($this->pdfFixture('<h1>Title</h1><p>Paragraph one.</p>'), 'doc.pdf')])
            ->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('result.filename', 'doc.docx')
            ->assertJsonPath('result.mime', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');
    }

    public function test_pdf_to_excel_detects_table_columns(): void
    {
        $html = '<table><tr><td>Name</td><td>Amount</td></tr><tr><td>Alpha</td><td>10</td></tr><tr><td>Beta</td><td>20</td></tr></table>';
        $response = $this->process('pdf-to-csv', [$this->upload($this->pdfFixture($html), 'table.pdf')]);

        $response->assertOk()->assertJsonPath('status', 'done');
        $csv = file_get_contents($this->withSession(['pdftk_owner' => 'owner-token'])->get($response->json('result.download_url'))->baseResponse->getFile()->getPathname());

        $this->assertStringContainsString('Alpha,10', $csv);
        $this->assertStringContainsString('Beta,20', $csv);
    }

    public function test_text_to_pdf_works_without_a_file_in_paste_mode(): void
    {
        $this->process('text-to-pdf', [], ['source' => 'paste', 'text' => 'Hello مرحبا Привет'])
            ->assertOk()
            ->assertJsonPath('status', 'done')
            ->assertJsonPath('result.mime', 'application/pdf');
    }

    public function test_html_to_pdf_strips_remote_resources(): void
    {
        $html = '<h1>Report</h1><img src="http://169.254.169.254/latest/meta-data"><script>alert(1)</script>';

        $this->process('html-to-pdf', [], ['source' => 'paste', 'html' => $html])
            ->assertOk()
            ->assertJsonPath('status', 'done');
    }

    public function test_images_to_pdf(): void
    {
        $image = imagecreatetruecolor(200, 100);
        $jpg = $this->fixtures.'/photo.jpg';
        imagejpeg($image, $jpg);

        $this->process('jpg-to-pdf', [$this->upload($jpg, 'photo.jpg'), $this->upload($jpg, 'photo2.jpg')], ['page_size' => 'a4'])
            ->assertOk()
            ->assertJsonPath('result.stats.pages', 2);
    }

    public function test_browser_only_tools_have_no_server_engine(): void
    {
        $this->process('pdf-editor', [$this->upload($this->pdfFixture())])
            ->assertStatus(503)
            ->assertJsonPath('error.code', 'engine_unavailable');
    }

    public function test_disabled_tools_cannot_be_used(): void
    {
        app(SettingsRepository::class)->set(['disabled_tools' => ['pdf-to-text']]);

        $this->get('/pdf-to-text')->assertNotFound();
        $this->process('pdf-to-text', [$this->upload($this->pdfFixture())])->assertStatus(503)->assertJsonPath('error.code', 'tool_disabled');
    }

    public function test_cleanup_command_removes_expired_jobs(): void
    {
        $response = $this->process('pdf-to-text', [$this->upload($this->pdfFixture())]);
        $dir = config('pdftoolkit.temp_path').'/'.$response->json('id');
        $this->assertDirectoryExists($dir);

        $this->artisan('pdf:cleanup')->assertSuccessful();
        $this->assertDirectoryExists($dir, 'fresh jobs are kept');

        $this->travel(config('pdftoolkit.job_ttl') + 1)->minutes();
        $this->artisan('pdf:cleanup')->assertSuccessful();
        $this->assertDirectoryDoesNotExist($dir);
    }

    public function test_usage_ping_for_browser_tools(): void
    {
        $this->post(route('api.usage', 'merge-pdf'))->assertNoContent();
        $this->assertSame(1, (int) DB::table('tool_usages')->where(['tool' => 'merge-pdf', 'engine' => 'browser'])->value('count'));
    }
}
