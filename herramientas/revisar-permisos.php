<?php
/**
 * ¿Cada permiso que se pide existe en la matriz?
 *
 * `Permisos::puede()` NIEGA lo que no conoce, a propósito: escribir mal
 * un permiso debe cerrar la puerta, no abrirla. El efecto secundario es
 * que un permiso renombrado no da error: simplemente deja fuera a todo
 * el mundo, en silencio, y el síntoma aparece cuando un cliente llama
 * diciendo que no puede entrar a una sección.
 *
 * Pasó al separar el CRM del POS: `ver.ventas` pasó a llamarse
 * `ver.contratos` en la matriz, y ocho lugares siguieron pidiendo el
 * viejo. Nada falló al revisar; la sección simplemente se habría quedado
 * vacía para todos.
 */
$raiz = dirname(__DIR__);

// Los que existen, leídos de la matriz sin cargar la clase.
$txt = file_get_contents($raiz . '/src/Dominio/Permisos.php');
$ini = strpos($txt, 'const MATRIZ');
$fin = strpos($txt, '];', $ini);
preg_match_all("/'([a-z]+\.[a-z.]+|[a-z]+)'\s*=>\s*\[/", substr($txt, $ini, $fin - $ini), $m);
$existen = array_flip($m[1]);

// Los que se piden, por todo el código.
$pedidos = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($raiz . '/src', FilesystemIterator::SKIP_DOTS));
$archivos = [];
foreach ($it as $f) if ($f->getExtension() === 'php') $archivos[] = $f->getPathname();
$archivos[] = $raiz . '/public/index.php';

foreach ($archivos as $a) {
    if (strpos($a, 'Dominio/Permisos.php') !== false) continue;
    $t = file_get_contents($a);
    // Permisos::puede('x'), 'p' => 'x', y la columna de permiso del menú.
    preg_match_all("/puede\(\s*'([a-z][a-z.]*)'/", $t, $p1);
    preg_match_all("/'p'\s*=>\s*'([a-z][a-z.]*)'/", $t, $p2);
    preg_match_all("/=>\s*'((?:ver|editar|borrar|crear|cancelar|marcar|asignar|quitar|abrir|cerrar|importar)\.[a-z.]+)'/", $t, $p3);
    foreach (array_merge($p1[1], $p2[1], $p3[1]) as $perm) {
        $pedidos[$perm][] = str_replace($raiz . '/', '', $a);
    }
}

$malos = [];
foreach ($pedidos as $perm => $donde) {
    if (!isset($existen[$perm])) {
        $malos[] = sprintf("  %-24s  %s", $perm, implode(', ', array_unique($donde)));
    }
}

echo "\n";
if ($malos) {
    echo "  permisos que no existen en la matriz:\n" . implode("\n", $malos) . "\n\n";
    echo '  ' . count($malos) . " sin definir — quien los pida quedará fuera, en silencio\n\n";
    exit(1);
}
echo '  permisos: los ' . count($pedidos) . " que se piden existen en la matriz\n\n";
