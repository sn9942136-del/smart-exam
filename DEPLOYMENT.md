# Deployment Checklist & Production Readiness Guide
## Sistem Ujian Online Berbasis Dual Kamera AI

Dokumen ini merupakan panduan checklist kesiapan operasional (*Production Readiness*) untuk implementasi **Sistem Ujian Online Berbasis Dual Kamera AI**.

---

## 1. Ringkasan Kesiapan Produksi (Production Readiness Summary)

| Domain | Status | Keterangan |
| :--- | :---: | :--- |
| **Security & OWASP Top 10** | ✅ **PASSED** | Dilindungi dari SQLi (PDO Prepared Statements), XSS (`e()`), CSRF (Token 64-char), Broken Access Control (RBAC Filter), Session Fixation/Hijacking (`session_regenerate_id`, HttpOnly, Lax). |
| **Exam Security & Engine** | ✅ **PASSED** | Idempotent double submission prevention (`SELECT ... FOR UPDATE`), Server Clock Time Authority, Server-side Grading Immutability, Cross-Participant Isolation. |
| **AI Proctoring & Dual Camera** | ✅ **PASSED** | WebRTC Dual Stream enumeration, fallback single camera, dynamic AI risk scoring (Low, Medium, High, Critical), telemetry heartbeats, violation logging. |
| **Database & Query Performance** | ✅ **PASSED** | Seluruh 52 tabel terindeks secara optimal (Primary, Foreign, Compound Keys). Anti N+1 query loops dengan eager loading. |
| **Grading & Reporting** | ✅ **PASSED** | Auto-grading PG, Manual Essay Grading, Immutabilitas nilai pasca publikasi, Dompdf PDF generator & PhpSpreadsheet Excel generator. |
| **Automated Test Suite** | ✅ **PASSED** | 100% Pass Rate (69/69 Asersi) pada 12 modul inti (`tests/production_audit_runner.php`). |

---

## 2. Checklist Pra-Deployment (Pre-Deployment Checklist)

### A. Lingkungan Server & Infrastruktur
- [ ] **Sistem Operasi**: Linux Ubuntu 22.04 LTS / 24.04 LTS (atau Windows Server IIS / Apache).
- [ ] **Web Server**: Nginx 1.24+ / Apache 2.4+ dengan modul `mod_rewrite` & `mod_headers` aktif.
- [ ] **PHP Runtime**: PHP 8.2+ atau PHP 8.3+ (CLI & FPM).
- [ ] **Ekstensi PHP Wajib**:
  - `pdo_mysql` (Koneksi basis data MySQL)
  - `mbstring` (Manipulasi string & multibyte)
  - `openssl` (Kriptografi & token acak CSPRNG)
  - `gd` atau `imagick` (Pemrosesan gambar & watermark Dompdf)
  - `zip` & `xml` (PhpSpreadsheet XLSX exporter)
  - `fileinfo` (Verifikasi MIME type berkas unggahan)
  - `curl` (Koneksi OpenAI / Google Gemini AI API)
  - `opcache` (Akselerasi performa eksekusi script PHP)
