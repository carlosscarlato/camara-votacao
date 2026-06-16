<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/helpers.php';
require_once __DIR__ . '/../config/bootstrap.php';

if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require_once __DIR__ . '/../vendor/autoload.php';
}

setCorsHeaders();
startSession();
resolveTenant();
requireAdminAuth();

$action = getAction();
$tid    = tenantId();
$tenant = getTenantData();

$controller = new \App\Controllers\ReportController(db(), $tid);
$exporter   = new \App\Services\ReportExportService();

switch ($action) {

    case 'gerar':
        $filters = [
            'tipo'        => (string)input('tipo', 'sessoes'),
            'data_inicio' => input('data_inicio'),
            'data_fim'    => input('data_fim'),
            'sessao_id'   => input('sessao_id'),
            'vereador_id' => input('vereador_id'),
            'resultado'   => input('resultado'),
            'status'      => input('status'),
        ];

        try {
            $report = $controller->query($filters);
        } catch (\InvalidArgumentException $e) {
            jsonError($e->getMessage());
        }

        $formato = strtolower((string)input('formato', 'json'));

        if ($formato === 'pdf') {
            $pdf = $exporter->toPdf($report, $tenant);
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="relatorio.pdf"');
            header('Content-Length: ' . strlen($pdf));
            echo $pdf;
            exit;
        }

        if ($formato === 'xlsx') {
            $xlsx = $exporter->toExcel($report);
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="relatorio.xlsx"');
            header('Content-Length: ' . strlen($xlsx));
            echo $xlsx;
            exit;
        }

        if ($formato === 'csv') {
            $csv = $exporter->toCsv($report);
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="relatorio.csv"');
            echo $csv;
            exit;
        }

        jsonSuccess([
            'titulo'  => $report['titulo'],
            'colunas' => $report['colunas'],
            'dados'   => $report['dados'],
            'total'   => count($report['dados']),
        ]);

    case 'listar_agendados':
        $stmt = db()->prepare("SELECT * FROM scheduled_reports WHERE tenant_id = ? ORDER BY id DESC");
        $stmt->execute([$tid]);
        jsonSuccess($stmt->fetchAll());

    case 'salvar_agendado':
        $type       = (string)requiredInput('type');
        $frequency  = (string)input('frequency', 'weekly');
        $recipients = json_encode(array_map('trim', explode(',', (string)requiredInput('recipients'))));
        $filters    = json_encode(input('filters') ?? []);

        if (!in_array($frequency, ['daily','weekly','monthly'], true)) jsonError('Frequência inválida.');

        $id = (int)input('id', 0);
        if ($id) {
            db()->prepare("UPDATE scheduled_reports SET type=?,frequency=?,recipients=?,filters=? WHERE id=? AND tenant_id=?")
                ->execute([$type, $frequency, $recipients, $filters, $id, $tid]);
        } else {
            db()->prepare("INSERT INTO scheduled_reports (tenant_id,type,frequency,recipients,filters) VALUES (?,?,?,?,?)")
                ->execute([$tid, $type, $frequency, $recipients, $filters]);
        }
        jsonSuccess(['message' => 'Agendamento salvo.']);

    case 'deletar_agendado':
        $id = (int)requiredInput('id');
        db()->prepare("DELETE FROM scheduled_reports WHERE id = ? AND tenant_id = ?")->execute([$id, $tid]);
        jsonSuccess(['message' => 'Agendamento removido.']);

    default:
        jsonError('Ação inválida.');
}
