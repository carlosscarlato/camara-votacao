<?php
function makePdo(): PDO {
    return new PDO('mysql:host=127.0.0.1;dbname=camara_votacao;charset=utf8mb4', 'root', 'root', [
        PDO::ATTR_ERRMODE                  => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
    ]);
}

function execMigration(string $file): void
{
    // Fresh connection per migration avoids unbuffered result leak
    $pdo = makePdo();
    echo "\nExecutando: " . basename($file) . "\n";
    $sql = file_get_contents($file);

    // Extract DELIMITER $$ blocks (stored procedures)
    $procs = [];
    $sql = preg_replace_callback(
        '/DELIMITER\s+\$\$(.*?)DELIMITER\s+;/si',
        function ($m) use (&$procs) {
            $body = trim($m[1]);
            // Remove trailing $$
            $body = preg_replace('/\$\$\s*$/', '', $body);
            $procs[] = trim($body);
            return '/* __PROC_' . (count($procs) - 1) . '__ */';
        },
        $sql
    );

    // Split remaining SQL by semicolons
    $stmts = preg_split('/;\s*(?:\n|$)/m', $sql);

    foreach ($stmts as $stmt) {
        $trimmed = trim($stmt);
        if ($trimmed === '') continue;
        // Skip pure-comment and pure-SELECT (informational) lines
        if (preg_match('/^(--.*|\/\*.*\*\/)$/s', $trimmed)) continue;
        if (preg_match('/^SELECT\s+[\'"][^\'"]+[\'"]/i', $trimmed)) {
            // Consume result to avoid unbuffered issue
            try { $pdo->query($trimmed)->fetchAll(); } catch (Exception $e) {}
            continue;
        }

        // Inject stored procedure
        if (preg_match('/\/\*\s*__PROC_(\d+)__\s*\*\//', $trimmed, $pm)) {
            $idx  = (int)$pm[1];
            $proc = $procs[$idx] ?? '';
            if ($proc !== '') {
                try {
                    $pdo->exec($proc);
                    echo "  [OK] PROCEDURE created\n";
                } catch (Exception $e) {
                    echo "  [WARN] " . $e->getMessage() . "\n";
                }
            }
            continue;
        }

        try {
            $pdo->exec($trimmed);
            $preview = str_replace("\n", ' ', substr($trimmed, 0, 55));
            echo "  [OK] $preview\n";
        } catch (Exception $e) {
            echo "  [WARN] " . $e->getMessage() . "\n";
        }
    }
}

$migrations = [
    __DIR__ . '/migrations/001_multi_tenant.sql',
    __DIR__ . '/migrations/002_reports.sql',
    __DIR__ . '/migrations/003_ai_chat.sql',
    __DIR__ . '/migrations/004_complementar.sql',
];

foreach ($migrations as $f) {
    execMigration($f);
}

// Read-only user — new connection
$pdo2 = makePdo();
echo "\nCriando usuário webvoto_readonly...\n";
try {
    $pdo2->exec("CREATE USER IF NOT EXISTS 'webvoto_readonly'@'localhost' IDENTIFIED BY 'R3adOnly!2024'");
    echo "  [OK] Usuário criado\n";
    $pdo2->exec("GRANT SELECT ON camara_votacao.* TO 'webvoto_readonly'@'localhost'");
    echo "  [OK] GRANT SELECT concedido\n";
    $pdo2->exec("FLUSH PRIVILEGES");
    echo "  [OK] FLUSH PRIVILEGES\n";
} catch (Exception $e) {
    echo "  [WARN] " . $e->getMessage() . "\n";
}

echo "\nTabelas no banco:\n";
foreach ($pdo2->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN) as $t) {
    echo "  - $t\n";
}
echo "\nPronto!\n";
