# Panduan Instalasi & Struktur Arsitektur Sistem
## Sistem Ujian Online Berbasis Dual Kamera AI

Dokumen ini menjelaskan langkah-langkah instalasi aplikasi dari awal (*fresh install*), konfigurasi basis data otomatis, dependensi pustaka, akun demo bawaan, serta arsitektur kode yang terstruktur dan mudah dipelihara (*maintainable*).

---

## 1. Persyaratan Sistem (System Requirements)

- **PHP**: Versi 8.2.0 atau lebih tinggi.
- **Ekstensi PHP Wajib**:
  - `pdo_mysql` (Koneksi database PDO)
  - `curl` (Komunikasi AI API Provider)
  - `mbstring` (Manipulasi string UTF-8)
  - `openssl` (Enkripsi token & security)
  - `gd` (Pemrosesan gambar & barcode/QR)
  - `zip` & `xml` (PhpSpreadsheet & Dompdf report generator)
- **Web Server**: Apache 2.4+ (dengan modul `mod_rewrite` aktif) atau Nginx 1.20+.
- **Database Server**: MySQL 8.0+ atau MariaDB 10.4+.
- **Composer**: Versi 2.x (Dependensi Dompdf & PhpSpreadsheet sudah disertakan dalam vendor bawaan).
- **Browser Klien**: Google Chrome 90+, Mozilla Firefox 88+, Microsoft Edge 90+, Safari 14+ (Mendukung WebRTC MediaDevices API & Fullscreen API).
- **Perangkat Keras Kamera (Untuk AI Proctoring)**:
  - Kamera Utama: Webcam laptop / PC terintegrasi atau USB external.
  - Kamera Sekunder: Kamera smartphone (via browser WebRTC IP) atau USB webcam eksternal kedua.

---

## 2. Struktur Direktori Proyek (Project Architecture)

Aplikasi dibangun menggunakan pola arsitektur **Clean MVC (Model-View-Controller) Modular**:

