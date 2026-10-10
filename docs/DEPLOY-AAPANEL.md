# aaPanel par deployment guide – ilove-pdf.site

Ye guide **PDF Toolkit** ko aaPanel wale Linux server par live karne ka poora tareeqa batati hai.

| | |
|---|---|
| Domain | `ilove-pdf.site` (aur `www.ilove-pdf.site`) |
| Site folder | `/www/wwwroot/ilove-pdf.site` |
| Code | `https://github.com/knsoftic/ilove-pdf.git` (branch `main`) |
| PHP | 8.3 (kam se kam 8.2) |
| Web server | Nginx |
| Database | SQLite (default, kuch install nahi karna) – ya MySQL |

> Har jagah PHP ka poora path `/www/server/php/83/bin/php` likha gaya hai. Agar aap PHP 8.2 install karein to `83` ki jagah `82` likhein.

---

## Jaldi checklist

1. [Server tayyar karein](#1-server-aur-domain): VPS + aaPanel + domain ka DNS
2. [aaPanel App Store](#2-aapanel-app-store-se-software): Nginx, PHP 8.3 (+ optional MySQL, Supervisor)
3. [PHP settings](#3-php-83-settings): extensions, **disabled functions**, upload limits
4. [Nginx upload limit](#4-nginx-upload-limit)
5. [Server software](#5-ssh-se-server-software-install-karein): git, Node.js, Composer, qpdf, Ghostscript, Poppler, Tesseract, LibreOffice
6. [Website banayein](#6-aapanel-mein-website-banayein)
7. [Code clone karein](#7-github-se-code-download-karein)
8. [.env file](#8-env-file-banayein)
9. [Install / build](#9-install-aur-build-deploy-script)
10. [Site settings](#10-website-settings-running-directory-rewrite-ssl): running directory `/public`, URL rewrite, SSL
11. [Cron job](#11-cron-job-scheduler--temp-files-ki-safai) (temporary files ki safai)
12. [Queue worker](#12-queue-worker-optional-recommended) (optional)
13. [Admin account](#13-admin-account-aur-pehli-settings) aur pehli settings
14. [Update ka tareeqa](#14-naya-code-update-karna)

---

## 1. Server aur domain

- **VPS:** Ubuntu 22.04 / 24.04 ya Debian 12 (recommended). Kam se kam **2 GB RAM**. 1 GB par bhi chal jayega, magar [swap](#swap-1-gb-ram-wale-server-ke-liye) zaroor banayein.
- **aaPanel** install hona chahiye (https://www.aapanel.com).
- **DNS:** domain provider par do `A` records bana kar server ka IP dein:
  - `ilove-pdf.site` → `SERVER_IP`
  - `www.ilove-pdf.site` → `SERVER_IP`

DNS update hone mein kuch minute se kuch ghante lag sakte hain. SSL tab hi banega jab domain server par point kar raha ho.

---

## 2. aaPanel App Store se software

**App Store** mein ye install karein:

| Software | Zaroori? | Kis liye |
|---|---|---|
| **Nginx** (latest stable) | Haan | Web server |
| **PHP-8.3** | Haan | Laravel app |
| MySQL 8 / MariaDB | Nahi | Sirf agar SQLite ki jagah MySQL chahiye |
| Supervisor manager | Nahi (recommended) | Queue worker ([step 12](#12-queue-worker-optional-recommended)) |
| Redis | Nahi | Zaroorat nahi |

---

## 3. PHP 8.3 settings

**App Store → PHP-8.3 → Setting** kholein.

### 3.1 Install extensions

**Install extensions** tab mein ye install karein:

- **fileinfo** – **zaroori** (upload validation isi se hoti hai; aaPanel mein default off hota hai, compile hone mein kuch minute lagte hain)
- **opcache** – recommended (speed)
- **exif** – recommended (mobile photos ka sahi rukh)
- intl, imagick – optional

Baqi zaroori extensions (`gd`, `zip`, `mbstring`, `xml`, `dom`, `curl`, `openssl`, `pdo_sqlite`/`pdo_mysql`, `iconv`, `zlib`) aaPanel ki PHP mein pehle se hoti hain. SSH se check karein:

```bash
/www/server/php/83/bin/php -m
```

```bash
/www/server/php/83/bin/php -r 'print_r(gd_info());'
```

`gd_info()` mein `JPEG Support`, `PNG Support` aur `WebP Support` teeno `1` hone chahiye.

### 3.2 Disabled functions (bohat zaroori)

aaPanel security ke liye kuch PHP functions band rakhta hai. Ye app PDF banane ke liye `qpdf`, `gs`, `tesseract`, `soffice` jaise programs chalati hai, aur Composer/queue ko bhi kuch functions chahiye.

**Disabled functions** tab mein in functions ko list se **delete** karein (yani enable karein):

| Function | Kis ko chahiye |
|---|---|
| `proc_open` | Server tools (qpdf, Ghostscript, Tesseract, LibreOffice), cron scheduler, Composer |
| `putenv` | Composer, Laravel |
| `pcntl_signal` | Queue worker |
| `pcntl_alarm` | Queue worker |

Baqi functions (`exec`, `system`, `shell_exec`, `passthru`…) band hi rehne dein. App unhein use nahi karti.

### 3.3 Upload aur timeout limits

**Configuration modify**, **Upload limit** aur **Timeout limit** tabs mein ye values set karein (ya **Config file** tab mein php.ini mein):

```ini
upload_max_filesize = 200M
post_max_size = 210M
memory_limit = 1024M
max_execution_time = 300
max_input_time = 300
```

Save karke **Service → Restart** karein.

---

## 4. Nginx upload limit

**App Store → Nginx → Setting → Performance tuning** mein:

- `client_max_body_size` = **210m**

Save → Nginx restart. Is ke baghair bari files par “413 Request Entity Too Large” aata hai.

---

## 5. SSH se server software install karein

aaPanel ka **Terminal** kholein (ya SSH se login karein). Neeche ki commands **root** ke taur par chalayein. Ye Ubuntu/Debian ke liye hain.

### 5.1 Basic tools aur PDF programs

```bash
apt update
apt install -y git unzip curl sudo \
    qpdf ghostscript poppler-utils \
    tesseract-ocr tesseract-ocr-eng tesseract-ocr-urd tesseract-ocr-hin tesseract-ocr-ara \
    tesseract-ocr-chi-sim tesseract-ocr-spa tesseract-ocr-fra tesseract-ocr-deu tesseract-ocr-ita \
    tesseract-ocr-por tesseract-ocr-rus tesseract-ocr-tur tesseract-ocr-ben tesseract-ocr-fas \
    tesseract-ocr-ind tesseract-ocr-msa \
    fonts-dejavu-core fonts-noto-core
```

### 5.2 LibreOffice (Word/Excel/PowerPoint → PDF)

Server ke liye bina GUI wala version:

```bash
apt install -y libreoffice-writer-nogui libreoffice-calc-nogui libreoffice-impress-nogui
```

Agar `-nogui` packages na milein (purana OS), to ye chalayein:

```bash
apt install -y libreoffice-writer libreoffice-calc libreoffice-impress
```

> LibreOffice ke baghair bhi Word/Excel → PDF chal jata hai (PHP se). Sirf **PowerPoint → PDF** ko LibreOffice chahiye.

### 5.3 Node.js 22 (frontend build ke liye)

```bash
curl -fsSL https://deb.nodesource.com/setup_22.x | bash -
apt install -y nodejs
node -v
```

`node -v` se `v22.x` aana chahiye. Node 20 bhi chalta hai, magar `20.19` ya us se naya hona chahiye.

### 5.4 Composer

```bash
curl -sS https://getcomposer.org/installer | /www/server/php/83/bin/php -- --install-dir=/usr/local/bin --filename=composer
composer --version
```

### Swap (1 GB RAM wale server ke liye)

`npm run build` ko memory chahiye. Kam RAM par 2 GB swap bana lein:

```bash
fallocate -l 2G /swapfile && chmod 600 /swapfile && mkswap /swapfile && swapon /swapfile
echo '/swapfile none swap sw 0 0' >> /etc/fstab
```

---

## 6. aaPanel mein website banayein

**Website → Add site**:

| Field | Value |
|---|---|
| Domain name | `ilove-pdf.site` aur nayi line mein `www.ilove-pdf.site` |
| Root directory | `/www/wwwroot/ilove-pdf.site` |
| FTP | Not create |
| Database | **SQLite ke liye:** Not create · **MySQL ke liye:** MySQL chunein aur naam/user/password note kar lein |
| PHP version | **PHP-83** |

**Submit** dabayein.

---

## 7. GitHub se code download karein

aaPanel naye folder mein `index.html`, `404.html` aur ek locked `.user.ini` rakh deta hai. `git clone` ke liye folder khaali hona chahiye:

```bash
cd /www/wwwroot/ilove-pdf.site
chattr -i .user.ini 2>/dev/null; rm -f .user.ini .htaccess index.html 404.html
git config --global --add safe.directory /www/wwwroot/ilove-pdf.site
git clone https://github.com/knsoftic/ilove-pdf.git .
```

(Aakhir mein `.` zaroor lagayein, taake code isi folder mein aaye.) Agar “destination path already exists and is not an empty directory” aaye, to `ls -A` se bachi hui files dekh kar delete karein.

### Agar GitHub repo private hai

Server ke liye ek **deploy key** banayein:

```bash
ssh-keygen -t ed25519 -f /root/.ssh/ilove_pdf_deploy -N ""
cat /root/.ssh/ilove_pdf_deploy.pub
```

Ye public key GitHub par **Repo → Settings → Deploy keys → Add deploy key** mein paste karein (sirf read access kaafi hai). Phir:

```bash
cat >> /root/.ssh/config <<'EOF'
Host github-ilove-pdf
    HostName github.com
    User git
    IdentityFile /root/.ssh/ilove_pdf_deploy
    IdentitiesOnly yes
EOF
git clone git@github-ilove-pdf:knsoftic/ilove-pdf.git .
```

---

## 8. .env file banayein

```bash
cd /www/wwwroot/ilove-pdf.site
cp .env.example .env
```

Ab `.env` edit karein. aaPanel **Files** mein file khol sakte hain, ya terminal se `nano .env` chalayein. Ye values badlein:

```dotenv
APP_NAME="PDF Toolkit"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://ilove-pdf.site

LOG_STACK=daily
LOG_LEVEL=warning

SESSION_SECURE_COOKIE=true

# Admin panel ka secret URL (khud ka mushkil naam rakhein)
ADMIN_PATH=control-panel-7k2x

# HTTPS (SSL lagne ke baad true rakhein)
SECURITY_HSTS=true

# Server programs ke poore paths (Ubuntu/Debian)
QPDF_PATH=/usr/bin/qpdf
GHOSTSCRIPT_PATH=/usr/bin/gs
PDFTOTEXT_PATH=/usr/bin/pdftotext
PDFTOPPM_PATH=/usr/bin/pdftoppm
PDFIMAGES_PATH=/usr/bin/pdfimages
PDFTOHTML_PATH=/usr/bin/pdftohtml
LIBREOFFICE_PATH=/usr/bin/soffice
TESSERACT_PATH=/usr/bin/tesseract
```

- `APP_KEY` khaali chhor dein. Deploy script use khud bana dega.
- Website ka naam, logo aur SEO baad mein admin panel se bhi badal sakte hain.
- Jo program install nahi kiya, uski line khaali chhor dein.
- `SESSION_SECURE_COOKIE=true` ki wajah se SSL lagne tak ([10.4](#104-ssl-https)) admin login ya forms “session expired” de sakte hain. SSL ke baad ye khud theek ho jata hai.

### Database

**SQLite (default):** `.env` mein `DB_CONNECTION=sqlite` rehne dein. Database file script khud banata hai (`database/database.sqlite`). Pehle check kar lein ke PHP mein SQLite hai:

```bash
/www/server/php/83/bin/php -m | grep -i sqlite
```

Is mein `pdo_sqlite` aana chahiye.

**MySQL (optional):** aaPanel **Databases** mein bana hua database use karein:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ilove_pdf
DB_USERNAME=ilove_pdf
DB_PASSWORD=aapka_database_password
```

### Cloudflare use kar rahe hain?

Agar domain Cloudflare ke orange cloud (proxy) ke peeche hai:

- Cloudflare mein **SSL/TLS → Full (strict)** set karein.
- `.env` mein `TRUSTED_PROXIES=*` set karein. Isi se HTTPS aur visitor ka asli IP sahi pehchana jata hai.

---

## 9. Install aur build (deploy script)

Saara install ek script se hota hai:

```bash
cd /www/wwwroot/ilove-pdf.site
bash scripts/deploy.sh
```

Script ye kaam karta hai:

1. `composer install --no-dev`
2. `APP_KEY` banana (agar khaali ho)
3. SQLite file banana (agar SQLite chuna ho)
4. `npm ci` + `npm run build`. Is se CSS/JS, aur PDF.js/Tesseract ki files `public/vendor` mein copy hoti hain.
5. `php artisan migrate --force` (database tables)
6. `php artisan optimize` (config, routes, views ka cache)
7. Queue restart
8. `pdf:doctor` – batata hai kaun se programs mile
9. Har file ka owner `www:www` karna

Agar PHP ka version ya path alag hai:

```bash
PHP_BIN=/www/server/php/82/bin/php bash scripts/deploy.sh
```

---

## 10. Website settings (running directory, rewrite, SSL)

**Website →** `ilove-pdf.site` par click karein (settings khulengi).

### 10.1 Site directory

- **Running directory:** `/public` chunein → **Save**
- **Anti-XSS attack (open_basedir):** **band** karein (checkbox hatayein)

`open_basedir` on ho to PHP `/usr/bin/qpdf` jaise programs tak nahi pahunch pati, aur server tools “unavailable” dikhte hain.

### 10.2 URL rewrite

**URL rewrite** mein dropdown se **laravel5** chunein → **Save**. Is ka matlab ye hai:

```nginx
location / {
    try_files $uri $uri/ /index.php$is_args$query_string;
}
```

### 10.3 Config file (Nginx)

**Config** tab mein site ki Nginx config khulti hai. Do kaam karein:

**a) Timeout aur security** – `server { ... }` ke andar `#PHP-INFO-END` wali line ke **neeche** add karein:

```nginx
    client_max_body_size 210m;
    fastcgi_read_timeout 330;

    # Uploads folder mein kabhi PHP na chale
    location ^~ /uploads/ {
        location ~ \.php$ { deny all; }
    }
```

**b) Monetag `sw.js` ke liye (sirf agar Push Notifications use karenge)** – aaPanel ki config mein `.js` files ke liye ek block hota hai, jo `/sw.js` ko Laravel tak pahunchne nahi deta. Ye block dhoondein:

```nginx
    location ~ .*\.(js|css)?$
    {
        expires      12h;
        error_log /dev/null;
        access_log off;
    }
```

Aur is ke andar ek line add karein:

```nginx
    location ~ .*\.(js|css)?$
    {
        try_files $uri /index.php$is_args$query_string;
        expires      12h;
        error_log /dev/null;
        access_log off;
    }
```

**Save** dabayein. aaPanel config ko test karke Nginx reload kar deta hai.

### 10.4 SSL (HTTPS)

**SSL → Let's Encrypt**:

1. Dono domains (`ilove-pdf.site`, `www.ilove-pdf.site`) select karein → **Apply**
2. Certificate ban jaye to **Force HTTPS** on karein.

aaPanel certificate khud renew karta hai.

### 10.5 www ko non-www par bhejein (SEO)

Google ko ek hi address dikhna chahiye. **Redirect → Add redirect**:

| Field | Value |
|---|---|
| Redirect type | Domain |
| Redirect domain | `www.ilove-pdf.site` |
| Target URL | `https://ilove-pdf.site` |
| Redirect method | 301 |

---

## 11. Cron job (scheduler – temp files ki safai)

Uploaded aur processed files har 5 minute baad khud delete hoti hain. Is ke liye scheduler ka chalna zaroori hai.

**Cron → Add Task**:

| Field | Value |
|---|---|
| Type of task | Shell Script |
| Name | `ilove-pdf scheduler` |
| Execution cycle | N Minutes → **1** |
| Script content | neeche wali line |

```bash
sudo -u www /www/server/php/83/bin/php /www/wwwroot/ilove-pdf.site/artisan schedule:run >> /dev/null 2>&1
```

> `sudo -u www` zaroori hai. Root se chalane par log aur cache files root ki ban jati hain, aur website unhein likh nahi pati.

---

## 12. Queue worker (optional, recommended)

Queue worker ke baghair bhi sab tools chalte hain (`QUEUE_CONNECTION=sync`). Worker lagane se OCR, Office conversion aur compression jaise bhari kaam alag process mein chalte hain. Website tez rehti hai aur progress bhi nazar aati hai.

1. `.env` mein ye line badlein:

   ```dotenv
   QUEUE_CONNECTION=database
   ```

   Phir ye chalayein:

   ```bash
   bash scripts/deploy.sh
   ```

2. **App Store → Supervisor manager → Add daemon**:

   | Field | Value |
   |---|---|
   | Name | `ilove-pdf-worker` |
   | Run user | `www` |
   | Run dir | `/www/wwwroot/ilove-pdf.site` |
   | Start command | neeche wali line |
   | Processes | `2` |

   ```bash
   /www/server/php/83/bin/php artisan queue:work --queue=pdf,default --timeout=400 --tries=1 --max-jobs=200 --sleep=1
   ```

Deploy script har update par workers ko restart kar deta hai.

---

## 13. Admin account aur pehli settings

Admin account banayein. Ye command email, naam aur password poochegi:

```bash
cd /www/wwwroot/ilove-pdf.site
sudo -u www /www/server/php/83/bin/php artisan pdf:admin
```

Phir browser mein `https://ilove-pdf.site/control-panel-7k2x` kholein. Yahan `control-panel-7k2x` ki jagah apna `ADMIN_PATH` likhein. Login karke:

1. **System:** “Detect software again” dabayein. qpdf, Ghostscript, Poppler, Tesseract, LibreOffice sab ✔ hone chahiye.
2. **Settings:** website ka naam, logo, favicon, social image (1200×630), upload limit.
3. **SEO → Verification:**
   - Google Search Console aur Bing ke codes daalein.
   - Search Console mein `https://ilove-pdf.site/sitemap.xml` submit karein.
4. **SEO → Overview:** score aur checklist dekh kar kamiyan theek karein.
5. **Advertising:** Adsterra/Monetag units add karein aur “Show ads on the website” on karein. `ads.txt` aur Monetag `sw.js` bhi yahin se lagte hain.
6. **Pages:** privacy policy mein ads/analytics ka zikr karein.

---

## 14. Naya code update karna

Local computer par changes karke GitHub par push karein. Phir server par:

```bash
cd /www/wwwroot/ilove-pdf.site
bash scripts/deploy.sh
```

Update ke dauran site 1–2 minute “maintenance” mode mein rehti hai, phir khud wapas aa jati hai.

---

## Backup

**Cron → Add Task** mein ye do backups bana lein:

- **Backup site** → `ilove-pdf.site` (rozana)
- **Backup database** – sirf agar MySQL use kar rahe hain

Zaroori files:

- `.env`
- `database/database.sqlite` (SQLite ho to)
- `public/uploads/` (logo/favicon)

Baqi sab GitHub mein hai.

---

## Masle aur hal (troubleshooting)

| Masla | Hal |
|---|---|
| Home page chalta hai, baqi pages par 404 | **URL rewrite → laravel5** set karein, aur Running directory `/public` ho |
| 500 error / blank page | `storage/logs/` mein aaj ki log file dekhein. Permissions theek karne ke liye `bash scripts/deploy.sh` dobara chalayein |
| “413 Request Entity Too Large” | Nginx `client_max_body_size 210m` ([step 4](#4-nginx-upload-limit), [10.3](#103-config-file-nginx)) |
| App kehti hai “file too large” | PHP `upload_max_filesize` / `post_max_size` ([3.3](#33-upload-aur-timeout-limits)) |
| Tool “temporarily unavailable” ya `pdf:doctor` mein “not found” | `proc_open` enable karein ([3.2](#32-disabled-functions-bohat-zaroori)), Anti-XSS/open_basedir band karein ([10.1](#101-site-directory)), `.env` mein `*_PATH` check karein, phir admin **System → Detect software again** |
| `proc_open() has been disabled` | [3.2](#32-disabled-functions-bohat-zaroori) dekhein, phir PHP restart karein |
| `git`: “detected dubious ownership” | `git config --global --add safe.directory /www/wwwroot/ilove-pdf.site` |
| `npm run build` “Killed” | RAM kam hai – [swap](#swap-1-gb-ram-wale-server-ke-liye) banayein |
| `.env` badla magar asar nahi hua | `bash scripts/deploy.sh` chalayein, ya `/www/server/php/83/bin/php artisan optimize` |
| `/sw.js` 404 | [10.3 (b)](#103-config-file-nginx) wali Nginx line add karein, aur Advertising → Settings mein file ka content daalein |
| Ads nazar nahi aate | Advertising mein master switch on ho. Browser ka ad-blocker band karein. “Hide ads while signed in” on ho to admin logout karke dekhein |
| HTTPS ke baad “Mixed content” / login loop | `APP_URL=https://…` rakhein. Cloudflare ho to `TRUSTED_PROXIES=*`, phir `bash scripts/deploy.sh` |
| Files `storage/app/pdf-temp` mein jama ho rahi hain | Cron ([step 11](#11-cron-job-scheduler--temp-files-ki-safai)) chal raha hai? Manual safai: `sudo -u www /www/server/php/83/bin/php artisan pdf:cleanup` |

Detailed technical documentation (translation server, OCR languages, architecture) [README.md](../README.md) mein hai.
