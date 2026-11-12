<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * Report Service
 * Mengelola ekspor laporan rekapitulasi hasil ujian ke format PDF (via Dompdf)
 * dan format Excel (via PhpSpreadsheet) sesuai standar UC-25.
 */
class ReportService
{
    protected ResultService $resultService;

    public function __construct()
    {
        $this->resultService = new ResultService();
    }

    /**
     * Ekspor Rekapitulasi Nilai Ujian ke PDF (UC-25)
     *
     * @param int $examId ID Ujian
     * @return string Binary data PDF
     */
    public function exportExamReportPdf(int $examId): string
    {
        $data = $this->resultService->getExamSubmissions($examId);
        $exam = $data['exam'];
        $submissions = $data['submissions'];
        $summary = $data['summary'];

        if (!$exam) {
            throw new \RuntimeException("Ujian ID #{$examId} tidak ditemukan.");
        }

        $html = $this->generatePdfHtml($exam, $submissions, $summary);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return (string)$dompdf->output();
    }

    /**
     * Ekspor Rekapitulasi Nilai Ujian ke Excel (.xlsx) (UC-25)
     *
     * @param int $examId ID Ujian
     * @return string Binary data Excel
     */
    public function exportExamReportExcel(int $examId): string
    {
        $data = $this->resultService->getExamSubmissions($examId);
        $exam = $data['exam'];
        $submissions = $data['submissions'];
        $summary = $data['summary'];

        if (!$exam) {
            throw new \RuntimeException("Ujian ID #{$examId} tidak ditemukan.");
        }

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Rekap Nilai');

        // 1. Judul & Header Laporan
        $sheet->setCellValue('A1', 'REKAPITULASI HASIL EVALUASI UJIAN ONLINE');
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        $sheet->setCellValue('A2', 'Sistem Ujian Online Dual Kamera AI Proctoring');
        $sheet->mergeCells('A2:J2');
        $sheet->getStyle('A2')->getFont()->setItalic(true)->setSize(10);
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // 2. Info Metadata Ujian
        $sheet->setCellValue('A4', 'Nama Ujian:');
        $sheet->setCellValue('B4', $exam['title']);
        $sheet->setCellValue('A5', 'Kode Ujian:');
        $sheet->setCellValue('B5', $exam['code']);
        $sheet->setCellValue('A6', 'KKM / Passing Grade:');
        $sheet->setCellValue('B6', (float)$exam['passing_grade']);

        $sheet->setCellValue('F4', 'Total Peserta:');
        $sheet->setCellValue('G4', $summary['total_submissions'] . ' Siswa');
        $sheet->setCellValue('F5', 'Rata-Rata Nilai:');
        $sheet->setCellValue('G5', $summary['average_score']);
        $sheet->setCellValue('F6', 'Persentase Kelulusan:');
        $sheet->setCellValue('G6', $summary['pass_rate'] . '%');

        $sheet->getStyle('A4:A6')->getFont()->setBold(true);
        $sheet->getStyle('F4:F6')->getFont()->setBold(true);

        // 3. Header Tabel Nilai
        $headers = [
            'A8' => 'No',
            'B8' => 'NIS / Username',
            'C8' => 'Nama Peserta',
            'D8' => 'Kelas',
            'E8' => 'Nilai PG',
            'F8' => 'Nilai Esai',
            'G8' => 'Nilai Akhir',
            'H8' => 'KKM',
            'I8' => 'Status Kelulusan',
            'J8' => 'Integritas AI (%)'
        ];

        foreach ($headers as $cell => $text) {
            $sheet->setCellValue($cell, $text);
        }

        $headerStyle = [
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '1E3A8A'] // Navy Blue
            ],
            'borders' => [
                'allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => '000000']]
            ]
        ];
        $sheet->getStyle('A8:J8')->applyFromArray($headerStyle);
        $sheet->getRowDimension(8)->setRowHeight(25);

        // 4. Baris Data Siswa
        $row = 9;
        $no = 1;
        foreach ($submissions as $sub) {
            $sheet->setCellValue('A' . $row, $no++);
            $sheet->setCellValue('B' . $row, $sub['student_nis'] ?? '-');
            $sheet->setCellValue('C' . $row, $sub['student_name']);
            $sheet->setCellValue('D' . $row, $sub['class_name'] ?? 'Reguler');
            $sheet->setCellValue('E' . $row, (float)($sub['multiple_choice_score'] ?? 0));
            $sheet->setCellValue('F' . $row, (float)($sub['essay_score'] ?? 0));
            $sheet->setCellValue('G' . $row, (float)($sub['final_score'] ?? 0));
            $sheet->setCellValue('H' . $row, (float)$exam['passing_grade']);
            
            $statusText = !empty($sub['is_passed']) ? 'LULUS' : 'REMEDIAL';
            $sheet->setCellValue('I' . $row, $statusText);
            $sheet->setCellValue('J' . $row, (float)($sub['integrity_score'] ?? 100));

            // Alignments
            $sheet->getStyle('A' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('D' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('E' . $row . ':H' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
            $sheet->getStyle('I' . $row . ':J' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Conditional styling for Pass / Remedial
            if (!empty($sub['is_passed'])) {
                $sheet->getStyle('I' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('008000'))->setBold(true);
            } else {
                $sheet->getStyle('I' . $row)->getFont()->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('D9534F'))->setBold(true);
            }

            $row++;
        }

        // Border untuk tabel
        $tableRange = 'A8:J' . ($row - 1);
        $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);

        // Auto size columns
        foreach (range('A', 'J') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        // Tulis output ke buffer
        $writer = new Xlsx($spreadsheet);
        ob_start();
        $writer->save('php://output');
        return (string)ob_get_clean();
    }

    /**
     * Ekspor Rapor Hasil Ujian Siswa Perorangan ke PDF
     *
     * @param int $sessionId ID sesi ujian siswa
     * @return string Binary data PDF
     */
    public function exportStudentPdf(int $sessionId): string
    {
        $data = $this->resultService->getSessionAnswersForGrading($sessionId);
        if (!$data) {
            throw new \RuntimeException("Data sesi ujian #{$sessionId} tidak ditemukan.");
        }

        $header = $data['header'];
        $answers = $data['answers'];

        $html = $this->generateStudentReportHtml($header, $answers);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return (string)$dompdf->output();
    }

    /**
     * Template HTML untuk Rekapitulasi Nilai PDF
     */
    protected function generatePdfHtml(array $exam, array $submissions, array $summary): string
    {
        $dateStr = date('d F Y, H:i');
        $passingGrade = (float)($exam['passing_grade'] ?? 75.0);

        $rowsHtml = '';
        $no = 1;
        foreach ($submissions as $s) {
            $isPassed = !empty($s['is_passed']);
            $badgeColor = $isPassed ? '#16a34a' : '#dc2626';
            $badgeBg = $isPassed ? '#dcfce7' : '#fee2e2';
            $statusLabel = $isPassed ? 'LULUS' : 'REMEDIAL';

            $rowsHtml .= "
                <tr>
                    <td style='text-align: center;'>{$no}</td>
                    <td style='text-align: center; font-family: monospace;'>" . htmlspecialchars((string)($s['student_nis'] ?? '-')) . "</td>
                    <td><strong>" . htmlspecialchars($s['student_name']) . "</strong></td>
                    <td style='text-align: center;'>" . htmlspecialchars((string)($s['class_name'] ?? 'Reguler')) . "</td>
                    <td style='text-align: right;'>" . number_format((float)($s['multiple_choice_score'] ?? 0), 1) . "</td>
                    <td style='text-align: right;'>" . number_format((float)($s['essay_score'] ?? 0), 1) . "</td>
                    <td style='text-align: right; font-weight: bold;'>" . number_format((float)($s['final_score'] ?? 0), 1) . "</td>
                    <td style='text-align: center;'>
                        <span style='background-color: {$badgeBg}; color: {$badgeColor}; padding: 3px 8px; border-radius: 4px; font-weight: bold; font-size: 11px;'>
                            {$statusLabel}
                        </span>
                    </td>
                    <td style='text-align: center;'>" . number_format((float)($s['integrity_score'] ?? 100), 0) . "%</td>
                </tr>
            ";
            $no++;
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Rekap Hasil Ujian - " . htmlspecialchars($exam['title']) . "</title>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #1e293b; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 12px; margin-bottom: 20px; }
                .header h2 { margin: 0 0 4px 0; font-size: 18px; color: #0f172a; text-transform: uppercase; }
                .header p { margin: 0; font-size: 11px; color: #64748b; }
                .meta-table { width: 100%; margin-bottom: 20px; }
                .meta-table td { padding: 4px 8px; font-size: 12px; }
                .stats-box { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
                .stats-box td { border: 1px solid #cbd5e1; padding: 8px 12px; text-align: center; background-color: #f8fafc; }
                .stats-box .val { font-size: 16px; font-weight: bold; color: #0284c7; display: block; }
                .stats-box .lbl { font-size: 10px; text-transform: uppercase; color: #64748b; }
                .data-table { width: 100%; border-collapse: collapse; margin-top: 10px; }
                .data-table th { background-color: #1e3a8a; color: #ffffff; padding: 8px; font-size: 11px; text-transform: uppercase; border: 1px solid #1e3a8a; }
                .data-table td { padding: 6px 8px; border: 1px solid #e2e8f0; font-size: 11px; }
                .data-table tr:nth-child(even) { background-color: #f8fafc; }
                .footer { margin-top: 30px; font-size: 10px; color: #94a3b8; text-align: right; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2>Rekapitulasi Hasil Evaluasi Ujian</h2>
                <p>Sistem Ujian Online Dual Kamera AI Proctoring &bull; Dicetak: {$dateStr}</p>
            </div>

            <table class='meta-table'>
                <tr>
                    <td width='20%'><strong>Nama Ujian</strong></td>
                    <td width='30%'>: " . htmlspecialchars($exam['title']) . " (" . htmlspecialchars($exam['code']) . ")</td>
                    <td width='20%'><strong>KKM / Passing Grade</strong></td>
                    <td width='30%'>: " . number_format($passingGrade, 1) . "</td>
                </tr>
                <tr>
                    <td><strong>Mata Pelajaran</strong></td>
                    <td>: " . htmlspecialchars($exam['subject_name'] ?? 'Umum') . "</td>
                    <td><strong>Durasi Waktu</strong></td>
                    <td>: " . (int)$exam['duration_minutes'] . " Menit</td>
                </tr>
            </table>

            <table class='stats-box'>
                <tr>
                    <td width='25%'>
                        <span class='val'>{$summary['total_submissions']}</span>
                        <span class='lbl'>Total Peserta</span>
                    </td>
                    <td width='25%'>
                        <span class='val'>{$summary['average_score']}</span>
                        <span class='lbl'>Rata-Rata Nilai</span>
                    </td>
                    <td width='25%'>
                        <span class='val' style='color: #16a34a;'>{$summary['pass_rate']}%</span>
                        <span class='lbl'>Tingkat Kelulusan ({$summary['total_passed']} Lulus)</span>
                    </td>
                    <td width='25%'>
                        <span class='val'>{$summary['highest_score']} / {$summary['lowest_score']}</span>
                        <span class='lbl'>Tertinggi / Terendah</span>
                    </td>
                </tr>
            </table>

            <table class='data-table'>
                <thead>
                    <tr>
                        <th width='5%'>No</th>
                        <th width='12%'>NIS</th>
                        <th width='30%'>Nama Siswa</th>
                        <th width='10%'>Kelas</th>
                        <th width='8%'>Nilai PG</th>
                        <th width='8%'>Nilai Esai</th>
                        <th width='9%'>Nilai Akhir</th>
                        <th width='10%'>Status</th>
                        <th width='8%'>Integritas</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>

            <div class='footer'>
                Dokumen ini digenerasi secara otomatis oleh Sistem Ujian Online Dual Kamera AI &bull; Halaman 1
            </div>
        </body>
        </html>
        ";
    }

    /**
     * Template HTML Rapor Siswa Individual PDF
     */
    protected function generateStudentReportHtml(array $header, array $answers): string
    {
        $finalScore = (float)($header['final_score'] ?? 0.0);
        $passingGrade = (float)($header['passing_grade'] ?? 75.0);
        $isPassed = ($finalScore >= $passingGrade);

        $answersHtml = '';
        foreach ($answers as $idx => $a) {
            $num = $idx + 1;
            $type = $a['question_type'];
            $isCorrect = !empty($a['is_correct']);
            $icon = $isCorrect ? '&#10004; Benar' : '&#10008; Salah / Kurang Tepat';
            $color = $isCorrect ? '#16a34a' : '#dc2626';

            $answersHtml .= "
                <div style='margin-bottom: 15px; border: 1px solid #e2e8f0; border-radius: 6px; padding: 10px;'>
                    <div style='display: flex; justify-content: space-between; font-weight: bold; margin-bottom: 6px;'>
                        <span>Soal #{$num} (" . strtoupper($type) . ") - Bobot: " . (float)$a['question_weight'] . "</span>
                        <span style='color: {$color};'>{$icon} (Skor: " . (float)$a['score_earned'] . ")</span>
                    </div>
                    <div style='margin-bottom: 6px;'>" . htmlspecialchars($a['question_content']) . "</div>
                    <div style='font-size: 11px; background-color: #f8fafc; padding: 6px; border-radius: 4px;'>
            ";

            if ($type !== 'essay') {
                $answersHtml .= "<strong>Jawaban Siswa:</strong> " . htmlspecialchars((string)($a['selected_option_text'] ?? '-')) . "<br>";
                $answersHtml .= "<strong>Kunci Jawaban:</strong> " . htmlspecialchars((string)($a['correct_option_text'] ?? '-'));
            } else {
                $answersHtml .= "<strong>Jawaban Esai:</strong> " . nl2br(htmlspecialchars((string)($a['essay_answer'] ?? '-')));
            }

            if (!empty($a['teacher_feedback'])) {
                $answersHtml .= "<div style='margin-top: 4px; color: #0284c7;'><strong>Catatan Guru:</strong> " . htmlspecialchars($a['teacher_feedback']) . "</div>";
            }

            if (!empty($a['ai_explanation'])) {
                $answersHtml .= "<div style='margin-top: 4px; color: #475569;'><strong>Pembahasan AI:</strong> " . htmlspecialchars($a['ai_explanation']) . "</div>";
            }

            $answersHtml .= "</div></div>";
        }

        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <title>Laporan Hasil Ujian - " . htmlspecialchars($header['student_name']) . "</title>
            <style>
                body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 12px; color: #1e293b; margin: 20px; }
                .header { text-align: center; border-bottom: 2px solid #0f172a; padding-bottom: 10px; margin-bottom: 15px; }
                .score-banner { background-color: " . ($isPassed ? '#dcfce7' : '#fee2e2') . "; color: " . ($isPassed ? '#16a34a' : '#dc2626') . "; text-align: center; padding: 15px; border-radius: 8px; margin-bottom: 20px; }
                .score-val { font-size: 32px; font-weight: bold; margin: 0; }
            </style>
        </head>
        <body>
            <div class='header'>
                <h2 style='margin: 0;'>LEMBAR HASIL EVALUASI UJIAN ONLINE</h2>
                <p style='margin: 4px 0 0 0; color: #64748b;'>Sistem Dual Kamera AI Proctoring</p>
            </div>

            <table width='100%' style='margin-bottom: 15px;'>
                <tr>
                    <td width='20%'><strong>Nama Siswa</strong></td>
                    <td width='30%'>: " . htmlspecialchars($header['student_name']) . "</td>
                    <td width='20%'><strong>Ujian</strong></td>
                    <td width='30%'>: " . htmlspecialchars($header['exam_title']) . "</td>
                </tr>
                <tr>
                    <td><strong>Mata Pelajaran</strong></td>
                    <td>: " . htmlspecialchars($header['subject_name']) . "</td>
                    <td><strong>KKM</strong></td>
                    <td>: " . number_format($passingGrade, 1) . "</td>
                </tr>
            </table>

            <div class='score-banner'>
                <div class='score-val'>" . number_format($finalScore, 1) . "</div>
                <div style='font-size: 14px; font-weight: bold; margin-top: 4px;'>STATUS: " . ($isPassed ? 'LULUS (MEMENUHI KKM)' : 'REMEDIAL (DI BAWAH KKM)') . "</div>
            </div>

            <h4 style='border-bottom: 1px solid #cbd5e1; padding-bottom: 4px; margin-bottom: 10px;'>Rincian Butir Soal & Evaluasi:</h4>
            {$answersHtml}
        </body>
        </html>
        ";
    }
}
