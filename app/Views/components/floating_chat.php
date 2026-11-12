<?php
/**
 * Global Floating AI Chatbot Component
 * Modern, Minimalist, Scoped Live Database AI Assistant Widget
 */

$userRole = (string)(user_role() ?? 'siswa');
$userName = (string)(user_name() ?? 'Pengguna');
$userId = (int)user_id();

if ($userId <= 0) {
    return; // Don't render floating chat for unauthenticated guests
}

$roleLabel = match($userRole) {
    'super_admin' => 'Super Administrator',
    'admin' => 'Administrator',
    'guru' => 'Guru & Pengawas',
    'peserta_didik', 'siswa' => 'Peserta Didik',
    default => 'Pengguna'
};

$roleEndpointPrefix = match($userRole) {
    'super_admin', 'admin' => 'admin',
    'guru' => 'guru',
    default => 'siswa'
};

$quickPrompts = match($userRole) {
    'super_admin', 'admin' => [
        ['icon' => 'bi-speedometer2', 'text' => 'Ringkasan operasional ujian saat ini'],
        ['icon' => 'bi-shield-check', 'text' => 'Statistik pelanggaran proctoring AI'],
        ['icon' => 'bi-people-fill', 'text' => 'Rekapitulasi akun siswa & guru aktif'],
        ['icon' => 'bi-database-fill-gear', 'text' => 'Status parameter & konfigurasi AI sistem']
    ],
    'guru' => [
        ['icon' => 'bi-pencil-square', 'text' => 'Berapa banyak esai siswa yang belum dinilai?'],
        ['icon' => 'bi-journal-check', 'text' => 'Analisis tingkat kesukaran bank soal saya'],
        ['icon' => 'bi-shield-exclamation', 'text' => 'Statistik pelanggaran integritas ujian saya'],
        ['icon' => 'bi-mortarboard', 'text' => 'Ringkasan nilai rata-rata ujian siswa saya']
    ],
    default => [
        ['icon' => 'bi-calendar-event', 'text' => 'Kapan jadwal ujian saya berikutnya?'],
        ['icon' => 'bi-award-fill', 'text' => 'Bagaimana evaluasi nilai dan kelulusan KKM saya?'],
        ['icon' => 'bi-person-badge-fill', 'text' => 'Siapa wali kelas dan data profil kelas saya?'],
        ['icon' => 'bi-lightbulb', 'text' => 'Tips cara belajar efektif dan memahami materi sulit']
    ]
};

$roleScopeDescription = match($userRole) {
    'super_admin', 'admin' => 'Anda memiliki wewenang analitik sistem menyeluruh, rekapitulasi ujian, statistik pengguna, dan pengawasan proctoring dual kamera.',
    'guru' => 'Anda memiliki akses pada paket ujian yang Anda ampu, evaluasi bank soal, penilaian esai siswa, dan kelas bimbingan Anda.',
    default => 'Anda memiliki akses aman pada jadwal ujian Anda, hasil nilai pribadi, dan pendampingan materi belajar. Data siswa lain dijaga kerahasiaannya.'
};
?>

