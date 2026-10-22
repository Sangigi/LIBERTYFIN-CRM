<?php
/**
 * El primer acceso.
 *
 * EL PROBLEMA QUE RESUELVE
 *
 * Un sistema recién subido no deja entrar a nadie: /registro guarda una
 * SOLICITUD, y las solicitudes las aprueba un superadmin desde
 * Mantenimiento. Pero no hay superadmin todavía. Sin esto, la única
 * salida es meterse a phpMyAdmin a escribir un hash a mano.
 *
 * Qué hace:
 *   1. Comprueba que config/config.php exista y conecte.
 *   2. Crea las tablas de la base principal si faltan.
 *   3. Crea (o repone la contraseña de) un usuario superadmin.
 *
 * Cómo se usa, desde la carpeta `app`:
 *
 *     php bin/instalar.php tu@correo.mx "UnaClaveLarga"
 *
 * Es IDEMPOTENTE: correrlo dos veces no duplica nada. Si el usuario ya
 * existe, le repone la contraseña, que es justo lo que hace falta
 * cuando alguien la pierde.
 *
 * BÓRRALO, O AL MENOS SÁCALO DE public/, cuando termines. Vive en bin/
 * —fuera de public/— precisamente para que no sea alcanzable por web,
 * pero un archivo que crea superadmins no debe quedarse rondando.
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este instalador solo corre desde la línea de comandos.\n");
}

$raiz = dirname(__DIR__);

$correo = $argv[1] ?? '';
$clave  = $argv[2] ?? '';

if ($correo === '' || $clave === '') {
    exit("\n  Uso: php bin/instalar.php correo@dominio.mx \"ClaveLarga\"\n\n");
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    exit("\n  Ese correo no es válido.\n\n");
}
// Doce caracteres, no ocho: esta cuenta puede entrar a los datos de
// todas las empresas del sistema.
if (strlen($clave) < 12) {
    exit("\n  La clave debe tener al menos 12 caracteres: esta cuenta ve todas las empresas.\n\n");
}

$archivoCfg = $raiz . '/config/config.php';
if (!is_file($archivoCfg)) {
    exit("\n  Falta config/config.php.\n"
       . "  Copia config/config.php.ejemplo, llénalo y vuelve a correr esto.\n\n");
}
$cfg = require $archivoCfg;
$bd  = $cfg['bd'] ?? [];
foreach (['host', 'usuario', 'principal'] as $k) {
    if (trim((string)($bd[$k] ?? '')) === '') exit("\n  Falta «$k» en config/config.php → bd.\n\n");
}

echo "\n  Conectando a {$bd['principal']} en {$bd['host']}…\n";

try {
    $puerto = !empty($bd['puerto']) ? ';port=' . (int)$bd['puerto'] : '';
    $db = new PDO(
        "mysql:host={$bd['host']}{$puerto};dbname={$bd['principal']};charset=utf8mb4",
        $bd['usuario'], (string)($bd['clave'] ?? ''),
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
         PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
} catch (PDOException $e) {
    exit("\n  No se pudo conectar: " . $e->getMessage() . "\n\n");
}

echo "  Conectado.\n";

// ── Las tablas de la base principal ──
//
// Solo las imprescindibles para entrar. El resto las crea Migraciones
// cuando se aprueba la primera empresa.
$db->exec("
    CREATE TABLE IF NOT EXISTS usuarios (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        nombre    VARCHAR(120) NOT NULL,
        email     VARCHAR(190) NOT NULL,
        clave     VARCHAR(255) NOT NULL,
        rol       VARCHAR(20)  NOT NULL DEFAULT 'agente',
        empresa_id INT NULL,
        activo    TINYINT(1) NOT NULL DEFAULT 1,
        creado_en DATETIME NOT NULL,
        UNIQUE KEY ux_usuario_email (email)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$db->exec("
    CREATE TABLE IF NOT EXISTS empresas (
        id        INT AUTO_INCREMENT PRIMARY KEY,
        nombre    VARCHAR(160) NOT NULL,
        bd        VARCHAR(64)  NOT NULL,
        plan      VARCHAR(30)  NOT NULL DEFAULT 'esencial',
        estado    VARCHAR(20)  NOT NULL DEFAULT 'activa',
        creado_en DATETIME NOT NULL,
        UNIQUE KEY ux_empresa_bd (bd)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

echo "  Tablas de plataforma listas.\n";

// ── El superadmin ──
$hash = password_hash($clave, PASSWORD_DEFAULT);

$st = $db->prepare("SELECT id FROM usuarios WHERE email = ?");
$st->execute([$correo]);
$id = $st->fetchColumn();

if ($id) {
    $db->prepare("UPDATE usuarios SET clave = ?, rol = 'superadmin', activo = 1 WHERE id = ?")
       ->execute([$hash, $id]);
    echo "\n  Usuario ya existía: se repuso su contraseña y se dejó como superadmin.\n";
} else {
    $db->prepare("INSERT INTO usuarios (nombre, email, clave, rol, activo, creado_en)
                  VALUES (?,?,?,'superadmin',1,NOW())")
       ->execute(['Administrador', $correo, $hash]);
    echo "\n  Superadmin creado.\n";
}

echo "\n  Entra en /login con:\n";
echo "    correo: $correo\n";
echo "    clave:  la que acabas de poner\n";
echo "\n  Desde ahí, en Mantenimiento puedes aprobar las empresas que se\n";
echo "  registren en /registro.\n";
echo "\n  Cuando termines, BORRA bin/instalar.php.\n\n";
