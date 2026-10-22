<?php
namespace LibertyFin\Dominio;

/**
 * Quién puede hacer qué.
 *
 * Una sola matriz. El router, el menú y los controladores leen de aquí,
 * así que no puede pasar lo del sistema anterior: que el enlace esté
 * escondido pero la URL siga funcionando si alguien la escribe.
 *
 * Los permisos se nombran por lo que la persona HACE, no por la pantalla
 * donde lo hace. "cobrar" es cobrar, exista o no una sección llamada
 * Caja. Así, mover algo de lugar no obliga a repensar los permisos.
 */
final class Permisos
{
    /**
     * Los roles que existen de verdad en LibertyFin.
     *
     * No invento ninguno: son los tres que ya usaba el sistema anterior
     * más soporte, que allá entraba por un login aparte. Aquí entra por
     * el mismo: dos puertas al mismo sistema son dos superficies que
     * proteger, dos lugares donde aplicar un cambio y una que siempre
     * se queda atrás.
     */
    const ROLES = [
        'superadmin' => [
            'rotulo' => 'Superadministrador',
            'para'   => 'Dueño de LibertyFin. Puede todo, en todas las empresas.',
            'nivel'  => 'plataforma',
        ],
        'soporte' => [
            'rotulo' => 'Soporte',
            'para'   => 'Mantenimiento y diagnóstico. Ve todo, no mueve dinero.',
            'nivel'  => 'plataforma',
        ],
        'validador' => [
            'rotulo' => 'Validación',
            'para'   => 'Revisa la documentación de las empresas. No ve ventas ni dinero.',
            'nivel'  => 'plataforma',
        ],
        'admin' => [
            'rotulo' => 'Administrador',
            'para'   => 'Dueño o gerente del negocio. Ve el dinero y configura.',
            'nivel'  => 'empresa',
        ],
        // El que atiende: abogado en un despacho, agente en una agencia,
        // asesor en una inmobiliaria. Lleva SUS clientes y SUS asuntos, y
        // sin `ver.todo` no alcanza los de nadie más.
        'agente' => [
            'rotulo' => 'Agente',
            'para'   => 'Lleva sus propios clientes y asuntos. No ve los de sus compañeros.',
            'nivel'  => 'empresa',
        ],
        // Quien agenda, recibe documentos y da entrada a los prospectos.
        // Ve a todos los clientes porque tiene que poder atender el
        // teléfono, pero no entra al dinero ni a las comisiones.
        'asistente' => [
            'rotulo' => 'Asistente',
            'para'   => 'Agenda citas, recibe documentos y da de alta prospectos. No ve el dinero.',
            'nivel'  => 'empresa',
        ],
    ];

