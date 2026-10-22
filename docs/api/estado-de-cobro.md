# El estado de cobro: cómo hablan CRM y POS

## La regla

**LibertyFin CRM no cobra.** No recibe efectivo, no pasa tarjetas, no abre
cajón, no manda ligas de pago a los clientes de la empresa y no timbra
CFDI. Todo eso es de LibertyFin POS.

Lo que sí lleva el CRM es el **estado**: cuánto se pactó, cuánto va
pagado, quién debe. Sin eso no podría cerrar su propio embudo, porque un
prospecto se vuelve cliente justo cuando paga el anticipo.

La diferencia en una línea:

> Marcar que el anticipo entró **no es** recibir el anticipo.

## Los dos modos

### Sin POS conectado

El CRM se vende solo. El agente marca a mano "anticipo recibido" cuando
el dinero ya entró por donde haya entrado: transferencia, efectivo en
recepción, terminal del banco. El CRM guarda el estado y la fecha, y con
eso cierra el embudo y arma el reporte del director.

Requiere el permiso `marcar.pago`. Queda en la bitácora con quién lo
marcó, porque marcar un pago que no entró es la forma más fácil de
inflar un reporte de ventas.

### Con POS conectado

El estado llega por la API y **nadie lo marca a mano**. El contrato del
CRM queda ligado a la venta del POS, y el saldo lo manda el POS, que es
quien tiene el movimiento de caja de verdad.

Cuando hay liga, el CRM **oculta** el botón de marcar: dos fuentes para
el mismo dato es la receta para que no cuadren.

## Quién le pregunta a quién

| Quién pregunta | Qué pide | A quién | Para qué |
|---|---|---|---|
| CRM | saldo de una venta | POS | cerrar el embudo, armar cobranza |
| CRM | ¿este cliente ya compró? | POS | ficha completa del cliente |
| CRM | crea un cobro pendiente | POS | el POS lo cobra en caja |
| POS | ¿tiene contrato abierto? | CRM | avisar al cajero antes de cobrar |
| POS | registra la compra | CRM | que aparezca en su expediente |

## Cómo se reconocen al mismo cliente

**Cada sistema es dueño de su registro.** Nadie manda sobre nadie.

Cada uno guarda `ref_externa` con el id del otro:

```
CRM.clientes.ref_externa  ->  id en POS
POS.clientes.ref_externa  ->  id en CRM
```

Esto es a propósito y no es pereza. Las alternativas son peores:

- **Una base compartida** ata los dos productos para siempre: el día que
  el POS cambie una columna, el CRM se cae. Y eran dos productos
  separados justamente para que eso no pasara.
- **Sincronización automática en ambos sentidos** necesita decidir quién
  gana cuando los dos editaron el mismo teléfono. Siempre se decide mal.

Con `ref_externa`, si uno de los dos se cae el otro sigue trabajando, y
la liga se vuelve a establecer cuando regresa.

## Autenticación

La que ya existe: llave de API creada en **Ajustes → API** del sistema
que recibe la llamada. Ver `src/Api/Puerta.php` y `src/Api/LlaveRepo.php`.

La llave lleva rol (`admin`, `agente`, `asistente`), y la misma matriz de
`Dominio\Permisos` decide qué alcanza. Una llave con rol `asistente` no
puede leer cobranza, igual que la persona.

Los escritorios usan `Idempotencia-Llave` en la cabecera: el POS puede
reintentar "registra la compra" sin que aparezca dos veces. Ya está
implementado en `src/Api/IdempotenciaRepo.php`.

## Qué pasa cuando el otro no contesta

El CRM **no se bloquea**. Si el POS no responde:

1. Muestra el último saldo conocido, con su fecha.
2. Dice que el dato puede estar atrasado. No lo esconde ni lo finge
   fresco.
3. No habilita el botón de marcar a mano, porque la liga sigue
   configurada: habilitarlo crearía el dato duplicado que la liga
   existe para evitar.

Mostrar un saldo viejo diciendo que es viejo es honesto. Mostrarlo como
si fuera de ahora es cómo se cobra dos veces.

## Lo que falta por construir

Esta es la especificación. Los endpoints del lado CRM
(`/api/contratos`, `/api/prospectos`, `/api/cobranza/estado`) todavía no
existen. El lado POS sí: ya expone `Clientes`, `Ventas`, `Cobranza` y
`Productos`.
