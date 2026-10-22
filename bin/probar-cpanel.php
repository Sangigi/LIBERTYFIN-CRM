<?php
/**
 * ¿Sirven las credenciales de cPanel?
 *
 *     php bin/probar-cpanel.php
 *
 * Hace la llamada MÁS INOFENSIVA que existe en esa API —listar las bases
 * que ya hay— y enseña tal cual lo que contesta el servidor. No crea
 * nada, no borra nada.
 *
 * POR QUÉ APARTE DEL SISTEMA
 *
 * Probar el token aprobando una empresa mezcla dos cosas: si falla, no
 * se sabe si el problema es la credencial o el alta. Aquí solo se prueba
 * la credencial, y el resultado no deja dudas.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit("Solo desde la terminal.\n"); }

$raiz = dirname(__DIR__);
$ruta = $raiz . '/config/integraciones.php';
if (!is_file($ruta)) {
    exit("\n  Falta config/integraciones.php\n"
       . "  Copia config/integraciones.php.ejemplo y llena la sección «cpanel».\n\n");
}

$cfg = require $ruta;
$cp  = $cfg['cpanel'] ?? [];

foreach (['host', 'usuario', 'token'] as $k) {
    if (trim((string)($cp[$k] ?? '')) === '') {
        exit("\n  Falta «$k» en la sección cpanel de config/integraciones.php\n\n");
    }
}

$host = $cp['host'];
$user = $cp['usuario'];

echo "\n  Host:    https://$host:2083\n";
echo "  Usuario: $user\n";
// El token NO se imprime. Solo su longitud, que basta para ver si se
// pegó cortado o con espacios de sobra.
echo "  Token:   " . strlen((string)$cp['token']) . " caracteres\n\n";

$url = "https://$host:2083/execute/Mysql/list_databases";
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => ['Authorization: cpanel ' . $user . ':' . $cp['token']],
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
]);
$r    = curl_exec($ch);
$err  = curl_error($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($r === false) {
    echo "  ✗ No se pudo conectar: $err\n\n";
    echo "  Lo más común:\n";
    echo "    · El puerto 2083 está cerrado para salidas desde este servidor.\n";
    echo "    · El host está mal escrito (va el dominio del cPanel, sin https://).\n";
    echo "    · El certificado no valida. Pruébalo a mano:\n";
    echo "        curl -I https://$host:2083\n\n";
    exit(1);
}

echo "  HTTP $http\n\n";

$j = json_decode($r, true);

if ($j === null) {
    echo "  ✗ La respuesta no es JSON. Casi siempre es el token.\n\n";
    echo "  Lo que llegó (primeros 400 caracteres):\n";
    echo "  " . trim(preg_replace('/\s+/', ' ', mb_substr((string)$r, 0, 400))) . "\n\n";
    echo "  Si ves HTML de inicio de sesión, el token no vale o no es de\n";
    echo "  ese usuario. Genera uno nuevo en cPanel → Manage API Tokens.\n\n";
    exit(1);
}

if (empty($j['status'])) {
    echo "  ✗ cPanel rechazó la llamada.\n\n";
    if (!empty($j['errors'])) foreach ($j['errors'] as $e) echo "    · $e\n";
    else echo "    " . json_encode($j, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
    echo "\n  Si dice algo de permisos, al token le falta el de bases de\n";
    echo "  datos. En cPanel se edita el token y se le marca.\n\n";
    exit(1);
}

echo "  ✓ Las credenciales sirven.\n\n";

$bases = $j['data'] ?? [];
echo "  Bases que ve ese usuario: " . count($bases) . "\n";
foreach (array_slice($bases, 0, 12) as $b) {
    echo "    · " . (is_array($b) ? ($b['database'] ?? '?') : $b) . "\n";
}
if (count($bases) > 12) echo "    … y " . (count($bases) - 12) . " más\n";

// El prefijo importa: cPanel se lo pone solo a todo lo que cree, y si el
// usuario del token no es el dueño de la cuenta, el nombre no coincide
// con el que el sistema espera y la empresa nace apuntando a una base
// que no existe.
echo "\n  Las bases nuevas se llamarán: {$user}_<loquesea>\n";
echo "  Comprueba que ese prefijo coincida con el de la lista de arriba.\n";
echo "  Si no coincide, el token es de otro usuario de cPanel.\n\n";
