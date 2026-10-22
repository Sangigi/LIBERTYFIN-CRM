<?php
/**
 * ¿Como quién corre PHP desde el navegador?
 *
 * POR QUÉ HACE FALTA UNA PÁGINA Y NO UN SCRIPT
 *
 * Desde la terminal, `uapi` funciona: eres el dueño de la cuenta. Desde
 * el navegador puede fallar, porque PHP corre como otro usuario y cPanel
 * deduce la cuenta del UID del proceso. El mismo comando, dos
 * resultados, y el error de cPanel no dice cuál de los dos pasó.
 *
 * Esto se abre en el navegador —el mismo contexto donde falla— y
 * responde a esa pregunta.
 *
 * NO TOCA NADA. Lee el usuario, comprueba si `shell_exec` está
 * disponible y hace UNA llamada de solo lectura.
 *
 * BÓRRALO EN CUANTO LO USES. No expone contraseñas, pero sí nombres de
 * usuario y rutas del servidor, que es información que no tiene por qué
 * estar en internet.
 */
header('Content-Type: text/plain; charset=utf-8');

$uid = function_exists('posix_geteuid') ? posix_geteuid() : null;
$nombre = null;
if ($uid !== null && function_exists('posix_getpwuid')) {
    $p = @posix_getpwuid($uid);
    $nombre = $p['name'] ?? null;
}
if (!$nombre) $nombre = get_current_user();

echo "PHP corre como\n";
echo "  usuario: " . ($nombre ?: '(no se pudo saber)') . "\n";
echo "  uid:     " . ($uid === null ? '(sin posix)' : $uid) . "\n";
echo "  sapi:    " . PHP_SAPI . "\n\n";

$deshabilitadas = array_map('trim', explode(',', (string)ini_get('disable_functions')));
$hayShell = function_exists('shell_exec') && !in_array('shell_exec', $deshabilitadas, true);

echo "shell_exec: " . ($hayShell ? 'disponible' : 'DESHABILITADO') . "\n";

$bin = null;
foreach (['/usr/local/cpanel/bin/uapi', '/usr/bin/uapi'] as $c) {
    if (is_executable($c)) { $bin = $c; break; }
}
echo "uapi:       " . ($bin ?: 'no se encuentra') . "\n\n";

if (!$hayShell || !$bin) {
    echo "Sin shell_exec o sin uapi, el alta de empresas tiene que ir por\n";
    echo "token. Genera uno en el cPanel DE ESTE dominio.\n";
    exit;
}

echo "Llamada de solo lectura (Mysql list_databases)…\n\n";
$salida = shell_exec(escapeshellcmd($bin) . ' --output=json Mysql list_databases 2>&1');
$j = json_decode((string)$salida, true);
$ok = !empty($j['result']['status']) || !empty($j['status']);

if ($ok) {
    $datos = $j['result']['data'] ?? ($j['data'] ?? []);
    echo "  OK · ve " . count($datos) . " bases\n\n";
    echo "Desde el navegador funciona igual que desde la terminal.\n";
    echo "Si el alta sigue fallando, el problema es otro: manda el error.\n";
} else {
    echo "  FALLA\n";
    echo "  " . trim(preg_replace('/\s+/', ' ', mb_substr((string)$salida, 0, 500))) . "\n\n";
    echo "Si en la terminal el mismo comando funciona, es el usuario:\n";
    echo "PHP está corriendo como «" . ($nombre ?: '?') . "» y no como el\n";
    echo "dueño de la cuenta. Dos salidas:\n\n";
    echo "  · Pasar el hosting a PHP-FPM o suPHP para que corra como tu\n";
    echo "    usuario (suele ser una opción en MultiPHP Manager).\n";
    echo "  · O usar token: genéralo en el cPanel DE ESTE dominio y ponlo\n";
    echo "    en config/integraciones.php. El sistema lo usa si uapi falla.\n";
}
echo "\n--- BORRA ESTE ARCHIVO ---\n";
