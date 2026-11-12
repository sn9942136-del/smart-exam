<?php

declare(strict_types=1);

use App\Controllers\AcademicYearController;
use App\Controllers\AdminDashboardController;
use App\Controllers\AiChatController;
use App\Controllers\AiChatReportController;
use App\Controllers\AiConfigurationController;
use App\Controllers\AiProctoringController;
use App\Controllers\AiQuestionController;
use App\Controllers\AuthController;
use App\Controllers\CameraPreCheckController;
use App\Controllers\ClassController;
use App\Controllers\DashboardController;
use App\Controllers\ErrorController;
use App\Controllers\ExamAnswerController;
use App\Controllers\ExamController;
use App\Controllers\ExamMonitoringController;
use App\Controllers\ExamSessionController;
use App\Controllers\GradingController;
use App\Controllers\GuruDashboardController;
use App\Controllers\HomeController;
use App\Controllers\NotificationController;
use App\Controllers\NotificationLogController;
use App\Controllers\PasswordController;
use App\Controllers\ProfileController;
use App\Controllers\QuestionBankController;
use App\Controllers\QuestionController;
use App\Controllers\ReportController;
use App\Controllers\RoleController;
use App\Controllers\SemesterController;
use App\Controllers\StudentDashboardController;
use App\Controllers\StudentExamController;
use App\Controllers\StudentResultController;
use App\Controllers\SubjectController;
use App\Controllers\SystemConfigurationController;
use App\Controllers\UserController;
use App\Libraries\Router;


/**
 * ==============================================================================
 * APPLICATION ROUTING TABLE
 * ==============================================================================
 */

// 1. Landing Page & Health Check
Router::get('/', [HomeController::class, 'index']);
Router::get('/health', [HomeController::class, 'health']);
Router::get('/unauthorized', [ErrorController::class, 'unauthorized']);

// 2. Authentication (Login & Logout)
Router::get('/login', [AuthController::class, 'showLogin']);
Router::post('/login', [AuthController::class, 'login'], ['filter' => ['csrf', 'rate_limit:5,60']]);
Router::get('/logout', [AuthController::class, 'logout']);
Router::post('/logout', [AuthController::class, 'logout']);

// 3. Password Recovery & 6-Digit OTP Verification
Router::get('/forgot-password', [PasswordController::class, 'showForgotPassword']);
Router::post('/forgot-password', [PasswordController::class, 'sendOtp'], ['filter' => ['csrf', 'rate_limit:5,60']]);
Router::get('/verify-otp', [PasswordController::class, 'showVerifyOtp']);
Router::post('/verify-otp', [PasswordController::class, 'verifyOtp'], ['filter' => ['csrf', 'rate_limit:5,60']]);
Router::get('/reset-password', [PasswordController::class, 'showResetPassword']);
Router::post('/reset-password', [PasswordController::class, 'resetPassword'], ['filter' => ['csrf', 'rate_limit:5,60']]);

