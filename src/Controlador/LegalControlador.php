<?php
namespace LibertyFin\Controlador;

use LibertyFin\Datos\AsuntoRepo;
use LibertyFin\Datos\Conexion;
use LibertyFin\Dominio\Permisos;
use LibertyFin\Servicio\Auditoria;
use LibertyFin\Servicio\Modulos;
use LibertyFin\Vista\Plantilla;

/**
 * Despacho jurídico (módulo «legal»).
 *
 * El alcance por renglón lo aplica AsuntoRepo en el SQL, no este
 * controlador: un abogado de mercantil no alcanza el divorcio que lleva
 * otro ni escribiendo la URL a mano.
 */
final class LegalControlador
{
    public function index()
    {
        if (!Modulos::exigir('legal')) return;
        $db = Conexion::de($_SESSION['empresa_db']);
        $repo = new AsuntoRepo($db);

        $f = ['materia' => (string)($_GET['materia'] ?? ''), 'q' => (string)($_GET['q'] ?? '')];

        Plantilla::pagina('legal/index', [
            'titulo'      => 'Asuntos',
            'icono'       => 'libro',
            'subtitulo'   => 'Los expedientes del despacho',
            'tablero'     => $repo->tablero($f),
            'proximos'    => $repo->proximos(1),
            'filtros'     => $f,
            'materias'    => AsuntoRepo::MATERIAS,
            'semaforos'   => AsuntoRepo::SEMAFORO,
            'equipo'      => $this->equipo($db),
            'clientes'    => $this->clientes($db),
            'puedeEditar' => Permisos::puede('editar.legal'),
            'aviso'       => $_SESSION['lf_aviso'] ?? null,
        ]);
        unset($_SESSION['lf_aviso']);
    }

    public function ver($id)
    {
        if (!Modulos::exigir('legal')) return;
        $db = Conexion::de($_SESSION['empresa_db']);
        $repo = new AsuntoRepo($db);

        $a = $repo->ver($id);
        if (!$a) return $this->noExiste();

        Plantilla::pagina('legal/detalle', [
            'titulo'       => $a['folio'] . ' · ' . $a['titulo'],
            'icono'        => 'libro',
            'subtitulo'    => AsuntoRepo::MATERIAS[$a['materia']] ?? $a['materia'],
            'a'            => $a,
            'etapas'       => AsuntoRepo::etapasDe($a['materia']),
            'semaforos'    => AsuntoRepo::SEMAFORO,
            'actuaciones'  => $repo->actuaciones($id),
            'documentos'   => $repo->documentos($id),
            'puedeEditar'  => Permisos::puede('editar.legal'),
            'aviso'        => $_SESSION['lf_aviso'] ?? null,
        ]);
        unset($_SESSION['lf_aviso']);
    }

