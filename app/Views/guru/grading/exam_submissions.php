<div class="container-fluid py-2">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 fs-xs">
                    <li class="breadcrumb-item"><a href="<?= base_url('guru/dashboard') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item"><a href="<?= base_url('guru/grading') ?>" class="text-decoration-none">Penilaian</a></li>
                    <li class="breadcrumb-item active" aria-current="page">Penyerahan Ujian</li>
                </ol>
            </nav>
            <h4 class="fw-bold text-slate-900 mb-0 d-flex align-items-center gap-2 fs-5">
                <i class="bi bi-person-check text-primary"></i> <?= e($exam['title']) ?>
            </h4>
            <p class="text-muted fs-xs mb-0">
                Kode: <strong class="text-dark font-monospace"><?= e($exam['code']) ?></strong> | 
                KKM: <strong class="text-primary"><?= number_format((float)$exam['passing_grade'], 1) ?></strong> | 
                Durasi: <strong class="text-dark"><?= (int)$exam['duration_minutes'] ?> Menit</strong>
            </p>
        </div>
        <div class="d-flex flex-wrap gap-2">
            <a href="<?= base_url('guru/grading') ?>" class="btn btn-sm btn-outline-secondary d-flex align-items-center gap-1.5 rounded-pill px-3.5">
                <i class="bi bi-arrow-left"></i> Kembali
            </a>
            
            <!-- Tombol Koreksi Otomatis -->
            <button type="button" class="btn btn-sm btn-outline-primary d-flex align-items-center gap-1.5 rounded-pill px-3.5" onclick="GradingModule.triggerAutoGrade(<?= (int)$exam['id'] ?>)">
                <i class="bi bi-arrow-repeat"></i> Koreksi Otomatis PG
            </button>

            <!-- Tombol Publikasi Nilai (UC-24) -->
            <?php if (!empty($summary['is_all_published'])): ?>
                <button type="button" class="btn btn-sm btn-success d-flex align-items-center gap-1.5 rounded-pill px-3.5 shadow-xs" disabled>
                    <i class="bi bi-check-all fs-6"></i> Nilai Terpublikasi
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-sm btn-success d-flex align-items-center gap-1.5 rounded-pill px-3.5 shadow-xs fw-semibold" onclick="GradingModule.publishExamResults(<?= (int)$exam['id'] ?>)">
                    <i class="bi bi-send-check fs-6"></i> Publikasikan Nilai
                </button>
            <?php endif; ?>

            <!-- Dropdown Ekspor Laporan (UC-25) -->
            <div class="dropdown">
                <button class="btn btn-sm btn-primary dropdown-toggle d-flex align-items-center gap-1.5 rounded-pill px-3.5 shadow-xs fw-semibold" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download"></i> Ekspor Laporan
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-xs border-slate-100 rounded-3 fs-xs">
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2 text-danger py-1.5" href="<?= base_url('guru/reports/exam/' . $exam['id'] . '/pdf') ?>" target="_blank">
                            <i class="bi bi-file-earmark-pdf-fill"></i> Ekspor PDF (Dompdf)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2 text-success py-1.5" href="<?= base_url('guru/reports/exam/' . $exam['id'] . '/excel') ?>">
                            <i class="bi bi-file-earmark-spreadsheet-fill"></i> Ekspor Excel (.xlsx)
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Summary Metrics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white p-3.5">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-xs text-muted fw-medium">Total Peserta</span>
                        <h4 class="fw-bold mb-0 mt-1 text-slate-900 fs-5"><?= (int)$summary['total_submissions'] ?> <span class="fs-xs fw-normal text-muted">siswa</span></h4>
                    </div>
                    <div class="p-2.5 bg-primary-subtle text-primary rounded-3 fs-5">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white p-3.5">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-xs text-muted fw-medium">Tingkat Kelulusan</span>
                        <h4 class="fw-bold mb-0 mt-1 text-success fs-5"><?= $summary['pass_rate'] ?>% <span class="fs-xs fw-normal text-muted">(<?= $summary['total_passed'] ?> Lulus)</span></h4>
                    </div>
                    <div class="p-2.5 bg-success-subtle text-success rounded-3 fs-5">
                        <i class="bi bi-award-fill"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white p-3.5">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-xs text-muted fw-medium">Rata-Rata Nilai</span>
                        <h4 class="fw-bold mb-0 mt-1 text-info fs-5"><?= number_format((float)$summary['average_score'], 1) ?></h4>
                    </div>
                    <div class="p-2.5 bg-info-subtle text-info rounded-3 fs-5">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white p-3.5">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-xs text-muted fw-medium">Nilai Tertinggi / Terendah</span>
                        <h4 class="fw-bold mb-0 mt-1 text-slate-900 fs-5"><?= $summary['highest_score'] ?> / <span class="text-danger"><?= $summary['lowest_score'] ?></span></h4>
                    </div>
                    <div class="p-2.5 bg-warning-subtle text-warning-emphasis rounded-3 fs-5">
                        <i class="bi bi-sliders"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Toolbar -->
    <div class="card border-0 shadow-xs rounded-4 mb-4 bg-white">
        <div class="card-body p-3">
            <form method="GET" action="<?= base_url('guru/grading/exam/' . $exam['id']) ?>" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text bg-light border-slate-200 border-end-0 text-muted"><i class="bi bi-search"></i></span>
                        <input type="text" class="form-control border-slate-200 border-start-0 ps-0 fs-xs" name="search" placeholder="Cari nama siswa atau NIS..." value="<?= e($search ?? '') ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select class="form-select form-select-sm border-slate-200 fs-xs" name="status" onchange="this.form.submit()">
                        <option value="all">-- Semua Status Penilaian --</option>
                        <option value="passed" <?= ($statusFilter === 'passed') ? 'selected' : '' ?>>Hanya yang LULUS (&gt;= KKM)</option>
                        <option value="failed" <?= ($statusFilter === 'failed') ? 'selected' : '' ?>>Hanya yang REMEDIAL (&lt; KKM)</option>
                        <option value="pending_grading" <?= ($statusFilter === 'pending_grading') ? 'selected' : '' ?>>Perlu Koreksi Esai</option>
                        <option value="published" <?= ($statusFilter === 'published') ? 'selected' : '' ?>>Telah Dipublikasikan</option>
                    </select>
                </div>
                <div class="col-md-4 d-flex gap-2 justify-content-md-end">
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3.5 fw-semibold shadow-xs">Filter</button>
                    <?php if (!empty($search) || !empty($statusFilter)): ?>
                        <a href="<?= base_url('guru/grading/exam/' . $exam['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Student Submissions Table -->
    <div class="card border-0 shadow-xs rounded-4 overflow-hidden bg-white">
        <div class="card-header bg-white border-bottom border-slate-100 py-3.5 px-4 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold text-dark mb-0 fs-6">Daftar Hasil Penyerahan Peserta (<?= count($submissions) ?> Siswa)</h5>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0 fs-xs">
                <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                    <tr>
                        <th class="ps-4">No</th>
                        <th>NIS & Nama Peserta</th>
                        <th>Kelas</th>
                        <th>Nilai PG</th>
                        <th>Nilai Esai</th>
                        <th>Nilai Akhir</th>
                        <th>Status KKM</th>
                        <th>Integritas AI</th>
                        <th>Status Koreksi</th>
                        <th class="text-end pe-4">Aksi Koreksi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($submissions)): ?>
                        <tr>
                            <td colspan="10" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-secondary"></i>
                                Tidak ada data penyerahan ujian yang cocok dengan filter pencarian.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($submissions as $idx => $s): ?>
                            <?php 
                                $finalScore = (float)($s['final_score'] ?? 0.0);
                                $isPassed = !empty($s['is_passed']);
                                $integrity = (float)($s['integrity_score'] ?? 100.0);
                            ?>
                            <tr>
                                <td class="ps-4 fw-semibold text-muted"><?= $idx + 1 ?></td>
                                <td>
                                    <div class="fw-bold text-dark"><?= e($s['student_name']) ?></div>
                                    <span class="text-muted fs-xs font-monospace"><?= e($s['student_nis'] ?? $s['student_email']) ?></span>
                                </td>
                                <td>
                                    <span class="badge bg-slate-100 text-slate-700 rounded-pill px-2 py-0.5"><?= e($s['class_name'] ?? 'Reguler') ?></span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= number_format((float)($s['multiple_choice_score'] ?? 0), 1) ?></span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= number_format((float)($s['essay_score'] ?? 0), 1) ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold fs-6 <?= $isPassed ? 'text-success' : 'text-danger' ?>">
                                        <?= number_format($finalScore, 1) ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($isPassed): ?>
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 fw-medium">
                                            <i class="bi bi-check-lg"></i> LULUS
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger rounded-pill px-2.5 py-1 fw-medium">
                                            <i class="bi bi-x-lg"></i> REMEDIAL
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge <?= $integrity >= 80 ? 'bg-success-subtle text-success' : ($integrity >= 60 ? 'bg-warning-subtle text-warning-emphasis' : 'bg-danger-subtle text-danger') ?> rounded-pill px-2.5 py-1 fw-medium">
                                        <i class="bi bi-shield-check me-1"></i> <?= number_format($integrity, 0) ?>%
                                    </span>
                                </td>
                                <td>
                                    <?php if ($s['grading_status'] === 'pending_grading'): ?>
                                        <span class="badge bg-warning-subtle text-warning-emphasis rounded-pill px-2.5 py-1 fw-medium">
                                            <i class="bi bi-pencil me-1"></i> Perlu Koreksi Esai
                                        </span>
                                    <?php elseif ($s['grading_status'] === 'published'): ?>
                                        <span class="badge bg-success-subtle text-success rounded-pill px-2.5 py-1 fw-medium">
                                            <i class="bi bi-send-check me-1"></i> Terpublikasi
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-info-subtle text-info rounded-pill px-2.5 py-1 fw-medium">
                                            <i class="bi bi-check2 me-1"></i> Dinilai
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= base_url('guru/grading/session/' . $s['session_id']) ?>" class="btn btn-outline-primary rounded-pill px-2.5 d-flex align-items-center gap-1">
                                            <i class="bi bi-pencil-square"></i> Koreksi & Review
                                        </a>
                                        <a href="<?= base_url('guru/reports/session/' . $s['session_id'] . '/pdf') ?>" class="btn btn-outline-secondary rounded-pill px-2 ms-1" target="_blank" title="Unduh Rapor PDF Siswa">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </a>
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

