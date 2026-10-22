<?php
namespace LibertyFin\Datos;

use LibertyFin\Dominio\Alcance;
use PDO;

/**
 * Las pólizas de la agencia.
 *
 * LO QUE HACE DISTINTA A UNA PÓLIZA: CADUCA
 *
 * Un despacho pierde un cliente cuando se va. Una agencia lo pierde
 * cuando nadie avisó que vencía el 14. Por eso la consulta que de verdad
 * importa aquí no es «cuántas pólizas tengo» sino «cuáles vencen en los
 * próximos treinta días», y `fin_vigencia` lleva índice por eso.
 *
 * LA RENOVACIÓN NO SOBRESCRIBE
 *
 * Renovar crea una póliza NUEVA que apunta a la anterior con
 * `renovada_de`. Sobrescribir la vieja borraría el historial del
 * cliente, que es justo lo que se consulta cuando hay un siniestro de
 * un año anterior.
 */
final class PolizaRepo
{
    const RAMOS = [
        'autos'      => 'Autos',
        'vida'       => 'Vida',
        'gmm'        => 'Gastos médicos mayores',
        'daños'      => 'Daños y hogar',
        'empresas'   => 'Empresarial',
        'fianzas'    => 'Fianzas',
    ];

    const ESTADOS = [
        'cotizacion' => 'En cotización',
        'vigente'    => 'Vigente',
        'por_vencer' => 'Por vencer',
        'vencida'    => 'Vencida',
        'renovada'   => 'Renovada',
        'cancelada'  => 'Cancelada',
    ];

    const FORMAS_PAGO = [
        'anual'      => 'Anual',
        'semestral'  => 'Semestral',
        'trimestral' => 'Trimestral',
        'mensual'    => 'Mensual',
    ];

    /**
     * Cuántos días antes empieza a avisar.
     *
     * Treinta: menos no alcanza para cotizar con otra aseguradora si la
     * renovación sube, y más satura el tablero con cosas que todavía no
     * se pueden trabajar.
     */
    const DIAS_AVISO = 30;

    private $db;
    private $alcance;

    public function __construct(PDO $db, Alcance $alcance = null)
    {
        $this->db = $db;
        $this->alcance = $alcance ?: Alcance::deSesion();
    }

