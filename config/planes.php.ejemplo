<?php
/**
 * Planes de LibertyFin CRM.
 *
 *   clave      minúsculas, números, - y _ (se guarda en empresas.plan)
 *   nombre     como lo ve el cliente
 *   precio     por MES, en pesos. El anual se calcula solo con el descuento.
 *   usuarios   cuántos usuarios incluye (informativo)
 *   popular    true marca la tarjeta como "Más popular"
 *   grupos     características, agrupadas por título
 *   modulos    los módulos que incluye (ver Servicio\Modulos). Sin esta
 *              llave, el plan incluye todos. La prueba gratis siempre los
 *              incluye todos. Comisiones y Expediente van en todos.
 *
 * ESTE ARCHIVO ES DE CRM, NO DE POS
 *
 * No hay planes por "cajas registradoras" ni por "productos": eso es del
 * otro producto. Aquí se cobra por usuarios y por el giro, que es lo que
 * de verdad escala en un despacho o una agencia.
 *
 * `descuento_anual` es el % que se descuenta al pagar los 12 meses juntos.
 *
 * `cuenta` son los datos a donde el cliente hace la transferencia:
 * LLÉNALOS antes de dar el acceso a clientes, o la pantalla de pago
 * saldrá sin ellos.
 */
return [
    'descuento_anual' => 20,

    'planes' => [
        'esencial' => [
            'nombre'   => 'Esencial',
            'precio'   => 399,
            'usuarios' => 2,
            'grupos'   => [
                'Comercial' => ['Prospectos con semáforo', 'Cotizaciones', 'Clientes ilimitados'],
                'Operación' => ['Expediente de clientes', 'Agenda de citas'],
                'Canales'   => ['WhatsApp (1 número)'],
            ],
            'modulos'  => ['prospectos', 'cotizaciones', 'expediente', 'agenda', 'canales'],
        ],
        'equipo' => [
            'nombre'   => 'Equipo',
            'precio'   => 899,
            'usuarios' => 6,
            'popular'  => true,
            'grupos'   => [
                'Comercial' => ['Todo lo de Esencial', 'Canales con Instagram y Facebook'],
                'Operación' => ['Seguimiento de trabajos', 'Igualas', 'Cobranza'],
                'Equipo'    => ['Cada quien ve solo sus clientes', 'Comisiones por colaborador'],
            ],
            'modulos'  => ['prospectos', 'cotizaciones', 'expediente', 'agenda',
                           'canales', 'seguimiento', 'igualas', 'paquetes'],
        ],
        // El giro entra aquí: es lo que cambia el vocabulario y las
        // pantallas del sistema entero, no una sección más.
        'despacho' => [
            'nombre'   => 'Despacho jurídico',
            'precio'   => 1499,
            'usuarios' => 10,
            'grupos'   => [
                'Todo lo de Equipo' => ['Y además:'],
                'Despacho' => ['Asuntos por materia', 'Etapas procesales',
                               'Expediente digital por asunto', 'Audiencias con aviso previo'],
                'Integración' => ['Conexión con LibertyFin POS'],
            ],
            'modulos'  => ['prospectos', 'cotizaciones', 'expediente', 'agenda',
                           'canales', 'seguimiento', 'igualas', 'legal'],
        ],
        'agencia' => [
            'nombre'   => 'Agencia de seguros',
            'precio'   => 1499,
            'usuarios' => 10,
            'grupos'   => [
                'Todo lo de Equipo' => ['Y además:'],
                'Agencia' => ['Pólizas por ramo', 'Control de vigencias',
                              'Avisos de renovación', 'Comisión de aseguradora'],
                'Integración' => ['Conexión con LibertyFin POS'],
            ],
            'modulos'  => ['prospectos', 'cotizaciones', 'expediente', 'agenda',
                           'canales', 'seguimiento', 'igualas', 'seguros'],
        ],
    ],

    'cuenta' => [
        'banco'   => '',
        'titular' => '',
        'clabe'   => '',
    ],
];
