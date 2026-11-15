# SKRIP LENGKAP REKAMAN VIDEO PRESENTASI & LIVE DEMO SISTEM (15 MENIT)
## SMART EXAM: SISTEM UJIAN ONLINE BERBASIS WEB DENGAN PENGAWASAN DUAL CAMERA AI DAN CHATBOT OPERASIONAL TERINTEGRASI DATABASE

> **Identitas Mahasiswa**:
> * **Nama Lengkap**: **Salsa Nabila**
> * **NIM**: **41257158**
> * **Program Studi**: S1 Teknik Informatika — **STMIK IKMI Cirebon**
> * **Mata Kuliah / SKS**: Rekayasa Perangkat Lunak (TIKB2503021) / 4 SKS
> * **Dosen Pengampu**: **Yudhistira Arie Wijaya, M. Kom.**
> * **Nama Kelompok**: **Tiba Tiba Talun**
> * **Tema Topik Kelompok**: **E-Learning & Smart Education AI**
> * **Total Alokasi Durasi**: **15 Menit 00 Detik**

---

## ⏱️ TABEL ALOKASI WAKTU PRESENTASI (TOTAL: 15 MENIT)

| Bagian | Topik Pembahasan | Alokasi Waktu | Timecode Video |
| :---: | :--- | :---: | :---: |
| **I** | **Pembukaan Resmi, Problem Statement, & PRD (Analisis Kebutuhan)** | **3 Menit 30 Detik** | `00:00 – 03:30` |
| **II** | **Pemodelan 4 Diagram UML Versi 2.0 (Deep-Dive Arsitektur)** | **5 Menit 30 Detik** | `03:30 – 09:00` |
| **III** | **Konstruksi Kode Bersih, Integrasi AI, & Otomatisasi CI/CD** | **2 Menit 30 Detik** | `09:00 – 11:30` |
| **IV** | **Live Demo Aplikasi End-to-End (Skenario Riil Siswa, Guru & Admin)** | **3 Menit 00 Detik** | `11:30 – 14:30` |
| **V** | **Kesimpulan Capaian Proyek, Evaluasi Kurikulum OBE, & Penutup** | **0 Menit 30 Detik** | `14:30 – 15:00` |

---

## 📹 PETUNJUK TEKNIS SETUP REKAMAN OBS STUDIO
1. **Tata Letak Layar (*Canvas Layout*)**:
   * *Display Capture* (Layar Utama): Resolusi 1920x1080 (Full HD).
   * *Video Capture Device* (Webcam Wajah Mahasiswa): Diletakkan di sudut kanan bawah layar (*Picture-in-Picture* / PIP) dengan pencahayaan terang dan pakaian rapi formal/almamater.
2. **Kesiapan Tab Browser & Perangkat**:
   * **Tab 1**: Berkas Slide Presentasi / Dokumen Laporan Bab I-V.
   * **Tab 2**: Berkas 4 Diagram UML 4K UHD (`docs/diagrams/`).
   * **Tab 3**: Aplikasi Smart Exam lokal (`http://localhost/PRD/public`).
   * **Tab 4**: Repositori GitHub (`https://github.com/sn9942136-del/smart-exam`) dan tab **Actions** (CI/CD).
   * **VS Code**: Membuka project `PRD` pada direktori `app/` dan `tests/`.

---

# 🎙️ NASKAH KATA DEMI KATA (WORD-FOR-WORD SCRIPT)

---

### BAGIAN I: PEMBUKAAN, PROBLEM STATEMENT, & PRD (3 MENIT 30 DETIK)
**Target Waktu: Menit `00:00` s.d `03:30`**

