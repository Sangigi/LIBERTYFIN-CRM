<?php
namespace LibertyFin\Controlador;

use LibertyFin\Datos\Conexion;
use LibertyFin\Datos\ProspectoRepo;
use LibertyFin\Dominio\Permisos;
use LibertyFin\Servicio\Auditoria;
use LibertyFin\Servicio\Modulos;
use LibertyFin\Vista\Plantilla;

/**
 * El embudo (módulo «prospectos»).
 *
 * Quién preguntó, en qué quedó y qué sigue. Resuelve el problema que
 * salió en la junta: la cotización que se manda y nadie vuelve a tocar.
 *
 * EL ALCANCE NO SE APLICA AQUÍ
 *
 * Lo aplica ProspectoRepo, dentro del SQL. Filtrar en el controlador
 * dejaría viva cualquier otra entrada a los mismos datos —la API, un
 * reporte, un trabajo de fondo— y bastaría una que se olvide.
 */
final class ProspectosControlador
{
    public function index()
    {
        if (!Modulos::exigir('prospectos')) return;
        $db   = Conexion::de($_SESSION['empresa_db']);
        $repo = new ProspectoRepo($db);

        Plantilla::pagina('prospectos/index', [
            'titulo'       => 'Prospectos',
            'icono'        => 'tablero',
            'subtitulo'    => 'Quién preguntó y qué sigue',
            'tablero'      => $repo->tablero(),
            'pendientes'   => $repo->pendientes(),
            'resumen'      => $repo->resumen(date('Y-m-d', strtotime('monday this week')), date('Y-m-d')),
            'etapas'       => $repo->etapas(),
            'colores'      => $repo->coloresEtapa(),
            'semaforos'    => ProspectoRepo::SEMAFORO,
            'origenes'     => ProspectoRepo::ORIGENES,
            'equipo'       => $this->equipo($db),
            'puedeEditar'  => Permisos::puede('editar.prospectos'),
            'puedeAsignar' => Permisos::puede('asignar.responsable'),
            'aviso'        => $_SESSION['lf_aviso'] ?? null,
        ]);
        unset($_SESSION['lf_aviso']);
    }

    public function ver($id)
    {
        if (!Modulos::exigir('prospectos')) return;
        $db   = Conexion::de($_SESSION['empresa_db']);
        $repo = new ProspectoRepo($db);

        // null = no existe, O no le toca a quien pregunta. Desde fuera
        // son el mismo caso a propósito: un 403 ya confirmaría que el
        // prospecto existe, y saber que «Constructora Vega» preguntó por
        // un divorcio es información que no le toca a quien no lo lleva.
        $p = $repo->ver($id);
        if (!$p) return $this->noExiste();

        Plantilla::pagina('prospectos/detalle', [
            'titulo'      => $p['nombre'],
            'icono'       => 'tablero',
            'subtitulo'   => 'Prospecto',
            'p'           => $p,
            'notas'       => $repo->notas($id),
            'etapas'      => $repo->etapas(),
            'semaforos'   => ProspectoRepo::SEMAFORO,
            'equipo'      => $this->equipo($db),
            'puedeEditar' => Permisos::puede('editar.prospectos'),
            'aviso'       => $_SESSION['lf_aviso'] ?? null,
        ]);
        unset($_SESSION['lf_aviso']);
    }

