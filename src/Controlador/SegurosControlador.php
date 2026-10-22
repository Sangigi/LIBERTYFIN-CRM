<?php
namespace LibertyFin\Controlador;

use LibertyFin\Datos\Conexion;
use LibertyFin\Datos\PolizaRepo;
use LibertyFin\Dominio\Permisos;
use LibertyFin\Servicio\Auditoria;
use LibertyFin\Servicio\Modulos;
use LibertyFin\Vista\Plantilla;

/**
 * Agencia de seguros (módulo «seguros»).
 *
 * La pantalla se ordena por lo que vence, no por lo que se vendió: en
 * una agencia el cliente no se pierde cuando se va, se pierde cuando
 * nadie avisó que su póliza caducaba el 14.
 */
final class SegurosControlador
{
    public function index()
    {
        if (!Modulos::exigir('seguros')) return;
        $db = Conexion::de($_SESSION['empresa_db']);
        $repo = new PolizaRepo($db);

        $f = ['ramo'   => (string)($_GET['ramo'] ?? ''),
              'estado' => (string)($_GET['estado'] ?? ''),
              'q'      => (string)($_GET['q'] ?? '')];

        Plantilla::pagina('seguros/index', [
            'titulo'      => 'Pólizas',
            'icono'       => 'escudo',
            'subtitulo'   => 'La cartera de la agencia',
            'polizas'     => $repo->listado($f),
            'porVencer'   => $repo->porVencer(),
            'resumen'     => $repo->resumen(),
            'filtros'     => $f,
            'ramos'       => PolizaRepo::RAMOS,
            'estados'     => PolizaRepo::ESTADOS,
            'formasPago'  => PolizaRepo::FORMAS_PAGO,
            'equipo'      => $this->equipo($db),
            'clientes'    => $this->clientes($db),
            'puedeEditar' => Permisos::puede('editar.seguros'),
            'aviso'       => $_SESSION['lf_aviso'] ?? null,
        ]);
        unset($_SESSION['lf_aviso']);
    }

    public function ver($id)
    {
        if (!Modulos::exigir('seguros')) return;
        $db = Conexion::de($_SESSION['empresa_db']);
        $p = (new PolizaRepo($db))->ver($id);
        if (!$p) return $this->noExiste();

        Plantilla::pagina('seguros/detalle', [
            'titulo'      => 'Póliza ' . $p['numero'],
            'icono'       => 'escudo',
            'subtitulo'   => PolizaRepo::RAMOS[$p['ramo']] ?? $p['ramo'],
            'p'           => $p,
            'ramos'       => PolizaRepo::RAMOS,
            'estados'     => PolizaRepo::ESTADOS,
            'formasPago'  => PolizaRepo::FORMAS_PAGO,
            'puedeEditar' => Permisos::puede('editar.seguros'),
            'aviso'       => $_SESSION['lf_aviso'] ?? null,
        ]);
        unset($_SESSION['lf_aviso']);
    }

    public function guardar()
    {
        if (!Modulos::exigir('seguros')) return;
        if (!$this->token()) return $this->volver('/seguros', 'Sesión vencida.', 'error');

        $db = Conexion::de($_SESSION['empresa_db']);
        try {
            $resp = Permisos::puede('asignar.responsable')
                  ? (int)($_POST['responsable_id'] ?? 0)
                  : (int)($_SESSION['usuario_id'] ?? 0);

            $id = (new PolizaRepo($db))->crear([
                'numero'         => $_POST['numero'] ?? '',
                'aseguradora'    => $_POST['aseguradora'] ?? '',
                'ramo'           => $_POST['ramo'] ?? 'autos',
                'cliente_id'     => $_POST['cliente_id'] ?? null,
                'prima'          => $_POST['prima'] ?? 0,
                'suma_asegurada' => $_POST['suma_asegurada'] ?? 0,
                'comision_pct'   => $_POST['comision_pct'] ?? 0,
                'forma_pago'     => $_POST['forma_pago'] ?? 'anual',
                'inicio_vigencia'=> $_POST['inicio_vigencia'] ?? null,
                'fin_vigencia'   => $_POST['fin_vigencia'] ?? null,
                'responsable_id' => $resp,
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->volver('/seguros', $e->getMessage(), 'error');
        } catch (\PDOException $e) {
            // La póliza es única por (numero, aseguradora): capturarla
            // dos veces es captura duplicada, no una póliza nueva.
            if ($e->getCode() === '23000') {
                return $this->volver('/seguros', 'Esa póliza ya está registrada con esa aseguradora.', 'error');
            }
            throw $e;
        }

        Auditoria::anota('poliza.alta', 'lf_polizas#' . $id, null,
                         ['numero' => $_POST['numero'] ?? ''], $db);
        $this->volver('/seguros/' . $id, 'Póliza registrada.');
    }

    /** Renovar: nace una nueva y la vieja queda como renovada. */
    public function renovar($id)
    {
        if (!Modulos::exigir('seguros')) return;
        if (!$this->token()) return $this->volver('/seguros', 'Sesión vencida.', 'error');

        $db = Conexion::de($_SESSION['empresa_db']);
        $nueva = (new PolizaRepo($db))->renovar($id, [
            'numero'         => $_POST['numero'] ?? null,
            'prima'          => $_POST['prima'] ?? null,
            'inicio_vigencia'=> $_POST['inicio_vigencia'] ?? null,
            'fin_vigencia'   => $_POST['fin_vigencia'] ?? null,
        ]);
        if (!$nueva) return $this->noExiste();

        Auditoria::anota('poliza.renovar', 'lf_polizas#' . (int)$id, null,
                         ['nueva' => $nueva], $db);
        $this->volver('/seguros/' . $nueva, 'Póliza renovada. La anterior queda en el historial.');
    }

    public function estado($id)
    {
        if (!Modulos::exigir('seguros')) return;
        $a = '/seguros/' . (int)$id;
        if (!$this->token()) return $this->volver($a, 'Sesión vencida.', 'error');

        $db = Conexion::de($_SESSION['empresa_db']);
        $ok = (new PolizaRepo($db))->cambiarEstado(
            $id, (string)($_POST['estado'] ?? ''), $_POST['nota'] ?? null);
        if (!$ok) return $this->volver($a, 'Ese estado no existe.', 'error');

        Auditoria::anota('poliza.estado', 'lf_polizas#' . (int)$id, null,
                         ['estado' => $_POST['estado'] ?? ''], $db);
        $this->volver($a, 'Estado actualizado.');
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
        Plantilla::pagina('errores/404', ['titulo' => 'No encontrada', 'icono' => 'alerta', 'subtitulo' => '']);
    }

    private function volver($a, $msg, $tipo = 'ok')
    {
        $_SESSION['lf_aviso'] = ['texto' => $msg, 'tipo' => $tipo];
        header('Location: ' . $a);
        exit;
    }
}
