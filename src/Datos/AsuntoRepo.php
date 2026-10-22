<?php
namespace LibertyFin\Datos;

use LibertyFin\Dominio\Alcance;
use PDO;

/**
 * Los asuntos del despacho.
 *
 * UN ASUNTO NO ES UN CONTRATO
 *
 * El contrato dice cuánto se cobra; el asunto dice en qué va el juicio.
 * Un cliente puede tener tres asuntos bajo una sola iguala, y un asunto
 * puede durar dos años mientras los cobros van por su cuenta. Por eso
 * `venta_id` es opcional: el expediente se abre cuando el cliente trae
 * el problema, no cuando paga.
 *
 * LAS ETAPAS NO SON CONFIGURABLES, Y ES A PROPÓSITO
 *
 * Las de un divorcio las fija el código de procedimientos, no el
 * despacho. Dejarlas editables lleva a que cada abogado las escriba
 * distinto y a que después no se pueda comparar ni medir nada.
 */
final class AsuntoRepo
{
    /** Las materias, con su secuencia procesal. */
    const MATERIAS = [
        'familiar'  => 'Familiar',
        'mercantil' => 'Mercantil',
        'civil'     => 'Civil',
        'penal'     => 'Penal',
        'laboral'   => 'Laboral',
        'amparo'    => 'Amparo',
    ];

    /**
     * Las etapas de cada materia, en orden.
     *
     * Salen de la junta: un divorcio va demanda → auto admisorio →
     * emplazamiento → audiencias → sentencia; un mercantil mete embargo
     * y alegatos. Son distintas de verdad, y meterlas todas en un
     * «inicio / en proceso / terminado» es lo que hacía inútil al
     * sistema anterior.
     */
    const ETAPAS = [
        'familiar' => [
            'sin_iniciar' => 'Sin iniciar',
            'demanda'     => 'Presentación de demanda',
            'admisorio'   => 'Auto admisorio',
            'emplazamiento' => 'Emplazamiento',
            'audiencias'  => 'Audiencias',
            'sentencia'   => 'Sentencia',
            'ejecucion'   => 'Ejecución',
        ],
        'mercantil' => [
            'sin_iniciar' => 'Sin iniciar',
            'demanda'     => 'Presentación de demanda',
            'embargo'     => 'Embargo',
            'pruebas'     => 'Desahogo de pruebas',
            'alegatos'    => 'Alegatos',
            'sentencia'   => 'Sentencia',
            'ejecucion'   => 'Ejecución',
        ],
        'civil' => [
            'sin_iniciar' => 'Sin iniciar',
            'demanda'     => 'Presentación de demanda',
            'admisorio'   => 'Auto admisorio',
            'pruebas'     => 'Desahogo de pruebas',
            'alegatos'    => 'Alegatos',
            'sentencia'   => 'Sentencia',
        ],
        'penal' => [
            'sin_iniciar'  => 'Sin iniciar',
            'carpeta'      => 'Carpeta de investigación',
            'imputacion'   => 'Audiencia de imputación',
            'vinculacion'  => 'Vinculación a proceso',
            'intermedia'   => 'Etapa intermedia',
            'juicio_oral'  => 'Juicio oral',
            'sentencia'    => 'Sentencia',
        ],
        'laboral' => [
            'sin_iniciar'  => 'Sin iniciar',
            'conciliacion' => 'Conciliación prejudicial',
            'demanda'      => 'Presentación de demanda',
            'audiencia'    => 'Audiencia preliminar',
            'juicio'       => 'Audiencia de juicio',
            'laudo'        => 'Laudo',
        ],
        'amparo' => [
            'sin_iniciar'  => 'Sin iniciar',
            'demanda'      => 'Demanda de amparo',
            'suspension'   => 'Suspensión',
            'informes'     => 'Informes justificados',
            'sentencia'    => 'Sentencia',
            'revision'     => 'Recurso de revisión',
        ],
    ];

    /** Verde sigue, amarillo pausado, rojo detenido. Igual que el embudo. */
    const SEMAFORO = [
        'verde'    => 'En proceso',
        'amarillo' => 'Pausado',
        'rojo'     => 'Detenido',
    ];

    /**
     * Lo que hay que pedirle al cliente según el trámite.
     *
     * Sale directo de la junta. Es una plantilla: al abrir el asunto se
     * copian a `lf_asunto_documentos` y desde ahí cada despacho los
     * marca, quita o agrega. Si fueran fijos, el primer trámite raro
     * dejaría la lista inservible.
     */
    const DOCUMENTOS = [
        'familiar'  => ['Acta de nacimiento', 'INE del promovente',
                        'Acta de matrimonio', 'Actas de nacimiento de los hijos',
                        'Comprobante de domicilio'],
        'mercantil' => ['Pagaré o título de crédito', 'Contrato', 'Facturas',
                        'INE del representante', 'Acta constitutiva'],
        'civil'     => ['INE del promovente', 'Escrituras o contrato',
                        'Comprobante de domicilio'],
        'penal'     => ['INE', 'Denuncia o querella', 'Dictámenes periciales'],
        'laboral'   => ['INE', 'Contrato de trabajo', 'Recibos de nómina',
                        'Constancia de despido'],
        'amparo'    => ['INE', 'Acto reclamado', 'Constancia de notificación'],
    ];

