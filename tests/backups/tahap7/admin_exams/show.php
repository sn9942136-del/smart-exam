<?php
// app/Views/admin/exams/show.php
$examCode = $exam['exam_code'] ?? $exam['code'] ?? 'EXAM';
$examTitle = $exam['title'] ?? 'Ujian';
$activeToken = $exam['active_token']['token'] ?? $exam['active_token']['token_code'] ?? null;
?>

<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Header & Actions -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 flex-wrap mb-1">
            <h4 class="fw-bold text-slate-900 mb-0 fs-5"><?= e($examTitle) ?></h4>
            <span class="badge bg-slate-100 text-slate-700 font-monospace border border-slate-200 px-2 py-0.5 rounded-pill fs-3xs"><?= e($examCode) ?></span>
            <?php
            $statusColors = [
                'draft' => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
                'published' => 'bg-info-subtle text-info border border-info-subtle',
                'scheduled' => 'bg-primary-subtle text-primary border border-primary-subtle',
                'ongoing' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                'in_progress' => 'bg-warning-subtle text-warning-emphasis border border-warning-subtle',
                'completed' => 'bg-success-subtle text-success border border-success-subtle',
                'archived' => 'bg-slate-100 text-slate-700 border border-slate-200'
            ];
            $stColor = $statusColors[$exam['status']] ?? 'bg-secondary-subtle text-secondary';
            ?>
            <span class="badge <?= $stColor ?> px-2.5 py-1 text-uppercase fs-3xs fw-semibold rounded-pill">
                <?= e($exam['status']) ?>
            </span>
        </div>
        <p class="text-muted fs-3xs mb-0">
            <i class="bi bi-book me-1"></i><?= e($exam['subject_name'] ?? 'Mata Pelajaran') ?> 
            <span class="mx-2">&bull;</span>
            <i class="bi bi-person me-1"></i>Guru Pengampu: <strong><?= e($exam['created_by_name'] ?? $exam['teacher_name'] ?? '-') ?></strong> (<?= e($exam['created_by_email'] ?? $exam['teacher_email'] ?? '-') ?>)
        </p>
    </div>

    <div class="d-flex flex-wrap gap-2">
        <a href="<?= base_url('admin/exams/preview/' . $exam['id']) ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5 shadow-xs">
            <i class="bi bi-eye me-1.5"></i> Preview
        </a>

        <a href="<?= base_url('admin/exams/edit/' . $exam['id']) ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3.5 shadow-xs">
            <i class="bi bi-calendar-event me-1.5"></i> Edit Jadwal
        </a>

        <!-- Status Dropdown -->
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3.5 shadow-xs" type="button" data-bs-toggle="dropdown">
                <i class="bi bi-flag me-1"></i> Status Ujian
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm border-0 fs-xs">
                <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="updateAdminStatus('draft')">Draft</a></li>
                <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="updateAdminStatus('scheduled')">Scheduled</a></li>
                <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="updateAdminStatus('published')">Published</a></li>
                <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="updateAdminStatus('ongoing')">Ongoing (Aktif)</a></li>
                <li><a class="dropdown-item py-1.5" href="javascript:void(0)" onclick="updateAdminStatus('completed')">Completed</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item py-1.5 text-danger" href="javascript:void(0)" onclick="updateAdminStatus('archived')">Archived</a></li>
            </ul>
        </div>

        <form id="adminStatusForm" action="<?= base_url('admin/exams/status/' . $exam['id']) ?>" method="POST" class="d-none">
            <?= csrf_field() ?>
            <input type="hidden" name="status" id="adminTargetStatus" value="">
        </form>

        <a href="<?= base_url('admin/exams') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3" title="Kembali">
            <i class="bi bi-arrow-left"></i>
        </a>
    </div>
</div>