// 4. Modul Admin (Role Protected: super_admin & admin)
Router::group(['prefix' => 'admin', 'filter' => ['auth', 'role:super_admin,admin', 'csrf']], function () {
    // 4.1 Dashboard Admin
    Router::get('/dashboard', [AdminDashboardController::class, 'index']);

    // 4.2 CRUD User & Password Reset Manual
    Router::get('/users', [UserController::class, 'index']);
    Router::get('/users/create', [UserController::class, 'create']);
    Router::post('/users/store', [UserController::class, 'store'], ['filter' => 'csrf']);
    Router::get('/users/edit/{id}', [UserController::class, 'edit']);
    Router::post('/users/update/{id}', [UserController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/users/delete/{id}', [UserController::class, 'delete'], ['filter' => 'csrf']);
    Router::get('/users/reset-password/{id}', [UserController::class, 'showResetPassword']);
    Router::post('/users/reset-password/{id}', [UserController::class, 'resetPassword'], ['filter' => 'csrf']);
    Router::post('/users/toggle-status/{id}', [UserController::class, 'toggleStatus'], ['filter' => 'csrf']);

    // 4.3 CRUD Role & Permissions
    Router::get('/roles', [RoleController::class, 'index']);
    Router::get('/roles/create', [RoleController::class, 'create']);
    Router::post('/roles/store', [RoleController::class, 'store'], ['filter' => 'csrf']);
    Router::get('/roles/edit/{id}', [RoleController::class, 'edit']);
    Router::post('/roles/update/{id}', [RoleController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/roles/delete/{id}', [RoleController::class, 'delete'], ['filter' => 'csrf']);

    // 4.4 CRUD Tahun Ajaran
    Router::get('/academic-years', [AcademicYearController::class, 'index']);
    Router::get('/academic-years/create', [AcademicYearController::class, 'create']);
    Router::post('/academic-years/store', [AcademicYearController::class, 'store'], ['filter' => 'csrf']);
    Router::get('/academic-years/edit/{id}', [AcademicYearController::class, 'edit']);
    Router::post('/academic-years/update/{id}', [AcademicYearController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/academic-years/delete/{id}', [AcademicYearController::class, 'delete'], ['filter' => 'csrf']);
    Router::post('/academic-years/set-active/{id}', [AcademicYearController::class, 'setActive'], ['filter' => 'csrf']);

    // 4.5 CRUD Semester
    Router::get('/semesters', [SemesterController::class, 'index']);
    Router::get('/semesters/create', [SemesterController::class, 'create']);
    Router::post('/semesters/store', [SemesterController::class, 'store'], ['filter' => 'csrf']);
    Router::get('/semesters/edit/{id}', [SemesterController::class, 'edit']);
    Router::post('/semesters/update/{id}', [SemesterController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/semesters/delete/{id}', [SemesterController::class, 'delete'], ['filter' => 'csrf']);
    Router::post('/semesters/set-active/{id}', [SemesterController::class, 'setActive'], ['filter' => 'csrf']);

    // 4.6 CRUD Kelas & Rombel
    Router::get('/classes', [ClassController::class, 'index']);
    Router::get('/classes/create', [ClassController::class, 'create']);
    Router::post('/classes/store', [ClassController::class, 'store'], ['filter' => 'csrf']);
    Router::get('/classes/edit/{id}', [ClassController::class, 'edit']);
    Router::post('/classes/update/{id}', [ClassController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/classes/delete/{id}', [ClassController::class, 'delete'], ['filter' => 'csrf']);
    Router::get('/classes/{id}/students', [ClassController::class, 'students']);
    Router::post('/classes/{id}/students/add', [ClassController::class, 'addStudent'], ['filter' => 'csrf']);
    Router::post('/classes/{id}/students/remove/{studentId}', [ClassController::class, 'removeStudent'], ['filter' => 'csrf']);

    // 4.7 CRUD Mata Pelajaran
    Router::get('/subjects', [SubjectController::class, 'index']);
    Router::get('/subjects/create', [SubjectController::class, 'create']);
    Router::post('/subjects/store', [SubjectController::class, 'store'], ['filter' => 'csrf']);
    Router::get('/subjects/edit/{id}', [SubjectController::class, 'edit']);
    Router::post('/subjects/update/{id}', [SubjectController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/subjects/delete/{id}', [SubjectController::class, 'delete'], ['filter' => 'csrf']);

    // 4.8 Konfigurasi Sistem Terpadu (System Configuration)
    Router::get('/system-configuration', [SystemConfigurationController::class, 'index']);
    Router::post('/system-configuration/update', [SystemConfigurationController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/system-configuration/reset', [SystemConfigurationController::class, 'resetDefaults'], ['filter' => 'csrf']);
    Router::post('/system-configuration/test-smtp', [SystemConfigurationController::class, 'testSmtp'], ['filter' => 'csrf']);
    Router::post('/system-configuration/test-whatsapp', [SystemConfigurationController::class, 'testWhatsApp'], ['filter' => 'csrf']);
    Router::get('/system-configuration/export', [SystemConfigurationController::class, 'export']);
    Router::post('/system-configuration/import', [SystemConfigurationController::class, 'import'], ['filter' => 'csrf']);

    // 4.9 Konfigurasi Parameter AI
    Router::get('/ai-configuration', [AiConfigurationController::class, 'index']);
    Router::post('/ai-configuration/update', [AiConfigurationController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/ai-configuration/reset', [AiConfigurationController::class, 'resetDefault'], ['filter' => 'csrf']);

    // 4.9 Kelola Jadwal & Token Ujian Terpusat (Admin UC-29)
    Router::get('/exams', [ExamController::class, 'index']);
    Router::get('/exams/show/{id}', [ExamController::class, 'show']);
    Router::get('/exams/preview/{id}', [ExamController::class, 'preview']);
    Router::get('/exams/edit/{id}', [ExamController::class, 'edit']);
    Router::post('/exams/update/{id}', [ExamController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/exams/status/{id}', [ExamController::class, 'updateStatus'], ['filter' => 'csrf']);
    Router::post('/exams/token/refresh/{id}', [ExamController::class, 'refreshToken'], ['filter' => 'csrf']);
    Router::post('/exams/token/toggle/{id}', [ExamController::class, 'toggleToken'], ['filter' => 'csrf']);
    Router::post('/exams/schedule/{id}', [ExamController::class, 'updateSchedule'], ['filter' => 'csrf']);

    // 4.10 Live Monitoring Dashboard Ujian (Admin UC-22, UC-22a, UC-22b, UC-22c)
    Router::get('/exams/monitoring', [ExamMonitoringController::class, 'hub']);
    Router::get('/monitoring', [ExamMonitoringController::class, 'hub']);
    Router::get('/exams/monitoring/{id}', [ExamMonitoringController::class, 'index']);
    Router::get('/exams/monitoring/{id}/poll', [ExamMonitoringController::class, 'pollData']);
    Router::get('/exams/monitoring/{id}/student/{studentId}', [ExamMonitoringController::class, 'studentDetail']);
    Router::post('/exams/monitoring/send-warning', [ExamMonitoringController::class, 'sendWarning'], ['filter' => 'csrf']);
    Router::post('/exams/monitoring/terminate', [ExamMonitoringController::class, 'terminate'], ['filter' => 'csrf']);
    Router::post('/exams/monitoring/reset-session', [ExamMonitoringController::class, 'resetSession'], ['filter' => 'csrf']);
    Router::post('/exams/monitoring/makeup-exam', [ExamMonitoringController::class, 'makeupExam'], ['filter' => 'csrf']);
    Router::post('/exams/monitoring/send-chat', [ExamMonitoringController::class, 'sendChat'], ['filter' => 'csrf']);
    Router::get('/exams/monitoring/chat-thread/{sessionId}', [ExamMonitoringController::class, 'getChatThread']);

    // 4.11 AI Operations & Intelligence Assistant Chatbot (Admin RAG / Database Integrated)
    Router::get('/ai-chat', [AiChatController::class, 'adminIndex']);
    Router::post('/ai-chat/send', [AiChatController::class, 'sendMessage'], ['filter' => ['csrf', 'rate_limit:30,60']]);
    Router::get('/ai-chat/history', [AiChatController::class, 'getHistory']);
    Router::post('/ai-chat/clear', [AiChatController::class, 'clearHistory'], ['filter' => 'csrf']);
    Router::post('/ai-chat/question-explain', [AiChatController::class, 'explainQuestion'], ['filter' => ['csrf', 'rate_limit:30,60']]);
    Router::get('/ai-chat/question-history', [AiChatController::class, 'getQuestionHistory']);
    Router::post('/ai-chat/question-clear', [AiChatController::class, 'clearQuestionHistory'], ['filter' => 'csrf']);

    // 4.12 Modul Notifikasi Admin
    Router::get('/notifications', [NotificationController::class, 'index']);
    Router::get('/notifications/inbox', [NotificationController::class, 'inbox']);
    Router::get('/notifications/dropdown', [NotificationController::class, 'getDropdownData']);
    Router::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
    Router::post('/notifications/mark-read/{id}', [NotificationController::class, 'markAsRead'], ['filter' => 'csrf']);
    Router::post('/notifications/mark-read', [NotificationController::class, 'markAsRead'], ['filter' => 'csrf']);
    Router::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'], ['filter' => 'csrf']);

    // 4.13 Modul Monitoring Log Notifikasi Eksternal (Admin Audit) & Pengiriman Kustom
    Router::get('/notification-logs', [NotificationLogController::class, 'index']);
    Router::get('/notifications/compose', [NotificationLogController::class, 'compose']);
    Router::post('/notifications/send-custom', [NotificationLogController::class, 'sendCustom'], ['filter' => 'csrf']);
    Router::get('/notifications/users-list', [NotificationLogController::class, 'getUsersList']);

    // 4.14 Modul Laporan & Analitik Chatbot AI (Admin Audit)
    Router::get('/ai-chat-reports', [AiChatReportController::class, 'index']);
    Router::get('/ai-chat-reports/chart-data', [AiChatReportController::class, 'chartData']);
    Router::get('/ai-chat-reports/detail-ajax/{id}', [AiChatReportController::class, 'detailAjax']);
    Router::get('/ai-chat-reports/detail/{id}', [AiChatReportController::class, 'detail']);
    Router::get('/ai-chat-reports/export-excel', [AiChatReportController::class, 'exportExcel']);
    Router::get('/ai-chat-reports/export-pdf', [AiChatReportController::class, 'exportPdf']);
    Router::get('/chat-reports', [AiChatReportController::class, 'index']);
});

// 5. Modul Guru & Bank Soal (Role Protected: guru, super_admin, admin)
Router::group(['prefix' => 'guru', 'filter' => ['auth', 'role:guru,super_admin,admin', 'csrf']], function () {
    // 5.1 Dashboard Guru
    Router::get('/dashboard', [GuruDashboardController::class, 'index']);

    // 5.2 CRUD Bank Soal & Unduh Template
    Router::get('/question-banks', [QuestionBankController::class, 'index']);
    Router::get('/question-banks/create', [QuestionBankController::class, 'create']);
    Router::post('/question-banks/store', [QuestionBankController::class, 'store'], ['filter' => 'csrf']);
    Router::get('/question-banks/template', [QuestionBankController::class, 'downloadTemplate']);
    Router::get('/question-banks/show/{id}', [QuestionBankController::class, 'show']);
    Router::get('/question-banks/edit/{id}', [QuestionBankController::class, 'edit']);
    Router::post('/question-banks/update/{id}', [QuestionBankController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/question-banks/delete/{id}', [QuestionBankController::class, 'delete'], ['filter' => 'csrf']);

    // 5.3 CRUD Butir Soal, Pratinjau, & Upload Gambar
    Router::get('/question-banks/{bankId}/questions/create', [QuestionController::class, 'create']);
    Router::post('/question-banks/{bankId}/questions/store', [QuestionController::class, 'store'], ['filter' => 'csrf']);
    Router::get('/questions/edit/{id}', [QuestionController::class, 'edit']);
    Router::post('/questions/update/{id}', [QuestionController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/questions/delete/{id}', [QuestionController::class, 'delete'], ['filter' => 'csrf']);
    Router::get('/questions/preview/{id}', [QuestionController::class, 'preview']);

    // 5.4 Impor Soal File (Excel/CSV/JSON)
    Router::get('/question-banks/{bankId}/import', [QuestionController::class, 'showImport']);
    Router::post('/question-banks/{bankId}/import', [QuestionController::class, 'processImport'], ['filter' => 'csrf']);

    // 5.5 Manajemen Ujian & Token (Guru UC-20, UC-20a, UC-29)
    Router::get('/exams', [ExamController::class, 'index']);
    Router::get('/exams/create', [ExamController::class, 'create']);
    Router::post('/exams/store', [ExamController::class, 'store'], ['filter' => 'csrf']);
    Router::get('/exams/show/{id}', [ExamController::class, 'show']);
    Router::get('/exams/preview/{id}', [ExamController::class, 'preview']);
    Router::get('/exams/edit/{id}', [ExamController::class, 'edit']);
    Router::post('/exams/update/{id}', [ExamController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/exams/status/{id}', [ExamController::class, 'updateStatus'], ['filter' => 'csrf']);
    Router::post('/exams/token/refresh/{id}', [ExamController::class, 'refreshToken'], ['filter' => 'csrf']);
    Router::post('/exams/token/toggle/{id}', [ExamController::class, 'toggleToken'], ['filter' => 'csrf']);
    Router::post('/exams/schedule/{id}', [ExamController::class, 'updateSchedule'], ['filter' => 'csrf']);

    // 5.6 AI Proctoring Live Monitoring & Review (UC-18)
    Router::get('/proctoring/session-detail/{id}', [AiProctoringController::class, 'teacherSessionDetail']);
    Router::post('/proctoring/review-violation', [AiProctoringController::class, 'teacherReviewViolation'], ['filter' => 'csrf']);

    // 5.7 Live Monitoring Dashboard Guru (UC-22, UC-22a, UC-22b, UC-22c)
    Router::get('/exams/monitoring', [ExamMonitoringController::class, 'hub']);
    Router::get('/monitoring', [ExamMonitoringController::class, 'hub']);
    Router::get('/exams/monitoring/{id}', [ExamMonitoringController::class, 'index']);
    Router::get('/exams/monitoring/{id}/poll', [ExamMonitoringController::class, 'pollData']);
    Router::get('/exams/monitoring/{id}/student/{studentId}', [ExamMonitoringController::class, 'studentDetail']);
    Router::post('/exams/monitoring/send-warning', [ExamMonitoringController::class, 'sendWarning'], ['filter' => 'csrf']);
    Router::post('/exams/monitoring/terminate', [ExamMonitoringController::class, 'terminate'], ['filter' => 'csrf']);
    Router::post('/exams/monitoring/reset-session', [ExamMonitoringController::class, 'resetSession'], ['filter' => 'csrf']);
    Router::post('/exams/monitoring/makeup-exam', [ExamMonitoringController::class, 'makeupExam'], ['filter' => 'csrf']);
    Router::post('/exams/monitoring/send-chat', [ExamMonitoringController::class, 'sendChat'], ['filter' => 'csrf']);
    Router::get('/exams/monitoring/chat-thread/{sessionId}', [ExamMonitoringController::class, 'getChatThread']);

    // 5.8 AI Question Generator (UC-21c)
    Router::get('/ai-questions', [AiQuestionController::class, 'index']);
    Router::get('/ai-questions/create', [AiQuestionController::class, 'create']);
    Router::post('/ai-questions/generate', [AiQuestionController::class, 'generate'], ['filter' => ['csrf', 'rate_limit:20,60']]);
    Router::get('/ai-questions/review/{id}', [AiQuestionController::class, 'review']);
    Router::post('/ai-questions/import', [AiQuestionController::class, 'import'], ['filter' => 'csrf']);
    Router::post('/ai-questions/delete/{id}', [AiQuestionController::class, 'delete'], ['filter' => 'csrf']);

    // 5.9 Modul Penilaian & Laporan (UC-16, UC-16a, UC-23, UC-24, UC-25)
    Router::get('/grading', [GradingController::class, 'index']);
    Router::get('/grading/exam/{id}', [GradingController::class, 'examSubmissions']);
    Router::get('/grading/session/{id}', [GradingController::class, 'gradeSession']);
    Router::post('/grading/save-essay-score', [GradingController::class, 'saveEssayScore'], ['filter' => 'csrf']);
    Router::post('/grading/auto-grade/{examId}', [GradingController::class, 'triggerAutoGrading'], ['filter' => 'csrf']);
    Router::post('/grading/publish/{examId}', [GradingController::class, 'publishResults'], ['filter' => 'csrf']);
    Router::post('/grading/save-feedback', [GradingController::class, 'saveFeedback'], ['filter' => 'csrf']);
    Router::post('/grading/generate-ai-explanation', [GradingController::class, 'generateAiExplanation'], ['filter' => ['csrf', 'rate_limit:30,60']]);

    // 5.10 Ekspor Laporan (PDF via Dompdf & Excel via PhpSpreadsheet)
    Router::get('/reports/exam/{id}/pdf', [ReportController::class, 'exportPdf']);
    Router::get('/reports/exam/{id}/excel', [ReportController::class, 'exportExcel']);
    Router::get('/reports/session/{id}/pdf', [ReportController::class, 'exportStudentPdf']);

    // 5.11 AI Pedagogic Assistant Chatbot (UC-21c/Chat)
    Router::get('/ai-chat', [AiChatController::class, 'guruIndex']);
    Router::post('/ai-chat/send', [AiChatController::class, 'sendMessage'], ['filter' => ['csrf', 'rate_limit:30,60']]);
    Router::get('/ai-chat/history', [AiChatController::class, 'getHistory']);
    Router::post('/ai-chat/clear', [AiChatController::class, 'clearHistory'], ['filter' => 'csrf']);
    Router::post('/ai-chat/question-explain', [AiChatController::class, 'explainQuestion'], ['filter' => ['csrf', 'rate_limit:30,60']]);
    Router::get('/ai-chat/question-history', [AiChatController::class, 'getQuestionHistory']);
    Router::post('/ai-chat/question-clear', [AiChatController::class, 'clearQuestionHistory'], ['filter' => 'csrf']);

    // 5.12 Modul Notifikasi Guru
    Router::get('/notifications', [NotificationController::class, 'index']);
    Router::get('/notifications/inbox', [NotificationController::class, 'inbox']);
    Router::get('/notifications/dropdown', [NotificationController::class, 'getDropdownData']);
    Router::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
    Router::post('/notifications/mark-read/{id}', [NotificationController::class, 'markAsRead'], ['filter' => 'csrf']);
    Router::post('/notifications/mark-read', [NotificationController::class, 'markAsRead'], ['filter' => 'csrf']);
    Router::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'], ['filter' => 'csrf']);
});


// 6. Modul Peserta Didik (UC-05, UC-06, UC-17)
Router::group(['prefix' => 'siswa', 'filter' => ['auth', 'role:peserta_didik', 'csrf']], function () {
    // 6.1 Dashboard Siswa
    Router::get('/dashboard', [StudentDashboardController::class, 'index']);

    // 6.2 Katalog Ujian Tersedia & Detail Validasi (UC-06)
    Router::get('/exams', [StudentExamController::class, 'index']);
    Router::get('/exams/show/{id}', [StudentExamController::class, 'show']);

    // 6.3 Riwayat Ujian (UC-05)
    Router::get('/history', [StudentExamController::class, 'history']);

    // 6.4 Nilai & Hasil Evaluasi Ujian (UC-05)
    Router::get('/results', [StudentResultController::class, 'index']);
    Router::get('/results/show/{id}', [StudentResultController::class, 'show']);

    // 6.5 Mesin Pengerjaan Ujian (UC-10, UC-11, UC-12, UC-14)
    Router::post('/exams/start/{id}', [ExamSessionController::class, 'start'], ['filter' => 'csrf']);
    Router::get('/exam/room/{sessionToken}', [ExamSessionController::class, 'room']);
    Router::post('/exam/answer/save', [ExamAnswerController::class, 'save'], ['filter' => 'csrf']);
    Router::get('/exam/timer/sync/{sessionToken}', [ExamAnswerController::class, 'syncTimer']);
    Router::post('/exam/submit', [ExamSessionController::class, 'submit'], ['filter' => 'csrf']);
    Router::get('/exam/completed/{sessionToken}', [ExamSessionController::class, 'completed']);

    // 6.6 Kalibrasi & Diagnostik Dual Kamera (UC-07, UC-09, UC-14)
    Router::get('/exam/pre-check', [CameraPreCheckController::class, 'index']);
    Router::get('/exam/pre-check/{id}', [CameraPreCheckController::class, 'index']);
    Router::post('/exam/pre-check/save-config', [CameraPreCheckController::class, 'saveConfig'], ['filter' => 'csrf']);

    // 6.7 AI Proctoring API (UC-08, UC-13, UC-13a, UC-13b, UC-18)
    Router::post('/proctoring/init-session', [AiProctoringController::class, 'initSession'], ['filter' => 'csrf']);
    Router::post('/proctoring/verify-face', [AiProctoringController::class, 'verifyFace'], ['filter' => 'csrf']);
    Router::post('/proctoring/log-telemetry', [AiProctoringController::class, 'logTelemetry'], ['filter' => 'csrf']);
    Router::post('/proctoring/record-violation', [AiProctoringController::class, 'recordViolation'], ['filter' => 'csrf']);
    Router::post('/proctoring/acknowledge-warning', [AiProctoringController::class, 'acknowledgeWarning'], ['filter' => 'csrf']);
    Router::get('/proctoring/session-status/{id}', [AiProctoringController::class, 'sessionStatus']);

    // 6.8 AI Learning Assistant Chatbot (Study Mode & EXAM_MODE & Question Explainer)
    Router::get('/ai-chat', [AiChatController::class, 'siswaIndex']);
    Router::post('/ai-chat/send', [AiChatController::class, 'sendMessage'], ['filter' => ['csrf', 'rate_limit:30,60']]);
    Router::get('/ai-chat/history', [AiChatController::class, 'getHistory']);
    Router::post('/ai-chat/clear', [AiChatController::class, 'clearHistory'], ['filter' => 'csrf']);
    Router::post('/ai-chat/question-explain', [AiChatController::class, 'explainQuestion'], ['filter' => ['csrf', 'rate_limit:30,60']]);
    Router::get('/ai-chat/question-history', [AiChatController::class, 'getQuestionHistory']);
    Router::post('/ai-chat/question-clear', [AiChatController::class, 'clearQuestionHistory'], ['filter' => 'csrf']);

    // 6.9 Modul Notifikasi Siswa
    Router::get('/notifications', [NotificationController::class, 'index']);
    Router::get('/notifications/inbox', [NotificationController::class, 'inbox']);
    Router::get('/notifications/dropdown', [NotificationController::class, 'getDropdownData']);
    Router::get('/notifications/unread-count', [NotificationController::class, 'getUnreadCount']);
    Router::post('/notifications/mark-read/{id}', [NotificationController::class, 'markAsRead'], ['filter' => 'csrf']);
    Router::post('/notifications/mark-read', [NotificationController::class, 'markAsRead'], ['filter' => 'csrf']);
    Router::post('/notifications/mark-all-read', [NotificationController::class, 'markAllAsRead'], ['filter' => 'csrf']);
});

// 7. Route Alias English (/student/exam/pre-check)
Router::group(['prefix' => 'student', 'filter' => ['auth', 'role:peserta_didik', 'csrf']], function () {
    Router::get('/exam/pre-check', [CameraPreCheckController::class, 'index']);
    Router::get('/exam/pre-check/{id}', [CameraPreCheckController::class, 'index']);
});

// 8. Modul Notifikasi Terpadu (Akses Global Seluruh Role Terotentikasi)
Router::group(['prefix' => 'notifications', 'filter' => ['auth', 'csrf']], function () {
    Router::get('/', [NotificationController::class, 'index']);
    Router::get('/inbox', [NotificationController::class, 'inbox']);
    Router::get('/dropdown', [NotificationController::class, 'getDropdownData']);
    Router::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
    Router::post('/mark-read/{id}', [NotificationController::class, 'markAsRead'], ['filter' => 'csrf']);
    Router::post('/mark-read', [NotificationController::class, 'markAsRead'], ['filter' => 'csrf']);
    Router::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'], ['filter' => 'csrf']);
});

Router::group(['prefix' => 'api/notifications', 'filter' => ['auth', 'csrf']], function () {
    Router::get('/', [NotificationController::class, 'index']);
    Router::get('/dropdown', [NotificationController::class, 'getDropdownData']);
    Router::get('/unread-count', [NotificationController::class, 'getUnreadCount']);
    Router::post('/mark-read/{id}', [NotificationController::class, 'markAsRead'], ['filter' => 'csrf']);
    Router::post('/mark-read', [NotificationController::class, 'markAsRead'], ['filter' => 'csrf']);
    Router::post('/mark-all-read', [NotificationController::class, 'markAllAsRead'], ['filter' => 'csrf']);
});

// 9. Modul Profil Saya & Edit Profil (Akses Global Seluruh Role Terotentikasi)
Router::group(['prefix' => 'profile', 'filter' => ['auth']], function () {
    Router::get('/', [ProfileController::class, 'index']);
    Router::get('/edit', [ProfileController::class, 'edit']);
    Router::post('/update', [ProfileController::class, 'update'], ['filter' => 'csrf']);
    Router::post('/upload-avatar', [ProfileController::class, 'uploadAvatar'], ['filter' => 'csrf']);
    Router::post('/delete-avatar', [ProfileController::class, 'deleteAvatar'], ['filter' => 'csrf']);
    Router::post('/theme', [ProfileController::class, 'updateTheme']);
});

// 10. Pengaturan Tema Tampilan Global (API & Tamu)
Router::post('/api/theme', [ProfileController::class, 'updateTheme']);

