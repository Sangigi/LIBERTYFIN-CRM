<?php
/* ════════════════════════════════════════════════════════
   VISTA PREVIA SIN BASE DE DATOS
   ════════════════════════════════════════════════════════
       php herramientas/vista-previa.php > /tmp/hoy.html

   Pinta la pantalla Hoy con el menú, la barra y las vistas REALES del
   sistema, con datos de muestra y sin tocar MySQL. Se abre en el
   navegador y se compara contra el prototipo.

   Nació porque el diseño se estaba corrigiendo a ciegas: se subía al
   servidor, se miraba, se corregía, y cada vuelta costaba una hora. Con
   esto, ver el efecto de un cambio de CSS cuesta dos segundos.

   NO ES UNA RUTA. Vive en herramientas/, fuera de public/, y solo corre
   desde la terminal.
   ════════════════════════════════════════════════════════ */
/* La pantalla Hoy COMPLETA: menú, barra y contenido reales del sistema,
   con la hoja crm.css incrustada. Sin base de datos. Sirve para
   comparar contra el prototipo sin tener que subir nada. */
session_start();
$RAIZ = dirname(__DIR__);
$_SESSION = ['lf_token'=>'x','usuario_id'=>1,'usuario_nombre'=>'Leonel Almanza',
  'usuario_rol'=>'admin','empresa_db'=>'demo','empresa_nombre'=>'Despacho Almanza',
  'sucursal_nombre'=>'Matriz','lf_marca_logo'=>''];
spl_autoload_register(function($c) use($RAIZ){
  $r = $RAIZ.'/src/'.str_replace(['LibertyFin\\','\\'],['','/'],$c).'.php';
  if (is_file($r)) require $r;
});
$titulo='Buenas tardes, Leonel'; $icono='reloj';
$subtitulo='Viernes 23 de octubre · 3 cosas te están esperando';
$porEtapa=['nuevo'=>['n'=>3,'monto'=>42000.0],'contactado'=>['n'=>2,'monto'=>61000.0],
  'cita'=>['n'=>4,'monto'=>118000.0],'propuesta'=>['n'=>2,'monto'=>63000.0]];
$etapas=['nuevo'=>'Nuevo','contactado'=>'Contactado','cita'=>'Cita agendada',
  'propuesta'=>'Propuesta enviada'];
$colores=[]; $enJuego=284000.0;
$esperan=[
 ['id'=>1,'nombre'=>'Inmobiliaria Cedro','asunto'=>'Iguala mensual','valor'=>63000,
  'etapa'=>'propuesta','origen'=>'instagram','proxima_fecha'=>date('Y-m-d',strtotime('-3 days')),
  'proximo_paso'=>'Seguimiento'],
 ['id'=>2,'nombre'=>'Farmacias del Valle','asunto'=>'Cobranza mercantil','valor'=>61000,
  'etapa'=>'contactado','origen'=>'whatsapp','proxima_fecha'=>date('Y-m-d',strtotime('-1 day')),
  'proximo_paso'=>'Confirmar cita'],
 ['id'=>3,'nombre'=>'Constructora Vega','asunto'=>'Juicio mercantil','valor'=>180000,
  'etapa'=>'nuevo','origen'=>'whatsapp','proxima_fecha'=>date('Y-m-d'),
  'proximo_paso'=>'Primer contacto']];
$origenes=[['origen'=>'whatsapp','n'=>14],['origen'=>'facebook','n'=>8],['origen'=>'instagram','n'=>5]];
$catOrigenes=['whatsapp'=>'WhatsApp','facebook'=>'Facebook','instagram'=>'Instagram','manual'=>'Captura manual'];
$resumen=['abiertos'=>11,'ganados'=>4,'perdidos'=>1,'monto_ganado'=>90000.0];
$citas=[['inicio'=>date('Y-m-d 10:30:00'),'servicio'=>'Audiencia preliminar',
  'cliente_nombre'=>'Lucía Ortega','estado'=>'agendada','especialista'=>'Leo']];
$aviso=null;

/* El menú real, tal cual lo arma sidebar.php, pero sin su filtro por
   permisos (que necesitaría la base). */
$menu = [
 ['ruta'=>'/hoy','icono'=>'reloj','texto'=>'Hoy'],
 ['ruta'=>'/prospectos','icono'=>'tablero','texto'=>'Embudo'],
 ['ruta'=>'/canales','icono'=>'chat','texto'=>'Canales'],
 ['sec'=>'Operación'],
 ['ruta'=>'/clientes','icono'=>'cliente','texto'=>'Clientes'],
 ['ruta'=>'/legal','icono'=>'libro','texto'=>'Asuntos'],
 ['ruta'=>'/seguros','icono'=>'doc','texto'=>'Pólizas'],
 ['ruta'=>'/agenda','icono'=>'agenda','texto'=>'Agenda'],
 ['ruta'=>'/cobranza','icono'=>'cobro','texto'=>'Cobranza'],
 ['sec'=>'Administración'],
 ['ruta'=>'/gastos','icono'=>'baja','texto'=>'Gastos'],
 ['ruta'=>'/reportes','icono'=>'pct','texto'=>'Reportes'],
 ['ruta'=>'/ajustes','icono'=>'serv','texto'=>'Ajustes'],
];
$activa='/hoy';
use LibertyFin\Vista\Widget as W;
use LibertyFin\Vista\Plantilla as P;
ob_start(); include $RAIZ.'/src/Vista/plantillas/hoy/index.php'; $cuerpo=ob_get_clean();
$css = file_get_contents($RAIZ.'/public/assets/css/crm.css');
?><!DOCTYPE html><html lang="es"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>LibertyFin CRM · Hoy</title>
<style><?= $css ?></style></head>
<body>
<div class="lf-app">
<aside class="lf-side" id="lfSide">
  <div class="lf-marca"><span class="g">D</span>
    <span><b>Despacho Almanza</b><small>Matriz</small></span></div>
  <nav class="lf-nav">
  <?php foreach ($menu as $m): if (isset($m['sec'])): ?>
    <div class="sec"><?= P::e($m['sec']) ?></div>
  <?php else: ?>
    <a href="<?= $m['ruta'] ?>" class="<?= $activa===$m['ruta']?'on':'' ?>" title="<?= P::e($m['texto']) ?>">
      <?= W::icono($m['icono'],'17px') ?><span><?= P::e($m['texto']) ?></span></a>
  <?php endif; endforeach ?>
  </nav>
  <button type="button" class="lf-angosta" id="lfAngosta" title="Reducir el menú">
    <span class="lf-ang-ico">&#8249;</span><span class="lf-ang-txt">Reducir el menú</span></button>
  <div class="lf-ucard"><span class="lf-av">LA</span>
    <div><b>Leonel Almanza</b><small>Administrador</small></div></div>
</aside>
<div class="lf-main">
<?php include $RAIZ.'/src/Vista/plantillas/parciales/topbar.php'; ?>
<div class="lf-cont"><?= $cuerpo ?></div>
</div></div>
<script>
document.getElementById('lfAngosta').addEventListener('click',function(){
  document.documentElement.classList.toggle('menu-chico');
});
document.querySelector('.lf-tema').addEventListener('click',function(){
  var o=document.documentElement.getAttribute('data-theme')==='dark';
  document.documentElement.setAttribute('data-theme',o?'light':'dark');
});
</script>
</body></html>
