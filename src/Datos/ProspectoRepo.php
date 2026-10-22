<?php
namespace LibertyFin\Datos;

use LibertyFin\Dominio\Alcance;
use PDO;

/**
 * El embudo.
 *
 * UN PROSPECTO NO ES UN CLIENTE
 *
 * Vive en su propia tabla y no en `clientes` con una bandera. Con la
 * bandera habría que filtrarla en CADA consulta del sistema, y el día
 * que alguien la olvide un prospecto que nunca contrató aparecerá en
 * cobranza, en un reporte de facturación o en una lista de morosos.
 *
 * Cuando gana, nace un cliente de verdad y el prospecto guarda a quién
 * dio origen. Los dos registros se quedan: el prospecto es la historia
 * de cómo llegó, y esa historia es lo que deja ver por qué se cerró.
 *
 * EL ALCANCE VA EN EL SQL
 *
 * Todas las lecturas llevan el filtro de Dominio\Alcance pegado al
 * WHERE. Un agente no puede pedir los prospectos de otro ni escribiendo
 * la URL a mano, porque la consulta nunca se los trae.
 */
final class ProspectoRepo
{
    /** Las etapas, en orden. La última no es etapa: es la salida. */
    const ETAPAS = [
        'nuevo'      => 'Nuevo',
        'contactado' => 'Contactado',
        'cita'       => 'Cita agendada',
        'propuesta'  => 'Propuesta enviada',
        'ganado'     => 'Ganado',
        'perdido'    => 'Perdido',
    ];

    /**
     * El semáforo, como se acordó: tres colores y nada más.
     *
     * No lleva "ganado" ni "perdido": esos son etapa, no color. Un
     * prospecto ganado no es "verde", simplemente ya salió del embudo.
     */
    const SEMAFORO = [
        'verde'    => 'Sigue en proceso',
        'amarillo' => 'Pausado',
        'rojo'     => 'Cancelado',
    ];

    /** De dónde llegó. `canales` escribe aquí cuando entra un mensaje. */
    const ORIGENES = [
        'manual'    => 'Captura manual',
        'whatsapp'  => 'WhatsApp',
        'instagram' => 'Instagram',
        'facebook'  => 'Facebook',
        'web'       => 'Sitio web',
        'referido'  => 'Referido',
        'llamada'   => 'Llamada',
    ];

    private $db;
    private $alcance;

    public function __construct(PDO $db, Alcance $alcance = null)
    {
        $this->db = $db;
        $this->alcance = $alcance ?: Alcance::deSesion();
    }

    /**
     * El tablero: los prospectos abiertos, agrupados por etapa.
     *
     * Ganados y perdidos NO salen. El embudo es para trabajar lo que
     * sigue vivo; lo cerrado se consulta en el historial, que es otra
     * pantalla y otra pregunta.
     */
    public function tablero()
    {
        $sql = "SELECT p.*, u.nombre AS responsable
                  FROM lf_prospectos p
                  LEFT JOIN usuarios u ON u.id = p.responsable_id
                 WHERE p.etapa NOT IN ('ganado','perdido')"
             . $this->alcance->filtro('responsable_id', 'p')
             . " ORDER BY FIELD(p.semaforo,'verde','amarillo','rojo'),
                          p.proxima_fecha IS NULL, p.proxima_fecha, p.creado_en DESC";

        $st = $this->db->prepare($sql);
        $st->execute($this->alcance->parametros('responsable_id'));

        $porEtapa = [];
        foreach (array_keys(self::ETAPAS) as $e) {
            if ($e === 'ganado' || $e === 'perdido') continue;
            $porEtapa[$e] = [];
        }
        foreach ($st->fetchAll() as $r) {
            $e = isset($porEtapa[$r['etapa']]) ? $r['etapa'] : 'nuevo';
            $porEtapa[$e][] = $r;
        }
        return $porEtapa;
    }

    /**
     * Uno solo. Devuelve null si no existe O si no le toca a quien
     * pregunta: desde fuera son el mismo caso a propósito, porque
     * distinguirlos ya confirma que el prospecto existe.
     */
    public function ver($id)
    {
        $sql = "SELECT p.*, u.nombre AS responsable
                  FROM lf_prospectos p
                  LEFT JOIN usuarios u ON u.id = p.responsable_id
                 WHERE p.id = :id"
             . $this->alcance->filtro('responsable_id', 'p');
        $st = $this->db->prepare($sql);
        $st->execute(['id' => (int)$id] + $this->alcance->parametros('responsable_id'));
        return $st->fetch() ?: null;
    }