    /**
     * La matriz.
     *
     * Dos reglas de fondo:
     *
     *  · Soporte VE todo y no MUEVE nada. Quien entra a arreglar un
     *    problema necesita mirar, no cobrar. Si además pudiera cobrar,
     *    cancelar pagos o asignar comisiones, no habría forma de saber
     *    si un descuadre lo causó la empresa o quien vino a ayudar.
     *
     *  · Agente no ve comisiones. No es desconfianza: es que el importe
     *    que cobra alguien más no es asunto suyo, y tenerlo a la vista
     *    en la pantalla donde atiende clientes es una fuga de información
     *    que nadie pidió.
     */
    /**
     * Quién puede qué.
     *
     * DOS NIVELES, Y NO SE MEZCLAN
     *
     * Las secciones de EMPRESA —Panel, Caja, Ventas, Reportes— muestran
     * los datos de UNA empresa: la de la sesión. Dárselas a soporte no
     * le sirve y además engaña: vería las ventas de quien le prestó su
     * cuenta y creería que son las del cliente que llamó.
     *
     * Para mirar dentro de una empresa, soporte entra por su ficha en
     * /soporte/{id}, que sí dice de quién son los números.
     *
     * Por eso ningún rol de plataforma tiene permisos de empresa, ni
     * superadmin. No es una limitación: es que ese permiso no significa
     * nada sin decir de qué empresa.
     */
    const MATRIZ = [
        // ── Secciones de EMPRESA ──
        'ver.panel'        => ['admin','agente','asistente'],
        'ver.contratos'    => ['admin','agente'],
        // Importar escribe contratos, cobros y comisiones de golpe:
        // es cosa de quien responde por los números.
        'importar.ventas'  => ['admin'],
        'ver.clientes'     => ['admin','agente','asistente'],
        'ver.cobranza'     => ['admin','agente'],
        'ver.servicios'    => ['admin','agente','asistente'],
        'ver.comisiones'   => ['admin'],
        'ver.gastos'       => ['admin','asistente'],
        'ver.reportes'     => ['admin'],
        'ver.ajustes'      => ['admin'],
        'ver.usuarios'     => ['admin'],
        'ver.auditoria'    => ['admin'],

        // EL CRM NO COBRA.
        //
        // `cobrar`, `abrir.caja`, `cerrar.caja`, `timbrar` y
        // `vender.recarga` se fueron con el POS: aquí no hay caja, no se
        // recibe dinero y no se emite comprobante fiscal.
        //
        // Lo que queda es `marcar.pago`: dejar asentado que el anticipo
        // YA se recibió, para que el embudo pueda cerrarse y el director
        // vea su reporte. Marcar no es cobrar. Cuando hay un POS
        // conectado, el estado llega por la API y nadie lo marca a mano.
        'marcar.pago'      => ['admin','agente'],
        'cancelar.pago'    => ['admin'],
        'editar.clientes'  => ['admin','agente'],
        'editar.servicios' => ['admin'],
        // ── Módulos (Servicio\Modulos) ──
        'cancelar.contrato'   => ['admin'],
        'ver.prospectos'      => ['admin','agente'],
        'editar.prospectos'   => ['admin','agente'],
        'ver.canales'         => ['admin','agente'],
        'editar.canales'      => ['admin'],
        'ver.expediente'      => ['admin','agente','asistente'],
        'editar.expediente'   => ['admin','agente'],
        'ver.cotizaciones'    => ['admin','agente'],
        'editar.cotizaciones' => ['admin','agente'],
        // Una iguala genera contratos cada mes: decide quien responde por el dinero.
        'ver.igualas'         => ['admin'],
        'editar.igualas'      => ['admin'],
        'ver.seguimiento'     => ['admin','agente'],
        'editar.seguimiento'  => ['admin','agente'],
        'ver.agenda'          => ['admin','agente','asistente'],
        'editar.agenda'       => ['admin','agente'],

        // ── Las verticales ──
        'ver.legal'           => ['admin','agente','asistente'],
        'editar.legal'        => ['admin','agente'],
        'ver.seguros'         => ['admin','agente','asistente'],
        'editar.seguros'      => ['admin','agente'],

        // ── Alcance: ¿todo, o solo lo mío? ──
        //
        // Esto NO es una sección más. Es la diferencia entre "Leo puede
        // ver expedientes" y "Leo puede ver LOS EXPEDIENTES DE LEO".
        //
        // En un despacho, un abogado de mercantil no tiene por qué leer
        // el divorcio que lleva otro: son datos de terceros que no son
        // sus clientes. En una agencia de seguros, un agente no ve la
        // cartera de sus compañeros porque es con lo que se cambiaría de
        // agencia.
        //
        // Quien NO tiene `ver.todo` solo alcanza los registros donde
        // figura como responsable. Lo aplica Dominio\Alcance, en la
        // consulta, no en la vista: esconder el enlace y dejar viva la
        // URL es exactamente el agujero que esta matriz existe para
        // evitar.
        'ver.todo'         => ['admin'],
        'asignar.responsable' => ['admin'],

        'editar.gastos'    => ['admin','asistente'],
        'borrar.gastos'    => ['admin'],
        'asignar.comision' => ['admin'],
        'quitar.comision'  => ['admin'],
        'editar.ajustes'   => ['admin'],
        'editar.usuarios'  => ['admin'],
        'editar.empresa'   => ['admin'],

        // ── Secciones de PLATAFORMA ──
        // Cualquiera de la empresa puede abrir un ticket. Si la única
        // puerta fuera el correo, media queja se pierde y la otra media
        // llega sin folio ni contexto.
        'abrir.ticket'     => ['admin','agente','asistente'],

        'ver.soporte'      => ['superadmin','soporte','validador'],
        'ver.tickets'      => ['superadmin','soporte','validador'],
        'ver.empresas'     => ['superadmin','soporte','validador'],
        'ver.conocimiento' => ['superadmin','soporte','validador'],
        'editar.conocimiento' => ['superadmin','soporte','validador'],
        'ver.mantenimiento'=> ['superadmin','soporte'],
        'ver.informes'     => ['superadmin','soporte'],

        'revisar.docs'     => ['superadmin','soporte','validador'],
        'clave.ajena'      => ['superadmin','soporte'],
        'bloquear.cuenta'  => ['superadmin','soporte'],
        'secciones'        => ['superadmin','soporte','admin'],
        'diagnostico'      => ['superadmin','soporte'],
        'alta.empresas'    => ['superadmin','soporte'],

        // Solo el superadministrador. Entrar a la sesión de una empresa
        // es la llave maestra: se deja fuera de soporte a propósito.
        // Cuentas de plataforma: solo el superadministrador.
        'usuarios.plataforma' => ['superadmin'],
        // Suspender una empresa la deja sin entrar, pero conserva todo.
        'suspender.empresa'   => ['superadmin'],
        // Apagar algo para TODAS las empresas es una decisión de
        // mantenimiento, no de atención: la toma quien responde por la
        // plataforma, no quien contesta un ticket.
        'ajustes.globales'    => ['superadmin'],
        // Apagar una sección o un método en UNA empresa sí lo hace
        // soporte: suele ser justo lo que el cliente está pidiendo.
        'ajustes.empresa'     => ['superadmin','soporte'],
    ];