    public function guardar()
    {
        if (!Modulos::exigir('legal')) return;
        if (!$this->token()) return $this->volver('/legal', 'Sesión vencida.', 'error');

        $db = Conexion::de($_SESSION['empresa_db']);
        try {
            $resp = Permisos::puede('asignar.responsable')
                  ? (int)($_POST['responsable_id'] ?? 0)
                  : (int)($_SESSION['usuario_id'] ?? 0);

            $id = (new AsuntoRepo($db))->crear([
                'titulo'         => $_POST['titulo'] ?? '',
                'materia'        => $_POST['materia'] ?? 'civil',
                'cliente_id'     => $_POST['cliente_id'] ?? null,
                'juzgado'        => $_POST['juzgado'] ?? null,
                'expediente_num' => $_POST['expediente_num'] ?? null,
                'contraparte'    => $_POST['contraparte'] ?? null,
                'cuantia'        => $_POST['cuantia'] ?? 0,
                'responsable_id' => $resp,
                'proxima_fecha'  => $_POST['proxima_fecha'] ?? null,
                'proximo_paso'   => $_POST['proximo_paso'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->volver('/legal', $e->getMessage(), 'error');
        }

        Auditoria::anota('asunto.alta', 'lf_asuntos#' . $id, null,
                         ['titulo' => $_POST['titulo'] ?? ''], $db);
        $this->volver('/legal/' . $id, 'Asunto abierto.');
    }

    public function mover($id)
    {
        if (!Modulos::exigir('legal')) return;
        if (!$this->token()) return $this->volver('/legal', 'Sesión vencida.', 'error');

        $db = Conexion::de($_SESSION['empresa_db']);
        $ok = (new AsuntoRepo($db))->mover(
            $id, (string)($_POST['etapa'] ?? ''),
            ($_POST['semaforo'] ?? '') !== '' ? (string)$_POST['semaforo'] : null,
            (int)($_SESSION['usuario_id'] ?? 0));

        // mover() devuelve false tanto si el asunto no le toca como si la
        // etapa no es de su materia: poner «embargo» en un divorcio no es
        // un error de dedo, es un expediente que deja de significar algo.
        if (!$ok) return $this->volver('/legal/' . (int)$id, 'Esa etapa no corresponde a la materia.', 'error');

        Auditoria::anota('asunto.etapa', 'lf_asuntos#' . (int)$id, null,
                         ['etapa' => $_POST['etapa'] ?? ''], $db);
        $this->volver('/legal/' . (int)$id, 'Etapa actualizada.');
    }

    public function anotar($id)
    {
        if (!Modulos::exigir('legal')) return;
        $a = '/legal/' . (int)$id;
        if (!$this->token()) return $this->volver($a, 'Sesión vencida.', 'error');

        $texto = trim((string)($_POST['texto'] ?? ''));
        if ($texto === '') return $this->volver($a, 'La actuación está vacía.', 'error');

        $db = Conexion::de($_SESSION['empresa_db']);
        $ok = (new AsuntoRepo($db))->anotar(
            $id, $texto, (int)($_SESSION['usuario_id'] ?? 0),
            (string)($_POST['tipo'] ?? 'nota'),
            ($_POST['fecha'] ?? '') ?: null);

        if (!$ok) return $this->noExiste();
        $this->volver($a, 'Actuación registrada.');
    }

    /** Fijar audiencia, emplazamiento o lo que toque, con aviso previo. */
    public function fecha($id)
    {
        if (!Modulos::exigir('legal')) return;
        $a = '/legal/' . (int)$id;
        if (!$this->token()) return $this->volver($a, 'Sesión vencida.', 'error');

        $db = Conexion::de($_SESSION['empresa_db']);
        $ok = (new AsuntoRepo($db))->fijarFecha(
            $id, (string)($_POST['proxima_fecha'] ?? ''),
            (string)($_POST['proximo_paso'] ?? ''),
            (int)($_SESSION['usuario_id'] ?? 0));

        if (!$ok) return $this->noExiste();
        $this->volver($a, 'Fecha fijada. Se avisará el día anterior.');
    }

    public function documento($id)
    {
        if (!Modulos::exigir('legal')) return;
        $a = '/legal/' . (int)$id;
        if (!$this->token()) return $this->volver($a, 'Sesión vencida.', 'error');

        $db = Conexion::de($_SESSION['empresa_db']);
        $ok = (new AsuntoRepo($db))->documento(
            $id, (int)($_POST['doc_id'] ?? 0), !empty($_POST['recibido']));

        if (!$ok) return $this->noExiste();
        $this->volver($a, 'Documento actualizado.');
    }

    // ── Apoyos ──

    private function equipo($db)
    {
        $st = $db->query("SELECT id, nombre FROM usuarios
                           WHERE activo = 1 AND rol IN ('admin','agente') ORDER BY nombre");
        return $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    private function clientes($db)
    {
        $st = $db->query("SELECT id, nombre FROM clientes ORDER BY nombre LIMIT 500");
        return $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
    }

    private function token()
    {
        return !empty($_SESSION['lf_token']) && !empty($_POST['token'])
            && hash_equals($_SESSION['lf_token'], (string)$_POST['token']);
    }

    private function noExiste()
    {
        http_response_code(404);
        Plantilla::pagina('errores/404', ['titulo' => 'No encontrado', 'icono' => 'alerta', 'subtitulo' => '']);
    }

    private function volver($a, $msg, $tipo = 'ok')
    {
        $_SESSION['lf_aviso'] = ['texto' => $msg, 'tipo' => $tipo];
        header('Location: ' . $a);
        exit;
    }
}
