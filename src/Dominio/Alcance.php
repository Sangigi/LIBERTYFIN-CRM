<?php
namespace LibertyFin\Dominio;

/**
 * ¿Todos los registros, o solo los míos?
 *
 * EL PROBLEMA QUE RESUELVE
 *
 * Permisos dice QUÉ puede hacer alguien. No dice SOBRE QUÉ. Con solo la
 * matriz, "Leo puede ver expedientes" significa que Leo puede ver TODOS
 * los expedientes, incluido el divorcio que lleva Alexis.
 *
 * En un despacho eso no se sostiene: son datos de terceros que no son
 * clientes de Leo. En una agencia de seguros es peor, porque la cartera
 * completa es justo lo que un agente se llevaría al cambiarse de agencia.
 *
 * DÓNDE SE APLICA
 *
 * En la CONSULTA, nunca en la vista. Filtrar al pintar deja la URL viva:
 * quien escriba /clientes/418 a mano recibe el expediente de otro. Por
 * eso esto devuelve SQL, para que el filtro viaje pegado al WHERE y no
 * haya forma de pedir una pantalla sin él.
 *
 * CÓMO SE USA
 *
 *     $a = Alcance::deSesion();
 *     $sql = "SELECT * FROM clientes WHERE activo = 1" . $a->filtro('responsable_id');
 *     $st  = $db->prepare($sql);
 *     $st->execute($a->parametros());
 *
 * Y para una ficha concreta, antes de pintarla:
 *
 *     $a->exigir((int)$cliente['responsable_id']);
 *
 * QUIÉN LO ESQUIVA
 *
 * Quien tenga `ver.todo`. Hoy es admin. Un socio del despacho que deba
 * ver todas las materias se le da ese permiso y listo, sin tocar código.
 */
final class Alcance
{
    /** @var int|null  null = sin límite: alcanza todo. */
    private $usuarioId;

    private function __construct($usuarioId)
    {
        $this->usuarioId = $usuarioId;
    }

    /** El de la sesión actual. */
    public static function deSesion()
    {
        if (Permisos::puede('ver.todo')) return new self(null);
        return new self((int)($_SESSION['usuario_id'] ?? 0));
    }

    /** Para pruebas y para trabajos de fondo que corren sin sesión. */
    public static function de($usuarioId) { return new self($usuarioId); }
    public static function sinLimite()    { return new self(null); }

    /** ¿Alcanza todo? */
    public function esTotal() { return $this->usuarioId === null; }

    /**
     * El trozo de SQL que va pegado al WHERE. Cadena vacía si no limita,
     * para que la consulta quede igual que antes cuando es un admin.
     *
     * El marcador es nombrado y lleva el nombre de la columna, así una
     * consulta que filtre por dos columnas distintas (el responsable del
     * cliente y el del asunto) no se pisa los parámetros.
     */
    public function filtro($columna, $tabla = null)
    {
        if ($this->esTotal()) return '';
        $col = $this->columnaSegura($columna);
        $pre = $tabla !== null ? $this->columnaSegura($tabla) . '.' : '';
        return ' AND ' . $pre . $col . ' = :alc_' . $col;
    }

    /**
     * Igual, pero para empezar un WHERE en vez de continuarlo.
     * Devuelve '1=1' cuando no limita, para no romper la sintaxis.
     */
    public function donde($columna, $tabla = null)
    {
        $f = $this->filtro($columna, $tabla);
        return $f === '' ? '1=1' : ltrim(substr($f, 5));   // le quita ' AND '
    }

    /** Los parámetros que pide filtro(). Vacío si no limita. */
    public function parametros($columna = 'responsable_id')
    {
        if ($this->esTotal()) return [];
        return ['alc_' . $this->columnaSegura($columna) => $this->usuarioId];
    }

    /**
     * ¿Este registro es alcanzable? Para la ficha de uno solo, donde no
     * hay WHERE que filtrar.
     *
     * Un registro SIN responsable (null o 0) lo ve cualquiera: es el caso
     * del prospecto que acaba de entrar por WhatsApp y todavía no se le
     * asigna a nadie. Si no, nadie podría tomarlo.
     */
    public function alcanza($responsableId)
    {
        if ($this->esTotal()) return true;
        if ($responsableId === null || (int)$responsableId === 0) return true;
        return (int)$responsableId === $this->usuarioId;
    }

    /**
     * Lo mismo, pero corta la ejecución. Se responde 404 y no 403 a
     * propósito: un 403 confirma que el expediente existe, y saber que
     * el cliente «Constructora Vega» es cliente del despacho ya es
     * información que no le toca a quien no lleva el asunto.
     */
    public function exigir($responsableId)
    {
        if ($this->alcanza($responsableId)) return;
        http_response_code(404);
        if (!headers_sent()) header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>No encontrado</title>'
           . '<p style="font:16px system-ui;padding:2rem">No encontrado.</p>';
        exit;
    }

    /**
     * Nombre de columna que entra a la consulta: solo letras, números y
     * guion bajo. Nunca llega de fuera, pero esto es lo que impide que
     * algún día llegue.
     */
    private function columnaSegura($c)
    {
        if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', (string)$c)) {
            throw new \InvalidArgumentException('Nombre de columna inválido: ' . $c);
        }
        return $c;
    }
}
