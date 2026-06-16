<?php
declare(strict_types=1);

// ┌─────────────────────────────────────────────────────────────┐
// │  COPIE este arquivo para config/database.php e preencha     │
// │  com as credenciais reais do ambiente.                       │
// │                                                              │
// │  ⚠  NUNCA commite config/database.php no git.               │
// └─────────────────────────────────────────────────────────────┘

define('DB_HOST',    'localhost');
define('DB_PORT',    '3306');
define('DB_NAME',    'camara_votacao');
define('DB_USER',    'camara_user');      // NÃO use root em produção
define('DB_PASS',    'TROQUE_AQUI');
define('DB_CHARSET', 'utf8mb4');

// Domínio da aplicação (usado no CORS e cookies)
// Exemplo produção: 'https://votacao.camaramunicipal.gov.br'
// Exemplo local:    'http://localhost'
define('APP_DOMAIN', 'http://localhost');

date_default_timezone_set('UTC');

final class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
            );
            try {
                self::$instance = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES   => false,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci; SET time_zone = '+00:00'",
                ]);
            } catch (PDOException $e) {
                http_response_code(500);
                die(json_encode(['success' => false, 'error' => 'Falha na conexão com o banco de dados.'], JSON_UNESCAPED_UNICODE));
            }
        }
        return self::$instance;
    }

    private function __construct() {}
    private function __clone() {}
}

function db(): PDO { return Database::getInstance(); }
