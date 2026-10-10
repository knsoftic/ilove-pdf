# PDF Toolkit – free, open-source PDF tools (Laravel)

A complete, production-ready PDF tools website built with **Laravel 12**. 57 tools – a full in-browser PDF editor, conversions, compression, page organisation, security, OCR and translation – usable **without registration**, with **no paid APIs** and with **privacy-first processing**:

* **Browser engine** – most tools run entirely in the visitor's browser (PDF.js, pdf-lib, Fabric.js, Tesseract.js). Files never leave the device.
* **Server engine** – conversions that need it run on your server with pure PHP libraries and, when installed, open-source binaries (qpdf, Ghostscript, Poppler, LibreOffice, Tesseract). Uploads are stored under random names outside the web root, source files are deleted right after processing and results expire automatically.

The application detects which binaries are installed and picks the best available engine per tool. With **no binaries at all** every tool except *PowerPoint → PDF* (needs LibreOffice) still works.

---

## Contents

1. [Features](#1-features)
2. [Architecture](#2-architecture)
3. [Requirements](#3-requirements)
4. [Installation (development)](#4-installation-development)
5. [Server binaries (optional, recommended)](#5-server-binaries-optional-recommended)
6. [Environment configuration](#6-environment-configuration)
7. [Queue](#7-queue)
8. [Scheduler & temporary file cleanup](#8-scheduler--temporary-file-cleanup)
9. [Translation engine](#9-translation-engine)
10. [OCR](#10-ocr)
11. [Admin panel](#11-admin-panel)
12. [Production deployment](#12-production-deployment)
13. [Security](#13-security)
14. [Adding a new tool](#14-adding-a-new-tool)
15. [Testing](#15-testing)
16. [Known limitations](#16-known-limitations)
17. [Troubleshooting](#17-troubleshooting)
18. [Licenses](#18-licenses)

---

## 1. Features

| Category | Tools (URL) | Engine |
|---|---|---|
| **PDF Conversion** | PDF to Word `/pdf-to-word`, PDF to Excel `/pdf-to-excel`, PDF to PowerPoint `/pdf-to-powerpoint`, PDF to JPG `/pdf-to-jpg`, PDF to PNG `/pdf-to-png`, PDF to WEBP `/pdf-to-webp`, PDF to Text `/pdf-to-text`, PDF to HTML `/pdf-to-html`, PDF to CSV `/pdf-to-csv` | server · browser |
| **Create PDF** | JPG/PNG/WEBP to PDF `/jpg-to-pdf` `/png-to-pdf` `/webp-to-pdf`, Word to PDF `/word-to-pdf`, Excel to PDF `/excel-to-pdf`, PowerPoint to PDF `/powerpoint-to-pdf`, HTML to PDF `/html-to-pdf`, Text to PDF `/text-to-pdf` | server · browser |
| **PDF Editing** | PDF Editor `/pdf-editor`, Edit PDF `/edit-pdf`, Add Text `/add-text-to-pdf`, Add Image `/add-image-to-pdf`, Draw `/draw-on-pdf`, Highlight `/highlight-pdf`, Underline `/underline-pdf`, Strikethrough `/strikethrough-pdf`, Shapes `/add-shapes-to-pdf`, Sign PDF `/sign-pdf`, Add Watermark `/add-watermark`, Remove Watermark `/remove-watermark`, Page Numbers `/add-page-numbers`, Header & Footer `/add-header-footer` | browser |
| **PDF Organization** | Merge `/merge-pdf`, Split `/split-pdf`, Extract pages `/extract-pages`, Delete pages `/delete-pages`, Organize (page manager) `/organize-pdf`, Reorder `/reorder-pages`, Rotate `/rotate-pdf`, Duplicate pages `/duplicate-pages` | browser · server |
| **PDF Compression** | Compress `/compress-pdf`, Optimize `/optimize-pdf`, Reduce size `/reduce-pdf-size`, Grayscale `/grayscale-pdf`, Flatten `/flatten-pdf` | server · browser |
| **PDF Security** | Protect `/protect-pdf`, Unlock `/unlock-pdf`, Encrypt `/encrypt-pdf`, Remove metadata `/remove-pdf-metadata`, Add metadata `/edit-pdf-metadata` | browser · server |
| **PDF OCR & Text** | OCR PDF `/ocr-pdf`, Extract text `/extract-text-from-pdf`, Extract images `/extract-images-from-pdf`, Search text `/search-text-in-pdf`, Replace text `/replace-text-in-pdf` | browser · server |
| **PDF Translation** | PDF Translator `/pdf-translator` (aliases `/translate-pdf`, `/pdf-translation`) – 16 languages | server · browser |
| **Image Tools** | Compress image `/compress-image`, Resize image `/resize-image` (+ image ↔ PDF tools) | browser · server |

Highlights

* **PDF editor** – page thumbnails with drag-to-reorder, rotate/duplicate/delete pages, select & move, *edit existing text* (covered with the sampled background colour and replaced in a matching font), add text (font, size, bold, italic, underline, colour), highlight / underline / strikethrough that snap to text lines, pen, eraser, rectangle, circle, line, arrow, images, signatures (draw / type / upload), stamps, watermark, page numbers, undo/redo, zoom, fullscreen, mobile toolbar. Export writes **real PDF vector content** (selectable text, sharp shapes).
* **PDF to Text** – large editor with copy, search, replace, clear, TXT/DOCX/PDF download and automatic OCR for scanned pages.
* **PDF Translator** – upload → extract (with OCR fallback) → detect language → choose target → translate in chunks → edit side-by-side (RTL aware) → download TXT/DOCX/PDF. Engines: self-hosted LibreTranslate / Argos Translate, or the browser's on-device translator.
* Generic tool template: drag & drop, multiple files with drag-to-reorder, file thumbnails, upload progress, processing progress, cancel, friendly errors, result statistics (e.g. *Original 10 MB → 4.2 MB, saved 58 %*), download, "process another file", "delete my files now".
* Home page with a universal upload area (files are handed to the chosen tool in the browser), searchable tools directory, dark mode, responsive layout.
* SEO: dedicated URL per tool, title/description/keywords, canonical, Open Graph, Twitter Card, Schema.org (`WebSite`, `Organization`, `SoftwareApplication`, `FAQPage`, `BreadcrumbList`, `ItemList`), `sitemap.xml`, `robots.txt`.
* Admin panel (separate login): site name, logo, favicon, social image, enable/disable tools, upload limits, allowed file types, processing preference, privacy notice, footer, privacy policy/terms/about (Markdown), usage statistics, server capability report, manual cleanup – plus a full **SEO manager** (score & audit, per-tool titles/descriptions/articles/FAQs, verification codes, GA4/GTM, redirects & 404 monitor, robots.txt, IndexNow) and an **ad manager** for Adsterra, Monetag, AdSense or any network (popunder, social bar, push, MultiTag, banners, native, direct links; page & device targeting; ads.txt; service worker).

## 2. Architecture

```
app/
├── Console/Commands/          pdf:cleanup, pdf:admin, pdf:doctor
├── Exceptions/ToolException   user-facing error codes & messages
├── Helpers/                   PageRange, FileName, ZipBuilder, TextEncoding, UnicodeText, helpers.php
├── Http/
│   ├── Controllers/Pdf/       ToolController (pages), JobController (process/status/download/delete),
│   │                          TextExportController, TranslationController, UsageController
│   ├── Controllers/Admin/     Auth, Dashboard, Settings, Tool, Seo, Redirect, Content, Ads, System, Account
│   └── Middleware/            SecurityHeaders, CleanupLottery
├── Jobs/RunToolJob            queued processing
├── Models/                    Admin, Setting, ToolUsage, AdUnit, Redirect, NotFoundLog
├── Services/
│   ├── Pdf/                   JobManager, TempStorage, TempCleaner, PdfInspector, PdfRasterizer,
│   │   │                      PdfTextExtractor, LayoutAnalyzer, ImagePdfWriter, ImageService
│   │   ├── Engines/           QpdfEngine, GhostscriptEngine, PopplerEngine, LibreOfficeEngine
│   │   └── Processors/        one class per server tool (PdfToWordProcessor, CompressProcessor, …)
│   ├── Documents/             DocxBuilder (PHPWord), PdfBuilder (mPDF), SpreadsheetBuilder, HtmlSanitizer
│   ├── Ocr/                   OcrManager, TesseractEngine, Contracts/OcrEngine
│   ├── Translation/           TranslationManager, LibreTranslate/Argos/Null translators, LanguageDetector
│   ├── Tools/                 ToolRegistry, Tool, FileTypes, UploadValidator, OptionValidator
│   ├── Seo/                   SeoManager, SeoData, SeoAudit, Sitemap, Redirector, IndexNow
│   ├── Ads/                   AdManager (targeting & rendering), AdCatalog (networks, formats, positions)
│   ├── Settings/              SettingsRepository
│   ├── Stats/                 UsageRecorder
│   └── System/                Capabilities, BinaryLocator, CommandRunner
config/
├── tools.php                  ★ tool registry (routes, UI, options, SEO, engines are generated from it)
└── pdftoolkit.php             storage, limits, binaries, OCR, translation, security
resources/
├── views/                     layouts, home, tools/ (generic template), editor/, pdf/ (organize, text, translator),
│                              pages/, admin/, errors/
└── js/
    ├── app.js                 entry – loads one code-split page module
    ├── pages/                 tool.js (generic runner), home, tools-index, organize, text, translator, admin
    ├── tools/                 browser handlers – one module per tool (merge, compress, watermark, ocr, …)
    ├── editor/                PDF editor (page-view, exporter, signature, dialogs, history)
    ├── components/            file-queue, options-form, page-picker, result-stats
    └── lib/                   pdf.js/pdf-lib loaders, content-stream parser, fonts, pptx writer, OCR, …
storage/app/pdf-temp/          temporary job folders (never web accessible)
```

**Processing flow (server engine)**

1. `POST /api/tools/{tool}/jobs` (CSRF + rate limit) → `UploadValidator` checks extension allow-list, block list (also double extensions), size, MIME detected from content and magic bytes → `OptionValidator` validates options against the schema in `config/tools.php`.
2. `JobManager` stores the files as `input-01.pdf` … in a random 40-character job folder, options are stored **encrypted** (they can contain passwords).
3. Light jobs run immediately; heavy jobs go to the queue (`RunToolJob`) when a queue worker is configured. Progress is written to `job.json` and polled through a **signed** status URL.
4. The processor writes one result (several files are zipped). Inputs and intermediate files are deleted immediately.
5. The result is streamed through a **signed, expiring** URL bound to the visitor's session. After download it is removed within a few minutes, otherwise after `PDF_JOB_TTL` minutes. "Delete my files now" removes it instantly.

No database is needed for processing. SQLite (or MySQL) only stores settings, the admin account, anonymous daily usage counters and the queue.

## 3. Requirements

| | Minimum |
|---|---|
| PHP | 8.2 or newer (8.3/8.4 recommended) |
| PHP extensions | `ctype`, `curl`, `dom`, `fileinfo`, `gd` (with JPEG, PNG, WEBP), `iconv`, `mbstring`, `openssl`, `pdo_sqlite` (or `pdo_mysql`), `xml`, `xmlreader`, `xmlwriter`, `zip`, `zlib`; optional: `exif` (photo orientation), `intl`, `imagick` |
| Composer | 2.x |
| Node.js | 20 or newer (only to build the frontend) |
| Database | SQLite (default) or MySQL/MariaDB/PostgreSQL |
| Optional binaries | qpdf, Ghostscript, Poppler-utils, LibreOffice, Tesseract (see §5) |

## 4. Installation (development)

### 4.1 Get the code and dependencies

```bash
git clone https://github.com/knsoftic/ilove-pdf.git pdf-toolkit
cd pdf-toolkit
composer install
npm install
```

### 4.2 Environment

```bash
cp .env.example .env
php artisan key:generate
```

The defaults use SQLite (`database/database.sqlite`), file cache/sessions and the `sync` queue, so nothing else has to be installed.

```bash
touch database/database.sqlite        # Windows: type nul > database\database.sqlite
php artisan migrate
```

### 4.3 Build the frontend

```bash
npm run build      # production build (also copies PDF.js/Tesseract/font assets to public/vendor)
npm run dev        # or: hot-reloading dev server while you work
```

### 4.4 Storage permissions

The web server user must be able to write to `storage/` and `bootstrap/cache/` (and `public/uploads/` for admin logo uploads):

```bash
chmod -R ug+rwX storage bootstrap/cache
mkdir -p public/uploads && chmod -R ug+rwX public/uploads
```

### 4.5 Run it

```bash
php artisan serve
```

Open http://127.0.0.1:8000. For larger uploads start the dev server with bigger PHP limits:

```bash
php -d upload_max_filesize=200M -d post_max_size=210M -d memory_limit=1024M artisan serve
```

> **XAMPP / Windows:** you can also point an Apache virtual host at the `public/` folder. Raise `upload_max_filesize`, `post_max_size` (e.g. 200M), `memory_limit` (1024M) and `max_execution_time` (300) in `C:\xampp\php\php.ini` and restart Apache.

### 4.6 Create the admin account

```bash
php artisan pdf:admin
```

Then sign in at `/admin` (path configurable with `ADMIN_PATH`).

### 4.7 Check what the server can do

```bash
php artisan pdf:doctor
```

Lists every binary, PHP extension, the translation engine and which engine each tool uses.

## 5. Server binaries (optional, recommended)

Everything is optional – missing software only removes the *server* engine of a tool. Installing the binaries gives the best results (real compression, Office conversions, server OCR, faster page tools).

| Software | Used for |
|---|---|
| **qpdf** | merge, split, page tools, protect/unlock (AES-256), optimize/linearize, flatten |
| **Ghostscript** | compression (image downsampling), grayscale, page rendering |
| **Poppler-utils** (`pdftotext`, `pdftoppm`, `pdfimages`, `pdftohtml`) | text/table extraction, fast page rendering, image extraction, PDF → HTML |
| **LibreOffice** | Word/Excel/PowerPoint → PDF (best fidelity), "keep layout" PDF → Word |
| **Tesseract OCR** + language packs | server-side OCR |

**Ubuntu / Debian**

```bash
sudo apt update
sudo apt install -y qpdf ghostscript poppler-utils libreoffice-core libreoffice-writer libreoffice-calc libreoffice-impress \
     tesseract-ocr tesseract-ocr-eng tesseract-ocr-urd tesseract-ocr-hin tesseract-ocr-ara tesseract-ocr-chi-sim \
     tesseract-ocr-spa tesseract-ocr-fra tesseract-ocr-deu tesseract-ocr-ita tesseract-ocr-por tesseract-ocr-rus \
     tesseract-ocr-tur tesseract-ocr-ben tesseract-ocr-fas tesseract-ocr-ind tesseract-ocr-msa fonts-noto-core
```

**macOS (Homebrew)**

```bash
brew install qpdf ghostscript poppler tesseract tesseract-lang
brew install --cask libreoffice
```

**Windows**

```powershell
winget install QPDF.QPDF
winget install ArtifexSoftware.GhostScript
winget install TheDocumentFoundation.LibreOffice
winget install UB-Mannheim.TesseractOCR
# Poppler: download the latest release from https://github.com/oschwartz10612/poppler-windows/releases,
# extract it to C:\poppler and add C:\poppler\Library\bin to PATH.
```

The binaries are found automatically on the `PATH` and in the usual install folders (`C:\Program Files\gs\…`, `C:\Program Files\LibreOffice\…`, `C:\Program Files\Tesseract-OCR`, …). Otherwise set the full path in `.env` (e.g. `GHOSTSCRIPT_PATH="C:\Program Files\gs\gs10.04.0\bin\gswin64c.exe"`), then run `php artisan pdf:doctor` (or *Admin → System → Detect software again*). Detection results are cached for one hour.

## 6. Environment configuration

All PDF Toolkit settings live in `config/pdftoolkit.php` and can be set from `.env`:

| Key | Default | Description |
|---|---|---|
| `APP_NAME` | PDF Toolkit | default site name (can be changed in the admin) |
| `APP_URL` | | public URL – required for correct canonical/sitemap URLs |
| `PDF_TEMP_PATH` | `storage/app/pdf-temp` | temporary job folders (must not be web accessible) |
| `PDF_JOB_TTL` | 30 | minutes until a job and its result are deleted |
| `PDF_DOWNLOADED_TTL` | 5 | minutes a result is kept after it was downloaded |
| `PDF_MAX_UPLOAD_MB` | 100 | upload limit per file (also capped by PHP limits) |
| `PDF_MAX_FILES` | 30 | maximum files per task |
| `PDF_MAX_PAGES` | 1000 | maximum pages for server processing |
| `PDF_PROCESS_TIMEOUT` | 300 | seconds per external command |
| `PDF_ENGINE_PREFERENCE` | browser | `browser` or `server` (also in the admin) |
| `PDF_QUEUE_NAME` | pdf | queue used for heavy jobs |
| `PDF_RATE_JOBS` / `PDF_RATE_STATUS` / `PDF_RATE_DOWNLOADS` / `PDF_RATE_TEXT` | 30 / 240 / 60 / 60 | requests per minute per IP |
| `PDF_BIND_JOBS_TO_SESSION` | true | results can only be fetched from the browser session that uploaded them |
| `PDF_QUEUE_CONNECTION` | default connection | queue connection for heavy jobs |
| `QPDF_PATH`, `GHOSTSCRIPT_PATH`, `PDFTOTEXT_PATH`, `PDFTOPPM_PATH`, `PDFIMAGES_PATH`, `PDFTOHTML_PATH`, `LIBREOFFICE_PATH`, `TESSERACT_PATH` | auto | full paths to binaries |
| `TRANSLATION_DRIVER` | none | `libretranslate`, `argos` or `none` |
| `LIBRETRANSLATE_URL` / `LIBRETRANSLATE_API_KEY` | http://127.0.0.1:5000 | self-hosted LibreTranslate |
| `ARGOS_TRANSLATE_PATH` | argos-translate | Argos CLI binary |
| `OCR_BROWSER_LANG_PATH` | jsDelivr CDN | where browsers download Tesseract language data |
| `ADMIN_PATH` | admin | URL path of the admin panel |
| `SECURITY_HSTS` / `SECURITY_CSP` | false | Strict-Transport-Security / Content-Security-Policy headers |
| `TRUSTED_PROXIES` | 127.0.0.1 | reverse proxies whose `X-Forwarded-*` headers are trusted |

## 7. Queue

Small installations can keep `QUEUE_CONNECTION=sync` – jobs then run inside the upload request. For production use a real queue so heavy jobs (OCR, Office conversion, compression) never block web workers and show live progress:

```dotenv
QUEUE_CONNECTION=database     # or redis
```

```bash
php artisan queue:work --queue=pdf,default --timeout=400 --tries=1 --max-jobs=200
```

Supervisor example (`/etc/supervisor/conf.d/pdf-toolkit.conf`):

```ini
[program:pdf-toolkit-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/pdf-toolkit/artisan queue:work --queue=pdf,default --timeout=400 --tries=1 --max-jobs=200 --sleep=1
user=www-data
numprocs=2
autostart=true
autorestart=true
stopwaitsecs=420
stdout_logfile=/var/www/pdf-toolkit/storage/logs/worker.log
```

Restart workers after each deployment: `php artisan queue:restart`.

## 8. Scheduler & temporary file cleanup

Expired uploads/results are deleted by `php artisan pdf:cleanup` (scheduled every five minutes). Add one cron entry:

```cron
* * * * * cd /var/www/pdf-toolkit && php artisan schedule:run >> /dev/null 2>&1
```

Windows: create a Task Scheduler task that runs `php C:\path\to\pdf-toolkit\artisan schedule:run` every minute.

Manual use:

```bash
php artisan pdf:cleanup            # delete expired job folders
php artisan pdf:cleanup --dry-run  # show what would be deleted
php artisan pdf:cleanup --all      # delete everything (e.g. before maintenance)
```

As a safety net, 2 % of web requests also trigger an expired-file cleanup after the response is sent (`cleanup_lottery` in `config/pdftoolkit.php`), so storage stays clean even on hosting without cron. Additionally, inputs are deleted immediately after processing and visitors can delete results instantly.

## 9. Translation engine

The translator never uses paid cloud APIs. Choose one of:

**LibreTranslate (recommended, self-hosted, open source)**

```bash
docker run -d --name libretranslate -p 5000:5000 libretranslate/libretranslate \
  --load-only en,ur,hi,ar,zh,es,fr,de,it,pt,ru,tr,bn,fa,id,ms
# or: pip install libretranslate && libretranslate --load-only en,ur,hi,ar,...
```

```dotenv
TRANSLATION_DRIVER=libretranslate
LIBRETRANSLATE_URL=http://127.0.0.1:5000
```

**Argos Translate (offline CLI)**

```bash
pip install argostranslate
argospm update && argospm install translate-en_ur translate-ur_en   # one package per language pair
```

```dotenv
TRANSLATION_DRIVER=argos
ARGOS_TRANSLATE_PATH=/usr/local/bin/argos-translate
```

**No server engine:** the page uses the browser's built-in on-device translator when available (Chrome 138+ on desktop). Language detection always works (built-in detector for all 16 languages).

**Other engines:** implement `App\Services\Translation\Contracts\Translator` (e.g. a client for your own NLLB / Opus-MT server) and register it in `TranslationManager::driver()`. Language codes per engine can be mapped in `pdftoolkit.translation.engine_codes`.

Translated PDFs are generated with mPDF, which ships fonts for Latin, Cyrillic, Arabic/Urdu/Persian (with right-to-left shaping), Devanagari, Bengali and CJK.

## 10. OCR

* **Server:** Tesseract with Ghostscript or pdftoppm (and qpdf to assemble searchable PDFs). Install the language packs you need (`tesseract --list-langs`).
* **Browser:** Tesseract.js – the worker and WebAssembly core are served from `public/vendor/tesseract`. Language data (≈ 2–15 MB per language) is downloaded from the jsDelivr CDN by default. To self-host it, download the `*.traineddata.gz` files (e.g. from https://github.com/naptha/tessdata/tree/gh-pages/4.0.0_best) into `public/tessdata/` and set `OCR_BROWSER_LANG_PATH=/tessdata`.

## 11. Admin panel

* Create or reset an admin: `php artisan pdf:admin --email=you@example.com --name="Your Name"` (the password is prompted).
* Visit `/{ADMIN_PATH}` – change `ADMIN_PATH` to something hard to guess.
* Admins are stored in the `admins` table and use their own `admin` guard; the public site has no accounts. Login is rate limited.
* Pages: **Dashboard** (usage per day, top tools, SEO score, ad status, temporary storage, capability warnings), **Settings** (name, logo, favicon, social image, upload limits, allowed file types, processing preference, privacy notice, footer), **Tools** (enable/disable, rename), **SEO** (see below), **Advertising** (see below), **Pages** (privacy policy, terms, about in Markdown), **System** (binaries, PHP limits, queue, cleanup), **Account**.

### 11.1 Advertising (Adsterra, Monetag, AdSense, any network)

*Admin → Advertising* manages **ad units**. Each unit has a network, a format, the code (or URL), a position, device targeting and page targeting.

| Format | Networks | Default position |
|---|---|---|
| Popunder / OnClick | Adsterra, Monetag | `<head>` |
| Social Bar / In-Page Push | Adsterra, Monetag | before `</body>` |
| MultiTag, Push Notifications, Vignette | Monetag | `<head>` |
| Display banner (728×90, 468×60, 300×250, 320×50, 160×600, 160×300) | Adsterra, AdSense | visible position |
| Native banner | Adsterra, AdSense | visible position |
| Direct Link / Smartlink | Adsterra, Monetag | opens in a new tab on “Download” clicks (frequency-capped per visitor) or a sponsored button |
| Auto ads, custom HTML/JS | AdSense, any | any |

* **Visible positions:** below the header, home page (between sections), all-tools page, tool pages (above the upload area, below the tool/result, between FAQ and related tools) and above the footer. Visible ads are never shown on error pages.
* **Targeting:** all devices / desktop & tablet / mobile phones (decided on the server, so desktop banners are not even loaded on phones); all pages / home / all tool pages / only selected tools / all except selected tools (e.g. keep the Social Bar away from the PDF editor).
* **Quick add** buttons pre-fill the form for every Adsterra and Monetag format with the recommended position and setup hints.
* **Preview ad positions** shows labelled placeholders for every position on the live site (only for you while signed in).
* **ads.txt** is edited in the admin and served at `/ads.txt`.
* **Service worker** – Monetag Push Notifications and site verification need a file such as `/sw.js` at the site root: paste its content in *Advertising → Settings*; it is served with `Service-Worker-Allowed: /`.
* **Monetag site verification** via meta tag: *SEO → Verification → Monetag*.
* Options: master switch, “Advertisement” label, hide ads while you are signed in (avoids invalid self-impressions).
* When ads are active, `Cross-Origin-Opener-Policy` allows popups and an enabled `SECURITY_CSP` is relaxed automatically so ad scripts can load.

### 11.2 SEO

*Admin → SEO* has these sections:

* **Overview** – overall SEO score, a site checklist (indexing allowed, APP_URL/HTTPS, home title/description length, Search Console/Bing verification, social image, duplicate titles, broken links) and the tool pages that need work first.
* **General** – title separator and “append site name”, `og:locale`, X/Twitter handle, social profiles (Organization `sameAs`), “discourage search engines” switch for staging sites.
* **Verification & analytics** – Google Search Console, Bing, Yandex, Baidu, Pinterest, Facebook and Monetag verification (paste the meta tag or its value), Google Analytics 4 ID, Google Tag Manager ID, other analytics code, custom `<head>` and before-`</body>` code.
* **Pages** – title/description/keywords of the home page and the all-tools page (`{count}` placeholder), title/description/noindex for About, Privacy and Terms.
* **Tool pages** – per tool: focus keyword, SEO title, meta description, keywords, H1, short description, a **Markdown article** shown below the tool, **custom FAQ** questions (also output as `FAQPage` structured data), noindex. Includes a live Google preview, character counters and a score checklist.
* **Redirects & 404** – 301/302/410 redirects (e.g. for renamed or disabled tools) and a 404 monitor that counts missing URLs (path and counter only; scanner noise such as `/wp-login.php` is ignored) with one-click “Redirect”.
* **Sitemap & robots** – include/exclude the legal pages, extra `robots.txt` rules (e.g. block AI crawlers), and **IndexNow** (free instant indexing for Bing, Yandex, Seznam, Naver): create a key (served at `/{key}.txt`) and submit all sitemap URLs with one click once the site runs on its public domain.

After going live: set `APP_URL` to your `https://` domain, verify the site in Google Search Console and Bing Webmaster Tools and submit `https://your-domain/sitemap.xml`.

## 12. Production deployment

> **aaPanel:** step-by-step guide (Roman Urdu) with the exact aaPanel menu paths, PHP/Nginx settings, cron, Supervisor and SSL: [docs/DEPLOY-AAPANEL.md](docs/DEPLOY-AAPANEL.md). Updates on the server: `bash scripts/deploy.sh`.

```bash
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan pdf:doctor
```

`.env`: `APP_ENV=production`, `APP_DEBUG=false` (raw errors are never shown to visitors), correct `APP_URL` with `https://`, `SECURITY_HSTS=true` once HTTPS works.

**php.ini**

```ini
upload_max_filesize = 200M
post_max_size = 210M
memory_limit = 1024M
max_execution_time = 300
```

**nginx**

```nginx
server {
    listen 443 ssl http2;
    server_name pdf.example.com;
    root /var/www/pdf-toolkit/public;
    index index.php;

    client_max_body_size 210M;

    location / { try_files $uri $uri/ /index.php?$query_string; }

    location ~ \.php$ {
        include fastcgi_params;
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        fastcgi_read_timeout 330;
    }

    # Long-lived caching for hashed build assets and vendor files
    location ~* ^/(build|vendor)/ { expires 30d; add_header Cache-Control "public, immutable"; }

    # Never execute anything in uploads
    location ^~ /uploads/ { location ~ \.php$ { deny all; } }

    location ~ /\.(?!well-known) { deny all; }
}
```

**Apache:** point the virtual host `DocumentRoot` to `public/` (the included `public/.htaccess` handles routing) and set `LimitRequestBody 220200960`.

After every deployment: `php artisan queue:restart` and clear/rebuild caches.

## 13. Security

* Uploads: extension allow-list per tool, global block list (`php`, `phtml`, `phar`, `svg`, …, also inside double extensions such as `x.php.pdf`), size limit, file count limit, MIME type detected from the content, **magic-byte signature check**, scan for embedded PHP tags, decompression-bomb limit for images.
* Storage: random 40-character folder names, random file names, original names never touch the disk, folder outside `public/` (plus a deny-all `.htaccess`), job ids validated by regex → no path traversal.
* Access: signed and expiring status/download URLs bound to the visitor's session, CSRF protection on every form/API call, rate limiting for jobs, status polling, downloads, text APIs and admin login.
* Processing: external programs run without a shell (argument arrays), with timeouts; passwords are passed to qpdf through a private argument file, not the process list; Ghostscript always runs with `-dSAFER`; HTML → PDF strips scripts and every remote/local resource (SSRF protection); options containing passwords are stored encrypted and deleted after processing.
* Output: downloads are sent with `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff` and a sandboxing CSP; CSV output is protected against formula injection.
* Headers: `X-Frame-Options`, `Referrer-Policy`, `Permissions-Policy`, `Cross-Origin-Opener-Policy: same-origin-allow-popups`, optional HSTS and CSP (strict by default, relaxed automatically while ads or analytics are configured).
* Errors: visitors only see friendly messages (*Invalid PDF*, *File too large*, *Password protected PDF*, …); details are logged.
* Admin: separate guard, hashed passwords, rate-limited login, `noindex` headers, configurable path, SVG uploads refused.

## 14. Adding a new tool

1. Add an entry to `config/tools.php` (name, category, icon, `accept`, `engines`, options schema, SEO texts). The route, tool page, sitemap entry, upload/option validation and admin toggle are generated automatically.
2. Server engine: create `app/Services/Pdf/Processors/YourProcessor.php` extending `AbstractProcessor` – implement `handle(JobContext $context): ProcessResult` and, if it needs binaries, `requirements()`.
3. Browser engine: create `resources/js/tools/your-tool.js` (set `'browser' => 'your-tool'` in the registry entry) exporting `async function run({ files, options, params, progress, signal })` that returns `{ blob, filename, stats }` – or `{ files: [{ name, blob }, …] }`, which is zipped automatically. Optional hooks: `setup(ctx)` (called once on page load) and `onFiles(ctx)` (called when the file list changes).
4. `npm run build`.

## 15. Testing

```bash
php artisan test
```

The suite covers all public pages and SEO tags, every tool page, server processing end-to-end (upload → job → signed download), upload security (fake PDFs, PHP polyglots, double extensions, size limits), option validation, session binding, file deletion, cleanup, text export, language detection, LibreTranslate integration (HTTP fake), the admin panel and the core services (page ranges, layout/table analysis, HTML sanitizer, Unicode/RTL handling, image → PDF writer).

## 16. Known limitations

These are inherent to the PDF format or to the browser and are communicated in the UI where relevant:

* **Edit text / Replace text** cover the original text and write new text on top. The original characters remain in the file (this is not a secure redaction tool), and the replacement uses a standard font close to the original.
* **Remove watermark** works for watermark annotations, tagged watermark artifacts, watermark layers and text that matches the entered string. Watermarks that are part of a scanned image cannot be removed.
* **PDF → Word** (default mode) rebuilds editable paragraphs, headings and tables; complex magazine layouts are simplified. With LibreOffice installed the *Keep layout* mode is available.
* **PowerPoint → PDF** requires LibreOffice on the server.
* **Browser PDF → HTML (reflow mode)** may merge table columns when PDF.js combines text runs; the server engine and the *Keep layout* mode are exact.
* **Text added in the browser editor** in Arabic-script languages is embedded without contextual shaping (PDF.js/pdf-lib limitation). Translated PDFs created on the server (mPDF) are shaped correctly.
* **Browser OCR** downloads language data on first use unless self-hosted (§10).
* **Browser translation** needs the on-device Translator API (Chrome desktop); otherwise configure LibreTranslate or Argos.
* **Compression in the browser** recompresses embedded images (JPEG/Flate RGB & gray). Ghostscript on the server compresses more (fonts, vector data, all image types).

## 17. Troubleshooting

| Problem | Solution |
|---|---|
| "This file is too large" although it is small | PHP `upload_max_filesize` / `post_max_size` are lower than the admin limit – raise them (see §12). |
| A tool says *temporarily unavailable* | Required software is missing – run `php artisan pdf:doctor` and install it (§5) or disable the tool in the admin. |
| Binaries installed but not detected | Set the full path in `.env`, then *Admin → System → Detect software again* (detection is cached for one hour). |
| Jobs stay "Waiting in queue" | No worker is running – start `php artisan queue:work --queue=pdf,default` or use `QUEUE_CONNECTION=sync`. |
| Blank tool pages / missing PDF.js files | Run `npm run build` (it also copies `public/vendor`). |
| 419 "session expired" | The page was open too long – reload. Check that `SESSION_DOMAIN` / `APP_URL` match the domain. |
| LibreOffice conversions hang | Make sure the web user has a writable home directory and that no other `soffice` process with the same profile is running (the app uses a fresh profile per job). |

## 18. Licenses

The application code is released under the MIT license. It relies on these open-source projects:

| Project | License |
|---|---|
| Laravel | MIT |
| PDF.js | Apache-2.0 |
| pdf-lib (Cantoo fork) & @cantoo/fontkit | MIT |
| Fabric.js | MIT |
| Tesseract.js / Tesseract OCR | Apache-2.0 |
| SortableJS, JSZip, fflate | MIT (JSZip: MIT/GPLv3) |
| Tailwind CSS | MIT |
| smalot/pdfparser, PHPWord, PHPPresentation | LGPL-3.0 |
| PhpSpreadsheet | MIT |
| mPDF | GPL-2.0 |
| DejaVu fonts, Dancing Script / Great Vibes / Caveat (Fontsource), Inter | Free / SIL OFL |
| qpdf | Apache-2.0 |
| Ghostscript | AGPL-3.0 |
| Poppler | GPL-2.0 |
| LibreOffice | MPL-2.0 |
| LibreTranslate | AGPL-3.0 · Argos Translate: MIT |

Make sure your use of the server binaries complies with their licenses (e.g. AGPL for Ghostscript when you modify it).
