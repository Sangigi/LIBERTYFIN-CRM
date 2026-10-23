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

// ════════════════════════════════════════════════════════
//  ¿LOS MÉTODOS QUE SE LLAMAN EXISTEN?
// ════════════════════════════════════════════════════════
//
// Un controlador que llama a `$repo->fijarPaso()` cuando ese método no
// está no falla al revisar: la sintaxis es válida, la clase existe, la
// ruta lleva permiso. Falla al pulsar el botón, con un error fatal.
//
// Pasó exactamente así al construir el panel lateral del embudo: la
// edición que añadía el método no se aplicó, la que añadía la ruta sí, y
// los cinco verificadores dijeron que todo estaba bien.
//
// Esto rastrea las variables que salen de un `new Clase(...)` dentro de
// cada archivo y comprueba que lo que se les pide exista. No ve las que
// llegan por parámetro ni por propiedad: la cobertura no es total, pero
// cubre el patrón que usa todo este código.

$metodosDe = [];   // clase corta => [métodos]
foreach ($clases as $fqn => $archivo) {
    $corto = substr($fqn, strrpos($fqn, '\\') + 1);
    preg_match_all('/function\s+(\w+)\s*\(/', file_get_contents($archivo), $m);
    $metodosDe[$corto] = $m[1];
}

$rotos = [];
foreach ($archivos ?? [] as $a) {}          // placeholder: se arma abajo

$it2 = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($src, FilesystemIterator::SKIP_DOTS));
foreach ($it2 as $f) {
    if ($f->getExtension() !== 'php') continue;
    $txt = file_get_contents($f->getPathname());

    // UNA FUNCIÓN A LA VEZ.
    //
    // La misma variable es cosas distintas en métodos distintos: en
    // Puerta.php, `$r` es un Router en uno y una respuesta en otro.
    // Mirar el archivo entero de golpe los confunde. Partir por
    // `function` no es un analizador de verdad, pero acierta en el
    // código que tenemos, y de más no acusa.
    $trozos = preg_split('/(?=\n\s*(?:public|private|protected|static|function)\s)/', $txt);

    foreach ($trozos as $trozo) {
        preg_match_all('/\$(\w+)\s*=\s*new\s+\\\\?([\w\\\\]+)\s*\(/', $trozo, $m, PREG_SET_ORDER);
        $tipoDe = []; $ambiguas = [];
        foreach ($m as $x) {
            $c = $x[2];
            $c = substr($c, strrpos($c, '\\') === false ? 0 : strrpos($c, '\\') + 1);
            if (!isset($metodosDe[$c])) continue;
            if (isset($tipoDe[$x[1]]) && $tipoDe[$x[1]] !== $c) $ambiguas[$x[1]] = true;
            $tipoDe[$x[1]] = $c;
        }
        foreach (array_keys($ambiguas) as $v) unset($tipoDe[$v]);
        if (!$tipoDe) continue;

        preg_match_all('/\$(\w+)->(\w+)\s*\(/', $trozo, $m2, PREG_SET_ORDER);
        foreach ($m2 as $x) {
            list(, $var, $metodo) = $x;
            if (!isset($tipoDe[$var])) continue;
            $clase = $tipoDe[$var];
            if (!in_array($metodo, $metodosDe[$clase], true)) {
                $rotos[] = sprintf('  %-40s  $%s->%s() — %s no lo tiene',
                    str_replace($raiz . '/', '', $f->getPathname()), $var, $metodo, $clase);
            }
        }
    }
}

echo "\n";
if ($rotos) {
    echo "  métodos que no existen:\n" . implode("\n", array_unique($rotos)) . "\n\n";
    exit(1);
}
echo "  métodos: los que se llaman sobre objetos recién creados existen\n\n";
