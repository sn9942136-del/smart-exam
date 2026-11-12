# PANDUAN LENGKAP SKRIP PRESENTASI & VIDEO DEMO
## Smart Exam — Tugas Proyek Akhir Rekayasa Perangkat Lunak

> **Penyusun**: **Salsa Nabila** (NIM: **41257158**)  
> **Kelompok**: **Tiba Tiba Talun**  
> **Tema Topik**: **E-Learning & Smart Education AI**  
> **Dosen Pengampu**: **Yudhistira Arie Wijaya, M.Kom., CDS.**  
> **Mata Kuliah**: Rekayasa Perangkat Lunak (TIKB2503021) — STMIK IKMI Cirebon

---

## 📹 KETENTUAN UMUM REKAMAN VIDEO (SESUAI RUBRIK OBE)

1. **Wajah Terlihat Jelas**: Wajib menampilkan *webcam overlay* (wajah pembicara) di sudut layar sepanjang video untuk membuktikan keaslian presentasi mandiri.
2. **Audio Jernih**: Pastikan suara terdengar jelas tanpa *noise* berlebih.
3. **Format Unggah**: Unggah ke YouTube (*Unlisted* atau *Public*) atau Google Drive (*Anyone with the link can view*).
4. **Pencatatan Link**: Masukkan link video ke tabel ringkasan pada berkas `README.md` dan lembar pengesahan laporan.

---

## 🎬 1. SKRIP VIDEO PROGRES 1 (DURASI: 3 – 5 MENIT)
**Fokus**: *Problem Statement, Identifikasi Masalah, PRD/SRS, dan Batasan Sistem.*

### Timeline Presentasi:
* **Menit 00:00 – 01:00 | Pembukaan & Identitas Diri**
  > *"Assalamu’alaikum Warahmatullahi Wabarakatuh. Selamat pagi/siang kepada Bapak Dosen Pengampu, Bapak Yudhistira Arie Wijaya, M.Kom., CDS., dan rekan-rekan semuanya. Perkenalkan, saya Salsa Nabila dengan NIM 41257158, perwakilan dari kelompok 'Tiba Tiba Talun'. Pada kesempatan kali ini, saya akan mempresentasikan progres pertama tugas proyek akhir mata kuliah Rekayasa Perangkat Lunak dengan topik E-Learning & Smart Education AI, yang berjudul 'Smart Exam: Sistem Ujian Online Berbasis Web dengan Pengawasan Dual Camera AI dan Chatbot Operasional Terintegrasi Database'."*

* **Menit 01:00 – 02:30 | Latar Belakang & Identifikasi Masalah**
  > *"Latar belakang proyek ini berakar dari maraknya kecurangan akademik pada ujian daring konvensional. Kamera laptop tunggal memiliki blind spot yang luas di sisi samping meja peserta. Melalui Smart Exam, kami merumuskan solusi berbasis rekayasa perangkat lunak dengan pendekatan Dual Camera Proctoring dan AI Face Mesh di sisi browser, serta asisten operasional cerdas."*

* **Menit 02:30 – 04:00 | Spesifikasi Kebutuhan (PRD & MoSCoW)**
  > *"Kebutuhan sistem telah kami susun dalam dokumen PRD berbasis standar IEEE 830. Fitur Must Have mencakup Autentikasi 2FA OTP multi-kanal, Dual Camera WebRTC, AI Face Tracking via MediaPipe, Server-Authoritative Timer, dan AI Chatbot operasional terintegrasi live database. Batasan sistem dibangun menggunakan arsitektur Clean MVC berbasis PHP 8.2 dan MySQL 8.0 tanpa framework berat."*

* **Menit 04:00 – 05:00 | Rencana Tahap Selanjutnya & Penutup**
  > *"Pada tahap kedua selanjutnya, kami akan menyelesaikan perancangan desain arsitektur UML dan normalisasi database relasional. Terima kasih atas perhatian Bapak, Wassalamu’alaikum Warahmatullahi Wabarakatuh."*

---

## 🎬 2. SKRIP VIDEO PROGRES 2 (DURASI: 5 – 7 MENIT)
**Fokus**: *Desain Perangkat Lunak (SDD), 4 Diagram UML, Arsitektur Clean MVC, & Skema Database.*

### Timeline Presentasi:
* **Menit 00:00 – 01:00 | Pembukaan & Rekap Progres**
  > *"Halo semuanya, kembali lagi bersama saya Salsa Nabila, NIM 41257158 dari kelompok Tiba Tiba Talun. Pada progres kedua ini, saya akan mendemonstrasikan hasil perancangan sistem dan arsitektur perangkat lunak Smart Exam."*

* **Menit 01:00 – 02:30 | Arsitektur Sistem 3-Tier Clean MVC**
  > *"Sistem Smart Exam dibangun menggunakan arsitektur 3-Tier Clean MVC yang terbagi atas Client Layer, Security & Middleware Layer, Application Service Layer, dan Data Layer. Dengan arsitektur ini, logika bisnis pada Service terisolasi dengan baik dari Controller dan View, mematuhi prinsip SOLID dan SoC."*

* **Menit 02:30 – 04:30 | Penjelasan 4 Diagram UML Utama**
  > *"1. Use Case Diagram: Memetakan 10 use case utama untuk aktor Siswa, Guru, dan Admin.  
  > 2. Activity Diagram: Menjelaskan siklus mulai dari verifikasi OTP, kalibrasi dua kamera, pengerjaan ujian dengan auto-save, hingga submit.  
  > 3. Class Diagram: Menggambarkan hubungan inheritance BaseModel ke 30+ Model relasional dan keterhubungan Service Layer.  
  > 4. Sequence Diagram: Menampilkan interaksi pesan asinkron saat MediaPipe mendeteksi pelanggaran dan langsung mengirim rekaman telemetri ke database."*

