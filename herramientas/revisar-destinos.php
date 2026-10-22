<?php
/**
 * ¿Cada ruta apunta a algo que existe?
 *
 * revisar-rutas.php comprueba que toda ruta lleve permiso. No comprueba
 * que la clase destino exista: si se borra un controlador y su ruta se
 * queda, el síntoma aparece en producción como un error fatal al entrar
 * a esa URL, no al revisar.
 *
 * Esto hizo falta al separar LibertyFin CRM de LibertyFin POS: al quitar
 * Caja, Corte, Inventario, Compras y Recargas, sus rutas quedaron vivas
 * apuntando a clases que ya no estaban.
 */
$raiz = dirname(__DIR__);
$indice = $raiz . '/public/index.php';
$src = $raiz . '/src';

// Dónde vive cada clase: se recorre src/ una vez y se arma el índice.
$clases = [];
$it = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    $txt = file_get_contents($f->getPathname());
    if (preg_match('/namespace\s+([^;]+);/', $txt, $ns)
        && preg_match('/(?:final\s+)?class\s+(\w+)/', $txt, $cl)) {
        $clases[trim($ns[1]) . '\\' . $cl[1]] = $f->getPathname();
    }
}

$texto = file_get_contents($indice);
preg_match_all(
    "/->(?:get|post|delete)\(\s*'([^']+)'\s*,\s*\['([^']+)'\s*,\s*'(\w+)'\]/",
    $texto, $m, PREG_SET_ORDER);

$rotas = [];
foreach ($m as $r) {
    list(, $ruta, $clase, $metodo) = $r;
    $clase = str_replace('\\\\', '\\', $clase);

    if (!isset($clases[$clase])) {
        $rotas[] = sprintf('  %-34s  no existe la clase %s', $ruta, $clase);
        continue;
    }
    // El método: se busca en el texto del archivo, sin cargar la clase
    // (cargarla ejecutaría su namespace y sus dependencias).
    $cuerpo = file_get_contents($clases[$clase]);
    if (!preg_match('/function\s+' . preg_quote($metodo, '/') . '\s*\(/', $cuerpo)) {
        $rotas[] = sprintf('  %-34s  %s no tiene %s()',
                           $ruta, basename($clases[$clase]), $metodo);
    }
}

echo "\n";
if ($rotas) {
    echo "  destinos rotos:\n" . implode("\n", $rotas) . "\n\n";
    echo '  ' . count($rotas) . " de " . count($m) . " rutas apuntan a algo que no existe\n\n";
    exit(1);
}
echo '  destinos: las ' . count($m) . " rutas apuntan a clases y métodos que existen\n\n";
