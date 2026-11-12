<?php
declare(strict_types=1);

$targetDb = 'ujian_dual_kamera_test';
$_ENV['DB_DATABASE'] = $targetDb;
$_SERVER['DB_DATABASE'] = $targetDb;
putenv("DB_DATABASE={$targetDb}");

require_once __DIR__ . '/../app/bootstrap.php';
\App\Config\Database::$database = $targetDb;

use App\Libraries\Database;
use App\Models\ExamModel;

echo "====================================================================\n";
echo "   COMPREHENSIVE TEST: EXAM SEARCH, FILTER, AND PAGINATION          \n";
echo "====================================================================\n\n";

$db = Database::getConnection();
$currentDb = $db->query("SELECT DATABASE()")->fetchColumn();
echo "[DATABASE] Connected to: {$currentDb}\n";
if ($currentDb !== 'ujian_dual_kamera_test') {
    die("FATAL: Must use ujian_dual_kamera_test!\n");
}

$model = new ExamModel();
$passCount = 0;
$failCount = 0;

function runTest(string $desc, callable $fn, &$passCount, &$failCount) {
    try {
        $result = $fn();
        if ($result === true) {
            echo " [PASS] {$desc}\n";
            $passCount++;
        } else {
            echo " [FAIL] {$desc}: returned false\n";
            $failCount++;
        }
    } catch (\Throwable $e) {
        echo " [FAIL] {$desc}: Exception " . get_class($e) . " - " . $e->getMessage() . "\n";
        $failCount++;
    }
}

// 1. Uji pencarian dengan search=test
runTest("1. Pencarian dengan search='test' (tanpa filter lain)", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, 'test', null, null, null);
    return is_array($res) && isset($res['data']) && isset($res['total']) && is_int($res['total']);
}, $passCount, $failCount);

// 2. Uji pencarian kosong (search=null dan search='')
runTest("2a. Pencarian kosong (search=null)", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, null, null, null, null);
    return is_array($res) && isset($res['data']) && isset($res['total']);
}, $passCount, $failCount);

runTest("2b. Pencarian string kosong (search='')", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, '', null, null, null);
    return is_array($res) && isset($res['data']) && isset($res['total']);
}, $passCount, $failCount);

runTest("2c. Pencarian whitespace saja (search='   ')", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, '   ', null, null, null);
    return is_array($res) && isset($res['data']) && isset($res['total']);
}, $passCount, $failCount);

// 3. Uji subject_id kosong ('', null, 'all')
runTest("3a. Filter subject_id kosong (subject_id='')", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, null, '', null, null);
    return is_array($res) && isset($res['data']);
}, $passCount, $failCount);

runTest("3b. Filter subject_id null", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, null, null, null, null);
    return is_array($res) && isset($res['data']);
}, $passCount, $failCount);

runTest("3c. Filter subject_id='all'", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, null, 'all', null, null);
    return is_array($res) && isset($res['data']);
}, $passCount, $failCount);

// 4. Uji status kosong ('', null, 'all', 'ongoing', status spesifik)
runTest("4a. Filter status kosong (status='')", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, null, null, '', null);
    return is_array($res) && isset($res['data']);
}, $passCount, $failCount);

runTest("4b. Filter status null", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, null, null, null, null);
    return is_array($res) && isset($res['data']);
}, $passCount, $failCount);

runTest("4c. Filter status='all'", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, null, null, 'all', null);
    return is_array($res) && isset($res['data']);
}, $passCount, $failCount);

runTest("4d. Filter status='ongoing' (menggunakan IN ('ongoing', 'in_progress'))", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, null, null, 'ongoing', null);
    return is_array($res) && isset($res['data']);
}, $passCount, $failCount);

runTest("4e. Filter status='published'", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, null, null, 'published', null);
    return is_array($res) && isset($res['data']);
}, $passCount, $failCount);

// 5. Uji kombinasi search, subject_id, dan status
runTest("5a. URL exact error kasus pengguna: search='test', subject_id='', status=''", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, 'test', '', '', null);
    return is_array($res) && isset($res['data']) && isset($res['total']);
}, $passCount, $failCount);

runTest("5b. URL exact dengan teacher_id (role Guru): search='test', subject_id='', status='', teacher_id=2", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, 'test', '', '', 2);
    return is_array($res) && isset($res['data']) && isset($res['total']);
}, $passCount, $failCount);

runTest("5c. Kombinasi lengkap: search='Matematika', subject_id='1', status='published', teacher_id=2", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, 'Matematika', '1', 'published', 2);
    return is_array($res) && isset($res['data']) && isset($res['total']);
}, $passCount, $failCount);

runTest("5d. Kombinasi lengkap: search='PAS', subject_id='2', status='ongoing', teacher_id=1", function() use ($model) {
    $res = $model->getPaginatedExams(1, 10, 'PAS', '2', 'ongoing', 1);
    return is_array($res) && isset($res['data']) && isset($res['total']);
}, $passCount, $failCount);

// 6. Uji pagination
runTest("6a. Pagination halaman 1 (page=1, perPage=5)", function() use ($model) {
    $res = $model->getPaginatedExams(1, 5, null, null, null, null);
    return $res['current_page'] === 1 && $res['per_page'] === 5 && count($res['data']) <= 5;
}, $passCount, $failCount);

runTest("6b. Pagination halaman 2 (page=2, perPage=5)", function() use ($model) {
    $res = $model->getPaginatedExams(2, 5, null, null, null, null);
    return $res['current_page'] === 2 && $res['per_page'] === 5;
}, $passCount, $failCount);

runTest("6c. Pagination dengan search aktif: page=1, perPage=2, search='a'", function() use ($model) {
    $res = $model->getPaginatedExams(1, 2, 'a', null, null, null);
    return $res['current_page'] === 1 && $res['per_page'] === 2 && count($res['data']) <= 2;
}, $passCount, $failCount);

// 7. Uji query count dan query data konsistensi
runTest("7. Konsistensi query count dan query data", function() use ($model) {
    $res = $model->getPaginatedExams(1, 100, null, null, null, null);
    return $res['total'] >= count($res['data']);
}, $passCount, $failCount);

// 8. Verifikasi tidak ada SQLSTATE[HY093] pada karakter khusus pencarian
runTest("8. Karakter khusus dalam pencarian ('test_100%', 'quote\'s', 'amp&')", function() use ($model) {
    $specialSearches = ['test_100%', "quote's", 'amp&', 'Math (X)', '100%'];
    foreach ($specialSearches as $s) {
        $res = $model->getPaginatedExams(1, 10, $s, null, null, 2);
        if (!is_array($res)) return false;
    }
    return true;
}, $passCount, $failCount);

echo "\n====================================================================\n";
echo "HASIL PENGUJIAN: {$passCount} PASS, {$failCount} FAIL\n";
echo "====================================================================\n";

if ($failCount > 0) {
    exit(1);
}