* **Menit 04:30 – 06:00 | Perancangan Database (ERD)**
  > *"Basis data kami dirancang ternormalisasi hingga 3NF dengan relasi antar tabel pengguna, sesi ujian, jawaban, bank soal, telemetri pelanggaran, hingga log chat AI."*

* **Menit 06:00 – 07:00 | Rencana Implementasi & Penutup**
  > *"Rancangan ini siap masuk ke tahap pengkodean penuh, pembuatan unit test, serta konfigurasi CI/CD pipeline. Terima kasih."*

---

## 🎬 3. SKRIP VIDEO FINAL LIVE DEMO (DURASI: 8 – 12 MENIT)
**Fokus**: *Live Demonstration End-to-End, Validasi AI, Pengujian Otomatis, dan CI/CD Badge.*

### Timeline Rinci (Menit per Menit):

| Menit | Segmen / Topik | Panduan Tindakan di Layar (*Screen Action*) |
| :--- | :--- | :--- |
| **00:00 – 01:30** | **Pembukaan & Identitas Resmi** | Tampilkan slide judul / profil GitHub `sn9942136-del/smart-exam`. Wajah webcam aktif di sudut. Sebutkan nama **Salsa Nabila**, NIM **41257158**, Dosen **Yudhistira Arie Wijaya, M.Kom., CDS.**, STMIK IKMI Cirebon. |
| **01:30 – 03:00** | **Demo Autentikasi & Verifikasi OTP Multi-Kanal** | Buka halaman login (`/login`). Masukkan akun. Tunjukkan layar verifikasi OTP 6-digit. Jelaskan bahwa kode dikirim via Email SMTP Gmail / WhatsApp Gateway dan input manual oleh user demi keamanan. Klik verifikasi dan masuk ke Dashboard. |
| **03:00 – 05:00** | **Demo Fitur Unggulan 1: Dual Camera Proctoring & AI Face Mesh** | Masuk sebagai Siswa, pilih paket ujian aktif, masukkan token. Tunjukkan preview **Kamera Depan** (laptop) dan **Kamera Sekunder** (HP/kamera meja). Mulai ujian. Coba menoleh ke samping atau tutup wajah sebentar: tunjukkan indikator peringatan AI Face Mesh yang langsung muncul di layar dan tercatat di telemetri server. |
| **05:00 – 06:30** | **Demo Fitur Pengerjaan Ujian & Auto-Save Asinkron** | Jawab beberapa soal pilihan ganda dan esai. Tunjukkan bahwa jawaban tersimpan otomatis (*auto-save*) tanpa reload halaman, dan timer waktu ujian dikendalikan oleh server (*Server-Authoritative Timer*). |
| **06:30 – 08:00** | **Demo Fitur Unggulan 2: Chatbot AI Operasional Live Database** | Pindah ke Dashboard Admin/Guru. Buka widget Chatbot Asisten di pojok kanan bawah. Ketik pertanyaan: *"Berapa jumlah bank soal dan paket ujian yang aktif saat ini?"*. Tunjukkan chatbot menjawab secara instan, singkat, padat, dan akurat (contoh: 10 bank soal dan 10 paket ujian) berdasarkan data riil dari database MySQL. Tunjukkan juga menu pengaturan model AI (Gemini 3.5 Flash-Lite / 3.8 Flash) dan konfigurasi API Key. |
| **08:00 – 09:30** | **Demo Dashboard Monitoring & Koreksi Esai Cerdas** | Tunjukkan panel pemantauan pengawas live, log insiden kecurangan yang tadi terekam, serta fitur koreksi esai otomatis yang memberikan rekomendasi nilai berdasarkan rubrik. |
| **09:30 – 10:45** | **Bukti Pengujian & CI/CD GitHub Actions** | Buka terminal dan jalankan `php tests/run_tests.php` atau tunjukkan 8 test suite lulus 100%. Buka browser di tab repositori GitHub `sn9942136-del/smart-exam` -> menu **Actions**. Tunjukkan pipeline CI workflow status hijau (*passing*) dengan badge aktif di README.md. |
| **10:45 – 12:00** | **Kesimpulan & Penutup** | Rangkum capaian proyek: berhasil mengintegrasikan Dual Camera WebRTC, AI Face Tracking, dan Live Database Chatbot ke dalam Clean MVC yang aman dan teruji. Sampaikan terima kasih kepada Dosen Pengampu Pak Yudhistira Arie Wijaya. Salam penutup. |

---

## 💡 TIPS TEKNIS PERSIAPAN REKAMAN

1. **Software Perekam Rekomendasi**:
   - **OBS Studio** (Gratis & Sangat Stabil): Buat 2 source: *Display Capture* untuk layar dan *Video Capture Device* untuk webcam wajah Anda di sudut kanan bawah.
   - **Loom** atau **Clipchamp Windows 11**: Alternatif instan yang langsung merekam layar + webcam secara otomatis.
2. **Kesiapan Layanan Lokal**:
   - Pastikan Apache dan MySQL di XAMPP telah menyala.
   - Buka browser pada resolusi 1080p (Full HD) dan perbesar font browser (Ctrl + '+') jika teks terlihat kecil.
3. **Penyimpanan Link Video**:
   - Setelah video selesai direkam dan diunggah ke YouTube, salin URL video tersebut dan ganti placeholder `[Link Video]` di berkas `README.md`.

