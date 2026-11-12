<div class="container-fluid py-2" id="adminAiChatApp">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-3 gap-2">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 fs-sm">
                    <li class="breadcrumb-item"><a href="<?= base_url('admin/dashboard') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">AI Operations & Intelligence</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-robot text-primary"></i> Asisten AI Administrator & Analitik Sistem
            </h4>
            <span class="text-muted fs-xs">Asisten cerdas berbasis data real-time untuk audit keamanan proctoring, analitik ujian, dan operasional akademik.</span>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-2 rounded-pill fs-xs">
                <i class="bi bi-database-check me-1"></i> Database RAG Integrated
            </span>
            <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3 shadow-2xs" onclick="AdminAiChat.clearChat()">
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
                    <span class="fw-bold text-dark fs-sm"><i class="bi bi-clock-history me-1"></i> Riwayat Sesi Admin</span>
                    <button type="button" class="btn btn-sm btn-primary-gradient rounded-pill px-2.5 py-1 fs-xs" onclick="AdminAiChat.newSession()">
                        <i class="bi bi-plus-lg me-1"></i> Baru
                    </button>
                </div>
                <div class="card-body p-2 overflow-auto" style="max-height: calc(100vh - 240px);" id="sessionsListContainer">
                    <?php if (empty($recentSessions)): ?>
                        <div class="text-center py-4 text-muted fs-xs">
                            <i class="bi bi-chat-square-dots fs-3 d-block mb-2 text-secondary opacity-50"></i>
                            Belum ada riwayat sesi admin.
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush gap-1">
                            <?php foreach ($recentSessions as $s): ?>
                                <?php $isActive = ($s['session_uuid'] === ($activeSession['session_uuid'] ?? '')); ?>
                                <a href="<?= base_url('admin/ai-chat?session_uuid=' . $s['session_uuid']) ?>" class="list-group-item list-group-item-action rounded-3 border-0 py-2 px-2.5 <?= $isActive ? 'bg-primary-subtle text-primary fw-semibold' : 'text-dark' ?>">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <span class="fs-xs text-truncate" style="max-width: 170px;"><?= e($s['title'] ?? 'Percakapan Admin') ?></span>
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
                            <i class="bi bi-shield-lock-fill fs-5"></i>
                        </div>
                        <div>
                            <h6 class="fw-bold mb-0 text-dark" id="chatSessionTitle"><?= e($activeSession['title'] ?? 'Konsultasi Analitik & Operasional') ?></h6>
                            <span class="text-success fs-3xs d-flex align-items-center gap-1">
                                <span class="p-1 bg-success rounded-circle d-inline-block"></span> Mode Administrator &bull; Data Terintegrasi Database &bull; Gemini AI
                            </span>
                        </div>
                    </div>
                    <div class="d-flex gap-1.5">
                        <a href="<?= base_url('admin/ai-configuration') ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fs-xs shadow-2xs">
                            <i class="bi bi-gear-fill me-1"></i> Konfigurasi AI
                        </a>
                    </div>
                </div>

                <!-- Messages Stream Box -->
                <div class="card-body p-3 p-md-4 flex-grow-1 overflow-auto bg-body-tertiary" id="chatMessagesBox" style="max-height: calc(100vh - 350px); min-height: 400px;">
                    <!-- Welcome Initial Bubble -->
                    <div class="d-flex gap-3 mb-4 ai-message-row">
                        <div class="ai-avatar-icon flex-shrink-0">
                            <i class="bi bi-shield-lock-fill fs-5"></i>
                        </div>
                        <div class="ai-message-card p-3.5 text-dark w-100" style="max-width: 88%;">
                            <div class="d-flex justify-content-between align-items-center mb-2 pb-1.5 border-bottom border-light">
                                <div class="fw-bold fs-xs text-primary d-flex align-items-center gap-1.5">
                                    <i class="bi bi-stars text-warning"></i>
                                    <span>Asisten AI Administrator</span>
                                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-3xs rounded-pill">Sistem & Operasional</span>
                                </div>
                                <span class="fs-3xs text-muted">Online</span>
                            </div>
                            <div class="chat-markdown-body">
                                <p class="mb-2">Halo Administrator! 👋 Saya terhubung langsung ke database sistem untuk memberikan intelijen operasional real-time:</p>
                                <ul class="ai-styled-list mb-2">
                                    <li>Ringkasan status akademik (Tahun Ajaran, Semester, Rombel, Mata Pelajaran)</li>
                                    <li>Statistik pengguna aktif (Siswa, Guru, Administrator)</li>
                                    <li>Live status ujian berjalan, token ujian, dan sesi peserta</li>
                                    <li>Laporan intelijen pelanggaran <strong>AI Proctoring Dual Kamera</strong> dan skor integritas</li>
                                    <li>Rekomendasi konfigurasi parameter sistem & audit keamanan</li>
                                </ul>
                                <p class="mb-0 text-muted fs-xs">Silakan ajukan pertanyaan atau klik saran topik cepat di bawah untuk analisis instan.</p>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-2.5 pt-1.5 border-top border-light fs-3xs text-muted">
                                <span>Platform Evaluasi Akademik Digital</span>
                                <span>Smart Exam AI Engine &bull; Database Connected</span>
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
                                        <i class="bi bi-shield-lock-fill fs-5"></i>
                                    </div>
                                    <div class="ai-message-card p-3.5 text-dark w-100 <?= !empty($msg['is_error']) ? 'border-danger-subtle bg-danger-subtle' : '' ?>" style="max-width: 88%;">
                                        <div class="d-flex justify-content-between align-items-center mb-2 pb-1.5 border-bottom border-light">
                                            <div class="fw-bold fs-xs <?= !empty($msg['is_error']) ? 'text-danger' : 'text-primary' ?> d-flex align-items-center gap-1.5">
                                                <i class="bi bi-stars text-warning"></i>
                                                <span>Asisten AI Administrator</span>
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
                            <i class="bi bi-shield-lock-fill fs-5"></i>
                        </div>
                        <div class="bg-white border border-secondary-subtle rounded-4 py-2.5 px-4 text-muted shadow-2xs d-inline-flex align-items-center gap-2">
                            <span class="spinner-grow spinner-grow-sm text-primary" role="status" aria-hidden="true" style="width: 8px; height: 8px;"></span>
                            <span class="spinner-grow spinner-grow-sm text-info" role="status" aria-hidden="true" style="width: 8px; height: 8px; animation-delay: 0.15s;"></span>
                            <span class="spinner-grow spinner-grow-sm text-success" role="status" aria-hidden="true" style="width: 8px; height: 8px; animation-delay: 0.3s;"></span>
                            <span class="fs-xs ms-1.5 text-secondary fw-medium">Sedang memproses analitik data sistem...</span>
                        </div>
                    </div>
                </div>

                <!-- Quick Prompt Suggestions Chips -->
                <div class="px-4 py-2 bg-white border-top border-light d-flex gap-2 overflow-auto text-nowrap" id="quickPromptChips">
                    <span class="fs-3xs text-uppercase fw-bold text-muted d-flex align-items-center gap-1">
                        <i class="bi bi-lightbulb-fill text-warning"></i> Topik:
                    </span>
                    <button type="button" class="quick-prompt-chip" onclick="AdminAiChat.insertPrompt('Tampilkan ringkasan status akademik, total kelas, mata pelajaran, dan statistik pengguna terdaftar saat ini.')">
                        📊 Ringkasan Data Master
                    </button>
                    <button type="button" class="quick-prompt-chip" onclick="AdminAiChat.insertPrompt('Bagaimana status operasional ujian hari ini dan berapa sesi peserta yang sedang aktif?')">
                        ⚡ Status Operasional Ujian
                    </button>
                    <button type="button" class="quick-prompt-chip" onclick="AdminAiChat.insertPrompt('Tampilkan rekapitulasi pelanggaran AI Proctoring Dual Kamera dan rekomendasi mitigasi integritas.')">
                        🛡️ Audit AI Proctoring
                    </button>
                    <button type="button" class="quick-prompt-chip" onclick="AdminAiChat.insertPrompt('Berikan analisis parameter konfigurasi AI aktif saat ini dan saran optimasi.')">
                        ⚙️ Analisis Konfigurasi AI
                    </button>
                </div>

                <!-- Input Message Box -->
                <div class="card-footer bg-white border-top border-light p-3">
                    <form id="adminChatForm" onsubmit="AdminAiChat.handleSubmit(event)">
                        <input type="hidden" id="currentSessionUuid" value="<?= e($activeSession['session_uuid'] ?? '') ?>">
                        <div class="input-group shadow-2xs rounded-4 overflow-hidden border">
                            <textarea 
                                class="form-control border-0 py-2.5 px-3 fs-sm shadow-none" 
                                id="adminChatMessage" 
                                rows="1" 
                                placeholder="Tanyakan analitik sistem, statistik pengguna, audit AI proctoring, atau operasional ujian..." 
                                required
                                onkeydown="AdminAiChat.handleKeyDown(event)"
                            ></textarea>
                            <button class="btn btn-primary-gradient px-4 d-flex align-items-center justify-content-center" type="submit" id="btnSendChat">
                                <i class="bi bi-send-fill fs-6 me-1.5"></i>
                                <span class="d-none d-sm-inline fw-semibold">Kirim</span>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2 px-1">
                            <span class="fs-3xs text-muted">
                                <i class="bi bi-shield-check text-success me-1"></i> Asisten Operasional Admin terhubung database &bull; Gemini 3.5 Flash Model
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
const AdminAiChat = {
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

        const input = document.getElementById('adminChatMessage');
        if (input) {
            input.addEventListener('input', () => {
                const len = input.value.length;
                const counter = document.getElementById('charCounter');
                if (counter) counter.innerText = `${len} / 3000`;
                
                // Auto-resize textarea
                input.style.height = 'auto';
                input.style.height = Math.min(input.scrollHeight, 120) + 'px';
            });
        }
    },

    handleKeyDown(event) {
        if (event.key === 'Enter' && !event.shiftKey) {
            event.preventDefault();
            this.handleSubmit(event);
        }
    },

    insertPrompt(text) {
        const input = document.getElementById('adminChatMessage');
        if (!input) return;
        input.value = text;
        input.focus();
        input.dispatchEvent(new Event('input'));
    },

    scrollToBottom() {
        const box = document.getElementById('chatMessagesBox');
        if (box) {
            box.scrollTop = box.scrollHeight;
        }
    },

    async handleSubmit(event) {
        if (event) event.preventDefault();
        if (this.isSending) return;

        const input = document.getElementById('adminChatMessage');
        const sessionUuidInput = document.getElementById('currentSessionUuid');
        const message = input ? input.value.trim() : '';

        if (!message) return;

        this.isSending = true;
        this.toggleSubmitButton(true);

        // 1. Tampilkan pesan user di UI
        this.appendUserMessage(message);
        input.value = '';
        input.style.height = 'auto';
        document.getElementById('charCounter').innerText = '0 / 3000';
        this.showTyping(true);
        this.scrollToBottom();

        try {
            const response = await fetch(`${this.baseUrl}admin/ai-chat/send`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    message: message,
                    session_uuid: sessionUuidInput ? sessionUuidInput.value : null
                })
            });

            const data = await response.json();

            this.showTyping(false);

            if (data.success) {
                if (data.session_uuid && sessionUuidInput) {
                    sessionUuidInput.value = data.session_uuid;
                }
                this.appendAiMessage(data.reply, data.model, false);
            } else {
                const errMsg = data.error || 'Terjadi kesalahan saat memproses jawaban dari AI.';
                this.appendAiMessage(errMsg, null, true);
            }
        } catch (err) {
            this.showTyping(false);
            this.appendAiMessage('Gagal menghubungi server. Periksa koneksi internet Anda.', null, true);
        } finally {
            this.isSending = false;
            this.toggleSubmitButton(false);
            this.scrollToBottom();
        }
    },

    appendUserMessage(message) {
        const box = document.getElementById('chatMessagesBox');
        const indicator = document.getElementById('aiTypingIndicator');
        const now = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});

        const div = document.createElement('div');
        div.className = 'd-flex justify-content-end align-items-start gap-2.5 mb-4 user-message-row';
        div.innerHTML = `
            <div class="chat-bubble-user p-3 shadow-2xs" style="max-width: 82%;">
                <div class="fs-sm lh-base text-white" style="white-space: pre-wrap;">${this.escapeHtml(message)}</div>
                <div class="text-end text-white-50 fs-3xs mt-1.5">${now}</div>
            </div>
            <div class="user-avatar-icon flex-shrink-0 d-none d-sm-flex">
                <i class="bi bi-person-fill fs-6"></i>
            </div>
        `;

        if (indicator) {
            box.insertBefore(div, indicator);
        } else {
            box.appendChild(div);
        }
    },

    appendAiMessage(markdownContent, modelUsed, isError = false) {
        const box = document.getElementById('chatMessagesBox');
        const indicator = document.getElementById('aiTypingIndicator');
        const now = new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
        const renderedHtml = MarkdownRenderer.render(markdownContent);

        const modelBadge = modelUsed ? `<span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle fs-3xs rounded-pill">${this.escapeHtml(modelUsed)}</span>` : '';
        const cardClass = isError ? 'border-danger-subtle bg-danger-subtle' : '';
        const titleClass = isError ? 'text-danger' : 'text-primary';

        const div = document.createElement('div');
        div.className = 'd-flex gap-3 mb-4 ai-message-row';
        div.innerHTML = `
            <div class="ai-avatar-icon flex-shrink-0">
                <i class="bi bi-shield-lock-fill fs-5"></i>
            </div>
            <div class="ai-message-card p-3.5 text-dark w-100 ${cardClass}" style="max-width: 88%;">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-1.5 border-bottom border-light">
                    <div class="fw-bold fs-xs ${titleClass} d-flex align-items-center gap-1.5">
                        <i class="bi bi-stars text-warning"></i>
                        <span>Asisten AI Administrator</span>
                        ${modelBadge}
                    </div>
                    <button type="button" class="btn btn-sm btn-link text-muted p-0 text-decoration-none fs-3xs" onclick="MarkdownRenderer.copyMessage(this)" title="Salin Jawaban">
                        <i class="bi bi-clipboard me-1"></i>Salin
                    </button>
                </div>
                <div class="chat-markdown-body" data-raw-message="${this.escapeHtml(markdownContent)}">
                    ${renderedHtml}
                </div>
                <div class="d-flex justify-content-between align-items-center mt-2.5 pt-1.5 border-top border-light fs-3xs text-muted">
                    <span><i class="bi bi-check2-all text-primary me-1"></i>Terverifikasi Gemini AI</span>
                    <span>${now}</span>
                </div>
            </div>
        `;

        if (indicator) {
            box.insertBefore(div, indicator);
        } else {
            box.appendChild(div);
        }
    },

    showTyping(show) {
        const indicator = document.getElementById('aiTypingIndicator');
        if (!indicator) return;
        if (show) {
            indicator.classList.remove('d-none');
            indicator.classList.add('d-flex');
        } else {
            indicator.classList.add('d-none');
            indicator.classList.remove('d-flex');
        }
    },

    toggleSubmitButton(disabled) {
        const btn = document.getElementById('btnSendChat');
        if (!btn) return;
        btn.disabled = disabled;
        btn.innerHTML = disabled 
            ? `<span class="spinner-border spinner-border-sm me-1.5" role="status"></span><span class="d-none d-sm-inline">Memproses...</span>`
            : `<i class="bi bi-send-fill fs-6 me-1.5"></i><span class="d-none d-sm-inline fw-semibold">Kirim</span>`;
    },

    async newSession() {
        const sessionUuidInput = document.getElementById('currentSessionUuid');
        try {
            const res = await fetch(`${this.baseUrl}admin/ai-chat/clear`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ session_uuid: sessionUuidInput ? sessionUuidInput.value : null })
            });
            window.location.href = `${this.baseUrl}admin/ai-chat`;
        } catch (e) {
            window.location.reload();
        }
    },

    async clearChat() {
        if (!confirm('Apakah Anda yakin ingin menghapus seluruh riwayat percakapan pada sesi ini?')) {
            return;
        }
        await this.newSession();
    },

    escapeHtml(str) {
        if (!str) return '';
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return String(str).replace(/[&<>"']/g, m => map[m]);
    }
};

document.addEventListener('DOMContentLoaded', () => {
    AdminAiChat.init();
});
</script>

<style>
/* Custom Styled Badges & Message Cards for AI Chat */
.ai-avatar-icon {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 4px 10px rgba(99, 102, 241, 0.25);
}

.user-avatar-icon {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    color: #fff;
    display: flex;
    align-items: center;
    justify-content: center;
}

.ai-message-card {
    background-color: var(--vui-bg-card, #ffffff);
    border: 1px solid var(--vui-border, #e2e8f0);
    border-radius: 20px 20px 20px 4px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    color: var(--vui-text-body, #334155);
}

.chat-bubble-user {
    background: linear-gradient(135deg, #6366f1 0%, #4f46e5 100%);
    border-radius: 20px 20px 4px 20px;
    box-shadow: 0 4px 14px rgba(99, 102, 241, 0.25);
}

.quick-prompt-chip {
    border: 1px solid var(--vui-border, #dee2e6);
    background-color: var(--vui-bg-body, #f8f9fa);
    color: var(--vui-text-muted, #495057);
    font-size: 0.775rem;
    font-weight: 500;
    padding: 5px 12px;
    border-radius: 20px;
    cursor: pointer;
    transition: all 0.2s ease;
}

.quick-prompt-chip:hover {
    background-color: var(--vui-border-light, #e9ecef);
    border-color: #6366f1;
    color: #6366f1;
    transform: translateY(-1px);
}

.ai-styled-list {
    padding-left: 1.25rem;
    font-size: 0.875rem;
}

.ai-styled-list li {
    margin-bottom: 0.35rem;
}
</style>
