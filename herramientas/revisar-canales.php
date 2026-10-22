<?php
/**
 * ¿Los traductores sacan un lead utilizable de cada plataforma?
 *
 * Un traductor que falla no revienta: devuelve un lead a medias, sin
 * teléfono o sin nombre, y Entrada lo descarta por «sin datos de
 * contacto». Desde fuera parece que la plataforma no mandó nada, y se
 * pierde el tiempo revisando el webhook del proveedor.
 *
 * Esto pasa cargas realistas por cada traductor y comprueba que salga
 * lo mínimo para devolver la llamada: un nombre, y un teléfono o un
 * correo. No toca la base ni la red.
 */
require dirname(__DIR__) . '/src/Servicio/Traductores.php';

use LibertyFin\Servicio\Traductores as T;

$casos = [

'WhatsApp · mensaje' => [T::class . '::meta', [
    'entry' => [['changes' => [['value' => [
        'messaging_product' => 'whatsapp',
        'contacts' => [['wa_id' => '5215512345678', 'profile' => ['name' => 'Ana Vega']]],
        'messages' => [['from' => '5215512345678', 'id' => 'wamid.X1',
                        'type' => 'text', 'text' => ['body' => 'Quiero cotizar un divorcio']]],
    ]]]]],
], ['plataforma' => 'whatsapp', 'nombre' => 'Ana Vega', 'telefono' => '5215512345678']],

'Facebook · formulario' => [T::class . '::meta', [
    'entry' => [['changes' => [['field' => 'leadgen', 'value' => [
        'leadgen_id' => '990011', 'ad_name' => 'Divorcios CDMX',
        'field_data' => [
            ['name' => 'full_name', 'values' => ['Raúl Medina']],
            ['name' => 'phone_number', 'values' => ['5544332211']],
            ['name' => 'email', 'values' => ['raul@ejemplo.mx']],
        ],
    ]]]]],
], ['plataforma' => 'facebook', 'nombre' => 'Raúl Medina',
    'telefono' => '5544332211', 'correo' => 'raul@ejemplo.mx', 'campana' => 'Divorcios CDMX']],

'Instagram · mensaje' => [T::class . '::meta', [
    'entry' => [['messaging' => [[
        'message' => ['mid' => 'ig.77', 'text' => '¿Manejan temas laborales?'],
    ]]]],
], ['plataforma' => 'instagram', 'externo_id' => 'ig.77']],

'TikTok · formulario' => [T::class . '::tiktok', [
    'data' => ['list' => [[
        'lead_id' => 'tt-5521', 'ad_name' => 'Seguro de auto',
        'field_data' => [
            ['name' => 'name', 'values' => ['Lucía Ortega']],
            ['name' => 'phone', 'values' => ['5599887766']],
            ['name' => 'tipo_de_seguro', 'values' => ['Auto']],
        ],
    ]]],
], ['plataforma' => 'tiktok', 'nombre' => 'Lucía Ortega',
    'telefono' => '5599887766', 'asunto' => 'Auto', 'externo_id' => 'tt-5521']],

'LinkedIn · formulario' => [T::class . '::linkedin', [
    'elements' => [[
        'id' => 'li-9', 'firstName' => 'Gabriel', 'lastName' => 'Sosa',
        'formResponse' => ['answers' => [
            ['name' => 'emailAddress', 'values' => ['gabriel@despacho.mx']],
            ['name' => 'phoneNumber',  'values' => ['5512120000']],
        ]],
    ]],
], ['plataforma' => 'linkedin', 'nombre' => 'Gabriel Sosa',
    'correo' => 'gabriel@despacho.mx', 'telefono' => '5512120000']],

'Google Ads · formulario' => [T::class . '::googleAds', [
    'lead_id' => 'ga-301', 'campaign_id' => '77421',
    'user_column_data' => [
        ['column_id' => 'FULL_NAME',    'string_value' => 'Marisol Pérez'],
        ['column_id' => 'PHONE_NUMBER', 'string_value' => '+52 55 7070 1111'],
        ['column_id' => 'EMAIL',        'string_value' => 'marisol@ejemplo.mx'],
    ],
], ['plataforma' => 'google_ads', 'nombre' => 'Marisol Pérez',
    'correo' => 'marisol@ejemplo.mx', 'externo_id' => 'ga-301']],

// Lo que NO debe pasar: un aviso de entrega no es un lead.
'Instagram · con PSID' => [T::class . '::meta', [
    'entry' => [['messaging' => [[
        'sender'  => ['id' => '77881122'],
        'message' => ['mid' => 'ig.90', 'text' => 'Hola'],
    ]]]],
], ['plataforma' => 'instagram', 'nombre' => 'Instagram 77881122']],

'Meta · acuse (no es lead)' => [T::class . '::meta', [
    'entry' => [['messaging' => [['delivery' => ['mids' => ['m.1']]]]]],
], '__vacio__'],
];

$fallas = [];
foreach ($casos as $nombre => $caso) {
    list($fn, $carga, $espera) = $caso;
    $r = call_user_func($fn, $carga);

    if ($espera === '__vacio__') {
        if ($r) $fallas[] = "  $nombre: debería no producir leads y produjo " . count($r);
        continue;
    }
    if (!$r) { $fallas[] = "  $nombre: no produjo ningún lead"; continue; }
    $lead = $r[0];

    foreach ($espera as $campo => $valor) {
        if (($lead[$campo] ?? null) !== $valor) {
            $fallas[] = sprintf('  %s: %s esperaba «%s» y dio «%s»',
                $nombre, $campo, $valor, (string)($lead[$campo] ?? 'null'));
        }
    }
    // Lo mínimo para devolver la llamada.
    if (trim((string)$lead['nombre']) === ''
        && !$lead['telefono'] && !$lead['correo']) {
        $fallas[] = "  $nombre: sin nombre, teléfono ni correo — Entrada lo descartaría";
    }
}

echo "\n";
if ($fallas) {
    echo "  traductores con problemas:\n" . implode("\n", $fallas) . "\n\n";
    echo '  ' . count($fallas) . ' fallas en ' . count($casos) . " casos\n\n";
    exit(1);
}
echo '  canales: los ' . count($casos) . " casos traducen a un lead utilizable\n\n";

// ── Coherencia: cada plataforma tiene su propio origen ──
//
// ProspectoRepo::valido() devuelve 'manual' para lo que no reconoce. Es
// lo correcto —nada inventado entra a la base— pero significa que
// olvidar un origen NO falla: el lead entra como «captura manual» y se
// pierde de dónde vino, que es justo el dato con el que se decide dónde
// gastar en anuncios.
require dirname(__DIR__) . '/src/Servicio/Entrada.php';
require dirname(__DIR__) . '/src/Datos/ProspectoRepo.php';

$sinOrigen = [];
foreach (LibertyFin\Servicio\Entrada::PLATAFORMAS as $p) {
    $o = $p === 'google_ads' ? 'google' : $p;
    if (!isset(LibertyFin\Datos\ProspectoRepo::ORIGENES[$o])) $sinOrigen[] = "$p → $o";
}
if ($sinOrigen) {
    echo "  plataformas sin origen propio (entrarían como «manual»):\n";
    foreach ($sinOrigen as $s) echo "    $s\n";
    echo "\n";
    exit(1);
}
echo '  origenes: las ' . count(LibertyFin\Servicio\Entrada::PLATAFORMAS)
   . " plataformas tienen el suyo\n\n";
