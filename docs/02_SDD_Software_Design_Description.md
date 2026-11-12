# DOKUMEN PERANCANGAN PERANGKAT LUNAK (SDD)
## Smart Exam — Sistem Ujian Online Berbasis AI & Dual Camera Proctoring

> **Mata Kuliah**: Rekayasa Perangkat Lunak (TIKB2503021) — 4 SKS  
> **Dosen Pengampu**: Yudhistira Arie Wijaya, M.Kom., CDS.  
> **Institusi**: S1 Teknik Informatika — STMIK IKMI Cirebon  
> **Tahun Akademik**: 2026/2027 (Semester 3)  
> **Penyusun**: **Salsa Nabila** (NIM: **41257158**)  
> **Kelompok**: **Tiba Tiba Talun**  
> **Tema Topik**: **E-Learning & Smart Education AI**

---

## 1. ARSITEKTUR PERANGKAT LUNAK

Sistem Smart Exam dirancang dengan arsitektur **3-Tier Clean MVC (Model-View-Controller)** yang dilengkapi lapisan **Service Layer** dan **Middleware Filters** untuk mematuhi prinsip *Separation of Concerns (SoC)* dan *Single Responsibility Principle (SRP)*.

```mermaid
graph TD
    subgraph ClientLayer["1. Client Layer (Presentation)"]
        UI_Browser["Web Browser (Chrome / Edge / Firefox)"]
        WebRTC_Cam["MediaStream WebRTC (Webcam Laptop + HP Sekunder)"]
        MediaPipe_JS["MediaPipe Face Mesh (Client-Side AI Engine)"]
    end

    subgraph SecurityLayer["2. Security & Middleware Layer"]
        Router["Front Controller & Router (index.php)"]
        AuthFilter["AuthFilter (Sesi & Identitas Pengguna)"]
        RoleFilter["RoleFilter (Role-Based Access Control / RBAC)"]
        CsrfFilter["CsrfFilter & RateLimiter (Token Valid & Anti-Brute Force)"]
    end

    subgraph ApplicationLayer["3. Application & Service Layer (Clean MVC)"]
        Controllers["Controllers (Auth, Exam, Admin, Proctoring, AiChat)"]
        ServiceLayer["Service Layer (AiChatService, ExamSessionService, ProctoringService)"]
        Models["Models (Active Record / PDO Prepared Statements)"]
    end

    subgraph ExternalAndData["4. Data & External Services Layer"]
        MySQL_DB[("MySQL 8.0 Database (InnodDB / Relational)")]
        Gemini_AI["Google Gemini Generative AI (Live Grounding)"]
        Email_WA["Notification Gateway (Gmail SMTP & WhatsApp API)"]
    end

    UI_Browser -->|HTTP GET / POST| Router
    WebRTC_Cam -->|Frame Telemetri| Controllers
    MediaPipe_JS -->|Log Pelanggaran| Controllers

    Router --> AuthFilter --> RoleFilter --> CsrfFilter --> Controllers
    Controllers --> ServiceLayer
    ServiceLayer --> Models
    Models --> MySQL_DB
    ServiceLayer --> Gemini_AI
    ServiceLayer --> Email_WA
```

---

## 2. DIAGRAM UML (UNIFIED MODELING LANGUAGE)

### 2.1 Use Case Diagram

```mermaid
flowchart LR
    subgraph Actors
        Siswa["Siswa / Peserta Ujian"]
        Guru["Guru / Pembuat Soal"]
        Admin["Super Admin / Admin Sistem"]
    end

    subgraph "Sistem Smart Exam"
        UC01["UC-01: Autentikasi & Verifikasi OTP"]
        UC02["UC-02: Kalibrasi Dual Kamera WebRTC"]
        UC03["UC-03: Mengerjakan Ujian & Auto-Save"]
        UC04["UC-04: Deteksi Kecurangan AI Face Mesh"]
        UC05["UC-05: Kelola Bank Soal & Paket Ujian"]
        UC06["UC-06: Monitoring Live Peserta Ujian"]
        UC07["UC-07: Koreksi Esai Otomatis AI"]
        UC08["UC-08: Tanya AI Chatbot Operasional"]
        UC09["UC-09: Kelola Pengguna & Pengaturan Sistem"]
        UC10["UC-10: Ekspor Rekap Nilai PDF / Excel"]
    end

    Siswa --> UC01
    Siswa --> UC02
    Siswa --> UC03
    Siswa --> UC04

    Guru --> UC01
    Guru --> UC05
    Guru --> UC06
    Guru --> UC07
    Guru --> UC08
    Guru --> UC10

    Admin --> UC01
    Admin --> UC05
    Admin --> UC06
    Admin --> UC08
    Admin --> UC09
    Admin --> UC10
```