    /** Las notas de seguimiento, lo más reciente arriba. */
    public function notas($id)
    {
        $st = $this->db->prepare(
            "SELECT n.*, u.nombre AS usuario
               FROM lf_prospecto_notas n
               LEFT JOIN usuarios u ON u.id = n.usuario_id
              WHERE n.prospecto_id = ?
              ORDER BY n.creado_en DESC, n.id DESC");
        $st->execute([(int)$id]);
        return $st->fetchAll();
    }

    /**
     * Alta. `responsable_id` puede venir vacío: es el prospecto que
     * entró por WhatsApp a las 11 de la noche y todavía no se reparte.
     */
    public function crear(array $d)
    {
        $st = $this->db->prepare(
            "INSERT INTO lf_prospectos
               (nombre, telefono, correo, origen, asunto, etapa, semaforo, valor,
                responsable_id, proximo_paso, proxima_fecha, creado_en)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $st->execute([
            trim((string)$d['nombre']),
            $this->oNull($d['telefono'] ?? null),
            $this->oNull($d['correo'] ?? null),
            $this->valido($d['origen'] ?? 'manual', self::ORIGENES, 'manual'),
            $this->oNull($d['asunto'] ?? null),
            $this->valido($d['etapa'] ?? 'nuevo', self::ETAPAS, 'nuevo'),
            $this->valido($d['semaforo'] ?? 'verde', self::SEMAFORO, 'verde'),
            (float)($d['valor'] ?? 0),
            !empty($d['responsable_id']) ? (int)$d['responsable_id'] : null,
            $this->oNull($d['proximo_paso'] ?? null),
            $this->oNull($d['proxima_fecha'] ?? null),
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Mover de etapa o de color.
     *
     * Comprueba el alcance ANTES de escribir: sin esto, un agente podría
     * mover el prospecto de otro mandando su id, aunque no pueda verlo.
     * Leer y escribir se protegen por separado.
     */
    public function mover($id, $etapa, $semaforo = null)
    {
        if (!$this->ver($id)) return false;

        $campos = ['etapa = ?'];
        $args   = [$this->valido($etapa, self::ETAPAS, 'nuevo')];
        if ($semaforo !== null) {
            $campos[] = 'semaforo = ?';
            $args[]   = $this->valido($semaforo, self::SEMAFORO, 'verde');
        }
        $args[] = (int)$id;

        $st = $this->db->prepare(
            'UPDATE lf_prospectos SET ' . implode(', ', $campos)
            . ', actualizado_en = NOW() WHERE id = ?');
        return $st->execute($args);
    }

    /** Anotar una llamada, un correo, un "quedó de avisar el martes". */
    public function anotar($id, $texto, $usuarioId, $tipo = 'nota')
    {
        if (!$this->ver($id)) return false;
        $st = $this->db->prepare(
            "INSERT INTO lf_prospecto_notas (prospecto_id, usuario_id, tipo, texto, creado_en)
             VALUES (?,?,?,?,NOW())");
        return $st->execute([(int)$id, $usuarioId ?: null, $tipo, trim((string)$texto)]);
    }

    /**
     * Ganado: nace el cliente y el prospecto guarda a quién dio origen.
     *
     * NO borra el prospecto. Su historia —de dónde llegó, cuántas veces
     * se le llamó, qué se le propuso— es lo único que deja saber después
     * por qué se cerró, y es lo primero que se pierde cuando se borra.
     */
    public function ganar($id, $clienteId)
    {
        if (!$this->ver($id)) return false;
        $st = $this->db->prepare(
            "UPDATE lf_prospectos
                SET etapa = 'ganado', cliente_id = ?, actualizado_en = NOW()
              WHERE id = ?");
        return $st->execute([(int)$clienteId, (int)$id]);
    }

    /** Perdido, con el motivo. Sin motivo no se aprende nada del embudo. */
    public function perder($id, $motivo)
    {
        if (!$this->ver($id)) return false;
        $st = $this->db->prepare(
            "UPDATE lf_prospectos
                SET etapa = 'perdido', semaforo = 'rojo',
                    motivo_cierre = ?, actualizado_en = NOW()
              WHERE id = ?");
        return $st->execute([trim((string)$motivo) ?: 'Sin motivo', (int)$id]);
    }

    /**
     * El reporte que pidió el director: cuántas cotizaciones se
     * concretaron esta semana, y cuánto se quedó en el camino.
     */
    public function resumen($desde, $hasta)
    {
        $sql = "SELECT etapa, COUNT(*) n, COALESCE(SUM(valor),0) monto
                  FROM lf_prospectos p
                 WHERE DATE(p.creado_en) BETWEEN :desde AND :hasta"
             . $this->alcance->filtro('responsable_id', 'p')
             . " GROUP BY etapa";
        $st = $this->db->prepare($sql);
        $st->execute(['desde' => $desde, 'hasta' => $hasta]
                     + $this->alcance->parametros('responsable_id'));

        $r = ['abiertos' => 0, 'ganados' => 0, 'perdidos' => 0, 'monto_ganado' => 0.0];
        foreach ($st->fetchAll() as $f) {
            if ($f['etapa'] === 'ganado') {
                $r['ganados'] = (int)$f['n'];
                $r['monto_ganado'] = (float)$f['monto'];
            } elseif ($f['etapa'] === 'perdido') {
                $r['perdidos'] = (int)$f['n'];
            } else {
                $r['abiertos'] += (int)$f['n'];
            }
        }
        return $r;
    }

    /**
     * Los que tienen el siguiente paso vencido o para hoy.
     *
     * Esto es lo que evita el problema de la junta: la cotización que
     * nadie volvió a tocar. Alimenta la campana y el aviso diario.
     */
    public function pendientes()
    {
        $sql = "SELECT p.*, u.nombre AS responsable
                  FROM lf_prospectos p
                  LEFT JOIN usuarios u ON u.id = p.responsable_id
                 WHERE p.etapa NOT IN ('ganado','perdido')
                   AND p.semaforo = 'verde'
                   AND p.proxima_fecha IS NOT NULL
                   AND p.proxima_fecha <= CURDATE()"
             . $this->alcance->filtro('responsable_id', 'p')
             . " ORDER BY p.proxima_fecha";
        $st = $this->db->prepare($sql);
        $st->execute($this->alcance->parametros('responsable_id'));
        return $st->fetchAll();
    }

    // ── Apoyos ──

    private function oNull($v)
    {
        $v = trim((string)$v);
        return $v === '' ? null : $v;
    }

    /** Un valor que no esté en el catálogo nunca entra a la base. */
    private function valido($v, array $catalogo, $porDefecto)
    {
        $v = (string)$v;
        return isset($catalogo[$v]) ? $v : $porDefecto;
    }
}
