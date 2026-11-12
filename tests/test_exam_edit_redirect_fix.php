<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/bootstrap.php';

use App\Controllers\ExamController;
use App\Libraries\Database;
use App\Libraries\Request;
use App\Libraries\Response;
use App\Libraries\Session;
use App\Models\ExamModel;
use App\Models\UserModel;

Session::start();

echo "==============================================================================\n";
echo "TEST SUITE: VERIFIKASI PERBAIKAN EDIT UJIAN & REDIRECT DUAL CAM AI\n";
echo "==============================================================================\n\n";

$passCount = 0;
$failCount = 0;

function it(string $description, callable $fn): void
{
    global $passCount, $failCount;
    try {
        $fn();
        echo "  [PASS] {$description}\n";
        $passCount++;
    } catch (\Throwable $e) {
        echo "  [FAIL] {$description}: " . $e->getMessage() . " (" . $e->getFile() . ":" . $e->getLine() . ")\n";
        $failCount++;
    }
}

$userModel = new UserModel();
$examModel = new ExamModel();

// 1. Ambil exam 18 atau buat fixture ujian khusus pengujian edit
$testExam = $examModel->find(18);
$createdDedicatedExam = false;
if (!$testExam) {
    $insertedId = $examModel->insert([
        'code'             => 'EXAM-EDIT-TEST-' . time(),
        'title'            => 'Ujian Uji Edit Redirect',
        'subject_id'       => 1,
        'teacher_id'       => 3,
        'type'             => 'daily_test',
        'duration_minutes' => 60,
        'passing_grade'    => 75.00,
        'status'           => 'published',
        'created_at'       => date('Y-m-d H:i:s'),
        'updated_at'       => date('Y-m-d H:i:s')
    ]);
    $testExam = $examModel->find($insertedId);
    $createdDedicatedExam = true;
}
$examId = (int)$testExam['id'];
$guruId = (int)$testExam['teacher_id'];

$otherGuruId = $guruId + 999;
$adminId = 1;

// -----------------------------------------------------------------------------
// TEST 1: Update Ujian oleh Guru Valid & Cek URL Redirect
// -----------------------------------------------------------------------------
it("Guru berhasil menyimpan perubahan ujian dan URL redirect valid (tanpa path berulang)", function () use ($guruId, $examId, $testExam, $examModel) {
    // Set sesi login sebagai Guru pemilik ujian
    Session::set('user', [
        'id'       => $guruId,
        'role'     => 'guru',
        'name'     => 'Guru Penguji',
        'email'    => 'guru@example.com',
        'username' => 'guru_budi'
    ]);

    $newTitle = 'Ujian Terverifikasi ' . date('His');
    $postData = [
        'title'                => $newTitle,
        'type'                 => 'midterm',
        'subject_id'           => (string)$testExam['subject_id'],
        'duration_minutes'     => '90',
        'passing_grade'        => '80',
        'status'               => 'draft',
        'description'          => 'Catatan pembaruan terverifikasi',
        'is_dual_cam_required' => '1',
        'lock_browser'         => '1'
    ];

    $request = new Request([], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/guru/exams/update/{$examId}"], $postData, []);
    $controller = new ExamController($request);

    $response = $controller->update($examId);

    // Verifikasi Status Code
    if ($response->getStatusCode() !== 302) {
        throw new RuntimeException("Status code diharapkan 302, didapat: " . $response->getStatusCode());
    }

    // Verifikasi Header Location
    $location = $response->getHeader('Location');
    $expectedUrl = base_url("guru/exams/show/{$examId}");

    if ($location !== $expectedUrl) {
        throw new RuntimeException("Location redirect salah. Diharapkan: '{$expectedUrl}', didapat: '{$location}'");
    }

    // Pastikan tidak ada penggabungan route berulang
    if (str_contains((string)$location, '/guru/exams/update/')) {
        throw new RuntimeException("Location mengandung path update berulang: '{$location}'");
    }

    // Verifikasi perubahan tersimpan di database
    $updatedExam = $examModel->find($examId);
    if ($updatedExam['title'] !== $newTitle) {
        throw new RuntimeException("Title di DB tidak terupdate. Diharapkan '{$newTitle}', didapat: '{$updatedExam['title']}'");
    }
    if ((int)$updatedExam['duration_minutes'] !== 90) {
        throw new RuntimeException("Durasi di DB tidak terupdate ke 90");
    }
});

// -----------------------------------------------------------------------------
// TEST 2: Buka kembali form edit dan verifikasi data tersimpan ditampilkan
// -----------------------------------------------------------------------------
it("Form edit ujian dapat dibuka kembali dan menampilkan perubahan yang tersimpan", function () use ($guruId, $examId, $examModel) {
    Session::set('user', [
        'id'       => $guruId,
        'role'     => 'guru',
        'name'     => 'Guru Penguji',
        'email'    => 'guru@example.com'
    ]);

    $request = new Request(['id' => (string)$examId], ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => "/guru/exams/edit/{$examId}"], [], []);
    $controller = new ExamController($request);

    $response = $controller->edit($examId);

    if ($response->getStatusCode() !== 200) {
        throw new RuntimeException("Status code edit bukan 200: " . $response->getStatusCode());
    }

    $content = $response->getContent();
    $updatedExam = $examModel->find($examId);

    if (!str_contains($content, htmlspecialchars($updatedExam['title'], ENT_QUOTES, 'UTF-8'))) {
        throw new RuntimeException("Judul ujian terbaru tidak ditemukan dalam output HTML form edit.");
    }

    // Pastikan form action mengarah ke base_url update yang benar
    $expectedAction = base_url("guru/exams/update/{$examId}");
    if (!str_contains($content, 'action="' . $expectedAction . '"')) {
        throw new RuntimeException("Form action pada view edit tidak sesuai. Diharapkan: '{$expectedAction}'");
    }
});