#### Skenario Use Case Utama (UC-03: Mengerjakan Ujian dengan AI Proctoring)
- **Aktor Utama**: Siswa
- **Pra-kondisi**: Siswa telah terotentikasi, memasukkan kode OTP, dan berhasil melakukan kalibrasi kedua kamera.
- **Alur Utama (Main Flow)**:
  1. Siswa memilih jadwal ujian yang sedang aktif dan memasukkan token ujian.
  2. Sistem memverifikasi token dan menginisialisasi sesi ujian di tabel `exam_sessions`.
  3. Sistem memuat butir soal dengan timer yang dikendalikan oleh server.
  4. Kamera utama dan sekunder mulai merekam dan mentransmisikan telemetri proctoring.
  5. Setiap kali siswa memilih jawaban, sistem mengirim request asinkron untuk menyimpan jawaban ke `exam_answers`.
  6. Sistem mendeteksi adanya pelanggaran (wajah menoleh, tab tertutup) dan mencatat insiden ke tabel `ai_violations`.
  7. Siswa menekan tombol "Selesai Ujian" dan mengonfirmasi pengumpulan jawaban.
  8. Sistem mengunci sesi, menghitung skor otomatis untuk pilihan ganda, dan memperbarui status sesi menjadi `submitted`.
- **Pasca-kondisi**: Jawaban tersimpan permanen, sesi terkunci, nilai awal terbentuk.

---

### 2.2 Activity Diagram (Alur Pelaksanaan Ujian & Pengawasan AI)

```mermaid
flowchart TD
    Start([Mulai]) --> Login[Input Email & Password]
    Login --> VerifyCred{Kredensial Valid?}
    VerifyCred -- Tidak --> ShowError[Tampilkan Error Login] --> Login
    VerifyCred -- Ya --> GenerateOTP[Generate 6-Digit OTP & Kirim via Email/WA]
    GenerateOTP --> InputOTP[Input Kode OTP]
    InputOTP --> VerifyOTP{OTP Valid & Belum Expired?}
    VerifyOTP -- Tidak --> ErrorOTP[Pesan OTP Salah / Kadaluarsa] --> InputOTP
    VerifyOTP -- Ya --> Dashboard[Buka Dashboard Siswa]

    Dashboard --> SelectExam[Pilih Ujian Aktif & Input Token]
    SelectExam --> VerifyToken{Token Valid?}
    VerifyToken -- Tidak --> TokenError[Peringatan Token Salah] --> SelectExam
    VerifyToken -- Ya --> CamSetup[Inisialisasi Kamera Utama & Kamera Sekunder]

    CamSetup --> CheckCam{Kedua Kamera Terdeteksi?}
    CheckCam -- Tidak --> PromptCam[Panduan Menghubungkan Kamera] --> CamSetup
    CheckCam -- Ya --> StartSession[Mulai Sesi Ujian di Server]

    StartSession --> DisplayQuestions[Tampilkan Soal & Jalankan Server Timer]
    DisplayQuestions --> ExamLoop{Ujian Selesai atau Waktu Habis?}

    ExamLoop -- Berlangsung --> DetectFace[MediaPipe Deteksi Wajah & Posisi Mata]
    DetectFace --> IsViolation{Ada Pelanggaran?}
    IsViolation -- Ya --> LogViolation[Catat Pelanggaran di Database & Tampilkan Peringatan]
    IsViolation -- Tidak --> AnswerQuestion[Siswa Menjawab Soal]
    LogViolation --> AnswerQuestion
    AnswerQuestion --> AutoSave[Simpan Jawaban Otomatis via AJAX]
    AutoSave --> ExamLoop

    ExamLoop -- Ya --> SubmitExam[Kunci Sesi Ujian]
    SubmitExam --> AutoGrade[Hitung Nilai Pilihan Ganda & Kirim Jawaban Esai ke AI]
    AutoGrade --> ShowSummary[Tampilkan Rangkuman Nilai Selesai]
    ShowSummary --> End([Selesai])
```

