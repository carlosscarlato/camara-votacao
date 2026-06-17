<?php
declare(strict_types=1);
namespace App\Controllers;

use PDO;

class ReportController
{
    public function __construct(private PDO $db, private int $tenantId) {}

    public function query(array $filters): array
    {
        return match ($filters['tipo'] ?? 'sessoes') {
            'sessoes'    => $this->relatorioSessoes($filters),
            'votos'      => $this->relatorioVotos($filters),
            'vereadores' => $this->relatorioVereadores($filters),
            'usuarios'   => $this->relatorioUsuarios($filters),
            'tribuna'    => $this->relatorioTribuna($filters),
            'auditoria'  => $this->relatorioAuditoria($filters),
            default      => throw new \InvalidArgumentException("Tipo inválido: {$filters['tipo']}"),
        };
    }

    private function relatorioSessoes(array $f): array
    {
        $where  = ['s.tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];

        if (!empty($f['data_inicio'])) { $where[] = 's.data >= :di'; $params[':di'] = $f['data_inicio']; }
        if (!empty($f['data_fim']))    { $where[] = 's.data <= :df'; $params[':df'] = $f['data_fim']; }
        if (!empty($f['status']))      { $where[] = 's.status = :st'; $params[':st'] = $f['status']; }

        $sql = "
            SELECT s.id, s.numero, s.data, s.tipo, s.status,
                   COUNT(DISTINCT od.id)           AS total_votacoes,
                   SUM(od.resultado='aprovado')    AS aprovadas,
                   SUM(od.resultado='rejeitado')   AS rejeitadas,
                   u.nome                          AS presidente
            FROM   sessoes_plenarias s
            LEFT JOIN ordem_do_dia od ON od.sessao_id = s.id
            LEFT JOIN usuarios     u  ON u.id = s.usuario_id
            WHERE  " . implode(' AND ', $where) . "
            GROUP  BY s.id
            ORDER  BY s.data DESC, s.id DESC
            LIMIT  500
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return [
            'titulo'  => 'Relatório de Sessões Plenárias',
            'colunas' => ['Nº','Data','Tipo','Status','Votações','Aprovadas','Rejeitadas','Presidente'],
            'dados'   => $stmt->fetchAll(),
        ];
    }

    private function relatorioVotos(array $f): array
    {
        $where  = ['s.tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];

        if (!empty($f['data_inicio'])) { $where[] = 's.data >= :di'; $params[':di'] = $f['data_inicio']; }
        if (!empty($f['data_fim']))    { $where[] = 's.data <= :df'; $params[':df'] = $f['data_fim']; }
        if (!empty($f['sessao_id']))   { $where[] = 's.id = :sid';  $params[':sid'] = (int)$f['sessao_id']; }
        if (!empty($f['resultado']))   { $where[] = 'od.resultado = :res'; $params[':res'] = $f['resultado']; }

        $sql = "
            SELECT p.tipo, p.numero, p.ano, p.ementa, p.autor,
                   od.resultado, od.votos_sim, od.votos_nao, od.votos_abstencao,
                   od.votos_ausente, od.tipo_votacao,
                   s.numero AS sessao_numero, s.data AS sessao_data
            FROM   ordem_do_dia od
            JOIN   proposicoes        p ON p.id = od.proposicao_id
            JOIN   sessoes_plenarias  s ON s.id = od.sessao_id
            WHERE  od.resultado IS NOT NULL
              AND  " . implode(' AND ', $where) . "
            ORDER  BY s.data DESC, od.id DESC
            LIMIT  1000
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return [
            'titulo'  => 'Relatório de Votações',
            'colunas' => ['Tipo','Nº','Ano','Ementa','Autor','Resultado','SIM','NÃO','ABSTENÇÃO','AUSENTE','Tipo Votação','Sessão','Data'],
            'dados'   => $stmt->fetchAll(),
        ];
    }

    private function relatorioVereadores(array $f): array
    {
        $where  = ['v.tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];
        if (!empty($f['status'])) { $where[] = 'v.status = :st'; $params[':st'] = $f['status']; }

        $sql = "
            SELECT v.nome, v.partido, v.status,
                   COUNT(DISTINCT vt.ordem_dia_id)                         AS total_votacoes,
                   SUM(vt.voto = 'SIM')                                    AS votos_sim,
                   SUM(vt.voto = 'NAO')                                    AS votos_nao,
                   SUM(vt.voto = 'ABSTENCAO')                              AS abstencoes,
                   SUM(vt.voto = 'AUSENTE')                                AS ausencias,
                   ROUND(SUM(vt.voto='SIM')*100/NULLIF(COUNT(vt.id),0),1) AS pct_sim
            FROM   vereadores v
            LEFT JOIN votos vt ON vt.vereador_id = v.id
            WHERE  " . implode(' AND ', $where) . "
            GROUP  BY v.id
            ORDER  BY v.nome
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return [
            'titulo'  => 'Relatório de Vereadores',
            'colunas' => ['Nome','Partido','Status','Votações','SIM','NÃO','Abstenções','Ausências','% SIM'],
            'dados'   => $stmt->fetchAll(),
        ];
    }

    private function relatorioUsuarios(array $f): array
    {
        $where  = ['u.tenant_id = :tid'];
        $params = [':tid' => $this->tenantId, ':tid2' => $this->tenantId];

        $sql = "
            SELECT u.nome, u.login, u.email, u.perfil, u.ativo,
                   COUNT(l.id)        AS total_acoes,
                   MAX(l.created_at)  AS ultimo_acesso,
                   u.created_at
            FROM   usuarios u
            LEFT JOIN logs_sistema l ON l.usuario_id = u.id AND l.tenant_id = :tid2
            WHERE  " . implode(' AND ', $where) . "
            GROUP  BY u.id
            ORDER  BY u.nome
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return [
            'titulo'  => 'Relatório de Usuários',
            'colunas' => ['Nome','Login','Email','Perfil','Ativo','Ações','Último Acesso','Criado em'],
            'dados'   => $stmt->fetchAll(),
        ];
    }

    private function relatorioTribuna(array $f): array
    {
        $where  = ['s.tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];
        if (!empty($f['data_inicio'])) { $where[] = 's.data >= :di'; $params[':di'] = $f['data_inicio']; }
        if (!empty($f['data_fim']))    { $where[] = 's.data <= :df'; $params[':df'] = $f['data_fim']; }

        $sql = "
            SELECT v.nome, v.partido,
                   COUNT(ct.id)                               AS usos_tribuna,
                   SUM(ct.tempo_inicial_segundos)             AS tempo_total_seg,
                   ROUND(AVG(ct.tempo_inicial_segundos)/60,1) AS media_min,
                   s.numero AS sessao_numero, s.data
            FROM   controle_tribuna ct
            JOIN   vereadores        v ON v.id = ct.vereador_id
            JOIN   sessoes_plenarias s ON s.id = ct.sessao_id
            WHERE  ct.status = 'encerrado'
              AND  " . implode(' AND ', $where) . "
            GROUP  BY v.id, s.id
            ORDER  BY s.data DESC
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return [
            'titulo'  => 'Relatório da Tribuna',
            'colunas' => ['Vereador','Partido','Usos','Tempo Total (s)','Média (min)','Sessão','Data'],
            'dados'   => $stmt->fetchAll(),
        ];
    }

    private function relatorioAuditoria(array $f): array
    {
        $where  = ['l.tenant_id = :tid'];
        $params = [':tid' => $this->tenantId];
        if (!empty($f['data_inicio'])) { $where[] = 'l.created_at >= :di'; $params[':di'] = $f['data_inicio']; }
        if (!empty($f['data_fim']))    { $where[] = 'l.created_at <= :df'; $params[':df'] = $f['data_fim'] . ' 23:59:59'; }

        $sql = "
            SELECT l.created_at, l.acao, l.detalhes, l.ip_origem,
                   u.nome AS usuario, v.nome AS vereador
            FROM   logs_sistema l
            LEFT JOIN usuarios   u ON u.id = l.usuario_id
            LEFT JOIN vereadores v ON v.id = l.vereador_id
            WHERE  " . implode(' AND ', $where) . "
            ORDER  BY l.id DESC LIMIT 2000
        ";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return [
            'titulo'  => 'Log de Auditoria',
            'colunas' => ['Data/Hora','Ação','Detalhes','IP','Usuário','Vereador'],
            'dados'   => $stmt->fetchAll(),
        ];
    }
}
