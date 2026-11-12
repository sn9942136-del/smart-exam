/**
 * Exam Auto-Save Module
 * Mengelola penyimpanan instan jawaban ke server, status konektivitas, dan backup ganda ke localStorage.
 */
class ExamAutoSave {
    constructor(config) {
        this.sessionToken = config.sessionToken;
        const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';
        const cleanBaseUrl = baseUrl.endsWith('/') ? baseUrl : baseUrl + '/';
        this.saveUrl = config.saveUrl || `${cleanBaseUrl}siswa/exam/answer/save`;
        this.csrfToken = config.csrfToken || document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        this.onStatusChange = config.onStatusChange || function() {};
        this.onCountsUpdate = config.onCountsUpdate || function() {};
        this.onConnectionChange = config.onConnectionChange || function() {};
        this.storageKey = `cbt_exam_answers_${this.sessionToken}`;
        
        this.debounceTimers = {};
        this.isOnline = navigator.onLine;
        this.isSyncing = false;

        this.initNetworkListeners();
        this.updateConnectionBadge(this.isOnline ? 'ONLINE' : 'OFFLINE');
    }

    initNetworkListeners() {
        window.addEventListener('online', async () => {
            this.isOnline = true;
            this.updateConnectionBadge('RECONNECTING', 'Menyambung Ulang...');
            this.onStatusChange('saving', 'Jaringan pulih, menyinkronkan data lokal ke server...');
            
            await this.resyncLocalStorage();
            this.updateConnectionBadge('ONLINE', 'ONLINE');
        });

        window.addEventListener('offline', () => {
            this.isOnline = false;
            this.updateConnectionBadge('OFFLINE', 'OFFLINE');
            this.onStatusChange('offline_saved', 'Koneksi Terputus (Tersimpan Lokal di Perangkat)');
        });
    }

    updateConnectionBadge(state, customLabel = null) {
        const badge = document.getElementById('networkStatusBadge');
        const dot = document.getElementById('networkStatusDot');
        const text = document.getElementById('networkStatusText');

        const label = customLabel || state;

        if (badge && text) {
            text.textContent = label;

            if (state === 'ONLINE') {
                badge.className = 'conn-badge conn-online';
                if (dot) dot.className = 'pulse-dot bg-success';
            } else if (state === 'OFFLINE') {
                badge.className = 'conn-badge conn-offline';
                if (dot) dot.className = 'pulse-dot bg-danger';
            } else if (state === 'RECONNECTING') {
                badge.className = 'conn-badge conn-reconnecting';
                if (dot) dot.className = 'pulse-dot bg-warning';
            } else if (state === 'SYNCING') {
                badge.className = 'conn-badge conn-syncing';
                if (dot) dot.className = 'pulse-dot bg-info';
            }
        }

        this.onConnectionChange(state, label);
    }

    saveChoice(questionId, selectedOptionId, isFlagged = 0) {
        return this.send(questionId, selectedOptionId, null, isFlagged);
    }

    saveFlag(questionId, selectedOptionId, essayAnswer, isFlagged) {
        return this.send(questionId, selectedOptionId, essayAnswer, isFlagged);
    }

    saveEssayDebounced(questionId, essayAnswer, isFlagged = 0, delay = 800) {
        if (this.debounceTimers[questionId]) {
            clearTimeout(this.debounceTimers[questionId]);
        }

        this.onStatusChange('saving', 'Menyimpan teks esai...');
        this.saveToLocalStorage(questionId, null, essayAnswer, isFlagged);

        this.debounceTimers[questionId] = setTimeout(() => {
            this.send(questionId, null, essayAnswer, isFlagged);
        }, delay);
    }

    async send(questionId, selectedOptionId, essayAnswer, isFlagged) {
        this.onStatusChange('saving', 'Menyimpan...');

        // 1. Cadangkan ke localStorage terlebih dahulu (Local Persistence)
        this.saveToLocalStorage(questionId, selectedOptionId, essayAnswer, isFlagged);

        // Jika navigator jelas offline, jangan kirim request yang pasti gagal
        if (!navigator.onLine) {
            this.isOnline = false;
            this.updateConnectionBadge('OFFLINE', 'OFFLINE');
            this.onStatusChange('offline_saved', 'Tersimpan Lokal di Perangkat (Offline - Belum ke Server)');
            return { success: false, offline: true };
        }

        const payload = {
            session_token: this.sessionToken,
            question_id: questionId,
            selected_option_id: selectedOptionId,
            essay_answer: essayAnswer,
            is_flagged: isFlagged ? 1 : 0
        };

        try {
            const res = await fetch(this.saveUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': this.csrfToken,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json();
            if (res.ok && data.success) {
                this.isOnline = true;
                this.updateConnectionBadge('ONLINE', 'ONLINE');
                this.onStatusChange('saved', `Tersimpan ke Server (${data.saved_at || 'Baru saja'})`);
                if (data.counts) {
                    this.onCountsUpdate(data.counts);
                }
                return { success: true, data };
            } else {
                throw new Error(data.error || 'Gagal menyimpan ke server');
            }
        } catch (err) {
            console.warn('Auto-save network error, backed up in local storage:', err);
            this.isOnline = false;
            this.updateConnectionBadge('OFFLINE', 'OFFLINE');
            // Pesan jujur: tersimpan di local browser, bukan server
            this.onStatusChange('offline_saved', 'Tersimpan Lokal di Perangkat (Offline - Belum ke Server)');
            return { success: false, error: err.message, offline: true };
        }
    }

    saveToLocalStorage(questionId, selectedOptionId, essayAnswer, isFlagged) {
        try {
            const current = JSON.parse(localStorage.getItem(this.storageKey) || '{}');
            current[questionId] = {
                selected_option_id: selectedOptionId,
                essay_answer: essayAnswer,
                is_flagged: isFlagged,
                saved_at: new Date().toISOString()
            };
            localStorage.setItem(this.storageKey, JSON.stringify(current));
        } catch (e) {
            console.warn('LocalStorage backup quota issue:', e);
        }
    }

    getLocalStorageAnswers() {
        try {
            return JSON.parse(localStorage.getItem(this.storageKey) || '{}');
        } catch (e) {
            return {};
        }
    }

    async resyncLocalStorage() {
        if (this.isSyncing) return;
        this.isSyncing = true;

        const local = this.getLocalStorageAnswers();
        const entries = Object.entries(local);
        if (entries.length === 0) {
            this.isSyncing = false;
            return;
        }

        this.updateConnectionBadge('SYNCING', 'SYNCING...');
        this.onStatusChange('saving', `Menyinkronkan ${entries.length} jawaban tersimpan lokal ke server...`);

        let syncSuccessCount = 0;
        for (const [qid, val] of entries) {
            const res = await this.send(parseInt(qid, 10), val.selected_option_id, val.essay_answer, val.is_flagged);
            if (res && res.success) {
                syncSuccessCount++;
            }
        }

        this.isSyncing = false;
        if (syncSuccessCount === entries.length) {
            this.updateConnectionBadge('ONLINE', 'ONLINE');
            this.onStatusChange('saved', 'Semua lembar jawaban tersinkronisasi lengkap ke server');
        }
    }

    clearLocalStorage() {
        try {
            localStorage.removeItem(this.storageKey);
        } catch (e) {}
    }
}
