<?php
namespace LibertyFin\Servicio;

use LibertyFin\Datos\Conexion;
use PDO;

/**
 * Alta de una empresa nueva.
 *
 * CÓMO ERA ANTES, Y POR QUÉ CAMBIA
 *
 * `registroEmpresa.php` era una página PÚBLICA, sin sesión, que creaba
 * bases de datos llamando a la API de cPanel con el token escrito dentro
 * del archivo. Cualquiera que diera con esa URL podía crear bases en el
 * hosting hasta llenarlo.
 *
 * Aquí el alta sigue siendo autoservicio —es lo que el negocio necesita—
 * pero con tres cosas que antes no había:
 *
 *   · Las credenciales de cPanel viven en config/integraciones.php.
 *   · Sin ellas, el registro simplemente no existe: la ruta da 404.
 *   · Se guarda como SOLICITUD, no como empresa activa. La base se crea
 *     cuando alguien de LibertyFin la aprueba.
 *
 * Ese último punto es el que de verdad protege: una solicitud cuesta un
 * renglón en una tabla; una base de datos cuesta espacio, un usuario de
 * MySQL y una entrada en el respaldo.
 */
final class CrearEmpresa
{
    /** Los giros que se ofrecen en el registro. «Otro» deja escribirlo. */
    /** Días de la prueba gratuita: de aquí sale el vencimiento y el texto del registro. */
    const DIAS_PRUEBA = 15;

    const GIROS = [
        'Contabilidad y fiscal', 'Despacho legal', 'Marketing y publicidad', 'Consultoría',
        'Salud y bienestar', 'Belleza y estética',
        'Tienda o comercio', 'Restaurante o alimentos', 'Otro',
    ];

    /** Cuántas personas trabajan en el negocio: sirve para acompañar el alta. */
    const EMPLEADOS = ['1' => 'Solo yo', '2-5' => 'De 2 a 5', '6-20' => 'De 6 a 20',
                       '21-50' => 'De 21 a 50', '50+' => 'Más de 50'];

    private $principal;
    public function __construct(PDO $principal) { $this->principal = $principal; }