// -----------------------------------------------------------------------------
// TEST 3: Validasi data tidak valid & Cek URL Redirect Kembali ke Form Edit
// -----------------------------------------------------------------------------
it("Validasi input gagal me-redirect ke form edit dengan URL valid dan flash errors", function () use ($guruId, $examId, $testExam) {
    Session::set('user', [
        'id'       => $guruId,
        'role'     => 'guru',
        'name'     => 'Guru Penguji'
    ]);

    // Kosongkan judul (wajib)
    $invalidData = [
        'title'            => '',
        'subject_id'       => (string)$testExam['subject_id'],
        'duration_minutes' => '60',
        'passing_grade'    => '75'
    ];

    $request = new Request([], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/guru/exams/update/{$examId}"], $invalidData, []);
    $controller = new ExamController($request);

    $response = $controller->update($examId);

    if ($response->getStatusCode() !== 302) {
        throw new RuntimeException("Status code diharapkan 302, didapat: " . $response->getStatusCode());
    }

    $location = $response->getHeader('Location');
    $expectedUrl = base_url("guru/exams/edit/{$examId}");

    if ($location !== $expectedUrl) {
        throw new RuntimeException("Location redirect validasi salah. Diharapkan: '{$expectedUrl}', didapat: '{$location}'");
    }

    $errors = session_flash('errors');
    if (empty($errors) || !isset($errors['title'])) {
        throw new RuntimeException("Flash errors untuk judul kosong tidak tersimpan.");
    }
});

// -----------------------------------------------------------------------------
// TEST 4: Otorisasi Guru - Guru lain tidak boleh mengedit ujian bukan miliknya
// -----------------------------------------------------------------------------
it("Guru lain ditolak ketika mencoba mengedit ujian milik guru pertama", function () use ($otherGuruId, $examId) {
    Session::set('user', [
        'id'       => $otherGuruId,
        'role'     => 'guru',
        'name'     => 'Guru Lain'
    ]);

    $postData = ['title' => 'Upaya Pembobolan Edit'];
    $request = new Request([], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/guru/exams/update/{$examId}"], $postData, []);
    $controller = new ExamController($request);

    $response = $controller->update($examId);

    if ($response->getStatusCode() !== 302) {
        throw new RuntimeException("Diharapkan 302, didapat: " . $response->getStatusCode());
    }

    $location = $response->getHeader('Location');
    $expectedUrl = base_url('guru/exams');

    if ($location !== $expectedUrl) {
        throw new RuntimeException("Redirect penolakan otorisasi salah. Diharapkan: '{$expectedUrl}', didapat: '{$location}'");
    }

    $dangerFlash = Session::getFlash('danger');
    $lastMsg = end($dangerFlash) ?: '';
    if (!str_contains($lastMsg, 'Anda tidak memiliki hak akses')) {
        throw new RuntimeException("Flash danger otorisasi tidak sesuai. Didapat: " . print_r($dangerFlash, true));
    }
});

// -----------------------------------------------------------------------------
// TEST 5: Fitur Edit Ujian oleh Admin
// -----------------------------------------------------------------------------
it("Admin dapat memperbarui ujian dan me-redirect ke URL admin/exams/show/{id}", function () use ($adminId, $examId, $testExam, $examModel) {
    Session::set('user', [
        'id'       => $adminId,
        'role'     => 'admin',
        'name'     => 'Super Administrator'
    ]);

    $adminUpdatedTitle = 'Ujian Diedit Admin ' . date('His');
    $postData = [
        'title'            => $adminUpdatedTitle,
        'type'             => 'final_exam',
        'subject_id'       => (string)$testExam['subject_id'],
        'duration_minutes' => '120',
        'passing_grade'    => '75',
        'status'           => 'published'
    ];

    $request = new Request([], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/admin/exams/update/{$examId}"], $postData, []);
    $controller = new ExamController($request);

    $response = $controller->update($examId);

    if ($response->getStatusCode() !== 302) {
        throw new RuntimeException("Status code diharapkan 302, didapat: " . $response->getStatusCode());
    }

    $location = $response->getHeader('Location');
    $expectedUrl = base_url("admin/exams/show/{$examId}");

    if ($location !== $expectedUrl) {
        throw new RuntimeException("Location redirect admin salah. Diharapkan: '{$expectedUrl}', didapat: '{$location}'");
    }

    $updatedExam = $examModel->find($examId);
    if ($updatedExam['title'] !== $adminUpdatedTitle) {
        throw new RuntimeException("Title di DB tidak terupdate oleh admin.");
    }
});

// -----------------------------------------------------------------------------
// TEST 6: Pengujian CSRF Filter melalui Router Dispatch
// -----------------------------------------------------------------------------
it("CsrfFilter memvalidasi token CSRF pada request POST ke route update", function () use ($examId) {
    $csrfFilter = new \App\Filters\CsrfFilter();
    
    // Request tanpa token CSRF
    $requestWithoutCsrf = new Request([], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/guru/exams/update/{$examId}"], [], []);
    $filterResponse = $csrfFilter->before($requestWithoutCsrf);
    
    if ($filterResponse === null || $filterResponse->getStatusCode() !== 302) {
        throw new RuntimeException("Request tanpa CSRF token seharusnya diblokir dengan redirect 302.");
    }
    $danger = Session::getFlash('danger');
    if (empty($danger) || !str_contains($danger[0], 'CSRF')) {
        throw new RuntimeException("Flash danger CSRF tidak ditemukan.");
    }

    // Request dengan token CSRF yang valid
    $validToken = Session::getCsrfToken();
    $requestWithCsrf = new Request([], ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => "/guru/exams/update/{$examId}"], ['_csrf_token' => $validToken], []);
    $filterResponseValid = $csrfFilter->before($requestWithCsrf);

    if ($filterResponseValid !== null) {
        throw new RuntimeException("Request dengan CSRF valid seharusnya lolos (null).");
    }
});

// -----------------------------------------------------------------------------
// TEST 7: Uji Konsistensi Helper BaseController::redirect()
// -----------------------------------------------------------------------------
it("BaseController::redirect mengonversi path relatif menjadi Full Base URL secara aman", function () {
    $dummy = new class extends \App\Controllers\BaseController {
        public function testRedirect(string $path): Response {
            return $this->redirect($path);
        }
    };

    $res1 = $dummy->testRedirect('guru/exams/show/18');
    if ($res1->getHeader('Location') !== base_url('guru/exams/show/18')) {
        throw new RuntimeException("Path relatif tanpa slash gagal dikonversi.");
    }

    $res2 = $dummy->testRedirect('/guru/exams/show/18');
    if ($res2->getHeader('Location') !== base_url('guru/exams/show/18')) {
        throw new RuntimeException("Path relatif dengan leading slash gagal dikonversi.");
    }

    $res3 = $dummy->testRedirect('http://example.com/external');
    if ($res3->getHeader('Location') !== 'http://example.com/external') {
        throw new RuntimeException("Full URL absolut tidak boleh diubah.");
    }
});

// Kembalikan data exam ke kondisi awal atau hapus fixture khusus
if ($createdDedicatedExam) {
    $examModel->delete($examId);
} else {
    $examModel->update($examId, [
        'title'            => $testExam['title'],
        'type'             => $testExam['type'],
        'subject_id'       => $testExam['subject_id'],
        'duration_minutes' => $testExam['duration_minutes'],
        'passing_grade'    => $testExam['passing_grade'],
        'status'           => $testExam['status'],
        'description'      => $testExam['description'] ?? null
    ]);
}

echo "\n==============================================================================\n";
echo "RINGKASAN HASIL TEST: {$passCount} PASSED, {$failCount} FAILED\n";
echo "==============================================================================\n";

if ($failCount > 0) {
    exit(1);
}
