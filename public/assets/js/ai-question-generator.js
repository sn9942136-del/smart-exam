/**
 * AI Question Generator & Review Workbench (UC-21c)
 * Modul Frontend untuk menangani AJAX request generasi soal AI dan peninjauan butir soal.
 */
const AiGenerator = {
    /**
     * Update tampilan jumlah soal dari slider
     */
    updateCountDisplay(value) {
        const badge = document.getElementById('countDisplay');
        if (badge) {
            badge.innerText = `${value} Soal`;
        }
    },

    /**
     * Pilih saran topik cepat
     */
    setTopic(topicName, subjectName = '') {
        const topicInput = document.getElementById('topic');
        if (topicInput) {
            topicInput.value = topicName;
            topicInput.focus();
        }

        const subjectInput = document.getElementById('subject_name');
        if (subjectInput && subjectName && !subjectInput.value.trim()) {
            subjectInput.value = subjectName;
        }
    },

    /**
     * Kirim formulir generasi soal ke backend AI
     */
    async handleGenerate(event) {
        event.preventDefault();

        const form = document.getElementById('aiQuestionForm');
        const alertBox = document.getElementById('aiFormAlert');
        const btnSubmit = document.getElementById('btnSubmitAi');

        if (!form) return;

        // Reset Alert
        if (alertBox) {
            alertBox.className = 'd-none alert alert-dismissible fade show';
            alertBox.innerHTML = '';
        }

        const bankSelect = document.getElementById('question_bank_id');
        if (!bankSelect || !bankSelect.value) {
            this.showAlert('Pilih Bank Soal tujuan terlebih dahulu.', 'danger');
            return;
        }

        const topicInput = document.getElementById('topic');
        if (!topicInput || !topicInput.value.trim()) {
            this.showAlert('Topik / Materi pembelajaran wajib diisi.', 'danger');
            return;
        }

        // Ambil payload formulir
        const formData = new FormData(form);
        const payload = Object.fromEntries(formData.entries());

        // UI Loading State
        const originalBtnHtml = btnSubmit.innerHTML;
        btnSubmit.disabled = true;
        btnSubmit.innerHTML = `
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>Membuat Draf Soal AI...</span>
        `;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';

            const response = await fetch(`${baseUrl}/guru/ai-questions/generate`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();

            if (data.success && data.redirect_url) {
                this.showAlert('Draf soal AI berhasil digenerasi! Mengalihkan ke meja peninjauan...', 'success');
                setTimeout(() => {
                    window.location.href = data.redirect_url;
                }, 800);
            } else {
                btnSubmit.disabled = false;
                btnSubmit.innerHTML = originalBtnHtml;
                this.showAlert(data.error || 'Terjadi kesalahan saat memproses generasi soal AI.', 'danger');
            }
        } catch (error) {
            console.error('AI Generation Error:', error);
            btnSubmit.disabled = false;
            btnSubmit.innerHTML = originalBtnHtml;
            this.showAlert('Gagal terhubung ke server. Silakan periksa koneksi Anda.', 'danger');
        }
    },

    /**
     * Tampilkan pesan alert di atas form
     */
    showAlert(message, type = 'info') {
        const alertBox = document.getElementById('aiFormAlert');
        if (alertBox) {
            alertBox.className = `alert alert-${type} alert-dismissible fade show rounded-3 shadow-xs mb-3`;
            alertBox.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'}"></i>
                    <div>${message}</div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            `;
            alertBox.scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else if (window.App && typeof window.App.toast === 'function') {
            window.App.toast(message, type);
        } else {
            alert(message);
        }
    }
};

/**
 * Review Workbench Object
 */
const AiReview = {
    /**
     * Pilih / Batalkan semua butir soal
     */
    selectAll(isChecked) {
        const checkboxes = document.querySelectorAll('.question-select-toggle');
        checkboxes.forEach(cb => {
            cb.checked = isChecked;
        });
        this.updateSelectionStats();
    },

    /**
     * Update counter butir soal terpilih
     */
    updateSelectionStats() {
        const checkboxes = document.querySelectorAll('.question-select-toggle');
        let selected = 0;
        checkboxes.forEach(cb => {
            const card = cb.closest('.question-item-card');
            if (cb.checked) {
                selected++;
                if (card) {
                    card.classList.remove('opacity-50', 'bg-light');
                }
            } else {
                if (card) {
                    card.classList.add('opacity-50', 'bg-light');
                }
            }
        });

        const badge = document.getElementById('selectedCountBadge');
        if (badge) {
            badge.innerText = selected;
        }

        const btnText = document.getElementById('btnImportText');
        if (btnText) {
            btnText.innerText = selected > 0 ? `Simpan (${selected} Soal)` : 'Pilih Soal Dulu';
        }

        const btnTop = document.getElementById('btnImportTop');
        const btnBottom = document.getElementById('btnImportBottom');
        if (btnTop) btnTop.disabled = (selected === 0);
        if (btnBottom) btnBottom.disabled = (selected === 0);
    },

    /**
     * Kumpulkan data butir soal yang disetujui dan kirim ke backend
     */
    async submitApprovedQuestions() {
        const generationIdEl = document.getElementById('generation_id');
        const generationId = parseInt(generationIdEl?.value || '0', 10);
        if (!generationId) {
            alert('ID draf generasi tidak valid.');
            return;
        }

        const cards = document.querySelectorAll('.question-item-card');
        const approvedQuestions = [];

        cards.forEach((card, index) => {
            const toggle = card.querySelector('.question-select-toggle');
            if (toggle && !toggle.checked) {
                return; // Soal tidak dipilih guru
            }

            const contentInput = card.querySelector('.q-content-input');
            const diffInput = card.querySelector('.q-diff-input');
            const weightInput = card.querySelector('.q-weight-input');
            const explanationInput = card.querySelector('.q-explanation-input');

            const content = contentInput ? contentInput.value.trim() : '';
            if (!content) return;

            const difficulty = diffInput ? diffInput.value : 'medium';
            const weight = weightInput ? parseFloat(weightInput.value) || 2.0 : 2.0;
            const explanation = explanationInput ? explanationInput.value.trim() : '';

            // Ambil opsi jawaban jika ada
            const optionRows = card.querySelectorAll('.option-row');
            const options = [];

            if (optionRows.length > 0) {
                optionRows.forEach((row, optIdx) => {
                    const textInput = row.querySelector('.q-opt-text');
                    const radio = row.querySelector('.q-correct-radio');
                    const label = textInput?.dataset?.label || String.fromCharCode(65 + optIdx);
                    const optText = textInput ? textInput.value.trim() : '';

                    options.push({
                        label: label,
                        text: optText,
                        is_correct: radio ? (radio.checked ? 1 : 0) : (optIdx === 0 ? 1 : 0)
                    });
                });
            }

            const qType = options.length > 0 ? 'multiple_choice' : 'essay';

            approvedQuestions.push({
                number: index + 1,
                type: qType,
                difficulty: difficulty,
                weight: weight,
                content: content,
                explanation: explanation,
                options: options
            });
        });

        if (approvedQuestions.length === 0) {
            alert('Pilih minimal 1 butir soal untuk disimpan ke Bank Soal.');
            return;
        }

        if (!confirm(`Konfirmasi: Simpan ${approvedQuestions.length} butir soal yang telah ditinjau ke Bank Soal?`)) {
            return;
        }

        // Disable buttons & show loading
        const btnTop = document.getElementById('btnImportTop');
        const btnBottom = document.getElementById('btnImportBottom');
        if (btnTop) {
            btnTop.disabled = true;
            btnTop.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';
        }
        if (btnBottom) {
            btnBottom.disabled = true;
            btnBottom.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Menyimpan...';
        }

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';

            const response = await fetch(`${baseUrl}/guru/ai-questions/import`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                },
                body: JSON.stringify({
                    generation_id: generationId,
                    questions: approvedQuestions
                })
            });

            const result = await response.json();

            if (result.success) {
                if (window.App && typeof window.App.toast === 'function') {
                    window.App.toast(result.message || 'Butir soal berhasil disimpan ke Bank Soal.', 'success');
                } else {
                    alert(result.message || 'Butir soal berhasil disimpan ke Bank Soal.');
                }

                setTimeout(() => {
                    window.location.href = result.bank_url || `${baseUrl}/guru/question-banks`;
                }, 1000);
            } else {
                alert(result.error || 'Gagal menyimpan butir soal ke Bank Soal.');
                if (btnTop) {
                    btnTop.disabled = false;
                    btnTop.innerHTML = '<i class="bi bi-check2-all fs-5"></i> Simpan ke Bank Soal';
                }
                if (btnBottom) {
                    btnBottom.disabled = false;
                    btnBottom.innerHTML = '<i class="bi bi-check2-circle fs-5"></i> Setujui & Simpan ke Bank Soal';
                }
            }
        } catch (err) {
            console.error('Import Error:', err);
            alert('Terjadi kesalahan jaringan saat menyimpan ke Bank Soal.');
            if (btnTop) {
                btnTop.disabled = false;
                btnTop.innerHTML = '<i class="bi bi-check2-all fs-5"></i> Simpan ke Bank Soal';
            }
            if (btnBottom) {
                btnBottom.disabled = false;
                btnBottom.innerHTML = '<i class="bi bi-check2-circle fs-5"></i> Setujui & Simpan ke Bank Soal';
            }
        }
    }
};

// Inisialisasi awal saat dokumen selesai dimuat
document.addEventListener('DOMContentLoaded', () => {
    if (document.getElementById('reviewWorkbenchApp')) {
        AiReview.updateSelectionStats();
    }
});
