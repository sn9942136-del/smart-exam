/**
 * EduAI Chatbot Controller (Global Floating Assistant & Post-Exam Question Explainer)
 * Integrated with Google Gemini Provider & Strict Security Guardrails
 */

(function () {
    'use strict';

    // =========================================================================
    // 1. GLOBAL FLOATING CHATBOT CONTROLLER
    // =========================================================================

    const FloatingChat = {
        wrapper: null,
        toggleBtn: null,
        windowEl: null,
        messagesEl: null,
        inputEl: null,
        formEl: null,
        typingEl: null,
        clearBtn: null,
        minimizeBtn: null,
        infoBtn: null,
        infoBanner: null,
        closeInfoBtn: null,
        rolePrefix: 'siswa',
        baseUrl: '',
        csrfToken: '',
        sessionUuid: null,
        isProcessing: false,
        _initialized: false,
        _lastToggle: 0,

        init() {
            this.wrapper = document.getElementById('eduFloatingAiChat');
            if (!this.wrapper) return;
            if (this._initialized) return;
            this._initialized = true;

            this.rolePrefix = this.wrapper.dataset.role || 'siswa';
            this.baseUrl = this.wrapper.dataset.baseUrl || '';
            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            this.csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            this.toggleBtn = document.getElementById('floatingChatToggle');
            this.windowEl = document.getElementById('floatingChatWindow');
            this.messagesEl = document.getElementById('floatingChatMessages');
            this.inputEl = document.getElementById('floatingChatInput');
            this.formEl = document.getElementById('floatingChatForm');
            this.typingEl = document.getElementById('floatingChatTyping');
            this.clearBtn = document.getElementById('btnFloatingChatClear');
            this.minimizeBtn = document.getElementById('btnFloatingChatMinimize');
            this.infoBtn = document.getElementById('btnFloatingChatInfo');
            this.infoBanner = document.getElementById('floatingChatInfoBanner');
            this.closeInfoBtn = document.getElementById('btnCloseInfoBanner');

            this.bindEvents();
            this.loadStoredHistory();
        },

        bindEvents() {
            if (this.toggleBtn) {
                this.toggleBtn.onclick = (e) => this.toggleWindow(e);
            }

            if (this.minimizeBtn) {
                this.minimizeBtn.onclick = (e) => {
                    if (e) e.preventDefault();
                    this.closeWindow();
                };
            }

            const closeBtn = document.getElementById('btnFloatingChatClose');
            if (closeBtn) {
                closeBtn.onclick = (e) => {
                    if (e) e.preventDefault();
                    this.closeWindow();
                };
            }

            if (this.infoBtn && this.infoBanner) {
                this.infoBtn.onclick = (e) => {
                    if (e) e.preventDefault();
                    this.infoBanner.classList.toggle('d-none');
                };
            }

            if (this.closeInfoBtn && this.infoBanner) {
                this.closeInfoBtn.onclick = (e) => {
                    if (e) e.preventDefault();
                    this.infoBanner.classList.add('d-none');
                };
            }

            if (this.clearBtn) {
                this.clearBtn.onclick = (e) => {
                    if (e) e.preventDefault();
                    this.clearHistory();
                };
            }

            if (this.formEl) {
                this.formEl.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleSendMessage();
                });
            }

            if (this.inputEl) {
                this.inputEl.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.handleSendMessage();
                    }
                });

                // Auto resize textarea height without unsightly scrollbars
                this.inputEl.addEventListener('input', () => {
                    this.inputEl.style.height = '38px';
                    const newHeight = Math.min(this.inputEl.scrollHeight, 80);
                    this.inputEl.style.height = Math.max(newHeight, 38) + 'px';
                    this.inputEl.style.overflowY = (this.inputEl.scrollHeight > 80) ? 'auto' : 'hidden';
                });
            }

            // Quick Prompt Chips
            const chips = this.wrapper.querySelectorAll('.btn-quick-chip');
            chips.forEach(chip => {
                chip.onclick = (e) => {
                    if (e) e.preventDefault();
                    const prompt = chip.getAttribute('data-prompt');
                    if (prompt && this.inputEl) {
                        this.inputEl.value = prompt;
                        this.handleSendMessage();
                    }
                };
            });
        },

        toggleWindow(e) {
            if (e) {
                if (typeof e.preventDefault === 'function') e.preventDefault();
                if (typeof e.stopPropagation === 'function') e.stopPropagation();
            }
            const now = Date.now();
            if (this._lastToggle && (now - this._lastToggle < 300)) {
                return; // Debounce rapid double-click
            }
            this._lastToggle = now;

            if (!this.windowEl) {
                this.windowEl = document.getElementById('floatingChatWindow');
            }
            if (!this.windowEl) return;

            const isHidden = this.windowEl.classList.contains('d-none') || 
                             this.windowEl.style.display === 'none' ||
                             (window.getComputedStyle && window.getComputedStyle(this.windowEl).display === 'none');

            if (isHidden) {
                this.openWindow();
            } else {
                this.closeWindow();
            }
        },

        openWindow() {
            if (!this.windowEl) {
                this.windowEl = document.getElementById('floatingChatWindow');
            }
            if (!this.windowEl) return;

            this.windowEl.classList.remove('d-none');
            this.windowEl.style.display = 'flex';

            if (!this.toggleBtn) {
                this.toggleBtn = document.getElementById('floatingChatToggle');
            }
            if (this.toggleBtn) {
                const iconOpen = this.toggleBtn.querySelector('.icon-open');
                const iconClose = this.toggleBtn.querySelector('.icon-close');
                if (iconOpen) {
                    iconOpen.classList.add('d-none');
                    iconOpen.style.display = 'none';
                }
                if (iconClose) {
                    iconClose.classList.remove('d-none');
                    iconClose.style.display = 'flex';
                }
            }
            this.scrollToBottom();
            if (this.inputEl) {
                setTimeout(() => this.inputEl.focus(), 150);
            }
        },

        closeWindow() {
            if (!this.windowEl) {
                this.windowEl = document.getElementById('floatingChatWindow');
            }
            if (!this.windowEl) return;

            this.windowEl.classList.add('d-none');
            this.windowEl.style.display = 'none';

            if (!this.toggleBtn) {
                this.toggleBtn = document.getElementById('floatingChatToggle');
            }
            if (this.toggleBtn) {
                const iconOpen = this.toggleBtn.querySelector('.icon-open');
                const iconClose = this.toggleBtn.querySelector('.icon-close');
                if (iconOpen) {
                    iconOpen.classList.remove('d-none');
                    iconOpen.style.display = 'flex';
                }
                if (iconClose) {
                    iconClose.classList.add('d-none');
                    iconClose.style.display = 'none';
                }
            }
        },

        async loadStoredHistory() {
            try {
                const endpoint = `${this.baseUrl}/${this.rolePrefix}/ai-chat/history`;
                const resp = await fetch(endpoint, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!resp.ok) return;
                const data = await resp.json();

                if (data.success && data.messages && data.messages.length > 0) {
                    this.sessionUuid = data.session ? data.session.session_uuid : null;
                    // Render historical messages
                    this.renderMessageList(data.messages);
                }
            } catch (err) {
                console.warn('[FloatingChat] Could not load initial history:', err);
            }
        },

        renderMessageList(messages) {
            // Keep the first welcome message if empty
            if (!messages || messages.length === 0) return;

            this.messagesEl.innerHTML = '';
            messages.forEach(msg => {
                this.appendMessage(msg.sender, msg.message, msg.created_at, msg.model_used);
            });
            this.scrollToBottom();
        },

        async handleSendMessage() {
            if (this.isProcessing) return;

            const text = this.inputEl.value.trim();
            if (!text) return;

            // Clear input & reset height cleanly
            this.inputEl.value = '';
            this.inputEl.style.height = '44px';
            this.inputEl.style.overflowY = 'hidden';

            // Append user bubble
            this.appendMessage('user', text, new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));
            this.scrollToBottom();

            this.setTyping(true);
            this.isProcessing = true;

            try {
                const endpoint = `${this.baseUrl}/${this.rolePrefix}/ai-chat/send`;
                const payload = {
                    message: text,
                    session_uuid: this.sessionUuid
                };

                const resp = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await resp.json();
                this.setTyping(false);
                this.isProcessing = false;

                if (data.success && data.reply) {
                    this.sessionUuid = data.session_uuid || this.sessionUuid;
                    this.appendMessage('assistant', data.reply, new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }), data.model);
                    this.playSoftChime();
                } else {
                    const errorMsg = data.error || 'Terjadi kesalahan saat memproses jawaban AI.';
                    this.appendMessage('assistant', `⚠️ **Kendala Sistem:** ${errorMsg}`, null, 'error');
                }
            } catch (err) {
                this.setTyping(false);
                this.isProcessing = false;
                this.appendMessage('assistant', '⚠️ Gagal terhubung ke server. Pastikan koneksi internet Anda stabil.', null, 'error');
            }

            this.scrollToBottom();
        },

        formatTimeline(timeStr) {
            if (!timeStr) {
                return new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            }
            const s = String(timeStr).trim();
            if (s.toLowerCase() === 'baru saja') {
                return new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
            }
            if (/AM|PM/i.test(s)) {
                return s;
            }
            // Check short time HH:mm or HH.mm
            const shortMatch = s.match(/^(\d{1,2})[:.](\d{2})$/);
            if (shortMatch) {
                let h = parseInt(shortMatch[1], 10);
                const m = shortMatch[2];
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                return `${h}:${m} ${ampm}`;
            }
            // Check ISO or MySQL datetime YYYY-MM-DD HH:mm:ss
            const fullMatch = s.match(/(?:T|\s)(\d{1,2}):(\d{2})(?::\d{2})?/);
            if (fullMatch) {
                let h = parseInt(fullMatch[1], 10);
                const m = fullMatch[2];
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                return `${h}:${m} ${ampm}`;
            }
            try {
                const d = new Date(s);
                if (!isNaN(d.getTime())) {
                    return d.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                }
            } catch (e) {}
            return s;
        },

        appendMessage(sender, rawText, timeStr, modelUsed) {
            const isUser = (sender === 'user');
            const row = document.createElement('div');
            row.className = `ai-msg-row d-flex gap-2.5 align-items-start ${isUser ? 'justify-content-end' : ''}`;

            const formattedTime = this.formatTimeline(timeStr);

            let renderedContent = '';
            if (isUser) {
                renderedContent = `<div class="user-msg-bubble fs-xs">${this.escapeHtml(rawText)}</div>`;
                row.innerHTML = `
                    <div class="ai-msg-bubble-wrapper max-w-85 text-end">
                        ${renderedContent}
                        <div class="chat-time-label mt-1 px-1 d-flex justify-content-end align-items-center gap-1">
                            <span>${formattedTime}</span>
                            <i class="bi bi-check2-all text-indigo-500"></i>
                        </div>
                    </div>
                `;
            } else {
                // Render rich markdown
                if (window.MarkdownRenderer) {
                    renderedContent = window.MarkdownRenderer.render(rawText);
                } else {
                    renderedContent = this.escapeHtml(rawText).replace(/\n/g, '<br>');
                }

                const dbBadge = (modelUsed && modelUsed !== 'error')
                    ? `<span class="badge bg-slate-100 text-slate-600 border border-slate-200 fs-5xs py-0.5 px-1.5"><i class="bi bi-database-fill-check text-emerald-600 me-0.5"></i>Live DB</span>`
                    : '';

                row.innerHTML = `
                    <div class="ai-msg-avatar flex-shrink-0 rounded-3 text-white d-flex align-items-center justify-content-center shadow-3xs">
                        <i class="bi bi-robot fs-6"></i>
                    </div>
                    <div class="ai-msg-bubble-wrapper max-w-85">
                        <div class="ai-msg-bubble shadow-3xs fs-xs">
                            <div class="chat-markdown-body">${renderedContent}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1 px-1 fs-4xs text-muted">
                            <span class="chat-time-label">${formattedTime}</span>
                            ${dbBadge ? `<span>&bull;</span>${dbBadge}` : ''}
                            <span>&bull;</span>
                            <button type="button" class="btn btn-link p-0 text-decoration-none fs-4xs text-muted btn-copy-chat" onclick="window.MarkdownRenderer ? window.MarkdownRenderer.copyMessage(this) : null">
                                <i class="bi bi-clipboard me-0.5"></i>Salin
                            </button>
                        </div>
                    </div>
                `;
            }

            this.messagesEl.appendChild(row);
        },

        setTyping(isTyping) {
            if (!this.typingEl) return;
            if (isTyping) {
                this.typingEl.classList.remove('d-none');
                this.typingEl.classList.add('d-flex');
            } else {
                this.typingEl.classList.add('d-none');
                this.typingEl.classList.remove('d-flex');
            }
        },

        async clearHistory() {
            if (!confirm('Apakah Anda yakin ingin memulai percakapan baru? Riwayat sesi ini akan direset.')) {
                return;
            }

            try {
                const endpoint = `${this.baseUrl}/${this.rolePrefix}/ai-chat/clear`;
                const resp = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ session_uuid: this.sessionUuid })
                });

                const data = await resp.json();
                if (data.success) {
                    this.sessionUuid = data.session_uuid || null;
                    const nowFormatted = new Date().toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
                    this.messagesEl.innerHTML = `
                        <div class="ai-msg-row d-flex gap-2.5 align-items-start">
                            <div class="ai-msg-avatar flex-shrink-0 rounded-3 text-white d-flex align-items-center justify-content-center shadow-3xs">
                                <i class="bi bi-robot fs-6"></i>
                            </div>
                            <div class="ai-msg-bubble-wrapper max-w-85">
                                <div class="ai-msg-bubble shadow-3xs fs-xs">
                                    <div class="fw-semibold mb-1 text-heading fs-xs">Hello! 👋</div>
                                    <p class="mb-1.5 leading-relaxed">I'm your AI assistant. How can I help you today?</p>
                                    <p class="mb-0 fs-3xs text-muted">Percakapan baru telah dimulai. Silakan ajukan pertanyaan seputar ujian atau materi sekolah.</p>
                                </div>
                                <span class="chat-time-label mt-1 px-1 d-block">${nowFormatted}</span>
                            </div>
                        </div>
                    `;
                }
            } catch (err) {
                console.error('[FloatingChat] Clear failed:', err);
            }
        },

        scrollToBottom() {
            if (this.messagesEl) {
                this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
            }
        },

        playSoftChime() {
            try {
                const AudioCtx = window.AudioContext || window.webkitAudioContext;
                if (!AudioCtx) return;
                const ctx = new AudioCtx();
                const osc = ctx.createOscillator();
                const gain = ctx.createGain();

                osc.type = 'sine';
                osc.frequency.setValueAtTime(587.33, ctx.currentTime); // D5
                osc.frequency.exponentialRampToValueAtTime(880, ctx.currentTime + 0.15); // A5

                gain.gain.setValueAtTime(0.04, ctx.currentTime);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + 0.25);

                osc.connect(gain);
                gain.connect(ctx.destination);

                osc.start();
                osc.stop(ctx.currentTime + 0.25);
            } catch (e) {
                // Ignore audio play errors on strict browser policies
            }
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };


    // =========================================================================
    // 2. POST-EXAM QUESTION EXPLAINER MODAL CONTROLLER
    // =========================================================================

    const QuestionExplainer = {
        activeAnswerId: null,
        activeSessionUuid: null,
        modalEl: null,
        bsModal: null,
        messagesEl: null,
        inputEl: null,
        formEl: null,
        typingEl: null,
        contextPreviewEl: null,
        baseUrl: '',
        csrfToken: '',
        rolePrefix: 'siswa',
        isProcessing: false,

        init() {
            this.modalEl = document.getElementById('modalQuestionExplainer');
            if (!this.modalEl) return;

            this.rolePrefix = this.modalEl.dataset.role || 'siswa';

            const csrfMeta = document.querySelector('meta[name="csrf-token"]');
            this.csrfToken = csrfMeta ? csrfMeta.getAttribute('content') : '';

            const baseMeta = document.querySelector('meta[name="base-url"]');
            this.baseUrl = baseMeta ? baseMeta.getAttribute('content').replace(/\/$/, '') : '';

            this.messagesEl = document.getElementById('explainerChatMessages');
            this.inputEl = document.getElementById('explainerChatInput');
            this.formEl = document.getElementById('explainerChatForm');
            this.typingEl = document.getElementById('explainerChatTyping');
            this.contextPreviewEl = document.getElementById('explainerQuestionContext');

            if (window.bootstrap && window.bootstrap.Modal) {
                this.bsModal = new bootstrap.Modal(this.modalEl);
            }

            this.bindEvents();
        },

        bindEvents() {
            if (this.formEl) {
                this.formEl.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.handleSendMessage();
                });
            }

            if (this.inputEl) {
                this.inputEl.addEventListener('keydown', (e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        this.handleSendMessage();
                    }
                });
            }

            // Quick Chips inside Explainer Modal
            const chips = this.modalEl.querySelectorAll('.btn-explainer-chip');
            chips.forEach(chip => {
                chip.addEventListener('click', () => {
                    const prompt = chip.getAttribute('data-prompt');
                    if (prompt && this.inputEl) {
                        this.inputEl.value = prompt;
                        this.handleSendMessage();
                    }
                });
            });
        },

        async open(answerId, questionNum, questionContent, studentAnswer, correctAnswer, isCorrect) {
            this.activeAnswerId = answerId;
            this.activeSessionUuid = null;

            // Set Header and Question Context Box
            const qNumTitle = document.getElementById('explainerModalQuestionNumber');
            if (qNumTitle) qNumTitle.textContent = `Soal #${questionNum}`;

            if (this.contextPreviewEl) {
                const statusBadge = (isCorrect === 1) 
                    ? '<span class="badge bg-success-subtle text-success border border-success-subtle"><i class="bi bi-check-circle-fill me-1"></i>Jawaban Anda Tepat</span>'
                    : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle"><i class="bi bi-x-circle-fill me-1"></i>Jawaban Anda Kurang Tepat</span>';

                this.contextPreviewEl.innerHTML = `
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <span class="fw-bold text-dark fs-xs">Pratinjau Butir Soal:</span>
                        ${statusBadge}
                    </div>
                    <div class="text-secondary fs-xs mb-2 p-2 bg-white rounded border">${questionContent}</div>
                    <div class="row g-2 fs-3xs">
                        <div class="col-6">
                            <div class="p-2 rounded bg-white border">
                                <span class="text-muted d-block fw-semibold mb-0.5">Jawaban Anda:</span>
                                <span class="fw-bold ${isCorrect === 1 ? 'text-success' : 'text-danger'}">${studentAnswer || '(Kosong)'}</span>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="p-2 rounded bg-white border">
                                <span class="text-muted d-block fw-semibold mb-0.5">Kunci Jawaban Benar:</span>
                                <span class="fw-bold text-success">${correctAnswer || '-'}</span>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Clear previous messages and show loading placeholder
            this.messagesEl.innerHTML = `
                <div class="text-center py-4 text-muted fs-xs">
                    <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                    <div>Memuat riwayat pembahasan dan menyiapkan AI Gemini...</div>
                </div>
            `;

            if (this.bsModal) {
                this.bsModal.show();
            }

            // Load Question Chat History from Server
            await this.loadQuestionHistory(answerId);
        },

        async loadQuestionHistory(answerId) {
            try {
                const endpoint = `${this.baseUrl}/${this.rolePrefix}/ai-chat/question-history?answer_id=${answerId}`;
                const resp = await fetch(endpoint, {
                    headers: {
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await resp.json();
                this.messagesEl.innerHTML = '';

                if (data.success && data.messages && data.messages.length > 0) {
                    this.activeSessionUuid = data.session ? data.session.session_uuid : null;
                    data.messages.forEach(msg => {
                        this.appendMessage(msg.sender, msg.message, msg.created_at);
                    });
                } else {
                    // Auto-trigger initial explanation if first time opening
                    this.appendMessage('assistant', `👋 **Selamat datang di Mode Pembahasan Butir Soal.**\n\nSaya siap membantu Anda memahami konsep materi soal ini secara mendalam. Silakan pilih topik di bawah atau ketik pertanyaan spesifik yang ingin Anda ketahui!`, null);
                }

                this.scrollToBottom();
            } catch (err) {
                this.messagesEl.innerHTML = `<div class="p-3 text-danger fs-xs">Gagal memuat riwayat pembahasan soal.</div>`;
            }
        },

        async handleSendMessage() {
            if (this.isProcessing || !this.activeAnswerId) return;

            const text = this.inputEl.value.trim();
            if (!text) return;

            this.inputEl.value = '';

            // Render User Bubble
            this.appendMessage('user', text, new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));
            this.scrollToBottom();

            this.setTyping(true);
            this.isProcessing = true;

            try {
                const endpoint = `${this.baseUrl}/${this.rolePrefix}/ai-chat/question-explain`;
                const payload = {
                    answer_id: this.activeAnswerId,
                    message: text,
                    session_uuid: this.activeSessionUuid
                };

                const resp = await fetch(endpoint, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(payload)
                });

                const data = await resp.json();
                this.setTyping(false);
                this.isProcessing = false;

                if (data.success && data.reply) {
                    this.activeSessionUuid = data.session_uuid || this.activeSessionUuid;
                    this.appendMessage('assistant', data.reply, new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }));
                } else {
                    const errorMsg = data.error || 'Gagal memproses pembahasan soal dari AI.';
                    this.appendMessage('assistant', `⚠️ **Kendala:** ${errorMsg}`);
                }
            } catch (err) {
                this.setTyping(false);
                this.isProcessing = false;
                this.appendMessage('assistant', '⚠️ Gagal terhubung ke server Gemini AI.');
            }

            this.scrollToBottom();
        },

        formatTimeline(timeStr) {
            if (!timeStr) {
                return new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace(':', '.');
            }
            const s = String(timeStr).trim();
            if (s.toLowerCase() === 'baru saja') {
                return 'Baru saja';
            }
            const shortMatch = s.match(/^(\d{1,2})[:.](\d{2})$/);
            if (shortMatch) {
                return `${shortMatch[1].padStart(2, '0')}.${shortMatch[2]}`;
            }
            const fullMatch = s.match(/(?:T|\s)(\d{1,2}):(\d{2})(?::\d{2})?/);
            if (fullMatch) {
                return `${fullMatch[1].padStart(2, '0')}.${fullMatch[2]}`;
            }
            try {
                const d = new Date(s);
                if (!isNaN(d.getTime())) {
                    return d.toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }).replace(':', '.');
                }
            } catch (e) {}
            return s;
        },

        appendMessage(sender, rawText, timeStr) {
            const isUser = (sender === 'user');
            const row = document.createElement('div');
            row.className = `ai-msg-row d-flex gap-2.5 align-items-start mb-3 ${isUser ? 'justify-content-end' : ''}`;

            const formattedTime = this.formatTimeline(timeStr);

            let renderedContent = '';
            if (isUser) {
                renderedContent = `<div class="user-msg-bubble p-3 fs-xs shadow-3xs">${this.escapeHtml(rawText)}</div>`;
                row.innerHTML = `
                    <div class="ai-msg-bubble-wrapper max-w-85 text-end">
                        ${renderedContent}
                        <span class="fs-4xs text-muted mt-1 px-1 d-block">${formattedTime}</span>
                    </div>
                `;
            } else {
                if (window.MarkdownRenderer) {
                    renderedContent = window.MarkdownRenderer.render(rawText);
                } else {
                    renderedContent = this.escapeHtml(rawText).replace(/\n/g, '<br>');
                }

                row.innerHTML = `
                    <div class="ai-msg-avatar flex-shrink-0 rounded-circle bg-primary-gradient text-white d-flex align-items-center justify-content-center shadow-2xs" style="width: 32px; height: 32px;">
                        <i class="bi bi-lightbulb-fill fs-6"></i>
                    </div>
                    <div class="ai-msg-bubble-wrapper max-w-85">
                        <div class="ai-msg-bubble p-3 bg-white border border-secondary-subtle shadow-3xs text-dark fs-xs">
                            <div class="chat-markdown-body">${renderedContent}</div>
                        </div>
                        <div class="d-flex align-items-center gap-2 mt-1 px-1 fs-4xs text-muted">
                            <span>${formattedTime}</span>
                            <span>&bull;</span>
                            <button type="button" class="btn btn-link p-0 text-decoration-none fs-4xs text-muted btn-copy-chat" onclick="window.MarkdownRenderer ? window.MarkdownRenderer.copyMessage(this) : null">
                                <i class="bi bi-clipboard me-0.5"></i>Salin Pembahasan
                            </button>
                        </div>
                    </div>
                `;
            }

            this.messagesEl.appendChild(row);
        },

        setTyping(isTyping) {
            if (!this.typingEl) return;
            if (isTyping) {
                this.typingEl.classList.remove('d-none');
                this.typingEl.classList.add('d-flex');
            } else {
                this.typingEl.classList.add('d-none');
                this.typingEl.classList.remove('d-flex');
            }
        },

        scrollToBottom() {
            if (this.messagesEl) {
                this.messagesEl.scrollTop = this.messagesEl.scrollHeight;
            }
        },

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    };


    // =========================================================================
    // EXPOSE TO GLOBAL SCOPE & AUTO-INIT
    // =========================================================================

    window.FloatingChat = FloatingChat;
    window.EduAiFloatingChat = FloatingChat;
    window.EduAiQuestionExplainer = QuestionExplainer;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            FloatingChat.init();
            QuestionExplainer.init();
        });
    } else {
        FloatingChat.init();
        QuestionExplainer.init();
    }

})();
