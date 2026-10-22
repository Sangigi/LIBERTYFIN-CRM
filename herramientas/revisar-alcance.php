<?php
/**
 * ¿Las consultas con Alcance siguen cuadrando, y de verdad filtran?
 *
 * DOS COSAS QUE SE ROMPEN EN SILENCIO
 *
 * 1. Datos\Repo cuenta los `?` y los compara con los parámetros. Añadir
 *    el filtro del alcance y olvidar su valor revienta, pero solo al
 *    ejecutarse: si esa pantalla no se abre en la prueba manual, el
 *    error sale en producción.
 *
 * 2. Peor: olvidar el filtro y NO olvidar nada más. Entonces todo
 *    funciona, no falla ninguna prueba, y simplemente cada quien ve los
 *    clientes de todos. Un fallo de privacidad no avisa.
 *
 * Esto llama a cada método con un PDO falso que recuerda el SQL y los
 * parámetros, y comprueba las dos cosas: que cuadren, y que cuando el
 * usuario está limitado el SQL LLEVE el filtro.
 *
 * No toca ninguna base de datos.
 */
$raiz = dirname(__DIR__);
require $raiz . '/src/Dominio/Permisos.php';
require $raiz . '/src/Dominio/Alcance.php';
require $raiz . '/src/Datos/Repo.php';

use LibertyFin\Dominio\Alcance;

/** Recuerda lo que se le pide; no ejecuta nada. */
class SentenciaFalsa extends PDOStatement
{
    public $sql;
    public static $ultimas = [];
    public function __construct($sql) { $this->sql = $sql; }
    #[\ReturnTypeWillChange]
    public function execute($params = null)
    {
        self::$ultimas[] = ['sql' => $this->sql, 'params' => $params ?: []];
        return true;
    }
    #[\ReturnTypeWillChange]
    public function fetchAll($mode = null, ...$a) { return []; }
    #[\ReturnTypeWillChange]
    public function fetch($mode = null, ...$a)    { return false; }
    #[\ReturnTypeWillChange]
    public function fetchColumn($n = 0)           { return 0; }
}

class PdoFalso extends PDO
{
    public function __construct() {}
    #[\ReturnTypeWillChange]
    public function prepare($sql, $opciones = []) { return new SentenciaFalsa($sql); }
    #[\ReturnTypeWillChange]
    public function query($sql, ...$a)            { return new SentenciaFalsa($sql); }
}

/** Corre un método y devuelve lo que se preparó. */
function corre(callable $fn)
{
    SentenciaFalsa::$ultimas = [];
    try { $fn(); }
    catch (\LogicException $e) { return ['error' => $e->getMessage()]; }
    catch (\Throwable $e)      { return ['error' => get_class($e) . ': ' . $e->getMessage()]; }
    return SentenciaFalsa::$ultimas;
}

$fallas = [];
$corridas = 0;

/**
 * Comprueba un método: que cuadren los marcadores, y que el filtro
 * aparezca cuando el usuario está limitado.
 */
function revisa($etiqueta, callable $fabrica, $metodo, array $args, $debeFiltrar = true)
{
    global $fallas, $corridas;
    $corridas++;

    // a) Limitado: tiene que cuadrar Y tiene que filtrar.
    $r = corre(function () use ($fabrica, $metodo, $args) {
        call_user_func_array([$fabrica(Alcance::de(7)), $metodo], $args);
    });
    if (isset($r['error'])) {
        $fallas[] = "  $etiqueta  limitado: " . $r['error'];
        return;
    }
    if ($debeFiltrar) {
        $filtra = false;
        foreach ($r as $c) {
            if (strpos($c['sql'], 'responsable_id') !== false && in_array(7, $c['params'], true)) {
                $filtra = true; break;
            }
        }
        if (!$filtra) $fallas[] = "  $etiqueta  NO FILTRA: ve los datos de todos";
    }

    // b) Sin límite: tiene que cuadrar igual, sin el filtro.
    $r2 = corre(function () use ($fabrica, $metodo, $args) {
        call_user_func_array([$fabrica(Alcance::sinLimite()), $metodo], $args);
    });
    if (isset($r2['error'])) $fallas[] = "  $etiqueta  sin límite: " . $r2['error'];
}

// ── Los repos que ya llevan alcance ──
require $raiz . '/src/Datos/ClienteRepo.php';

$db = new PdoFalso();
$cliente = function ($a) use ($db) { return new LibertyFin\Datos\ClienteRepo($db, $a); };