#### 1. Pembukaan Resmi & Identitas Akademik [00:00 – 00:45]
* **Tampilan di Layar**: Tampilkan Slide Judul Presentasi atau Halaman Depan Laporan Tugas Akhir yang memuat Logo STMIK IKMI Cirebon dan identitas lengkap mahasiswa.
* **Aksi Pembicara**: Tatap kamera webcam, senyum ramah, dan berbicara dengan nada percaya diri, lugas, serta terstruktur.
* **Narasi Suara**:
  > *"Bismillahirrohmanirrohim. Assalamu’alaikum Warahmatullahi Wabarakatuh.  
  > Selamat pagi, siang, dan salam sejahtera bagi kita semua.  
  > Yang terhormat Bapak Dosen Pengampu mata kuliah Rekayasa Perangkat Lunak, Bapak **Yudhistira Arie Wijaya, M. Kom.**, serta rekan-rekan mahasiswa yang saya banggakan.  
  > 
  > Perkenalkan, nama saya **Salsa Nabila**, dengan Nomor Induk Mahasiswa **41257158**, mahasiswi Program Studi S1 Teknik Informatika, Sekolah Tinggi Manajemen Informatika dan Komputer — **STMIK IKMI Cirebon**. Saya tergabung dalam kelompok **Tiba Tiba Talun** dengan fokus topik **E-Learning & Smart Education AI**.  
  > 
  > Pada kesempatan ujian akhir semester berbasis kurikulum Outcome-Based Education (OBE) kali ini, saya akan mempresentasikan secara komprehensif karya proyek akhir mandiri saya yang berjudul:  
  > **'Smart Exam: Sistem Ujian Online Berbasis Web dengan Pengawasan Dual Camera AI dan Chatbot Operasional Terintegrasi Database'**."*

---

#### 2. Latar Belakang & 4 Kelemahan CBT Konvensional [00:45 – 01:50]
* **Tampilan di Layar**: Tampilkan Slide Latar Belakang Masalah (Bab 1.1) dengan diagram perbandingan kamera tunggal vs kamera ganda.
* **Narasi Suara**:
  > *"Bapak Dosen dan rekan-rekan sekalian, peralihan evaluasi pembelajaran ke ranah digital atau Computer-Based Test (CBT) saat ini menghadapi tantangan terbesar, yaitu **merosotnya integritas akademik akibat maraknya kecurangan peserta**.  
  > 
  > Sistem CBT daring konvensional pada umumnya hanya mengandalkan **satu webcam laptop**. Dari hasil analisis kami, pengawasan kamera tunggal ini memiliki empat kelemahan fatal:  
  > 1. **Titik Buta Fisik (*Blind Spots*)**: Kamera depan hanya menangkap area wajah dan dada ke atas. Area meja kerja, keyboard, dan kedua tangan peserta sama sekali tidak terpantau.  
  > 2. **Kecurangan Perangkat Kedua (*Secondary Device*)**: Peserta dapat dengan mudah menyembunyikan smartphone atau kertas catatan di bawah layar untuk mencari jawaban di internet atau grup perpesanan.  
  > 3. **Beban Server & Pelanggaran Privasi**: Jika seluruh video peserta direkam dan diunggah ke server cloud, kebutuhan bandwidth internet institusi akan melonjak drastis dan biaya komputasi GPU server menjadi sangat mahal.  
  > 4. **Lambatnya Analisis Operasional**: Pengawas dan manajemen sekolah kesulitan membaca puluhan jadwal, ketersediaan bank soal, dan anomali nilai secara instan di tengah ribuan rekaman log sistem.  
  > 
  > Berangkat dari permasalahan tersebut, **Smart Exam** hadir sebagai solusi rekayasa perangkat lunak modern yang memadukan pengawasan ganda kamera (*Dual-Camera Proctoring*), kecerdasan artifisial di sisi browser (*Edge AI Vision*), serta asisten operasional cerdas yang terintegrasi langsung dengan live database."*

---

