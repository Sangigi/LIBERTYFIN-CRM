<?php
/**
 * Crea las tablas de la base PRINCIPAL que falten.
 *
 *     php bin/esquema-plataforma.php
 *
 * POR QUÉ HACE FALTA
 *
 * Casi todas las tablas de la base principal se crean solas la primera
 * vez que alguien las usa: `usuarios_indice` desde AutenticacionRepo,
 * `pagos_plan` desde PlanRepo, los tickets desde TicketRepo,
 * `solicitudes_empresa` desde CrearEmpresa.
 *
 * `empresas` no. En la instalación original venía de un volcado de SQL,
 * así que nunca hizo falta crearla. Sobre una base nueva, el
 * superadministrador entra y el panel revienta con «Table 'empresas'
 * doesn't exist» antes de poder hacer nada.
 *
 * `public/instalar.php` ya la crea, pero se apaga en cuanto existe un
 * superadministrador. Si ya pasaste por ahí, esto es la salida.
 *
 * Es IDEMPOTENTE: usa CREATE TABLE IF NOT EXISTS. Correrlo sobre una
 * base que ya tiene todo no cambia nada y lo dice.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo desde la terminal.\n"); }

$raiz = dirname(__DIR__);
$cfgRuta = $raiz . '/config/config.php';
if (!is_file($cfgRuta)) exit("\n  Falta config/config.php\n\n");

$cfg = require $cfgRuta;
$bd  = $cfg['bd'] ?? [];
$principal = (string)($bd['principal'] ?? '');
if ($principal === '') exit("\n  Falta «principal» en config/config.php → bd.\n\n");

echo "\n  Base principal: {$principal}\n\n";

try {
    $puerto = !empty($bd['puerto']) ? ';port=' . (int)$bd['puerto'] : '';
    $db = new PDO("mysql:host={$bd['host']}{$puerto};dbname={$principal};charset=utf8mb4",
        $bd['usuario'], (string)($bd['clave'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) {
    exit("  No conecta: " . $e->getMessage() . "\n\n");
}

/**
 * Las que este script crea.
 *
 * Solo van aquí las que NO se crean solas. Duplicar aquí una tabla que
 * ya se autocrea es pedir que las dos definiciones se separen con el
 * tiempo, y que la forma dependa de quién llegó primero.
 */
$tablas = [

// Las columnas son las que pide el código, ni una más: EmpresaRepo,
// CrearEmpresa, Suscripcion y AutenticacionRepo.
'empresas' => "
    CREATE TABLE IF NOT EXISTS empresas (
        id                INT AUTO_INCREMENT PRIMARY KEY,
        nombre_empresa    VARCHAR(200) NOT NULL,
        giro_comercial    VARCHAR(200) NULL,
        rfc               VARCHAR(20)  NULL,
        no_distribuidor   VARCHAR(40)  NULL,
        telefono          VARCHAR(40)  NULL,
        direccion         VARCHAR(255) NULL,
        nombre_contacto   VARCHAR(160) NULL,
        email_admin       VARCHAR(190) NULL,
        usuario_admin     VARCHAR(60)  NULL,
        nombre_base_datos VARCHAR(64)  NOT NULL,
        plan              VARCHAR(30)  NOT NULL DEFAULT 'prueba',
        fecha_vencimiento DATE NULL,
        activo            TINYINT(1) NOT NULL DEFAULT 1,
        creado_en         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY ix_emp_base (nombre_base_datos),
        KEY ix_emp_activo (activo)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
];

$creadas = 0;
foreach ($tablas as $nombre => $sql) {
    $st = $db->prepare("SELECT COUNT(*) FROM information_schema.TABLES
                         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?");
    $st->execute([$principal, $nombre]);
    $existia = (int)$st->fetchColumn() > 0;

    $db->exec($sql);

    if ($existia) {
        echo "  · $nombre — ya estaba, sin tocar\n";
    } else {
        echo "  ✓ $nombre — creada\n";
        $creadas++;
    }
}

echo "\n  " . ($creadas ? "$creadas tabla(s) creada(s)." : 'No hacía falta crear nada.') . "\n";
echo "  Comprueba con: php bin/diagnostico.php\n\n";