    /** Se crea sola la primera vez. */
    private function asegurar()
    {
        $this->principal->exec("
            CREATE TABLE IF NOT EXISTS solicitudes_empresa (
                id INT AUTO_INCREMENT PRIMARY KEY,
                nombre_empresa VARCHAR(200) NOT NULL,
                giro_comercial VARCHAR(200) NULL,
                rfc VARCHAR(20) NULL,
                nombre_contacto VARCHAR(200) NOT NULL,
                email_admin VARCHAR(160) NOT NULL,
                telefono VARCHAR(30) NULL,
                no_distribuidor VARCHAR(50) NULL,
                plan VARCHAR(40) NULL,
                estado VARCHAR(20) NOT NULL DEFAULT 'pendiente',
                motivo_rechazo VARCHAR(400) NULL,
                empresa_id INT NULL,
                ip VARCHAR(45) NULL,
                creada_en DATETIME NOT NULL,
                resuelta_en DATETIME NULL,
                resuelta_por INT NULL,
                KEY ix_se_estado (estado),
                KEY ix_se_email (email_admin)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    /** @throws \InvalidArgumentException con un mensaje apto para mostrar */
    public function solicitar(array $d, $ip)
    {
        $this->asegurar();

        $nombre = trim($d['nombre_empresa'] ?? '');
        if (mb_strlen($nombre) < 3) {
            throw new \InvalidArgumentException('Escribe el nombre de tu negocio');
        }
        $contacto = trim($d['nombre_contacto'] ?? '');
        if (mb_strlen($contacto) < 3) {
            throw new \InvalidArgumentException('Escribe tu nombre');
        }
        $email = trim($d['email_admin'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('El correo no es válido');
        }
        $rfc = mb_strtoupper(trim($d['rfc'] ?? ''));
        if ($rfc !== '' && !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
            throw new \InvalidArgumentException('El RFC no tiene el formato correcto');
        }
        // El teléfono: es por donde se le acompaña en el alta. Al menos los
        // 10 dígitos de un número en México; la lada internacional, opcional.
        $tel = preg_replace('/[^0-9+]/', '', (string)($d['telefono'] ?? ''));
        if (strlen(preg_replace('/\D/', '', $tel)) < 10) {
            throw new \InvalidArgumentException('Escribe un teléfono de 10 dígitos');
        }
        $empleados = (string)($d['empleados'] ?? '');
        if ($empleados !== '' && !isset(self::EMPLEADOS[$empleados])) $empleados = '';

        // Un correo, una solicitud pendiente. Sin esto, recargar el
        // formulario llena la tabla de duplicados y quien revisa no sabe
        // cuál atender.
        $st = $this->principal->prepare("
            SELECT COUNT(*) FROM solicitudes_empresa
            WHERE email_admin = ? AND estado = 'pendiente'");
        $st->execute([$email]);
        if ((int)$st->fetchColumn() > 0) {
            throw new \InvalidArgumentException(
                'Ya hay una solicitud en revisión con ese correo. Te avisamos en cuanto esté lista.');
        }

        // Y un tope por IP: una página pública sin tope es una invitación.
        $st = $this->principal->prepare("
            SELECT COUNT(*) FROM solicitudes_empresa
            WHERE ip = ? AND creada_en > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
        $st->execute([$ip]);
        if ((int)$st->fetchColumn() >= 3) {
            throw new \InvalidArgumentException('Demasiadas solicitudes. Inténtalo más tarde.');
        }

        $conEmpleados = $this->asegurarEmpleados();
        $this->principal->prepare("
            INSERT INTO solicitudes_empresa
                (nombre_empresa, giro_comercial, rfc, nombre_contacto, email_admin,
                 telefono, no_distribuidor, plan, estado, ip, creada_en"
                 . ($conEmpleados ? ", empleados" : "") . ")
            VALUES (?,?,?,?,?,?,?,?, 'pendiente', ?, NOW()" . ($conEmpleados ? ",?" : "") . ")
        ")->execute(array_merge([
            $nombre, mb_substr(trim($d['giro_comercial'] ?? ''), 0, 200) ?: null, $rfc ?: null,
            $contacto, $email, $tel,
            mb_substr(trim($d['no_distribuidor'] ?? ''), 0, 50) ?: null,
            trim($d['plan'] ?? '') ?: 'prueba',
            $ip,
        ], $conEmpleados ? [$empleados ?: null] : []));
        return (int)$this->principal->lastInsertId();
    }

    /**
     * La columna `empleados` llegó después que la tabla: se agrega si falta.
     * Si no se puede (permisos), la solicitud se guarda igual, sin ese dato.
     */
    private function asegurarEmpleados()
    {
        try {
            $st = $this->principal->query("SHOW COLUMNS FROM solicitudes_empresa LIKE 'empleados'");
            if ($st->fetch()) return true;
            $this->principal->exec("ALTER TABLE solicitudes_empresa ADD COLUMN empleados VARCHAR(10) NULL");
            return true;
        } catch (\Throwable $e) {
            error_log('[LibertyFin] solicitudes.empleados: ' . $e->getMessage());
            return false;
        }
    }

    public function pendientes()
    {
        $this->asegurar();
        $st = $this->principal->query("
            SELECT *, TIMESTAMPDIFF(HOUR, creada_en, NOW()) AS horas
            FROM solicitudes_empresa WHERE estado = 'pendiente'
            ORDER BY creada_en");
        return $st->fetchAll();
    }

    /**
     * Aprueba una solicitud: crea la base, carga el esquema, corre las
     * migraciones y deja al administrador listo para entrar.
     *
     * Devuelve la contraseña generada UNA sola vez. No se guarda en claro
     * en ningún lado: se muestra a quien aprueba para que la entregue.
     */
    public function aprobar($id, $raiz, $revisorId)
    {
        $this->asegurar();
        $cp = Integraciones::de('cpanel') ?: [];

        // Con `uapi` aquí mismo no hacen falta credenciales: la llamada
        // se hace localmente como el usuario que ya somos. Lo único que
        // se sigue necesitando es el PREFIJO, que cPanel antepone a todo
        // lo que crea, y que sale del propio usuario del sistema.
        $local = self::uapiLocal();
        if (!$local && empty($cp['token'])) {
            throw new \InvalidArgumentException(
                'No hay forma de crear la base: ni `uapi` en este servidor, ni '
                . 'credenciales de cPanel en config/integraciones.php. '
                . 'Comprueba con: php bin/probar-cpanel.php');
        }
        if (empty($cp['usuario'])) {
            $cp['usuario'] = self::usuarioDelSistema();
        }
        if (empty($cp['usuario'])) {
            throw new \InvalidArgumentException(
                'No se pudo saber el usuario de cPanel, que es el prefijo de las '
                . 'bases. Ponlo en config/integraciones.php → cpanel → usuario.');
        }

        $st = $this->principal->prepare("SELECT * FROM solicitudes_empresa WHERE id = ?");
        $st->execute([(int)$id]);
        $s = $st->fetch();
        if (!$s) throw new \InvalidArgumentException('Esa solicitud no existe');
        if ($s['estado'] !== 'pendiente') throw new \InvalidArgumentException('Ya estaba resuelta');

        $esquema = $raiz . '/config/esquema.sql';
        if (!is_readable($esquema)) {
            throw new \RuntimeException('No se encuentra config/esquema.sql');
        }

        // El nombre de la base sale del nombre del negocio, limpio y corto.
        // cPanel le antepone el usuario de la cuenta, así que el prefijo no
        // se escribe aquí.
        $slug = strtolower(preg_replace('/[^a-z0-9]/i', '', $s['nombre_empresa']));
        $sufijo = substr($slug, 0, 8) . substr(bin2hex(random_bytes(3)), 0, 4);
        $base = $cp['usuario'] . '_' . $sufijo;

        $creada = self::crearBaseEnCpanel($cp, $sufijo);
        if (!$creada['ok']) {
            throw new \RuntimeException('cPanel no creó la base: ' . $creada['error']);
        }

        // Cargar el esquema y ponerlo al día.
        $db = Conexion::de($base);
        self::cargarEsquema($db, $esquema);

        // Sucursal matriz y administrador.
        $db->exec("INSERT INTO sucursales (nombre, es_matriz, activo, fecha_creacion)
                   VALUES ('Matriz', 1, 1, NOW())");
        $sucursal = (int)$db->lastInsertId();

        $usuario = strtolower(preg_replace('/[^a-z0-9]/i', '', explode('@', $s['email_admin'])[0]));
        $usuario = substr($usuario ?: 'admin', 0, 30);
        $clave   = self::clave();
        $db->prepare("
            INSERT INTO usuarios (username, password, nombre, email, rol, sucursal_id)
            VALUES (?,?,?,?, 'admin', ?)
        ")->execute([$usuario, password_hash($clave, PASSWORD_DEFAULT),
                     $s['nombre_contacto'], $s['email_admin'], $sucursal]);

        // Estrena: su administrador verá primero las preguntas de la
        // configuración inicial, con lo que dijo en el registro ya puesto.
        try {
            Arranque::marcarPendiente($db, $s['giro_comercial'] ?? null, $s['empleados'] ?? null);
        } catch (\Throwable $e) {
            error_log('[LibertyFin] arranque pendiente: ' . $e->getMessage());
        }

        // Y el renglón en empresas, ya activa.
        $this->principal->prepare("
            INSERT INTO empresas
                (nombre_empresa, giro_comercial, rfc, no_distribuidor, telefono,
                 nombre_contacto, email_admin, usuario_admin, nombre_base_datos,
                 activo, plan, fecha_vencimiento)
            VALUES (?,?,?,?,?,?,?,?,?, 1, ?, DATE_ADD(CURDATE(), INTERVAL " . (int)self::DIAS_PRUEBA . " DAY))
        ")->execute([
            $s['nombre_empresa'], $s['giro_comercial'], $s['rfc'], $s['no_distribuidor'],
            $s['telefono'], $s['nombre_contacto'], $s['email_admin'], $usuario, $base,
            $s['plan'] ?: 'prueba',
        ]);
        $empresaId = (int)$this->principal->lastInsertId();

        $this->principal->prepare("
            UPDATE solicitudes_empresa
            SET estado = 'aprobada', empresa_id = ?, resuelta_en = NOW(), resuelta_por = ?
            WHERE id = ?")->execute([$empresaId, $revisorId ?: null, (int)$id]);

        return ['base' => $base, 'usuario' => $usuario, 'clave' => $clave,
                'empresa_id' => $empresaId, 'email' => $s['email_admin'],
                'contacto' => $s['nombre_contacto'], 'empresa' => $s['nombre_empresa']];
    }

    /**
     * Carga config/esquema.sql en una base vacía y la pone al día.
     *
     * Aparte del alta para que las pruebas armen sus bases EXACTAMENTE
     * como se arma la de una empresa nueva: si el esquema o una migración
     * deja de cargar en MySQL, lo dice la prueba y no el primer cliente.
     *
     * @return string[] los errores de las sentencias que no entraron
     */
    public static function cargarEsquema(PDO $db, $archivo)
    {
        $sql = file_get_contents($archivo);
        // Se quitan los comentarios de línea ANTES de partir por ";". Si no,
        // el encabezado del archivo se pega a la primera sentencia
        // (CREATE TABLE sucursales), esta empieza con "--" y se descartaba
        // completa: sin sucursales fallaban también las tablas con llave
        // foránea hacia ella y el alta terminaba con "1146 table doesn't exist".
        $sql = preg_replace('/^[ \t]*--.*$/m', '', $sql);
        $errores = [];
        foreach (array_filter(array_map('trim', explode(";\n", $sql))) as $sent) {
            if ($sent === '') continue;
            try { $db->exec($sent); }
            catch (\Throwable $e) {
                $errores[] = $e->getMessage();
                error_log('[LibertyFin] esquema: ' . $e->getMessage());
            }
        }
        Migraciones::aplicar($db);
        return $errores;
    }

    public function rechazar($id, $motivo, $revisorId)
    {
        $this->asegurar();
        $motivo = trim((string)$motivo);
        if (mb_strlen($motivo) < 10) {
            throw new \InvalidArgumentException(
                'Escribe por qué se rechaza: es lo único que el solicitante va a recibir');
        }
        $st = $this->principal->prepare("SELECT * FROM solicitudes_empresa WHERE id = ?");
        $st->execute([(int)$id]);
        $sol = $st->fetch();

        $this->principal->prepare("
            UPDATE solicitudes_empresa
            SET estado = 'rechazada', motivo_rechazo = ?, resuelta_en = NOW(), resuelta_por = ?
            WHERE id = ? AND estado = 'pendiente'")->execute([$motivo, $revisorId ?: null, (int)$id]);

        // Se devuelve la solicitud para poder avisarle a quien la mandó.
        return $sol ?: true;
    }

    /**
     * Una contraseña que se pueda dictar por teléfono.
     *
     * Sin l, I, 1, O ni 0: en una llamada nadie distingue esos, y la
     * primera experiencia con el sistema no puede ser "no puedo entrar".
     */
    private static function clave($largo = 12)
    {
        $abc = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
        $r = '';
        for ($i = 0; $i < $largo; $i++) $r .= $abc[random_int(0, strlen($abc) - 1)];
        return $r;
    }

    /**
     * El usuario de la cuenta, que es el prefijo de toda base que cPanel
     * cree. Se saca del dueño del proceso, no de la configuración: así
     * una instalación dentro de su propia cuenta no necesita escribirlo.
     */
    private static function usuarioDelSistema()
    {
        if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
            $u = @posix_getpwuid(posix_geteuid());
            if (!empty($u['name'])) return $u['name'];
        }
        $u = get_current_user();
        return $u !== '' ? $u : null;
    }

    /**
     * ¿Está `uapi` aquí mismo y lo podemos ejecutar?
     *
     * Cuando el sistema corre DENTRO de la misma cuenta de cPanel cuya
     * base queremos crear —que es el caso normal—, dar la vuelta por
     * HTTPS al puerto 2083 es cruzar la calle dando la vuelta a la
     * manzana, y encima se tropieza con todo lo que hay en el camino: el
     * certificado, cPHulk, un WAF delante, o una restricción de IP en el
     * token. Un «403 Access denied» a secas suele ser alguna de esas, y
     * no el token.
     *
     * `uapi` es el MISMO API, ejecutado localmente como el usuario que
     * ya somos. No pide token, ni puerto, ni certificado.
     */
    private static function uapiLocal()
    {
        static $ruta = false;
        if ($ruta !== false) return $ruta;

        $ruta = null;
        if (!function_exists('shell_exec')) return $ruta;
        // Si PHP tiene shell_exec deshabilitado, esto llega vacío.
        $deshabilitadas = array_map('trim', explode(',', (string)ini_get('disable_functions')));
        if (in_array('shell_exec', $deshabilitadas, true)) return $ruta;

        foreach (['/usr/local/cpanel/bin/uapi', '/usr/bin/uapi'] as $c) {
            if (is_executable($c)) { $ruta = $c; break; }
        }
        return $ruta;
    }

    /** Una llamada a uapi local. Devuelve el mismo arreglo que la de red. */
    private static function porUapi($bin, $modulo, $funcion, array $params)
    {
        $cmd = escapeshellcmd($bin) . ' --output=json ' . escapeshellarg($modulo)
             . ' ' . escapeshellarg($funcion);
        foreach ($params as $k => $v) {
            $cmd .= ' ' . escapeshellarg($k . '=' . $v);
        }
        $salida = shell_exec($cmd . ' 2>&1');
        $j = json_decode((string)$salida, true);

        if (!empty($j['result']['status'])) return ['ok' => true, 'error' => null];
        if (!empty($j['result']['errors'][0])) {
            return ['ok' => false, 'error' => $j['result']['errors'][0]];
        }
        if (!empty($j['status'])) return ['ok' => true, 'error' => null];
        if (!empty($j['errors'][0])) return ['ok' => false, 'error' => $j['errors'][0]];

        $trozo = trim(preg_replace('/\s+/', ' ', mb_substr((string)$salida, 0, 300)));
        return ['ok' => false, 'error' => 'uapi: ' . ($trozo ?: 'sin respuesta')];
    }

    /** Llama a la API de cPanel para crear la base y darle permisos. */
    private static function crearBaseEnCpanel(array $cp, $sufijo)
    {
        // Local primero: menos piezas que se puedan romper.
        $bin = self::uapiLocal();

        $llamar = function ($modulo, $funcion, $params) use ($cp, $bin) {
            if ($bin) return self::porUapi($bin, $modulo, $funcion, $params);
            $url = 'https://' . $cp['host'] . ':2083/execute/' . $modulo . '/' . $funcion
                 . '?' . http_build_query($params);
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER => ['Authorization: cpanel ' . $cp['usuario'] . ':' . $cp['token']],
                CURLOPT_TIMEOUT => 30,
                // El certificado SÍ se verifica. El sistema anterior lo
                // desactivaba, y eso convierte HTTPS en decoración.
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_SSL_VERIFYHOST => 2,
            ]);
            $r    = curl_exec($ch);
            $err  = curl_error($ch);
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($r === false) return ['ok' => false, 'error' => 'cURL: ' . $err];

            $j = json_decode($r, true);
            if (!empty($j['status'])) return ['ok' => true, 'error' => null];

            // DECIR QUÉ LLEGÓ, NO «respuesta inesperada».
            //
            // Antes, cualquier cosa que no fuera el JSON esperado daba ese
            // mensaje: el token mal, el puerto cerrado, la base repetida y
            // una página de login de cPanel se veían todas igual. Con el
            // código HTTP y un trozo del cuerpo, la causa se distingue en
            // el primer vistazo.
            //
            // El cuerpo va recortado y SIN la cabecera de autorización,
            // que nunca viaja en la respuesta: aquí no se filtra el token.
            if (isset($j['errors'][0]))     return ['ok' => false, 'error' => $j['errors'][0]];
            if (isset($j['error']))         return ['ok' => false, 'error' => $j['error']];
            if (!empty($j['cpanelresult']['error'])) {
                return ['ok' => false, 'error' => $j['cpanelresult']['error']];
            }

            $pista = $j === null
                ? 'la respuesta no es JSON (¿token inválido, o el puerto 2083 cerrado?)'
                : 'JSON sin «status» ni «errors»';
            $trozo = trim(preg_replace('/\s+/', ' ', mb_substr((string)$r, 0, 300)));

            return ['ok' => false,
                    'error' => sprintf('HTTP %d · %s · respuesta: %s', $http, $pista, $trozo)];
        };

        $r = $llamar('Mysql', 'create_database', ['name' => $cp['usuario'] . '_' . $sufijo]);
        if (!$r['ok']) return $r;
        // EL USUARIO DE MYSQL, NO EL DE CPANEL.
        //
        // Sin `usuario_bd` esto arma «cuenta_cuenta», que casi nunca
        // existe: la base queda creada y sin nadie que pueda entrar, y el
        // error sale después, al conectarse, lejos de aquí. Si no está
        // configurado, se dice ahora.
        $usuarioBd = trim((string)($cp['usuario_bd'] ?? ''));
        if ($usuarioBd === '') {
            return ['ok' => false, 'error' =>
                'Falta «usuario_bd» en la sección cpanel de config/integraciones.php: '
                . 'es el usuario de MySQL que tendrá permisos sobre la base nueva, '
                . 'sin el prefijo de la cuenta. La base se creó, pero sin permisos.'];
        }
        return $llamar('Mysql', 'set_privileges_on_database', [
            'user'       => $cp['usuario'] . '_' . $usuarioBd,
            'database'   => $cp['usuario'] . '_' . $sufijo,
            'privileges' => 'ALL PRIVILEGES',
        ]);
    }
}