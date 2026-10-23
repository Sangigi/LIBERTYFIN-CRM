<?php
namespace LibertyFin\Controlador;

use LibertyFin\Datos\Conexion;
use LibertyFin\Dominio\Alcance;
use LibertyFin\Dominio\Permisos;
use LibertyFin\Servicio\Modulos;
use PDO;

/**
 * La búsqueda de la paleta de comandos (Ctrl+K).
 *
 * UNA SOLA LLAMADA PARA TODO
 *
 * Se pregunta una vez y contesta con prospectos, clientes, asuntos y
 * pólizas a la vez. Hacer cuatro llamadas desde el navegador sería más
 * limpio de escribir y mucho peor de usar: cuatro viajes, cuatro
 * esperas, y los resultados llegando a destiempo y saltando mientras se
 * escribe.
 *
 * EL ALCANCE MANDA AQUÍ TAMBIÉN
 *
 * Es la trampa obvia de un buscador global: encontrar por el nombre lo
 * que no se puede abrir. Cada consulta lleva el filtro de Alcance, así
 * que un agente no ve en los resultados el cliente de otro.
 */
final class BuscarControlador
{
    /** Cuántos de cada tipo. Más de cinco y la lista deja de leerse. */
    const TOPE = 5;

    public function buscar()
    {
        header('Content-Type: application/json; charset=utf-8');

        $q = trim((string)($_GET['q'] ?? ''));
        // Con una letra, todo coincide con todo: no es una búsqueda, es
        // un volcado de la base ordenado por id.
        if (mb_strlen($q) < 2) { echo json_encode(['ok' => true, 'grupos' => []]); return; }

        $db  = Conexion::de($_SESSION['empresa_db']);
        $alc = Alcance::deSesion();
        $mod = Modulos::deSesion();
        $like = '%' . $q . '%';
        $grupos = [];

        if ($mod->activo('prospectos') && Permisos::puede('ver.prospectos')) {
            $grupos[] = ['titulo' => 'Prospectos', 'items' => $this->prospectos($db, $alc, $like)];
        }
        if (Permisos::puede('ver.clientes')) {
            $grupos[] = ['titulo' => 'Clientes', 'items' => $this->clientes($db, $alc, $like)];
        }
        if ($mod->activo('legal') && Permisos::puede('ver.legal')) {
            $grupos[] = ['titulo' => 'Asuntos', 'items' => $this->asuntos($db, $alc, $like)];
        }
        if ($mod->activo('seguros') && Permisos::puede('ver.seguros')) {
            $grupos[] = ['titulo' => 'Pólizas', 'items' => $this->polizas($db, $alc, $like)];
        }

        // Los grupos vacíos no se mandan: un encabezado sin nada debajo
        // ocupa sitio para decir que no hay nada.
        $grupos = array_values(array_filter($grupos, function ($g) { return !empty($g['items']); }));

        echo json_encode(['ok' => true, 'grupos' => $grupos], JSON_UNESCAPED_UNICODE);
    }

    private function prospectos(PDO $db, Alcance $a, $like)
    {
        $st = $db->prepare("
            SELECT id, nombre, asunto, etapa FROM lf_prospectos
             WHERE (nombre LIKE ? OR telefono LIKE ? OR correo LIKE ? OR asunto LIKE ?)"
             . $a->filtroPos('responsable_id') . "
             ORDER BY etapa IN ('ganado','perdido'), id DESC LIMIT " . self::TOPE);
        $st->execute(array_merge([$like, $like, $like, $like], $a->valores()));
        return array_map(function ($r) {
            return ['t' => $r['nombre'], 's' => $r['asunto'] ?: 'Prospecto',
                    'u' => '/prospectos/' . (int)$r['id'], 'i' => 'tablero'];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    private function clientes(PDO $db, Alcance $a, $like)
    {
        $st = $db->prepare("
            SELECT id, nombre, telefono FROM clientes
             WHERE (nombre LIKE ? OR telefono LIKE ? OR rfc LIKE ?)"
             . $a->filtroPos('responsable_id') . "
             ORDER BY nombre LIMIT " . self::TOPE);
        $st->execute(array_merge([$like, $like, $like], $a->valores()));
        return array_map(function ($r) {
            return ['t' => $r['nombre'], 's' => $r['telefono'] ?: 'Cliente',
                    'u' => '/clientes/' . (int)$r['id'], 'i' => 'cliente'];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    private function asuntos(PDO $db, Alcance $a, $like)
    {
        $st = $db->prepare("
            SELECT id, folio, titulo, materia FROM lf_asuntos
             WHERE (folio LIKE ? OR titulo LIKE ? OR expediente_num LIKE ?)"
             . $a->filtroPos('responsable_id') . "
             ORDER BY id DESC LIMIT " . self::TOPE);
        $st->execute(array_merge([$like, $like, $like], $a->valores()));
        return array_map(function ($r) {
            return ['t' => $r['titulo'], 's' => $r['folio'] . ' · ' . $r['materia'],
                    'u' => '/legal/' . (int)$r['id'], 'i' => 'libro'];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }

    private function polizas(PDO $db, Alcance $a, $like)
    {
        $st = $db->prepare("
            SELECT id, numero, aseguradora, ramo FROM lf_polizas
             WHERE (numero LIKE ? OR aseguradora LIKE ?)"
             . $a->filtroPos('responsable_id') . "
             ORDER BY fin_vigencia IS NULL, fin_vigencia LIMIT " . self::TOPE);
        $st->execute(array_merge([$like, $like], $a->valores()));
        return array_map(function ($r) {
            return ['t' => 'Póliza ' . $r['numero'], 's' => $r['aseguradora'] . ' · ' . $r['ramo'],
                    'u' => '/seguros/' . (int)$r['id'], 'i' => 'doc'];
        }, $st->fetchAll(PDO::FETCH_ASSOC));
    }
}
