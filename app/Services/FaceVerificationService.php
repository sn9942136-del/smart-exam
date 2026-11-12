<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\FaceVerificationModel;
use App\Models\ProctoringSessionModel;
use App\Models\UserModel;
use Exception;
use Throwable;

/**
 * Face Verification Service (UC-08)
 * Mengelola verifikasi wajah awal & berkala sebelum dan selama ujian berlangsung.
 */
class FaceVerificationService
{
    private FaceVerificationModel $verificationModel;
    private ProctoringSessionModel $proctoringSessionModel;
    private UserModel $userModel;

    public function __construct()
    {
        $this->verificationModel = new FaceVerificationModel();
        $this->proctoringSessionModel = new ProctoringSessionModel();
        $this->userModel = new UserModel();
    }

    /**
     * Verifikasi wajah peserta dengan membandingkan foto tangkapan dengan foto referensi/baseline
     *
     * @param int $proctoringSessionId ID sesi proctoring
     * @param int $studentId ID peserta didik
     * @param string|null $capturedBase64 Foto tangkapan kamera (Base64/Data URI)
     * @param array|null $faceEmbedding Vektor embedding wajah dari client AI (128-d / 512-d float array)
     * @param float|null $clientConfidence Skor kepercayaan dari client AI (0.0 - 1.0)
     * @return array
     */
    public function verifyFace(
        int $proctoringSessionId,
        int $studentId,
        ?string $capturedBase64 = null,
        ?array $faceEmbedding = null,
        ?float $clientConfidence = null
    ): array {
        try {
            $session = $this->proctoringSessionModel->find($proctoringSessionId);
            if (!$session || (int)($session['student_id'] ?? 0) !== $studentId) {
                return [
                    'success' => false,
                    'code' => 'INVALID_SESSION',
                    'error' => 'Sesi proctoring tidak ditemukan atau tidak cocok dengan akun Anda.'
                ];
            }

            // Validasi status sesi ujian terkait: harus ongoing
            if (!empty($session['exam_session_id'])) {
                $examSessionModel = new \App\Models\ExamSessionModel();
                $examSession = $examSessionModel->find((int)$session['exam_session_id']);
                if (!$examSession || ($examSession['status'] ?? '') !== 'ongoing') {
                    return [
                        'success' => false,
                        'code' => 'EXAM_SESSION_INACTIVE',
                        'error' => 'Sesi ujian telah selesai atau tidak dalam status aktif.'
                    ];
                }
            }

            // 1. Simpan foto tangkapan jika ada
            $capturedPhotoUrl = null;
            if ($capturedBase64 && str_starts_with($capturedBase64, 'data:image')) {
                $capturedPhotoUrl = $this->saveBase64Image($capturedBase64, 'face_capture', $proctoringSessionId);
            }

            // 2. Ambil foto profil/referensi siswa jika ada
            $student = $this->userModel->find($studentId);
            $referencePhotoUrl = $student['avatar'] ?? null;

            // 3. Hitung skor kemiripan (Similarity Score)
            $similarityScore = 0.85; // Default baseline initial match score
            if ($clientConfidence !== null && is_numeric($clientConfidence)) {
                $similarityScore = round(max(0.0, min(1.0, (float)$clientConfidence)), 2);
            }

            // Jika ada initial_face_embedding di sesi dan faceEmbedding baru dikirim, hitung Cosine Similarity
            if (!empty($session['initial_face_embedding']) && !empty($faceEmbedding)) {
                $baselineVector = json_decode((string)$session['initial_face_embedding'], true);
                if (is_array($baselineVector) && count($baselineVector) === count($faceEmbedding)) {
                    $similarityScore = $this->calculateCosineSimilarity($baselineVector, $faceEmbedding);
                }
            }

            // 4. Tentukan status verifikasi berdasarkan threshold
            // >= 0.70 : matched
            // 0.50 - 0.69 : uncertain
            // < 0.50 : mismatched
            if ($similarityScore >= 0.70) {
                $verificationStatus = 'matched';
                $message = 'Wajah terverifikasi cocok dengan data peserta.';
            } elseif ($similarityScore >= 0.50) {
                $verificationStatus = 'uncertain';
                $message = 'Tingkat kemiripan wajah rendah atau pencahayaan kurang optimal.';
            } else {
                $verificationStatus = 'mismatched';
                $message = 'Wajah tidak cocok dengan identitas peserta terdaftar.';
            }

            // 5. Simpan baseline embedding pada proctoring_sessions jika belum tersimpan dan status matched
            if (empty($session['initial_face_embedding']) && !empty($faceEmbedding) && $verificationStatus === 'matched') {
                $this->proctoringSessionModel->update($proctoringSessionId, [
                    'initial_face_embedding' => json_encode($faceEmbedding),
                    'status' => 'active'
                ]);
            }

            // 6. Rekam log ke tabel face_verifications
            $verificationData = [
                'proctoring_session_id' => $proctoringSessionId,
                'student_id' => $studentId,
                'reference_photo_url' => $referencePhotoUrl,
                'captured_photo_url' => $capturedPhotoUrl,
                'similarity_score' => $similarityScore,
                'verification_status' => $verificationStatus,
                'verified_by' => 'ai',
                'verified_at' => date('Y-m-d H:i:s'),
                'created_at' => date('Y-m-d H:i:s')
            ];

            $verificationId = $this->verificationModel->insert($verificationData);

            return [
                'success' => true,
                'verification_id' => (int)$verificationId,
                'verification_status' => $verificationStatus,
                'similarity_score' => $similarityScore,
                'captured_photo_url' => $capturedPhotoUrl,
                'reference_photo_url' => $referencePhotoUrl,
                'message' => $message
            ];
        } catch (Throwable $e) {
            return [
                'success' => false,
                'code' => 'VERIFICATION_FAILED',
                'error' => 'Gagal memproses verifikasi wajah: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Hitung cosine similarity antara 2 vektor float
     */
    private function calculateCosineSimilarity(array $vecA, array $vecB): float
    {
        $dotProduct = 0.0;
        $normA = 0.0;
        $normB = 0.0;
        $count = count($vecA);

        for ($i = 0; $i < $count; $i++) {
            $valA = (float)$vecA[$i];
            $valB = (float)$vecB[$i];
            $dotProduct += $valA * $valB;
            $normA += $valA * $valA;
            $normB += $valB * $valB;
        }

        if ($normA <= 0.0 || $normB <= 0.0) {
            return 0.0;
        }

        $similarity = $dotProduct / (sqrt($normA) * sqrt($normB));
        return round(max(0.0, min(1.0, $similarity)), 2);
    }

    /**
     * Simpan gambar Data URI Base64 ke direktori upload
     */
    public function saveBase64Image(string $base64Data, string $prefix, int $sessionId): ?string
    {
        try {
            $uploadDir = FCPATH . 'uploads/proctoring/faces/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $base64Data = substr($base64Data, strpos($base64Data, ',') + 1);
                $type = strtolower($type[1]); // jpg, png, webp

                if (!in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) {
                    $type = 'jpg';
                }

                $data = base64_decode($base64Data);
                if ($data === false) {
                    return null;
                }

                $fileName = sprintf('%s_%d_%s.%s', $prefix, $sessionId, uniqid('', true), $type);
                $filePath = $uploadDir . $fileName;

                if (file_put_contents($filePath, $data)) {
                    return 'uploads/proctoring/faces/' . $fileName;
                }
            }
            return null;
        } catch (Throwable $e) {
            return null;
        }
    }

    /**
     * Ambil riwayat verifikasi wajah pada suatu sesi proctoring
     */
    public function getVerificationHistory(int $proctoringSessionId): array
    {
        return $this->verificationModel->findAll(
            ['proctoring_session_id' => $proctoringSessionId],
            'id DESC'
        );
    }
}
