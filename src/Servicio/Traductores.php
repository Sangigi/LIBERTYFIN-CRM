<?php
namespace LibertyFin\Servicio;

/**
 * El sobre de cada plataforma, traducido a la misma carta.
 *
 * Cada método recibe el JSON crudo de una plataforma y devuelve el
 * arreglo normalizado que Servicio\Entrada sabe meter. NO escriben en la
 * base, no deciden si el lead es repetido y no saben qué es un
 * prospecto: solo traducen. Así se pueden probar con un JSON de ejemplo
 * y sin base de datos, que es justo lo que hace revisar-canales.php.
 *
 * Todos devuelven una LISTA, porque un solo webhook puede traer varios
 * leads: Meta manda sus `entry` en bloque y TikTok pagina.
 */
final class Traductores
{
    /**
     * Meta: WhatsApp, Instagram y Facebook comparten formato.
     *
     * Dos cosas distintas llegan por aquí:
     *  - mensajes (alguien escribió al número o al perfil)
     *  - leadgen (alguien rellenó un formulario de anuncio)
     */
    public static function meta(array $j)
    {
        $out = [];
        foreach ($j['entry'] ?? [] as $entrada) {
            foreach ($entrada['changes'] ?? [] as $cambio) {
                $v = $cambio['value'] ?? [];

                // a) Formulario de anuncio
                if (($cambio['field'] ?? '') === 'leadgen' || isset($v['leadgen_id'])) {
                    $out[] = self::deCampos(
                        $v['field_data'] ?? [],
                        self::red($v, $entrada),
                        $v['leadgen_id'] ?? null,
                        $v['ad_name'] ?? ($v['campaign_name'] ?? null));
                    continue;
                }

                // b) Mensaje. El nombre viene en `contacts`, aparte del
                //    mensaje: hay que cruzarlos por el teléfono.
                $nombres = [];
                foreach ($v['contacts'] ?? [] as $c) {
                    $nombres[$c['wa_id'] ?? ''] = $c['profile']['name'] ?? '';
                }
                foreach ($v['messages'] ?? [] as $m) {
                    $de = $m['from'] ?? '';
                    $out[] = [
                        'plataforma' => self::red($v, $entrada),
                        'nombre'     => $nombres[$de] ?? '',
                        'telefono'   => $de,
                        'correo'     => null,
                        'asunto'     => null,
                        'texto'      => $m['text']['body'] ?? ('[' . ($m['type'] ?? 'mensaje') . ']'),
                        'campana'    => null,
                        'externo_id' => $m['id'] ?? null,
                    ];
                }
            }

            // Instagram y Messenger mandan los mensajes en `messaging`.
            foreach ($entrada['messaging'] ?? [] as $m) {
                if (!isset($m['message'])) continue;   // entregas y lecturas, no leads
                // Un DM de Instagram NO trae teléfono ni correo: trae
                // un PSID. Si se dejara el nombre vacío, Entrada lo
                // descartaría por «sin datos de contacto» y el lead se
                // perdería sin que nadie se entere.
                //
                // Se guarda el PSID como nombre provisional para que el
                // agente pueda contestarle por el mismo canal. El nombre
                // real se pide a la Graph API cuando haya permiso; hasta
                // entonces, vale más un lead feo que un lead perdido.
                $psid = $m['sender']['id'] ?? '';
                $out[] = [
                    'plataforma' => 'instagram',
                    'nombre'     => $psid !== '' ? 'Instagram ' . $psid : 'Instagram (sin identificar)',
                    'telefono'   => null,
                    'correo'     => null,
                    'asunto'     => null,
                    'texto'      => $m['message']['text'] ?? '[mensaje]',
                    'campana'    => null,
                    'externo_id' => $m['message']['mid'] ?? null,
                ];
            }
        }
        return $out;
    }

    /** TikTok Lead Generation. */
    public static function tiktok(array $j)
    {
        $out = [];
        foreach (self::lista($j, ['data', 'list']) as $l) {
            $out[] = self::deCampos(
                $l['field_data'] ?? ($l['answers'] ?? []),
                'tiktok',
                $l['lead_id'] ?? ($l['id'] ?? null),
                $l['ad_name'] ?? ($l['campaign_name'] ?? null));
        }
        return $out;
    }

    /** LinkedIn Lead Gen Forms. */
    public static function linkedin(array $j)
    {
        $out = [];
        foreach (self::lista($j, ['elements', 'data']) as $l) {
            // LinkedIn parte el nombre en dos campos propios, fuera de
            // las respuestas del formulario.
            $nombre = trim(($l['firstName'] ?? '') . ' ' . ($l['lastName'] ?? ''));
            $lead = self::deCampos(
                $l['formResponse']['answers'] ?? ($l['answers'] ?? []),
                'linkedin',
                $l['id'] ?? null,
                $l['campaign'] ?? null);
            if ($nombre !== '') $lead['nombre'] = $nombre;
            $out[] = $lead;
        }
        return $out;
    }

