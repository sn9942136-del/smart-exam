/**
 * Exam Engine Core Module
 * Mengoordinasikan navigasi soal, interaksi jawaban, auto-save, timer, dan submit ujian.
 */
class ExamEngine {
    constructor(config) {
        this.sessionToken = config.sessionToken;
        this.questions = config.questions || [];
        this.savedAnswers = config.savedAnswers || {};
        this.remainingSeconds = parseInt(config.remainingSeconds, 10) || 0;
        
        const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
        const cleanBaseUrl = baseUrl.endsWith('/') ? baseUrl : baseUrl + '/';
        this.submitUrl = config.submitUrl || `${cleanBaseUrl}siswa/exam/submit`;
        this.csrfToken = config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        
        this.currentIndex = 0;
        this.isSubmitting = false;

        this.initAutoSave();
        this.initTimer();
        this.initUI();
        this.initKeyBindings();
    }

    initAutoSave() {
        this.autoSave = new ExamAutoSave({
            sessionToken: this.sessionToken,
            onStatusChange: (status, message) => {
                // Update badge in topbar if present
                const autosaveStatus = document.getElementById('autosaveStatus');
                const autosaveIcon = document.getElementById('autosaveIcon');
                const autosaveText = document.getElementById('autosaveText');

                if (autosaveStatus && autosaveIcon && autosaveText) {
                    autosaveText.textContent = message;
                    if (status === 'saving') {
                        autosaveStatus.className = 'autosave-badge bg-warning-subtle text-warning border border-warning-subtle d-inline-flex';
                        autosaveIcon.className = 'spinner-border spinner-border-sm text-warning';
                    } else if (status === 'saved' || status === 'online') {
                        autosaveStatus.className = 'autosave-badge bg-success-subtle text-success border border-success-subtle d-inline-flex';
                        autosaveIcon.className = 'bi bi-cloud-check-fill text-success fs-6';
                    } else if (status === 'offline' || status === 'offline_saved') {
                        autosaveStatus.className = 'autosave-badge bg-danger-subtle text-danger border border-danger-subtle d-inline-flex';
                        autosaveIcon.className = 'bi bi-hdd-fill text-danger fs-6';
                    }
                }

                // Also update legacy badge if present
                const badge = document.getElementById('examSaveStatusBadge');
                if (badge) {
                    badge.textContent = message;
                }
            },
            onCountsUpdate: (counts) => {
                this.updateCounters(counts);
            }
        });
    }

    initTimer() {
        this.timer = new ExamTimer({
            sessionToken: this.sessionToken,
            remainingSeconds: this.remainingSeconds,
            onTick: (formatted, seconds) => {
                // Update dedicated hour/minute/second elements if present
                const hrsEl = document.getElementById('timerHours');
                const minsEl = document.getElementById('timerMinutes');
                const secsEl = document.getElementById('timerSeconds');

                if (hrsEl && minsEl && secsEl) {
                    const parts = formatted.split(':');
                    hrsEl.textContent = parts[0] || '00';
                    minsEl.textContent = parts[1] || '00';
                    secsEl.textContent = parts[2] || '00';
                }

                // Update single display element if present
                const timerEl = document.getElementById('examTimerDisplay');
                if (timerEl) {
                    timerEl.textContent = formatted;
                }

                // Update visual alert styling on timer container
                const timerBox = document.getElementById('examCountdownTimer');
                if (timerBox) {
                    if (seconds <= 300) {
                        timerBox.className = 'timer-badge-box timer-danger';
                    } else if (seconds <= 600) {
                        timerBox.className = 'timer-badge-box timer-warning';
                    } else {
                        timerBox.className = 'timer-badge-box';
                    }
                }
            },
            onWarning: (seconds) => {
                const mins = Math.floor(seconds / 60);
                if (window.App && typeof window.App.toast === 'function') {
                    window.App.toast(`Waktu ujian tersisa ${mins} menit lagi!`, 'warning', 'Peringatan Waktu');
                }
            },
            onExpired: () => {
                this.autoSubmit();
            }
        });

        this.timer.start();
    }

    initUI() {
        if (this.questions.length > 0) {
            this.renderQuestion(0);
        }
        this.updatePalette();
    }

    initKeyBindings() {
        // Prevent accidental page unload
        window.addEventListener('beforeunload', (e) => {
            if (!this.isSubmitting) {
                e.preventDefault();
                e.returnValue = '';
            }
        });

        // Fullscreen toggle button
        const fsBtn = document.getElementById('btnToggleFullscreen');
        if (fsBtn) {
            fsBtn.addEventListener('click', () => this.toggleFullscreen());
        }
    }

