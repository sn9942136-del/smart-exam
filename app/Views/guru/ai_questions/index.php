<div class="container-fluid py-2">
    <!-- Breadcrumb & Header -->
    <div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
        <div>
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-1 fs-xs">
                    <li class="breadcrumb-item"><a href="<?= base_url('guru/dashboard') ?>" class="text-decoration-none">Dashboard</a></li>
                    <li class="breadcrumb-item active" aria-current="page">AI Question Generator</li>
                </ol>
            </nav>
            <h3 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                <i class="bi bi-robot text-primary"></i> AI Question Generator
            </h3>
            <p class="text-muted fs-xs mb-0">Hasilkan draf butir soal ujian cerdas secara otomatis berbasis kurikulum dan tinjau sebelum disimpan ke Bank Soal.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="<?= base_url('guru/question-banks') ?>" class="btn btn-sm btn-outline-secondary rounded-pill px-3.5 d-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Bank Soal
            </a>
            <a href="#historyCard" class="btn btn-sm btn-outline-primary rounded-pill px-3.5 d-flex align-items-center gap-2">
                <i class="bi bi-clock-history"></i> Riwayat Draf (<?= count($generations) ?>)
            </a>
        </div>
    </div>

    <!-- AI Generator Statistics Cards -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white p-3 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-xs text-muted text-uppercase fw-semibold tracking-wider">Total Generasi</span>
                        <h3 class="fw-bold mb-0 mt-1 text-primary"><?= (int)($stats['total_generations'] ?? 0) ?> <span class="fs-xs fw-normal text-muted">batch</span></h3>
                    </div>
                    <div class="p-3 bg-primary-subtle text-primary rounded-circle fs-4">
                        <i class="bi bi-cpu"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white p-3 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-xs text-muted text-uppercase fw-semibold tracking-wider">Soal Dihasilkan</span>
                        <h3 class="fw-bold mb-0 mt-1 text-dark"><?= (int)($stats['total_questions_generated'] ?? 0) ?></h3>
                    </div>
                    <div class="p-3 bg-info-subtle text-info rounded-circle fs-4">
                        <i class="bi bi-lightbulb"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white p-3 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-xs text-muted text-uppercase fw-semibold tracking-wider">Disetujui Guru</span>
                        <h3 class="fw-bold mb-0 mt-1 text-success"><?= (int)($stats['total_questions_accepted'] ?? 0) ?></h3>
                    </div>
                    <div class="p-3 bg-success-subtle text-success rounded-circle fs-4">
                        <i class="bi bi-check2-circle"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="card border-0 shadow-xs rounded-4 h-100 bg-white p-3 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <span class="fs-xs text-muted text-uppercase fw-semibold tracking-wider">Menunggu Review</span>
                        <h3 class="fw-bold mb-0 mt-1 text-warning"><?= (int)($stats['pending_batches'] ?? 0) ?> <span class="fs-xs fw-normal text-muted">draf</span></h3>
                    </div>
                    <div class="p-3 bg-warning-subtle text-warning rounded-circle fs-4">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Generator Form & Guide Grid -->
    <div class="row g-4">
        <!-- AI Generator Input Form -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-xs rounded-4 bg-white">
                <div class="card-header bg-white py-3 px-4 border-bottom border-slate-100 d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <span class="p-1.5 bg-primary-subtle text-primary rounded-3">
                            <i class="bi bi-robot fs-5"></i>
                        </span>
                        <div>
                            <h5 class="fw-bold text-slate-900 mb-0 fs-6">Parameter Generasi Soal AI</h5>
                            <span class="text-muted fs-xs">Konfigurasikan target kurikulum & kriteria butir soal yang diinginkan</span>
                        </div>
                    </div>
                    <span class="badge rounded-pill bg-primary-subtle text-primary fs-xs">
                        <i class="bi bi-shield-check me-1"></i> Human-in-the-Loop
                    </span>
                </div>

                <div class="card-body p-4">
                    <form id="aiQuestionForm" onsubmit="AiGenerator.handleGenerate(event)">
                        <!-- Alert Box for AJAX Messages -->
                        <div id="aiFormAlert" class="d-none alert alert-dismissible fade show rounded-3" role="alert"></div>

                        <!-- 1. Pilihan Bank Soal Tujuan -->
                        <div class="mb-3">
                            <label for="question_bank_id" class="form-label fw-semibold text-slate-800 fs-xs">
                                Bank Soal Tujuan <span class="text-danger">*</span>
                            </label>
                            <select class="form-select border-slate-200 fs-xs rounded-3" id="question_bank_id" name="question_bank_id" required>
                                <option value="">-- Pilih Bank Soal --</option>
                                <?php foreach ($banks as $b): ?>
                                    <option value="<?= (int)$b['id'] ?>" 
                                            data-subject="<?= e($b['subject_name'] ?? '') ?>"
                                            <?= ($preselectedBankId === (int)$b['id']) ? 'selected' : '' ?>>
                                        <?= e($b['code'] ?? '') ?> - <?= e($b['title'] ?? '') ?> (<?= e($b['subject_name'] ?? 'Umum') ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text fs-3xs text-muted">Soal yang Anda setujui akan disimpan ke Bank Soal ini.</div>
                        </div>

                        <!-- 2. Mata Pelajaran & Tingkat / Kelas -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="subject_name" class="form-label fw-semibold text-slate-800 fs-xs">
                                    Mata Pelajaran <span class="text-danger">*</span>
                                </label>
                                <input type="text" class="form-control border-slate-200 fs-xs rounded-3" id="subject_name" name="subject_name" 
                                       placeholder="Contoh: Matematika, Fisika, Bahasa Indonesia" 
                                       value="<?= e($selectedBank['subject_name'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="grade_level" class="form-label fw-semibold text-slate-800 fs-xs">
                                    Tingkat / Kelas <span class="text-danger">*</span>
                                </label>
                                <select class="form-select border-slate-200 fs-xs rounded-3" id="grade_level" name="grade_level" required>
                                    <option value="Kelas 7 (SMP/MTs)">Kelas 7 (SMP / MTs)</option>
                                    <option value="Kelas 8 (SMP/MTs)" selected>Kelas 8 (SMP / MTs)</option>
                                    <option value="Kelas 9 (SMP/MTs)">Kelas 9 (SMP / MTs)</option>
                                    <option value="Kelas 10 (SMA/SMK/MA)">Kelas 10 (SMA / SMK / MA)</option>
                                    <option value="Kelas 11 (SMA/SMK/MA)">Kelas 11 (SMA / SMK / MA)</option>
                                    <option value="Kelas 12 (SMA/SMK/MA)">Kelas 12 (SMA / SMK / MA)</option>
                                    <option value="Perguruan Tinggi / Umum">Perguruan Tinggi / Umum</option>
                                </select>
                            </div>
                        </div>

                        <!-- 3. Topik / Materi Pembelajaran -->
                        <div class="mb-3">
                            <label for="topic" class="form-label fw-semibold text-slate-800 fs-xs d-flex justify-content-between align-items-center">
                                <span>Materi / Topik Pembelajaran <span class="text-danger">*</span></span>
                                <span class="text-muted fs-3xs">Spesifik agar hasil soal presisi</span>
                            </label>
                            <input type="text" class="form-control border-slate-200 fs-xs rounded-3" id="topic" name="topic" 
                                   placeholder="Contoh: Persamaan Linear Dua Variabel (SPLDV), Hukum Newton I & II" required>
                            
                            <!-- Topic Quick Suggestions -->
                            <div class="d-flex flex-wrap gap-1 mt-2 align-items-center">
                                <span class="fs-3xs text-muted me-1">Saran Topik:</span>
                                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5" onclick="AiGenerator.setTopic('Persamaan Linear Dua Variabel (SPLDV)', 'Matematika')">SPLDV</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5" onclick="AiGenerator.setTopic('Teorema Pythagoras & Trigonometri Dasar', 'Matematika')">Pythagoras</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5" onclick="AiGenerator.setTopic('Teks Eksplanasi & Struktur Paragraf', 'Bahasa Indonesia')">Teks Eksplanasi</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5" onclick="AiGenerator.setTopic('Hukum Kekekalan Energi & Usaha', 'Fisika')">Hukum Fisika</button>
                                <button type="button" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5" onclick="AiGenerator.setTopic('Grammar: Simple Past vs Present Perfect Tense', 'Bahasa Inggris')">English Grammar</button>
                            </div>
                        </div>

                        <!-- 4. Jenis Soal, Jumlah Soal, & Tingkat Kesulitan -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label for="question_type" class="form-label fw-semibold text-slate-800 fs-xs">
                                    Bentuk Soal
                                </label>
                                <select class="form-select border-slate-200 fs-xs rounded-3" id="question_type" name="question_type">
                                    <option value="multiple_choice" selected>Pilihan Ganda (4 Opsi)</option>
                                    <option value="essay">Esai / Uraian</option>
                                    <option value="true_false">Benar / Salah</option>
                                    <option value="mixed">Campuran (PG + Esai)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label for="question_count" class="form-label fw-semibold text-slate-800 fs-xs d-flex justify-content-between">
                                    <span>Jumlah Soal</span>
                                    <span id="countDisplay" class="badge rounded-pill bg-primary fs-3xs">5 Soal</span>
                                </label>
                                <input type="range" class="form-range mt-2" id="question_count" name="question_count" 
                                       min="1" max="20" step="1" value="5" oninput="AiGenerator.updateCountDisplay(this.value)">
                            </div>
                            <div class="col-md-4">
                                <label for="difficulty" class="form-label fw-semibold text-slate-800 fs-xs">
                                    Tingkat Kesulitan
                                </label>
                                <select class="form-select border-slate-200 fs-xs rounded-3" id="difficulty" name="difficulty">
                                    <option value="easy">Mudah (LOTS)</option>
                                    <option value="medium" selected>Sedang (MOTS)</option>
                                    <option value="hard">Sulit / Analisis (HOTS)</option>
                                </select>
                            </div>
                        </div>

                        <!-- 5. Bahasa & Model Provider -->
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="language" class="form-label fw-semibold text-slate-800 fs-xs">
                                    Bahasa Pengantar
                                </label>
                                <select class="form-select border-slate-200 fs-xs rounded-3" id="language" name="language">
                                    <option value="id" selected>Bahasa Indonesia</option>
                                    <option value="en">Bahasa Inggris (English)</option>
                                    <option value="ar">Bahasa Arab (Arabic)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="provider" class="form-label fw-semibold text-slate-800 fs-xs">
                                    Mesin AI (Provider)
                                </label>
                                <select class="form-select border-slate-200 fs-xs rounded-3" id="provider" name="provider">
                                    <option value="openai">OpenAI (GPT-4o Mini)</option>
                                    <option value="gemini">Google Gemini (Gemini 3.8 Flash)</option>
                                    <option value="mock" selected>Mock Kurikulum Cepat (Tanpa Kuota)</option>
                                </select>
                            </div>
                        </div>

                        <!-- 6. Instruksi Tambahan Khusus Guru -->
                        <div class="mb-4">
                            <label for="custom_instructions" class="form-label fw-semibold text-slate-800 fs-xs">
                                Instruksi Khusus Tambahan <span class="text-muted fw-normal fs-3xs">(Opsional)</span>
                            </label>
                            <textarea class="form-control border-slate-200 fs-xs rounded-3" id="custom_instructions" name="custom_instructions" rows="2" 
                                      placeholder="Contoh: Sertakan soal cerita kontekstual kehidupan sehari-hari, fokus pada rumus eliminasi dan substitusi, dan sertakan rumus dalam pembahasan."></textarea>
                        </div>

                        <!-- Submit Button & State -->
                        <div class="d-flex justify-content-between align-items-center pt-3 border-top border-slate-100">
                            <div class="text-muted fs-3xs d-flex align-items-center gap-1.5">
                                <i class="bi bi-info-circle text-primary"></i>
                                <span>Hasil AI wajib ditinjau dan disetujui sebelum masuk ke Bank Soal.</span>
                            </div>
                            <button type="submit" id="btnSubmitAi" class="btn btn-sm btn-primary px-4 rounded-pill fw-semibold shadow-xs d-flex align-items-center gap-2">
                                <i class="bi bi-stars"></i>
                                <span>Generasi Draf Soal</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Sidebar Info & Workflow Guide -->
        <div class="col-lg-4">
            <!-- Workflow Steps Card -->
            <div class="card border-0 shadow-xs rounded-4 mb-4 bg-white">
                <div class="card-header bg-white border-bottom border-slate-100 py-3 px-3">
                    <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                        <i class="bi bi-diagram-3 text-primary"></i> Alur Kerja Human-in-the-Loop
                    </h6>
                </div>
                <div class="card-body p-3">
                    <div class="timeline-steps">
                        <div class="d-flex gap-3 mb-3">
                            <div class="flex-shrink-0">
                                <span class="badge rounded-circle bg-primary p-2 fs-xs">1</span>
                            </div>
                            <div>
                                <h6 class="fw-semibold text-dark mb-0 fs-sm">Isi Kriteria Soal</h6>
                                <p class="text-muted fs-xs mb-0">Tentukan topik, tingkat kelas, jumlah soal, dan tingkat kesulitan kurikulum.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-3 mb-3">
                            <div class="flex-shrink-0">
                                <span class="badge rounded-circle bg-info text-dark p-2 fs-xs">2</span>
                            </div>
                            <div>
                                <h6 class="fw-semibold text-dark mb-0 fs-sm">Generasi AI Cerdas</h6>
                                <p class="text-muted fs-xs mb-0">AI merancang pertanyaan, 4 pilihan jawaban, kunci benar, dan pembahasan.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-3 mb-3">
                            <div class="flex-shrink-0">
                                <span class="badge rounded-circle bg-warning text-dark p-2 fs-xs">3</span>
                            </div>
                            <div>
                                <h6 class="fw-semibold text-dark mb-0 fs-sm">Review & Edit Workbench</h6>
                                <p class="text-muted fs-xs mb-0">Guru memeriksa, mengedit butir soal, dan memilih soal yang layak simpan.</p>
                            </div>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="flex-shrink-0">
                                <span class="badge rounded-circle bg-success p-2 fs-xs">4</span>
                            </div>
                            <div>
                                <h6 class="fw-semibold text-dark mb-0 fs-sm">Simpan ke Bank Soal</h6>
                                <p class="text-muted fs-xs mb-0">Butir soal terverifikasi masuk ke Bank Soal dan siap diujikan.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- API Key Security Notice -->
            <div class="card border-0 shadow-xs rounded-4 bg-light p-3">
                <div class="d-flex gap-3">
                    <div class="p-2.5 bg-white text-primary rounded-3 shadow-xs fs-4 align-self-start">
                        <i class="bi bi-shield-lock-fill"></i>
                    </div>
                    <div>
                        <h6 class="fw-bold text-dark mb-1 fs-sm">Keamanan & Standar AI</h6>
                        <p class="text-muted fs-xs mb-0">API key dikonfigurasikan di server via environment variables. Respon AI divalidasi dengan JSON schema murni untuk mencegah format corrupt.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- History of AI Generation Drafts Table -->
    <div class="card border-0 shadow-xs rounded-4 mt-4 bg-white" id="historyCard">
        <div class="card-header bg-white border-bottom border-slate-100 py-3 px-4 d-flex justify-content-between align-items-center">
            <div>
                <h5 class="fw-bold text-dark mb-0">Riwayat Draf Generasi Soal AI</h5>
                <span class="text-muted fs-xs">Daftar draf soal yang telah digenerasi dan status peninjauannya</span>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light fs-xs text-uppercase tracking-wider">
                    <tr>
                        <th class="ps-4">ID & Bank Soal</th>
                        <th>Topik / Materi</th>
                        <th>Kelas & Tipe</th>
                        <th>Jumlah / Diterima</th>
                        <th>Status</th>
                        <th>Tanggal</th>
                        <th class="text-end pe-4">Aksi</th>
                    </tr>
                </thead>
                <tbody class="fs-xs">
                    <?php if (empty($generations)): ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state py-4 text-center">
                                    <div class="empty-state-icon mx-auto mb-2 bg-primary-subtle text-primary shadow-2xs">
                                        <i class="bi bi-robot fs-2"></i>
                                    </div>
                                    <h6 class="fw-bold text-dark mb-1">Belum Ada Riwayat Draf AI</h6>
                                    <p class="text-muted fs-xs mb-3">Gunakan formulir di atas untuk menghasilkan butir soal otomatis berbasis kurikulum pertama Anda.</p>
                                    <a href="#aiQuestionForm" class="btn btn-sm btn-primary rounded-pill px-3 shadow-xs">
                                        <i class="bi bi-magic me-1"></i> Mulai Generasi Soal AI
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($generations as $g): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="fw-bold text-dark">Batch #<?= (int)$g['id'] ?></div>
                                    <span class="text-muted fs-xs"><?= e($g['bank_title'] ?? 'Bank Soal') ?> (<?= e($g['bank_code'] ?? '') ?>)</span>
                                </td>
                                <td>
                                    <span class="fw-semibold text-dark"><?= e($g['subject_topic'] ?? '-') ?></span>
                                    <div class="text-muted fs-xs"><?= e($g['subject_name'] ?? 'Umum') ?></div>
                                </td>
                                <td>
                                    <div><?= e($g['grade_level'] ?? '-') ?></div>
                                    <span class="badge rounded-pill bg-slate-100 text-slate-700 fs-3xs"><?= e($g['question_type'] ?? 'multiple_choice') ?></span>
                                </td>
                                <td>
                                    <span class="fw-bold text-primary"><?= (int)$g['question_count'] ?> Soal</span>
                                    <?php if ($g['status'] === 'success'): ?>
                                        <div class="text-success fs-xs fw-semibold"><i class="bi bi-check-lg"></i> <?= (int)$g['accepted_count'] ?> Disetujui</div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($g['status'] === 'pending'): ?>
                                        <span class="badge rounded-pill bg-warning-subtle text-warning-emphasis border border-warning-subtle px-2.5 py-1">
                                            <i class="bi bi-hourglass-split me-1"></i> Menunggu Review
                                        </span>
                                    <?php elseif ($g['status'] === 'success'): ?>
                                        <span class="badge rounded-pill bg-success-subtle text-success border border-success-subtle px-2.5 py-1">
                                            <i class="bi bi-check2-circle me-1"></i> Selesai Disimpan
                                        </span>
                                    <?php else: ?>
                                        <span class="badge rounded-pill bg-danger-subtle text-danger border border-danger-subtle px-2.5 py-1">
                                            <i class="bi bi-x-circle me-1"></i> Gagal
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="text-muted fs-xs"><?= date('d M Y, H:i', strtotime($g['created_at'])) ?></span>
                                </td>
                                <td class="text-end pe-4">
                                    <div class="d-flex align-items-center justify-content-end gap-1.5">
                                        <?php if ($g['status'] === 'pending'): ?>
                                            <a href="<?= base_url('guru/ai-questions/review/' . $g['id']) ?>" class="btn btn-xs btn-warning rounded-pill px-2.5 d-flex align-items-center gap-1">
                                                <i class="bi bi-pencil-square"></i> Tinjau Draf
                                            </a>
                                        <?php else: ?>
                                            <a href="<?= base_url('guru/ai-questions/review/' . $g['id']) ?>" class="btn btn-xs btn-outline-secondary rounded-pill px-2.5 d-flex align-items-center gap-1">
                                                <i class="bi bi-eye"></i> Detail
                                            </a>
                                        <?php endif; ?>
                                        <button type="button" class="btn btn-xs btn-outline-danger rounded-pill px-2" 
                                           onclick="App.confirm('Hapus riwayat draf generasi ini?', () => App.submitPost('<?= base_url('guru/ai-questions/delete/' . $g['id']) ?>'))" title="Hapus">
                                            <i class="bi bi-trash"></i>
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
</div>

<!-- AI Generator JS Module -->
<script src="<?= asset('js/ai-question-generator.js') ?>"></script>