---

### 2.3 Class Diagram (Arsitektur Model, Controller, Service)

```mermaid
classDiagram
    class BaseModel {
        #PDO db
        #string table
        #string primaryKey
        +find(id)
        +where(conditions)
        +create(data)
        +update(id, data)
        +delete(id)
    }

    class UserModel {
        +int id
        +string name
        +string email
        +string password
        +string role
        +verifyPassword(plain)
        +findByEmail(email)
    }

    class ExamModel {
        +int id
        +string title
        +int duration_minutes
        +datetime start_time
        +datetime end_time
        +string status
        +getActiveExams()
    }

    class ExamSessionModel {
        +int id
        +int exam_id
        +int user_id
        +datetime started_at
        +datetime submitted_at
        +string status
        +getRemainingSeconds()
    }

    class ExamAnswerModel {
        +int id
        +int session_id
        +int question_id
        +text answer_text
        +float score
        +saveAnswer(sessionId, questionId, answer)
    }

    class AiChatService {
        -string apiKey
        -string model
        +processUserQuery(query, userRole, userId) string
        +getOperationalContext() array
        +formatPromptWithGrounding(prompt, context) string
    }

    class ExamSessionService {
        +startSession(userId, examId, token) array
        +validateActiveSession(sessionId) bool
        +submitSession(sessionId) bool
        +calculateScore(sessionId) float
    }

    class AuthController {
        +login(request) response
        +verifyOtp(request) response
        +logout() response
    }

    class ExamController {
        +index(request) response
        +start(examId) response
        +saveAnswer(request) response
        +finish(sessionId) response
    }

    class AiChatController {
        +sendMessage(request) response
        +getHistory(sessionId) response
    }

    BaseModel <|-- UserModel
    BaseModel <|-- ExamModel
    BaseModel <|-- ExamSessionModel
    BaseModel <|-- ExamAnswerModel

    AuthController --> UserModel
    ExamController --> ExamSessionService
    ExamSessionService --> ExamSessionModel
    ExamSessionService --> ExamAnswerModel
    ExamSessionService --> ExamModel
    AiChatController --> AiChatService
    AiChatService --> ExamModel
    AiChatService --> UserModel
```

---

### 2.4 Sequence Diagram

#### Sequence Diagram 1: Autentikasi Pengguna & Two-Factor OTP
```mermaid
sequenceDiagram
    autonumber
    actor Pengguna as Pengguna (Siswa/Guru/Admin)
    participant AuthCtrl as AuthController
    participant UserMdl as UserModel
    participant OtpMdl as OtpTokenModel
    participant NotifSvc as NotificationClient (Email/WA)
    participant Session as UserSession

    Pengguna->>AuthCtrl: POST /auth/login (email, password)
    AuthCtrl->>UserMdl: findByEmail(email)
    UserMdl-->>AuthCtrl: Data Pengguna & Hash Sandi
    AuthCtrl->>AuthCtrl: verifyPassword(password, hash)
    
    alt Password Salah
        AuthCtrl-->>Pengguna: Tampilkan pesan "Kredensial tidak valid"
    else Password Benar
        AuthCtrl->>OtpMdl: generateOtpToken(userId, validMinutes=5)
        OtpMdl-->>AuthCtrl: Kode 6 Digit (contoh: 440888)
        AuthCtrl->>NotifSvc: Kirim OTP ke Email / WhatsApp
        NotifSvc-->>AuthCtrl: Berhasil terkirim
        AuthCtrl-->>Pengguna: Redirect ke halaman verifikasi OTP
    end

    Pengguna->>AuthCtrl: POST /auth/verify-otp (kode 6 digit)
    AuthCtrl->>OtpMdl: validateToken(userId, kode)
    alt OTP Valid & Aktif
        OtpMdl->>OtpMdl: markAsUsed(tokenId)
        AuthCtrl->>Session: createAuthenticatedSession(userId, role)
        AuthCtrl-->>Pengguna: Redirect ke Dashboard Sesuai Role
    else OTP Salah atau Kadaluarsa
        AuthCtrl-->>Pengguna: Tampilkan error "Kode OTP tidak valid"
    end
```

