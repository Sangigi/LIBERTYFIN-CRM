<?php
namespace LibertyFin\Controlador;

use LibertyFin\Datos\Conexion;
use LibertyFin\Dominio\Permisos;
use LibertyFin\Servicio\Entrada;
use LibertyFin\Servicio\Integraciones;
use LibertyFin\Servicio\Modulos;
use LibertyFin\Servicio\Traductores;
use LibertyFin\Vista\Plantilla;

/**
 * Canales (módulo «canales»): por dónde entran los leads.
 *
 * Dos mitades que no se parecen:
 *
 *  - El WEBHOOK, que llama la plataforma. Sin sesión, sin cookie, sin
 *    persona. Su puerta es el secreto de la URL más la firma.
 *  - La PANTALLA, que ve el equipo para saber qué entró y qué se
 *    descartó.
 *
 * REGLA DEL WEBHOOK: RESPONDER 200 RÁPIDO
 *
 * Meta, TikTok y Google reintentan si no reciben un 200 a tiempo, y si
 * se reintenta mucho acaban desactivando la suscripción. Por eso se
 * responde en cuanto el lead queda ANOTADO, y los errores de traducción
 * se guardan en la bitácora en vez de devolverse como 500: un lead mal
 * traducido es un problema nuestro, no de la plataforma.
 */
final class CanalesControlador
{
    /**
     * La verificación que hace Meta al dar de alta el webhook: manda un
     * `hub.challenge` y espera recibirlo de vuelta, en crudo.
     */
    public function verificar($empresa)
    {
        $cfg = Integraciones::de('meta');
        $token = $cfg['secreto_webhook'] ?? '';
        $dado  = (string)($_GET['hub_verify_token'] ?? ($_GET['hub.verify_token'] ?? ''));

        if ($token === '' || !hash_equals((string)$token, $dado)) {
            http_response_code(403);
            echo 'token no válido';
            return;
        }
        header('Content-Type: text/plain; charset=utf-8');
        echo (string)($_GET['hub_challenge'] ?? ($_GET['hub.challenge'] ?? ''));
    }

    /**
     * La entrada de verdad.
     *
     * @param string $empresa  la base de la empresa, en la URL: cada una
     *                         tiene su propio webhook porque cada una
     *                         tiene su propia base.
     * @param string $red      meta | tiktok | linkedin | google
     */
    public function recibir($empresa, $red)
    {
        $crudo = file_get_contents('php://input');
        $j = json_decode($crudo, true);
        if (!is_array($j)) { $this->ok(0, 'json inválido'); return; }

        $cfg = Integraciones::de($this->llaveDe($red));
        if (!$cfg) { $this->ok(0, 'canal no configurado'); return; }

        // La firma: lo único que separa un lead de verdad de cualquiera
        // que descubra la URL. Se comprueba sobre el cuerpo CRUDO, no
        // sobre el JSON ya decodificado: volver a serializarlo cambia
        // los espacios y la firma deja de cuadrar.
        if (!$this->firmaValida($red, $crudo, $cfg)) {
            http_response_code(401);
            echo 'firma no válida';
            return;
        }

        try {
            $db = Conexion::de($this->baseDe($empresa));
        } catch (\Throwable $e) {
            error_log('[canales] empresa desconocida: ' . $empresa);
            $this->ok(0, 'empresa desconocida');
            return;
        }

        $leads = $this->traducir($red, $j);
        $entrada = new Entrada($db);
        $metidos = 0;

        foreach ($leads as $lead) {
            try {
                $r = $entrada->recibir($lead);
                if (!empty($r['id'])) $metidos++;
                $this->bitacora($db, $lead['plataforma'] ?? $red, $lead['externo_id'] ?? null,
                                $crudo, $r['id'] ?? null,
                                empty($r['id']) ? ($r['motivo'] ?? 'descartado')
                                                : ($r['nuevo'] ? 'nuevo' : 'repetido'));
            } catch (\Throwable $e) {
                // Un lead roto no tumba a los demás del mismo lote.
                error_log('[canales] ' . $red . ': ' . $e->getMessage());
                $this->bitacora($db, $red, null, $crudo, null, 'error');
            }
        }

        if (!$leads) $this->bitacora($db, $red, null, $crudo, null, 'sin leads');
        $this->ok($metidos, null);
    }