#### 3. Pemaparan PRD / SRS & Spesifikasi Kebutuhan (MoSCoW) [01:50 – 03:30]
* **Tampilan di Layar**: Tampilkan Tabel Spesifikasi Kebutuhan Perangkat Lunak (PRD/SRS) pada Bab 3.1, sorot kategori Must Have dan Should Have.
* **Narasi Suara**:
  > *"Seluruh kebutuhan sistem telah kami formulasikan ke dalam dokumen **Product Requirement Document (PRD)** atau SRS berstandar IEEE 830, yang kami klasifikasikan menggunakan skala prioritas **MoSCoW**:  
  > 
  > * **Kategori MUST HAVE**:  
  >   1. **FR-01 Autentikasi Akun**: Login multi-role dengan hashing sandi bcrypt dan proteksi rate limiter anti-brute force.  
  >   2. **FR-02 Two-Factor Authentication (2FA)**: Penerbitan kode OTP 6 digit melalui Gmail SMTP dan WhatsApp Gateway yang **wajib diketik manual** oleh pengguna untuk membuktikan kepemilikan fisik perangkat.  
  >   3. **FR-03 Dual Camera WebRTC**: Penangkapan simultan kamera primer depan laptop dan kamera sekunder smartphone peserta yang diletakkan di sudut samping meja.  
  >   4. **FR-04 Server-Authoritative Timer**: Kendali sisa waktu ujian mutlak diatur oleh jam server, kebal terhadap manipulasi jam di komputer klien.  
  >   5. **FR-05 Idempotent Auto-Save**: Penyimpanan lembar jawaban secara asinkron via AJAX tanpa reload halaman.  
  >   6. **FR-06 Client-Side AI Vision**: Pustaka MediaPipe Face Mesh mendeteksi gerakan wajah menoleh (*look away*), wajah hilang, atau wajah ganda.  
  >   7. **FR-08 Operational AI Chatbot**: Asisten cerdas berbasis Google Gemini dengan teknik *Live Database Grounding* yang membaca 50+ tabel MySQL secara real-time.  
  > 
  > * **Kategori SHOULD & COULD HAVE**:  
  >   Mencakup koreksi esai semantik otomatis berbantuan AI, monitoring pengawas real-time, perlindungan lock-browser dari perpindahan tab, generator butir soal otomatis, audit logging forensik terenkripsi, serta preferensi tema Dark dan Light Mode.  
  > 
  > Secara Non-Fungsional, sistem menjamin waktu respon penyimpanan jawaban di bawah 300 milidetik, konsumsi bandwidth telemetri di bawah 50 Kilobyte per menit karena komputasi vision dilakukan di sisi browser, serta kepatuhan penuh terhadap standar keamanan OWASP Top 10 dan Undang-Undang Perlindungan Data Pribadi."*

---

### BAGIAN II: PEMODELAN 4 DIAGRAM UML VERSI 2.0 (5 MENIT 30 DETIK)
**Target Waktu: Menit `03:30` s.d `09:00`**

#### 1. Use Case Diagram Versi 2.0 (6 Aktor: 4 di Kiri & 2 di Kanan) [03:30 – 04:50]
* **Tampilan di Layar**: Buka gambar resolusi 4K `01_use_case_diagram.png`. Zoom in pada batas sistem dan susunan aktor.
* **Narasi Suara**:
  > *"Selanjutnya, mari kita masuk ke perancangan arsitektur perangkat lunak pada Bab 3, diawali dengan **Use Case Diagram Versi 2.0**.  
  > 
  > Diagram Use Case ini dirancang mematuhi standar notasi UML 2.5 dengan pemisahan tata letak yang sangat rapi untuk mencapai **zero line crossings** atau garis tanpa saling silang:  
  > * Di sisi **kiri**, terdapat **4 Aktor Manusia (Primer)** yang digambarkan dengan garis solid:  
  >   1. **Super Admin**: Memegang hak akses tertinggi, konfigurasi model AI, dan audit log forensik.  
  >   2. **Administrator**: Mengelola data master akademik, kelas, mata pelajaran, dan akun pengguna.  
  >   3. **Guru / Pengawas**: Menyusun bank soal, merilis paket ujian, melakukan live monitoring, dan mengevaluasi penilaian esai.  
  >   4. **Peserta Didik (Siswa)**: Menjalankan kalibrasi dual-cam, mengerjakan soal CBT, dan melihat hasil evaluasi.  
  > 
  > * Di sisi **kanan**, terdapat **2 Aktor Sistem Sekunder** bergaris putus-putus dengan stereotipe `<<System>>`:  
  >   1. **AI Proctoring Engine**: Layanan MediaPipe Face Mesh yang terhubung langsung ke use case kalibrasi kamera dan deteksi kecurangan.  
  >   2. **Sistem Layanan OTP**: Gateway eksternal Gmail SMTP dan WhatsApp API yang terhubung dengan relasi `<<include>>` pada use case autentikasi dan login.  
  > 
  > Seluruh use case tertata dalam dua kolom simetris di dalam *system boundary*, memastikan batas wewenang tiap peran terisolasi secara transparan."*