```
PRD/
├── app/
│   ├── Config/              # Konfigurasi aplikasi, database, dan perutean (Routing)
│   │   ├── App.php          # Pengaturan URL, timezone, session lifetime, CSRF
│   │   ├── Database.php     # Singleton koneksi PDO MySQL dengan error handling
│   │   └── Routes.php       # Deklarasi seluruh route sistem dengan Route Groups & Filters
│   ├── Controllers/         # Controller penanganan HTTP request & response
│   │   ├── BaseController.php         # Controller induk (render view, json helper, auth checking)
│   │   ├── AuthController.php         # Otentikasi login & logout
│   │   ├── PasswordController.php     # Forgot password & 6-digit OTP reset
│   │   ├── UserController.php         # CRUD Pengguna & reset password admin
│   │   ├── ExamController.php         # Manajemen jadwal ujian & token terpusat
│   │   ├── QuestionBankController.php   # Manajemen Bank Soal
│   │   ├── QuestionController.php     # Manajemen Butir Soal (PG, Essay, True/False)
│   │   ├── ExamSessionController.php  # CBT Exam Engine (Mulai, Room, Submit, Selesai)
│   │   ├── ExamAnswerController.php   # Auto-save jawaban via AJAX & sinkronisasi timer
│   │   ├── CameraPreCheckController.php # Diagnostik & kalibrasi Dual Camera
│   │   ├── AiProctoringController.php # Telemetri AI, deteksi wajah & pelanggaran
│   │   ├── ExamMonitoringController.php # Live monitoring dashboard pengawas/guru
│   │   ├── GradingController.php      # Penilaian PG otomatis & koreksi essay manual
│   │   ├── ReportController.php       # Generator laporan PDF (Dompdf) & Excel (PhpSpreadsheet)
│   │   ├── AiQuestionController.php   # Generator butir soal cerdas (Gemini / OpenAI)
│   │   └── Student*.php               # Controller khusus peserta didik
│   ├── Filters/             # Middleware filter
│   │   ├── AuthFilter.php   # Memastikan pengguna telah login
│   │   ├── RoleFilter.php   # Pembatasan akses berbasis Role (RBAC)
│   │   └── CsrfFilter.php   # Validasi token keamanan anti-CSRF
│   ├── Helpers/             # Fungsi pembantu global
│   │   ├── app_helper.php   # base_url(), asset(), e(), csrf_token(), flash()
│   │   └── auth_helper.php  # auth_user(), user_id(), user_role(), has_role()
│   ├── Libraries/           # Pustaka utilitas independen
│   │   ├── Database.php     # PDO Connection Handler
│   │   ├── Router.php       # Regex-based HTTP Router
│   │   ├── Request.php      # HTTP Request wrapper (inputs, headers, files, IP)
│   │   ├── Response.php     # HTTP Response wrapper (view, json, redirect, download)
│   │   └── Session.php      # Secure PHP Session & Flash message manager
│   ├── Models/              # Active Record & Model basis data (36 model tabel)
│   │   ├── BaseModel.php    # Abstraksi Query Builder & CRUD PDO
│   │   ├── UserModel.php
│   │   ├── ExamModel.php
│   │   ├── QuestionModel.php
│   │   └── ...
│   ├── Services/            # Business Logic Layer (Terpisah dari Controller)
│   │   ├── AI/              # Integrasi Provider AI (Gemini, OpenAI, Mock)
│   │   ├── AuthService.php
│   │   ├── ExamService.php
│   │   ├── ExamSessionService.php
│   │   ├── ExamAnswerService.php
│   │   ├── ExamTimerService.php
│   │   ├── FaceVerificationService.php
│   │   ├── ViolationDetectionService.php
│   │   ├── AiProctoringService.php
│   │   ├── ExamMonitoringService.php
│   │   ├── GradingService.php
│   │   ├── ResultService.php
│   │   └── ReportService.php
│   ├── Views/               # Antarmuka Blade-like PHP Templates
│   │   ├── layouts/         # Master layout (admin, guru, peserta, auth)
│   │   ├── components/      # Komponen UI (navbar, sidebar, breadcrumb, datatable, modal)
│   │   └── [admin|guru|siswa|auth]/ # Halaman per modul
│   └── bootstrap.php        # Bootstrapper awal & autoloader PSR-4
├── database/
│   ├── migrate.php          # Unified CLI Migration & Seeder Runner
│   ├── migrations/          # Berkas skrip SQL skema tabel (36 tabel)
│   └── seeders/             # DatabaseSeeder (Roles, Users, Bank Soal, Soal, Ujian Demo, Token)
├── public/                  # Document Root publik
│   ├── assets/              # CSS modern, Vanilla JS modular, icons
│   ├── uploads/             # Direktori berkas unggahan (gambar soal, tangkapan wajah)
│   ├── index.php            # Front Controller tunggal
│   └── .htaccess            # URL Rewriting Apache
├── storage/
│   └── logs/                # File log error dan audit trail harian
├── tests/                   # Automated Unit & End-to-End Test Suite
│   ├── production_audit_runner.php # 12-modul comprehensive test runner
│   ├── test_security_regression_suite.php # 19-assertion security test suite
│   ├── test_exam_management.php    # Exam management lifecycle tests
│   └── test_phase7_8_9_e2e_full.php # E2E CBT exam room full flow tests
├── vendor/                  # Pustaka Composer pihak ketiga (Dompdf, PhpSpreadsheet)
├── .env                     # Konfigurasi environment lokal
├── .env.example             # Contoh template environment
├── composer.json            # Daftar dependensi composer
└── README.md
```

---

## 3. Langkah-Langkah Instalasi Cepat (Quick Start Installation)

### Langkah 1: Clone / Pindahkan Folder Proyek
Tempatkan folder proyek ke direktori web server:
- **XAMPP Windows**: `C:\xampp\htdocs\PRD`
- **Linux Nginx/Apache**: `/var/www/ujian-online`

