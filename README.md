# Sistem Ujian Online Berbasis Dual Kamera AI

Aplikasi web sistem ujian online berbasis Computer-Based Test (CBT) dengan pengawasan cerdas (*AI Proctoring*) berbasis dua kamera (Kamera Utama untuk pelacakan wajah/arah pandang & Kamera Sekunder untuk lingkungan sekitar meja kerja peserta). Dibangun menggunakan arsitektur Clean MVC PHP 8.2+ yang modular, terstruktur, aman, dan mudah dipelihara tanpa ketergantungan framework berat.

---

## 🚀 Panduan Cepat 5 Langkah (Quick Start)

Untuk menjalankan aplikasi dari kondisi awal secara instan:

### 1. Salin File Konfigurasi Environment
```bash
cp .env.example .env
```

### 2. Inisialisasi Database & Seeder Lengkap (1 Perintah)
Pastikan server MySQL/MariaDB (misal via XAMPP) sudah aktif pada port 3306, lalu jalankan:
```bash
php database/migrate.php --fresh --seed
```
*Perintah ini otomatis membuat database `ujian_dual_kamera`, mengeksekusi 36 skema tabel, dan mengisi data demo master role, akun pengguna, bank soal, butir soal, jadwal ujian, dan token aktif.*

### 3. Jalankan Web Server
- **Opsi XAMPP Apache**: Letakkan folder di `c:/xampp/htdocs/PRD`, lalu buka `http://localhost/PRD/public`
- **Opsi PHP Built-in Server**:
  ```bash
  php -S localhost:8000 -t public
  ```
  Lalu buka browser di `http://localhost:8000`

### 4. Login Menggunakan Akun Demo Bawaan

| Role | Email Login | Kata Sandi | Hak Akses Utama |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `superadmin@example.com` | `SuperAdmin123!` | Manajemen penuh sistem, role, master data, audit logs, konfigurasi AI |
| **Administrator** | `admin@example.com` | `Admin123!` | Manajemen pengguna, kelas, mata pelajaran, penjadwalan & token terpusat |
| **Guru / Pengawas** | `guru@example.com` | `Guru123!` | Bank Soal (`BNK-MAT12-UAS`), AI Generator Soal, Live Monitoring, Grading |
| **Peserta Didik** | `siswa@example.com` | `Siswa123!` | CBT Exam Room, Dual-Cam Pre-check, Autosave, Submit, Hasil Nilai |

### 5. Data Ujian Demo Siap Uji
- **Mata Pelajaran**: Matematika Peminatan (Kelas XII MIPA 1)
- **Kode Ujian**: `EXAM-MAT12-UAS`
- **Token Aktif**: `UAS2026`
- **Jumlah Soal**: 6 Soal (5 Pilihan Ganda + 1 Essay)
- **Fitur Terpasang**: Dual Camera AI Proctoring, Acak Soal, Acak Opsi, Anti-cheat Detector, Real-time Autosave.

---

## 🧪 Menjalankan Pengujian Otomatis (Test Suites)

Aplikasi dilengkapi dengan automated test suite untuk memvalidasi integritas kode dan keamanan:

```bash
# 1. Menjalankan 12-Modul Production Audit Runner (69 Asersi Otomatis)
php tests/production_audit_runner.php

# 2. Menjalankan Security Regression Test Suite (19 Security Asersi)
php tests/test_security_regression_suite.php

# 3. Menjalankan Exam Lifecycle & Management Tests
php tests/test_exam_management.php

# 4. Menjalankan End-to-End CBT Exam Room Flow Tests
php tests/test_phase7_8_9_e2e_full.php
```

---

## 📁 Struktur Direktori Proyek

