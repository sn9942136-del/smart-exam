<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<div class="row justify-content-center">
    <div class="col-lg-10">
        <div class="card border-0 shadow-xs rounded-4 bg-white mb-4">
            <div class="card-header bg-white py-3.5 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                <div class="d-flex align-items-center gap-2">
                    <a href="<?= base_url('guru/exams') ?>" class="btn btn-sm btn-light border-0 rounded-pill p-1 px-2.5 me-1 text-muted" title="Kembali"><i class="bi bi-arrow-left"></i></a>
                    <h5 class="fw-bold text-slate-900 mb-0 fs-6">Buat Jadwal & Pengaturan Ujian Baru</h5>
                </div>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fs-xs fw-medium">Jadwal Ujian</span>
            </div>

            <form method="POST" action="<?= base_url('guru/exams/store') ?>" class="p-4 needs-validation" novalidate>
                <?= csrf_field() ?>

                <!-- SECTION 1: INFORMASI UTAMA UJIAN -->
                <div class="form-section-title">
                    <i class="bi bi-card-checklist"></i>
                    <span>Informasi & Materi Ujian</span>
                </div>
                <div class="row g-3 mb-4 p-3.5 bg-slate-50 rounded-3 border border-slate-200">
                    <!-- Judul Ujian -->
                    <div class="col-md-8">
                        <label class="form-label">Judul Ujian <span class="text-danger">*</span></label>
                        <input type="text" name="title" class="form-control border-slate-200 <?= isset($errors['title']) ? 'is-invalid' : '' ?>" placeholder="Contoh: Penilaian Akhir Semester Ganjil Matematika X" value="<?= e($old['title'] ?? '') ?>" required>
                        <?php if (isset($errors['title'])): ?>
                            <div class="invalid-feedback"><?= e($errors['title']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Jenis Ujian -->
                    <div class="col-md-4">
                        <label class="form-label">Jenis / Kategori <span class="text-danger">*</span></label>
                        <?php $oldType = $old['type'] ?? 'daily_test'; ?>
                        <select name="type" class="form-select border-slate-200" required>
                            <option value="daily_test" <?= ($oldType === 'daily_test') ? 'selected' : '' ?>>Ulangan Harian</option>
                            <option value="midterm" <?= ($oldType === 'midterm') ? 'selected' : '' ?>>Penilaian Tengah Semester (PTS)</option>
                            <option value="final_exam" <?= ($oldType === 'final_exam') ? 'selected' : '' ?>>Penilaian Akhir Semester (PAS)</option>
                            <option value="quiz" <?= ($oldType === 'quiz') ? 'selected' : '' ?>>Kuis Singkat</option>
                            <option value="tryout" <?= ($oldType === 'tryout') ? 'selected' : '' ?>>Tryout Ujian</option>
                        </select>
                    </div>

                    <!-- Mata Pelajaran -->
                    <div class="col-md-6">
                        <label class="form-label">Mata Pelajaran <span class="text-danger">*</span></label>
                        <select name="subject_id" class="form-select border-slate-200 <?= isset($errors['subject_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih Mata Pelajaran --</option>
                            <?php foreach ($subjects as $s): ?>
                                <option value="<?= $s['id'] ?>" <?= ((string)($old['subject_id'] ?? '') === (string)$s['id']) ? 'selected' : '' ?>>
                                    <?= e($s['name']) ?> (<?= e($s['code']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['subject_id'])): ?>
                            <div class="invalid-feedback"><?= e($errors['subject_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Bank Soal Sumber -->
                    <div class="col-md-6">
                        <label class="form-label">Pilih Bank Soal Materi <span class="text-danger">*</span></label>
                        <select name="question_bank_id" class="form-select border-slate-200 <?= isset($errors['question_bank_id']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Pilih Bank Soal --</option>
                            <?php foreach ($questionBanks as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= ((string)($old['question_bank_id'] ?? '') === (string)$b['id']) ? 'selected' : '' ?>>
                                    <?= e($b['title']) ?> (<?= (int)$b['total_questions'] ?> Butir Soal)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['question_bank_id'])): ?>
                            <div class="invalid-feedback"><?= e($errors['question_bank_id']) ?></div>
                        <?php endif; ?>
                    </div>

                    <!-- Durasi & Passing Grade -->
                    <div class="col-md-4">
                        <label class="form-label">Durasi Pengerjaan (Menit) <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <input type="number" name="duration_minutes" class="form-control border-slate-200 <?= isset($errors['duration_minutes']) ? 'is-invalid' : '' ?>" min="5" max="360" value="<?= e($old['duration_minutes'] ?? '90') ?>" required>
                            <span class="input-group-text bg-slate-50 border-slate-200 text-muted">Menit</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Standar Kelulusan (KKM) <span class="text-danger">*</span></label>
                        <input type="number" step="0.5" name="passing_grade" class="form-control border-slate-200 <?= isset($errors['passing_grade']) ? 'is-invalid' : '' ?>" min="0" max="100" value="<?= e($old['passing_grade'] ?? '75.00') ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Status Awal</label>
                        <select name="status" class="form-select border-slate-200">
                            <option value="published" selected>Published (Siap Digunakan)</option>
                            <option value="draft">Draft (Disimpan Sementara)</option>
                        </select>
                    </div>

                    <!-- Petunjuk / Deskripsi -->
                    <div class="col-12">
                        <label class="form-label">Petunjuk Pengerjaan Ujian</label>
                        <textarea name="description" class="form-control border-slate-200" rows="2" placeholder="Petunjuk khusus bagi peserta didik..."><?= e($old['description'] ?? '') ?></textarea>
                    </div>
                </div>

                <!-- SECTION 2: ATURAN PENGATURAN AI PROCTORING & DUAL KAMERA -->
                <div class="form-section-title">
                    <i class="bi bi-shield-lock"></i>
                    <span>Pengawasan Dual Kamera AI & Anti-Kecurangan</span>
                </div>
                <div class="row g-3 mb-4 p-3.5 bg-slate-50 rounded-3 border border-slate-200">
                    <!-- Dual Cam Switch -->
                    <div class="col-md-6">
                        <div class="form-check p-3 bg-white rounded-3 border border-slate-200">
                            <input class="form-check-input" type="checkbox" name="is_dual_cam_required" value="1" id="switchDualCam" checked>
                            <label class="form-check-label fw-bold text-slate-800 fs-sm" for="switchDualCam">
                                <i class="bi bi-camera-video text-danger me-1"></i> Wajibkan Dual Camera AI
                                <span class="d-block fs-xs text-muted fw-normal mt-0.5">Webcam Depan Laptop + Kamera HP Samping terhubung aktif.</span>
                            </label>
                        </div>
                    </div>

                    <!-- Lock Fullscreen -->
                    <div class="col-md-6">
                        <div class="form-check p-3 bg-white rounded-3 border border-slate-200">
                            <input class="form-check-input" type="checkbox" name="lock_browser" value="1" id="switchLockBrowser" checked>
                            <label class="form-check-label fw-bold text-slate-800 fs-sm" for="switchLockBrowser">
                                <i class="bi bi-fullscreen text-primary me-1"></i> Kunci Mode Fullscreen Anti-Contek
                                <span class="d-block fs-xs text-muted fw-normal mt-0.5">Layar ujian terkunci penuh dan mendeteksi upaya keluar layar.</span>
                            </label>
                        </div>
                    </div>

                    <!-- AI Violations Checks -->
                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200">
                            <input class="form-check-input" type="checkbox" name="detect_multiple_faces" value="1" id="swMultiFace" checked>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium" for="swMultiFace">
                                Deteksi Multi Wajah
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200">
                            <input class="form-check-input" type="checkbox" name="detect_look_away" value="1" id="swGaze" checked>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium" for="swGaze">
                                Deteksi Lirik Mata
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200">
                            <input class="form-check-input" type="checkbox" name="detect_secondary_device" value="1" id="swSecDev" checked>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium" for="swSecDev">
                                Deteksi HP Tambahan
                            </label>
                        </div>
                    </div>

                    <!-- Randomisasi -->
                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200">
                            <input class="form-check-input" type="checkbox" name="randomize_questions" value="1" id="swRandQ" checked>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium" for="swRandQ">
                                Acak Urutan Soal
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200">
                            <input class="form-check-input" type="checkbox" name="randomize_options" value="1" id="swRandOpt" checked>
                            <label class="form-check-label fs-xs text-slate-800 fw-medium" for="swRandOpt">
                                Acak Pilihan Opsi A-E
                            </label>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="form-check p-2.5 bg-white rounded-3 border border-slate-200">
                            <input class="form-check-input" type="checkbox" name="show_result_immediately" value="1" id="swShowRes">
                            <label class="form-check-label fs-xs text-slate-800 fw-medium" for="swShowRes">
                                Tampilkan Nilai Instan
                            </label>
                        </div>
                    </div>
                </div>

                <!-- SECTION 3: JADWAL PELAKSANAAN & KELAS PESERTA -->
                <div class="form-section-title">
                    <i class="bi bi-calendar3"></i>
                    <span>Jadwal Pelaksanaan & Rombel Peserta</span>
                </div>
                <div class="row g-3 mb-4 p-3.5 bg-slate-50 rounded-3 border border-slate-200">
                    <!-- Pemilihan Kelas Peserta -->
                    <div class="col-12">
                        <label class="form-label">Pilih Kelas / Rombel Peserta Ujian <span class="text-danger">*</span></label>
                        <?php if (isset($errors['class_ids'])): ?>
                            <div class="text-danger fs-xs mb-2"><?= e($errors['class_ids']) ?></div>
                        <?php endif; ?>
                        <div class="row g-2">
                            <?php foreach ($classes as $c): ?>
                                <div class="col-sm-6 col-md-4 col-lg-3">
                                    <div class="p-2.5 rounded-3 bg-white border border-slate-200">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" name="class_ids[]" value="<?= $c['id'] ?>" id="class_<?= $c['id'] ?>" checked>
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
                        <label class="form-label">Waktu Mulai Ujian <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="start_time" class="form-control border-slate-200" value="<?= date('Y-m-d\TH:i', strtotime('+1 hour')) ?>" required>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label">Waktu Selesai Ujian <span class="text-danger">*</span></label>
                        <input type="datetime-local" name="end_time" class="form-control border-slate-200" value="<?= date('Y-m-d\TH:i', strtotime('+3 hours')) ?>" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label">Toleransi</label>
                        <div class="input-group">
                            <input type="number" name="grace_period_minutes" class="form-control border-slate-200" value="15" min="0" max="60">
                            <span class="input-group-text bg-slate-50 border-slate-200 text-muted">Mnt</span>
                        </div>
                    </div>
                </div>

                <!-- Action Footer -->
                <div class="d-flex justify-content-between align-items-center pt-3 border-top border-slate-100">
                    <a href="<?= base_url('guru/exams') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">
                        <i class="bi bi-arrow-left me-1"></i> Batal
                    </a>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs">
                        <i class="bi bi-check-lg me-1.5"></i> Simpan & Terbitkan Jadwal Ujian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
