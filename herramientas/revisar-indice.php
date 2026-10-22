<?php
/**
 * ¿El front controller usa algo antes de tenerlo, o lo registra dos veces?
 *
 * ESTO NACIÓ DE UN FALLO REAL
 *
 * Al separar el CRM del POS se quitaron líneas por nombre de clase. Una
 * de ellas era la asignación `$pdt = '...PagosEntrantesControlador'`, y
 * al reponer el bloque quedó DUPLICADO: la copia huérfana —sin la
 * asignación— iba primero, así que las 22 rutas que usaban $pdt
 * disparaban «Undefined variable» en cada carga de página, incluida la
 * de login.
 *
 * Ninguno de los otros verificadores lo vio: la sintaxis era válida, las
 * rutas tenían permiso y las clases existían. El fallo era de ORDEN y de
 * REPETICIÓN, que es justo lo que ningún linter mira.
 *
 * Comprueba dos cosas sobre public/index.php:
 *   1. Toda variable usada en un registro de ruta se asignó ANTES.
 *   2. Ninguna ruta se registra dos veces.
 */
$archivo = dirname(__DIR__) . '/public/index.php';
$lineas = file($archivo);

$asignadas = [];
$malas = [];
$rutas = [];
$repetidas = [];
$camino = [];     // en qué bloques estamos ahora mismo
$bloque = 0;      // cada bloque que se abre recibe un número propio

foreach ($lineas as $n => $linea) {
    $num = $n + 1;

    // Asignaciones: $x = ... , foreach (… as $x), function ($x)
    if (preg_match_all('/\$([A-Za-z_]\w*)\s*=(?!=)/', $linea, $m)) {
        foreach ($m[1] as $v) if (!isset($asignadas[$v])) $asignadas[$v] = $num;
    }
    if (preg_match_all('/as\s+\$([A-Za-z_]\w*)/', $linea, $m)) {
        foreach ($m[1] as $v) if (!isset($asignadas[$v])) $asignadas[$v] = $num;
    }
    if (preg_match_all('/function\s*\([^)]*\$([A-Za-z_]\w*)/', $linea, $m)) {
        foreach ($m[1] as $v) if (!isset($asignadas[$v])) $asignadas[$v] = $num;
    }

    // Usos dentro de un registro de ruta: [$x, 'metodo']
    if (preg_match_all('/\[\s*\$([A-Za-z_]\w*)\s*,/', $linea, $m)) {
        foreach ($m[1] as $v) {
            if (!isset($asignadas[$v]) || $asignadas[$v] > $num) {
                $malas[] = sprintf('  línea %-4d  $%s se usa sin estar asignada todavía', $num, $v);
            }
        }
    }

    // Rutas repetidas: mismo verbo, mismo camino Y mismo bloque.
    //
    // Lo del bloque importa: `/panel` se registra dos veces a propósito,
    // una por rama de un if/else según el nivel del rol. Son
    // alternativas, no repeticiones: solo corre una. Por eso se compara
    // el CAMINO de bloques y no solo la ruta.
    if (preg_match('/\$r->(get|post|delete)\s*\(\s*[\'"]([^\'"]+)/', $linea, $m)) {
        $clave = strtoupper($m[1]) . ' ' . $m[2] . ' @ ' . implode('.', $camino);
        if (isset($rutas[$clave])) {
            $repetidas[] = sprintf('  %-46s  líneas %d y %d',
                                   strtoupper($m[1]) . ' ' . $m[2], $rutas[$clave], $num);
        } else {
            $rutas[$clave] = $num;
        }
    }

    // El camino de bloques. Cada `{` abre uno nuevo; `} else {` cierra el
    // anterior y abre otro, que es lo que separa las dos ramas.
    $abre   = substr_count($linea, '{');
    $cierra = substr_count($linea, '}');
    if (preg_match('/}\s*else/', $linea)) { array_pop($camino); $camino[] = ++$bloque; $abre--; $cierra--; }
    for ($i = 0; $i < $cierra; $i++) array_pop($camino);
    for ($i = 0; $i < $abre; $i++)   $camino[] = ++$bloque;
}

echo "\n";
if ($malas || $repetidas) {
    if ($malas) {
        echo "  variables usadas antes de asignarse:\n" . implode("\n", $malas) . "\n\n";
    }
    if ($repetidas) {
        echo "  rutas registradas dos veces:\n" . implode("\n", $repetidas) . "\n\n";
    }
    echo '  ' . (count($malas) + count($repetidas)) . " problemas en public/index.php\n\n";
    exit(1);
}
printf("  índice: %d rutas únicas, ninguna variable usada antes de asignarse\n\n", count($rutas));