    /**
     * Google Ads Lead Forms.
     *
     * Manda un solo lead por llamada, con los campos en `user_column_data`
     * y el nombre del campo en `column_id`.
     */
    public static function googleAds(array $j)
    {
        $campos = [];
        foreach ($j['user_column_data'] ?? [] as $c) {
            $campos[] = ['name'  => $c['column_id'] ?? ($c['column_name'] ?? ''),
                         'values' => [$c['string_value'] ?? '']];
        }
        if (!$campos) return [];
        return [self::deCampos($campos, 'google_ads',
                               $j['lead_id'] ?? null,
                               $j['campaign_id'] ?? null)];
    }

    // ── Apoyos ──

    /**
     * De los campos de un formulario al lead.
     *
     * Las plataformas nombran lo mismo de formas distintas —`full_name`,
     * `FULL_NAME`, `nombre`, `first_name`— y cada anunciante además
     * inventa los suyos. Se reconocen por lo que CONTIENE el nombre del
     * campo, no por coincidencia exacta, y lo que no se reconoce no se
     * tira: va al texto, para que el agente lo vea al llamar.
     */
    private static function deCampos(array $campos, $plataforma, $externoId, $campana)
    {
        $lead = ['plataforma' => $plataforma, 'nombre' => '', 'telefono' => null,
                 'correo' => null, 'asunto' => null, 'texto' => '',
                 'campana' => $campana, 'externo_id' => $externoId];
        $sobras = [];

        foreach ($campos as $c) {
            $k = strtolower((string)($c['name'] ?? ($c['key'] ?? '')));
            $v = is_array($c['values'] ?? null) ? implode(', ', $c['values'])
                                                : (string)($c['values'] ?? ($c['value'] ?? ''));
            $v = trim($v);
            if ($v === '') continue;

            if (self::tiene($k, ['full_name', 'nombre_completo'])) { $lead['nombre'] = $v; }
            elseif (self::tiene($k, ['first_name', 'given_name'])) {
                $lead['nombre'] = trim($v . ' ' . $lead['nombre']);
            } elseif (self::tiene($k, ['last_name', 'family_name', 'apellido'])) {
                $lead['nombre'] = trim($lead['nombre'] . ' ' . $v);
            // `name` a secas lo manda TikTok. Va DESPUÉS de first/last
            // a propósito: 'first_name' también contiene 'name', y si
            // esta rama fuera antes se tragaría el nombre de pila.
            } elseif (self::tiene($k, ['name', 'nombre'])) {
                if (trim((string)$lead['nombre']) === '') $lead['nombre'] = $v;
            } elseif (self::tiene($k, ['phone', 'telefono', 'tel', 'movil', 'celular', 'whatsapp'])) {
                $lead['telefono'] = $v;
            } elseif (self::tiene($k, ['email', 'correo', 'mail'])) {
                $lead['correo'] = $v;
            } elseif (self::tiene($k, ['asunto', 'servicio', 'interes', 'subject', 'tipo'])) {
                $lead['asunto'] = $v;
            } else {
                $sobras[] = ($c['name'] ?? '?') . ': ' . $v;
            }
        }

        if ($sobras) {
            $lead['texto'] = trim($lead['texto'] . "\n" . implode("\n", $sobras));
        }
        return $lead;
    }

    private static function tiene($clave, array $agujas)
    {
        foreach ($agujas as $a) if (strpos($clave, $a) !== false) return true;
        return false;
    }

    /** La lista de leads, esté donde esté dentro del JSON. */
    private static function lista(array $j, array $rutas)
    {
        foreach ($rutas as $r) {
            if (isset($j[$r]) && is_array($j[$r])) {
                // A veces es la lista; a veces el envoltorio que la lleva.
                $v = $j[$r];
                if (isset($v['list']) && is_array($v['list'])) return $v['list'];
                if (array_keys($v) === range(0, count($v) - 1)) return $v;
            }
        }
        return [];
    }

    /** ¿WhatsApp, Instagram o Facebook? Meta lo dice de varias formas. */
    private static function red(array $v, array $entrada)
    {
        if (isset($v['messaging_product'])) {
            return $v['messaging_product'] === 'whatsapp' ? 'whatsapp' : 'facebook';
        }
        if (isset($v['metadata']['phone_number_id'])) return 'whatsapp';
        if (($entrada['object'] ?? '') === 'instagram') return 'instagram';
        return 'facebook';
    }
}
