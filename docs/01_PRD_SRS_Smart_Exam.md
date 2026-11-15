# DOKUMEN SPESIFIKASI KEBUTUHAN PRODUK & PERANGKAT LUNAK (PRD / SRS)
## Smart Exam — Sistem Ujian Online Berbasis AI & Dual Camera Proctoring

> **Mata Kuliah**: Rekayasa Perangkat Lunak (TIKB2503021) — 4 SKS (Semester 3)  
> **Dosen Pengampu**: Yudhistira Arie Wijaya, M. Kom.  
> **Program Studi**: S1 Teknik Informatika — **STMIK IKMI Cirebon**  
> **Penyusun**: **Salsa Nabila** (NIM: **41257158**)  
> **Kelompok**: **Tiba Tiba Talun**  
> **Tema Topik Kelompok**: **E-Learning & Smart Education AI**  
> **Versi Dokumen**: 2.0 (Final OBE Release)  
> **Repositori Resmi**: [https://github.com/sn9942136-del/smart-exam](https://github.com/sn9942136-del/smart-exam)

---

## DAFTAR ISI
1. [Ringkasan Eksekutif & Latar Belakang](#1-ringkasan-eksekutif--latar-belakang)
2. [Visi Produk & Sasaran Strategis](#2-visi-produk--sasaran-strategis)
3. [Analisis Pengguna & Stakeholder (6 Aktor)](#3-analisis-pengguna--stakeholder-6-aktor)
4. [Spesifikasi Kebutuhan Fungsional (FR-01 s.d. FR-15)](#4-spesifikasi-kebutuhan-fungsional-fr-01-sd-fr-15)
5. [Spesifikasi Kebutuhan Non-Fungsional (NFR-01 s.d. NFR-08)](#5-spesifikasi-kebutuhan-non-fungsional-nfr-01-sd-nfr-08)
6. [Batasan Sistem, Asumsi, & Dependensi](#6-batasan-sistem-asumsi--dependensi)
7. [Spesifikasi Antarmuka & Protokol Komunikasi](#7-spesifikasi-antarmuka--protokol-komunikasi)
8. [Perlindungan Privasi & Keamanan Data (UU PDP)](#8-perlindungan-privasi--keamanan-data-uu-pdp)
9. [Matriks Ketertelusuran Kebutuhan (RTM)](#9-matriks-ketertelusuran-kebutuhan-rtm)
10. [Kriteria Penyelesaian & Kelayakan Rilis (Definition of Done)](#10-kriteria-penyelesaian--kelayakan-rilis-definition-of-done)

---

## 1. RINGKASAN EKSEKUTIF & LATAR BELAKANG

### 1.1 Masalah Utama Industri CBT (*Problem Statement*)
Pelaksanaan ujian daring (*Computer-Based Test / CBT*) pasca-pandemi menghadapi krisis integritas akademik yang masif. Pengawasan konvensional berbasis satu kamera laptop (*single webcam*) terbukti memiliki kelemahan fatal:
1. **Titik Buta yang Luas (*Blind Spots*)**: Kamera depan laptop hanya menyorot wajah peserta dari batas dada ke atas. Area meja kerja, layar smartphone tersembunyi di bawah laptop, buku catatan, atau bantuan pihak kedua di samping peserta tidak terjangkau.
2. **Kecurangan Perangkat Paralel**: Peserta sering kali membuka grup diskusi WhatsApp / Telegram di smartphone atau menggunakan perangkat kedua di samping layar untuk mencari jawaban.
3. **Beban Komputasi Server Ekstrem**: Sistem pengawasan video AI terpusat (*cloud-heavy video processing*) membutuhkan bandwidth dan biaya server GPU yang sangat mahal untuk memproses puluhan *stream* video secara simultan.
4. **Lambatnya Analisis Data Ujian**: Pengawas dan guru kesulitan memeriksa rekapitulasi ujian, bank soal, dan kejanggalan statistik secara cepat di tengah ribuan data log.

### 1.2 Solusi Rekayasa: Smart Exam
**Smart Exam** memadukan pendekatan **Dual-Camera Proctoring** berbasis WebRTC dengan **Client-Side AI Edge Processing** (MediaPipe Face Mesh) dan **Operational Live Database Chatbot** (Google Gemini):
* **Kamera Primer (Webcam Depan)**: Memantau wajah, estimasi arah pandangan (*gaze direction*), dan mendeteksi ketiadaan wajah atau kehadiran orang kedua.
* **Kamera Sekunder (Smartphone via WebRTC / DroidCam)**: Diletakkan pada sudut 45 derajat di samping meja peserta untuk memantau tangan, keyboard, dan meja kerja secara *real-time*.
* **Client-Side AI Processing**: Komputasi deteksi wajah diproses di browser peserta menggunakan MediaPipe, sehingga server hanya menerima data telemetri ringan berbentuk stempel waktu (*timestamp*) dan jenis pelanggaran tanpa membebani GPU server.
* **Grounding Live Chatbot AI**: Asisten virtual terintegrasi langsung dengan database MySQL sistem untuk memfasilitasi audit cepat terkait ketersediaan paket ujian, bank soal, dan analisis nilai siswa.

---

## 2. VISI PRODUK & SASARAN STRATEGIS

### 2.1 Visi Produk (*Product Vision*)
> *"Menjadi platform evaluasi pendidikan daring yang paling andal, transparan, dan berintegritas tinggi dengan menggabungkan pengawasan ganda cerdas dan asisten operasional terotomatisasi, tanpa mengorbankan performa sistem dan privasi data peserta."*

### 2.2 Nilai Guna Utama (*Key Value Propositions*)
1. **Integritas Ujian 360 Derajat**: Menghilangkan 95% *blind spots* fisik pada ujian jarak jauh tanpa perlu pengawas manusia hadir di lokasi peserta.
2. **Arsitektur Bersih & Ringan**: Dibangun dengan *Clean MVC* berbasis PHP 8.2 murni dan prinsip SOLID, menghasilkan waktu muat halaman < 1 detik tanpa kelebihan beban *framework* berat.
3. **Keamanan Berlapis (Defense-in-Depth)**: Verifikasi 2FA OTP multi-kanal (Email Gmail & WhatsApp Gateway), proteksi CSRF ketat, enkripsi *bcrypt*, dan *Server-Authoritative Timer*.
4. **Efisiensi Guru & Pengawas**: Analisis otomatis untuk pilihan ganda, rekomendasi skor cerdas berbasis AI untuk soal esai, serta generator bank soal otomatis.

---

## 3. ANALISIS PENGGUNA & STAKEHOLDER (6 AKTOR)

Sistem Smart Exam berinteraksi dengan **6 entitas aktor** (4 aktor manusia di sisi primer dan 2 aktor sistem otomatis di sisi sekunder):

```
+--------------------------------------------------------------------------------------------------+
|                                    AKTOR SISTEM SMART EXAM                                       |
|                                                                                                  |
|   [4 AKTOR MANUSIA (PRIMER)]                                  [2 AKTOR SISTEM (SEKUNDER)]        |
|   1. Super Admin   (Akses Tertinggi, Audit Forensik & AI)     1. AI Proctoring Engine            |
|   2. Administrator (Data Akademik, User, Jadwal Terpusat)        (MediaPipe Face Mesh di Klien)  |
|   3. Guru/Pengawas (Bank Soal, Penilaian & Live Monitor)      2. Sistem Layanan OTP              |
|   4. Peserta Siswa (Pelaksana Ujian Dual-Cam & Auto-Save)        (Gmail SMTP & WhatsApp Gateway) |
+--------------------------------------------------------------------------------------------------+
```

### 3.1 Persona Pengguna Rinci

| Aktor | Peran & Tanggung Jawab Utama | Kebutuhan Utama (*User Needs*) | Titik Masalah (*Pain Points*) |
| :--- | :--- | :--- | :--- |
| **Super Admin** | Pemegang otoritas tertinggi sistem, pengelola audit log, konfigurasi API Key AI, dan pengawasan parameter infrastruktur. | Akses ke seluruh log forensik, pengaturan model Gemini, dan isolasi hak akses role. | Sulit melacak kebocoran data jika tidak ada jejak audit logging terstruktur. |
| **Administrator** | Penanggung jawab administrasi sekolah/kampus, manajemen akun siswa, guru, rombel/kelas, dan penjadwalan ujian terpusat. | Kemudahan impor data massal, manajemen tahun akademik, dan rekapitulasi data. | Entri data manual yang memakan waktu dan risiko akun ganda. |
| **Guru / Pengawas** | Penyusun bank soal, pembuat paket ujian, pengawas ruang ujian secara daring, dan penilai jawaban esai siswa. | Monitoring live peserta, deteksi otomatis kecurangan, dan koreksi esai instan berbantuan AI. | Kewalahan mengawasi puluhan siswa sekaligus melalui webcam kecil konvensional. |
| **Peserta (Siswa)** | Mahasiswa / siswa pelaksana ujian berbasis komputer dengan kewajiban mematuhi tata tertib pengawasan ganda. | Antarmuka ujian yang bersih, bebas gangguan, auto-save jawaban tanpa lag, dan kejelasan sisa waktu. | Takut jawaban hilang jika internet terputus di tengah ujian (*connection loss*). |
| **AI Proctoring Engine** | Layanan komputasi visi komputer (*client-side*) berbasis MediaPipe Face Mesh yang berjalan di peramban siswa. | Akses stream video WebRTC kamera depan dengan latensi rendah untuk estimasi pose wajah. | Variasi pencahayaan ruangan peserta dan sudut kemiringan wajah. |
| **Sistem Layanan OTP** | Layanan pihak ketiga terintegrasi (Gmail SMTP Relay & WhatsApp Fonnte Gateway) untuk transmisi token 6 digit. | Ketersediaan API yang stabil untuk mendistribusikan kode otentikasi dalam hitungan detik. | Potensi nomor/email tidak valid atau kuota pengiriman pihak ketiga habis. |

---

## 4. SPESIFIKASI KEBUTUHAN FUNGSIONAL (FR-01 s.d. FR-15)

Kebutuhan fungsional di bawah disusun dengan metode **User Story**, prioritas **MoSCoW** (*Must have*, *Should have*, *Could have*, *Won't have*), dan **Kriteria Penerimaan (*Acceptance Criteria*)**.

### FR-01: Autentikasi Pengguna & Manajemen Sesi Multi-Role
* **User Story**: *Sebagai Pengguna (Siswa/Guru/Admin), saya ingin masuk ke akun dengan email dan kata sandi yang aman, agar hak akses saya terisolasi sesuai wewenang saya.*
* **Prioritas**: **MUST HAVE**
* **Kriteria Penerimaan**:
  - Sandi diverifikasi menggunakan fungsi `password_verify()` terhadap hash `bcrypt` (cost 10).
  - Percobaan gagal > 5 kali dalam 15 menit memicu blokir *Rate Limiting* otomatis (HTTP 429).
  - Sesi login terisolasi menggunakan token sesi PHP yang di-regenerate saat login berhasil.

### FR-02: Verifikasi Two-Factor Authentication (2FA OTP)
* **User Story**: *Sebagai Pengguna, saya ingin menerima kode OTP 6 digit yang harus saya ketikkan sendiri di layar, agar akun saya tidak dapat disalahgunakan pihak lain.*
* **Prioritas**: **MUST HAVE**
* **Kriteria Penerimaan**:
  - Sistem membuat token 6 digit acak yang berlaku tepat 5 menit di tabel `otp_tokens`.
  - Kode dikirimkan ke Email SMTP dan/atau WhatsApp Gateway secara otomatis.
  - Halaman verifikasi OTP menampilkan kotak isian kosong (tidak terisi otomatis) sehingga pengguna wajib mengetik kode sendiri.
  - Setelah kode diverifikasi benar, status token diubah menjadi `is_used = 1` dan tidak dapat dipakai ulang (*one-time use*).

### FR-03: Kalibrasi & Pratinjau Dual-Camera WebRTC
* **User Story**: *Sebagai Siswa, saya ingin menguji dan melihat preview kamera depan laptop dan kamera sekunder smartphone sebelum ujian dimulai, agar posisi pengawasan saya telah terverifikasi sah.*
* **Prioritas**: **MUST HAVE**
* **Kriteria Penerimaan**:
  - Kamera Primer menangkap stream video lokal via `navigator.mediaDevices.getUserMedia()`.
  - Kamera Sekunder dapat dihubungkan via IP camera (DroidCam/Iriun) atau WebRTC peer stream.
  - Tombol "Mulai Ujian" hanya aktif setelah kedua indikator kamera berstatus *Connected* (Hijau).

### FR-04: Server-Authoritative Timer & Penguncian Otomatis
* **User Story**: *Sebagai Guru/Penguji, saya ingin sisa waktu ujian dihitung mutlak oleh server, agar siswa tidak dapat memanipulasi durasi ujian dengan mengubah jam lokal perangkat mereka.*
* **Prioritas**: **MUST HAVE**
* **Kriteria Penerimaan**:
  - Sisa waktu dihitung berdasarkan selisih waktu server: `remaining_seconds = (started_at + duration_minutes * 60) - current_server_timestamp`.
  - Setiap request ping AJAX mengembalikan sisa detik resmi dari database.
  - Saat `remaining_seconds <= 0`, sesi otomatis dikunci menjadi `submitted` dan mutasi jawaban ditolak.

### FR-05: Penyimpanan Jawaban Otomatis & Idempoten (Auto-Save)
* **User Story**: *Sebagai Siswa, saya ingin setiap jawaban pilihan ganda maupun esai yang saya pilih langsung tersimpan di server tanpa menekan tombol simpan manual, agar tidak hilang saat listrik padam.*
* **Prioritas**: **MUST HAVE**
* **Kriteria Penerimaan**:
  - Pilihan opsi memicu AJAX `POST /exam/save-answer` yang mengeksekusi operasi `UPSERT` di tabel `exam_answers`.
  - Respon diterima dalam waktu < 200 ms dengan indikator visual *Saved* (Centang Hijau).
  - Jika internet terputus, antarmuka menampilkan status *Pending Sync* dan mencoba mengirim ulang saat koneksi kembali pulih.

### FR-06: Deteksi Kecurangan Wajah AI (MediaPipe Face Mesh)
* **User Story**: *Sebagai Pengawas, saya ingin sistem mendeteksi secara otomatis ketika siswa menoleh, keluar dari pandangan kamera, atau ada orang lain di layar, agar pengawasan berlangsung adil.*
* **Prioritas**: **MUST HAVE**
* **Kriteria Penerimaan**:
  - MediaPipe Face Mesh membaca 468 titik landmark wajah pada frame video siswa setiap 3 detik.
  - Menghitung rasio sudut yaw/pitch: jika sudut menoleh > 30 derajat selama > 3 detik berturut-turut, sistem mengirim telemetri `look_away`.
  - Jika tidak ada wajah terdeteksi > 5 detik, sistem mencatat insiden `face_absent`.
  - Setiap insiden dicatat di tabel `ai_violations` dan memunculkan peringatan banner kuning/merah di layar siswa.

### FR-07: Manajemen Bank Soal & Paket Ujian Berimbang
* **User Story**: *Sebagai Guru, saya ingin membuat bank soal kurikulum dan merilis paket ujian dengan jadwal, durasi, dan token acak, agar evaluasi terstruktur rapi.*
* **Prioritas**: **MUST HAVE**
* **Kriteria Penerimaan**:
  - Guru dapat mengelola bank soal (pilihan ganda dengan kunci jawaban dan bobot nilai, serta esai bertingkat).
  - Paket ujian dapat disetel acak urutan soal (*randomize questions*) dan acak urutan opsi jawaban (*randomize options*).
  - Sistem mendukung soft-delete untuk menjaga integritas data relasional historis.

### FR-08: AI Asisten Operasional Live Database (Google Gemini Grounding)
* **User Story**: *Sebagai Admin/Guru, saya ingin bertanya kepada Chatbot AI tentang status jadwal, jumlah bank soal, atau rekap nilai, dan mendapatkan jawaban singkat yang bersumber dari data live database.*
* **Prioritas**: **MUST HAVE**
* **Kriteria Penerimaan**:
  - Chatbot terintegrasi dengan Google Gemini (3.5 Flash-Lite / 3.8 Flash) via REST API.
  - Layanan `AiChatService` menyuntikkan konteks ringkas (*grounding context*) dari kueri SQL live (misal: jumlah bank soal aktif = 10, paket ujian aktif = 10).
  - Respon AI dibatasi singkat, padat, dan akurat (to-the-point) dengan batas maksimal 3 kalimat.
  - Admin dapat mengganti API Key Gemini langsung melalui antarmuka modal pengaturan tanpa membuka berkas `.env`.

### FR-09: Koreksi Esai Cerdas Berbantuan AI (*Semantic Essay Grading*)
* **User Story**: *Sebagai Guru, saya ingin AI membantu menganalisis kesesuaian jawaban esai siswa terhadap rubrik kunci jawaban, agar proses koreksi lebih cepat dan objektif.*
* **Prioritas**: **SHOULD HAVE**
* **Kriteria Penerimaan**:
  - Sistem mengirimkan teks pertanyaan, kunci jawaban guru, dan jawaban siswa ke model Gemini.
  - AI menghasilkan rekomendasi skor (skala 0–100) dan ringkasan umpan balik semantik.
  - Guru tetap memiliki wewenang penuh untuk mengubah skor akhir secara manual (*human-in-the-loop*).

### FR-10: Pemantauan Ujian Real-Time (*Live Monitoring Dashboard*)
* **User Story**: *Sebagai Pengawas, saya ingin melihat daftar seluruh peserta ujian di kelas saya beserta status kamera dan jumlah pelanggaran yang terdeteksi secara real-time.*
* **Prioritas**: **SHOULD HAVE**
* **Kriteria Penerimaan**:
  - Halaman pengawas menampilkan tabel peserta dengan status: *Belum Mulai*, *Sedang Mengerjakan*, *Selesai*, atau *Terkunci*.
  - Indikator kamera menampilkan status hijau/merah dan kartu counter jumlah pelanggaran AI.

### FR-11: Proteksi Kecurangan Sisi Klien (*Browser Lockdown Features*)
* **User Story**: *Sebagai Pengawas, saya ingin peramban siswa dibatasi agar tidak dapat berpindah tab, menyalin teks, atau membuka menu inspeksi kode.*
* **Prioritas**: **SHOULD HAVE**
* **Kriteria Penerimaan**:
  - Perpindahan tab atau *window blur* memicu pencatatan insiden `tab_switch` dan peringatan layar.
  - Klik kanan (*context menu*), shortcut salin-tempel (`Ctrl+C`, `Ctrl+V`), dan print screen diblokir dengan JavaScript event listener.

### FR-12: Generator Butir Soal AI Otomatis
* **User Story**: *Sebagai Guru, saya ingin meminta AI membuatkan 5–10 butir soal pilihan ganda baru berdasarkan materi pelajaran dan tingkat Taksonomi Bloom tertentu.*
* **Prioritas**: **SHOULD HAVE**
* **Kriteria Penerimaan**:
  - Form generator menerima input topik materi, tingkat kesulitan (Mudah/Sedang/Sulit), dan jumlah soal.
  - Gemini menghasilkan format JSON terstruktur yang langsung dapat ditinjau dan disimpan ke bank soal.

### FR-13: Ekspor Rekapitulasi Nilai & Berita Acara (PDF & Excel)
* **User Story**: *Sebagai Administrator, saya ingin mengunduh daftar nilai akhir dan berita acara ujian dalam format PDF atau Excel, untuk keperluan arsip akademik resmi.*
* **Prioritas**: **COULD HAVE**
* **Kriteria Penerimaan**:
  - Ekspor PDF menggunakan Dompdf menghasilkan lembar rekap nilai rapi dengan kop institusi STMIK IKMI Cirebon.
  - Ekspor Excel (.xlsx) menghasilkan tabel terperinci berisi NIM, Nama, Kelas, Skor PG, Skor Esai, dan Total Nilai.

### FR-14: Audit Logging Forensik Terpusat
* **User Story**: *Sebagai Super Admin, saya ingin seluruh tindakan modifikasi data penting tercatat dalam riwayat audit log, agar setiap perubahan data dapat dipertanggungjawabkan.*
* **Prioritas**: **COULD HAVE**
* **Kriteria Penerimaan**:
  - Tabel `audit_logs` mencatat `user_id`, `ip_address`, `action`, `endpoint`, dan `user_agent`.
  - Log audit bersifat *append-only* (tidak dapat diubah atau dihapus oleh siapapun).

### FR-15: Pengaturan Preferensi Tema & Antarmuka Responsif
* **User Story**: *Sebagai Pengguna, saya ingin tampilan antarmuka nyaman dibaca baik pada mode terang (*Light Mode*) maupun mode gelap (*Dark Mode*).*
* **Prioritas**: **COULD HAVE**
* **Kriteria Penerimaan**:
  - Preferensi tema tersimpan di `localStorage` dan tabel `user_preferences`.
  - Tampilan responsif pada resolusi desktop (1920x1080), laptop (1366x768), dan tablet (768x1024).

---

## 5. SPESIFIKASI KEBUTUHAN NON-FUNGSIONAL (NFR-01 s.d. NFR-08)

| ID NFR | Kategori | Parameter Kebutuhan | Spesifikasi Teknis & Standar Kepatuhan |
| :--- | :--- | :--- | :--- |
| **NFR-01** | **Performa** | Waktu Respon API (*Response Time*) | Rata-rata respon penyimpanan jawaban AJAX < 300 milidetik pada beban 100 request/detik. |
| **NFR-02** | **Keamanan** | Kriptografi Sandi Pengguna | Hash kata sandi menggunakan fungsi bawaan `PASSWORD_BCRYPT` dengan cost factor 10. |
| **NFR-03** | **Keamanan** | Proteksi CSRF & Sanitasi XSS | Seluruh form mutasi wajib menyertakan token CSRF 32-karakter acak; seluruh output HTML disanitasi via `htmlspecialchars()`. |
| **NFR-04** | **Keamanan** | Pencegahan SQL Injection | 100% kueri manipulasi data wajib menggunakan PDO Prepared Statements dengan parameter binding terpisah. |
| **NFR-05** | **Keandalan** | Toleransi Gangguan Jaringan (*Fault Tolerance*) | Jika koneksi internet siswa terputus hingga 10 menit, sesi tetap valid dan jawaban yang belum tersinkronisasi disimpan di antrean memori lokal hingga koneksi pulih. |
| **NFR-06** | **Ketersediaan** | Waktu Aktif Sistem (*System Uptime*) | Target ketersediaan operasional sistem sebesar 99.5% selama periode pekan ujian semester. |
| **NFR-07** | **Usabilitas** | Aksesibilitas Desain Antarmuka | Mematuhi standar WCAG 2.1 level AA dengan rasio kontras teks minimal 4.5:1 terhadap latar belakang. |
| **NFR-08** | **Efisiensi** | Konsumsi Bandwidth & Memori | Bandwidth telemetri AI di peramban siswa < 50 KB/menit karena pemrosesan frame dilakukan di sisi klien. |

---

## 6. BATASAN SISTEM, ASUMSI, & DEPENDENSI

### 6.1 Batasan Sistem (*System Constraints*)
1. **Perangkat Keras Minimal**: Perangkat peserta wajib memiliki minimal 1 kamera aktif (webcam internal/eksternal) dan peramban dengan dukungan WebGL/WebRTC. Kamera sekunder memerlukan smartphone Android/iOS dalam jaringan yang sama.
2. **Konektivitas Internet**: Membutuhkan kecepatan unggah (*upload speed*) minimal 1 Mbps untuk transmisi telemetri dan sinkronisasi sesi.
3. **Framework-Free Engine**: Arsitektur Clean MVC dibangun menggunakan pustaka inti mandiri tanpa framework Laravel/Symfony demi kecepatan dan efisiensi memori.

### 6.2 Asumsi (*Assumptions*)
1. Siswa telah memiliki akun terdaftar dengan alamat email Gmail dan/atau nomor WhatsApp yang aktif untuk menerima OTP.
2. Guru telah menyusun bank soal dan mengaktifkan jadwal ujian sebelum sesi dimulai.
3. Jam server (*server clock*) menggunakan sinkronisasi NTP resmi zona waktu Asia/Jakarta (WIB).

### 6.3 Dependensi Eksternal (*External Dependencies*)
1. **Google Gemini Generative AI API**: Memerlukan kuota API aktif dan koneksi internet keluar (port 443 HTTPS).
2. **MediaPipe CDN Library**: Memerlukan pengunduhan berkas skrip `camera_utils.js` dan `face_mesh.js` saat halaman ujian pertama kali dimuat.
3. **Fonnte WhatsApp Gateway & Gmail SMTP**: Bergantung pada ketersediaan layanan pihak ketiga untuk pengiriman pesan OTP.

---

## 7. SPESIFIKASI ANTARMUKA & PROTOKOL KOMUNIKASI

```
+--------------------------------------------------------------------------------------------------+
|                              ARSITEKTUR PROTOKOL KOMUNIKASI                                      |
|                                                                                                  |
|   [BROWSER SISWA]                                                                                |
|   • WebRTC MediaStream (Kamera Laptop & HP)                                                      |
|   • MediaPipe JS (Face Mesh Vision)                                                              |
|          │                                                                                       |
|          ▼ HTTP REST (JSON / AJAX Fetch)                                                         |
|   [FRONT CONTROLLER (index.php)] ──> [SECURITY FILTERS (Auth, Role, CSRF)]                       |
|          │                                                                                       |
|          ▼ Clean MVC Routing                                                                     |
|   [CONTROLLERS (Exam, Auth, AiChat)] ──> [SERVICE LAYER (Business Logic)]                        |
|                                                  │                                               |
|                      ┌───────────────────────────┴───────────────────────────┐                   |
|                      ▼ PDO Prepared Stmts                                    ▼ HTTPS REST        |
|             [MySQL 8.0 DATABASE]                                      [GEMINI AI API]            |
|             (50+ Relational Tables)                                   (Live Chatbot Engine)      |
+--------------------------------------------------------------------------------------------------+
```

* **Format Data**: Pertukaran data asinkron menggunakan format standar **JSON** dengan header `Content-Type: application/json`.
* **Protokol Kamera**: Menggunakan WebRTC `MediaStream` API dan Canvas 2D Rendering Context untuk penggambaran mesh wajah.
* **Format Respons Standar API**:
  ```json
  {
    "success": true,
    "status": 200,
    "message": "Jawaban berhasil disinkronkan secara otomatis.",
    "data": {
      "question_id": 142,
      "saved_at": "2026-11-12 18:30:15",
      "remaining_seconds": 3540
    }
  }
  ```

---

## 8. PERLINDUNGAN PRIVASI & KEAMANAN DATA (UU PDP)

Sistem Smart Exam dirancang dengan mematuhi prinsip **Privasi Berdasarkan Desain (*Privacy-by-Design*)** dan Undang-Undang Perlindungan Data Pribadi (UU No. 27 Tahun 2022):
1. **Tidak Ada Penyimpanan Rekaman Video Utuh di Server**: Sistem tidak mengunggah atau merekam video utuh wajah siswa ke database server. Seluruh deteksi wajah diproses lokal di memori RAM peramban (*client-side*).
2. **Hanya Metadata Pelanggaran yang Disimpan**: Data yang dikirimkan ke server murni berupa metadata telemetri teks (misal: waktu pelanggaran, jenis `look_away`, durasi 4 detik, dan persentase keyakinan model).
3. **Penghapusan Otomatis Log OTP**: Kode token OTP pada tabel `otp_tokens` otomatis kedaluwarsa dalam 5 menit dan dibersihkan secara berkala melalui *maintenance cron job*.
4. **Isolasi API Key**: API Key Google Gemini disimpan pada berkas rahasia `.env` dan tidak pernah disuntikkan ke JavaScript peramban publik.

---

## 9. MATRIKS KETERTELUSURAN KEBUTUHAN (RTM)

Matriks berikut memetakan setiap kebutuhan fungsional ke dalam komponen kode implementasi, tabel basis data, dan skrip pengujian:

| ID Kebutuhan | Use Case UML | Komponen Controller & Service | Tabel Database Terkait | Berkas Automated Test Suite |
| :--- | :--- | :--- | :--- | :--- |
| **FR-01** (Login & Auth) | UC-01 | `AuthController.php`, `UserModel.php` | `users`, `roles`, `user_sessions` | `test_auth_flow.php` |
| **FR-02** (Verifikasi OTP) | UC-02 | `AuthController.php`, `OtpTokenModel.php` | `otp_tokens`, `notification_logs` | `test_otp_verification.php` |
| **FR-03** (Dual Camera) | UC-08 | `ExamProctoringController.php`, `exam-dual-camera.js` | `proctoring_sessions` | `test_proctoring_rules.php` |
| **FR-04** (Server Timer) | UC-10 | `ExamSessionService.php`, `ExamSessionModel.php` | `exam_sessions` | `test_timer_authoritative.php` |
| **FR-05** (Auto-Save) | UC-10 | `ExamController.php`, `ExamAnswerModel.php` | `exam_answers` | `test_answer_submission.php` |
| **FR-06** (Deteksi Wajah AI)| UC-12 | `AiProctoringService.php`, `ai-face-mesh.js` | `ai_violations`, `ai_warnings` | `test_violation_telemetry.php` |
| **FR-07** (Bank Soal & Ujian)| UC-05 | `QuestionBankController.php`, `ExamController.php` | `question_banks`, `questions`, `exams` | `test_question_bank_crud.php` |
| **FR-08** (Chatbot AI Live) | UC-06 | `AiChatService.php`, `AiChatController.php` | `ai_chat_sessions`, `ai_chat_messages` | `test_ai_chat_reports.php` |
| **FR-09** (Koreksi Esai AI) | UC-09 | `GradingService.php`, `AiGradingProvider.php` | `exam_answers`, `exam_results` | `test_grading_service.php` |
| **FR-10** (Live Monitoring)| UC-07 | `AdminMonitoringController.php` | `exam_sessions`, `proctoring_sessions`| `test_monitoring_stream.php` |
| **FR-11** (Browser Lockdown)| UC-10 | `exam-lockdown.js`, `ExamController.php` | `ai_violations` | `test_security_regression_suite.php`|
| **FR-12** (Generator Soal) | UC-05 | `AiQuestionGenerationService.php` | `ai_question_generations`, `questions`| `test_question_generator.php` |
| **FR-13** (Ekspor PDF/Excel)| UC-11 | `ReportController.php`, `Dompdf/Spreadsheet` | `exam_results`, `exam_sessions` | `test_report_export.php` |
| **FR-14** (Audit Logging) | UC-04 | `AuditLogModel.php`, `BaseController.php` | `audit_logs` | `test_audit_logging.php` |
| **FR-15** (User Preference)| UC-03 | `UserController.php`, `UserPreferenceModel.php`| `user_preferences` | `test_user_preferences.php` |

---

## 10. KRITERIA PENYELESAIAN & KELAYAKAN RILIS (DEFINITION OF DONE)

Sesuai standar rubrik OBE Tugas Proyek Akhir Rekayasa Perangkat Lunak STMIK IKMI Cirebon, produk dinyatakan memenuhi kualifikasi rilis penuh jika memenuhi syarat berikut:
1. ✅ **Kelulusan Pengujian Otomatis 100%**: 8 test suite dan 19 asersi keamanan pada `test_security_regression_suite.php` berstatus *PASSED*.
2. ✅ **Badge CI/CD Hijau**: Alur kerja `.github/workflows/ci.yml` berhasil lolos kompilasi, migrasi, dan pengujian pada GitHub Actions.
3. ✅ **Integritas Database Terverifikasi**: Tepat memiliki 10 bank soal kurikulum dan 10 paket ujian aktif di database `ujian_dual_kamera`.
4. ✅ **Keamanan OTP Manual**: Kode 6-digit OTP terkirim ke perangkat dan wajib diketik manual oleh peserta tanpa otomatisasi bypass.
5. ✅ **Dokumentasi Lengkap**: PRD/SRS standar IEEE 830, SDD dengan 4 Diagram UML V2.0, Rencana Pengujian, dan Skrip Video tersedia di repositori publik.

---

*Disahkan untuk pemenuhan Tahap 1 Penilaian OBE Mata Kuliah Rekayasa Perangkat Lunak (TIKB2503021) — STMIK IKMI Cirebon.*  
*Penyusun: **Salsa Nabila** (NIM: **41257158**) — Kelompok **Tiba Tiba Talun***
