<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Page Header & Action Buttons -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <h4 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2 fs-5">
            <i class="bi bi-cpu text-primary"></i>
            <span>Konfigurasi Parameter Sensitivitas AI Engine</span>
        </h4>
        <p class="text-muted fs-xs mb-0">Atur batas toleransi deteksi wajah, sensitivitas *gaze tracking*, interval dual kamera, dan model AI LLM terintegrasi.</p>
    </div>
    <div class="d-flex gap-2">
        <button type="button" class="btn btn-outline-danger btn-sm rounded-pill px-3.5" onclick="App.confirm('Apakah Anda yakin ingin mereset seluruh parameter konfigurasi AI ke baseline default?', () => App.submitPost('<?= base_url('admin/ai-configuration/reset') ?>'))">
            <i class="bi bi-arrow-counterclockwise me-1"></i> Reset ke Default
        </button>
        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3.5 fw-semibold shadow-xs" onclick="App.openCameraDiagnostic()">
            <i class="bi bi-camera-video me-1"></i> Uji Live Dual Cam
        </button>
    </div>
</div>

<form action="<?= base_url('admin/ai-configuration/update') ?>" method="POST">
    <?= csrf_field() ?>

    <div class="row g-4">
        <!-- 1. AI Dual Camera Proctoring Parameters -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-xs rounded-4 bg-white h-100">
                <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                    <h6 class="fw-bold text-slate-900 mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-camera-reels text-primary"></i>
                        <span>AI Dual Camera Proctoring Engine</span>
                    </h6>
                    <span class="badge rounded-pill bg-primary-subtle text-primary fs-xs">Real-Time WebRTC</span>
                </div>

                <div class="card-body p-4">
                    <!-- Gaze Tracking Sensitivity -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-0">Sensitivitas Gaze Tracking (Arah Pandangan Mata)</label>
                            <span class="badge rounded-pill bg-primary fs-xs" id="gaze_val_badge">0.75</span>
                        </div>
                        <input type="range" class="form-range" min="0.50" max="0.95" step="0.05" name="configs[gaze_sensitivity_threshold]" id="gaze_input" value="<?= e($configsGrouped['proctoring_ai'][1]['key_value'] ?? '0.75') ?>" oninput="document.getElementById('gaze_val_badge').textContent = this.value">
                        <div class="d-flex justify-content-between fs-3xs text-muted">
                            <span>0.50 (Toleransi Longgar)</span>
                            <span>0.75 (Rekomendasi Standar)</span>
                            <span>0.95 (Sangat Ketat)</span>
                        </div>
                    </div>

                    <!-- No Face Timeout -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-0">Batas Waktu Toleransi Wajah Hilang (*No Face Timeout*)</label>
                            <span class="badge rounded-pill bg-danger fs-xs" id="timeout_val_badge">5 Detik</span>
                        </div>
                        <div class="input-group">
                            <input type="number" class="form-control border-slate-200 fs-xs rounded-start-3" min="3" max="30" name="configs[no_face_timeout_seconds]" id="timeout_input" value="<?= e($configsGrouped['proctoring_ai'][2]['key_value'] ?? '5') ?>" oninput="document.getElementById('timeout_val_badge').textContent = this.value + ' Detik'">
                            <span class="input-group-text bg-slate-50 border-slate-200 fs-xs rounded-end-3">Detik</span>
                        </div>
                        <div class="form-text fs-3xs text-muted">AI akan memicu peringatan jika wajah tidak terdeteksi melebihi durasi ini.</div>
                    </div>

                    <!-- Dual Cam Sync Interval -->
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-0">Interval Sinkronisasi Snapshot Dual Kamera</label>
                            <span class="badge rounded-pill bg-success fs-xs" id="interval_val_badge">1000 ms</span>
                        </div>
                        <select name="configs[dual_cam_sync_interval_ms]" class="form-select border-slate-200 fs-xs rounded-3" onchange="document.getElementById('interval_val_badge').textContent = this.value + ' ms'">
                            <option value="500" <?= (($configsGrouped['proctoring_ai'][3]['key_value'] ?? '') === '500') ? 'selected' : '' ?>>500 ms (0.5 Detik - Beban Jaringan Tinggi)</option>
                            <option value="1000" <?= (($configsGrouped['proctoring_ai'][3]['key_value'] ?? '1000') === '1000') ? 'selected' : '' ?>>1000 ms (1.0 Detik - Rekomendasi Optimal)</option>
                            <option value="2000" <?= (($configsGrouped['proctoring_ai'][3]['key_value'] ?? '') === '2000') ? 'selected' : '' ?>>2000 ms (2.0 Detik - Hemat Bandwidth)</option>
                            <option value="3000" <?= (($configsGrouped['proctoring_ai'][3]['key_value'] ?? '') === '3000') ? 'selected' : '' ?>>3000 ms (3.0 Detik)</option>
                        </select>
                    </div>

                    <!-- Face Detection Model -->
                    <div class="mb-0">
                        <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Model Face & Gaze Tracking Engine</label>
                        <select name="configs[face_detection_model]" class="form-select border-slate-200 fs-xs rounded-3">
                            <option value="Canvas Vision Engine" selected>Canvas Vision Engine (Real-Time Face Centroid & Gaze Tracking)</option>
                            <option value="Canvas Heuristic Detector">Canvas Heuristic Fast Detector</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. AI Question Generation & Essay Evaluation -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-xs rounded-4 bg-white h-100 d-flex flex-column justify-content-between">
                <div>
                    <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                        <h6 class="fw-bold text-slate-900 mb-0 d-flex align-items-center gap-2">
                            <i class="bi bi-robot text-primary"></i>
                            <span>AI Generator Soal & Evaluasi Esai</span>
                        </h6>
                        <span class="badge rounded-pill bg-primary-subtle text-primary fs-xs">LLM Engine</span>
                    </div>

                    <div class="card-body p-4">
                        <!-- AI Question Model -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold text-slate-800 fs-xs mb-1">Model AI untuk Pembuatan Bank Soal Otomatis</label>
                            <select name="configs[ai_question_generation_model]" class="form-select border-slate-200 fs-xs rounded-3">
                                <option value="gpt-4o-mini" selected>OpenAI GPT-4o Mini (Cepat & Akurat)</option>
                                <option value="gemini-3.8-flash">Google Gemini 3.8 Flash (Latensi Rendah)</option>
                                <option value="claude-3-haiku">Anthropic Claude 3 Haiku</option>
                            </select>
                            <div class="form-text fs-3xs text-muted">Digunakan guru untuk menyusun variasi soal pilihan ganda dan esai.</div>
                        </div>

                        <!-- AI Essay Grading Tolerance -->
                        <div class="mb-4">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <label class="form-label fw-semibold text-slate-800 fs-xs mb-0">Derajat Kesamaan Semantik Koreksi Esai AI</label>
                                <span class="badge rounded-pill bg-primary fs-xs" id="essay_val_badge">0.85</span>
                            </div>
                            <input type="range" class="form-range" min="0.60" max="0.95" step="0.05" name="configs[ai_essay_grading_tolerance]" value="<?= e($configsGrouped['essay_grading_ai'][0]['key_value'] ?? '0.85') ?>" oninput="document.getElementById('essay_val_badge').textContent = this.value">
                            <div class="d-flex justify-content-between fs-3xs text-muted">
                                <span>0.60 (Fleksibel)</span>
                                <span>0.85 (Standar Guru)</span>
                                <span>0.95 (Sangat Ketat)</span>
                            </div>
                        </div>

                        <!-- Simulation Card -->
                        <div class="p-3 rounded-3 bg-slate-50 border border-slate-100">
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <i class="bi bi-shield-check text-success fs-5"></i>
                                <h6 class="fw-bold text-slate-900 mb-0 fs-xs">Validasi Integritas AI</h6>
                            </div>
                            <p class="fs-3xs text-muted mb-2">Setiap pelanggaran yang terdeteksi AI akan disimpan dengan lampiran bukti snapshot visual dari kedua kamera untuk keperluan evaluasi guru.</p>
                            <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-3" onclick="App.simulateAlert()">
                                <i class="bi bi-play me-1"></i> Uji Simulasi Pemicu Event
                            </button>
                        </div>
                    </div>
                </div>

                <div class="card-footer bg-white border-top border-slate-100 p-4 d-flex justify-content-end gap-2">
                    <a href="<?= base_url('admin/dashboard') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">Batal</a>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs">
                        <i class="bi bi-save me-1"></i> Simpan Parameter Konfigurasi AI
                    </button>
                </div>
            </div>
        </div>
    </div>
</form>
