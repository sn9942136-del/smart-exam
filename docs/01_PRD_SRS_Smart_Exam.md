# DOKUMEN SPESIFIKASI KEBUTUHAN PERANGKAT LUNAK (PRD / SRS)
## Smart Exam — Sistem Ujian Online Berbasis AI & Dual Camera Proctoring

> **Mata Kuliah**: Rekayasa Perangkat Lunak (TIKB2503021) — 4 SKS  
> **Dosen Pengampu**: Yudhistira Arie Wijaya, M.Kom., CDS.  
> **Institusi**: S1 Teknik Informatika — STMIK IKMI Cirebon  
> **Tahun Akademik**: 2026/2027 (Semester 3)  
> **Penyusun**: **Salsa Nabila** (NIM: **41257158**)  
> **Kelompok**: **Tiba Tiba Talun**  
> **Tema Topik**: **E-Learning & Smart Education AI**  
> **Repositori**: [https://github.com/sn9942136-del/smart-exam](https://github.com/sn9942136-del/smart-exam)

---

## 1. PENDAHULUAN

### 1.1 Latar Belakang
Perkembangan teknologi pembelajaran digital dan evaluasi daring (*Computer-Based Test / CBT*) telah diterapkan secara luas di berbagai jenjang pendidikan. Namun, kelemahan mendasar dari sistem ujian konvensional adalah tingginya risiko kecurangan (*cheating*) yang sulit dideteksi tanpa kehadiran fisik pengawas. Beberapa bentuk kecurangan umum meliputi:
1. Menoleh atau berkomunikasi dengan orang lain di luar jangkauan pandangan kamera laptop tunggal.
2. Membuka perangkat lain (smartphone, catatan fisik, tab browser lain) di samping laptop (*blind spot*).
3. Penggantian identitas peserta ujian (*impersonation*).

Proyek **Smart Exam** hadir sebagai solusi rekayasa perangkat lunak cerdas untuk mengatasi celah tersebut melalui pengawasan ganda (*Dual-Camera Proctoring*) berbasis WebRTC, deteksi wajah cerdas (*AI Face Tracking*) via MediaPipe, chatbot operasional cerdas terintegrasi basis data (*Google Gemini*), serta sistem verifikasi keamanan multi-faktor (OTP multi-kanal).

### 1.2 Tujuan Sistem
1. Mengembangkan sistem ujian online terpercaya yang memadukan kamera primer (wajah/layar) dan kamera sekunder (meja/lingkungan sekitar) secara *real-time*.
2. Memanfaatkan teknologi AI berbasis browser (MediaPipe) untuk *face tracking* tanpa membebani server secara komputasional.
3. Mengintegrasikan asisten virtual (*Operational AI Chatbot*) yang terhubung langsung dengan basis data live MySQL untuk mempermudah administrator dan guru dalam memantau statistik, jadwal, dan bank soal.
4. Menerapkan arsitektur perangkat lunak modular **Clean MVC (Model-View-Controller)** yang mematuhi prinsip SOLID, PSR, dan standar keamanan web (OWASP Top 10).

### 1.3 Ruang Lingkup Sistem
- **Pengguna**: Terdiri dari 3 aktor utama: *Super Admin/Administrator*, *Guru/Penguji*, dan *Siswa/Peserta Ujian*.
- **Platform**: Aplikasi Web responsif berbasis PHP 8.2+, MySQL 8.0, Vanilla JavaScript ES6+, dan Tailwind CSS.
- **Batasan**: Ujian memerlukan perangkat berkamera (laptop/webcam) dan koneksi internet stabil; kamera sekunder dapat dihubungkan menggunakan smartphone via URL pengawas WebRTC atau aplikasi perantara (DroidCam/Iriun).

---

## 2. DESKRIPSI KESELURUHAN (OVERALL DESCRIPTION)

### 2.1 Perspektif Produk
Smart Exam merupakan aplikasi web independen (*self-contained*) dengan arsitektur 3-Tier yang tidak bergantung pada framework pihak ketiga yang berat (seperti Laravel penuh), melainkan dibangun dengan kerangka Clean MVC internal yang ringan, cepat, dan terukur.

### 2.2 Karakteristik Pengguna (User Personas)
| Aktor | Tanggung Jawab & Akses |
| :--- | :--- |
| **Super Admin / Admin** | Mengelola seluruh pengguna (RBAC), konfigurasi server, manajemen sesi AI, audit log, pemantauan sistem, dan pengaturan API Key Gemini. |
| **Guru / Penguji** | Mengelola bank soal, membuat paket ujian, mengatur jadwal & durasi, memantau ujian live, melihat rekaman pelanggaran proctoring, serta menilai jawaban esai. |
| **Siswa / Peserta** | Melakukan verifikasi akun (login + OTP), kalibrasi kamera ganda, mengerjakan ujian dengan pengawasan cerdas, dan melihat riwayat nilai ujian. |

### 2.3 Batasan Perancangan & Lingkungan
- Bahasa Pemrograman: PHP 8.2 ke atas dengan ekstensi `pdo_mysql`, `curl`, `mbstring`, `openssl`, `gd`.
- Web Server: Apache / Nginx (XAMPP pada lingkungan pengembangan lokal).
- Basis Data: MySQL 8.0 / MariaDB 10.4+.
- Kompatibilitas Browser: Google Chrome 110+, Mozilla Firefox 110+, Microsoft Edge 110+ dengan dukungan WebRTC dan getUserMedia API.

---

## 3. SPESIFIKASI KEBUTUHAN SISTEM

### 3.1 Kebutuhan Fungsional (Functional Requirements) — MoSCoW Prioritization

| Kode FR | Modul | Deskripsi Kebutuhan | Prioritas |
| :--- | :--- | :--- | :--- |
| **FR-01** | **Autentikasi & OTP** | Sistem harus menyediakan login multi-role dengan verifikasi Two-Factor Authentication (2FA) via kode OTP 6 digit yang dikirimkan melalui Email SMTP atau WhatsApp Gateway. | **MUST HAVE** |
| **FR-02** | **Dual Camera Proctoring** | Sistem harus dapat menangkap video dari kamera utama (webcam depan) dan kamera sekunder (kamera sudut samping/meja) secara bersamaan selama ujian berlangsung. | **MUST HAVE** |
| **FR-03** | **AI Face Tracking** | Sistem harus mendeteksi keberadaan wajah peserta secara berkala menggunakan MediaPipe Face Mesh di sisi browser untuk mendeteksi wajah hilang, wajah berpaling, atau lebih dari 1 orang. | **MUST HAVE** |
| **FR-04** | **Server-Authoritative Timer**| Sistem harus mengontrol sisa waktu ujian di sisi server berdasarkan `started_at` dan `duration_minutes` agar tidak dapat dimanipulasi dengan mengubah jam komputer klien. | **MUST HAVE** |
| **FR-05** | **Penyimpanan Jawaban Otomatis** | Sistem harus menyimpan jawaban peserta secara otomatis (*auto-save*) secara periodik dan asinkron (AJAX fetch) tanpa memuat ulang halaman. | **MUST HAVE** |
| **FR-06** | **Manajemen Bank Soal & Ujian** | Guru/Admin harus dapat mengelola bank soal (pilihan ganda, esai), membuat paket ujian, mengacak urutan soal/opsi, dan mengatur jadwal pelaksanaan. | **MUST HAVE** |
| **FR-07** | **AI Asisten Operasional (Chatbot)** | Sistem harus menyediakan widget chatbot cerdas berbasis Google Gemini yang terhubung ke live database untuk menjawab kueri operasional (bank soal, jadwal, statistik). | **MUST HAVE** |
| **FR-08** | **Koreksi Esai Cerdas (AI)** | Sistem dapat memberikan rekomendasi skor dan umpan balik semantik terhadap jawaban esai siswa berdasarkan rubrik yang ditentukan guru. | **SHOULD HAVE** |
| **FR-09** | **Generator Soal AI** | Sistem dapat menghasilkan butir soal baru secara otomatis berdasarkan topik, tingkat kesulitan, dan Taksonomi Bloom menggunakan Gemini API. | **SHOULD HAVE** |
| **FR-10** | **Monitoring Ujian Real-Time** | Pengawas/Guru dapat melihat status peserta yang sedang aktif ujian, status koneksi kamera, dan log peringatan kecurangan yang terdeteksi. | **SHOULD HAVE** |
| **FR-11** | **Pencegahan Kecurangan Klien** | Sistem harus mendeteksi peristiwa keluar jendela (*tab switch* / *blur*), pemblokiran klik kanan, dan pencegahan salin-tempel (*copy-paste*). | **SHOULD HAVE** |
| **FR-12** | **Ekspor Laporan & Sertifikat** | Sistem dapat mengekspor rekapitulasi nilai dan berita acara ujian dalam format PDF dan Excel (.xlsx). | **COULD HAVE** |
| **FR-13** | **Audit Logging Forensik** | Sistem harus mencatat riwayat aktivitas administratif dan rekaman insiden pelanggaran untuk keperluan audit keaslian ujian. | **COULD HAVE** |

---

### 3.2 Kebutuhan Non-Fungsional (Non-Functional Requirements)

| Kategori | Parameter | Spesifikasi Standar |
| :--- | :--- | :--- |
| **Performa** | Waktu Respon (*Response Time*) | Rata-rata respon API penyimpanan jawaban < 300 ms pada koneksi normal. |
| **Keamanan** | Enkripsi Sandi | Algoritma `bcrypt` dengan cost factor 10. |
| **Keamanan** | Proteksi Mutasi Data | Setiap request POST/PUT/DELETE wajib menyertakan token CSRF yang valid. |
| **Keamanan** | Anti-Brute Force | Rate limiter memblokir upaya login setelah 5 kali kegagalan berturut-turut selama 15 menit. |
| **Keandalan** | Pemulihan Sesi (*Session Recovery*) | Jika koneksi internet terputus, jawaban terakhir yang tersimpan di server tetap utuh saat halaman dimuat ulang. |
| **Usabilitas** | Tampilan Antarmuka | Desain antarmuka responsif (Desktop, Tablet, Mobile) dengan kontras warna mematuhi standar WCAG 2.1 AA. |

---

## 4. MATRIKS KETERTELUSURAN (TRACEABILITY MATRIX)

| ID Kebutuhan | Komponen MVC Terkait | Tabel Basis Data Terkait | Berkas Pengujian |
| :--- | :--- | :--- | :--- |
| **FR-01 (Auth & OTP)** | `AuthController.php`, `OtpTokenModel.php` | `users`, `otp_tokens`, `roles` | `test_auth_flow.php` |
| **FR-02 (Dual Camera)**| `ExamProctoringController.php`, `exam-dual-camera.js` | `proctoring_sessions` | `test_proctoring_rules.php` |
| **FR-03 (AI Face Tracking)**| `ai-face-mesh.js`, `AiProctoringService.php` | `ai_violations`, `ai_warnings` | `test_violation_telemetry.php` |
| **FR-04 (Server Timer)**| `ExamSessionService.php`, `ExamSessionModel.php`| `exam_sessions` | `test_timer_authoritative.php` |
| **FR-05 (Auto-Save)** | `ExamAnswerController.php`, `ExamAnswerModel.php` | `exam_answers` | `test_answer_submission.php` |
| **FR-06 (Bank Soal)** | `QuestionBankController.php`, `ExamController.php` | `question_banks`, `questions`, `exams` | `test_question_bank_crud.php` |
| **FR-07 (AI Chatbot)** | `AiChatService.php`, `AiChatSessionModel.php` | `ai_chat_sessions`, `ai_chat_messages`| `test_ai_chat_reports.php` |

---

*Disahkan untuk pemenuhan Tahap 1 Penilaian OBE Mata Kuliah Rekayasa Perangkat Lunak STMIK IKMI Cirebon.*
