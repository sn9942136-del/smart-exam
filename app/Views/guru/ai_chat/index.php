<div class="container-fluid py-2" id="guruAiChatApp">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 fs-sm">
                    <li class="breadcrumb-item"><a href="<?= base_url('guru/dashboard') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">AI Pedagogic Assistant</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-chat-heart-fill text-primary"></i> Asisten AI Guru & Kurikulum
            </h4>
            <span class="text-muted fs-xs">Konsultasikan perumusan soal HOTS, rubrik penilaian, modul ajar, dan strategi asesmen dengan kecerdasan buatan.</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fs-xs">
                <i class="bi bi-cpu-fill me-1"></i> Gemini 3.5 Flash Powered
            </span>
            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-2xs" onclick="GuruAiChat.clearChat()">
                <i class="bi bi-trash3 me-1"></i> Bersihkan Chat
            </button>
        </div>
    </div>

    <!-- Main Chat Workspace Card -->
    <div class="row g-3">
        <!-- Sidebar Riwayat Percakapan (Desktop) -->
        <div class="col-lg-4 col-xl-3 d-none d-lg-block">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white">
                <div class="card-header bg-white border-bottom border-light py-3 px-3 d-flex justify-content-between align-items-center">
                    <span class="fw-bold text-dark fs-sm"><i class="bi bi-clock-history me-1"></i> Riwayat Sesi</span>
                    <button type="button" class="btn btn-sm btn-primary-gradient rounded-pill px-2.5 py-1 fs-xs" onclick="GuruAiChat.newSession()">
                        <i class="bi bi-plus-lg me-1"></i> Baru
                    </button>
                </div>
                <div class="card-body p-2 overflow-auto" style="max-height: calc(100vh - 240px);" id="sessionsListContainer">
                    <?php if (empty($recentSessions)): ?>
                        <div class="text-center py-4 text-muted fs-xs">
                            <i class="bi bi-chat-square-dots fs-3 d-block mb-2 text-secondary opacity-50"></i>
                            Belum ada riwayat sesi.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush gap-1">
                            <?php foreach ($recentSessions as $s): ?>
                                <?php $isActive = ($s['session_uuid'] === ($activeSession['session_uuid'] ?? '')); ?>
                                <a href="<?= base_url('guru/ai-chat?session_uuid=' . $s['session_uuid']) ?>" class="list-group-item list-group-item-action rounded-3 border-0 py-2 px-2.5 <?= $isActive ? 'bg-primary-subtle text-primary fw-semibold' : 'text-dark' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fs-xs text-truncate" style="max-width: 170px;"><?= e($s['title'] ?? 'Percakapan') ?></span>
                                        <small class="fs-3xs text-muted"><?= date('d/m', strtotime($s['updated_at'])) ?></small>
                                    </div>
                                    <small class="fs-3xs text-muted d-block text-truncate"><?= (int)($s['total_messages'] ?? 0) ?> pesan</small>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Chat Conversation Screen -->
        <div class="col-lg-8 col-xl-9">
            <div class="card border-0 shadow-sm rounded-4 h-100 bg-white d-flex flex-column" style="min-height: calc(100vh - 200px);">
                <!-- Active Session Top Bar -->
                <div class="card-header bg-white border-bottom border-light py-2.5 px-4 d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-2.5">
                        <div class="ai-avatar-icon">
                            <i class="bi bi-robot fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" id="chatSessionTitle"><?= e($activeSession['title'] ?? 'Konsultasi Pedagogik') ?></h6>
                            <span class="text-success fs-3xs d-flex align-items-center gap-1">
                                <span class="p-1 bg-success rounded-circle d-inline-block"></span> Asisten Aktif &bull; Mode Guru &bull; Gemini AI
                            </span>
                        </div>
                    </div>
                    <div class="d-flex gap-1.5">
                        <a href="<?= base_url('guru/ai-questions') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fs-xs shadow-2xs">
                            <i class="bi bi-magic me-1"></i> Ke AI Question Gen
                        </a>
                    </div>
                </div>

                <!-- Messages Stream Box -->
                <div class="card-body p-3 p-md-4 flex-grow-1 overflow-auto bg-body-tertiary" id="chatMessagesBox" style="max-height: calc(100vh - 350px); min-height: 400px;">
                    <!-- Welcome Initial Bubble -->
                    <div class="d-flex gap-3 mb-4 ai-message-row">
                        <div class="ai-avatar-icon flex-shrink-0">
                            <i class="bi bi-robot fs-5"></i>
                        </div>
                        <div class="ai-message-card p-3.5 text-dark w-100" style="max-width: 88%;">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1.5 border-bottom border-light">
                                <div class="fw-bold fs-xs text-primary d-flex align-items-center gap-1.5">
                                    <i class="bi bi-stars text-warning"></i>
                                    <span>Asisten AI Guru</span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-3xs rounded-pill">Pedagogik & Kurikulum</span>
                                </div>
                                <span class="fs-3xs text-muted">Online</span>
                            </div>
                            <div class="chat-markdown-body">
                                <p class="mb-2">Halo Bapak/Ibu Guru! 👋 Saya siap membantu Anda dalam perencanaan dan evaluasi pembelajaran:</p>
                                <ul class="ai-styled-list mb-2">
                                    <li>Perumusan indikator dan butir soal berbasis <strong>HOTS (Higher Order Thinking Skills)</strong></li>
                                    <li>Penyusunan <strong>rubrik penilaian esai & asesmen diagnostik</strong> yang terstandar</li>
                                    <li>Inspirasi modul ajar dan diferensiasi pembelajaran Kurikulum Merdeka</li>
                                    <li>Analisis butir soal dan rekomendasi pengayaan / remedial</li>
                                </ul>
                                <p class="mb-0 text-muted fs-xs">Silakan ketik kebutuhan asesmen Anda atau klik saran topik cepat di bawah.</p>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2.5 pt-1.5 border-top border-light fs-3xs text-muted">
                                <span>Platform Evaluasi Akademik Digital</span>
                                <span>Smart Exam AI Engine</span>
                            </div>
                        </div>
                    </div>

                    <!-- Render Existing Messages from DB -->
                    <?php if (!empty($messages)): ?>
                        <?php foreach ($messages as $msg): ?>
                            <?php if ($msg['sender'] === 'user'): ?>
                                <div class="d-flex justify-content-end align-items-start gap-2.5 mb-4 user-message-row">
                                    <div class="chat-bubble-user p-3 shadow-2xs" style="max-width: 82%;">
                                        <div class="fs-sm lh-base text-white" style="white-space: pre-wrap;"><?= e($msg['message']) ?></div>
                                        <div class="text-end text-white-50 fs-3xs mt-1.5"><?= date('H:i', strtotime($msg['created_at'])) ?></div>
                                    </div>
                                    <div class="user-avatar-icon flex-shrink-0 d-none d-sm-flex">
                                        <i class="bi bi-person-fill fs-6"></i>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="d-flex gap-3 mb-4 ai-message-row">
                                    <div class="ai-avatar-icon flex-shrink-0">
                                        <i class="bi bi-robot fs-5"></i>
                                    </div>
                                    <div class="ai-message-card p-3.5 text-dark w-100 <?= !empty($msg['is_error']) ? 'border-danger-subtle bg-danger-subtle' : '' ?>" style="max-width: 88%;">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1.5 border-bottom border-light">
                                            <div class="fw-bold fs-xs <?= !empty($msg['is_error']) ? 'text-danger' : 'text-primary' ?> d-flex align-items-center gap-1.5">
                                                <i class="bi bi-stars text-warning"></i>
                                                <span>Asisten AI Guru</span>
                                                <?php if (!empty($msg['model_used'])): ?>
                                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-3xs rounded-pill">
                                                        <?= e($msg['model_used']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                            <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none fs-3xs" onclick="MarkdownRenderer.copyMessage(this)" title="Salin Jawaban">
                                                <i class="bi bi-clipboard me-1"></i>Salin
                                            </button>
                                        </div>
                                        <div class="chat-markdown-body" data-raw-message="<?= e($msg['message']) ?>">
                                            <!-- Will be rendered by MarkdownRenderer -->
                                            <?= nl2br(e($msg['message'])) ?>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center mt-2.5 pt-1.5 border-top border-light fs-3xs text-muted">
                                            <span><i class="bi bi-check2-all text-primary me-1"></i>Terverifikasi Gemini AI</span>
                                            <span><?= date('H:i', strtotime($msg['created_at'])) ?></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>

                    <!-- Dynamic Typing Indicator Box -->
                    <div id="aiTypingIndicator" class="d-none gap-3 mb-4 ai-message-row align-items-center">
                        <div class="ai-avatar-icon flex-shrink-0">
                            <i class="bi bi-robot fs-5"></i>
                        </div>
                        <div class="bg-white border border-secondary-subtle rounded-4 py-2.5 px-4 text-muted shadow-2xs d-inline-flex align-items-center gap-2">
                            <span class="spinner-grow spinner-grow-sm text-primary" role="status" aria-hidden="true" style="width: 8px; height: 8px;"></span>
                            <span class="spinner-grow spinner-grow-sm text-info" role="status" aria-hidden="true" style="width: 8px; height: 8px; animation-delay: 0.15s;"></span>
                            <span class="spinner-grow spinner-grow-sm text-success" role="status" aria-hidden="true" style="width: 8px; height: 8px; animation-delay: 0.3s;"></span>
                            <span class="fs-xs ms-1.5 text-secondary fw-medium">Sedang merumuskan respon pedagogik...</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Prompt Suggestions Chips -->
                <div class="px-4 py-2 bg-white border-top border-light d-flex gap-2 overflow-auto text-nowrap" id="quickPromptChips">
                    <span class="fs-3xs text-uppercase fw-bold text-muted d-flex align-items-center gap-1">
                        <i class="bi bi-lightbulb-fill text-warning"></i> Analisis Cepat:
                    </span>
                    <button type="button" class="quick-prompt-chip" onclick="GuruAiChat.insertPrompt('Tampilkan ringkasan bank soal yang saya miliki beserta sebaran tingkat kesukarannya.')">
                        📚 Bank Soal & Tingkat Kesukaran
                    </button>
                    <button type="button" class="quick-prompt-chip" onclick="GuruAiChat.insertPrompt('Bagaimana rekapitulasi nilai dan rata-rata kelas pada paket ujian yang saya ampu?')">
                        📊 Rekap Nilai Ujian Siswa
                    </button>
                    <button type="button" class="quick-prompt-chip" onclick="GuruAiChat.insertPrompt('Bagaimana ringkasan pelanggaran integritas proctoring pada ujian yang saya selenggarakan?')">
                        🛡️ Statistik Pelanggaran Ujian
                    </button>
                    <button type="button" class="quick-prompt-chip" onclick="GuruAiChat.insertPrompt('Berikan rekomendasi stimulus soal HOTS kontekstual untuk mata pelajaran dan kelas yang saya ampu.')">
                        📝 Rancangan Soal HOTS
                    </button>
                    <button type="button" class="quick-prompt-chip" onclick="GuruAiChat.insertPrompt('Buatkan program remedial dan pengayaan berdasarkan capaian hasil ujian siswa saya.')">
                        🎯 Rekomendasi Remedial
                    </button>
                </div>

                <!-- Input Message Box -->
                <div class="card-footer bg-white border-top border-light p-3">
                    <form id="guruChatForm" onsubmit="GuruAiChat.handleSubmit(event)">
                        <input type="hidden" id="currentSessionUuid" value="<?= e($activeSession['session_uuid'] ?? '') ?>">
                        <div class="input-group shadow-2xs rounded-4 overflow-hidden border">
                            <textarea 
                                class="form-control border-0 py-2.5 px-3 fs-sm shadow-none" 
                                id="guruChatMessage" 
                                rows="1" 
                                placeholder="Konsultasikan perumusan soal, modul ajar, rubrik penilaian, atau kurikulum..." 
                                required
                                onkeydown="GuruAiChat.handleKeyDown(event)"
                            ></textarea>
                            <button class="btn btn-primary-gradient px-4 d-flex align-items-center justify-content-center" type="submit" id="btnSendChat">
                                <i class="bi bi-send-fill fs-6 me-1.5"></i>
                                <span class="d-none d-sm-inline fw-semibold">Kirim</span>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                            <span class="fs-3xs text-muted">
                                <i class="bi bi-shield-check text-success me-1"></i> Asisten pedagogik cerdas terenkripsi &bull; Gemini 3.5 Flash Model
                            </span>
                            <span class="fs-3xs text-muted fw-semibold" id="charCounter">0 / 3000</span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Load Markdown & Math Renderer Library -->
<script src="<?= asset('js/markdown-renderer.js') ?>"></script>
<script src="https://cdn.jsdelivr.net/npm/marked/marked.min.js"></script>

<script>
const GuruAiChat = {
    csrfToken: '<?= csrf_token() ?>',
    baseUrl: '<?= base_url() ?>',
    isSending: false,

    init() {
        // Parse all existing message markdown on load
        document.querySelectorAll('.chat-markdown-body[data-raw-message]').forEach(el => {
            const raw = el.getAttribute('data-raw-message');
            if (raw) {
                el.innerHTML = MarkdownRenderer.render(raw);
            }
        });
        this.scrollToBottom();
    },

    handleKeyDown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this.handleSubmit(event);
        }
        this.updateCharCount();
    },

    updateCharCount() {
        const input = document.getElementById('guruChatMessage');
        const counter = document.getElementById('charCounter');
        if (input && counter) {
            counter.innerText = `${input.value.length} / 3000`;
        }
    },

    insertPrompt(text) {
        const input = document.getElementById('guruChatMessage');
        if (input) {
            input.value = text;
            input.focus();
            this.updateCharCount();
        }
    },

    scrollToBottom() {
        const box = document.getElementById('chatMessagesBox');
        if (box) {
            setTimeout(() => {
                box.scrollTop = box.scrollHeight;
            }, 50);
        }
    },

    async handleSubmit(event) {
        if (event) event.preventDefault();
        if (this.isSending) return;

        const input = document.getElementById('guruChatMessage');
        const message = input ? input.value.trim() : '';
        if (!message) return;

        const sessionUuid = document.getElementById('currentSessionUuid')?.value || '';
        const messagesBox = document.getElementById('chatMessagesBox');
        const typingIndicator = document.getElementById('aiTypingIndicator');
        const sendBtn = document.getElementById('btnSendChat');

        // 1. Render User Message to Screen Immediately
        const nowTime = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        const userHtml = `
            <div class="d-flex justify-content-end align-items-start gap-2.5 mb-4 user-message-row">
                <div class="chat-bubble-user p-3 shadow-2xs" style="max-width: 82%;">
                    <div class="fs-sm lh-base text-white" style="white-space: pre-wrap;">${MarkdownRenderer.escapeHtml(message)}</div>
                    <div class="text-end text-white-50 fs-3xs mt-1.5">${nowTime}</div>
                </div>
                <div class="user-avatar-icon flex-shrink-0 d-none d-sm-flex">
                    <i class="bi bi-person-fill fs-6"></i>
                </div>
            </div>
        `;
        typingIndicator.insertAdjacentHTML('beforebegin', userHtml);
        input.value = '';
        this.updateCharCount();

        // 2. Set Loading State
        this.isSending = true;
        if (sendBtn) sendBtn.disabled = true;
        typingIndicator.classList.remove('d-none');
        typingIndicator.classList.add('d-flex');
        this.scrollToBottom();

        try {
            const response = await fetch(`${this.baseUrl}/guru/ai-chat/send`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({
                    message: message,
                    session_uuid: sessionUuid
                })
            });

            const data = await response.json();

            // Sembunyikan loading state
            typingIndicator.classList.add('d-none');
            typingIndicator.classList.remove('d-flex');

            if (data.success && data.reply) {
                if (data.session_uuid && document.getElementById('currentSessionUuid')) {
                    document.getElementById('currentSessionUuid').value = data.session_uuid;
                }

                // Render Rich Formatted Markdown Response
                const renderedContent = MarkdownRenderer.render(data.reply);
                const modelLabel = MarkdownRenderer.escapeHtml(data.model || 'Gemini 3.5 Flash');

                const aiHtml = `
                    <div class="d-flex gap-3 mb-4 ai-message-row">
                        <div class="ai-avatar-icon flex-shrink-0">
                            <i class="bi bi-robot fs-5"></i>
                        </div>
                        <div class="ai-message-card p-3.5 text-dark w-100" style="max-width: 88%;">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1.5 border-bottom border-light">
                                <div class="fw-bold fs-xs text-primary d-flex align-items-center gap-1.5">
                                    <i class="bi bi-stars text-warning"></i>
                                    <span>Asisten AI Guru</span>
                                    <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-3xs rounded-pill">${modelLabel}</span>
                                </div>
                                <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none fs-3xs" onclick="MarkdownRenderer.copyMessage(this)" title="Salin Jawaban">
                                    <i class="bi bi-clipboard me-1"></i>Salin
                                </button>
                            </div>
                            <div class="chat-markdown-body">
                                ${renderedContent}
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2.5 pt-1.5 border-top border-light fs-3xs text-muted">
                                <span><i class="bi bi-check2-all text-primary me-1"></i>Terverifikasi Gemini AI</span>
                                <span>${nowTime}</span>
                            </div>
                        </div>
                    </div>
                `;
                typingIndicator.insertAdjacentHTML('beforebegin', aiHtml);
            } else {
                const errorMsg = data.error || 'Terjadi kesalahan saat memproses respon AI.';
                const errHtml = `
                    <div class="d-flex gap-3 mb-4 ai-message-row">
                        <div class="ai-avatar-icon flex-shrink-0 bg-danger text-white">
                            <i class="bi bi-exclamation-triangle fs-5"></i>
                        </div>
                        <div class="ai-message-card p-3.5 text-danger border-danger-subtle bg-danger-subtle w-100" style="max-width: 88%;">
                            <div class="fw-bold fs-xs text-danger mb-1.5 d-flex align-items-center gap-1.5">
                                <i class="bi bi-x-circle-fill"></i> Gagal Mendapatkan Jawaban
                            </div>
                            <div class="fs-sm lh-base">${MarkdownRenderer.escapeHtml(errorMsg)}</div>
                            <button type="button" class="btn btn-sm btn-outline-danger mt-2.5 rounded-pill px-3 fs-3xs" onclick="GuruAiChat.retryMessage('${MarkdownRenderer.escapeHtml(message)}')">
                                <i class="bi bi-arrow-clockwise me-1"></i> Coba Lagi
                            </button>
                        </div>
                    </div>
                `;
                typingIndicator.insertAdjacentHTML('beforebegin', errHtml);
            }
        } catch (err) {
            typingIndicator.classList.add('d-none');
            typingIndicator.classList.remove('d-flex');

            const netErrHtml = `
                <div class="d-flex gap-3 mb-4 ai-message-row">
                    <div class="ai-avatar-icon flex-shrink-0 bg-danger text-white">
                        <i class="bi bi-wifi-off fs-5"></i>
                    </div>
                    <div class="ai-message-card p-3.5 text-danger border-danger-subtle bg-danger-subtle w-100" style="max-width: 88%;">
                        <div class="fw-bold fs-xs text-danger mb-1.5">Koneksi Terputus</div>
                        <div class="fs-sm lh-base">Tidak dapat terhubung ke server. Periksa koneksi internet Anda.</div>
                    </div>
                </div>
            `;
            typingIndicator.insertAdjacentHTML('beforebegin', netErrHtml);
        } finally {
            this.isSending = false;
            if (sendBtn) sendBtn.disabled = false;
            this.scrollToBottom();
            if (input) input.focus();
        }
    },

    retryMessage(text) {
        this.insertPrompt(text);
        this.handleSubmit();
    },

    async clearChat() {
        if (!confirm('Apakah Bapak/Ibu yakin ingin membersihkan riwayat percakapan sesi ini?')) return;

        const sessionUuid = document.getElementById('currentSessionUuid')?.value || '';
        try {
            const res = await fetch(`${this.baseUrl}/guru/ai-chat/clear`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken
                },
                body: JSON.stringify({ session_uuid: sessionUuid })
            });
            const data = await res.json();
            if (data.success) {
                window.location.reload();
            }
        } catch (e) {
            alert('Gagal membersihkan chat.');
        }
    },

    newSession() {
        window.location.href = `${this.baseUrl}/guru/ai-chat`;
    }
};

document.addEventListener('DOMContentLoaded', () => {
    GuruAiChat.init();
});
</script>