<!-- Quick Stats & Token Card -->
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card border-0 shadow-xs rounded-4 bg-white p-3 h-100">
            <small class="text-muted text-uppercase fw-semibold fs-3xs d-block">Struktur Soal & Durasi</small>
            <div class="d-flex align-items-baseline gap-2 mt-1">
                <h4 class="fw-bold mb-0 text-slate-900 fs-5"><?= count($exam['questions'] ?? []) ?> Soal</h4>
                <span class="text-muted fs-xs">| <?= $exam['duration_minutes'] ?> Menit (KKM: <?= (int)($exam['passing_grade'] ?? 75) ?>)</span>
            </div>
            <small class="text-muted fs-3xs mt-2 d-block">Bank Soal: <strong><?= e($exam['bank_title'] ?? '-') ?></strong></small>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-xs rounded-4 bg-white p-3 h-100">
            <small class="text-muted text-uppercase fw-semibold fs-3xs d-block">Peserta & Rombel</small>
            <div class="d-flex align-items-baseline gap-2 mt-1">
                <h4 class="fw-bold mb-0 text-slate-900 fs-5"><?= count($exam['participants'] ?? []) ?> Siswa</h4>
                <span class="text-muted fs-xs">dalam <?= count($exam['schedules'] ?? []) ?> Rombel</span>
            </div>
            <small class="text-muted fs-3xs mt-2 d-block">Tahun: <?= e($exam['academic_year_name'] ?? '-') ?> (<?= e($exam['semester_name'] ?? '-') ?>)</small>
        </div>
    </div>

    <div class="col-md-4">
        <div class="card border-0 shadow-xs rounded-4 bg-primary-subtle border border-primary-subtle p-3 h-100">
            <div class="d-flex align-items-center justify-content-between">
                <div>
                    <small class="text-primary text-uppercase fw-bold fs-3xs d-block">Token Ujian Terpusat</small>
                    <?php if ($activeToken): ?>
                        <div class="d-flex align-items-center gap-2 mt-1">
                            <span class="font-monospace fw-bold fs-5 text-primary tracking-wider" id="examTokenCode"><?= e($activeToken) ?></span>
                            <button type="button" class="btn btn-xs btn-outline-primary rounded-circle p-1" title="Salin Token" onclick="copyToken('<?= e($activeToken) ?>')">
                                <i class="bi bi-clipboard"></i>
                            </button>
                        </div>
                    <?php else: ?>
                        <span class="badge bg-secondary-subtle text-secondary rounded-pill mt-1 fs-3xs">Nonaktif</span>
                    <?php endif; ?>
                </div>
                <form action="<?= base_url('admin/exams/token/refresh/' . $exam['id']) ?>" method="POST">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn btn-sm btn-primary rounded-pill px-3 fw-semibold shadow-xs" onclick="return confirm('Generate token baru?')">
                        <i class="bi bi-arrow-clockwise me-1"></i> Rilis Token
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Schedules and AI Rules -->
<div class="row g-4 mb-4">
    <!-- Left: Schedules -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-xs rounded-4 bg-white p-4 h-100">
            <h6 class="fw-bold text-slate-900 mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-calendar3 text-primary"></i>
                <span>Jadwal Pelaksanaan Per Kelas</span>
            </h6>
            <?php if (empty($exam['schedules'])): ?>
                <div class="empty-state py-4 text-center">
                    <i class="bi bi-calendar-x fs-2 text-muted mb-2 d-block"></i>
                    <p class="text-muted fs-xs mb-0">Belum ada jadwal kelas yang diatur.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                            <tr>
                                <th class="py-2.5 px-3">Kelas</th>
                                <th class="py-2.5 px-3">Mulai</th>
                                <th class="py-2.5 px-3">Selesai</th>
                                <th class="py-2.5 px-3">Toleransi</th>
                                <th class="py-2.5 px-3">Status</th>
                            </tr>
                        </thead>
                        <tbody class="fs-xs">
                            <?php foreach ($exam['schedules'] as $sch): ?>
                                <tr>
                                    <td class="px-3 fw-semibold text-primary"><?= e($sch['class_name'] ?? 'Semua') ?></td>
                                    <td class="px-3"><?= date('d M, H:i', strtotime($sch['start_time'])) ?></td>
                                    <td class="px-3"><?= date('d M, H:i', strtotime($sch['end_time'])) ?></td>
                                    <td class="px-3"><?= $sch['grace_period_minutes'] ?>m</td>
                                    <td class="px-3">
                                        <?php
                                        $schStat = $sch['status'] ?? 'scheduled';
                                        $schBadge = [
                                            'active' => 'bg-success-subtle text-success border-success-subtle',
                                            'scheduled' => 'bg-primary-subtle text-primary border-primary-subtle',
                                            'expired' => 'bg-secondary-subtle text-secondary border-secondary-subtle'
                                        ];
                                        ?>
                                        <span class="badge <?= $schBadge[$schStat] ?? 'bg-secondary-subtle text-secondary' ?> text-uppercase rounded-pill px-2.5 py-0.5 fs-3xs">
                                            <?= e($schStat) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Right: AI Rules Overview -->
    <div class="col-lg-6">
        <div class="card border-0 shadow-xs rounded-4 bg-white p-4 h-100">
            <h6 class="fw-bold text-slate-900 mb-3 d-flex align-items-center gap-2">
                <i class="bi bi-shield-lock text-danger"></i>
                <span>Parameter Keamanan & AI Proctoring</span>
            </h6>
            <?php $rules = $exam['rules'] ?? []; ?>
            <div class="d-flex flex-column gap-2.5">
                <div class="p-2.5 rounded-3 border d-flex align-items-center justify-content-between <?= ($exam['require_dual_camera'] ?? 1) ? 'bg-danger-subtle border-danger-subtle text-danger' : 'bg-slate-50 border-slate-200' ?>">
                    <span class="fw-semibold fs-xs"><i class="bi bi-camera-video me-2"></i>Dual Camera Mode (Webcam & HP)</span>
                    <span class="badge <?= ($exam['require_dual_camera'] ?? 1) ? 'bg-danger' : 'bg-secondary' ?> rounded-pill px-2.5 py-0.5 fs-3xs"><?= ($exam['require_dual_camera'] ?? 1) ? 'AKTIF' : 'OFF' ?></span>
                </div>

                <div class="p-2.5 rounded-3 border d-flex align-items-center justify-content-between <?= ($exam['enable_ai_proctoring'] ?? 1) ? 'bg-primary-subtle border-primary-subtle text-primary' : 'bg-slate-50 border-slate-200' ?>">
                    <span class="fw-semibold fs-xs"><i class="bi bi-cpu me-2"></i>AI Proctoring Real-time</span>
                    <span class="badge <?= ($exam['enable_ai_proctoring'] ?? 1) ? 'bg-primary' : 'bg-secondary' ?> rounded-pill px-2.5 py-0.5 fs-3xs"><?= ($exam['enable_ai_proctoring'] ?? 1) ? 'AKTIF' : 'OFF' ?></span>
                </div>

                <div class="card border border-slate-200 rounded-3 p-3 bg-slate-50 mt-1">
                    <div class="row g-2 fs-xs">
                        <div class="col-6"><i class="bi <?= ($rules['detect_multiple_faces'] ?? 1) ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' ?> me-1"></i> Multiple Face</div>
                        <div class="col-6"><i class="bi <?= ($rules['detect_look_away'] ?? 1) ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' ?> me-1"></i> Look Away</div>
                        <div class="col-6"><i class="bi <?= ($rules['detect_audio_noise'] ?? 1) ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' ?> me-1"></i> Audio Noise</div>
                        <div class="col-6"><i class="bi <?= ($rules['detect_secondary_device'] ?? 1) ? 'bi-check-circle-fill text-success' : 'bi-x-circle text-muted' ?> me-1"></i> Secondary Device</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Riwayat Token Ujian Terpusat Card -->