```
PRD/
├── app/
│   ├── Config/              # Konfigurasi aplikasi, koneksi database, dan routing
│   │   ├── App.php          # URL, timezone, session lifetime, CSRF toggle
│   │   ├── Database.php     # Singleton PDO MySQL connector
│   │   └── Routes.php       # Deklarasi seluruh routing & middleware filters
│   ├── Controllers/         # Penanganan HTTP Request & Response
│   │   ├── BaseController.php         # Base Controller (view, json, redirect, auth)
│   │   ├── AuthController.php         # Login, logout, multi-role auth
│   │   ├── PasswordController.php     # Forgot password & OTP reset
│   │   ├── UserController.php         # User management & credentials reset
│   │   ├── ExamController.php         # Exam management & live token generation
│   │   ├── QuestionBankController.php   # Bank soal & topik
│   │   ├── QuestionController.php     # Butir soal PG, Essay, True/False
│   │   ├── ExamSessionController.php  # CBT Engine (Mulai, Room, Autosave, Submit)
│   │   ├── ExamAnswerController.php   # AJAX autosave & response tracking
│   │   ├── CameraPreCheckController.php # Diagnostik & kalibrasi Dual Camera
│   │   ├── AiProctoringController.php # Telemetri AI, deteksi wajah & pelanggaran
│   │   ├── ExamMonitoringController.php # Real-time proctoring monitoring
│   │   ├── GradingController.php      # Auto-grading PG & manual essay grading
│   │   ├── ReportController.php       # Ekspor PDF (Dompdf) & Excel (PhpSpreadsheet)
│   │   ├── AiQuestionController.php   # Generator butir soal cerdas (Gemini / OpenAI)
│   │   └── Student*.php               # Controller khusus peserta didik
│   ├── Filters/             # Middleware filter
│   │   ├── AuthFilter.php   # Memastikan pengguna telah terotentikasi
│   │   ├── RoleFilter.php   # Pembatasan akses berbasis Role (RBAC)
│   │   └── CsrfFilter.php   # Validasi token keamanan anti-CSRF
│   ├── Helpers/             # Helper fungsi global (app_helper, auth_helper)
│   ├── Libraries/           # Core library (Router, Request, Response, Session, Database)
│   ├── Models/              # Active Record / Query Builder (36 Tabel Model)
│   ├── Services/            # Business Logic Layer (Terpisah dari Controller)
│   │   ├── AI/              # Provider AI (Gemini, OpenAI, Mock)
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
│   ├── Views/               # Antarmuka Blade-like PHP Templates (layouts, components)
│   └── bootstrap.php        # PSR-4 Autoloader & Error Handler bootstrap
├── database/
│   ├── migrate.php          # Unified CLI Migration & Seeder Engine
│   ├── migrations/          # Berkas skrip SQL skema tabel (001 - 009)
│   └── seeders/             # DatabaseSeeder komprehensif
├── public/                  # Document root publik (index.php, assets, uploads)
├── storage/                 # Direktori writable untuk log harian & rekaman
├── tests/                   # Automated unit & E2E test suites
├── vendor/                  # Pustaka third-party (Dompdf, PhpSpreadsheet)
├── .env.example             # Template konfigurasi environment
├── .env                     # File konfigurasi lokal aktif
├── composer.json            # Daftar dependensi composer
├── INSTALLATION.md          # Panduan instalasi lengkap
└── README.md                # Dokumentasi utama proyek
```

---

## 🛡️ Keamanan & Kepatuhan Arsitektur

- **Server-Authoritative Timer**: Sisa waktu ujian dihitung murni di sisi server berdasarkan `started_at` dan `duration_minutes`. Manipulasi jam klien tidak berpengaruh.
- **Answer Ownership Validation**: Setiap request penyimpanan jawaban diverifikasi kepemilikan sesi dan nomor identitas peserta untuk mencegah cross-session tampering.
- **Idempotent Single Submission**: Sesi ujian yang telah disubmit dikunci dengan status `'submitted'`. Double submit atau perubahan jawaban pasca submit ditolak secara otomatis.
- **CSRF & XSS Protection**: Seluruh form mutasi dilindungi token CSRF dan rendering output disanitasi menggunakan helper `e()`.
- **RBAC Strict Enforcement**: Filter middleware memastikan setiap endpoint hanya dapat diakses oleh role yang berwenang (Super Admin, Admin, Guru, Siswa).
- **Proctoring Telemetry Security**: Telemetri AI dan pencatatan pelanggaran diverifikasi secara ketat hanya aktif pada sesi berstatus `'ongoing'`.

