<?php
namespace LibertyFin\Servicio;

use LibertyFin\Datos\ProspectoRepo;
use LibertyFin\Dominio\Alcance;
use PDO;

/**
 * La puerta única por la que entran los leads.
 *
 * POR QUÉ UNA SOLA
 *
 * WhatsApp manda un mensaje, Instagram una historia respondida, TikTok
 * un formulario, Google Ads otro formulario con otros nombres de campo.
 * Seis sobres distintos para la misma carta: alguien preguntó algo y
 * dejó cómo contactarlo.
 *
 * Si cada plataforma escribiera en `lf_prospectos` por su cuenta,
 * acabaría habiendo seis criterios distintos para decidir si un lead es
 * repetido, seis formas de llamar al teléfono y seis sitios donde
 * arreglar el mismo error. Por eso cada adaptador solo TRADUCE a un
 * arreglo normalizado, y esta clase es la única que escribe.
 *
 * EL ALCANCE AQUÍ ES «TODO», A PROPÓSITO
 *
 * Esto corre desde un webhook: no hay sesión ni usuario. Un lead entra
 * SIN responsable, y así cualquiera puede tomarlo (ver
 * Alcance::alcanza(), que deja pasar lo que no tiene dueño). Si entrara
 * asignado a alguien, los leads de las 11 de la noche se quedarían
 * esperando a esa persona.
 */
final class Entrada
{
    /** De dónde puede venir. Coincide con ProspectoRepo::ORIGENES. */
    const PLATAFORMAS = ['whatsapp', 'instagram', 'facebook', 'tiktok', 'linkedin', 'google_ads'];

    /**
     * Cuánto tiempo hacia atrás se busca un lead repetido.
     *
     * Quince días: menos deja entrar al que rellena el mismo formulario
     * dos días seguidos, y más junta al que preguntó por un divorcio en
     * marzo con el que pregunta por un mercantil en junio, que son dos
     * asuntos distintos de la misma persona.
     */
    const DIAS_REPETIDO = 15;

    private $db;

    public function __construct(PDO $db) { $this->db = $db; }

    /**
     * Mete un lead ya traducido. Devuelve [id, nuevo].
     *
     * @param array $lead  plataforma, nombre, telefono, correo, asunto,
     *                     texto, campana, externo_id
     */
    public function recibir(array $lead)
    {
        $plataforma = in_array($lead['plataforma'] ?? '', self::PLATAFORMAS, true)
                    ? $lead['plataforma'] : 'web';

        $tel    = self::telefono($lead['telefono'] ?? '');
        $correo = trim((string)($lead['correo'] ?? '')) ?: null;
        $nombre = trim((string)($lead['nombre'] ?? ''));

        // Sin nombre, teléfono NI correo no hay a quién devolverle la
        // llamada: es ruido, y meterlo ensucia el embudo del agente.
        if ($nombre === '' && !$tel && !$correo) {
            return ['id' => 0, 'nuevo' => false, 'motivo' => 'sin datos de contacto'];
        }
        if ($nombre === '') $nombre = $tel ?: $correo;

        // El webhook escribe como el sistema, sin límite de alcance.
        $repo = new ProspectoRepo($this->db, Alcance::sinLimite());

        $repetido = $this->repetido($tel, $correo, $lead['externo_id'] ?? null);
        if ($repetido) {
            // No se crea otro: se anota en el que ya existe. Dos tarjetas
            // del mismo señor es cómo dos agentes le llaman el mismo día.
            $repo->anotar($repetido, self::resumen($lead, $plataforma), null, $plataforma);
            return ['id' => $repetido, 'nuevo' => false];
        }

        $id = $repo->crear([
            'nombre'         => mb_substr($nombre, 0, 160),
            'telefono'       => $tel,
            'correo'         => $correo,
            'origen'         => $plataforma === 'google_ads' ? 'google' : $plataforma,
            'asunto'         => mb_substr(trim((string)($lead['asunto'] ?? '')), 0, 255) ?: null,
            'etapa'          => 'nuevo',
            'semaforo'       => 'verde',
            'responsable_id' => null,   // sin dueño: cualquiera puede tomarlo
            'proximo_paso'   => 'Primer contacto',
            'proxima_fecha'  => date('Y-m-d'),   // hoy: un lead frío en un día
        ]);

        if ($id) {
            $repo->anotar($id, self::resumen($lead, $plataforma), null, $plataforma);
            $this->recordarExterno($id, $lead['externo_id'] ?? null);
        }
        return ['id' => $id, 'nuevo' => true];
    }

    /**
     * ¿Ya lo tenemos?
     *
     * Se busca por el id externo primero —es exacto y lo manda la
     * plataforma al reintentar— y luego por teléfono o correo dentro de
     * la ventana. El nombre NO entra en la comparación: hay demasiados
     * «José García» y juntarlos sería peor que duplicar.
     */
    private function repetido($tel, $correo, $externoId)
    {
        if ($externoId) {
            $st = $this->db->prepare(
                "SELECT prospecto_id FROM lf_prospecto_externos WHERE externo_id = ? LIMIT 1");
            $st->execute([(string)$externoId]);
            if ($id = $st->fetchColumn()) return (int)$id;
        }
        if (!$tel && !$correo) return null;

        $w = []; $p = [];
        if ($tel)    { $w[] = 'telefono = ?'; $p[] = $tel; }
        if ($correo) { $w[] = 'correo = ?';   $p[] = $correo; }
        $p[] = self::DIAS_REPETIDO;

        $st = $this->db->prepare(
            "SELECT id FROM lf_prospectos
              WHERE (" . implode(' OR ', $w) . ")
                AND etapa NOT IN ('ganado','perdido')
                AND creado_en >= DATE_SUB(NOW(), INTERVAL ? DAY)
              ORDER BY id DESC LIMIT 1");
        $st->execute($p);
        $id = $st->fetchColumn();
        return $id ? (int)$id : null;
    }

    /** Guarda el id de la plataforma, para que un reintento no duplique. */
    private function recordarExterno($prospectoId, $externoId)
    {
        if (!$externoId) return;
        $st = $this->db->prepare(
            "INSERT IGNORE INTO lf_prospecto_externos (prospecto_id, externo_id, creado_en)
             VALUES (?,?,NOW())");
        $st->execute([(int)$prospectoId, (string)$externoId]);
    }

    /** Lo que se anota en la bitácora cuando entra. */
    private static function resumen(array $lead, $plataforma)
    {
        $p = ['whatsapp' => 'WhatsApp', 'instagram' => 'Instagram',
              'facebook' => 'Facebook', 'tiktok' => 'TikTok',
              'linkedin' => 'LinkedIn', 'google_ads' => 'Google Ads'][$plataforma] ?? $plataforma;

        $t = 'Entró por ' . $p;
        if (!empty($lead['campana'])) $t .= ' (campaña: ' . trim((string)$lead['campana']) . ')';
        if (!empty($lead['texto']))   $t .= ': ' . mb_substr(trim((string)$lead['texto']), 0, 1500);
        return $t;
    }

    /**
     * El teléfono, solo con lo que sirve para marcar.
     *
     * Se guarda igual que lo guarda ClienteRepo, o el mismo número no
     * se reconocería entre los dos y el lead entraría duplicado.
     */
    private static function telefono($v)
    {
        $v = preg_replace('/[^0-9+]/', '', (string)$v);
        return $v !== '' ? mb_substr($v, 0, 40) : null;
    }
}