---

#### 2. Activity Diagram Versi 2.0 (Alur 3 Swimlane Formal) [04:50 – 06:10]
* **Tampilan di Layar**: Buka gambar resolusi 4K `02_activity_diagram.png`. Telusuri alur dari Swimlane 1 ke Swimlane 2 dan 3.
* **Narasi Suara**:
  > *"Diagram kedua adalah **Activity Diagram Versi 2.0** yang memodelkan alur kerja pelaksanaan ujian dan AI proctoring secara end-to-end.  
  > Diagram ini membagi tanggung jawab proses ke dalam **3 Swimlane Formal** dengan perutean siku tegak lurus (*orthogonal connectors*):  
  > 
  > 1. **Swimlane 1 — Peserta Ujian (Siswa)**: Dimulai dari inisial node saat siswa memasukkan email dan sandi, mengetikkan kode OTP 6-digit secara manual, melakukan kalibrasi dua kamera, mengerjakan butir soal, hingga menekan tombol selesai ujian.  
  > 2. **Swimlane 2 — Sistem Smart Exam (Clean MVC Backend)**: Bertanggung jawab memverifikasi hash bcrypt, mengotorisasi sesi pada basis data, mengaktifkan timer mutlak server, menerima sinkronisasi auto-save jawaban asinkron, hingga mengunci status lembar jawaban menjadi `submitted`.  
  > 3. **Swimlane 3 — AI Engine & Layanan Gateway**: Mengorkestrasi penerbitan OTP multi-kanal via SMTP/WhatsApp, serta menjalankan algoritma MediaPipe Face Mesh setiap 3 detik secara paralel untuk memindai sudut pandang mata dan orientasi kepala siswa.  
  > 
  > Adanya *decision node* pada validasi OTP dan pencabangan aktivitas proctoring di latar belakang memastikan alur pengerjaan ujian tetap lancar tanpa ada proses yang memblokir antarmuka pengguna."*

---

#### 3. Class Diagram Versi 2.0 (Arsitektur Clean MVC 4-Tier & Multiplicity) [06:10 – 07:35]
* **Tampilan di Layar**: Buka gambar resolusi 4K `03_class_diagram.png`. Tunjukkan pembagian tier warna: Controller (Ungu), Security (Merah), Service (Hijau), dan Model (Biru).
* **Narasi Suara**:
  > *"Diagram ketiga adalah **Class Diagram Versi 2.0**, yang memetakan konstruksi berorientasi objek sistem Smart Exam mengikuti arsitektur **Clean MVC 4-Tier** dan berpegang teguh pada prinsip **SOLID**:  
  > 
  > * **Tier 1 — Presentation Layer (Controller)**: Terdapat `ExamController`, `AuthController`, dan `AiChatController`. Kontroler hanya bertindak sebagai penerima HTTP request dan pengembali response view atau JSON, mematuhi *Single Responsibility Principle*.  
  > * **Tier 2 — Security Middleware Layer**: Berisi `SecurityFilters` seperti `CsrfFilter` dan `RoleFilter` yang mengawal setiap rute sebelum mencapai kontroler.  
  > * **Tier 3 — Application Service Layer**: Menampung logika bisnis terisolasi, yaitu `ExamSessionService` untuk kalkulasi skor dan timer, `AiProctoringService` untuk validasi telemetri kecurangan, `AiChatService` untuk grounding live database, serta `NotificationQueueService` untuk manajemen antrean email dan WhatsApp.  
  > * **Tier 4 — Persistence Data Layer (Model)**: Seluruh model entitas seperti `UserModel`, `ExamModel`, `ExamSessionModel`, dan `AiViolationModel` mewarisi kelas abstrak `BaseModel` melalui panah generalisasi segitiga kosong tertutup untuk operasi PDO terparameterisasi.  
  > 
  > Multiplisitas relasi terdefinisi secara presisi: satu `UserModel` memiliki hubungan 1 ke 0..* terhadap `ExamSessionModel`, satu `ExamModel` terhubung 1 ke 0..* sesi ujian, dan satu sesi ujian menampung 1 ke banyak baris jawaban di `ExamAnswerModel`."*

