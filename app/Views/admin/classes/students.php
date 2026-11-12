<!-- Breadcrumb Navigation -->
<?php include __DIR__ . '/../../components/breadcrumb.php'; ?>

<!-- Class Header Information Card -->
<div class="card border-0 shadow-xs rounded-4 bg-white mb-4 overflow-hidden">
    <div class="card-body p-4">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3">
            <div>
                <div class="d-flex align-items-center gap-2 mb-2">
                    <a href="<?= base_url('admin/classes') ?>" class="btn btn-sm btn-light border-0 rounded-circle p-1.5" title="Kembali ke Daftar Kelas">
                        <i class="bi bi-arrow-left"></i>
                    </a>
                    <h4 class="fw-bold text-slate-900 mb-0 fs-5 d-flex align-items-center gap-2">
                        <i class="bi bi-mortarboard-fill text-primary"></i>
                        <span>Siswa Kelas: <?= e($class['name']) ?></span>
                    </h4>
                    <span class="badge rounded-pill bg-primary-subtle text-primary fs-xs px-2.5 py-1">
                        Kelas <?= e($class['grade_level']) ?> <?= !empty($class['major']) ? '- ' . e($class['major']) : '' ?>
                    </span>
                </div>
                <div class="d-flex flex-wrap align-items-center gap-3 text-muted fs-xs">
                    <span><i class="bi bi-calendar-event me-1 text-slate-400"></i> Tahun Ajaran: <strong class="text-slate-700">T.A. <?= e($class['academic_year_name']) ?></strong></span>
                    <span class="text-slate-300">•</span>
                    <span><i class="bi bi-person-badge me-1 text-slate-400"></i> Wali Kelas: 
                        <?php if (!empty($class['homeroom_teacher_name'])): ?>
                            <strong class="text-slate-800"><?= e($class['homeroom_teacher_name']) ?></strong>
                            <?php if (!empty($class['homeroom_teacher_nip'])): ?>
                                <span class="font-monospace text-muted">(NIP: <?= e($class['homeroom_teacher_nip']) ?>)</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <em class="text-muted">Belum Ditentukan</em>
                        <?php endif; ?>
                    </span>
                    <span class="text-slate-300">•</span>
                    <span><i class="bi bi-people me-1 text-slate-400"></i> Total Terdaftar: <strong class="text-success"><?= count($enrolledStudents) ?> Siswa</strong></span>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex gap-2">
                <a href="<?= base_url('admin/classes') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5">
                    <i class="bi bi-arrow-left me-1"></i> Kembali
                </a>
                <button type="button" class="btn btn-sm btn-primary rounded-pill px-3.5 fw-semibold shadow-xs" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                    <i class="bi bi-person-plus-fill me-1.5"></i> Tambah Siswa ke Kelas
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Student Statistics Counters -->
<?php
$maleCount = 0;
$femaleCount = 0;
foreach ($enrolledStudents as $s) {
    if (($s['gender'] ?? '') === 'L') {
        $maleCount++;
    } elseif (($s['gender'] ?? '') === 'P') {
        $femaleCount++;
    }
}
?>
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-xs rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3">
            <div class="rounded-circle p-2.5 bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="bi bi-people-fill fs-5"></i>
            </div>
            <div>
                <div class="fs-4 fw-bold text-slate-900"><?= count($enrolledStudents) ?></div>
                <div class="fs-xs text-muted">Total Siswa Aktif</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-xs rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3">
            <div class="rounded-circle p-2.5 bg-info-subtle text-info d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="bi bi-gender-male fs-5"></i>
            </div>
            <div>
                <div class="fs-4 fw-bold text-slate-900"><?= $maleCount ?></div>
                <div class="fs-xs text-muted">Siswa Laki-laki</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-xs rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3">
            <div class="rounded-circle p-2.5 bg-danger-subtle text-danger d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="bi bi-gender-female fs-5"></i>
            </div>
            <div>
                <div class="fs-4 fw-bold text-slate-900"><?= $femaleCount ?></div>
                <div class="fs-xs text-muted">Siswa Perempuan</div>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-lg-3">
        <div class="card border-0 shadow-xs rounded-4 bg-white p-3 d-flex flex-row align-items-center gap-3">
            <div class="rounded-circle p-2.5 bg-success-subtle text-success d-flex align-items-center justify-content-center" style="width: 44px; height: 44px;">
                <i class="bi bi-person-check-fill fs-5"></i>
            </div>
            <div>
                <div class="fs-4 fw-bold text-slate-900"><?= count($availableStudents) ?></div>
                <div class="fs-xs text-muted">Siswa Tersedia untuk Ditambah</div>
            </div>
        </div>
    </div>
</div>

