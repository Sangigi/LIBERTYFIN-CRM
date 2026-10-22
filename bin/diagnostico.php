<?php
/**
 * ¿Está la base como el sistema espera?
 *
 * SOLO LEE. No crea, no borra, no cambia nada. Está hecho para correrlo
 * cuando algo no entra y no se sabe por dónde empezar.
 *
 *     php bin/diagnostico.php
 *
 * Comprueba la base PRINCIPAL: que conecte, que las tablas que el login
 * necesita existan, y que tengan las columnas que el código pide. Una
 * tabla con el nombre correcto pero las columnas equivocadas es peor que
 * una que falta, porque el error que sale («Unknown column») aparece
 * lejos de donde está el problema.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo desde la terminal.\n"); }

$raiz = dirname(__DIR__);
$cfgRuta = $raiz . '/config/config.php';
if (!is_file($cfgRuta)) exit("\n  ✗ Falta config/config.php\n\n");

$cfg = require $cfgRuta;
$bd  = $cfg['bd'] ?? [];
$principal = (string)($bd['principal'] ?? '');

echo "\n  Base principal: {$principal} en " . ($bd['host'] ?? '?') . "\n\n";

try {
    $puerto = !empty($bd['puerto']) ? ';port=' . (int)$bd['puerto'] : '';
    $db = new PDO("mysql:host={$bd['host']}{$puerto};dbname={$principal};charset=utf8mb4",
        $bd['usuario'], (string)($bd['clave'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
    echo "  ✓ Conecta\n";
} catch (PDOException $e) {
    exit("  ✗ No conecta: " . $e->getMessage() . "\n\n");
}

/** Las columnas que el código PIDE de cada tabla de la base principal. */
$esperado = [
    'usuarios_plataforma' => ['id','username','password','nombre','email','rol','activo','creado_en'],
    'empresas'            => ['id','nombre_empresa','nombre_base_datos','activo','plan',
                              'fecha_vencimiento','email_admin','usuario_admin'],
];

/**
 * Estas se crean solas la primera vez que alguien las usa, así que no
 * existir no es un problema: solo quiere decir que nadie ha pasado por
 * ahí todavía. Se listan para que su ausencia no asuste.
 */
$seCreanSolas = ['usuarios_indice', 'pagos_plan', 'solicitudes_empresa',
                 'tickets', 'ticket_mensajes', 'ticket_eventos', 'ticket_lecturas'];

$problemas = [];

foreach ($esperado as $tabla => $cols) {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
    $st->execute([$principal, $tabla]);
    if (!(int)$st->fetchColumn()) {
        echo "  · $tabla — no existe todavía\n";
        continue;
    }

    $st = $db->prepare("SELECT COLUMN_NAME FROM information_schema.COLUMNS
                         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
    $st->execute([$principal, $tabla]);
    $hay = array_column($st->fetchAll(), 'COLUMN_NAME');

    $faltan = array_diff($cols, $hay);
    if ($faltan) {
        echo "  ✗ $tabla — le faltan columnas: " . implode(', ', $faltan) . "\n";
        $n = (int)$db->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn();
        echo "      filas que tiene: $n\n";
        $problemas[] = [$tabla, $faltan, $n];
    } else {
        $n = (int)$db->query("SELECT COUNT(*) FROM `$tabla`")->fetchColumn();
        echo "  ✓ $tabla — bien ($n filas)\n";
    }
}

// Las que se crean solas: solo informativo.
echo "\n";
foreach ($seCreanSolas as $t2) {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
    $st->execute([$principal, $t2]);
    $hay = (int)$st->fetchColumn() > 0;
    echo '  ' . ($hay ? '·' : '·') . " $t2 — " . ($hay ? 'existe' : 'se creará sola al usarse') . "\n";
}

// ¿Hay ya por quién entrar?
$st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'usuarios_plataforma'");
$st->execute([$principal]);
if ((int)$st->fetchColumn()) {
    $n = (int)$db->query(
        "SELECT COUNT(*) FROM usuarios_plataforma WHERE rol = 'superadmin' AND activo = 1")
        ->fetchColumn();
    echo "\n  Superadministradores activos: $n\n";
    if ($n === 0) {
        echo "  → Entra a /instalar.php en el navegador para crear el primero.\n";
    } else {
        echo "  → Ya puedes entrar en /login. Borra public/instalar.php si sigue ahí.\n";
    }
} else {
    echo "\n  → Entra a /instalar.php en el navegador: crea la tabla y el primer acceso.\n";
}

if ($problemas) {
    echo "\n  ── QUÉ HACER CON LO MARCADO CON ✗ ──\n\n";
    foreach ($problemas as list($tabla, $faltan, $n)) {
        if ($n === 0) {
            echo "  `$tabla` está VACÍA y con la forma equivocada. Es seguro tirarla\n";
            echo "  para que el sistema la cree bien:\n\n";
            echo "      DROP TABLE `$tabla`;\n\n";
        } else {
            echo "  `$tabla` tiene $n filas y le faltan columnas. NO la tires: mira\n";
            echo "  qué hay dentro antes de decidir.\n\n";
        }
    }
}

echo "\n";
