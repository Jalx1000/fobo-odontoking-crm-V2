<?php

$envPath = __DIR__ . '/../.env';

if (! file_exists($envPath)) {
    fwrite(STDERR, "ERR: .env not found at {$envPath}\n");
    exit(1);
}

$lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$cfg = [];
foreach ($lines as $line) {
    if ($line[0] === '#' || strpos($line, '=') === false) {
        continue;
    }
    [$k, $v] = explode('=', $line, 2);
    $cfg[$k] = trim($v, "\"");
}

$host = $cfg['DB_HOST'] ?? 'localhost';
$port = $cfg['DB_PORT'] ?? '3306';
$db   = $cfg['DB_DATABASE'] ?? '';
$user = $cfg['DB_USERNAME'] ?? '';
$pass = $cfg['DB_PASSWORD'] ?? '';

if (! $db || ! $user) {
    fwrite(STDERR, "ERR: Missing DB configuration in .env\n");
    exit(1);
}

$dsn = "mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (Throwable $e) {
    fwrite(STDERR, "ERR: " . $e->getMessage() . "\n");
    exit(1);
}

function runCount(PDO $pdo, string $sql, string $label): void
{
    try {
        $cnt = (int) $pdo->query($sql)->fetchColumn();
        echo "{$label}: {$cnt}\n";
    } catch (Throwable $e) {
        echo "{$label}: ERR " . $e->getMessage() . "\n";
    }
}

echo "== BEFORE ==\n";
runCount($pdo, "SELECT COUNT(*) FROM attribute_values av LEFT JOIN persons p ON p.id = av.entity_id WHERE av.entity_type = 'persons' AND p.id IS NULL", "Orphans attribute_values(persons)");
runCount($pdo, "SELECT COUNT(*) FROM leads l LEFT JOIN persons p ON p.id = l.person_id WHERE l.person_id IS NOT NULL AND p.id IS NULL", "Broken lead.person_id");
runCount($pdo, "SELECT COUNT(*) FROM leads l LEFT JOIN lead_pipelines pl ON pl.id = l.lead_pipeline_id WHERE l.lead_pipeline_id IS NOT NULL AND pl.id IS NULL", "Broken lead.lead_pipeline_id");
runCount($pdo, "SELECT COUNT(*) FROM leads l LEFT JOIN lead_pipeline_stages s ON s.id = l.lead_pipeline_stage_id WHERE l.lead_pipeline_stage_id IS NOT NULL AND s.id IS NULL", "Broken lead.lead_pipeline_stage_id");

echo "== CLEANUP ==\n";
try {
    $affected = $pdo->exec("DELETE av FROM attribute_values av LEFT JOIN persons p ON p.id = av.entity_id WHERE av.entity_type = 'persons' AND p.id IS NULL");
    echo "Deleted orphan attribute_values(persons): {$affected}\n";
} catch (Throwable $e) {
    echo "Delete orphan attribute_values(persons): ERR " . $e->getMessage() . "\n";
}

try {
    $affected = $pdo->exec("UPDATE leads l LEFT JOIN persons p ON p.id = l.person_id SET l.person_id = NULL WHERE l.person_id IS NOT NULL AND p.id IS NULL");
    echo "Fixed lead.person_id set NULL: {$affected}\n";
} catch (Throwable $e) {
    echo "Fix lead.person_id: ERR " . $e->getMessage() . "\n";
}

try {
    $affected = $pdo->exec("UPDATE leads l LEFT JOIN lead_pipelines pl ON pl.id = l.lead_pipeline_id SET l.lead_pipeline_id = NULL WHERE l.lead_pipeline_id IS NOT NULL AND pl.id IS NULL");
    echo "Fixed lead.lead_pipeline_id set NULL: {$affected}\n";
} catch (Throwable $e) {
    echo "Fix lead.lead_pipeline_id: ERR " . $e->getMessage() . "\n";
}

try {
    $affected = $pdo->exec("UPDATE leads l LEFT JOIN lead_pipeline_stages s ON s.id = l.lead_pipeline_stage_id SET l.lead_pipeline_stage_id = NULL WHERE l.lead_pipeline_stage_id IS NOT NULL AND s.id IS NULL");
    echo "Fixed lead.lead_pipeline_stage_id set NULL: {$affected}\n";
} catch (Throwable $e) {
    echo "Fix lead.lead_pipeline_stage_id: ERR " . $e->getMessage() . "\n";
}

echo "== AFTER ==\n";
runCount($pdo, "SELECT COUNT(*) FROM attribute_values av LEFT JOIN persons p ON p.id = av.entity_id WHERE av.entity_type = 'persons' AND p.id IS NULL", "Orphans attribute_values(persons)");
runCount($pdo, "SELECT COUNT(*) FROM leads l LEFT JOIN persons p ON p.id = l.person_id WHERE l.person_id IS NOT NULL AND p.id IS NULL", "Broken lead.person_id");
runCount($pdo, "SELECT COUNT(*) FROM leads l LEFT JOIN lead_pipelines pl ON pl.id = l.lead_pipeline_id WHERE l.lead_pipeline_id IS NOT NULL AND pl.id IS NULL", "Broken lead.lead_pipeline_id");
runCount($pdo, "SELECT COUNT(*) FROM leads l LEFT JOIN lead_pipeline_stages s ON s.id = l.lead_pipeline_stage_id WHERE l.lead_pipeline_stage_id IS NOT NULL AND s.id IS NULL", "Broken lead.lead_pipeline_stage_id");

echo "DONE\n";