### Langkah 2: Konfigurasi File Environment (`.env`)
Salin file template konfigurasi `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
Sesuaikan parameter koneksi basis data jika berbeda dari default XAMPP:
```ini
APP_NAME="Sistem Ujian Online Dual Kamera AI"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost/PRD/public

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ujian_dual_kamera
DB_USERNAME=root
DB_PASSWORD=

# Konfigurasi AI Provider (Opsional - default fallback otomatis ke mode offline/mock)
GEMINI_API_KEY=
GEMINI_MODEL=gemini-3.8-flash
AI_PROVIDER=gemini
```

### Langkah 3: Eksekusi Migrasi & Seeder Database (1 Perintah Otomatis)
Jalankan migration runner CLI bawaan untuk membuat database, 36 tabel, relasi foreign key, dan data demo lengkap secara otomatis:
```bash
php database/migrate.php --fresh --seed
```
*Catatan: Perintah di atas akan otomatis membuat database `ujian_dual_kamera` jika belum ada, mengeksekusi seluruh skrip migrasi SQL secara berurutan, dan menginisialisasi seluruh data demo.*

### Langkah 4: Pengaturan Hak Akses Direktori (Khusus Linux/macOS)
Pastikan direktori `storage/` dan `public/uploads/` dapat ditulis (*writable*) oleh web server:
```bash
chmod -R 775 storage/ public/uploads/
```

### Langkah 5: Akses Aplikasi di Browser
Buka browser dan navigasikan ke:
- **Via XAMPP Apache**: `http://localhost/PRD/public` atau `http://localhost/PRD/`
- **Via PHP Built-in Server**: 
  ```bash
  php -S localhost:8000 -t public
  ```
  Lalu buka: `http://localhost:8000`

---

## 4. Akun Pengguna Demo Bawaan (Default Seed Accounts)

Setelah menjalankan `php database/migrate.php --fresh --seed`, sistem secara otomatis menyediakan akun-akun demo berikut yang siap digunakan:

| Role | Email Login | Kata Sandi | Data Demo Terhubung |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `superadmin@example.com` | `SuperAdmin123!` | Akses penuh manajemen sistem, role, audit logs, konfigurasi AI |
| **Administrator** | `admin@example.com` | `Admin123!` | Manajemen pengguna, jadwal ujian, rilis token ujian terpusat |
| **Guru / Pengawas** | `guru@example.com` | `Guru123!` | Bank Soal Matematika (`BNK-MAT12-UAS`), 6 Soal Ujian, Live Monitoring |
| **Peserta Didik** | `siswa@example.com` | `Siswa123!` | Terdaftar pada Kelas XII MIPA 1 & Ujian `EXAM-MAT12-UAS` (Token: `UAS2026`) |

### Data Ujian Demo Siap Uji:
- **Nama Ujian**: Penilaian Akhir Semester - Matematika Peminatan XII
- **Kode Ujian**: `EXAM-MAT12-UAS`
- **Token Aktif**: `UAS2026`
- **Durasi**: 60 Menit
- **Fitur Aktif**: Dual Camera AI Proctoring, Acak Soal, Acak Opsi, Autosave, Server-Authoritative Timer.

---

## 5. Menjalankan Regression & Automated Test Suite

Untuk memastikan seluruh fungsionalitas dan keamanan sistem berjalan 100% sempurna:

```bash
# 1. Menjalankan 12-Modul Production Audit Runner (69 Assertions)
php tests/production_audit_runner.php

# 2. Menjalankan Security Regression Test Suite (19 Security Assertions)
php tests/test_security_regression_suite.php

# 3. Menjalankan Exam Lifecycle & Management Tests
php tests/test_exam_management.php

# 4. Menjalankan End-to-End CBT Exam Room Flow Tests
php tests/test_phase7_8_9_e2e_full.php
```