revisa('ClienteRepo::listado', $cliente, 'listado', ['2026-01-01', '2026-12-31', '', 25, 0]);
revisa('ClienteRepo::listado+buscar', $cliente, 'listado', ['2026-01-01', '2026-12-31', 'vega', 25, 0]);
revisa('ClienteRepo::cuantos', $cliente, 'cuantos', ['2026-01-01', '2026-12-31', '']);
revisa('ClienteRepo::cuantos+buscar', $cliente, 'cuantos', ['2026-01-01', '2026-12-31', 'vega']);
revisa('ClienteRepo::uno_', $cliente, 'uno_', [418]);
revisa('ClienteRepo::masFacturan', $cliente, 'masFacturan', ['2026-01-01', '2026-12-31', 5]);

require $raiz . '/src/Datos/VentaRepo.php';
$venta = function ($a) use ($db) { return new LibertyFin\Datos\VentaRepo($db, $a); };

revisa('VentaRepo::listado', $venta, 'listado', ['2026-01-01', '2026-12-31', [], 25, 0]);
revisa('VentaRepo::listado+filtros', $venta, 'listado',
       ['2026-01-01', '2026-12-31', ['estado' => 'pagada', 'buscar' => 'vega'], 25, 0]);
revisa('VentaRepo::cuantas', $venta, 'cuantas', ['2026-01-01', '2026-12-31', []]);
revisa('VentaRepo::cuantas+filtros', $venta, 'cuantas',
       ['2026-01-01', '2026-12-31', ['estado' => 'pagada', 'buscar' => 'vega']]);
revisa('VentaRepo::detalle', $venta, 'detalle', [91]);
revisa('VentaRepo::saldosAbiertos', $venta, 'saldosAbiertos', [5]);
revisa('VentaRepo::conSaldo', $venta, 'conSaldo', [30]);

// Los servicios: no heredan de Repo, preparan a mano, pero usan los
// mismos marcadores posicionales.
require $raiz . '/src/Servicio/Cotizaciones.php';
require $raiz . '/src/Servicio/Agenda.php';
require $raiz . '/src/Servicio/Trabajos.php';

$cot = function ($a) use ($db) { return new LibertyFin\Servicio\Cotizaciones($db, $a); };
revisa('Cotizaciones::listar',  $cot, 'listar', [[]]);
revisa('Cotizaciones::listar+f', $cot, 'listar', [['estado' => 'vencida', 'q' => 'vega']]);
revisa('Cotizaciones::una',     $cot, 'una', [12]);
revisa('Cotizaciones::resumen', $cot, 'resumen', []);

$ag = function ($a) use ($db) { return new LibertyFin\Servicio\Agenda($db, $a); };
revisa('Agenda::dia',           $ag, 'dia', ['2026-10-22']);
revisa('Agenda::cuantasPorDia', $ag, 'cuantasPorDia', ['2026-10-01', '2026-10-31']);
revisa('Agenda::una',           $ag, 'una', [5]);

$tr = function ($a) use ($db) { return new LibertyFin\Servicio\Trabajos($db, $a); };
revisa('Trabajos::tablero',     $tr, 'tablero', [[]]);
revisa('Trabajos::tablero+f',   $tr, 'tablero', [['responsable' => 3, 'q' => 'vega']]);

// Las verticales.
require $raiz . '/src/Datos/AsuntoRepo.php';
require $raiz . '/src/Datos/PolizaRepo.php';

$as = function ($a) use ($db) { return new LibertyFin\Datos\AsuntoRepo($db, $a); };
revisa('AsuntoRepo::tablero',   $as, 'tablero', [[]]);
revisa('AsuntoRepo::tablero+f', $as, 'tablero', [['materia' => 'mercantil', 'q' => 'vega']]);
revisa('AsuntoRepo::ver',       $as, 'ver', [7]);
revisa('AsuntoRepo::proximos',  $as, 'proximos', [1]);

$po = function ($a) use ($db) { return new LibertyFin\Datos\PolizaRepo($db, $a); };
revisa('PolizaRepo::listado',   $po, 'listado', [[]]);
revisa('PolizaRepo::listado+f', $po, 'listado', [['ramo' => 'autos', 'estado' => 'vigente', 'q' => 'x']]);
revisa('PolizaRepo::ver',       $po, 'ver', [3]);
revisa('PolizaRepo::porVencer', $po, 'porVencer', [30]);
revisa('PolizaRepo::resumen',   $po, 'resumen', []);

echo "\n";
if ($fallas) {
    echo "  problemas:\n" . implode("\n", $fallas) . "\n\n";
    echo '  ' . count($fallas) . " de $corridas comprobaciones fallaron\n\n";
    exit(1);
}
echo "  alcance: las $corridas consultas cuadran y filtran\n\n";
