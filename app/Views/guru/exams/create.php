<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-10 col-xl-9">
        <div class="card vui-card mb-4">
            <div class="card-header bg-white py-3.5 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('guru/exams') ?>" class="btn btn-sm btn-light border border-slate-200 rounded-pill p-1 px-2.5 me-1 text-muted" title="Kembali">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <div>
                        <h5 class="fw-bold text-slate-900 mb-0 fs-6">Buat Jadwal & Pengaturan Ujian Baru</h5>
                        <p class="text-muted fs-3xs mb-0">Konfigurasikan jadwal pelaksanaan, bank soal rujukan, dan protokol pengawasan Smart Exam.</p>
                    </div>
                </div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle rounded-pill px-2.5 py-1 fs-3xs fw-medium">
                    <i class="bi bi-calendar-plus me-1"></i> Jadwal Baru
                </span>
            </div>

            <form method="POST" action="<?= base_url('guru/exams/store') ?>" class="p-4 needs-validation" novalidate>
                <?= csrf_field() ?>

                <!-- SECTION 1: INFORMASI UTAMA UJIAN -->
                <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom border-slate-100">
                    <div class="vui-icon-wrap" style="width: 28px; height: 28px; font-size: 0.875rem;">
                        <i class="bi bi-card-checklist"></i>
                    </div>
                    <span class="fw-bold text-slate-900 fs-xs text-uppercase tracking-wider">Informasi & Materi Ujian</span>
                </div>

                <div class="row g-3 mb-4 p-3.5 bg-slate-50 bg-opacity-50 rounded-3 border border-slate-200">
                    <!-- Judul Ujian -->
                    <div class="col-md-8">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Judul Ujian <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control form-control-sm border-slate-200 fs-xs <?= isset($errors['title']) ? 'is-invalid' : '' ?>" placeholder="Contoh: Penilaian Akhir Semester Ganjil Matematika X" value="<?= e($old['title'] ?? '') ?>" required>
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback fs-3xs"><?= e($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Jenis Ujian -->
                    <div class="col-md-4">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Jenis / Kategori <span class="text-danger">*</span></label>
                        <?php $oldType = $old['type'] ?? 'daily_test'; ?>
                        <select name="type" class="form-select form-select-sm border-slate-200 fs-xs" required>
                            <option value="daily_test" <?= ($oldType === 'daily_test') ? 'selected' : '' ?>>Ulangan Harian</option>
                            <option value="midterm" <?= ($oldType === 'midterm') ? 'selected' : '' ?>>Penilaian Tengah Semester (PTS)</option>
                            <option value="final_exam" <?= ($oldType === 'final_exam') ? 'selected' : '' ?>>Penilaian Akhir Semester (PAS)</option>
                            <option value="quiz" <?= ($oldType === 'quiz') ? 'selected' : '' ?>>Kuis Singkat</option>
                            <option value="tryout" <?= ($oldType === 'tryout') ? 'selected' : '' ?>>Tryout Ujian</option>
                        </select>
                    </div>

                    <!-- Mata Pelajaran -->
                    <div class="col-md-6">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select form-select-sm border-slate-200 fs-xs <?= isset($errors['subject_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ((string)($old['subject_id'] ?? '') === (string)$s['id']) ? 'selected' : '' ?>>
                                    <?= e($s['name']) ?> (<?= e($s['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['subject_id'])): ?>
                            <div class="invalid-feedback fs-3xs"><?= e($errors['subject_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Bank Soal Sumber -->
                    <div class="col-md-6">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Pilih Bank Soal Materi <span class="text-danger">*</span></label>
                        <select name="question_bank_id" class="form-select form-select-sm border-slate-200 fs-xs <?= isset($errors['question_bank_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih Bank Soal --</option>
                            <?php foreach ($questionBanks as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= ((string)($old['question_bank_id'] ?? '') === (string)$b['id']) ? 'selected' : '' ?>>
                                    <?= e($b['title']) ?> (<?= (int)$b['total_questions'] ?> Butir Soal)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['question_bank_id'])): ?>
                            <div class="invalid-feedback fs-3xs"><?= e($errors['question_bank_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Durasi & Passing Grade -->
                    <div class="col-md-4">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Durasi Pengerjaan <span class="text-danger">*</span></label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="duration_minutes" class="form-control border-slate-200 fs-xs <?= isset($errors['duration_minutes']) ? 'is-invalid' : '' ?>" min="5" max="360" value="<?= e($old['duration_minutes'] ?? '90') ?>" required>
                            <span class="input-group-text bg-white border-slate-200 text-muted fs-xs">Menit</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Standar Kelulusan (KKM) <span class="text-danger">*</span></label>
                        <input type="number" step="0.5" name="passing_grade" class="form-control form-control-sm border-slate-200 fs-xs <?= isset($errors['passing_grade']) ? 'is-invalid' : '' ?>" min="0" max="100" value="<?= e($old['passing_grade'] ?? '75.00') ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Status Awal</label>
                        <select name="status" class="form-select form-select-sm border-slate-200 fs-xs">
                            <option value="published" selected>Published (Siap Digunakan)</option>
                            <option value="draft">Draft (Disimpan Sementara)</option>
                        </select>
                    </div>

                    <!-- Petunjuk / Deskripsi -->
                    <div class="col-12">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Petunjuk Pengerjaan Ujian</label>
                        <textarea name="description" class="form-control border-slate-200 fs-xs" rows="2" placeholder="Petunjuk khusus bagi peserta didik..."><?= e($old['description'] ?? '') ?></textarea>
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
                    <!-- Dual Cam Switch -->
                    <div class="col-md-6">
                        <div class="form-check p-3 bg-white rounded-3 border border-slate-200 shadow-2xs h-100">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="is_dual_cam_required" value="1" id="switchDualCam" checked>
                            <label class="form-check-label fw-bold text-slate-800 fs-sm cursor-pointer" for="switchDualCam">
                                <span class="d-flex align-items-center gap-1.5 text-danger">
                                    <i class="bi bi-camera-video"></i>
                                    <span>Wajibkan Dual Camera AI</span>
                                </span>
                                <span class="d-block fs-3xs text-muted fw-normal mt-0.5">Webcam Depan Laptop + Kamera HP Samping terhubung aktif selama ujian.</span>
                            </label>
                        </div>
                    </div>

                    <!-- Lock Fullscreen -->
                    <div class="col-md-6">
                        <div class="form-check p-3 bg-white rounded-3 border border-slate-200 shadow-2xs h-100">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="lock_browser" value="1" id="switchLockBrowser" checked>
                            <label class="form-check-label fw-bold text-slate-800 fs-sm cursor-pointer" for="switchLockBrowser">
                                <span class="d-flex align-items-center gap-1.5 text-primary">
                                    <i class="bi bi-fullscreen"></i>
                                    <span>Kunci Mode Fullscreen Anti-Contek</span>
                                </span>
                                <span class="d-block fs-3xs text-muted fw-normal mt-0.5">Layar ujian terkunci penuh dan mencatat insiden keluar tab/layar.</span>
                            </label>
                        </div>
                    </div>

                    <!-- AI Violations Checks -->
                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200 shadow-2xs">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="detect_multiple_faces" value="1" id="swMultiFace" checked>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium cursor-pointer" for="swMultiFace">
                                <i class="bi bi-people text-primary me-1"></i> Deteksi Multi Wajah
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200 shadow-2xs">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="detect_look_away" value="1" id="swGaze" checked>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium cursor-pointer" for="swGaze">
                                <i class="bi bi-eye text-primary me-1"></i> Deteksi Lirik Mata
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200 shadow-2xs">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="detect_secondary_device" value="1" id="swSecDev" checked>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium cursor-pointer" for="swSecDev">
                                <i class="bi bi-phone text-primary me-1"></i> Deteksi HP Tambahan
                            </label>
                        </div>
                    </div>

                    <!-- Randomisasi & Tampilan Nilai -->
                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200 shadow-2xs">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="randomize_questions" value="1" id="swRandQ" checked>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium cursor-pointer" for="swRandQ">
                                <i class="bi bi-shuffle text-info me-1"></i> Acak Urutan Soal
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200 shadow-2xs">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="randomize_options" value="1" id="swRandOpt" checked>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium cursor-pointer" for="swRandOpt">
                                <i class="bi bi-arrow-repeat text-info me-1"></i> Acak Opsi Pilihan A-E
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200 shadow-2xs">
                            <input class="form-check-input ms-0 me-2" type="checkbox" name="show_result_immediately" value="1" id="swShowRes">
                            <label class="form-check-label fs-xs text-slate-800 fw-medium cursor-pointer" for="swShowRes">
                                <i class="bi bi-award text-warning me-1"></i> Tampilkan Nilai Instan
                            </label>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: JADWAL PELAKSANAAN & KELAS PESERTA -->
                <div class="d-flex align-items-center gap-2 mb-3 pb-2 border-bottom border-slate-100">
                    <div class="vui-icon-wrap vui-icon-warning" style="width: 28px; height: 28px; font-size: 0.875rem;">
                        <i class="bi bi-calendar3"></i>
                    </div>
                    <span class="fw-bold text-slate-900 fs-xs text-uppercase tracking-wider">Jadwal Pelaksanaan & Rombel Peserta</span>
                </div>

                <div class="row g-3 mb-4 p-3.5 bg-slate-50 bg-opacity-50 rounded-3 border border-slate-200">
                    <!-- Pemilihan Kelas Peserta -->
                    <div class="col-12">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Pilih Kelas / Rombel Peserta Ujian <span class="text-danger">*</span></label>
                        <?php if (isset($errors['class_ids'])): ?>
                            <div class="text-danger fs-3xs mb-2"><?= e($errors['class_ids']) ?></div>
                        <?php endif; ?>
                        <div class="row g-2">
                            <?php foreach ($classes as $c): ?>
                                <div class="col-sm-6 col-md-4 col-lg-3">
                                    <div class="p-2.5 rounded-3 bg-white border border-slate-200 shadow-2xs">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input ms-0 me-2" type="checkbox" name="class_ids[]" value="<?= $c['id'] ?>" id="class_<?= $c['id'] ?>" checked>
                                            <label class="form-check-label fs-xs fw-semibold text-slate-800 cursor-pointer" for="class_<?= $c['id'] ?>">
                                                <?= e($c['name']) ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Waktu Mulai & Selesai -->
                    <div class="col-md-5">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Waktu Mulai Ujian <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="start_time" class="form-control form-control-sm border-slate-200 fs-xs" value="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>" required>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Waktu Selesai Ujian <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="end_time" class="form-control form-control-sm border-slate-200 fs-xs" value="<?= date('Y-m-d\TH:i', strtotime('+3 hours')) ?>" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label fs-xs fw-semibold text-slate-800">Toleransi</label>
                        <div class="input-group input-group-sm">
                            <input type="number" name="grace_period_minutes" class="form-control border-slate-200 fs-xs" value="15" min="0" max="60">
                            <span class="input-group-text bg-white border-slate-200 text-muted fs-xs">Mnt</span>
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="d-flex justify-content-between align-items-center pt-3 border-top border-slate-100">
                    <a href="<?= base_url('guru/exams') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5 d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-arrow-left"></i>
                        <span>Batal</span>
                    </a>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs d-inline-flex align-items-center gap-1.5">
                        <i class="bi bi-check-lg"></i>
                        <span>Simpan & Terbitkan Jadwal Ujian</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