---

#### 4. Sequence Diagram Versi 2.0 (Interaksi 23 Langkah & Activation Bars) [07:35 – 09:00]
* **Tampilan di Layar**: Buka gambar resolusi 4K `04_sequence_diagram.png`. Sorot 6 lifelines, balok aktivasi, dan area kotak *Loop Frame*.
* **Narasi Suara**:
  > *"Diagram pemodelan keempat adalah **Sequence Diagram Versi 2.0** yang merinci urutan pertukaran pesan secara kronologis antar **6 Lifelines**: Peserta, Browser Client MediaPipe, Controller/Filter, Service Layer, Database MySQL, dan AI/Gateway Service.  
  > 
  > Diagram ini memiliki **23 langkah interaksi terstruktur** yang dilengkapi balok aktivasi (*focus of control*) nyata:  
  > * **Fase 1 (Langkah 1–9)**: Alur autentikasi dan 2FA. Browser mengirim login → verifikasi hash di database → pembuatan token OTP → siswa menerima pesan di ponsel → siswa mengetikkan kode secara manual → server memvalidasi masa aktif 5 menit → status token diubah menjadi `is_used = 1` → dashboard terbuka.  
  > * **Fase 2 (Langkah 10–15)**: Inisialisasi ujian. Kalibrasi dua kamera → aktivasi landmark mesh → request `POST /exam/start` → `ExamSessionService` mencatat waktu mulai resmi di tabel `exam_sessions` → server mengembalikan durasi sisa detik.  
  > * **Fase 3 (Langkah 16–20 pada Kotak Loop Frame)**: Selama ujian berlangsung setiap 3 detik, MediaPipe memindai landmark wajah. Jika terjadi anomali sudut menoleh atau wajah hilang, browser mengirim telemetri ke endpoint proctoring yang langsung dicatat ke tabel `ai_violations`, bersamaan dengan transmisi auto-save jawaban siswa ke tabel `exam_answers`.  
  > * **Fase 4 (Langkah 21–23)**: Penyerahan ujian. Siswa klik selesai ujian → sistem mengunci baris sesi menjadi `submitted`, menghitung otomatis nilai pilihan ganda, dan menutup ruang ujian secara permanen."*

---

### BAGIAN III: KONSTRUKSI KODE, INTEGRASI AI, & TESTING CI/CD (2 MENIT 30 DETIK)
**Target Waktu: Menit `09:00` s.d `11:30`**