#### Sequence Diagram 2: Pelaksanaan Ujian & AI Proctoring
```mermaid
sequenceDiagram
    autonumber
    actor Siswa as Siswa
    participant Browser as Browser Client (JS + MediaPipe)
    participant ExamCtrl as ExamController
    participant ExamSvc as ExamSessionService
    participant AnswerMdl as ExamAnswerModel
    participant ViolationMdl as AiViolationModel

    Siswa->>Browser: Klik "Mulai Ujian"
    Browser->>ExamCtrl: POST /exam/start (exam_id, token)
    ExamCtrl->>ExamSvc: startSession(userId, examId)
    ExamSvc-->>Browser: Sesi Aktif + Sisa Waktu Detik (Server-Authoritative)
    
    par Siklus Pengawasan AI
        loop Setiap 3 Detik
            Browser->>Browser: MediaPipe lacak landmark wajah & arah mata
            opt Terdeteksi Wajah Berpaling / Hilang
                Browser->>ExamCtrl: POST /api/proctoring/violation (jenis pelanggaran, timestamp)
                ExamCtrl->>ViolationMdl: recordViolation(sessionId, type, details)
                Browser->>Siswa: Tampilkan banner peringatan di layar
            end
        end
    and Siklus Pengisian Jawaban
        loop Setiap Siswa Memilih Jawaban
            Siswa->>Browser: Pilih opsi jawaban
            Browser->>ExamCtrl: POST /exam/save-answer (question_id, answer_text)
            ExamCtrl->>AnswerMdl: saveAnswer(sessionId, questionId, answer)
            AnswerMdl-->>Browser: Status OK (Auto-Saved)
        end
    end

    Siswa->>Browser: Klik "Kumpulkan Ujian"
    Browser->>ExamCtrl: POST /exam/finish (session_id)
    ExamCtrl->>ExamSvc: submitSession(sessionId)
    ExamSvc->>ExamSvc: Lock session, hitung total nilai otomatis
    ExamCtrl-->>Browser: Redirect ke halaman hasil ujian
```

---

## 3. PERANCANGAN BASIS DATA (ENTITY RELATIONSHIP DIAGRAM)

```mermaid
erDiagram
    USERS ||--o{ EXAM_SESSIONS : "memiliki"
    USERS ||--o{ OTP_TOKENS : "menerima"
    USERS ||--o{ AI_CHAT_SESSIONS : "memulai"
    ROLES ||--o{ USERS : "diberikan kepada"
    
    EXAMS ||--o{ EXAM_SESSIONS : "dijadwalkan dalam"
    EXAMS ||--|{ EXAM_QUESTIONS : "berisi"
    QUESTION_BANKS ||--|{ QUESTIONS : "menyimpan"
    QUESTIONS ||--|{ EXAM_QUESTIONS : "dimasukkan ke"
    QUESTIONS ||--o{ QUESTION_OPTIONS : "memiliki pilihan"
    
    EXAM_SESSIONS ||--o{ EXAM_ANSWERS : "menghasilkan"
    EXAM_SESSIONS ||--o{ PROCTORING_SESSIONS : "dipantau via"
    PROCTORING_SESSIONS ||--o{ AI_VIOLATIONS : "mencatat"
    
    AI_CHAT_SESSIONS ||--|{ AI_CHAT_MESSAGES : "menyimpan obrolan"

    USERS {
        int id PK
        string name
        string email UK
        string password
        int role_id FK
        datetime created_at
    }

    EXAMS {
        int id PK
        string title
        int duration_minutes
        datetime start_time
        datetime end_time
        string token
        string status
    }

    QUESTION_BANKS {
        int id PK
        string code
        string title
        int total_questions
    }

    QUESTIONS {
        int id PK
        int bank_id FK
        text question_text
        string question_type
        float weight
    }

    EXAM_SESSIONS {
        int id PK
        int exam_id FK
        int user_id FK
        datetime started_at
        datetime submitted_at
        float total_score
        string status
    }

    EXAM_ANSWERS {
        int id PK
        int session_id FK
        int question_id FK
        text answer_text
        float score
        datetime saved_at
    }

    AI_VIOLATIONS {
        int id PK
        int session_id FK
        string violation_type
        text details
        datetime detected_at
    }

    AI_CHAT_MESSAGES {
        int id PK
        int session_id FK
        string sender_role
        text message_text
        datetime sent_at
    }
```

---

*Disahkan untuk pemenuhan Tahap 2 Penilaian OBE Mata Kuliah Rekayasa Perangkat Lunak STMIK IKMI Cirebon.*
