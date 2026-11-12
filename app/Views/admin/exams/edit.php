<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="card vui-card mb-4">
            <div class="card-header bg-white py-3.5 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('admin/exams/show/' . $exam['id']) ?>" class="btn btn-sm btn-light border border-slate-200 rounded-pill p-1 px-2.5 me-1 text-muted" title="Kembali">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div>
                        <h5 class="fw-bold text-slate-900 mb-0 fs-6">Edit Pengaturan Ujian</h5>
                        <p class="text-muted fs-3xs mb-0">Kode Ujian: <strong class="font-monospace text-slate-900"><?= e($exam['code'] ?? $exam['exam_code'] ?? '') ?></strong> &bull; Guru: <?= e($exam['created_by_name'] ?? $exam['teacher_name'] ?? '-') ?></p>
                    </div>
                </div>
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle rounded-pill px-2.5 py-1 fs-3xs fw-medium">
                    <i class="bi bi-shield-check me-1"></i> Admin Edit
                </span>
            </div>

            <form method="POST" action="<?= base_url('admin/exams/update/' . $exam['id']) ?>" class="p-4 needs-validation" novalidate>
                <?= csrf_field() ?>

                <!-- SECTION 1: INFORMASI UTAMA UJIAN -->
                <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom border-slate-100">
                    <div class="vui-icon-wrap" style="width: 28px; height: 28px; font-size: 0.875rem;">
                        <i class="bi bi-card-checklist"></i>
                    </div>
                    <span class="fw-bold text-slate-900 fs-xs text-uppercase tracking-wider">Informasi & Parameter Utama</span>
                </div>

                <div class="row g-3 mb-4 p-3.5 bg-slate-50 bg-opacity-50 rounded-3 border border-slate-200">
                    <!-- Judul Ujian -->
                    <div class="col-md-8">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Judul Ujian <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-sm border-slate-200 fs-xs <?= isset($errors['title']) ? 'is-invalid' : '' ?>" value="<?= e($old['title'] ?? $exam['title']) ?>" required>
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback fs-3xs"><?= e($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Jenis Ujian -->
                    <div class="col-md-4">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Jenis / Kategori <span class="text-danger">*</span></label>
                        <?php $currentType = $old['type'] ?? $exam['type']; ?>
                        <select name="type" class="form-select form-select-sm border-slate-200 fs-xs" required>
                            <option value="daily_test" <?= ($currentType === 'daily_test') ? 'selected' : '' ?>>Ulangan Harian</option>
                            <option value="midterm" <?= ($currentType === 'midterm') ? 'selected' : '' ?>>Penilaian Tengah Semester (PTS)</option>
                            <option value="final_exam" <?= ($currentType === 'final_exam') ? 'selected' : '' ?>>Penilaian Akhir Semester (PAS)</option>
                            <option value="quiz" <?= ($currentType === 'quiz') ? 'selected' : '' ?>>Kuis Singkat</option>
                            <option value="tryout" <?= ($currentType === 'tryout') ? 'selected' : '' ?>>Tryout Ujian</option>
                        </select>
                    </div>

                    <!-- Mata Pelajaran -->
                    <div class="col-md-4">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select form-select-sm border-slate-200 fs-xs" required>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ((string)($old['subject_id'] ?? $exam['subject_id']) === (string)$s['id']) ? 'selected' : '' ?>>
                                    <?= e($s['name']) ?> (<?= e($s['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Durasi & Passing Grade -->
                    <div class="col-md-4">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Durasi Pengerjaan <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="duration_minutes" class="form-control border-slate-200 fs-xs" min="5" max="360" value="<?= e($old['duration_minutes'] ?? $exam['duration_minutes']) ?>" required>
                            <span class="input-group-text bg-white border-slate-200 text-muted fs-xs">Menit</span>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-xs fw-semibold text-slate-800">KKM <span class="text-danger">*</span></label>
                        <input type="number" step="0.5" name="passing_grade" class="form-control form-control-sm border-slate-200 fs-xs" min="0" max="100" value="<?= e($old['passing_grade'] ?? $exam['passing_grade']) ?>" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Status</label>
                        <?php $currentStatus = $old['status'] ?? $exam['status']; ?>
                        <select name="status" class="form-select form-select-sm border-slate-200 fs-xs">
                            <option value="draft" <?= ($currentStatus === 'draft') ? 'selected' : '' ?>>Draft</option>
                            <option value="published" <?= ($currentStatus === 'published') ? 'selected' : '' ?>>Published</option>
                            <option value="scheduled" <?= ($currentStatus === 'scheduled') ? 'selected' : '' ?>>Scheduled</option>
                            <option value="ongoing" <?= in_array($currentStatus, ['ongoing', 'in_progress'], true) ? 'selected' : '' ?>>Ongoing</option>
                            <option value="completed" <?= ($currentStatus === 'completed') ? 'selected' : '' ?>>Completed</option>
                            <option value="archived" <?= ($currentStatus === 'archived') ? 'selected' : '' ?>>Archived</option>
                        </select>
                    </div>

                    <!-- Petunjuk / Deskripsi -->
                    <div class="col-12">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Petunjuk Pengerjaan Ujian</label>
                        <textarea name="description" class="form-control border-slate-200 fs-xs" rows="2"><?= e($old['description'] ?? $exam['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- SECTION 2: ATURAN PENGATURAN AI PROCTORING & DUAL KAMERA -->
                <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom border-slate-100">
                    <div class="vui-icon-wrap vui-icon-danger" style="width: 28px; height: 28px; font-size: 0.875rem;">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <span class="fw-bold text-slate-900 fs-xs text-uppercase tracking-wider">Pengawasan Dual Kamera AI & Anti-Kecurangan</span>
                </div>

                <div class="row g-3 mb-4 p-3.5 bg-slate-50 bg-opacity-50 rounded-3 border border-slate-200">
                    <?php 
                    $rules = $exam['rules'] ?? [];
                    ?>
                    <!-- Dual Cam Switch -->
                    <div class="col-md-6">
                        <div class="form-check p-3 bg-white rounded-3 border border-slate-200 shadow-2xs h-100">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="is_dual_cam_required" value="1" id="swDualCamEditAdmin" <?= !empty($exam['is_dual_cam_required']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold text-slate-800 fs-sm cursor-pointer" for="swDualCamEditAdmin">
                                <span class="d-flex align-items-center gap-1.5 text-danger">
                                    <i class="bi bi-camera-video"></i>
                                    <span>Wajibkan Dual Camera AI</span>
                                </span>
                                <span class="d-block fs-3xs text-muted fw-normal mt-0.5">Webcam Depan Laptop + Kamera HP Samping terhubung aktif.</span>
                            </label>
                        </div>
                    </div>

                    <!-- Lock Fullscreen -->
                    <div class="col-md-6">
                        <div class="form-check p-3 bg-white rounded-3 border border-slate-200 shadow-2xs h-100">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="lock_browser" value="1" id="swLockEditAdmin" <?= !empty($rules['lock_browser']) ? 'checked' : '' ?>>
                            <label class="form-check-label fw-bold text-slate-800 fs-sm cursor-pointer" for="swLockEditAdmin">
                                <span class="d-flex align-items-center gap-1.5 text-primary">
                                    <i class="bi bi-fullscreen"></i>
                                    <span>Kunci Mode Fullscreen Anti-Contek</span>
                                </span>
                                <span class="d-block fs-3xs text-muted fw-normal mt-0.5">Layar ujian terkunci penuh dan mendeteksi upaya keluar layar.</span>
                            </label>
                        </div>
                    </div>

                    <!-- AI Violations Checks -->
                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200 shadow-2xs">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="detect_multiple_faces" value="1" id="swMultiFaceEditAdmin" <?= !empty($rules['detect_multiple_faces']) ? 'checked' : '' ?>>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium cursor-pointer" for="swMultiFaceEditAdmin">
                                <i class="bi bi-people text-primary me-1"></i> Deteksi Multi Wajah
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200 shadow-2xs">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="detect_look_away" value="1" id="swGazeEditAdmin" <?= !empty($rules['detect_look_away']) ? 'checked' : '' ?>>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium cursor-pointer" for="swGazeEditAdmin">
                                <i class="bi bi-eye text-primary me-1"></i> Deteksi Lirik Mata
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200 shadow-2xs">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="detect_secondary_device" value="1" id="swSecDevEditAdmin" <?= !empty($rules['detect_secondary_device']) ? 'checked' : '' ?>>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium cursor-pointer" for="swSecDevEditAdmin">
                                <i class="bi bi-phone text-primary me-1"></i> Deteksi HP Tambahan
                            </label>
                        </div>
                    </div>

                    <!-- Randomisasi -->
                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200 shadow-2xs">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="randomize_questions" value="1" id="swRandQEditAdmin" <?= !empty($exam['randomize_questions']) ? 'checked' : '' ?>>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium cursor-pointer" for="swRandQEditAdmin">
                                <i class="bi bi-shuffle text-info me-1"></i> Acak Urutan Soal
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200 shadow-2xs">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="randomize_options" value="1" id="swRandOptEditAdmin" <?= !empty($exam['randomize_options']) ? 'checked' : '' ?>>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium cursor-pointer" for="swRandOptEditAdmin">
                                <i class="bi bi-arrow-repeat text-info me-1"></i> Acak Pilihan Opsi A-E
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200 shadow-2xs">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="show_result_immediately" value="1" id="swShowResEditAdmin" <?= !empty($exam['show_result_immediately']) ? 'checked' : '' ?>>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium cursor-pointer" for="swShowResEditAdmin">
                                <i class="bi bi-award text-warning me-1"></i> Tampilkan Nilai Instan
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="d-flex justify-content-between align-items-center pt-3 border-top border-slate-100">
                    <a href="<?= base_url('admin/exams/show/' . $exam['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5 d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-arrow-left"></i>
                        <span>Batal</span>
                    </a>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-check-lg"></i>
                        <span>Simpan Perubahan Ujian</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