    private $db;
    private $alcance;

    public function __construct(PDO $db, Alcance $alcance = null)
    {
        $this->db = $db;
        $this->alcance = $alcance ?: Alcance::deSesion();
    }

    /** Las etapas de una materia, o las civiles si la materia no existe. */
    public static function etapasDe($materia)
    {
        return self::ETAPAS[$materia] ?? self::ETAPAS['civil'];
    }

    /** El tablero: los asuntos abiertos, agrupados por materia. */
    public function tablero(array $f = [])
    {
        $w = ["a.etapa <> 'archivado'"]; $p = [];

        if (!empty($f['materia']) && isset(self::MATERIAS[$f['materia']])) {
            $w[] = 'a.materia = ?'; $p[] = $f['materia'];
        }
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $w[] = '(a.folio LIKE ? OR a.titulo LIKE ? OR c.nombre LIKE ? OR a.expediente_num LIKE ?)';
            $l = '%' . $q . '%'; $p[] = $l; $p[] = $l; $p[] = $l; $p[] = $l;
        }
        $alc = $this->alcance->filtroPos('responsable_id', 'a');
        if ($alc !== '') { $w[] = ltrim(substr($alc, 5)); $p[] = $this->alcance->valores()[0]; }

        $st = $this->db->prepare("
            SELECT a.*, c.nombre AS cliente, u.nombre AS responsable
              FROM lf_asuntos a
              LEFT JOIN clientes c ON c.id = a.cliente_id
              LEFT JOIN usuarios u ON u.id = a.responsable_id
             WHERE " . implode(' AND ', $w) . "
             ORDER BY FIELD(a.semaforo,'verde','amarillo','rojo'),
                      a.proxima_fecha IS NULL, a.proxima_fecha, a.id DESC
             LIMIT 400");
        $st->execute($p);

        $r = array_fill_keys(array_keys(self::MATERIAS), []);
        foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $a) {
            $m = isset($r[$a['materia']]) ? $a['materia'] : 'civil';
            $r[$m][] = $a;
        }
        return $r;
    }

    /** Uno. null si no existe o no le toca a quien pregunta. */
    public function ver($id)
    {
        $st = $this->db->prepare("
            SELECT a.*, c.nombre AS cliente, c.telefono, u.nombre AS responsable
              FROM lf_asuntos a
              LEFT JOIN clientes c ON c.id = a.cliente_id
              LEFT JOIN usuarios u ON u.id = a.responsable_id
             WHERE a.id = ?" . $this->alcance->filtroPos('responsable_id', 'a'));
        $st->execute(array_merge([(int)$id], $this->alcance->valores()));
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function actuaciones($id)
    {
        $st = $this->db->prepare("
            SELECT ac.*, u.nombre AS usuario
              FROM lf_asunto_actuaciones ac
              LEFT JOIN usuarios u ON u.id = ac.usuario_id
             WHERE ac.asunto_id = ?
             ORDER BY COALESCE(ac.fecha, DATE(ac.creado_en)) DESC, ac.id DESC");
        $st->execute([(int)$id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function documentos($id)
    {
        $st = $this->db->prepare(
            "SELECT * FROM lf_asunto_documentos WHERE asunto_id = ? ORDER BY recibido, id");
        $st->execute([(int)$id]);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Abrir un asunto. Copia la lista de documentos de su materia.
     *
     * Todo en una transacción: un asunto sin su lista de documentos es
     * peor que ningún asunto, porque nadie vuelve a revisarla.
     */
    public function crear(array $d)
    {
        $materia = isset(self::MATERIAS[$d['materia'] ?? '']) ? $d['materia'] : 'civil';
        $titulo  = trim((string)($d['titulo'] ?? ''));
        if ($titulo === '') throw new \InvalidArgumentException('Falta el título del asunto.');

        $this->db->beginTransaction();
        try {
            $st = $this->db->prepare("
                INSERT INTO lf_asuntos
                  (folio, cliente_id, venta_id, materia, titulo, etapa, semaforo,
                   juzgado, expediente_num, contraparte, cuantia, responsable_id,
                   proxima_fecha, proximo_paso, creado_en)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
            $st->execute([
                $this->folio($materia),
                !empty($d['cliente_id']) ? (int)$d['cliente_id'] : null,
                !empty($d['venta_id'])   ? (int)$d['venta_id']   : null,
                $materia,
                mb_substr($titulo, 0, 255),
                'sin_iniciar',
                'verde',
                $this->oNull($d['juzgado'] ?? null),
                $this->oNull($d['expediente_num'] ?? null),
                $this->oNull($d['contraparte'] ?? null),
                (float)($d['cuantia'] ?? 0),
                !empty($d['responsable_id']) ? (int)$d['responsable_id'] : null,
                $this->oNull($d['proxima_fecha'] ?? null),
                $this->oNull($d['proximo_paso'] ?? null),
            ]);
            $id = (int)$this->db->lastInsertId();

            $doc = $this->db->prepare(
                "INSERT INTO lf_asunto_documentos (asunto_id, nombre) VALUES (?,?)");
            foreach (self::DOCUMENTOS[$materia] ?? [] as $n) $doc->execute([$id, $n]);

            $this->db->commit();
            return $id;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Mover de etapa.
     *
     * La etapa se valida contra las de SU materia: poner «embargo» en un
     * divorcio no es un error de dedo, es un expediente que ya no
     * significa nada.
     *
     * Cada movimiento deja actuación. El expediente ES su historia, y
     * una etapa que cambia sin dejar rastro no deja reconstruir nada.
     */
    public function mover($id, $etapa, $semaforo = null, $usuarioId = null)
    {
        $a = $this->ver($id);
        if (!$a) return false;

        $etapas = self::etapasDe($a['materia']);
        if (!isset($etapas[$etapa])) return false;

        $campos = ['etapa = ?']; $args = [$etapa];
        if ($semaforo !== null && isset(self::SEMAFORO[$semaforo])) {
            $campos[] = 'semaforo = ?'; $args[] = $semaforo;
        }
        $args[] = (int)$id;

        $st = $this->db->prepare('UPDATE lf_asuntos SET ' . implode(', ', $campos)
                                 . ', actualizado_en = NOW() WHERE id = ?');
        $st->execute($args);

        if ($a['etapa'] !== $etapa) {
            $this->anotar($id, 'Pasó a: ' . $etapas[$etapa], $usuarioId, 'etapa', null, $etapa);
        }
        return true;
    }

    public function anotar($id, $texto, $usuarioId, $tipo = 'nota', $fecha = null, $etapa = null)
    {
        if (!$this->ver($id)) return false;
        $st = $this->db->prepare("
            INSERT INTO lf_asunto_actuaciones
              (asunto_id, usuario_id, etapa, tipo, texto, fecha, creado_en)
            VALUES (?,?,?,?,?,?,NOW())");
        return $st->execute([(int)$id, $usuarioId ?: null, $etapa,
                             $tipo, trim((string)$texto), $fecha ?: null]);
    }

    /** Marcar un documento como recibido, o deshacerlo. */
    public function documento($asuntoId, $docId, $recibido)
    {
        if (!$this->ver($asuntoId)) return false;
        $st = $this->db->prepare("
            UPDATE lf_asunto_documentos
               SET recibido = ?, recibido_en = CASE WHEN ? = 1 THEN NOW() ELSE NULL END
             WHERE id = ? AND asunto_id = ?");
        $r = $recibido ? 1 : 0;
        return $st->execute([$r, $r, (int)$docId, (int)$asuntoId]);
    }

    /**
     * La siguiente fecha: audiencia, emplazamiento, lo que toque.
     *
     * Alimenta el aviso del día anterior, que fue petición expresa de la
     * junta: un emplazamiento que se pasa no se recupera.
     */
    public function fijarFecha($id, $fecha, $paso, $usuarioId = null)
    {
        if (!$this->ver($id)) return false;
        $st = $this->db->prepare(
            "UPDATE lf_asuntos SET proxima_fecha = ?, proximo_paso = ?,
                    actualizado_en = NOW() WHERE id = ?");
        $ok = $st->execute([$fecha ?: null, mb_substr(trim((string)$paso), 0, 255) ?: null, (int)$id]);
        if ($ok && $fecha) {
            $this->anotar($id, 'Fecha fijada: ' . $paso . ' el ' . $fecha,
                          $usuarioId, 'fecha', $fecha);
        }
        return $ok;
    }

    /** Lo que vence hoy o antes, y lo de mañana. Para el aviso previo. */
    public function proximos($dias = 1)
    {
        $st = $this->db->prepare("
            SELECT a.*, c.nombre AS cliente, u.nombre AS responsable
              FROM lf_asuntos a
              LEFT JOIN clientes c ON c.id = a.cliente_id
              LEFT JOIN usuarios u ON u.id = a.responsable_id
             WHERE a.semaforo = 'verde'
               AND a.proxima_fecha IS NOT NULL
               AND a.proxima_fecha <= DATE_ADD(CURDATE(), INTERVAL ? DAY)"
             . $this->alcance->filtroPos('responsable_id', 'a') . "
             ORDER BY a.proxima_fecha");
        $st->execute(array_merge([(int)$dias], $this->alcance->valores()));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    // ── Apoyos ──

    /** Folio legible: MER-2026-0004. */
    private function folio($materia)
    {
        $pre = strtoupper(substr($materia, 0, 3)) . '-' . date('Y') . '-';
        $st = $this->db->prepare(
            "SELECT folio FROM lf_asuntos WHERE folio LIKE ? ORDER BY id DESC LIMIT 1");
        $st->execute([$pre . '%']);
        $ultimo = (string)$st->fetchColumn();
        $n = $ultimo ? (int)substr($ultimo, -4) + 1 : 1;
        return $pre . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
    }

    private function oNull($v)
    {
        $v = trim((string)$v);
        return $v === '' ? null : $v;
    }
}