#### 1. Konstruksi Kode Bersih & Clean MVC Engine [09:00 – 09:50]
* **Tampilan di Layar**: Buka aplikasi VS Code. Tampilkan struktur direktori `app/` (`Config`, `Controllers`, `Filters`, `Libraries`, `Models`, `Services`). Buka salah satu file kontroler dan model.
* **Narasi Suara**:
  > *"Beralih ke Bab 4, mari kita bedah implementasi konstruksi kode perangkat lunak.  
  > Proyek Smart Exam dibangun dengan arsitektur **Custom Clean MVC mandiri berbasis PHP 8.2+ Strict Types** tanpa ketergantungan framework pihak ketiga yang berat:  
  > * Struktur direktori dipisahkan secara tegas: `app/Controllers` menangani HTTP request, `app/Services` mengisolasi aturan bisnis logika, `app/Models` mengelola kueri relasional, dan `app/Filters` bertindak sebagai middleware penjaga keamanan.  
  > * Seluruh komunikasi basis data menggunakan **PDO Prepared Statements terparameterisasi 100%**, menjamin sistem kebal terhadap SQL Injection.  
  > * Keamanan form dilindungi token CSRF kriptografis, sanitasi output XSS via `htmlspecialchars ENT_QUOTES`, serta mekanisme Rate-Limiting berbasis IP dan session."*

---

#### 2. Implementasi Integrasi AI: MediaPipe Vision & Google Gemini Grounding [09:50 – 10:45]
* **Tampilan di Layar**: Buka berkas `public/assets/js/exam-dual-camera.js` dan `app/Services/AI/AiChatService.php`. Tunjukkan fungsi komputasi landmark MediaPipe dan integrasi kueri live DB Chatbot.
* **Narasi Suara**:
  > *"Sistem ini mengintegrasikan dua pilar Kecerdasan Artifisial modern:  
  > 1. **Client-Side Edge AI Vision (MediaPipe Face Mesh)**:  
  >    Pemrosesan landmark 468 titik wajah dihitung langsung di peramban pengguna menggunakan WebAssembly dan Canvas API. Sudut Yaw dan Pitch dihitung secara lokal. Jika siswa menoleh melebihi ambang 30 derajat selama 3 detik, sistem mencatat status pelanggaran `look_away`. Karena video tidak diunggah ke server, bandwidth yang dibutuhkan sangat hemat—kurang dari 50 KB per menit—dan privasi siswa terlindungi penuh sesuai UU Perlindungan Data Pribadi.  
  > 
  > 2. **Operational Live Database Chatbot (Google Gemini 3.5 & 3.8)**:  
  >    Asisten AI kami tidak sekadar chatbot percakapan umum. Dengan teknik **Live Database Grounding**, backend membaca data agregat dari 50+ tabel relasional MySQL secara real-time, lalu menyuntikkannya ke dalam konteks prompt. Chatbot mampu menjawab pertanyaan seputar jadwal, bank soal, data pengguna, hingga status ujian secara singkat, padat, to-the-point, dan bebas dari halusinasi data."*

---

#### 3. Otomatisasi Pengujian & Status Hijau CI/CD GitHub Actions [10:45 – 11:30]
* **Tampilan di Layar**: Buka terminal VS Code, jalankan perintah pengujian: `php tests/test_security_regression_suite.php` (menampilkan 19 PASS). Kemudian beralih ke browser di tab GitHub Actions repositori `sn9942136-del/smart-exam` yang menampilkan tanda centang hijau *passing*.
* **Narasi Suara**:
  > *"Untuk menjamin keandalan perangkat lunak, kami mengimplementasikan **Automated Testing Suite** yang terdiri dari 8 berkas pengujian otomatis dan **19 Asersi Security Regression**:  
  > Seperti yang terlihat pada terminal, seluruh 19 asersi keamanan—mulai dari isolasi peran, pencegahan double submit, manipulasi timer, hingga proteksi brute force—**lulus 100% (PASS)**.  
  > 
  > Seluruh kode sumber dikelola menggunakan Git pada repositori publik GitHub `sn9942136-del/smart-exam`. Kami mengonfigurasi pipa **Continuous Integration & Continuous Deployment (CI/CD)** melalui GitHub Actions (`.github/workflows/ci.yml`). Setiap kali ada kode baru di-*push*, sistem otomatis membangun kontainer MySQL, menjalankan migrasi basis data, memeriksa sintaks PHP, dan mengeksekusi test suite. Hasilnya, **badge CI/CD berstatus hijau (Passing)**, memenuhi 100% rubrik penilaian mandiri OBE."*

