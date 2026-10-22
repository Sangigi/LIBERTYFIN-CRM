<?php
namespace LibertyFin\Servicio;

/**
 * Qué integraciones están disponibles.
 *
 * Las credenciales viven en config/integraciones.php, fuera de public/
 * y fuera del repositorio. Esta clase es lo único que las lee, y NUNCA
 * las devuelve completas hacia una vista: para eso está estado(), que
 * solo dice si están puestas.
 */
final class Integraciones
{
    private static $cfg = null;
    private static $ruta = '';
    private static $hayArchivo = false;

    const CATALOGO = [
        // Emida pide cuatro datos, no tres. `merchant_id` identifica al
        // comercio y `clerk_password` a la terminal: son distintos de la
        // clave de la cuenta aunque el proveedor a veces los reparta igual.
        // ── Lo que NO está aquí, y por qué ──
        //
        // Emida (recargas), Facturapi (CFDI) y PayPal (ligas con
        // tarjeta) se fueron con el POS: las tres mueven o comprueban
        // dinero del cliente de la empresa, y el CRM no cobra.
        //
        // `spei` SÍ se queda, pero solo para la SUSCRIPCIÓN: es la
        // empresa pagándole su plan a LibertyFin, no la empresa
        // cobrándole a su cliente. Ver PagosEntrantesControlador.
        'spei'          => ['Suscripción por SPEI', 'Cobro del plan de LibertyFin CRM',
                            ['usuario','clave','integracion_id']],
        'domiciliacion' => ['Cargos recurrentes', 'Domiciliación del plan',
                            ['usuario','clave','integracion_id']],

        // LibertyPay: la pasarela propia para cobros con tarjeta.
        //
        // AQUÍ SOLO SIRVE PARA LA SUSCRIPCIÓN, por la misma razón que
        // spei. El cobro con tarjeta al cliente de la empresa lo hace
        // LibertyFin POS, que es quien tiene caja y quien timbra.
        //
        // Cuando el CRM necesite saber si un contrato ya se pagó con
        // tarjeta, NO le pregunta a LibertyPay: le pregunta al POS por
        // el estado de la venta (docs/api/estado-de-cobro.md). Un solo
        // camino para un solo dato; dos fuentes es como dejan de cuadrar.
        'libertypay'    => ['LibertyPay', 'Pago del plan con tarjeta',
                            ['comercio','llave','secreto']],
        // ══════════════════════════════════════════════════════
        //  CAPTACIÓN DE LEADS
        // ══════════════════════════════════════════════════════
        //
        // Esto es el corazón del CRM base, no un extra. Un despacho, una
        // agencia y una inmobiliaria se diferencian en lo que hacen
        // DESPUÉS del lead; en cómo les llega, no.
        //
        // Las seis entran por el mismo sitio —Canales\Entrada— y salen
        // como prospecto. Lo que cambia entre ellas es el sobre, no la
        // carta: por eso el módulo las trata igual en cuanto cruzan.
        //
        // Meta (WhatsApp, Instagram y Facebook) comparte credenciales:
        // un solo token de app y un solo secreto de webhook sirven para
        // las tres, y pedirlos tres veces solo invita a equivocarse.
        'meta'          => ['WhatsApp, Instagram y Facebook',
                            'Mensajes y formularios de Meta como prospectos',
                            ['token','secreto_webhook','id_app']],
        'tiktok'        => ['TikTok Lead Generation', 'Formularios de TikTok como prospectos',
                            ['token','id_anunciante']],
        'linkedin'      => ['LinkedIn Lead Gen Forms', 'Formularios de LinkedIn como prospectos',
                            ['token','id_cuenta']],
        'google_ads'    => ['Google Ads Lead Forms', 'Formularios de Google Ads como prospectos',
                            ['secreto_webhook','id_cliente']],
        'smtp'          => ['Correo saliente', 'Envío de avisos y recordatorios',
                            ['host','usuario','clave']],
        'cpanel'        => ['cPanel', 'Alta de bases para empresas nuevas',
                            ['host','usuario','token']],
    ];

    public static function cargar($raiz)
    {
        if (self::$cfg !== null) return;
        self::$ruta = $raiz . '/config/integraciones.php';
        self::$hayArchivo = is_readable(self::$ruta);
        self::$cfg = self::$hayArchivo ? (array)require self::$ruta : [];
    }

