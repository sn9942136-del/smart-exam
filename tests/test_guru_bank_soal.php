<?php

declare(strict_types=1);

/**
 * ==============================================================================
 * TEST SUITE: MODUL GURU & BANK SOAL (UC-19, UC-20, UC-20a, UC-21, UC-21a, UC-21b)
 * ==============================================================================
 */

require_once __DIR__ . '/../app/bootstrap.php';

use App\Models\QuestionBankModel;
use App\Models\QuestionImportModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;
use App\Models\SubjectModel;
use App\Models\UserModel;
use App\Services\QuestionImportService;
use App\Services\QuestionService;

echo "\n======================================================\n";
echo "  PENGUJIAN MODUL GURU & MANAJEMEN BANK SOAL\n";
echo "======================================================\n\n";

$passCount = 0;
$totalTests = 8;

$bankModel = new QuestionBankModel();
$questionModel = new QuestionModel();
$optionModel = new QuestionOptionModel();
$subjectModel = new SubjectModel();
$userModel = new UserModel();
$questionService = new QuestionService();
$importService = new QuestionImportService();

// Ambil atau siapkan Guru dan Mata Pelajaran untuk test fixture
$teacher = $userModel->findByEmail('guru@ujian.test');
if (!$teacher) {
    $teacherId = $userModel->insert([
        'username' => 'guru_test_' . time(),
        'email' => 'guru_test_' . time() . '@ujian.test',
        'password_hash' => password_hash('Password123!', PASSWORD_BCRYPT),
        'name' => 'Bpk. Hendra Wijaya, M.Pd',
        'status' => 'active'
    ]);
} else {
    $teacherId = (int)$teacher['id'];
}

$subjects = $subjectModel->getAllActive();
if (empty($subjects)) {
    $subjectId = $subjectModel->insert([
        'code' => 'MAT-10',
        'name' => 'Matematika Wajib X',
        'description' => 'Mata pelajaran matematika wajib tingkat 10.'
    ]);
} else {
    $subjectId = (int)$subjects[0]['id'];
}

