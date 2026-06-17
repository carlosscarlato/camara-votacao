<?php
declare(strict_types=1);
namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class ReportExportService
{
    public function toPdf(array $reportData, array $tenant): string
    {
        $titulo  = htmlspecialchars($reportData['titulo']);
        $colunas = $reportData['colunas'];
        $dados   = $reportData['dados'];
        $empresa = htmlspecialchars($tenant['company_name'] ?? 'WebVoto');
        $primary = $tenant['primary_color'] ?? '#1e3a5f';
        $data    = date('d/m/Y H:i');

        $thStyle = "background:$primary;color:#fff;padding:8px 6px;font-size:11px;text-align:left;";
        $ths     = implode('', array_map(
            fn($c) => "<th style='$thStyle'>" . htmlspecialchars($c) . "</th>",
            $colunas
        ));

        $rows = '';
        foreach ($dados as $i => $row) {
            $bg  = $i % 2 === 0 ? '#f8fafc' : '#ffffff';
            $tds = implode('', array_map(
                fn($v) => "<td style='padding:6px;font-size:10px;border-bottom:1px solid #e2e8f0'>"
                        . htmlspecialchars((string)($v ?? '')) . "</td>",
                array_values($row)
            ));
            $rows .= "<tr style='background:$bg'>$tds</tr>";
        }

        $html = "<!DOCTYPE html><html><head><meta charset='utf-8'>
        <style>body{font-family:DejaVu Sans,sans-serif;margin:0;padding:20px;font-size:11px;}
        table{width:100%;border-collapse:collapse;}
        .header{display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid $primary;}
        </style></head><body>
        <div class='header'><div><strong style='color:$primary'>$empresa</strong></div>
        <div style='text-align:right;color:#64748b;font-size:10px;'>Gerado em: $data</div></div>
        <h2 style='color:$primary;margin-bottom:16px;font-size:14px;'>$titulo</h2>
        <table><thead><tr>$ths</tr></thead><tbody>$rows</tbody></table>
        </body></html>";

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        return $dompdf->output();
    }

    public function toExcel(array $reportData): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet       = $spreadsheet->getActiveSheet();
        $sheet->setTitle(mb_substr($reportData['titulo'], 0, 31));

        $col = 'A';
        foreach ($reportData['colunas'] as $header) {
            $sheet->setCellValue($col . '1', $header);
            $sheet->getStyle($col . '1')->applyFromArray([
                'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill'      => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_LEFT],
            ]);
            $sheet->getColumnDimension($col)->setAutoSize(true);
            $col++;
        }

        $row = 2;
        foreach ($reportData['dados'] as $dataRow) {
            $col = 'A';
            foreach (array_values($dataRow) as $val) {
                $sheet->setCellValue($col . $row, $val ?? '');
                $col++;
            }
            $row++;
        }

        $writer = new Xlsx($spreadsheet);
        $tmp    = tempnam(sys_get_temp_dir(), 'report_') . '.xlsx';
        $writer->save($tmp);
        $content = file_get_contents($tmp);
        unlink($tmp);
        return $content;
    }

    public function toCsv(array $reportData): string
    {
        $output = fopen('php://temp', 'r+');
        fwrite($output, "\xEF\xBB\xBF"); // BOM UTF-8 para Excel
        fputcsv($output, $reportData['colunas'], ';');
        foreach ($reportData['dados'] as $row) {
            fputcsv($output, array_values($row), ';');
        }
        rewind($output);
        $content = stream_get_contents($output);
        fclose($output);
        return $content;
    }

    public function toJson(array $reportData): string
    {
        return json_encode([
            'titulo'    => $reportData['titulo'],
            'gerado_em' => date('c'),
            'total'     => count($reportData['dados']),
            'colunas'   => $reportData['colunas'],
            'dados'     => $reportData['dados'],
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }
}