    /**
     * La ficha en JSON, para el panel lateral del tablero.
     *
     * En Kommo abrir un lead no te saca del embudo: se despliega a la
     * derecha y sigues viendo el tablero detrás. Perder el tablero para
     * leer una nota y tener que volver rompe el ritmo de trabajo, que es
     * justo lo que un embudo existe para sostener.
     */
    public function ficha($id)
    {
        if (!Modulos::exigir('prospectos')) return;
        $db   = Conexion::de($_SESSION['empresa_db']);
        $repo = new ProspectoRepo($db);

        $p = $repo->ver($id);
        if (!$p) {
            http_response_code(404);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
            return;
        }

        $notas = [];
        foreach ($repo->notas($id) as $n) {
            $notas[] = [
                'texto'   => $n['texto'],
                'tipo'    => $n['tipo'],
                'usuario' => $n['usuario'] ?: 'sistema',
                'cuando'  => date('d/m/Y H:i', strtotime($n['creado_en'])),
            ];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'ok' => true,
            'p'  => [
                'id'            => (int)$p['id'],
                'nombre'        => $p['nombre'],
                'telefono'      => $p['telefono'],
                'correo'        => $p['correo'],
                'asunto'        => $p['asunto'],
                'valor'         => (float)$p['valor'],
                'origen'        => ProspectoRepo::ORIGENES[$p['origen']] ?? $p['origen'],
                'origen_clave'  => $p['origen'],
                'etapa'         => $p['etapa'],
                'semaforo'      => $p['semaforo'],
                'responsable'   => $p['responsable'],
                'proximo_paso'  => $p['proximo_paso'],
                'proxima_fecha' => $p['proxima_fecha'],
                'creado'        => date('d/m/Y', strtotime($p['creado_en'])),
            ],
            'notas'     => $notas,
            'etapas'    => $repo->etapas(),
            'semaforos' => ProspectoRepo::SEMAFORO,
            'editar'    => Permisos::puede('editar.prospectos'),
        ], JSON_UNESCAPED_UNICODE);
    }

    /** Fijar el siguiente paso desde el panel lateral. */
    public function tarea($id)
    {
        if (!Modulos::exigir('prospectos')) return;
        $json = $this->pideJson();
        if (!$this->tokenValido()) return $this->responder($json, '/prospectos', 'Sesión vencida.', 'error');

        $db   = Conexion::de($_SESSION['empresa_db']);
        $repo = new ProspectoRepo($db);
        if (!$repo->ver($id)) return $this->noExiste($json);

        $repo->fijarPaso($id, (string)($_POST['proximo_paso'] ?? ''),
                              (string)($_POST['proxima_fecha'] ?? ''));
        $this->responder($json, '/prospectos/' . (int)$id, 'Siguiente paso fijado.', 'ok');
    }

    public function guardar()
    {
        if (!Modulos::exigir('prospectos')) return;
        if (!$this->tokenValido()) return $this->volver('/prospectos', 'Sesión vencida. Inténtalo otra vez.', 'error');

        $db   = Conexion::de($_SESSION['empresa_db']);
        $repo = new ProspectoRepo($db);

        $nombre = trim((string)($_POST['nombre'] ?? ''));
        if ($nombre === '') return $this->volver('/prospectos', 'Falta el nombre.', 'error');

        // Sin permiso para asignar, el prospecto nace de quien lo captura.
        // Si no, un agente podría encajarle prospectos a otro.
        $resp = Permisos::puede('asignar.responsable')
              ? (int)($_POST['responsable_id'] ?? 0)
              : (int)($_SESSION['usuario_id'] ?? 0);

        $id = $repo->crear([
            'nombre'         => $nombre,
            'telefono'       => $_POST['telefono'] ?? null,
            'correo'         => $_POST['correo'] ?? null,
            'origen'         => $_POST['origen'] ?? 'manual',
            'asunto'         => $_POST['asunto'] ?? null,
            'valor'          => $_POST['valor'] ?? 0,
            'responsable_id' => $resp,
            'proximo_paso'   => $_POST['proximo_paso'] ?? null,
            'proxima_fecha'  => $_POST['proxima_fecha'] ?? null,
        ]);

        Auditoria::anota('prospecto.alta', 'lf_prospectos#' . $id, null,
                         ['nombre' => $nombre], $db);
        $this->volver('/prospectos/' . $id, 'Prospecto registrado.');
    }

    /** Mover de etapa o de color desde el tablero. */
    public function mover($id)
    {
        if (!Modulos::exigir('prospectos')) return;
        $json = $this->pideJson();
        if (!$this->tokenValido()) return $this->responder($json, '/prospectos', 'Sesión vencida.', 'error');

        $db   = Conexion::de($_SESSION['empresa_db']);
        $repo = new ProspectoRepo($db);

        $etapa = (string)($_POST['etapa'] ?? '');
        $sem   = isset($_POST['semaforo']) && $_POST['semaforo'] !== '' ? (string)$_POST['semaforo'] : null;

        // mover() comprueba el alcance ANTES de escribir: sin eso, un
        // agente podría mover el prospecto de otro mandando su id.
        if (!$repo->mover($id, $etapa, $sem)) return $this->noExiste($json);

        Auditoria::anota('prospecto.mover', 'lf_prospectos#' . (int)$id, null,
                         ['etapa' => $etapa, 'semaforo' => $sem], $db);
        $this->responder($json, '/prospectos', 'Actualizado.', 'ok');
    }

    public function anotar($id)
    {
        if (!Modulos::exigir('prospectos')) return;
        $a = '/prospectos/' . (int)$id;
        // El panel lateral anota sin recargar, así que esta ruta tiene que
        // saber contestar JSON: una redirección obligaría al navegador a
        // traerse la página entera para tirarla.
        $json = $this->pideJson();
        if (!$this->tokenValido()) return $this->responder($json, $a, 'Sesión vencida.', 'error');

        $texto = trim((string)($_POST['texto'] ?? ''));
        if ($texto === '') return $this->responder($json, $a, 'La nota está vacía.', 'error');

        $db = Conexion::de($_SESSION['empresa_db']);
        $ok = (new ProspectoRepo($db))->anotar(
            $id, $texto, (int)($_SESSION['usuario_id'] ?? 0),
            (string)($_POST['tipo'] ?? 'nota'));

        if (!$ok) return $this->noExiste($json);
        $this->responder($json, $a, 'Anotado.', 'ok');
    }

    /**
     * Perdido, con el motivo.
     *
     * El motivo se pide siempre. Un embudo donde la mitad se pierde «sin
     * motivo» no deja mejorar nada, y era justo la queja de la junta:
     * cotizaciones que se abandonan sin que nadie sepa por qué.
     */
    public function perder($id)
    {
        if (!Modulos::exigir('prospectos')) return;
        if (!$this->tokenValido()) return $this->volver('/prospectos', 'Sesión vencida.', 'error');

        $db     = Conexion::de($_SESSION['empresa_db']);
        $motivo = (string)($_POST['motivo'] ?? '');
        if (!(new ProspectoRepo($db))->perder($id, $motivo)) return $this->noExiste();

        Auditoria::anota('prospecto.perder', 'lf_prospectos#' . (int)$id, null,
                         ['motivo' => $motivo], $db);
        $this->volver('/prospectos', 'Marcado como perdido.');
    }

    // ── Apoyos ──

    /** A quién se le puede asignar: los de la empresa que atienden. */
    private function equipo($db)
    {
        $st = $db->query("SELECT id, nombre, rol FROM usuarios
                           WHERE activo = 1 AND rol IN ('admin','agente')
                           ORDER BY nombre");
        return $st ? $st->fetchAll() : [];
    }

    private function pideJson()
    {
        return ($_SERVER['HTTP_X_LF_JSON'] ?? '') === '1'
            || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
    }

    private function tokenValido()
    {
        return !empty($_SESSION['lf_token']) && !empty($_POST['token'])
            && hash_equals($_SESSION['lf_token'], (string)$_POST['token']);
    }

    private function noExiste($json = false)
    {
        http_response_code(404);
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'No encontrado.'], JSON_UNESCAPED_UNICODE);
            exit;
        }
        Plantilla::pagina('errores/404', ['titulo' => 'No encontrado', 'icono' => 'alerta', 'subtitulo' => '']);
    }

    private function responder($json, $a, $texto, $tipo)
    {
        if ($json) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => $tipo === 'ok', 'texto' => $texto], JSON_UNESCAPED_UNICODE);
            exit;
        }
        $this->volver($a, $texto, $tipo);
    }

    private function volver($a, $msg, $tipo = 'ok')
    {
        $_SESSION['lf_aviso'] = ['texto' => $msg, 'tipo' => $tipo];
        header('Location: ' . $a);
        exit;
    }
}
