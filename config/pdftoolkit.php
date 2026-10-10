<?php

/*
|--------------------------------------------------------------------------
| PDF Toolkit configuration
|--------------------------------------------------------------------------
|
| Everything that controls how uploads are stored, processed and cleaned up.
| Values marked "admin" can also be changed at runtime from the admin panel
| (they are stored in the `settings` table and override these defaults).
|
*/

return [

    /*
    |--------------------------------------------------------------------------
    | Temporary storage
    |--------------------------------------------------------------------------
    |
    | Uploaded files live in a random per-job directory below this path. It is
    | outside the public web root, so nothing in here can ever be executed or
    | requested directly. Results are only reachable through signed URLs.
    |
    */
    'temp_path' => env('PDF_TEMP_PATH', storage_path('app/pdf-temp')),

    // Minutes a job (and its result) is kept before the cleanup removes it.
    'job_ttl' => (int) env('PDF_JOB_TTL', 30),

    // Minutes a result is kept after it has been downloaded once.
    'downloaded_ttl' => (int) env('PDF_DOWNLOADED_TTL', 5),

    // Delete uploaded source files right after processing finished.
    'delete_inputs_after_processing' => true,

    // Only the browser session that created a job may poll / download / delete it.
    'bind_jobs_to_session' => env('PDF_BIND_JOBS_TO_SESSION', true),

    // Opportunistic cleanup on web requests (for hosts without a cron job):
    // [chance, out of]. Set to [0, 100] to disable.
    'cleanup_lottery' => [2, 100],

    /*
    |--------------------------------------------------------------------------
    | Limits (admin)
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_upload_mb' => (int) env('PDF_MAX_UPLOAD_MB', 100),
        'max_files' => (int) env('PDF_MAX_FILES', 30),
        'max_pages' => (int) env('PDF_MAX_PAGES', 1000),
        'max_image_pixels' => 60_000_000,
        'max_text_chars' => 2_000_000,
        'process_timeout' => (int) env('PDF_PROCESS_TIMEOUT', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate limiting (requests per minute, per IP address)
    |--------------------------------------------------------------------------
    */
    'rate_limits' => [
        'jobs' => (int) env('PDF_RATE_JOBS', 30),
        'status' => (int) env('PDF_RATE_STATUS', 240),
        'downloads' => (int) env('PDF_RATE_DOWNLOADS', 60),
        'text' => (int) env('PDF_RATE_TEXT', 60),
        'admin_login' => 5,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    |
    | Heavy tools (OCR, office conversion, compression...) are dispatched to
    | this queue when QUEUE_CONNECTION is not "sync". With "sync" they simply
    | run inside the request, which is fine for small installations.
    |
    */
    'queue' => [
        'connection' => env('PDF_QUEUE_CONNECTION'),
        'name' => env('PDF_QUEUE_NAME', 'pdf'),
    ],

    /*
    |--------------------------------------------------------------------------
    | External open-source binaries
    |--------------------------------------------------------------------------
    |
    | Leave a value empty to auto-detect (PATH + common install folders).
    | Every binary is optional: when a binary is missing the tools that need it
    | fall back to a pure PHP implementation or to in-browser processing.
    |
    */
    'binaries' => [
        'qpdf' => env('QPDF_PATH'),
        'ghostscript' => env('GHOSTSCRIPT_PATH'),
        'pdftotext' => env('PDFTOTEXT_PATH'),
        'pdftoppm' => env('PDFTOPPM_PATH'),
        'pdfimages' => env('PDFIMAGES_PATH'),
        'pdftohtml' => env('PDFTOHTML_PATH'),
        'libreoffice' => env('LIBREOFFICE_PATH'),
        'tesseract' => env('TESSERACT_PATH'),
    ],

    // Seconds the capability report is cached (binary detection is slow-ish).
    'capabilities_cache' => 3600,

    /*
    |--------------------------------------------------------------------------
    | Processing engine preference (admin)
    |--------------------------------------------------------------------------
    |
    | "browser" – use in-browser processing when a tool supports it (files never
    |             leave the device, no server load). Server is the fallback.
    | "server"  – prefer server-side processing when the server is capable.
    |
    */
    'engine_preference' => env('PDF_ENGINE_PREFERENCE', 'browser'),

    /*
    |--------------------------------------------------------------------------
    | OCR
    |--------------------------------------------------------------------------
    */
    'ocr' => [
        'dpi' => 300,
        // Browser OCR (tesseract.js) language data. Point this at your own
        // server (e.g. /tessdata) to avoid using the public CDN.
        'browser_lang_path' => env('OCR_BROWSER_LANG_PATH', 'https://cdn.jsdelivr.net/npm/@tesseract.js-data/{lang}/4.0.0_best_int'),
        'languages' => [
            'eng' => 'English', 'urd' => 'Urdu', 'hin' => 'Hindi', 'ara' => 'Arabic',
            'chi_sim' => 'Chinese (Simplified)', 'chi_tra' => 'Chinese (Traditional)',
            'spa' => 'Spanish', 'fra' => 'French', 'deu' => 'German', 'ita' => 'Italian',
            'por' => 'Portuguese', 'rus' => 'Russian', 'tur' => 'Turkish', 'ben' => 'Bengali',
            'fas' => 'Persian', 'ind' => 'Indonesian', 'msa' => 'Malay', 'jpn' => 'Japanese',
            'kor' => 'Korean', 'nld' => 'Dutch', 'pol' => 'Polish',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Translation
    |--------------------------------------------------------------------------
    |
    | driver: "libretranslate" (self-hosted, open source), "argos" (argos-translate
    | CLI, open source) or "none". When no server driver is configured, the
    | translator falls back to the on-device Translator API of the browser
    | (Chrome 138+) where available.
    |
    */
    'translation' => [
        'driver' => env('TRANSLATION_DRIVER', 'none'),
        'chunk_size' => 3500,
        'timeout' => 120,

        'libretranslate' => [
            'url' => env('LIBRETRANSLATE_URL', 'http://127.0.0.1:5000'),
            'api_key' => env('LIBRETRANSLATE_API_KEY'),
        ],

        'argos' => [
            'binary' => env('ARGOS_TRANSLATE_PATH', 'argos-translate'),
        ],

        // App language code => [label, native name, rtl, engine code overrides]
        'languages' => [
            'en' => ['English', 'English', false],
            'ur' => ['Urdu', 'اردو', true],
            'hi' => ['Hindi', 'हिन्दी', false],
            'ar' => ['Arabic', 'العربية', true],
            'zh' => ['Chinese', '中文', false],
            'es' => ['Spanish', 'Español', false],
            'fr' => ['French', 'Français', false],
            'de' => ['German', 'Deutsch', false],
            'it' => ['Italian', 'Italiano', false],
            'pt' => ['Portuguese', 'Português', false],
            'ru' => ['Russian', 'Русский', false],
            'tr' => ['Turkish', 'Türkçe', false],
            'bn' => ['Bengali', 'বাংলা', false],
            'fa' => ['Persian', 'فارسی', true],
            'id' => ['Indonesian', 'Bahasa Indonesia', false],
            'ms' => ['Malay', 'Bahasa Melayu', false],
        ],

        // Map app codes to the codes a specific engine expects.
        'engine_codes' => [
            'libretranslate' => ['zh' => 'zh-Hans'],
            'argos' => [],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Security
    |--------------------------------------------------------------------------
    */
    'security' => [
        // Never accept files with these extensions, whatever the tool.
        'blocked_extensions' => [
            'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar', 'pht', 'phps',
            'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'exe', 'dll', 'bat', 'cmd', 'com', 'msi',
            'jsp', 'asp', 'aspx', 'shtml', 'htaccess', 'svg', 'svgz', 'js', 'mjs', 'vbs', 'jar',
        ],

        'headers' => true,
        'hsts' => env('SECURITY_HSTS', false),
        // A strict Content-Security-Policy. Disabled by default because ad and
        // analytics snippets configured in the admin need third-party scripts.
        'csp' => env('SECURITY_CSP', false),
    ],

    /*
    |--------------------------------------------------------------------------
    | Admin area
    |--------------------------------------------------------------------------
    */
    'admin' => [
        // Change this to hide the admin login from bots, e.g. "control-7f3a".
        'path' => env('ADMIN_PATH', 'admin'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default site settings (admin)
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'site_name' => env('APP_NAME', 'PDF Toolkit'),
        'site_tagline' => 'Free online PDF tools',
        'logo_path' => null,
        'favicon_path' => null,
        'seo_title' => 'PDF Toolkit – Free Online PDF Tools: Edit, Convert, Merge & Compress',
        'seo_description' => 'Free, fast and easy PDF tools. Edit, convert, compress, merge, split and translate your PDF files directly from your browser. No sign-up required.',
        'seo_keywords' => 'pdf tools, pdf editor, merge pdf, split pdf, compress pdf, pdf to word, pdf converter, free pdf tools',
        'og_image_path' => null,
        'twitter_handle' => null,
        'footer_text' => 'Free and open-source PDF tools. No registration, no watermarks, no limits on usage.',
        'privacy_notice' => 'Your files are processed temporarily and automatically deleted after processing.',
        'analytics_code' => null,
        'head_code' => null,
        'max_upload_mb' => null,
        'max_files' => null,
        'engine_preference' => null,
        'allowed_types' => null,
        'disabled_tools' => [],
        'tool_overrides' => [],
        // SEO (admin → SEO)
        'title_separator' => '|',
        'title_append_site' => true,
        'og_locale' => 'en_US',
        'social_profiles' => [],
        'seo_noindex_site' => false,
        'seo_tools_title' => null,
        'seo_tools_description' => null,
        'seo_pages' => [],
        'verification' => [],
        'ga4_id' => null,
        'gtm_id' => null,
        'body_code' => null,
        'robots_extra' => null,
        'sitemap_pages' => true,
        'indexnow_key' => null,
        'log_404' => true,

        // Advertising (admin → Advertising; ad units live in the ad_units table)
        'ads_enabled' => false,
        'ads_label' => true,
        'ads_hide_for_admin' => false,
        'ads_txt' => null,
        'sw_filename' => 'sw.js',
        'sw_code' => null,
        'stats_enabled' => true,
        'page_privacy' => null,
        'page_terms' => null,
        'page_about' => null,
    ],
];