// -----------------------------------------------------------------------------
// TEST 1: CRUD Bank Soal & Generator Kode Unik
// -----------------------------------------------------------------------------
echo "1. Pengujian CRUD Bank Soal & Generator Kode Unik... ";
try {
    $code = $bankModel->generateBankCode('MAT10');
    $bankId = $bankModel->insert([
        'subject_id' => $subjectId,
        'creator_id' => $teacherId,
        'code' => $code,
        'title' => 'Bank Soal Ujian Tengah Semester Matematika X',
        'description' => 'Mencakup aljabar, fungsi kuadrat, dan trigonometri dasar.',
        'total_questions' => 0,
        'status' => 'draft'
    ]);

    $bank = $bankModel->getByIdWithDetails((int)$bankId);
    if (!$bank || $bank['code'] !== $code || (int)$bank['total_questions'] !== 0) {
        throw new Exception("Gagal membuat bank soal dengan detail valid.");
    }

    // Update status ke active
    $bankModel->update((int)$bankId, ['status' => 'active']);
    $updatedBank = $bankModel->find((int)$bankId);
    if ($updatedBank['status'] !== 'active') {
        throw new Exception("Gagal memperbarui status bank soal.");
    }

    echo "[PASSED]\n";
    $passCount++;
} catch (\Throwable $e) {
    echo "[FAILED]: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// TEST 2: Validasi Input Butir Soal (Negative Testing)
// -----------------------------------------------------------------------------
echo "2. Pengujian Validasi Input Butir Soal (Rejection)... ";
try {
    // 2.1 Soal kosong
    $res1 = $questionService->createQuestion((int)$bankId, [
        'type' => 'multiple_choice',
        'content' => '',
        'weight' => 2.0,
        'difficulty' => 'easy'
    ]);
    if ($res1['success'] || empty($res1['errors']['content'])) {
        throw new Exception("Harusnya gagal saat teks soal kosong.");
    }

    // 2.2 Bobot 0 atau negatif
    $res2 = $questionService->createQuestion((int)$bankId, [
        'type' => 'multiple_choice',
        'content' => 'Pertanyaan uji coba?',
        'weight' => -1.0,
        'difficulty' => 'easy'
    ]);
    if ($res2['success'] || empty($res2['errors']['weight'])) {
        throw new Exception("Harusnya gagal saat bobot nilai tidak valid.");
    }

    // 2.3 Pilihan ganda tanpa opsi
    $res3 = $questionService->createQuestion((int)$bankId, [
        'type' => 'multiple_choice',
        'content' => 'Pertanyaan pilihan ganda tanpa opsi?',
        'weight' => 2.0,
        'difficulty' => 'medium',
        'options' => []
    ]);
    if ($res3['success'] || empty($res3['errors']['options'])) {
        throw new Exception("Harusnya gagal saat pilihan ganda tidak memiliki opsi.");
    }

    echo "[PASSED]\n";
    $passCount++;
} catch (\Throwable $e) {
    echo "[FAILED]: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// TEST 3: CRUD Butir Soal - Pilihan Ganda (5 Opsi)
// -----------------------------------------------------------------------------
echo "3. Pengujian Butir Soal Pilihan Ganda (5 Opsi & Kunci)... ";
try {
    $mcData = [
        'type' => 'multiple_choice',
        'content' => 'Jika f(x) = 2x + 5, maka nilai dari f(4) adalah...',
        'difficulty' => 'easy',
        'weight' => 2.0,
        'explanation' => 'Substitusi x = 4: f(4) = 2(4) + 5 = 8 + 5 = 13.',
        'options' => [
            'A' => '11',
            'B' => '13',
            'C' => '15',
            'D' => '17',
            'E' => '20'
        ],
        'correct_option' => 'B'
    ];

    $createMcRes = $questionService->createQuestion((int)$bankId, $mcData);
    if (!$createMcRes['success'] || empty($createMcRes['question_id'])) {
        throw new Exception("Gagal membuat soal pilihan ganda: " . json_encode($createMcRes['errors']));
    }

    $mcQid = (int)$createMcRes['question_id'];
    $options = $optionModel->getByQuestionId($mcQid);
    if (count($options) !== 5) {
        throw new Exception("Jumlah opsi yang tersimpan harus 5, didapat: " . count($options));
    }

    $correctOpt = null;
    foreach ($options as $opt) {
        if (!empty($opt['is_correct'])) {
            $correctOpt = $opt;
            break;
        }
    }

    if (!$correctOpt || $correctOpt['option_key'] !== 'B' || $correctOpt['content'] !== '13') {
        throw new Exception("Kunci jawaban benar opsi B ('13') tidak terekam dengan benar.");
    }

    // Periksa total butir soal di bank
    $bankCheck = $bankModel->find((int)$bankId);
    if ((int)$bankCheck['total_questions'] !== 1) {
        throw new Exception("Total questions di bank harusnya 1.");
    }

    echo "[PASSED]\n";
    $passCount++;
} catch (\Throwable $e) {
    echo "[FAILED]: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// TEST 4: CRUD Butir Soal - Benar / Salah (True / False)
// -----------------------------------------------------------------------------
echo "4. Pengujian Butir Soal Benar / Salah (True/False)... ";
try {
    $tfData = [
        'type' => 'true_false',
        'content' => 'Himpunan penyelesaian persamaan kuadrat x^2 - 9 = 0 adalah {-3, 3}.',
        'difficulty' => 'medium',
        'weight' => 1.5,
        'explanation' => 'Faktorisasi: (x - 3)(x + 3) = 0, sehingga x = 3 atau x = -3.',
        'correct_tf' => 'Benar'
    ];

    $createTfRes = $questionService->createQuestion((int)$bankId, $tfData);
    if (!$createTfRes['success'] || empty($createTfRes['question_id'])) {
        throw new Exception("Gagal membuat soal benar/salah: " . json_encode($createTfRes['errors']));
    }

    $tfQid = (int)$createTfRes['question_id'];
    $tfOptions = $optionModel->getByQuestionId($tfQid);
    if (count($tfOptions) !== 2) {
        throw new Exception("Jumlah opsi benar/salah harus 2.");
    }

    if (empty($tfOptions[0]['is_correct']) || $tfOptions[0]['content'] !== 'Benar') {
        throw new Exception("Opsi Benar harus ditandai is_correct = 1.");
    }

    echo "[PASSED]\n";
    $passCount++;
} catch (\Throwable $e) {
    echo "[FAILED]: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// TEST 5: CRUD Butir Soal - Esai / Uraian
// -----------------------------------------------------------------------------
echo "5. Pengujian Butir Soal Esai & Rubrik Penilaian... ";
try {
    $essayData = [
        'type' => 'essay',
        'content' => 'Tentukan koordinat titik puncak dari grafik fungsi kuadrat f(x) = x^2 - 4x + 3!',
        'difficulty' => 'hard',
        'weight' => 5.0,
        'explanation' => 'Sumbu simetri xp = -b/(2a) = 4/2 = 2. Nilai yp = f(2) = 4 - 8 + 3 = -1. Jadi titik puncak (2, -1).'
    ];

    $createEssayRes = $questionService->createQuestion((int)$bankId, $essayData);
    if (!$createEssayRes['success'] || empty($createEssayRes['question_id'])) {
        throw new Exception("Gagal membuat soal esai.");
    }

    $essayQid = (int)$createEssayRes['question_id'];
    $essayOptions = $optionModel->getByQuestionId($essayQid);
    if (!empty($essayOptions)) {
        throw new Exception("Soal esai seharusnya tidak memiliki entri opsi di question_options.");
    }

    $essayDetail = $questionModel->getWithFullDetails($essayQid);
    if ($essayDetail['difficulty'] !== 'hard' || (float)$essayDetail['weight'] !== 5.0) {
        throw new Exception("Atribut soal esai tidak sesuai.");
    }

    echo "[PASSED]\n";
    $passCount++;
} catch (\Throwable $e) {
    echo "[FAILED]: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// TEST 6: Upload Gambar Soal & Sanitasi Berkas
// -----------------------------------------------------------------------------
echo "6. Pengujian Upload Gambar Soal & Penanganan Berkas... ";
try {
    // Buat file dummy gambar PNG valid via base64
    $tempImg = tempnam(sys_get_temp_dir(), 'test_img_') . '.png';
    $pngBytes = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==');
    file_put_contents($tempImg, $pngBytes);

    $dummyFile = [
        'name' => 'diagram_segitiga.png',
        'type' => 'image/png',
        'tmp_name' => $tempImg,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tempImg)
    ];

    $imgQuestionData = [
        'type' => 'multiple_choice',
        'content' => 'Perhatikan gambar diagram segitiga siku-siku terlampir. Berapakah panjang sisi miring c jika a=6 dan b=8?',
        'difficulty' => 'medium',
        'weight' => 3.0,
        'options' => ['A' => '9', 'B' => '10', 'C' => '12', 'D' => '14', 'E' => '16'],
        'correct_option' => 'B'
    ];

    $createImgRes = $questionService->createQuestion((int)$bankId, $imgQuestionData, $dummyFile);
    if (!$createImgRes['success'] || empty($createImgRes['question_id'])) {
        throw new Exception("Gagal membuat soal bergambar: " . json_encode($createImgRes['errors'] ?? []));
    }

    $imgQid = (int)$createImgRes['question_id'];
    $savedQ = $questionModel->find($imgQid);
    if (empty($savedQ['question_image']) || !file_exists(APP_ROOT . '/public/' . $savedQ['question_image'])) {
        throw new Exception("Berkas gambar tidak ditemukan di path: " . ($savedQ['question_image'] ?? 'null'));
    }

    // Uji penghapusan soal beserta berkas fisiknya
    $savedImagePath = APP_ROOT . '/public/' . $savedQ['question_image'];
    $questionService->deleteQuestion($imgQid);
    if (file_exists($savedImagePath)) {
        throw new Exception("File gambar fisik harusnya ikut terhapus saat butir soal dihapus.");
    }

    if (file_exists($tempImg)) {
        @unlink($tempImg);
    }

    echo "[PASSED]\n";
    $passCount++;
} catch (\Throwable $e) {
    echo "[FAILED]: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// TEST 7: Impor Soal Masal (QuestionImportService)
// -----------------------------------------------------------------------------
echo "7. Pengujian Impor Soal Masal (QuestionImportService)... ";
try {
    // Generate sample template CSV
    $sampleCsv = $importService->generateSampleCsv();
    if (empty($sampleCsv) || !str_contains($sampleCsv, 'multiple_choice')) {
        throw new Exception("Gagal menghasilkan template sample CSV.");
    }

    $tempCsvPath = tempnam(sys_get_temp_dir(), 'import_test_') . '.csv';
    file_put_contents($tempCsvPath, $sampleCsv);

    $importFile = [
        'name' => 'soal_import_matematika.csv',
        'tmp_name' => $tempCsvPath,
        'error' => UPLOAD_ERR_OK,
        'size' => filesize($tempCsvPath)
    ];

    $importRes = $importService->importFromFile((int)$bankId, (int)$teacherId, $importFile);
    if (!$importRes['success'] || $importRes['imported_count'] < 3) {
        throw new Exception("Gagal mengimpor file CSV: " . ($importRes['message'] ?? ''));
    }

    if (file_exists($tempCsvPath)) {
        @unlink($tempCsvPath);
    }

    echo "[PASSED]\n";
    $passCount++;
} catch (\Throwable $e) {
    echo "[FAILED]: " . $e->getMessage() . "\n";
}

// -----------------------------------------------------------------------------
// TEST 8: Pratinjau Butir Soal & Distribusi Statistik
// -----------------------------------------------------------------------------
echo "8. Pengujian Pratinjau Soal & Distribusi Statistik Bank Soal... ";
try {
    $bankDetails = $bankModel->getByIdWithDetails((int)$bankId);
    $stats = $bankDetails['stats'];

    if ((int)$stats['total_items'] < 4) {
        throw new Exception("Total butir soal terdata harus >= 4, didapat: " . $stats['total_items']);
    }

    if ((int)$stats['total_mc'] < 1 || (int)$stats['total_tf'] < 1 || (int)$stats['total_essay'] < 1) {
        throw new Exception("Distribusi tipe soal tidak lengkap.");
    }

    // Ambil detail soal untuk preview
    $allQ = $questionModel->getQuestionsByBankId((int)$bankId);
    if (empty($allQ)) {
        throw new Exception("Tidak ada soal yang diambil oleh getQuestionsByBankId.");
    }

    foreach ($allQ as $q) {
        if ($q['type'] === 'multiple_choice' && empty($q['options'])) {
            throw new Exception("Soal multiple choice #{$q['id']} tidak memuat options.");
        }
    }

    echo "[PASSED]\n";
    $passCount++;
} catch (\Throwable $e) {
    echo "[FAILED]: " . $e->getMessage() . "\n";
}

echo "\n======================================================\n";
echo "HASIL PENGUJIAN: {$passCount} / {$totalTests} Test Suite BERHASIL (" . round(($passCount / $totalTests) * 100) . "% PASSED)\n";
echo "======================================================\n\n";

if ($passCount === $totalTests) {
    exit(0);
} else {
    exit(1);
}