    toggleFullscreen() {
        if (!document.fullscreenElement) {
            document.documentElement.requestFullscreen().catch((err) => {
                console.warn('Fullscreen denied:', err);
            });
        } else {
            if (document.exitFullscreen) {
                document.exitFullscreen();
            }
        }
    }

    renderQuestion(index) {
        if (index < 0 || index >= this.questions.length) return;
        this.currentIndex = index;

        const q = this.questions[index];
        const qid = parseInt(q.question_id || q.id, 10);
        const saved = this.savedAnswers[qid] || {};

        // Question number and headers
        const numEl = document.getElementById('currentQuestionNumber');
        if (numEl) numEl.textContent = index + 1;

        const totalEl = document.getElementById('totalQuestionsCount');
        if (totalEl) totalEl.textContent = this.questions.length;

        const weightEl = document.getElementById('currentQuestionWeight');
        if (weightEl) weightEl.textContent = parseFloat(q.score_weight || q.weight || 1.0).toFixed(1);

        const typeBadge = document.getElementById('currentQuestionTypeBadge');
        if (typeBadge) {
            if (q.question_type === 'essay') {
                typeBadge.textContent = 'Esai / Uraian';
                typeBadge.className = 'badge bg-warning-subtle text-warning border border-warning-subtle fs-3xs';
            } else {
                typeBadge.textContent = 'Pilihan Ganda';
                typeBadge.className = 'badge bg-primary-subtle text-primary border border-primary-subtle fs-3xs';
            }
        }

        // Question content
        const bodyEl = document.getElementById('questionContentBody');
        if (bodyEl) bodyEl.innerHTML = this.sanitizeHtml(q.content || q.question_text || '');

        // Question image
        const imgContainer = document.getElementById('questionImageContainer');
        if (imgContainer) {
            if (q.question_image || q.image_url) {
                const rawImg = q.question_image || q.image_url;
                const imgSrc = this.escapeHtml(rawImg);
                imgContainer.innerHTML = `<img src="${imgSrc}" class="img-fluid rounded-3 border mb-3 max-h-300" alt="Lampiran Soal">`;
                imgContainer.classList.remove('d-none');
            } else {
                imgContainer.innerHTML = '';
                imgContainer.classList.add('d-none');
            }
        }

        // Question options or essay area
        const optionsArea = document.getElementById('questionOptionsArea');
        if (optionsArea) {
            optionsArea.innerHTML = '';

            if (q.question_type === 'essay') {
                const currentEssay = saved.essay_answer || '';
                optionsArea.innerHTML = `
                    <div class="mb-3">
                        <label class="form-label fw-bold text-dark fs-xs">
                            <i class="bi bi-pencil-square me-1 text-primary"></i> Lembar Jawaban Esai / Uraian:
                        </label>
                        <textarea class="form-control" id="essayAnswerInput" rows="7" placeholder="Ketikkan uraian jawaban lengkap Anda di sini...">${this.escapeHtml(currentEssay)}</textarea>
                        <div class="d-flex justify-content-between mt-1 text-muted fs-3xs">
                            <span><i class="bi bi-shield-check text-success me-1"></i>Tersimpan otomatis secara real-time.</span>
                            <span id="essayCharCount">${currentEssay.length} Karakter</span>
                        </div>
                    </div>
                `;

                const textarea = document.getElementById('essayAnswerInput');
                if (textarea) {
                    textarea.addEventListener('input', (e) => {
                        const text = e.target.value;
                        const charEl = document.getElementById('essayCharCount');
                        if (charEl) charEl.textContent = `${text.length} Karakter`;

                        this.savedAnswers[qid] = this.savedAnswers[qid] || {};
                        this.savedAnswers[qid].essay_answer = text;

                        const isFlagged = document.getElementById('flagRaguCheckbox')?.checked ? 1 : 0;
                        this.autoSave.saveEssayDebounced(qid, text, isFlagged);
                        this.updatePalette();
                    });
                }
            } else {
                // Multiple Choice
                const options = q.options || [];
                let optHtml = '<div class="d-flex flex-column gap-2.5">';

                options.forEach((opt) => {
                    const optId = parseInt(opt.id, 10);
                    const isSelected = (parseInt(saved.selected_option_id, 10) === optId);
                    const optKey = opt.option_key || opt.key || '';
                    const optContent = this.sanitizeHtml(opt.content || opt.option_text || '');

                    optHtml += `
                        <div class="option-choice-item ${isSelected ? 'selected' : ''}" onclick="examEngine.selectOption(${qid}, ${optId})">
                            <div class="option-choice-letter">
                                ${this.escapeHtml(optKey)}
                            </div>
                            <div class="fs-xs text-dark lh-base flex-grow-1 pt-1">
                                ${optContent}
                            </div>
                            <div class="form-check m-0 pt-1">
                                <input class="form-check-input" type="radio" name="question_option_${qid}" id="opt_${optId}" value="${optId}" ${isSelected ? 'checked' : ''} onclick="event.stopPropagation(); examEngine.selectOption(${qid}, ${optId})">
                            </div>
                        </div>
                    `;
                });

                optHtml += '</div>';
                optionsArea.innerHTML = optHtml;
            }
        }

        // Flag Ragu Checkbox
        const isFlagged = parseInt(saved.is_flagged, 10) === 1;
        const flagBox = document.getElementById('flagRaguCheckbox');
        if (flagBox) {
            flagBox.checked = isFlagged;
        }

        // Prev & Next Buttons
        const btnPrev = document.getElementById('btnPrevQuestion');
        if (btnPrev) btnPrev.disabled = (index === 0);

        const btnNext = document.getElementById('btnNextQuestion');
        if (btnNext) btnNext.disabled = (index === this.questions.length - 1);

        this.updatePalette();
    }