- [ ] **Basis Data**: MySQL 8.0+ atau MariaDB 10.6+ dengan konfigurasi `innodb_buffer_pool_size` minimal 50-70% total RAM server.
- [ ] **Sertifikat SSL/TLS (HTTPS)**: Wajib HTTPS (Let's Encrypt / DigiCert) karena browser memblokir akses MediaDevices API (Webcam & Mikrofon) pada koneksi HTTP biasa.

### B. Konfigurasi WebRTC & Dual Camera (STUN / TURN Server)
- [ ] **STUN Server**: Gunakan Google STUN (`stun:stun.l.google.com:19302`) atau STUN publik kampus/sekolah.
- [ ] **TURN Server (Opsional tapi Direkomendasikan untuk NAT Ketat)**:
  - Instal `coturn` pada server terpisah atau VPS jika siswa berada di jaringan NAT Simetris / Firewall ketat.
  - Port TURN default: `3478` (UDP/TCP) dan `5349` (TLS).

### C. Konfigurasi Environment Variable (`.env`)
- [ ] Duplikasi `.env.example` menjadi `.env`.
- [ ] Ubah `APP_ENV=production` dan `APP_DEBUG=false` (Mencegah stack trace terekspos ke publik).
- [ ] Set `APP_URL=https://ujian.sekolah.sch.id` (URL HTTPS resmi).
- [ ] Set kredensial MySQL (`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`).
- [ ] Masukkan API Key Provider AI (`OPENAI_API_KEY` atau `GEMINI_API_KEY`) untuk fitur AI Question Generator dan Penjelasan Jawaban AI.
- [ ] Pastikan file `.env` memiliki permission `600` (hanya bisa dibaca oleh user web server / `www-data`).

---

## 3. Hardening Keamanan Server (Security Hardening)

### A. Pengaturan PHP `php.ini` untuk Produksi
```ini
; Sembunyikan versi PHP
expose_php = Off

; Pengaturan Error Logging Aman
display_errors = Off
display_startup_errors = Off
log_errors = On
error_log = /var/log/php/error.log

; Batasan Upload & Eksekusi
upload_max_filesize = 10M
post_max_size = 15M
max_execution_time = 60
memory_limit = 256M

; Keamanan Session Cookie
session.cookie_httponly = 1
session.cookie_secure = 1
session.cookie_samesite = "Lax"
session.use_strict_mode = 1
session.use_only_cookies = 1
session.gc_maxlifetime = 7200

; Optimasi OPcache
opcache.enable = 1
opcache.memory_consumption = 128
opcache.interned_strings_buffer = 8
opcache.max_accelerated_files = 10000
opcache.validate_timestamps = 0
```

### B. Konfigurasi VirtualHost Nginx
```nginx
server {
    listen 80;
    server_name ujian.sekolah.sch.id;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name ujian.sekolah.sch.id;

    root /var/www/ujian-online/public;
    index index.php index.html;

    ssl_certificate /etc/letsencrypt/live/ujian.sekolah.sch.id/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/ujian.sekolah.sch.id/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Blokir akses ke file sensitif (.env, .git, storage logs)
    location ~ /\.(env|git|htaccess) {
        deny all;
        return 404;
    }

    location ~ ^/(storage|database|app|tests)/ {
        deny all;
        return 404;
    }

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|webp|svg)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }
}
```

---

## 4. Jadwal Cron Job Pemeliharaan (Maintenance Cron Jobs)

Tambahkan ke crontab server (`crontab -e -u www-data`):

```bash
# 1. Bersihkan sesi login kadaluarsa setiap jam 02:00 pagi
0 2 * * * php /var/www/ujian-online/public/index.php cleanup:sessions > /dev/null 2>&1

# 2. Rotasi file log sistem yang berusia lebih dari 30 hari setiap minggu
0 3 * * 0 find /var/www/ujian-online/storage/logs/ -name "*.log" -mtime +30 -delete

# 3. Backup otomatis basis data setiap hari pukul 01:00 WIB
0 1 * * * mysqldump -u root -p'SECURE_PASSWORD' ujian_dual_kamera | gzip > /var/backups/db_ujian_$(date +\%Y\%m\%d).sql.gz
```

---

## 5. Prosedur Uji Asap Pasca-Deploy (Post-Deployment Smoke Test)

1. **Akses Landing Page**: Buka `https://ujian.sekolah.sch.id/` dan pastikan ikon status API hijau (`/health`).
2. **Uji Login Super Admin**: Masuk dengan akun administrator, periksa dashboard dan menu pengguna.
3. **Uji Login Guru**: Periksa pembuatan Bank Soal dan Manajemen Ujian.
4. **Uji Pre-Check Siswa**: Buka `/siswa/exam/pre-check`, izinkan akses kamera utama dan sekunder, pastikan preview video lancar.
5. **Uji Pengerjaan Ujian**: Masukkan token ujian, jawab soal PG dan Essay, verifikasi indikator auto-save tersimpan hijau.
6. **Uji Submit & Laporan**: Selesaikan ujian, lakukan koreksi essay oleh guru, lalu unduh dokumen PDF dan Excel rekapitulasi nilai.
