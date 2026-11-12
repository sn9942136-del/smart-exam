<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\QuestionBankModel;
use App\Models\QuestionImportModel;
use App\Models\QuestionModel;
use App\Models\QuestionOptionModel;

/**
 * Question Import Service
 * Mengimpor butir soal secara masal dari berkas Excel (.xlsx), CSV, TSV, dan JSON.
 */
class QuestionImportService
{
    protected QuestionService $questionService;
    protected QuestionBankModel $bankModel;
    protected QuestionImportModel $importModel;
    protected QuestionModel $questionModel;
    protected QuestionOptionModel $optionModel;

    public function __construct()
    {
        $this->questionService = new QuestionService();
        $this->bankModel = new QuestionBankModel();
        $this->importModel = new QuestionImportModel();
        $this->questionModel = new QuestionModel();
        $this->optionModel = new QuestionOptionModel();
    }

    /**
     * Proses impor berkas soal
     */
    public function importFromFile(int $bankId, int $userId, array $file): array
    {
        $origName = (string)($file['name'] ?? 'import.csv');
        $tmpPath = (string)($file['tmp_name'] ?? '');
        $fileSize = (int)($file['size'] ?? 0);

        if ($file['error'] !== UPLOAD_ERR_OK || empty($tmpPath) || !file_exists($tmpPath)) {
            return [
                'success' => false,
                'imported_count' => 0,
                'errors' => ['Berkas gagal diunggah ke server.'],
                'message' => 'Gagal mengunggah berkas impor.'
            ];
        }

        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
        $validExts = ['csv', 'xlsx', 'xls', 'tsv', 'json', 'txt'];
        if (!in_array($ext, $validExts, true)) {
            return [
                'success' => false,
                'imported_count' => 0,
                'errors' => ['Format berkas tidak didukung. Harap gunakan format .xlsx, .csv, atau .json.'],
                'message' => 'Format berkas tidak didukung.'
            ];
        }

        // Catat entri import awal di database
        $importRecordId = $this->importModel->insert([
            'question_bank_id' => $bankId,
            'user_id' => $userId,
            'file_name' => $origName,
            'file_type' => in_array($ext, ['xlsx', 'xls'], true) ? 'excel' : ($ext === 'json' ? 'json' : 'csv'),
            'status' => 'processing',
            'total_imported' => 0,
            'error_log' => null,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        // Parse rows
        $parsedData = $this->parseFile($tmpPath, $ext);
        if (!$parsedData['success']) {
            $this->importModel->update($importRecordId, [
                'status' => 'failed',
                'error_log' => implode("\n", $parsedData['errors'])
            ]);

            return [
                'success' => false,
                'imported_count' => 0,
                'errors' => $parsedData['errors'],
                'message' => 'Gagal membaca isi berkas impor.'
            ];
        }

        $rows = $parsedData['rows'];
        if (empty($rows)) {
            $this->importModel->update($importRecordId, [
                'status' => 'failed',
                'error_log' => 'Berkas tidak mengandung data butir soal yang valid.'
            ]);

            return [
                'success' => false,
                'imported_count' => 0,
                'errors' => ['Berkas kosong atau tidak ada data yang terbaca.'],
                'message' => 'Berkas tidak mengandung data butir soal.'
            ];
        }

        // Proses batch insert
        $importedCount = 0;
        $rowErrors = [];

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2; // Mengingat baris 1 adalah header

            // Normalisasi data baris
            $normalized = $this->normalizeRow($row);
            if (!$normalized['valid']) {
                $rowErrors[] = "Baris {$rowNumber}: " . implode(', ', $normalized['errors']);
                continue;
            }

            $questionData = $normalized['data'];

            // Simpan via QuestionService
            $result = $this->questionService->createQuestion($bankId, $questionData);
            if ($result['success']) {
                $importedCount++;
            } else {
                $errorMsgs = array_values($result['errors']);
                $rowErrors[] = "Baris {$rowNumber}: " . implode(', ', $errorMsgs);
            }
        }

        $status = ($importedCount > 0) ? 'completed' : 'failed';
        $errorLogText = !empty($rowErrors) ? implode("\n", $rowErrors) : null;

        $this->importModel->update($importRecordId, [
            'status' => $status,
            'total_imported' => $importedCount,
            'error_log' => $errorLogText
        ]);

        $this->bankModel->recalculateTotalQuestions($bankId);

        return [
            'success' => $importedCount > 0,
            'imported_count' => $importedCount,
            'errors' => $rowErrors,
            'message' => "Berhasil mengimpor {$importedCount} butir soal ke dalam bank soal." . (!empty($rowErrors) ? ' (' . count($rowErrors) . ' baris bermasalah)' : '')
        ];
    }

    /**
     * Parsing berkas berdasarkan ekstensi
     */
    protected function parseFile(string $filePath, string $ext): array
    {
        if ($ext === 'json') {
            return $this->parseJson($filePath);
        }

        if (in_array($ext, ['xlsx', 'xls'], true) && class_exists('\\PhpOffice\\PhpSpreadsheet\\IOFactory')) {
            return $this->parseWithPhpSpreadsheet($filePath);
        }

        // Fallback untuk CSV / TSV / Excel CSV
        return $this->parseCsvOrTsv($filePath);
    }

    /**
     * Parsing CSV / TSV dengan auto-detect pemisah (koma, titik-koma, tab)
     */
    protected function parseCsvOrTsv(string $filePath): array
    {
        $content = file_get_contents($filePath);
        if ($content === false) {
            return ['success' => false, 'rows' => [], 'errors' => ['Tidak dapat membaca berkas.']];
        }

        // Hapus UTF-8 BOM jika ada
        if (str_starts_with($content, "\xEF\xBB\xBF")) {
            $content = substr($content, 3);
        }

        // Deteksi delimiter
        $firstLine = strtok($content, "\r\n") ?: '';
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        $lines = preg_split("/\r\n|\n|\r/", $content);
        if (empty($lines)) {
            return ['success' => false, 'rows' => [], 'errors' => ['Berkas CSV kosong.']];
        }

        $headerRow = array_shift($lines);
        $headers = str_getcsv((string)$headerRow, $delimiter);
        if (empty($headers)) {
            return ['success' => false, 'rows' => [], 'errors' => ['Header kolom tidak ditemukan.']];
        }

        $cleanHeaders = array_map(function ($h) {
            return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', (string)$h)));
        }, $headers);

        $rows = [];
        foreach ($lines as $line) {
            if (trim((string)$line) === '') {
                continue;
            }
            $cols = str_getcsv((string)$line, $delimiter);
            if (empty(array_filter($cols))) {
                continue;
            }

            $rowAssoc = [];
            foreach ($cleanHeaders as $i => $headerName) {
                if ($headerName !== '') {
                    $rowAssoc[$headerName] = trim((string)($cols[$i] ?? ''));
                }
            }
            $rows[] = $rowAssoc;
        }

        return [
            'success' => true,
            'rows' => $rows,
            'errors' => []
        ];
    }

    /**
     * Parsing JSON
     */
    protected function parseJson(string $filePath): array
    {
        $content = file_get_contents($filePath);
        $decoded = json_decode((string)$content, true);

        if (!is_array($decoded)) {
            return [
                'success' => false,
                'rows' => [],
                'errors' => ['Format berkas JSON tidak valid: ' . json_last_error_msg()]
            ];
        }

        // Jika JSON berisi root objek `questions`
        if (isset($decoded['questions']) && is_array($decoded['questions'])) {
            $decoded = $decoded['questions'];
        }

        return [
            'success' => true,
            'rows' => $decoded,
            'errors' => []
        ];
    }

    /**
     * Parsing dengan PhpSpreadsheet jika terpasang
     */
    protected function parseWithPhpSpreadsheet(string $filePath): array
    {
        try {
            /** @var mixed $spreadsheet */
            $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($filePath);
            $sheet = $spreadsheet->getActiveSheet();
            $data = $sheet->toArray(null, true, true, true);

            if (empty($data)) {
                return ['success' => false, 'rows' => [], 'errors' => ['Lembar kerja Excel kosong.']];
            }

            $headerRow = array_shift($data);
            $headers = [];
            foreach ($headerRow as $colLetter => $headerText) {
                $headers[$colLetter] = strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', (string)$headerText)));
            }

            $rows = [];
            foreach ($data as $row) {
                if (empty(array_filter($row))) {
                    continue;
                }
                $rowAssoc = [];
                foreach ($row as $colLetter => $val) {
                    $key = $headers[$colLetter] ?? '';
                    if ($key !== '') {
                        $rowAssoc[$key] = trim((string)$val);
                    }
                }
                $rows[] = $rowAssoc;
            }

            return ['success' => true, 'rows' => $rows, 'errors' => []];
        } catch (\Throwable $e) {
            return ['success' => false, 'rows' => [], 'errors' => ['Gagal membaca berkas Excel: ' . $e->getMessage()]];
        }
    }

    /**
     * Normalisasi baris data menjadi struktur QuestionService
     */
    protected function normalizeRow(array $row): array
    {
        $errors = [];

        // 1. Tipe Soal
        $rawType = strtolower(trim((string)($row['type'] ?? $row['jenis_soal'] ?? $row['jenis'] ?? 'multiple_choice')));
        $type = 'multiple_choice';
        if (in_array($rawType, ['essay', 'esai', 'uraian'], true)) {
            $type = 'essay';
        } elseif (in_array($rawType, ['true_false', 'benar_salah', 'tf', 'bs'], true)) {
            $type = 'true_false';
        }

        // 2. Teks Soal
        $content = trim((string)($row['content'] ?? $row['soal'] ?? $row['pertanyaan'] ?? $row['teks_soal'] ?? ''));
        if ($content === '') {
            $errors[] = 'Teks soal kosong';
        }

        // 3. Kesulitan
        $rawDiff = strtolower(trim((string)($row['difficulty'] ?? $row['kesulitan'] ?? $row['tingkat'] ?? 'medium')));
        $difficulty = 'medium';
        if (in_array($rawDiff, ['easy', 'mudah'], true)) {
            $difficulty = 'easy';
        } elseif (in_array($rawDiff, ['hard', 'sukar', 'sulit'], true)) {
            $difficulty = 'hard';
        }

        // 4. Bobot
        $rawWeight = (float)($row['weight'] ?? $row['bobot'] ?? $row['nilai'] ?? 1.0);
        $weight = ($rawWeight > 0) ? $rawWeight : 1.0;

        // 5. Pembahasan / Penjelasan
        $explanation = trim((string)($row['explanation'] ?? $row['pembahasan'] ?? $row['keterangan'] ?? ''));

        // 6. Opsi & Kunci Jawaban
        $options = [];
        $correctOption = 'A';
        $correctTf = 'Benar';

        if ($type === 'multiple_choice') {
            $optA = trim((string)($row['option_a'] ?? $row['pilihan_a'] ?? $row['opsi_a'] ?? $row['a'] ?? ''));
            $optB = trim((string)($row['option_b'] ?? $row['pilihan_b'] ?? $row['opsi_b'] ?? $row['b'] ?? ''));
            $optC = trim((string)($row['option_c'] ?? $row['pilihan_c'] ?? $row['opsi_c'] ?? $row['c'] ?? ''));
            $optD = trim((string)($row['option_d'] ?? $row['pilihan_d'] ?? $row['opsi_d'] ?? $row['d'] ?? ''));
            $optE = trim((string)($row['option_e'] ?? $row['pilihan_e'] ?? $row['opsi_e'] ?? $row['e'] ?? ''));

            if ($optA !== '') $options['A'] = $optA;
            if ($optB !== '') $options['B'] = $optB;
            if ($optC !== '') $options['C'] = $optC;
            if ($optD !== '') $options['D'] = $optD;
            if ($optE !== '') $options['E'] = $optE;

            if (count($options) < 2) {
                $errors[] = 'Pilihan ganda minimal membutuhkan 2 opsi jawaban (A dan B)';
            }

            $rawCorrect = strtoupper(trim((string)($row['correct_answer'] ?? $row['kunci_jawaban'] ?? $row['kunci'] ?? $row['correct_option'] ?? 'A')));
            if (!isset($options[$rawCorrect])) {
                $correctOption = array_key_first($options) ?: 'A';
            } else {
                $correctOption = $rawCorrect;
            }
        } elseif ($type === 'true_false') {
            $rawTf = strtolower(trim((string)($row['correct_answer'] ?? $row['kunci_jawaban'] ?? $row['kunci'] ?? 'benar')));
            if (in_array($rawTf, ['salah', 'false', 'f', 'b_salah', 's'], true)) {
                $correctTf = 'Salah';
            } else {
                $correctTf = 'Benar';
            }
        }

        if (!empty($errors)) {
            return [
                'valid' => false,
                'errors' => $errors,
                'data' => []
            ];
        }

        return [
            'valid' => true,
            'errors' => [],
            'data' => [
                'type' => $type,
                'content' => $content,
                'difficulty' => $difficulty,
                'weight' => $weight,
                'explanation' => $explanation,
                'options' => $options,
                'correct_option' => $correctOption,
                'correct_tf' => $correctTf
            ]
        ];
    }

    /**
     * Hasilkan template CSV contoh siap pakai untuk diunduh pengguna
     */
    public function generateSampleCsv(): string
    {
        $headers = [
            'type',
            'content',
            'difficulty',
            'weight',
            'option_a',
            'option_b',
            'option_c',
            'option_d',
            'option_e',
            'correct_answer',
            'explanation'
        ];

        $sampleRows = [
            [
                'multiple_choice',
                'Berapakah hasil dari 15 x 12 + 25?',
                'easy',
                '2.00',
                '185',
                '205',
                '215',
                '195',
                '225',
                'B',
                'Perhitungan: 15 x 12 = 180, kemudian 180 + 25 = 205.'
            ],
            [
                'multiple_choice',
                'Organel sel yang berfungsi sebagai tempat respirasi sel dan penghasil energi utama adalah...',
                'medium',
                '2.50',
                'Ribosom',
                'Mitokondria',
                'Badan Golgi',
                'Lisosom',
                'Kloroplas',
                'B',
                'Mitokondria merupakan pembangkit energi seluler (ATP) melalui respirasi aerob.'
            ],
            [
                'true_false',
                'Protokol HTTPS menggunakan enkripsi TLS/SSL pada port standar 443.',
                'easy',
                '1.50',
                '',
                '',
                '',
                '',
                '',
                'Benar',
                'HTTPS beroperasi secara terenkripsi menggunakan port 443.'
            ],
            [
                'essay',
                'Jelaskan perbedaan mendasar antara model kecerdasan buatan Supervised Learning dan Unsupervised Learning disertai contoh penerapannya!',
                'hard',
                '5.00',
                '',
                '',
                '',
                '',
                '',
                '',
                'Supervised learning menggunakan dataset berlabel (misal: klasifikasi email spam), sedangkan unsupervised learning mengelompokkan data tanpa label (misal: segmentasi pasar clustering).'
            ]
        ];

        $output = fopen('php://temp', 'r+');
        // Tambahkan UTF-8 BOM untuk kompatibilitas Microsoft Excel
        fputs($output, "\xEF\xBB\xBF");
        fputcsv($output, $headers);

        foreach ($sampleRows as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return (string)$csv;
    }
}