---

### BAGIAN IV: LIVE DEMO APLIKASI END-TO-END (3 MENIT 00 DETIK)
**Target Waktu: Menit `11:30` s.d `14:30`**

#### 1. Demo Login & Verifikasi 2FA OTP Manual [11:30 – 12:10]
* **Tampilan di Layar**: Buka halaman login browser `http://localhost/PRD/public/login`.
* **Aksi Pembicara**: Masukkan akun siswa (`siswa@example.com` / `Siswa123!`), klik Masuk. Layar beralih ke `/verify-otp`.
* **Narasi Suara**:
  > *"Sekarang, mari kita saksikan demonstrasi langsung sistem Smart Exam secara end-to-end.  
  > Pertama, saya masuk sebagai Siswa. Saya masukkan email dan kata sandi, lalu klik Masuk.  
  > Sistem mengarahkan ke halaman **Verifikasi Two-Factor Authentication (2FA)**.  
  > Kode OTP 6 digit telah dibuat secara kriptografis dan dikirimkan secara instan melalui Gmail SMTP dan WhatsApp Gateway.  
  > Perhatikan bahwa demi standar autentikasi fisik yang sebenarnya, kode OTP ini tidak disuntikkan otomatis, melainkan **wajib diketikkan secara manual** oleh pengguna ke enam kotak input ini. Saya masukkan kode OTP, klik Verifikasi, dan kita berhasil masuk ke antarmuka Dashboard Siswa."*

---

#### 2. Demo Kalibrasi Dual Camera & Pengerjaan Ujian CBT [12:10 – 13:20]
* **Tampilan di Layar**: Masuk ke menu Ujian, pilih paket ujian aktif, masukkan token. Tampilkan modal kalibrasi kamera.
* **Aksi Pembicara**: 
  1. Tunjukkan feed **Kamera Depan (Webcam)** mendeteksi wajah.
  2. Tunjukkan feed **Kamera Samping (Smartphone via DroidCam/WebRTC)** memantau area meja kerja.
  3. Klik Mulai Ujian. Halaman ruang ujian terbuka.
  4. Gerakkan kepala menoleh ke samping kiri/kanan selama beberapa detik sampai muncul notifikasi peringatan proctoring.
  5. Jawab satu soal pilihan ganda, tunjukkan indikator auto-save tersimpan.
* **Narasi Suara**:
  > *"Langkah kedua adalah inisialisasi ujian. Siswa memilih paket ujian aktif dan memasukkan token.  
  > Muncul antarmuka **Kalibrasi Dual Camera WebRTC**: di sebelah kiri adalah Kamera 1 (webcam depan laptop) untuk memantau wajah, dan di sebelah kanan adalah Kamera 2 (kamera smartphone) yang diarahkan ke meja kerja untuk mengawasi tangan dan perangkat sekunder.  
  > 
  > Kita klik Mulai Ujian. Sesi ujian resmi aktif dengan **Server-Authoritative Timer** yang menghitung mundur sisa waktu secara presisi dari server.  
  > Sekarang perhatikan algoritma AI Proctoring: saat saya memalingkan wajah ke samping selama lebih dari 3 detik... sistem MediaPipe langsung mendeteksi anomali *look away* dan menampilkan peringatan di layar! Pelanggaran ini langsung dicatat ke tabel `ai_violations`.  
  > Saat saya memilih jawaban soal nomor satu, perhatikan pojok atas: jawaban tersimpan secara instan via AJAX auto-save tanpa perlu memuat ulang halaman."*

---

#### 3. Demo Asisten Chatbot AI Operasional & Live Monitoring Pengawas [13:20 – 14:30]
* **Tampilan di Layar**: Beralih akun (login sebagai Admin / Guru) di tab lain. Buka menu AI Chatbot di kanan bawah, lalu buka halaman Live Monitoring Ujian.
* **Aksi Pembicara**: 
  1. Ketik di widget Chatbot: *"siapa saya"* -> Chatbot menjawab: *"Anda adalah Arie (Admin) pada Smart Exam."* dengan badge `[Live DB]`.
  2. Ketik di Chatbot: *"ada berapa paket ujian di sistem?"* -> Chatbot menjawab jumlah paket ujian akurat dari live DB.
  3. Buka halaman Live Monitoring Pengawas, tunjukkan log pelanggaran yang tadi dilakukan oleh siswa.
