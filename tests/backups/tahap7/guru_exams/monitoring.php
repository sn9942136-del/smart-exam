<div class="container-fluid py-2 px-3 px-xl-4">

    <!-- Header Section -->
    <div class="d-flex flex-column flex-md-row align-items-start align-items-md-center justify-content-between gap-3 mb-4 pb-3 border-bottom border-slate-100">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1 flex-wrap">
                <span class="badge bg-danger-subtle text-danger px-2.5 py-1 rounded-pill fw-semibold d-inline-flex align-items-center gap-1.5 fs-xs">
                    <span class="spinner-grow spinner-grow-sm text-danger" style="width: 0.5rem; height: 0.5rem;" role="status"></span>
                    LIVE MONITORING
                </span>
                <span class="badge bg-primary-subtle text-primary rounded-pill px-2.5 py-1 fs-xs fw-medium">
                    <?= e($exam['subject_name'] ?? 'Mata Pelajaran') ?>
                </span>
                <span class="badge bg-slate-100 text-slate-700 rounded-pill px-2.5 py-1 fs-xs fw-medium">
                    <?= e($exam['academic_year_name'] ?? '2025/2026') ?>
                </span>
            </div>
            <h1 class="h4 fw-bold text-slate-900 mb-1 d-flex align-items-center gap-2">
                <?= e($exam['title']) ?>
            </h1>
            <p class="text-muted fs-xs mb-0">
                <i class="bi bi-clock me-1"></i> Durasi: <strong><?= (int)$exam['duration_minutes'] ?> Menit</strong> &bull;
                <i class="bi bi-key me-1"></i> Token: <code class="fw-bold fs-xs text-primary"><?= e($exam['token'] ?? '-') ?></code> &bull;
                <i class="bi bi-shield-check me-1"></i> AI Proctoring: <strong class="<?= !empty($exam['is_proctoring_active']) ? 'text-success' : 'text-secondary' ?>"><?= !empty($exam['is_proctoring_active']) ? 'Aktif' : 'Nonaktif' ?></strong> &bull;
                <i class="bi bi-camera-video me-1"></i> Dual Kamera: <strong class="<?= !empty($exam['require_dual_camera']) ? 'text-info' : 'text-secondary' ?>"><?= !empty($exam['require_dual_camera']) ? 'Wajib' : 'Tunggal' ?></strong>
            </p>
        </div>

        <div class="d-flex align-items-center gap-2 flex-wrap">
            <div class="bg-white border border-slate-200 rounded-pill px-3 py-1 shadow-xs d-flex align-items-center gap-2">
                <span class="badge bg-success rounded-circle p-1" id="pollingStatusDot" style="width: 7px; height: 7px;"></span>
                <small class="text-muted fs-xs" id="pollingStatusText">Sinkron: <span class="fw-semibold text-dark" id="lastSyncTime">Baru saja</span></small>
            </div>

            <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3.5 shadow-xs" id="btnRefreshNow" title="Segarkan Data Sekarang">
                <i class="bi bi-arrow-clockwise me-1"></i> Refresh
            </button>

            <a href="<?= base_url("{$basePrefix}/exams/show/{$exam['id']}") ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3.5 shadow-xs">
                <i class="bi bi-arrow-left me-1"></i> Kembali ke Ujian
            </a>
        </div>
    </div>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4" id="kpiCardsContainer">
        <!-- Total Terdaftar -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white">
                <div class="card-body p-3.5">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fs-xs fw-medium">Total Peserta</span>
                        <div class="rounded-3 p-2 bg-primary-subtle text-primary">
                            <i class="bi bi-people-fill fs-6"></i>
                        </div>
                    </div>
                    <h3 class="h5 fw-bold mb-0 text-dark" id="kpiTotal"><?= (int)($summary['total_registered'] ?? 0) ?></h3>
                    <small class="text-muted fs-xs">Siswa terdaftar</small>
                </div>
            </div>
        </div>

        <!-- Sedang Mengerjakan -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white border-start border-3 border-success">
                <div class="card-body p-3.5">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fs-xs fw-medium">Mengerjakan</span>
                        <div class="rounded-3 p-2 bg-success-subtle text-success">
                            <i class="bi bi-play-circle-fill fs-6"></i>
                        </div>
                    </div>
                    <h3 class="h5 fw-bold mb-0 text-success" id="kpiActive"><?= (int)($summary['active_count'] ?? 0) ?></h3>
                    <small class="text-muted fs-xs">Sesi ujian aktif</small>
                </div>
            </div>
        </div>

        <!-- Selesai Dikumpulkan -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white">
                <div class="card-body p-3.5">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fs-xs fw-medium">Dikumpulkan</span>
                        <div class="rounded-3 p-2 bg-info-subtle text-info">
                            <i class="bi bi-check2-all fs-6"></i>
                        </div>
                    </div>
                    <h3 class="h5 fw-bold mb-0 text-info" id="kpiSubmitted"><?= (int)($summary['submitted_count'] ?? 0) ?></h3>
                    <small class="text-muted fs-xs">Sudah submit</small>
                </div>
            </div>
        </div>

        <!-- Kendala Kamera -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white border-start border-3 border-warning">
                <div class="card-body p-3.5">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fs-xs fw-medium">Kendala Cam</span>
                        <div class="rounded-3 p-2 bg-warning-subtle text-warning">
                            <i class="bi bi-camera-video-off fs-6"></i>
                        </div>
                    </div>
                    <h3 class="h5 fw-bold mb-0 text-warning" id="kpiCamError"><?= (int)($summary['cam_error_count'] ?? 0) ?></h3>
                    <small class="text-muted fs-xs">Kamera terputus</small>
                </div>
            </div>
        </div>

        <!-- Risiko Kritis AI -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white border-start border-3 border-danger">
                <div class="card-body p-3.5">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fs-xs fw-medium">Risiko Kritis</span>
                        <div class="rounded-3 p-2 bg-danger-subtle text-danger">
                            <i class="bi bi-exclamation-octagon-fill fs-6"></i>
                        </div>
                    </div>
                    <h3 class="h5 fw-bold mb-0 text-danger" id="kpiCritical"><?= (int)($summary['critical_risk_count'] ?? 0) ?></h3>
                    <small class="text-muted fs-xs">Skor risiko &gt; 80</small>
                </div>
            </div>
        </div>

        <!-- Rata-rata Integritas -->
        <div class="col-6 col-md-4 col-xl-2">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white">
                <div class="card-body p-3.5">
                    <div class="d-flex align-items-center justify-content-between mb-2">
                        <span class="text-muted fs-xs fw-medium">Skor Integritas</span>
                        <div class="rounded-3 p-2 bg-secondary-subtle text-secondary">
                            <i class="bi bi-graph-up-arrow fs-6"></i>
                        </div>
                    </div>
                    <h3 class="h5 fw-bold mb-0 text-dark" id="kpiAvgScore"><?= number_format((float)($summary['avg_integrity_score'] ?? 100), 1) ?>%</h3>
                    <small class="text-muted fs-xs">Rata-rata kelas</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Controls & Filter Toolbar -->
    <div class="card border-0 shadow-xs rounded-4 mb-4 bg-white">
        <div class="card-body p-3">
            <div class="row g-3 align-items-center justify-content-between">
                <!-- Status Filter Pills -->
                <div class="col-12 col-lg-7">
                    <div class="d-flex align-items-center gap-1.5 flex-wrap" id="statusFilterGroup">
                        <button type="button" class="btn btn-sm btn-primary rounded-pill px-3 filter-status active" data-status="">
                            Semua (<span id="countAll"><?= count($initialParticipants) ?></span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 filter-status" data-status="ongoing">
                            <i class="bi bi-play-circle me-1 text-success"></i> Aktif (<span id="countOngoing"><?= (int)($summary['active_count'] ?? 0) ?></span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 filter-status" data-status="submitted">
                            <i class="bi bi-check2-circle me-1 text-info"></i> Selesai (<span id="countSubmitted"><?= (int)($summary['submitted_count'] ?? 0) ?></span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 filter-status" data-status="terminated">
                            <i class="bi bi-x-circle me-1 text-danger"></i> Dihentikan (<span id="countTerminated"><?= (int)($summary['terminated_count'] ?? 0) ?></span>)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3 filter-status" data-status="cam_error">
                            <i class="bi bi-camera-video-off me-1 text-warning"></i> Kendala Cam (<span id="countCamError"><?= (int)($summary['cam_error_count'] ?? 0) ?></span>)
                        </button>
                    </div>
                </div>

                <!-- Right Controls: Risk Filter, Search, View Mode, Polling Interval -->
                <div class="col-12 col-lg-5">
                    <div class="d-flex align-items-center justify-content-lg-end gap-2 flex-wrap">
                        <!-- Risk Filter Select -->
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-secondary dropdown-toggle rounded-pill px-3.5 shadow-none" type="button" id="riskFilterDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="bi bi-shield-exclamation me-1"></i> <span id="riskFilterLabel">Semua Risiko</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end shadow-xs border-slate-100 rounded-3 fs-xs" aria-labelledby="riskFilterDropdown">
                                <li><a class="dropdown-item filter-risk active" href="javascript:void(0)" data-risk="">Semua Level Risiko</a></li>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item filter-risk text-success" href="javascript:void(0)" data-risk="low"><i class="bi bi-check-circle me-1.5"></i> Rendah (0 - 30)</a></li>
                                <li><a class="dropdown-item filter-risk text-warning" href="javascript:void(0)" data-risk="medium"><i class="bi bi-exclamation-circle me-1.5"></i> Sedang (31 - 60)</a></li>
                                <li><a class="dropdown-item filter-risk text-warning-emphasis" href="javascript:void(0)" data-risk="high"><i class="bi bi-exclamation-triangle me-1.5"></i> Tinggi (61 - 80)</a></li>
                                <li><a class="dropdown-item filter-risk text-danger" href="javascript:void(0)" data-risk="critical"><i class="bi bi-exclamation-octagon me-1.5"></i> Kritis (81 - 100)</a></li>
                            </ul>
                        </div>

                        <!-- Search Input -->
                        <div class="input-group input-group-sm" style="max-width: 200px;">
                            <span class="input-group-text bg-light border-slate-200 border-end-0 rounded-start-pill ps-3"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control bg-light border-slate-200 border-start-0 rounded-end-pill pe-3" id="searchInput" placeholder="Cari siswa...">
                        </div>

                        <!-- View Toggle (Grid vs Table) -->
                        <div class="btn-group btn-group-sm shadow-none" role="group">
                            <button type="button" class="btn btn-outline-secondary active rounded-start-pill px-2.5" id="btnViewGrid" title="Tampilan Kartu">
                                <i class="bi bi-grid-fill"></i>
                            </button>
                            <button type="button" class="btn btn-outline-secondary rounded-end-pill px-2.5" id="btnViewTable" title="Tampilan Tabel">
                                <i class="bi bi-list-ul"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Live Participant Content Container -->
    <div id="participantContentArea">
        <!-- Grid View Container -->
        <div class="row g-3" id="gridParticipantsView">
            <!-- Will be dynamically populated via JS -->
            <div class="col-12 text-center py-5 text-muted">
                <div class="spinner-border spinner-border-sm text-primary mb-2" role="status"></div>
                <p class="fs-xs mb-0">Memuat data peserta ujian...</p>
            </div>
        </div>

        <!-- Table View Container (Hidden by default) -->
        <div class="card border-0 shadow-xs rounded-4 bg-white d-none" id="tableParticipantsView">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 fs-xs" id="monitoringDataTable">
                    <thead class="table-light fs-xs text-uppercase text-secondary border-bottom border-slate-100">
                        <tr>
                            <th class="ps-4">Peserta Didik</th>
                            <th>Kelas</th>
                            <th>Status Sesi</th>
                            <th>Konektivitas Kamera</th>
                            <th>Skor Risiko & Pelanggaran</th>
                            <th>Progress & Timer</th>
                            <th class="text-end pe-4">Tindakan Pengawas</th>
                        </tr>
                    </thead>
                    <tbody id="tableBodyParticipants">
                        <!-- Populated via JS -->
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- ==============================================================================
     MODAL & DRAWER CONTAINERS (Teguran, Terminasi, Soft Reset, Ujian Susulan, Chat, Detail)
     ============================================================================== -->

<!-- 1. Modal Detail Peserta & Bukti AI Proctoring -->
<div class="modal fade" id="modalStudentDetail" tabindex="-1" aria-labelledby="modalStudentDetailLabel" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-white border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2.5">
                    <div class="rounded-circle bg-primary-subtle p-2 text-primary d-flex align-items-center justify-content-center" style="width: 38px; height: 38px;">
                        <i class="bi bi-person-bounding-box fs-5"></i>
                    </div>
                    <div>
                        <h5 class="modal-title fw-bold fs-6 mb-0 text-dark" id="modalDetailStudentName">Detail Peserta Didik</h5>
                        <small class="text-muted fs-3xs" id="modalDetailSubTitle">Informasi integritas &amp; log pemantauan real-time</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4" id="modalDetailBody">
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="text-muted mt-2 fs-xs">Memuat telemetri dan log proctoring...</p>
                </div>
            </div>
            <div class="modal-footer bg-white border-top py-3 px-4">
                <button type="button" class="btn btn-secondary btn-sm rounded-pill px-4" data-bs-dismiss="modal">Tutup</button>
            </div>
        </div>
    </div>
</div>

<!-- 2. Modal Kirim Teguran / Peringatan (UC-22a) -->
<div class="modal fade" id="modalSendWarning" tabindex="-1" aria-labelledby="modalSendWarningLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-warning-subtle text-warning-emphasis border-bottom border-warning-subtle py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-exclamation-triangle-fill text-warning fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6 mb-0">Beri Teguran Pengawas</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formSendWarning">
                <input type="hidden" name="session_id" id="warningSessionId">
                <div class="modal-body p-4">
                    <p class="text-muted fs-xs mb-3">
                        Pesan teguran akan langsung ditampilkan pada layar ujian peserta <strong id="warningStudentName" class="text-dark">Siswa</strong> secara real-time.
                    </p>

                    <div class="mb-3">
                        <label class="form-label fs-xs fw-semibold">Pilih Template Teguran Cepat:</label>
                        <select class="form-select form-select-sm" id="selectWarningTemplate">
                            <option value="">-- Tulis Pesan Kustom --</option>
                            <option value="Mohon posisikan wajah Anda tegak lurus menghadap kamera utama.">Wajah tidak menghadap kamera</option>
                            <option value="Dilarang membuka buku, catatan, atau perangkat sekunder di sekitar area ujian.">Objek/buku terlarang</option>
                            <option value="Terdeteksi pergerakan orang lain di sekitar Anda. Harap pastikan ruangan tenang dan steril.">Orang lain di ruangan</option>
                            <option value="Harap aktifkan kembali kamera sekunder Anda dalam waktu 1 menit.">Kamera sekunder mati</option>
                            <option value="Dilarang meninggalkan area pandang kamera selama ujian berlangsung.">Meninggalkan kursi ujian</option>
                        </select>
                    </div>

                    <div class="mb-3">
                        <label for="warningMessage" class="form-label fs-xs fw-semibold">Pesan Teguran / Peringatan: <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="warningMessage" name="message" rows="3" placeholder="Ketik pesan teguran resmi pengawas di sini..." required></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-xs fw-semibold">Tipe Tampilan di Layar Siswa:</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="warning_type" id="typeToast" value="toast" checked>
                                <label class="form-check-label fs-xs" for="typeToast">
                                    <i class="bi bi-bell me-1 text-primary"></i> Notifikasi Toast (Melayang)
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="warning_type" id="typeModal" value="modal_ack">
                                <label class="form-check-label fs-xs" for="typeModal">
                                    <i class="bi bi-window-fullscreen me-1 text-danger"></i> Modal Wajib Konfirmasi (Pause)
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning btn-sm rounded-pill px-4 fw-semibold" id="btnSubmitWarning">
                        <i class="bi bi-send-fill me-1.5"></i> Kirim Peringatan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 3. Modal Hentikan Ujian / Diskualifikasi (UC-22a) -->
<div class="modal fade" id="modalTerminate" tabindex="-1" aria-labelledby="modalTerminateLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-danger-subtle text-danger border-bottom border-danger-subtle py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-slash-circle-fill fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6 mb-0">Hentikan / Diskualifikasi Ujian</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formTerminate">
                <input type="hidden" name="session_id" id="terminateSessionId">
                <div class="modal-body p-4">
                    <div class="alert alert-danger border-0 rounded-3 fs-xs mb-3">
                        <i class="bi bi-exclamation-diamond-fill me-1.5"></i>
                        <strong>Perhatian:</strong> Tindakan ini akan menghentikan sesi ujian siswa <strong id="terminateStudentName" class="text-decoration-underline">Siswa</strong> secara paksa. Siswa tidak akan dapat melanjutkan pengerjaan soal. Tindakan ini dicatat ke dalam audit log.
                    </div>

                    <div class="mb-3">
                        <label for="terminateReason" class="form-label fs-xs fw-semibold">Alasan Penghentian / Diskualifikasi: <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="terminateReason" name="reason" rows="3" placeholder="Contoh: Terdeteksi membuka tab terlarang dan berkomunikasi dengan pihak lain secara berulang..." required></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-danger btn-sm rounded-pill px-4 fw-semibold" id="btnSubmitTerminate">
                        <i class="bi bi-x-octagon-fill me-1.5"></i> Konfirmasi Hentikan Ujian
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 4. Modal Soft Reset Sesi Ujian (UC-22c) -->
<div class="modal fade" id="modalResetSession" tabindex="-1" aria-labelledby="modalResetSessionLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-white border-bottom py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-arrow-counterclockwise text-primary fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6 mb-0 text-dark">Soft Reset Sesi Ujian</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formResetSession">
                <input type="hidden" name="session_id" id="resetSessionId">
                <div class="modal-body p-4">
                    <div class="alert alert-info border-0 rounded-3 fs-xs mb-3">
                        <i class="bi bi-info-circle-fill me-1.5"></i>
                        Kebijakan Soft Reset: Data jawaban dan riwayat pengerjaan sebelumnya tetap tersimpan secara aman di database.
                    </div>

                    <p class="fs-xs text-muted mb-3">
                        Pilih metode pemulihan sesi untuk peserta <strong id="resetStudentName" class="text-dark">Siswa</strong>:
                    </p>

                    <div class="mb-3">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="reset_option" id="resetOptionRestore" value="restore_time" checked>
                            <label class="form-check-label fs-xs fw-semibold" for="resetOptionRestore">
                                Pulihkan Sesi &amp; Tambah Waktu Kompensasi
                            </label>
                            <div class="text-muted fs-3xs ps-4">Siswa melanjutkan sesi yang sama dengan sisa waktu yang dipulihkan + kompensasi.</div>
                        </div>

                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="reset_option" id="resetOptionFull" value="full_restart">
                            <label class="form-check-label fs-xs fw-semibold" for="resetOptionFull">
                                Buat Sesi Baru (Durasi Penuh)
                            </label>
                            <div class="text-muted fs-3xs ps-4">Sesi lama diarsipkan, siswa dapat login kembali dengan timer ujian penuh.</div>
                        </div>
                    </div>

                    <div class="mb-3" id="customMinutesContainer">
                        <label for="customMinutes" class="form-label fs-xs fw-semibold">Tambahan Waktu Kompensasi (Menit):</label>
                        <input type="number" class="form-control form-control-sm" id="customMinutes" name="custom_minutes" value="10" min="0" max="180">
                        <div class="form-text fs-3xs">Waktu ini akan ditambahkan ke sisa timer siswa saat ini.</div>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary btn-sm rounded-pill px-4 fw-semibold" id="btnSubmitReset">
                        <i class="bi bi-arrow-repeat me-1.5"></i> Lakukan Reset Sesi
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 5. Modal Otorisasi Ujian Susulan (UC-22c) -->
<div class="modal fade" id="modalMakeupExam" tabindex="-1" aria-labelledby="modalMakeupExamLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden">
            <div class="modal-header bg-success-subtle text-success border-bottom border-success-subtle py-3 px-4">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-calendar2-check fs-5"></i>
                    <h5 class="modal-title fw-bold fs-6 mb-0">Izinkan Ujian Susulan</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="formMakeupExam">
                <input type="hidden" name="exam_id" value="<?= (int)$exam['id'] ?>">
                <input type="hidden" name="student_id" id="makeupStudentId">
                <div class="modal-body p-4">
                    <p class="fs-xs text-muted mb-3">
                        Berikan otorisasi ujian susulan kepada siswa <strong id="makeupStudentName" class="text-dark">Siswa</strong>. Status pendaftaran siswa akan direset ke <code>not_started</code> sehingga siswa dapat memasukkan token ujian baru.
                    </p>

                    <div class="mb-3">
                        <label for="makeupNotes" class="form-label fs-xs fw-semibold">Catatan / Alasan Ujian Susulan:</label>
                        <textarea class="form-control" id="makeupNotes" name="notes" rows="2" placeholder="Contoh: Siswa sakit pada jadwal utama, melampirkan surat dokter..."></textarea>
                    </div>
                </div>
                <div class="modal-footer bg-white border-top py-3 px-4 d-flex justify-content-end gap-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm rounded-pill px-3" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-success btn-sm rounded-pill px-4 fw-semibold" id="btnSubmitMakeup">
                        <i class="bi bi-check-circle-fill me-1.5"></i> Berikan Izin Susulan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- 6. Offcanvas Chat Drawer (UC-22b) -->
<div class="offcanvas offcanvas-end border-0 shadow-lg" tabindex="-1" id="chatDrawer" aria-labelledby="chatDrawerLabel" style="width: 420px;">
    <div class="offcanvas-header bg-white border-bottom py-3 px-4">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-chat-dots-fill text-primary fs-5"></i>
            <div>
                <h6 class="offcanvas-title fw-bold mb-0 text-dark" id="chatDrawerStudentName">Chat Pengawas - Siswa</h6>
                <small class="text-muted fs-3xs" id="chatDrawerStatus">Komunikasi internal ruang ujian</small>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column p-0 bg-light">
        <!-- Chat History Scroll Box -->
        <div class="flex-grow-1 p-3 overflow-auto" id="chatHistoryContainer" style="max-height: calc(100vh - 160px);">
            <div class="text-center py-5 text-muted small">
                <i class="bi bi-chat-square-text fs-2 d-block mb-2 text-secondary"></i>
                Pilih peserta untuk memuat riwayat pesan chat.
            </div>
        </div>

        <!-- Chat Input Form -->
        <div class="p-3 bg-white border-top">
            <form id="formSendChat" class="d-flex gap-2">
                <input type="hidden" id="chatSessionId" name="session_id">
                <input type="hidden" id="chatReceiverId" name="receiver_id">
                <input type="text" class="form-control rounded-pill shadow-none" id="chatInputMessage" placeholder="Ketik pesan ke peserta..." autocomplete="off" required>
                <button type="submit" class="btn btn-primary rounded-circle p-2 shadow-sm d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;" id="btnSendChat">
                    <i class="bi bi-send-fill"></i>
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Include Frontend Monitoring Manager -->
<script>
    window.EXAM_MONITORING_CONFIG = {
        examId: <?= (int)$exam['id'] ?>,
        basePrefix: '<?= e($basePrefix) ?>',
        baseUrl: '<?= base_url() ?>',
        pollInterval: 4000,
        csrfToken: '_csrf_token',
        csrfHash: '<?= csrf_token() ?>'
    };
</script>
<script src="<?= base_url('assets/js/exam-monitoring.js') ?>"></script>