<!-- Floating AI Chatbot Container -->
<div id="eduFloatingAiChat" class="edu-floating-ai-wrapper" data-role="<?= e($roleEndpointPrefix) ?>" data-base-url="<?= base_url() ?>">
    <!-- Floating Chat Window (Minimalist Modern UI - Ref: Modern AI Chatbot) -->
    <div id="floatingChatWindow" class="floating-chat-window d-none rounded-4 overflow-hidden shadow-2xl" aria-label="AI Chatbot Window">
        <!-- Chat Header (Clean Modern Header with Avatar & Online Dot) -->
        <div class="floating-chat-header d-flex align-items-center justify-content-between">
            <!-- Left: Squircle Avatar & Identity -->
            <div class="d-flex align-items-center gap-2.5 min-w-0">
                <div class="ai-avatar-squircle flex-shrink-0">
                    <i class="bi bi-robot fs-5 text-white"></i>
                </div>
                <div class="header-info-wrapper text-truncate">
                    <div class="d-flex align-items-center gap-1.5">
                        <h6 class="mb-0 fw-bold fs-xs text-heading text-truncate">AI Chatbot</h6>
                    </div>
                    <div class="d-flex align-items-center gap-1.5 mt-0.5">
                        <span class="fs-4xs text-muted">Always here to help you</span>
                        <span class="status-indicator-dot" title="Live Database Terhubung (59 Tabel)"></span>
                    </div>
                </div>
            </div>

            <!-- Right: Action Controls (Options Dropdown & Close) -->
            <div class="d-flex align-items-center gap-1 flex-shrink-0 ms-2">
                <div class="dropdown">
                    <button type="button" class="btn btn-icon-subtle text-muted" id="btnFloatingChatMenu" data-bs-toggle="dropdown" aria-expanded="false" title="Opsi Percakapan" aria-label="Menu Opsi">
                        <i class="bi bi-three-dots fs-5"></i>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm border rounded-3 fs-xs py-1" aria-labelledby="btnFloatingChatMenu">
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-1.5 px-3" id="btnFloatingChatClear">
                                <i class="bi bi-arrow-clockwise text-primary"></i> Mulai Percakapan Baru
                            </button>
                        </li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-1.5 px-3" id="btnFloatingChatInfo">
                                <i class="bi bi-shield-check text-info"></i> Hak Wewenang & Privasi
                            </button>
                        </li>
                        <li><hr class="dropdown-divider my-1"></li>
                        <li>
                            <button type="button" class="dropdown-item d-flex align-items-center gap-2 py-1.5 px-3 text-muted" id="btnFloatingChatMinimize" onclick="if(window.FloatingChat){ window.FloatingChat.closeWindow(); } else { const w=document.getElementById('floatingChatWindow'); if(w) w.classList.add('d-none'); }">
                                <i class="bi bi-dash-lg"></i> Perkecil Chat
                            </button>
                        </li>
                    </ul>
                </div>
                <button type="button" class="btn btn-icon-subtle text-muted" id="btnFloatingChatClose" title="Tutup Chat" aria-label="Tutup Chat" onclick="if(window.FloatingChat){ window.FloatingChat.closeWindow(); } else { const w=document.getElementById('floatingChatWindow'); if(w) w.classList.add('d-none'); }">
                    <i class="bi bi-x-lg fs-6"></i>
                </button>
            </div>
        </div>

        <!-- Privacy & Authority Info Banner (Collapsible) -->
        <div id="floatingChatInfoBanner" class="floating-info-banner d-none p-3 border-bottom">
            <div class="d-flex align-items-start gap-2 fs-3xs">
                <i class="bi bi-shield-lock-fill text-indigo-600 fs-5 flex-shrink-0 mt-0.5"></i>
                <div class="flex-grow-1">
                    <div class="fw-bold text-slate-800 mb-0.5 d-flex justify-content-between align-items-center">
                        <span>Wewenang & Perlindungan Privasi (<?= e($roleLabel) ?>)</span>
                        <button type="button" class="btn-close fs-4xs" id="btnCloseInfoBanner" aria-label="Tutup"></button>
                    </div>
                    <p class="text-slate-600 mb-1 leading-snug"><?= e($roleScopeDescription) ?></p>
                    <div class="d-flex align-items-center gap-2 text-slate-500 fs-4xs">
                        <span><i class="bi bi-check-circle-fill text-success me-0.5"></i>Database Real-Time (59 Tabel)</span>
                        <span><i class="bi bi-lock-fill text-danger me-0.5"></i>Data Akun Terisolasi</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Suggestion Chips Carousel -->
        <div class="floating-quick-chips d-flex overflow-x-auto no-scrollbar">
            <?php foreach ($quickPrompts as $qp): ?>
                <button type="button" class="btn btn-quick-chip text-nowrap d-flex align-items-center gap-1.5" data-prompt="<?= e($qp['text']) ?>">
                    <i class="bi <?= e($qp['icon']) ?> text-indigo-500"></i>
                    <span><?= e($qp['text']) ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <!-- Chat Messages Body -->
        <div id="floatingChatMessages" class="floating-chat-messages overflow-y-auto d-flex flex-column" role="log" aria-live="polite">
            <!-- Initial Welcome Message (Clean modern style) -->
            <div class="ai-msg-row d-flex gap-2.5 align-items-start">
                <div class="ai-msg-avatar flex-shrink-0 rounded-3 text-white d-flex align-items-center justify-content-center shadow-3xs">
                    <i class="bi bi-robot fs-6"></i>
                </div>
                <div class="ai-msg-bubble-wrapper max-w-85">
                    <div class="ai-msg-bubble rounded-4 shadow-3xs">
                        <div class="fw-semibold mb-1 d-flex align-items-center gap-1.5 text-heading fs-xs">
                            <span>Hello! 👋</span>
                        </div>
                        <p class="mb-1.5 leading-relaxed">
                            I'm your AI assistant. How can I help you today?
                        </p>
                        <p class="mb-0 fs-3xs text-muted leading-relaxed">
                            Saya asisten AI Smart Exam Anda untuk informasi jadwal ujian, bank soal, hasil nilai, dan bimbingan belajar.
                        </p>
                    </div>
                    <span class="chat-time-label mt-1 px-1 d-block"><?= date('h:i A') ?></span>
                </div>
            </div>
        </div>

        <!-- Typing Indicator -->
        <div id="floatingChatTyping" class="floating-chat-typing px-3 py-2 d-none align-items-center gap-2 text-muted fs-3xs border-top">
            <div class="typing-dots d-flex gap-1">
                <span></span><span></span><span></span>
            </div>
            <span class="d-flex align-items-center gap-1">
                <i class="bi bi-cpu text-indigo-500"></i>
                <span>Mengetik jawaban...</span>
            </span>
        </div>

        <!-- Chat Input Form (Pill Shape with Circular Send Button) -->
        <div class="floating-chat-footer border-top">
            <form id="floatingChatForm" class="d-flex align-items-center" data-no-auto-loading="true" onsubmit="return false;">
                <div class="chat-input-pill flex-grow-1 d-flex align-items-center position-relative">
                    <textarea 
                        id="floatingChatInput" 
                        class="form-control chat-pill-textarea fs-xs" 
                        rows="1" 
                        placeholder="Type your message..."
                        aria-label="Ketik pesan chat"></textarea>
                    <button type="submit" id="btnFloatingChatSend" class="btn btn-chat-send-circle text-white d-flex align-items-center justify-content-center flex-shrink-0" title="Kirim Pesan" aria-label="Kirim Pesan">
                        <i class="bi bi-send-fill fs-6"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating Toggle Button -->
    <button type="button" id="floatingChatToggle" class="floating-chat-btn rounded-circle text-white d-flex align-items-center justify-content-center border-0 position-relative" title="Buka AI Chatbot" aria-label="Buka AI Chatbot">
        <div class="pulse-ring"></div>
        <div class="icon-open d-flex align-items-center justify-content-center">
            <i class="bi bi-robot fs-4"></i>
        </div>
        <div class="icon-close d-none d-flex align-items-center justify-content-center">
            <i class="bi bi-x-lg fs-5"></i>
        </div>
        <span class="floating-btn-badge position-absolute top-0 end-0 bg-danger border border-2 border-white rounded-pill px-1.5 py-0.5 fs-4xs fw-bold text-white d-none">
            AI
        </span>
    </button>
</div>

