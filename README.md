# Smart Exam — Sistem Ujian Online Berbasis AI & Dual Camera Proctoring

[![Smart Exam CI/CD Pipeline](https://github.com/sn9942136-del/smart-exam/actions/workflows/ci.yml/badge.svg)](https://github.com/sn9942136-del/smart-exam/actions/workflows/ci.yml)
![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![MySQL Version](https://img.shields.io/badge/MySQL-8.0%2B-4479A1?logo=mysql&logoColor=white)
![AI Engine](https://img.shields.io/badge/AI-Google%20Gemini%20%7C%20MediaPipe-blue?logo=google)
![WebRTC](https://img.shields.io/badge/WebRTC-Dual%20Camera%20Live-orange?logo=webrtc)
![Architecture](https://img.shields.io/badge/Architecture-Clean%20MVC%20%26%20SOLID-success)

> **Tugas Proyek Akhir Mandiri — Rekayasa Perangkat Lunak (Berbasis Kurikulum OBE)**  
> Program Studi S1 Teknik Informatika — **STMIK IKMI Cirebon**  
> Tahun Akademik 2026/2027 (Semester 3)

---

## 📌 Identitas Mahasiswa & Kelompok

| Data Mahasiswa | Informasi Resmi |
| :--- | :--- |
| **Nama Lengkap** | **Salsa Nabila** |
| **NIM** | **41257158** |
| **Program Studi** | S1 Teknik Informatika |
| **Perguruan Tinggi** | STMIK IKMI Cirebon |
| **Mata Kuliah / SKS** | Rekayasa Perangkat Lunak (TIKB2503021) / 4 SKS (2 Teori + 2 Praktik) |
| **Dosen Pengampu** | **Yudhistira Arie Wijaya, M.Kom.** |
| **Nama Kelompok** | **Tiba Tiba Talun** |
| **Tema Topik Kelompok** | **E-Learning & Smart Education AI** |
| **Judul Proyek Mandiri** | **Smart Exam: Sistem Ujian Online Berbasis Web dengan Pengawasan Dual Camera AI dan Chatbot Operasional Terintegrasi Database** |
| **Repositori GitHub** | [https://github.com/sn9942136-del/smart-exam](https://github.com/sn9942136-del/smart-exam) |

---

## 📖 Deskripsi Eksekutif Proyek

**Smart Exam** adalah platform ujian daring (*Computer-Based Test / CBT*) generasi baru yang dirancang untuk menjawab tantangan terbesar dalam pembelajaran jarak jauh: **integritas akademik dan pencegahan kecurangan tanpa mengorbankan privasi peserta**. 

Sistem ini mentransformasikan kebutuhan abstrak institusi pendidikan menjadi produk perangkat lunak tangguh (*end-to-end software engineering*) yang memadukan arsitektur **Clean MVC berprinsip SOLID** dengan teknologi **Kecerdasan Artifisial (AI)** dan **WebRTC Dual Camera**.

### 🌟 Nilai Guna & Inovasi Utama
1. **Dual-Camera Proctoring (Kamera Depan + Sudut Samping):** Mengeliminasi titik buta (*blind spots*) dengan menyinkronkan webcam depan laptop (untuk deteksi wajah & *eye-tracking*) dan kamera samping (menggunakan smartphone via DroidCam/Iriun/WebRTC) untuk memantau meja kerja peserta.
2. **AI Asisten Operasional (Grounding Live Database):** Chatbot berbasis Google Gemini yang terhubung langsung ke basis data sistem secara *real-time*, mampu menjawab pertanyaan seputar jadwal, bank soal, dan statistik secara singkat, padat, dan akurat dengan batasan hak akses role.
3. **Koreksi Esai Cerdas Otomatis:** Menilai jawaban esai siswa berdasarkan kesesuaian semantik terhadap rubrik dan kunci jawaban guru.
4. **Generator Bank Soal AI:** Mempermudah guru menyusun instrumen evaluasi bertingkat taksonomi Bloom secara otomatis.
5. **Keamanan & Ketahanan Tingkat Tinggi:** Dilengkapi proteksi CSRF, filter Rate-Limiting brute force, verifikasi OTP multi-kanal (Email SMTP Gmail & WhatsApp Gateway), sesi terisolasi, serta audit logging forensik.

---

## 🤖 Spesifikasi Fitur Kecerdasan Artifisial (AI)

| Fitur AI | Teknologi / Engine | Fungsi & Peran Strategis |
| :--- | :--- | :--- |
| **Dual-Camera Face Tracking** | MediaPipe Face Mesh & Canvas Vision | Mendeteksi keberadaan wajah, menghitung sudut pandang (*gaze/look away*), dan memicu peringatan jika wajah tidak terdeteksi atau terdapat wajah lain di layar. |
| **Operational Live Chatbot** | Google Gemini (3.5 Flash-Lite / 3.8 Flash) | Asisten cerdas operasional sistem yang membaca 50+ tabel database aktif secara *real-time*, menyajikan data jadwal, hasil nilai, dan statistik untuk Admin, Guru, dan Siswa. |
| **Semantic Essay Grading** | Gemini NLP & Cosine Similarity | Menganalisis esai siswa, memberikan skor otomatis yang adil, serta menghasilkan umpan balik perbaikan (*constructive feedback*). |
| **Smart Question Generator** | Gemini Structured Prompting | Membantu guru membuat draf butir soal (Pilihan Ganda, Benar/Salah, Esai) lengkap dengan opsi, pembahasan, dan tingkat kesulitan (*easy/medium/hard*). |

---

## 🎥 Tautan Video Presentasi & Live Demo

Sesuai ketentuan standar panduan proyek akhir, video presentasi diunggah dengan format perekaman layar (*screen recording*) disertai wajah mahasiswa (*webcam aktif di pojok layar*):

| Tahapan Video | Konten yang Dibahas | Target Durasi | Tautan Video (YouTube / GDrive) |
| :--- | :--- | :---: | :--- |
| **Video Progres 1: PRD & Konsep Aplikasi** | Latar belakang masalah, analisis kebutuhan fungsional & non-fungsional, serta peran strategis fitur AI. | 3–5 Menit | [Tautan Video Progres 1 (Klik di Sini)](#) |
| **Video Progres 2: Pemodelan UML & UI/UX** | Pembedahan 4 Diagram UML Utama (Use Case, Activity, Class, Sequence), ERD, dan interaktif prototype Figma. | 5–7 Menit | [Tautan Video Progres 2 (Klik di Sini)](#) |
| **Video Final: Live Demo & Sistem Utuh** | Elevator Pitch, arsitektur sistem, **Live Demo aplikasi + fitur AI real-time**, Unit Test, verifikasi status hijau CI/CD, & refleksi. | 8–12 Menit | [Tautan Video Final Demo (Klik di Sini)](#) |

*(Catatan: Tautan video di atas dapat diperbarui dengan URL rekaman YouTube/Google Drive Anda sebelum tenggat Pertemuan ke-16).*

---

## 📐 Arsitektur Perangkat Lunak & Desain UML

Aplikasi dibangun dengan mematuhi standar rekayasa perangkat lunak modern:

```
┌─────────────────────────────────────────────────────────────┐
│                    PRESENTATION TIER                        │
│   HTML5 / CSS3 (Bootstrap 5.3) / Modern Vanilla JS          │
│   WebRTC Camera Stream / MediaPipe Face Mesh Client         │
└──────────────────────────────▲──────────────────────────────┘
                               │ HTTP / JSON REST
┌──────────────────────────────▼──────────────────────────────┐
│                    APPLICATION LOGIC TIER                   │
│   Custom Clean MVC Engine (PHP 8.2+)                        │
│   • Controllers (Admin, Guru, Siswa, Exam, Chatbot, Notif)  │
│   • Middlewares & Filters (Auth, Role, CSRF, Rate-Limit)    │
│   • Services (AiChat, Proctoring, Grading, Otp, Queue)      │
└──────────────────────────────▲──────────────────────────────┘
                               │ PDO Prepared Statements
┌──────────────────────────────▼──────────────────────────────┐
│                      DATA & AI TIER                         │
│   • MySQL Database (50+ Relational Tables & Foreign Keys)   │
│   • Google Gemini REST API (Live LLM Engine)                │
│   • Fonnte WhatsApp Gateway & Gmail SMTP Relay              │
└─────────────────────────────────────────────────────────────┘
```

### 4 Pemodelan UML Utama:
1. **Use Case Diagram & Skenario:** Menggambarkan 4 aktor utama (*Super Admin*, *Administrator*, *Guru Pengawas*, dan *Peserta Didik*) beserta relasi *include/extend* pada fitur CBT dan AI.
2. **Activity Diagram:** Memvisualisasikan alur bisnis sistem mulai dari verifikasi kamera, pengerjaan ujian, sinkronisasi autosave jawaban, hingga deteksi kecurangan AI.
3. **Class Diagram (SOLID Principles):** Mengelompokkan Controller, Model, Service, dan Provider dengan prinsip *Single Responsibility*, *Open/Closed*, dan *Dependency Inversion*.
4. **Sequence Diagram:** Merinci alur pertukaran pesan secara kronologis antara *Browser Peserta* $\leftrightarrow$ *Backend Server* $\leftrightarrow$ *Database MySQL* $\leftrightarrow$ *Google Gemini AI API*.

---

## 📑 Dokumentasi Lengkap Proyek (OBE Deliverables)

Seluruh berkas dokumentasi rekayasa perangkat lunak telah disusun lengkap pada direktori [`docs/`](docs/):

| Dokumen | Tahapan & Bobot OBE | Tautan Berkas | Deskripsi Isi Dokumen |
| :--- | :--- | :--- | :--- |
| **PRD / SRS** | **Tahap 1 (20%)** | [`docs/01_PRD_SRS_Smart_Exam.md`](docs/01_PRD_SRS_Smart_Exam.md) | Analisis kebutuhan fungsional (FR-01 s.d FR-13), Non-Fungsional, persona pengguna, dan matriks ketertelusuran standar IEEE 830. |
| **SDD & UML** | **Tahap 2 (25%)** | [`docs/02_SDD_Software_Design_Description.md`](docs/02_SDD_Software_Design_Description.md) | Arsitektur 3-Tier Clean MVC, 4 Diagram UML lengkap (Use Case, Activity, Class, Sequence), dan ERD basis data ternormalisasi. |
| **Test Matrix** | **Tahap 3 (30%)** | [`docs/03_Test_Plan_and_Results.md`](docs/03_Test_Plan_and_Results.md) | Hasil eksekusi test suite otomatis (Unit, Integration, Security), mitigasi OWASP Top 10, dan verifikasi CI/CD GitHub Actions. |
| **Panduan Video**| **Tahap 4 (25%)** | [`docs/04_Panduan_Presentasi_dan_Video.md`](docs/04_Panduan_Presentasi_dan_Video.md) | Skrip presentasi kata demi kata untuk Progres 1 (3–5 menit), Progres 2 (5–7 menit), dan Live Demo Final (8–12 menit) dengan webcam. |

---

## 🛠️ Petunjuk Instalasi & Menjalankan Proyek (Setup Instruction)

### 1. Prasyarat Sistem
* **PHP:** Versi 8.2 atau lebih baru (Ekstensi aktif: `pdo_mysql`, `mbstring`, `curl`, `json`, `gd`, `openssl`).
* **Basis Data:** MySQL 8.0+ atau MariaDB 10.4+ (misal via XAMPP).
* **Web Server:** Apache (XAMPP) atau PHP Built-in Server.
* **Browser:** Google Chrome / Microsoft Edge versi terbaru (mendukung WebRTC & MediaDevices).

---

### 2. Langkah Instalasi Cepat

#### Langkah 1: Kloning Repositori
```bash
git clone https://github.com/sn9942136-del/smart-exam.git
cd smart-exam
```
*(Atau letakkan folder pada direktori web server lokal: `C:\xampp\htdocs\PRD`)*

#### Langkah 2: Konfigurasi Lingkungan (`.env`)
Salin file template `.env.example` menjadi `.env`:
```bash
cp .env.example .env
```
Sesuaikan kredensial database pada `.env`:
```ini
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=ujian_dual_kamera
DB_USERNAME=root
DB_PASSWORD=""
```

#### Langkah 3: Migrasi Database & Seeder Otomatis (1 Perintah)
Pastikan server MySQL pada XAMPP sudah berjalan, lalu jalankan:
```bash
php database/migrate.php --fresh --seed
```
*Perintah ini otomatis membangun 50+ tabel relasional, membuat aturan indeks/foreign key, serta menyuntikkan data master role, pengguna demo, 10 bank soal kurikulum, 10 paket ujian terstandar, dan jadwal kelas.*

#### Langkah 4: Menjalankan Server Aplikasi
* **Menggunakan XAMPP Apache:**
  Buka browser dan akses alamat:
  👉 `http://localhost/PRD/public`
* **Menggunakan PHP Built-in CLI Server:**
  ```bash
  php -S localhost:8000 -t public
  ```
  Lalu buka browser di: `http://localhost:8000`

---

## 🔑 Akun Uji Coba Bawaan (Demo Accounts)

Untuk mempermudah pengujian seluruh hak akses sistem:

| Peran (Role) | Email Login | Kata Sandi | Lingkup Hak Akses |
| :--- | :--- | :--- | :--- |
| **Super Admin** | `superadmin@example.com` | `SuperAdmin123!` | Kontrol penuh sistem, audit logs forensik, pengaturan AI & notifikasi. |
| **Administrator** | `admin@example.com` | `Admin123!` | Manajemen akun pengguna, kelas, mapel, jadwal terpusat, dan rekapitulasi. |
| **Guru / Pengawas** | `guru@example.com` | `Guru123!` | Kelola bank soal, paket ujian, live monitoring ujian, koreksi esai AI. |
| **Peserta Didik (MIPA)**| `siswa@example.com` | `Siswa123!` | Kalibrasi dual-cam, ruang ujian CBT, pengerjaan soal, submit nilai. |
| **Peserta Didik (IPS)** | `siswa.ips@example.com` | `Siswa123!` | Ruang ujian CBT khusus rumpun IPS & live AI chatbot pendamping. |

---

## 🧪 Pengujian Otomatis & DevOps CI/CD

Aplikasi dilengkapi *automated test suite* komprehensif untuk memastikan keandalan kode (*Reliability*) dan keamanan data (*Security*):

```bash
# 1. Menjalankan Security Regression Test Suite (19 Asersi Keamanan)
php tests/test_security_regression_suite.php

# 2. Menjalankan Pengujian Notifikasi Multi-Kanal (Email & WhatsApp)
php tests/test_otp_notification_integration.php

# 3. Menjalankan Audit Kualitas Respon AI Chatbot & Database Grounding
php tests/test_smart_exam_knowledge_and_db_to_the_point.php
```

### Otomatisasi GitHub Actions CI/CD:
File alur kerja [`.github/workflows/ci.yml`](.github/workflows/ci.yml) secara otomatis mengeksekusi pemeriksaan sintaks (*linting*), migrasi basis data pada kontainer MySQL layanan, serta verifikasi kelulusan uji keamanan pada setiap *push* dan *pull request* ke branch `main`.

---

## 📂 Struktur Direktori Proyek

```
smart-exam/
├── .github/workflows/ci.yml  # Pipeline CI/CD GitHub Actions
├── app/
│   ├── Config/              # Konfigurasi Aplikasi, Database, Routing, Notifikasi
│   ├── Controllers/         # Kontroler Presentasi & Logika Bisnis (MVC)
│   ├── Filters/             # Middleware (Auth, Role, CSRF, Rate-Limiting)
│   ├── Helpers/             # Fungsi Pembantu Global
│   ├── Libraries/           # Pustaka Database PDO, SmtpClient, WhatsAppClient
│   ├── Models/              # Model Relasional Entitas Basis Data
│   ├── Services/            # Service Layer (AI Chat, Proctoring, Grading, Queue)
│   └── Views/               # Tampilan Antarmuka Antarmuka Pengguna
├── database/
│   ├── migrations/          # Berkas Skema SQL Terstruktur
│   ├── seeders/             # Pembangkit Data Master & Demo
│   └── migrate.php          # Migration Engine CLI
├── public/                  # Dokumen Root Web Server (index.php, CSS, JS, Aset)
├── storage/                 # Direktori Log Sistem & Unggahan
├── tests/                   # Kumpulan Automated Test Suites
├── .env.example             # Template Konfigurasi Publik
├── .gitignore               # Proteksi Berkas Kredensial Sensitif
└── README.md                # Dokumentasi Utama Proyek OBE
```

---

## 📜 Lisensi & Pernyataan Orisinalitas

Proyek ini dikembangkan secara mandiri oleh **Salsa Nabila (NIM: 41257158)** untuk memenuhi persyaratan kelulusan Ujian Akhir Semester pada mata kuliah **Rekayasa Perangkat Lunak (TIKB2503021)** di **STMIK IKMI Cirebon**. Seluruh kode sumber, arsitektur sistem, dan integrasi fitur AI adalah karya orisinal yang mematuhi standar etika akademik dan integritas rekayasa perangkat lunak.