    selectOption(questionId, optionId) {
        this.savedAnswers[questionId] = this.savedAnswers[questionId] || {};
        this.savedAnswers[questionId].selected_option_id = optionId;

        const isFlagged = document.getElementById('flagRaguCheckbox')?.checked ? 1 : 0;
        this.autoSave.saveChoice(questionId, optionId, isFlagged);

        this.renderQuestion(this.currentIndex);
    }

    toggleFlag() {
        const q = this.questions[this.currentIndex];
        const qid = parseInt(q.question_id || q.id, 10);
        const flagBox = document.getElementById('flagRaguCheckbox');
        const isFlagged = flagBox.checked ? 1 : 0;

        this.savedAnswers[qid] = this.savedAnswers[qid] || {};
        this.savedAnswers[qid].is_flagged = isFlagged;

        const selectedOpt = this.savedAnswers[qid].selected_option_id || null;
        const essayText = this.savedAnswers[qid].essay_answer || null;

        this.autoSave.saveFlag(qid, selectedOpt, essayText, isFlagged);
        this.updatePalette();
    }

    nextQuestion() {
        if (this.currentIndex < this.questions.length - 1) {
            this.renderQuestion(this.currentIndex + 1);
        }
    }

    prevQuestion() {
        if (this.currentIndex > 0) {
            this.renderQuestion(this.currentIndex - 1);
        }
    }

    goToQuestion(index) {
        this.renderQuestion(index);
    }

    updatePalette() {
        let answeredCount = 0;
        let flaggedCount = 0;
        let unansweredCount = 0;

        this.questions.forEach((q, idx) => {
            const qid = parseInt(q.question_id || q.id, 10);
            const ans = this.savedAnswers[qid] || {};
            const isAnswered = (ans.selected_option_id || (ans.essay_answer && ans.essay_answer.trim() !== ''));
            const isFlagged = parseInt(ans.is_flagged, 10) === 1;
            const isActive = (idx === this.currentIndex);

            const btn = document.getElementById(`paletteBtn_${idx}`);
            if (btn) {
                let classes = 'exam-palette-btn';
                if (isActive) classes += ' active';
                if (isAnswered) classes += ' answered';
                if (isFlagged) classes += ' flagged';

                btn.className = classes;
            }

            if (isFlagged) {
                flaggedCount++;
            }
            if (isAnswered) {
                answeredCount++;
            } else {
                unansweredCount++;
            }
        });

        this.updateCounters({
            answered: answeredCount,
            flagged: flaggedCount,
            total: this.questions.length
        });
    }

    updateCounters(counts) {
        const ansEl = document.getElementById('countAnsweredBadge');
        const flagEl = document.getElementById('countFlaggedBadge');
        const unansEl = document.getElementById('countUnansweredBadge');

        const answered = counts.answered || 0;
        const flagged = counts.flagged || 0;
        const total = counts.total || this.questions.length;
        const unanswered = Math.max(0, total - answered);

        if (ansEl) ansEl.textContent = answered;
        if (flagEl) flagEl.textContent = flagged;
        if (unansEl) unansEl.textContent = unanswered;
    }