    /**
     * El nombre legible de un rol, sin quedarse nunca en blanco.
     *
     * Si en la base hay un rol que esta versión no conoce —porque venía
     * del sistema anterior o porque alguien lo escribió a mano— se
     * muestra el valor crudo. Una celda vacía hace pensar que el dato
     * se perdió; ver "empleado" dice exactamente qué pasa.
     */
    public static function rotulo($rol)
    {
        $crudo = trim((string)$rol);
        if ($crudo === '') return 'sin rol asignado';
        $n = self::normalizar($crudo);
        if (isset(self::ROLES[$n])) {
            // Un rol traducido se muestra con su nombre actual, pero
            // diciendo de dónde viene: si no, nadie entiende por qué
            // "vendedor" abre lo mismo que "agente".
            return $n === $crudo
                ? self::ROLES[$n]['rotulo']
                : self::ROLES[$n]['rotulo'] . ' (antes ' . $crudo . ')';
        }
        return $crudo;
    }

    /** ¿Es un rol que esta versión conoce? */
    public static function conocido($rol)
    {
        return isset(self::ROLES[self::normalizar($rol)]);
    }

    /**
     * Roles del sistema anterior que ya no existen, y a qué equivalen.
     *
     * Una cuenta con uno de estos seguiría funcionando en vez de quedarse
     * sin permisos sin explicación. Se traduce, no se niega.
     */
    const EQUIVALENCIAS = [
        'supervisor'  => 'admin',
        'vendedor'    => 'agente',
        'super_admin' => 'admin',
        'empleado'    => 'agente',
    ];

    /** Traduce un rol viejo al actual. Devuelve el mismo si ya es actual. */
    public static function normalizar($rol)
    {
        $rol = trim((string)$rol);
        if (isset(self::ROLES[$rol])) return $rol;
        return self::EQUIVALENCIAS[$rol] ?? $rol;
    }