    public function listado(array $f = [])
    {
        $w = ['1 = 1']; $p = [];

        if (!empty($f['ramo']) && isset(self::RAMOS[$f['ramo']])) {
            $w[] = 'p.ramo = ?'; $p[] = $f['ramo'];
        }
        if (!empty($f['estado']) && isset(self::ESTADOS[$f['estado']])) {
            $w[] = 'p.estado = ?'; $p[] = $f['estado'];
        }
        $q = trim((string)($f['q'] ?? ''));
        if ($q !== '') {
            $w[] = '(p.numero LIKE ? OR p.aseguradora LIKE ? OR c.nombre LIKE ?)';
            $l = '%' . $q . '%'; $p[] = $l; $p[] = $l; $p[] = $l;
        }
        $alc = $this->alcance->filtroPos('responsable_id', 'p');
        if ($alc !== '') { $w[] = ltrim(substr($alc, 5)); $p[] = $this->alcance->valores()[0]; }

        $st = $this->db->prepare("
            SELECT p.*, c.nombre AS cliente, u.nombre AS responsable,
                   DATEDIFF(p.fin_vigencia, CURDATE()) AS dias
              FROM lf_polizas p
              LEFT JOIN clientes c ON c.id = p.cliente_id
              LEFT JOIN usuarios u ON u.id = p.responsable_id
             WHERE " . implode(' AND ', $w) . "
             ORDER BY p.fin_vigencia IS NULL, p.fin_vigencia
             LIMIT 400");
        $st->execute($p);
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    public function ver($id)
    {
        $st = $this->db->prepare("
            SELECT p.*, c.nombre AS cliente, c.telefono, u.nombre AS responsable,
                   DATEDIFF(p.fin_vigencia, CURDATE()) AS dias
              FROM lf_polizas p
              LEFT JOIN clientes c ON c.id = p.cliente_id
              LEFT JOIN usuarios u ON u.id = p.responsable_id
             WHERE p.id = ?" . $this->alcance->filtroPos('responsable_id', 'p'));
        $st->execute(array_merge([(int)$id], $this->alcance->valores()));
        return $st->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Las que vencen pronto. La pantalla que justifica el módulo.
     *
     * Trae también las ya vencidas: una póliza que caducó ayer todavía
     * se puede rehacer, y esconderla es perder al cliente por no
     * mirarla.
     */
    public function porVencer($dias = self::DIAS_AVISO)
    {
        $st = $this->db->prepare("
            SELECT p.*, c.nombre AS cliente, c.telefono, u.nombre AS responsable,
                   DATEDIFF(p.fin_vigencia, CURDATE()) AS dias
              FROM lf_polizas p
              LEFT JOIN clientes c ON c.id = p.cliente_id
              LEFT JOIN usuarios u ON u.id = p.responsable_id
             WHERE p.estado IN ('vigente','por_vencer','vencida')
               AND p.fin_vigencia IS NOT NULL
               AND p.fin_vigencia <= DATE_ADD(CURDATE(), INTERVAL ? DAY)"
             . $this->alcance->filtroPos('responsable_id', 'p') . "
             ORDER BY p.fin_vigencia");
        $st->execute(array_merge([(int)$dias], $this->alcance->valores()));
        return $st->fetchAll(PDO::FETCH_ASSOC);
    }

    /** Lo del encabezado: cuántas, cuánto valen y cuánto se comisiona. */
    public function resumen()
    {
        $st = $this->db->prepare("
            SELECT COUNT(*) AS n,
                   COALESCE(SUM(p.prima),0) AS prima,
                   COALESCE(SUM(p.prima * p.comision_pct / 100),0) AS comision,
                   COALESCE(SUM(CASE WHEN p.fin_vigencia IS NOT NULL
                                      AND p.fin_vigencia <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                                     THEN 1 ELSE 0 END),0) AS por_vencer
              FROM lf_polizas p
             WHERE p.estado IN ('vigente','por_vencer')"
             . $this->alcance->filtroPos('responsable_id', 'p'));
        $st->execute(array_merge([self::DIAS_AVISO], $this->alcance->valores()));
        return $st->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    public function crear(array $d)
    {
        $numero = trim((string)($d['numero'] ?? ''));
        if ($numero === '') throw new \InvalidArgumentException('Falta el número de póliza.');
        $aseg = trim((string)($d['aseguradora'] ?? ''));
        if ($aseg === '') throw new \InvalidArgumentException('Falta la aseguradora.');

        $st = $this->db->prepare("
            INSERT INTO lf_polizas
              (numero, cliente_id, venta_id, ramo, aseguradora, prima, suma_asegurada,
               comision_pct, forma_pago, inicio_vigencia, fin_vigencia, estado,
               responsable_id, renovada_de, nota, creado_en)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())");
        $st->execute([
            mb_substr($numero, 0, 60),
            !empty($d['cliente_id']) ? (int)$d['cliente_id'] : null,
            !empty($d['venta_id'])   ? (int)$d['venta_id']   : null,
            isset(self::RAMOS[$d['ramo'] ?? '']) ? $d['ramo'] : 'autos',
            mb_substr($aseg, 0, 120),
            (float)($d['prima'] ?? 0),
            (float)($d['suma_asegurada'] ?? 0),
            (float)($d['comision_pct'] ?? 0),
            isset(self::FORMAS_PAGO[$d['forma_pago'] ?? '']) ? $d['forma_pago'] : 'anual',
            $this->oNull($d['inicio_vigencia'] ?? null),
            $this->oNull($d['fin_vigencia'] ?? null),
            isset(self::ESTADOS[$d['estado'] ?? '']) ? $d['estado'] : 'vigente',
            !empty($d['responsable_id']) ? (int)$d['responsable_id'] : null,
            !empty($d['renovada_de']) ? (int)$d['renovada_de'] : null,
            $this->oNull($d['nota'] ?? null),
        ]);
        return (int)$this->db->lastInsertId();
    }

    /**
     * Renovar: NACE una póliza nueva, la vieja queda como 'renovada'.
     *
     * No se sobrescribe la anterior. Si se sobrescribiera, el día que
     * llegue un siniestro del año pasado no habría contra qué cotejarlo.
     */
    public function renovar($id, array $d)
    {
        $vieja = $this->ver($id);
        if (!$vieja) return 0;

        $this->db->beginTransaction();
        try {
            $nueva = $this->crear(array_merge([
                'numero'         => $vieja['numero'],
                'cliente_id'     => $vieja['cliente_id'],
                'ramo'           => $vieja['ramo'],
                'aseguradora'    => $vieja['aseguradora'],
                'prima'          => $vieja['prima'],
                'suma_asegurada' => $vieja['suma_asegurada'],
                'comision_pct'   => $vieja['comision_pct'],
                'forma_pago'     => $vieja['forma_pago'],
                'responsable_id' => $vieja['responsable_id'],
                'estado'         => 'vigente',
                'renovada_de'    => (int)$id,
            ], array_filter($d, function ($v) { return $v !== null && $v !== ''; })));

            $this->db->prepare("UPDATE lf_polizas SET estado = 'renovada',
                                actualizado_en = NOW() WHERE id = ?")->execute([(int)$id]);
            $this->db->commit();
            return $nueva;
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function cambiarEstado($id, $estado, $nota = null)
    {
        if (!$this->ver($id)) return false;
        if (!isset(self::ESTADOS[$estado])) return false;
        $st = $this->db->prepare(
            "UPDATE lf_polizas SET estado = ?, nota = COALESCE(?, nota),
                    actualizado_en = NOW() WHERE id = ?");
        return $st->execute([$estado, $this->oNull($nota), (int)$id]);
    }

    /**
     * Marca como 'por_vencer' o 'vencida' lo que corresponda.
     *
     * Se recalcula en vez de guardarse al crear, porque el estado
     * depende de la fecha de HOY: una póliza guardada como «vigente» en
     * enero sigue diciendo vigente en diciembre si nadie la toca.
     * Pensado para correr desde el cron diario.
     */
    public function actualizarVencimientos()
    {
        $a = $this->db->prepare("
            UPDATE lf_polizas SET estado = 'por_vencer', actualizado_en = NOW()
             WHERE estado = 'vigente' AND fin_vigencia IS NOT NULL
               AND fin_vigencia > CURDATE()
               AND fin_vigencia <= DATE_ADD(CURDATE(), INTERVAL ? DAY)");
        $a->execute([self::DIAS_AVISO]);

        $b = $this->db->prepare("
            UPDATE lf_polizas SET estado = 'vencida', actualizado_en = NOW()
             WHERE estado IN ('vigente','por_vencer')
               AND fin_vigencia IS NOT NULL AND fin_vigencia < CURDATE()");
        $b->execute();

        return $a->rowCount() + $b->rowCount();
    }

    private function oNull($v)
    {
        $v = trim((string)$v);
        return $v === '' ? null : $v;
    }
}