    openSubmitModal() {
        let answeredCount = 0;
        let flaggedCount = 0;

        this.questions.forEach((q) => {
            const qid = parseInt(q.question_id || q.id, 10);
            const ans = this.savedAnswers[qid] || {};
            if (ans.selected_option_id || (ans.essay_answer && ans.essay_answer.trim() !== '')) {
                answeredCount++;
            }
            if (parseInt(ans.is_flagged, 10) === 1) {
                flaggedCount++;
            }
        });

        const total = this.questions.length;
        const unanswered = total - answeredCount;

        const elAns = document.getElementById('modalSummaryAnswered');
        const elFlag = document.getElementById('modalSummaryFlagged');
        const elUnans = document.getElementById('modalSummaryUnanswered');

        if (elAns) elAns.textContent = answeredCount;
        if (elFlag) elFlag.textContent = flaggedCount;
        if (elUnans) elUnans.textContent = unanswered;

        // Uncheck confirmation checkbox
        const confirmCheck = document.getElementById('checkAgreeSubmit');
        if (confirmCheck) {
            confirmCheck.checked = false;
        }
        const btnSubmit = document.getElementById('btnConfirmSubmitExam');
        if (btnSubmit) {
            btnSubmit.disabled = true;
        }

        const modalEl = document.getElementById('confirmSubmitModal');
        if (modalEl) {
            const modal = new bootstrap.Modal(modalEl);
            modal.show();
        }
    }

    async submitExam() {
        if (this.isSubmitting) return;
        this.isSubmitting = true;

        const btnSubmit = document.getElementById('btnConfirmSubmitExam');
        if (btnSubmit) {
            btnSubmit.disabled = true;
            btnSubmit.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Mengumpulkan...';
        }

        try {
            const res = await fetch(this.submitUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ session_token: this.sessionToken })
            });

            const data = await res.json();
            if (res.ok && data.success) {
                this.timer.stop();
                this.autoSave.clearLocalStorage();
                window.location.href = data.redirect;
            } else {
                throw new Error(data.error || 'Terjadi kendala saat submit ujian.');
            }
        } catch (err) {
            alert('Gagal mengumpulkan ujian: ' + err.message);
            if (btnSubmit) {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = '<i class="bi bi-check2-circle me-1"></i> Ya, Kumpulkan Sekarang';
            }
            this.isSubmitting = false;
        }
    }

    autoSubmit() {
        if (this.isSubmitting) return;

        // Show auto submit modal
        const autoModalEl = document.getElementById('autoSubmitModal');
        if (autoModalEl) {
            const modal = new bootstrap.Modal(autoModalEl, { backdrop: 'static', keyboard: false });
            modal.show();
        }

        this.submitExam();
    }

    sanitizeHtml(dirty) {
        if (!dirty || typeof dirty !== 'string') return '';

        try {
            const parser = new DOMParser();
            const doc = parser.parseFromString(dirty, 'text/html');

            // Disallowed tags
            const forbiddenTags = ['SCRIPT', 'STYLE', 'IFRAME', 'OBJECT', 'EMBED', 'APPLET', 'META', 'LINK', 'FORM', 'INPUT', 'BUTTON', 'SVG', 'MATH', 'BASE'];

            forbiddenTags.forEach(tag => {
                const elements = doc.body.querySelectorAll(tag);
                elements.forEach(el => el.remove());
            });

            // Clean all remaining elements
            const allElements = doc.body.querySelectorAll('*');
            allElements.forEach(el => {
                // Remove all on* event handlers and dangerous attributes
                const attrs = Array.from(el.attributes);
                attrs.forEach(attr => {
                    const name = attr.name.toLowerCase();
                    const val = attr.value || '';
                    if (name.startsWith('on')) {
                        el.removeAttribute(attr.name);
                    } else if (name === 'href' || name === 'src' || name === 'action') {
                        const cleanVal = val.trim().toLowerCase();
                        if (cleanVal.startsWith('javascript:') || cleanVal.startsWith('vbscript:') || (cleanVal.startsWith('data:') && !cleanVal.startsWith('data:image/'))) {
                            el.setAttribute(attr.name, '#');
                        }
                    } else if (name === 'style') {
                        if (/expression|javascript|behavior/i.test(val)) {
                            el.removeAttribute(attr.name);
                        }
                    }
                });
            });

            return doc.body.innerHTML;
        } catch (e) {
            console.warn('[ExamEngine] sanitizeHtml error, escaping plain text:', e);
            return this.escapeHtml(dirty);
        }
    }

    escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