    public static function puede($permiso, $rol = null)
    {
        $rol = self::normalizar($rol ?? ($_SESSION['usuario_rol'] ?? ''));
        $lista = self::MATRIZ[$permiso] ?? null;
        // Un permiso que no existe se niega. Escribirlo mal debe cerrar
        // la puerta, no abrirla.
        if ($lista === null) return false;
        return in_array($rol, $lista, true);
    }

    /** Todo lo que puede un rol, para mostrarlo al asignarlo. */
    public static function de($rol)
    {
        $rol = self::normalizar($rol);
        $r = [];
        foreach (self::MATRIZ as $p => $roles) {
            if (in_array($rol, $roles, true)) $r[] = $p;
        }
        return $r;
    }

    /**
     * Qué roles puede ASIGNAR cada rol.
     *
     * Esto es lo que impide una escalada de privilegios silenciosa: sin
     * este límite, un administrador de empresa podía crear un usuario
     * con rol `soporte` y con eso ver los datos de TODOS los clientes.
     * No hacía falta ningún truco, solo elegir del desplegable.
     *
     * Nadie puede repartir un poder que no tiene. Un administrador
     * reparte roles de su empresa; los de plataforma solo los da el
     * superadministrador, y esos ni siquiera viven en una empresa.
     */
    const PUEDE_ASIGNAR = [
        'superadmin' => ['superadmin','soporte','validador','admin','agente','asistente'],
        'soporte'    => [],
        'validador'  => [],
        'admin'      => ['admin','agente','asistente'],
        'agente'     => [],
        'asistente'  => [],
    ];

    /**
     * Los permisos que NO necesitan una empresa.
     *
     * Sirve para un guardia central: si una sesión sin empresa llega a
     * una ruta que pide cualquier otro permiso, se detiene ahí con un
     * mensaje. Sin eso, la petición avanza hasta que un repositorio
     * intenta consultar una base vacía y revienta con "1046 No database
     * selected" y una traza que no explica nada.
     *
     * Es una red, no el arreglo: cada pantalla sigue siendo responsable
     * de lo suyo. Pero convierte un error feo en uno que se entiende, y
     * lo hace en UN lugar en vez de cuarenta y siete.
     */
    const SIN_EMPRESA = [
        'ver.soporte', 'ver.tickets', 'ver.empresas', 'ver.conocimiento',
        'editar.conocimiento', 'ver.mantenimiento', 'ver.informes',
        'revisar.docs', 'clave.ajena', 'bloquear.cuenta', 'diagnostico',
        'alta.empresas', 'usuarios.plataforma', 'suspender.empresa',
        'ajustes.globales', 'ajustes.empresa',
    ];

    public static function necesitaEmpresa($permiso)
    {
        return !in_array($permiso, self::SIN_EMPRESA, true);
    }

    public static function rolesQuePuedeAsignar($rol = null)
    {
        $rol = self::normalizar($rol ?? ($_SESSION['usuario_rol'] ?? ''));
        $lista = self::PUEDE_ASIGNAR[$rol] ?? [];
        $r = [];
        foreach ($lista as $k) if (isset(self::ROLES[$k])) $r[$k] = self::ROLES[$k];
        return $r;
    }

    public static function puedeAsignar($rolDestino, $rol = null)
    {
        return array_key_exists($rolDestino, self::rolesQuePuedeAsignar($rol));
    }

    /** ¿Es un rol de plataforma? Esos no pertenecen a una empresa. */
    public static function esPlataforma($rol)
    {
        $n = self::normalizar($rol);
        return (self::ROLES[$n]['nivel'] ?? 'empresa') === 'plataforma';
    }

    /** Un resumen legible del rol, para la pantalla de usuarios. */
    public static function resumen($rol)
    {
        $todos = self::de($rol);
        $ve    = count(array_filter($todos, function ($p) { return strpos($p, 'ver.') === 0; }));
        $hace  = count($todos) - $ve;
        return ['ve' => $ve, 'hace' => $hace, 'total' => count(self::MATRIZ)];
    }
}
