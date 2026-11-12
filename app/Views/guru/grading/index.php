<div class="container-fluid py-2">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 fs-xs">
                    <li class="breadcrumb-item"><a href="<?= base_url('guru/dashboard') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Penilaian & Koreksi</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-slate-900 mb-0 d-flex align-items-center gap-2 fs-5">
                <i class="bi bi-check2-all text-primary"></i> Penilaian, Koreksi & Rapor Ujian
            </h4>
            <p class="text-muted fs-xs mb-0">Kelola koreksi esai guru, rilis publikasi hasil ujian, dan unduh laporan PDF/Excel.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('guru/exams') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5 d-flex align-items-center gap-1.5">
                <i class="bi bi-calendar-check"></i> Manajemen Ujian
            </a>
        </div>
    </div>

    <!-- Summary Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white p-3.5">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-xs text-muted fw-medium">Total Ujian</span>
                        <h4 class="fw-bold mb-0 mt-1 text-slate-900 fs-5"><?= (int)($summary['total_exams'] ?? count($exams)) ?> <span class="fs-xs fw-normal text-muted">paket</span></h4>
                    </div>
                    <div class="p-2.5 bg-primary-subtle text-primary rounded-3 fs-5">
                        <i class="bi bi-journal-check"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white p-3.5">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-xs text-muted fw-medium">Total Penyerahan</span>
                        <h4 class="fw-bold mb-0 mt-1 text-slate-900 fs-5"><?= (int)($summary['total_submissions'] ?? 0) ?> <span class="fs-xs fw-normal text-muted">lembar</span></h4>
                    </div>
                    <div class="p-2.5 bg-info-subtle text-info rounded-3 fs-5">
                        <i class="bi bi-people"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white p-3.5">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-xs text-muted fw-medium">Perlu Koreksi Esai</span>
                        <h4 class="fw-bold mb-0 mt-1 text-warning-emphasis fs-5"><?= (int)($summary['pending_grading_count'] ?? 0) ?> <span class="fs-xs fw-normal text-muted">antrean</span></h4>
                    </div>
                    <div class="p-2.5 bg-warning-subtle text-warning-emphasis rounded-3 fs-5">
                        <i class="bi bi-pencil-square"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white p-3.5">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-xs text-muted fw-medium">Telah Dipublikasikan</span>
                        <h4 class="fw-bold mb-0 mt-1 text-success fs-5"><?= (int)($summary['published_count'] ?? 0) ?> <span class="fs-xs fw-normal text-muted">siswa</span></h4>
                    </div>
                    <div class="p-2.5 bg-success-subtle text-success rounded-3 fs-5">
                        <i class="bi bi-send-check"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Exams Table Card -->
    <div class="card border-0 shadow-xs rounded-4 overflow-hidden bg-white">
        <div class="card-header bg-white border-bottom border-slate-100 py-3.5 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-0 fs-6">Daftar Paket Ujian untuk Evaluasi & Penilaian</h5>
                <span class="text-muted fs-xs">Pilih paket ujian untuk memeriksa lembar jawaban, memberi nilai esai, dan mengekspor laporan</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-xs">
                <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                    <tr>
                        <th class="ps-4">Kode & Judul Ujian</th>
                        <th>Mata Pelajaran</th>
                        <th>KKM</th>
                        <th>Soal / Peserta</th>
                        <th>Rata-Rata Nilai</th>
                        <th>Status Publikasi</th>
                        <th class="text-end pe-4">Aksi Penilaian & Laporan</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($exams)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state py-4 text-center">
                                    <div class="empty-state-icon mx-auto mb-2 bg-info-subtle text-info shadow-xs rounded-circle p-3 d-inline-block">
                                        <i class="bi bi-journal-check fs-2"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">Belum Ada Ujian untuk Dinilai</h6>
                                    <p class="text-muted fs-xs mb-3">Tidak ada paket ujian yang memerlukan pemeriksaan esai atau belum ada peserta yang menyelesaikan ujian.</p>
                                    <a href="<?= base_url('guru/exams') ?>" class="btn btn-sm btn-primary rounded-pill px-3.5 fw-semibold shadow-xs">
                                        <i class="bi bi-calendar-check me-1.5"></i> Lihat Jadwal Ujian
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($exams as $ex): ?>
                            <?php 
                                $submissions = (int)($ex['total_submissions'] ?? 0);
                                $pending = (int)($ex['pending_grading_count'] ?? 0);
                                $avgScore = (float)($ex['average_score'] ?? 0.0);
                                $isPublished = ($ex['published_submissions'] > 0 && $ex['published_submissions'] === $submissions && $submissions > 0);
                            ?>
                            <tr>
                                <td class="ps-4">
                                    <span class="badge bg-slate-100 text-slate-700 font-monospace rounded-pill px-2 py-0.5 mb-1 fs-xs"><?= e($ex['code']) ?></span>
                                    <div class="fw-bold text-dark"><?= e($ex['title']) ?></div>
                                </td>
                                <td>
                                    <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fw-medium"><?= e($ex['subject_name'] ?? 'Umum') ?></span>
                                    <div class="text-muted fs-xs mt-0.5"><?= e($ex['type'] ?? 'midterm') ?> &bull; <?= (int)$ex['duration_minutes'] ?> Menit</div>
                                </td>
                                <td>
                                    <span class="badge bg-slate-100 text-slate-700 rounded-pill px-2.5 py-1 fw-medium"><?= number_format((float)$ex['passing_grade'], 0) ?></span>
                                </td>
                                <td>
                                    <div><strong><?= (int)($ex['question_count'] ?? 0) ?></strong> Soal</div>
                                    <span class="text-muted fs-xs"><?= $submissions ?> Peserta Selesai</span>
                                </td>
                                <td>
                                    <?php if ($submissions > 0): ?>
                                        <span class="fw-bold fs-6 <?= $avgScore >= (float)$ex['passing_grade'] ? 'text-success' : 'text-danger' ?>">
                                            <?= number_format($avgScore, 1) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted fs-xs">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($pending > 0): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2.5 py-1 fw-medium">
                                            <i class="bi bi-hourglass-split me-1"></i> <?= $pending ?> Esai Belum Dinilai
                                        </span>
                                    <?php elseif ($isPublished): ?>
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 fw-medium">
                                            <i class="bi bi-check2-all me-1"></i> Terpublikasi
                                        </span>
                                    <?php elseif ($submissions > 0): ?>
                                        <span class="badge bg-info-subtle text-info rounded-pill px-2.5 py-1 fw-medium">
                                            <i class="bi bi-check-circle me-1"></i> Siap Publikasi
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary-subtle text-secondary rounded-pill px-2.5 py-1 fw-medium">
                                            Belum Ada Peserta
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('guru/grading/exam/' . $ex['id']) ?>" class="btn btn-primary rounded-start-pill px-3 shadow-xs fw-medium">
                                            <i class="bi bi-card-checklist me-1"></i> Periksa Hasil
                                        </a>
                                        <button type="button" class="btn btn-outline-secondary rounded-end-pill dropdown-toggle dropdown-toggle-split px-2" data-bs-toggle="dropdown" aria-expanded="false">
                                            <span class="visually-hidden">Toggle Dropdown</span>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-end shadow-xs border-slate-100 rounded-3 fs-xs">
                                            <li><h6 class="dropdown-header">Ekspor Laporan</h6></li>
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center gap-2 text-danger" href="<?= base_url('guru/reports/exam/' . $ex['id'] . '/pdf') ?>" target="_blank">
                                                    <i class="bi bi-file-earmark-pdf-fill"></i> Unduh PDF (Dompdf)
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item d-flex align-items-center gap-2 text-success" href="<?= base_url('guru/reports/exam/' . $ex['id'] . '/excel') ?>">
                                                    <i class="bi bi-file-earmark-spreadsheet-fill"></i> Unduh Excel (.xlsx)
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