<script>
const GradingModule = {
    async triggerAutoGrade(examId) {
        if (!confirm('Jalankan koreksi otomatis seluruh soal pilihan ganda untuk semua peserta?')) return;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';

            const res = await fetch(`${baseUrl}/guru/grading/auto-grade/${examId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const data = await res.json();
            if (data.success) {
                alert(data.message || 'Koreksi otomatis selesai.');
                window.location.reload();
            } else {
                alert(data.error || 'Terjadi kesalahan.');
            }
        } catch (e) {
            console.error(e);
            alert('Kesalahan jaringan.');
        }
    },

    async publishExamResults(examId) {
        if (!confirm('Konfirmasi: Publikasikan nilai ujian ini ke seluruh siswa? Nilai yang dipublikasikan akan dikunci secara resmi.')) return;

        try {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
            const baseUrl = document.querySelector('meta[name="base-url"]')?.getAttribute('content') || '';

            const res = await fetch(`${baseUrl}/guru/grading/publish/${examId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            const data = await res.json();
            if (data.success) {
                alert(data.message || 'Hasil ujian berhasil dipublikasikan!');
                window.location.reload();
            } else {
                alert(data.error || 'Gagal mempublikasikan nilai.');
            }
        } catch (e) {
            console.error(e);
            alert('Kesalahan jaringan.');
        }
    }
};
</script>