<div class="card border-0 shadow-xs rounded-4 bg-white p-4">
    <div class="d-flex align-items-center justify-content-between mb-3">
        <div>
            <h6 class="fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-key-fill text-primary"></i>
                <span>Riwayat & Manajemen Token Ujian Terpusat</span>
            </h6>
            <p class="text-muted fs-3xs mb-0">Kelola dan pantau seluruh token akses ujian 6-digit.</p>
        </div>
        <form action="<?= base_url('admin/exams/token/refresh/' . $exam['id']) ?>" method="POST">
            <?= csrf_field() ?>
            <button type="submit" class="btn btn-primary rounded-pill btn-sm px-3.5 fw-semibold shadow-xs" onclick="return confirm('Rilis token baru sekarang?')">
                <i class="bi bi-plus-lg me-1"></i> Rilis Token Baru
            </button>
        </form>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <th class="py-2.5 px-3">Kode Token</th>
                    <th class="py-2.5 px-3">Valid Dari</th>
                    <th class="py-2.5 px-3">Valid Sampai</th>
                    <th class="py-2.5 px-3">Status</th>
                    <th class="py-2.5 px-3 text-end">Aksi</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php 
                $tokensList = $exam['tokens'] ?? $exam['all_tokens'] ?? [];
                if (empty($tokensList)): 
                ?>
                    <tr>
                        <td colspan="5" class="text-center text-muted py-4">
                            <span class="fs-xs">Belum ada token yang diterbitkan.</span>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($tokensList as $tok): 
                        $tCode = $tok['token'] ?? $tok['token_code'] ?? '';
                    ?>
                        <tr>
                            <td class="px-3">
                                <span class="font-monospace fw-bold fs-6 px-2.5 py-1 bg-light rounded-pill border border-slate-200 text-primary">
                                    <?= e($tCode) ?>
                                </span>
                            </td>
                            <td class="px-3"><?= date('d M Y, H:i', strtotime($tok['valid_from'])) ?> WIB</td>
                            <td class="px-3"><?= date('d M Y, H:i', strtotime($tok['valid_until'])) ?> WIB</td>
                            <td class="px-3">
                                <span class="badge <?= !empty($tok['is_active']) ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle' ?> rounded-pill px-2.5 py-0.5 fs-3xs">
                                    <?= !empty($tok['is_active']) ? 'AKTIF' : 'NONAKTIF' ?>
                                </span>
                            </td>
                            <td class="px-3 text-end">
                                <div class="d-flex justify-content-end gap-1">
                                    <form action="<?= base_url('admin/exams/token/toggle/' . $tok['id']) ?>" method="POST" class="d-inline">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn btn-xs btn-outline-secondary rounded-pill px-2" title="Toggle Aktif/Nonaktif">
                                            <i class="bi bi-power"></i>
                                        </button>
                                    </form>
                                    <button type="button" class="btn btn-xs btn-outline-primary rounded-pill px-2" onclick="copyToken('<?= e($tCode) ?>')" title="Salin">
                                        <i class="bi bi-clipboard"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function copyToken(code) {
    navigator.clipboard.writeText(code).then(() => {
        App.toast('Token ' + code + ' berhasil disalin!', 'success');
    }).catch(err => {
        App.toast('Gagal menyalin token', 'danger');
    });
}

function updateAdminStatus(status) {
    if (confirm('Ubah status ujian ke: ' + status.toUpperCase() + '?')) {
        document.getElementById('adminTargetStatus').value = status;
        document.getElementById('adminStatusForm').submit();
    }
}
</script>
