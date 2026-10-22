<?php
/**
 * ¿Las piezas sueltas siguen encajando entre sí?
 *
 * Cada catálogo de este sistema funciona solo. El problema aparece
 * cuando dejan de cuadrar ENTRE ellos, y eso no revienta nunca: un plan
 * que incluye un módulo borrado simplemente no enciende nada, y una
 * plataforma sin origen propio mete el lead como «captura manual».
 *
 * Son fallos de dato, no de código. Por eso hacen falta estas
 * comprobaciones: nada aquí lo detecta un linter.
 */
$raiz = dirname(__DIR__);
foreach (['Dominio/Permisos', 'Dominio/Alcance', 'Servicio/Modulos',
          'Servicio/Integraciones', 'Servicio/Entrada', 'Servicio/Arranque',
          'Datos/ProspectoRepo', 'Datos/AsuntoRepo', 'Datos/PolizaRepo'] as $c) {
    require_once $raiz . '/src/' . $c . '.php';
}

use LibertyFin\Dominio\Permisos;
use LibertyFin\Servicio\Modulos;
use LibertyFin\Servicio\Integraciones;
use LibertyFin\Servicio\Entrada;
use LibertyFin\Datos\ProspectoRepo;
use LibertyFin\Datos\AsuntoRepo;
use LibertyFin\Servicio\Arranque;

$mal = [];

// 1. Todo rol citado en la matriz tiene que existir.
foreach (Permisos::MATRIZ as $p => $roles) {
    foreach ($roles as $r) {
        if (!isset(Permisos::ROLES[$r])) $mal[] = "permiso «$p» cita el rol «$r», que no existe";
    }
}

// 2. Los planes no pueden vender módulos que no están.
$planes = require $raiz . '/config/planes.php';
$cat = array_keys(Modulos::CATALOGO);
foreach ($planes['planes'] as $k => $pl) {
    foreach (($pl['modulos'] ?? []) as $m) {
        if (!in_array($m, $cat, true)) $mal[] = "el plan «$k» incluye el módulo «$m», que no existe";
    }
}

// 3. Un módulo no puede depender de uno que no está.
foreach (Modulos::CATALOGO as $k => $d) {
    $q = $d['requiere'] ?? null;
    if ($q && !isset(Modulos::CATALOGO[$q])) $mal[] = "el módulo «$k» requiere «$q», que no existe";
}
foreach (Modulos::EN_TODO_PLAN as $m) {
    if (!in_array($m, $cat, true)) $mal[] = "EN_TODO_PLAN cita «$m», que no existe";
}

// 4. Alcance no sirve de nada sin este permiso.
if (!isset(Permisos::MATRIZ['ver.todo'])) {
    $mal[] = 'falta el permiso «ver.todo»: sin él, Alcance limitaría hasta al dueño';
}

// 5. Cada plataforma de captación tiene que tener su propio origen, o
//    el lead entra como «captura manual» y se pierde de dónde vino.
foreach (Entrada::PLATAFORMAS as $p) {
    $o = $p === 'google_ads' ? 'google' : $p;
    if (!isset(ProspectoRepo::ORIGENES[$o])) {
        $mal[] = "la plataforma «$p» no tiene origen propio: entraría como «manual»";
    }
}

// 6. Cada materia necesita etapas y documentos, o su expediente se abre vacío.
foreach (AsuntoRepo::MATERIAS as $m => $_) {
    if (!isset(AsuntoRepo::ETAPAS[$m]))     $mal[] = "la materia «$m» no tiene etapas procesales";
    if (!isset(AsuntoRepo::DOCUMENTOS[$m])) $mal[] = "la materia «$m» no tiene lista de documentos";
}
foreach (AsuntoRepo::ETAPAS as $m => $et) {
    if (!isset(AsuntoRepo::MATERIAS[$m]))  $mal[] = "hay etapas de «$m», que no es una materia";
    if (!isset($et['sin_iniciar']))        $mal[] = "la materia «$m» no arranca en «sin_iniciar»";
}

// 7. El asistente de bienvenida.
//
// Ofrece módulos por nombre, en listas fijas. Nombrar uno que ya no
// existe NO revienta al revisar: revienta en la cara del cliente la
// primera vez que entra, que es el peor sitio posible. Pasó con
// «compras» al separar del POS.
foreach (Arranque::MODULOS as $m) {
    if (!in_array($m, $cat, true)) $mal[] = "el asistente ofrece el módulo «$m», que no existe";
}
foreach (Arranque::PERFILES as $k => $p) {
    foreach (($p['modulos'] ?? []) as $m) {
        if (!in_array($m, $cat, true)) $mal[] = "el perfil «$k» enciende «$m», que no existe";
    }
}
foreach (Arranque::DEL_REGISTRO as $giro => $perfil) {
    if (!isset(Arranque::PERFILES[$perfil])) {
        $mal[] = "el giro «$giro» apunta al perfil «$perfil», que no existe";
    }
}

// 8. Las integraciones que el código da por hechas.
foreach (['meta', 'tiktok', 'linkedin', 'google_ads', 'libertypay'] as $i) {
    if (!isset(Integraciones::CATALOGO[$i])) $mal[] = "falta la integración «$i» en el catálogo";
}

echo "\n";
if ($mal) {
    echo "  piezas que no encajan:\n";
    foreach ($mal as $m) echo "    $m\n";
    echo "\n  " . count($mal) . " incoherencias\n\n";
    exit(1);
}
printf("  coherencia: %d módulos, %d planes, %d permisos, %d roles, %d materias, %d integraciones\n\n",
    count($cat), count($planes['planes']), count(Permisos::MATRIZ),
    count(Permisos::ROLES), count(AsuntoRepo::MATERIAS), count(Integraciones::CATALOGO));