    /** La pantalla: qué entró, por dónde y qué pasó con cada uno. */
    public function index()
    {
        if (!Modulos::exigir('canales')) return;
        $db = Conexion::de($_SESSION['empresa_db']);

        $st = $db->prepare(
            "SELECT e.*, p.nombre AS prospecto
               FROM lf_canal_entradas e
               LEFT JOIN lf_prospectos p ON p.id = e.prospecto_id
              ORDER BY e.creado_en DESC, e.id DESC
              LIMIT 200");
        $st->execute();

        Plantilla::pagina('canales/index', [
            'titulo'      => 'Canales',
            'icono'       => 'chat',
            'subtitulo'   => 'Por dónde entran los leads',
            'entradas'    => $st->fetchAll(\PDO::FETCH_ASSOC),
            'estado'      => Integraciones::estado(),
            'base'        => $_SESSION['empresa_db'] ?? '',
            'puedeEditar' => Permisos::puede('editar.canales'),
            'aviso'       => $_SESSION['lf_aviso'] ?? null,
        ]);
        unset($_SESSION['lf_aviso']);
    }

    // ── Apoyos ──

    private function traducir($red, array $j)
    {
        switch ($red) {
            case 'meta':     return Traductores::meta($j);
            case 'tiktok':   return Traductores::tiktok($j);
            case 'linkedin': return Traductores::linkedin($j);
            case 'google':   return Traductores::googleAds($j);
        }
        return [];
    }

    private function llaveDe($red)
    {
        return $red === 'google' ? 'google_ads' : $red;
    }

    /**
     * Cada plataforma firma distinto.
     *
     * Meta y TikTok mandan un HMAC del cuerpo en una cabecera. Google
     * Ads no firma: manda una clave compartida DENTRO del JSON, así que
     * ahí se compara esa. LinkedIn entrega por API y no por webhook
     * abierto, así que no trae firma que comprobar.
     *
     * hash_equals en los tres casos: comparar con === filtra el secreto
     * por el tiempo que tarda en fallar.
     */
    private function firmaValida($red, $crudo, array $cfg)
    {
        if ($red === 'meta') {
            $cab = (string)($_SERVER['HTTP_X_HUB_SIGNATURE_256'] ?? '');
            if ($cab === '') return false;
            $esperada = 'sha256=' . hash_hmac('sha256', $crudo, (string)($cfg['secreto_webhook'] ?? ''));
            return hash_equals($esperada, $cab);
        }
        if ($red === 'tiktok') {
            $cab = (string)($_SERVER['HTTP_X_TT_SIGNATURE'] ?? '');
            if ($cab === '') return false;
            return hash_equals(hash_hmac('sha256', $crudo, (string)($cfg['token'] ?? '')), $cab);
        }
        if ($red === 'google') {
            $j = json_decode($crudo, true);
            $dada = (string)($j['google_key'] ?? '');
            return $dada !== '' && hash_equals((string)($cfg['secreto_webhook'] ?? ''), $dada);
        }
        return true;   // linkedin: se consulta por API, no entra por aquí
    }

    /**
     * De la empresa en la URL a su base.
     *
     * La URL lleva el nombre de la base para no exponer un id interno,
     * y se limpia porque va a una conexión: letras, números y guion bajo.
     */
    private function baseDe($empresa)
    {
        $e = preg_replace('/[^A-Za-z0-9_]/', '', (string)$empresa);
        if ($e === '') throw new \InvalidArgumentException('empresa vacía');
        return $e;
    }

    /**
     * Lo que llegó, antes y después de traducirlo.
     *
     * Cuando un lead «no aparece», esto es lo único que distingue entre
     * «la plataforma no lo mandó» y «lo mandó y lo descartamos».
     */
    private function bitacora($db, $plataforma, $externoId, $crudo, $prospectoId, $resultado)
    {
        try {
            $st = $db->prepare(
                "INSERT INTO lf_canal_entradas
                   (plataforma, externo_id, carga, prospecto_id, resultado, creado_en)
                 VALUES (?,?,?,?,?,NOW())");
            $st->execute([
                mb_substr((string)$plataforma, 0, 20),
                $externoId ? mb_substr((string)$externoId, 0, 190) : null,
                mb_substr((string)$crudo, 0, 60000),
                $prospectoId ?: null,
                mb_substr((string)$resultado, 0, 30),
            ]);
        } catch (\Throwable $e) {
            error_log('[canales] bitácora: ' . $e->getMessage());
        }
    }

    /** 200 siempre que el problema sea nuestro, o la plataforma reintenta. */
    private function ok($metidos, $nota)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'leads' => $metidos] + ($nota ? ['nota' => $nota] : []),
                         JSON_UNESCAPED_UNICODE);
    }
}