<!-- Enrolled Students Table Card -->
<div class="card border-0 shadow-xs rounded-4 bg-white overflow-hidden mb-4">
    <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <h5 class="fw-bold text-slate-900 mb-0 fs-6">Daftar Anggota Kelas (Rombel)</h5>
            <p class="text-muted fs-xs mb-0">Siswa yang terdaftar secara resmi di rombongan belajar ini untuk kegiatan ujian dan absensi.</p>
        </div>
        <?php if (!empty($enrolledStudents)): ?>
            <div class="d-flex align-items-center gap-2">
                <input type="text" id="filterStudentInput" class="form-control form-control-sm border-slate-200 fs-xs rounded-pill" placeholder="Cari nama / NISN..." style="width: 220px;" onkeyup="filterStudentTable()">
            </div>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0" id="classStudentsTable">
            <thead class="table-light fs-xs text-muted border-bottom border-slate-100">
                <tr>
                    <th class="py-3 px-3" style="width: 50px;">#</th>
                    <th class="py-3 px-3" style="width: 70px;">Absen</th>
                    <th class="py-3 px-3">Nama Siswa</th>
                    <th class="py-3 px-3">NISN</th>
                    <th class="py-3 px-3">Kontak & Email</th>
                    <th class="py-3 px-3">L/P</th>
                    <th class="py-3 px-3">Status</th>
                    <th class="py-3 px-3">Terdaftar Sejak</th>
                    <th class="py-3 px-3 text-end" style="width: 120px;">Aksi</th>
                </tr>
            </thead>
            <tbody class="fs-xs">
                <?php if (empty($enrolledStudents)): ?>
                    <tr>
                        <td colspan="9" class="text-center py-5">
                            <div class="empty-state py-4 text-center">
                                <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs">
                                    <i class="bi bi-people fs-2"></i>
                                </div>
                                <h6 class="fw-bold text-dark mb-1">Belum Ada Siswa di Kelas Ini</h6>
                                <p class="text-muted fs-xs mb-3" style="max-width: 480px; margin: 0 auto;">
                                    Kelas <strong><?= e($class['name']) ?></strong> saat ini belum memiliki siswa terdaftar. Silakan klik tombol di bawah untuk memasukkan siswa yang sudah terdaftar di sistem.
                                </p>
                                <button type="button" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs" data-bs-toggle="modal" data-bs-target="#addStudentModal">
                                    <i class="bi bi-person-plus-fill me-1.5"></i> Tambah Siswa Sekarang
                                </button>
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($enrolledStudents as $idx => $s): ?>
                        <tr class="student-row">
                            <td class="px-3 text-muted"><?= $idx + 1 ?></td>
                            <td class="px-3">
                                <?php if (!empty($s['roll_number'])): ?>
                                    <span class="badge rounded-pill bg-slate-100 text-slate-800 border font-monospace px-2.5 py-1">
                                        <?= e($s['roll_number']) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted fst-italic fs-3xs">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3">
                                <div class="d-flex align-items-center gap-2.5">
                                    <div class="rounded-circle bg-primary-subtle text-primary fw-bold d-flex align-items-center justify-content-center fs-xs flex-shrink-0" style="width: 32px; height: 32px;">
                                        <?= strtoupper(substr(trim($s['name']), 0, 1)) ?>
                                    </div>
                                    <div>
                                        <span class="fw-bold text-dark d-block"><?= e($s['name']) ?></span>
                                        <small class="text-muted font-monospace fs-3xs">@<?= e($s['username']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td class="px-3">
                                <?php if (!empty($s['nip_nisn'])): ?>
                                    <span class="font-monospace text-dark fw-semibold"><?= e($s['nip_nisn']) ?></span>
                                <?php else: ?>
                                    <span class="text-muted fst-italic">Belum diisi</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3">
                                <div class="d-flex flex-column">
                                    <span class="text-dark"><?= e($s['email']) ?></span>
                                    <?php if (!empty($s['phone'])): ?>
                                        <small class="text-muted"><i class="bi bi-whatsapp text-success me-1"></i><?= e($s['phone']) ?></small>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="px-3">
                                <?php if (($s['gender'] ?? '') === 'L'): ?>
                                    <span class="badge rounded-pill bg-info-subtle text-info">Laki-laki</span>
                                <?php elseif (($s['gender'] ?? '') === 'P'): ?>
                                    <span class="badge rounded-pill bg-danger-subtle text-danger">Perempuan</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="px-3">
                                <span class="badge rounded-pill bg-success-subtle text-success d-inline-flex align-items-center gap-1">
                                    <i class="bi bi-check-circle-fill fs-3xs"></i> Aktif
                                </span>
                            </td>
                            <td class="px-3 text-muted">
                                <?= !empty($s['enrolled_at']) ? date('d M Y', strtotime($s['enrolled_at'])) : '-' ?>
                            </td>
                            <td class="px-3 text-end">
                                <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2.5" title="Keluarkan siswa dari kelas ini" onclick="App.confirm('Keluarkan siswa [<?= e($s['name']) ?>] dari kelas <?= e($class['name']) ?>?', () => App.submitPost('<?= base_url('admin/classes/' . $class['id'] . '/students/remove/' . $s['user_id']) ?>'))">
                                    <i class="bi bi-person-x me-1"></i> Keluarkan
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Modal Tambah Siswa ke Kelas -->
<div class="modal fade" id="addStudentModal" tabindex="-1" aria-labelledby="addStudentModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <form action="<?= base_url('admin/classes/' . $class['id'] . '/students/add') ?>" method="POST" class="needs-validation" novalidate onsubmit="return App.validateForm(this)">
                <?= csrf_field() ?>

                <div class="modal-header bg-white py-3 px-4 border-bottom border-slate-100">
                    <div class="d-flex align-items-center gap-2">
                        <div class="rounded-circle p-2 bg-primary-subtle text-primary d-flex align-items-center justify-content-center" style="width: 36px; height: 36px;">
                            <i class="bi bi-person-plus-fill fs-5"></i>
                        </div>
                        <div>
                            <h5 class="modal-title fw-bold text-slate-900 fs-6 mb-0" id="addStudentModalLabel">Tambah Siswa ke Kelas <?= e($class['name']) ?></h5>
                            <span class="text-muted fs-xs">Pilih siswa terdaftar untuk dimasukkan ke rombongan belajar ini</span>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tutup"></button>
                </div>

                <div class="modal-body p-4">
                    <div class="row g-3">
                        <!-- Pilihan Siswa -->
                        <div class="col-12">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Pilih Siswa <span class="text-danger">*</span></label>
                            <?php if (empty($availableStudents)): ?>
                                <div class="alert alert-warning border-0 rounded-3 py-2 px-3 fs-xs mb-0 d-flex align-items-center gap-2">
                                    <i class="bi bi-exclamation-triangle-fill fs-5 text-warning"></i>
                                    <span>Tidak ada siswa lain yang tersedia. Seluruh siswa terdaftar telah berada di kelas ini atau belum ada akun siswa baru. Silakan tambahkan pengguna baru dengan peran <strong>Peserta Didik</strong> di menu Manajemen Pengguna.</span>
                                </div>
                            <?php else: ?>
                                <select name="student_id" class="form-select border-slate-200 fs-xs rounded-3" required size="6" style="height: 180px;">
                                    <?php foreach ($availableStudents as $as): ?>
                                        <option value="<?= $as['id'] ?>">
                                            <?= e($as['name']) ?> 
                                            <?= !empty($as['nip_nisn']) ? '(NISN: ' . e($as['nip_nisn']) . ')' : '' ?>
                                            — 
                                            <?= !empty($as['current_class_name']) ? '[Saat ini di kelas: ' . e($as['current_class_name']) . ']' : '[Belum Memiliki Kelas]' ?>
                                            (<?= e($as['email']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text fs-3xs text-muted mt-1">
                                    <i class="bi bi-info-circle me-1"></i> Klik salah satu siswa di atas untuk memilih. Siswa yang saat ini berada di kelas lain akan otomatis dipindahkan ke kelas ini.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Nomor Absen -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold text-slate-800 fs-xs">Nomor Absen / Roll Number <span class="text-muted fw-normal fs-xs">(Opsional)</span></label>
                            <input type="text" name="roll_number" class="form-control border-slate-200 fs-xs rounded-3" placeholder="Contoh: 01, 02, ..." maxlength="10">
                            <div class="form-text fs-3xs text-muted">Urutan nomor absen dalam daftar presensi kelas.</div>
                        </div>

                        <!-- Info Alert -->
                        <div class="col-12">
                            <div class="alert alert-info border-0 rounded-3 py-2.5 px-3 fs-xs mb-0 d-flex align-items-start gap-2 bg-info-subtle text-info-emphasis">
                                <i class="bi bi-info-circle-fill fs-5 flex-shrink-0 mt-0.5"></i>
                                <div>
                                    <strong>Sinkronisasi Ujian Otomatis:</strong> Siswa yang dimasukkan ke kelas ini akan secara otomatis dapat melihat dan mengerjakan seluruh ujian CBT yang dijadwalkan untuk kelas <strong><?= e($class['name']) ?></strong>.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer bg-white border-top border-slate-100 py-3 px-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5" data-bs-dismiss="modal">Batal</button>
                    <?php if (!empty($availableStudents)): ?>
                        <button type="submit" class="btn btn-sm btn-primary rounded-pill px-4 fw-semibold shadow-xs">
                            <i class="bi bi-check2-circle me-1.5"></i> Masukkan Siswa
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function filterStudentTable() {
    const input = document.getElementById('filterStudentInput');
    const filter = input.value.toLowerCase();
    const rows = document.querySelectorAll('.student-row');

    rows.forEach(row => {
        const text = row.textContent.toLowerCase();
        row.style.display = text.includes(filter) ? '' : 'none';
    });
}
</script>