* **Narasi Suara**:
  > *"Kini kita beralih ke peran Administrator dan Guru Pengawas.  
  > Saya buka widget **AI Chatbot Operasional** di pojok kanan bawah. Chatbot ini terhubung langsung ke basis data aktif:  
  > Saat saya ketik pertanyaan: **'siapa saya'**, chatbot membaca tabel pengguna dan langsung menjawab dengan tepat: **'Anda adalah Arie (Admin) pada Smart Exam.'** berlabel Live DB.  
  > Saat saya tanyakan: **'ada berapa paket ujian di sistem?'**, chatbot menghitung data riil tabel `exams` dan menjawab langsung secara ringkas dan to-the-point tanpa basa-basi!  
  > 
  > Terakhir, kita buka menu **Live Monitoring Ujian**: pengawas dapat melihat daftar peserta yang sedang aktif, status koneksi dua kamera, serta grafik rekaman pelanggaran proctoring yang tadi terekam secara real-time. Guru juga dapat menggunakan modul koreksi esai cerdas untuk menilai jawaban esai siswa berdasarkan rubrik evaluasi."*

---

### BAGIAN V: KESIMPULAN, CAPAIAN OBE, & PENUTUP (0 MENIT 30 DETIK)
**Target Waktu: Menit `14:30` s.d `15:00`**

* **Tampilan di Layar**: Tampilkan Slide Penutup yang memuat rangkuman capaian, ucapan terima kasih, dan tautan GitHub. Tampilkan webcam wajah presenter secara dominan.
* **Aksi Pembicara**: Tatap kamera dengan sikap ramah, penuh percaya diri, dan profesional.
* **Narasi Suara**:
  > *"Bapak Yudhistira Arie Wijaya, M. Kom. dan rekan-rekan sekalian,  
  > Dari serangkaian presentasi dan demonstrasi tadi, dapat disimpulkan bahwa proyek **Smart Exam** telah berhasil memenuhi seluruh capaian pembelajaran kurikulum Outcome-Based Education (OBE): mulai dari analisis kebutuhan PRD berstandar IEEE 830, pemodelan 4 diagram UML Versi 2.0 yang komprehensif, arsitektur Clean MVC berbasis prinsip SOLID, pengawasan Dual Camera AI, hingga integrasi CI/CD otomatis berstatus passing.  
  > 
  > Saya mengucapkan terima kasih yang sebesar-besarnya kepada Bapak Dosen Pengampu atas seluruh bimbingan dan arahannya sepanjang semester ini.  
  > Mohon maaf atas segala kekurangan.  
  > 
  > Wabillahi taufiq wal hidayah,  
  > Wassalamu’alaikum Warahmatullahi Wabarakatuh."*

---

## 🎯 PANDUAN CHECKLIST SEBELUM MENEKAN TOMBOL RECORD
- [ ] Layar laptop diset ke resolusi 1920x1080 (100% scaling).
- [ ] Apache dan MySQL di XAMPP berstatus aktif (port 80 dan 3306 menyala hijau).
- [ ] File slide presentasi / dokumen laporan telah dibuka pada posisi awal.
- [ ] Keempat diagram UML 4K telah disiapkan di penampil gambar (*Image Viewer* / browser tab).
- [ ] Mikrofon telah diuji suara (*sound check*) dan tidak ada dengung / *noise*.
- [ ] Webcam wajah telah diposisikan di sudut kanan bawah tanpa menutupi konten penting sistem.
- [ ] Stopwatch / timer disiapkan di samping laptop untuk memandu transisi tiap bagian pas 15 menit.

