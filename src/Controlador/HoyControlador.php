<?php
namespace LibertyFin\Controlador;

use LibertyFin\Datos\Conexion;
use LibertyFin\Datos\ProspectoRepo;
use LibertyFin\Dominio\Permisos;
use LibertyFin\Servicio\Modulos;
use LibertyFin\Vista\Plantilla;

/**
 * Hoy: la portada.
 *
 * POR QUÉ NO ES UN PANEL DE CIFRAS
 *
 * Los CRM del mercado abren con un banner que te vende lo que ya
 * compraste, un contador de prueba y unos tutoriales. Esa pantalla no
 * contesta la única pregunta con la que alguien abre su CRM a las nueve
 * de la mañana: ¿a quién le hablo hoy?
 *
 * Aquí lo primero es el embudo con su forma real, y debajo lo que se va
 * a caer si nadie lo toca, ordenado por cuánto lleva esperando. Si no
 * hay nada, lo dice y se calla.
 *
 * TODO SALE DE CONSULTAS QUE YA EXISTÍAN
 *
 * No hay tablas nuevas ni cálculos a medida. Es el mismo embudo mirado
 * desde otro sitio, y por eso no puede desincronizarse con el tablero.
 */
final class HoyControlador
{
    public function index()
    {
        // La configuración inicial va antes que nada.
        if (!empty($_SESSION['lf_bienvenida'])) { header('Location: /bienvenida'); exit; }

        $db   = Conexion::de($_SESSION['empresa_db']);
        $mod  = Modulos::deSesion();

        // Sin el módulo de prospectos esta pantalla no tiene qué
        // contar: el panel de cifras sigue siendo mejor portada.
        if (!$mod->activo('prospectos') || !Permisos::puede('ver.prospectos')) {
            header('Location: /panel?cifras=1'); exit;
        }

        $repo = new ProspectoRepo($db);

        $porEtapa = $repo->porEtapa();
        $enJuego  = 0.0;
        foreach ($porEtapa as $e) $enJuego += $e['monto'];

        Plantilla::pagina('hoy/index', [
            'titulo'      => $this->saludo(),
            'icono'       => 'panel',
            'subtitulo'   => $this->subtitulo(count($repo->pendientes())),
            'porEtapa'    => $porEtapa,
            'etapas'      => $repo->etapas(),
            'colores'     => $repo->coloresEtapa(),
            'enJuego'     => $enJuego,
            'esperan'     => $repo->pendientes(),
            'origenes'    => $repo->porOrigen(7),
            'catOrigenes' => ProspectoRepo::ORIGENES,
            'resumen'     => $repo->resumen(date('Y-m-d', strtotime('monday this week')), date('Y-m-d')),
            'citas'       => $this->citasDeHoy($db, $mod),
            'aviso'       => $_SESSION['lf_aviso'] ?? null,
        ]);
        unset($_SESSION['lf_aviso']);
    }

    /**
     * El saludo cambia con la hora.
     *
     * Cuesta tres líneas y es lo que hace que la pantalla parezca tuya
     * y no una plantilla. Se usa el primer nombre: «Buenas tardes,
     * Leonel Almanza Medina» suena a carta del banco.
     */
    private function saludo()
    {
        $h = (int)date('G');
        $momento = $h < 12 ? 'Buenos días' : ($h < 19 ? 'Buenas tardes' : 'Buenas noches');
        $nombre = trim((string)($_SESSION['usuario_nombre'] ?? ''));
        $pila = $nombre !== '' ? explode(' ', $nombre)[0] : '';
        return $pila !== '' ? $momento . ', ' . $pila : $momento;
    }

    private function subtitulo($cuantos)
    {
        $dias  = ['domingo','lunes','martes','miércoles','jueves','viernes','sábado'];
        $meses = ['','enero','febrero','marzo','abril','mayo','junio','julio',
                  'agosto','septiembre','octubre','noviembre','diciembre'];
        $fecha = ucfirst($dias[(int)date('w')]) . ' ' . (int)date('j')
               . ' de ' . $meses[(int)date('n')];

        if ($cuantos === 0) return $fecha . ' · nada pendiente';
        return $fecha . ' · ' . $cuantos
             . ($cuantos === 1 ? ' cosa te está esperando' : ' cosas te están esperando');
    }

    /** Las citas de hoy, si la agenda está encendida. */
    private function citasDeHoy($db, Modulos $mod)
    {
        if (!$mod->activo('agenda') || !Permisos::puede('ver.agenda')) return [];
        try {
            return (new \LibertyFin\Servicio\Agenda($db))->dia(date('Y-m-d'));
        } catch (\Throwable $e) {
            // Una agenda que falla no debe tumbar la portada entera.
            error_log('[hoy] agenda: ' . $e->getMessage());
            return [];
        }
    }
}