    /**
     * Si el archivo existe y dónde se buscó.
     *
     * Sin esto, una integración configurada en
     * `integraciones.php.ejemplo` —que es el error natural— se ve
     * exactamente igual que una sin configurar, y no hay forma de saber
     * cuál de las dos cosas pasa.
     */
    public static function archivo()
    {
        return ['ruta' => self::$ruta, 'existe' => self::$hayArchivo,
                'bloques' => is_array(self::$cfg) ? count(self::$cfg) : 0];
    }

    /** La configuración de una integración, o null si no está lista. */
    public static function de($nombre)
    {
        if (self::$cfg === null) return null;
        $c = self::$cfg[$nombre] ?? null;
        return (self::lista($nombre) && !empty($c['activo'])) ? $c : null;
    }

    /** ¿Tiene todas sus credenciales llenas? */
    public static function lista($nombre)
    {
        $c = self::$cfg[$nombre] ?? null;
        if (!$c || !isset(self::CATALOGO[$nombre])) return false;
        foreach (self::CATALOGO[$nombre][2] as $campo) {
            if (trim((string)($c[$campo] ?? '')) === '') return false;
        }
        return true;
    }

    public static function activa($nombre) { return self::de($nombre) !== null; }

    /**
     * El estado de todas, para mostrarlo.
     * Devuelve si están listas y en qué modo, NUNCA las credenciales.
     */
    public static function estado()
    {
        $r = [];
        foreach (self::CATALOGO as $k => $d) {
            $c = self::$cfg[$k] ?? [];
            $lista = self::lista($k);
            $r[$k] = [
                'nombre'    => $d[0],
                'para'      => $d[1],
                'campos'    => $d[2],
                'lista'     => $lista,
                'activa'    => $lista && !empty($c['activo']),
                'sandbox'   => !empty($c['sandbox']),
                'faltan'    => array_values(array_filter($d[2], function ($campo) use ($c) {
                    return trim((string)($c[$campo] ?? '')) === '';
                })),
                'aviso'     => self::aviso($k, $c),
            ];
        }
        return $r;
    }

    /**
     * Lo que está configurado a medias.
     *
     * No es lo mismo que `faltan`: ahí van las credenciales sin las que
     * la integración ni enciende. Esto es lo que enciende pero deja algo
     * sin funcionar, y que de otro modo solo se descubre el día que un
     * cliente paga y el pago nunca aparece.
     */
    private static function aviso($nombre, array $c)
    {
        if ($nombre !== 'spei' || empty($c['activo'])) return '';

        // Una `url_*` puesta a mano que apunta al servicio equivocado. El
        // sistema la ignora y usa la buena, pero hay que decirlo: si no,
        // el archivo se queda mintiendo para siempre.
        foreach ([
            'liga'       => '/Service/GenerarLigaIndi',
            'clabe'      => '/Service/GenerarClabeIndi',
            'referencia' => '/Service/GenerarReferenciaIndi',
            'estado'     => '/Service/ConsultarEstatusLigaIndi',
        ] as $servicio => $ruta) {
            $puesta = trim((string)($c['url_' . $servicio] ?? ''));
            if ($puesta === '') continue;
            $queja = \LibertyFin\Servicio\LigaPago::revisarUrl($puesta, $ruta);
            if ($queja !== '') {
                return '`url_' . $servicio . '` ' . $queja . '. Se ignora y se usa la '
                     . 'dirección normal, pero bórrala del archivo. '
                     . 'Corre php app/bin/revisar-pagos.php para ver todas.';
            }
        }

        if (trim((string)($c['secreto_webhook'] ?? '')) === '') {
            return 'Falta `secreto_webhook`: sin él los avisos del proveedor se '
                 . 'rechazan y los pagos por SPEI y en tienda nunca se van a aplicar solos.';
        }

        // En Sandbox no se cobra de verdad: sin credenciales propias no se
        // genera nada.
        $bloqueo = (new \LibertyFin\Servicio\LigaPago($c))->bloqueoSandbox();
        if ($bloqueo !== '') return $bloqueo;
        if (trim((string)($c['negocio_id'] ?? '')) === ''
            && trim((string)($c['escuela_id'] ?? '')) !== '') {
            return 'Estás usando `escuela_id` (Paga la Escuela) como BusinessID. '
                 . 'Si tu convenio es de Paga de Todo, pásalo a `negocio_id`.';
        }
        $host = (string)($c['host'] ?? '');
        if ($host !== '' && stripos($host, 'pagalaescuela') !== false) {
            return 'El `host` apunta a Paga la Escuela. Para Paga de Todo va '
                 . 'https://pagadetodo.mx/Pagadetodo';
        }
        return '';
    }
}
