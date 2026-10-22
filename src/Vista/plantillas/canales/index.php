<?php
use LibertyFin\Vista\Plantilla as P;
use LibertyFin\Vista\Widget as W;

$host = 'https://' . ($_SERVER['HTTP_HOST'] ?? 'tu-dominio.mx');

/** Las cuatro puertas, con la llave del catálogo de integraciones. */
$canales = [
    ['meta',       'WhatsApp, Instagram y Facebook', 'meta'],
    ['tiktok',     'TikTok',                          'tiktok'],
    ['linkedin',   'LinkedIn',                        'linkedin'],
    ['google',     'Google Ads',                      'google_ads'],
];

$colorRes = function ($r) {
    if ($r === 'nuevo')    return '#16a34a';
    if ($r === 'repetido') return '#2563eb';
    if ($r === 'error')    return '#dc2626';
    return '#94a3b8';
};
?>

<?php if ($aviso): ?>
<div class="alert alert-<?= $aviso['tipo']==='error'?'danger':'success' ?>" style="margin-bottom:18px">
  <?= W::icono('alerta','18px') ?><span><?= P::e($aviso['texto']) ?></span>
</div>
<?php endif ?>

<div class="card" style="padding:18px;margin-bottom:20px">
  <h3 style="font-size:15px;margin:0 0 6px">Las puertas de entrada</h3>
  <p style="font-size:13px;color:#64748b;margin:0 0 14px">
    Pega cada dirección en la plataforma correspondiente. Las credenciales
    se cargan en <a href="/ajustes">Ajustes</a>; mientras falten, esa puerta
    rechaza lo que llegue.
  </p>

  <table class="table table-sm" style="font-size:13px">
    <thead><tr><th>Plataforma</th><th>Dirección del webhook</th><th>Estado</th></tr></thead>
    <tbody>
    <?php foreach ($canales as $c):
            list($ruta, $rotulo, $llave) = $c;
            $lista = !empty($estado[$llave]['lista']); ?>
      <tr>
        <td><?= P::e($rotulo) ?></td>
        <td><code style="font-size:12px;word-break:break-all"><?= P::e($host . '/canales/' . $base . '/' . $ruta) ?></code></td>
        <td>
          <?php if ($lista): ?>
            <span class="badge" style="background:#16a34a">Conectada</span>
          <?php else: ?>
            <span class="badge bg-secondary">Faltan credenciales</span>
          <?php endif ?>
        </td>
      </tr>
    <?php endforeach ?>
    </tbody>
  </table>

  <?php /* LinkedIn no manda webhook: se le consulta. Decirlo aquí evita
           que alguien pegue la dirección y espere leads que no vendrán. */ ?>
  <p style="font-size:12px;color:#94a3b8;margin:10px 0 0">
    LinkedIn no envía avisos: sus formularios se consultan por API. Esa
    dirección existe para pruebas, no para pegarla en LinkedIn.
  </p>
</div>

<h3 style="font-size:15px;margin:0 0 10px">Lo que ha entrado</h3>

<?php if (!$entradas): ?>
  <p data-lf-vacio style="text-align:center;color:var(--lf-tinta-4);font-size:13px;padding:34px">
    Todavía no ha entrado nada. Cuando una plataforma mande su primer
    lead, aparecerá aquí aunque se descarte.</p>
<?php else: ?>

<?php /* La bitácora cruda importa: cuando alguien dice «me llegó un lead
         y no aparece», esto es lo único que distingue entre que la
         plataforma no lo mandó y que nosotros lo descartamos. */ ?>
<div style="overflow-x:auto">
<table class="table table-sm" style="font-size:13px">
  <thead><tr>
    <th>Cuándo</th><th>Plataforma</th><th>Resultado</th><th>Prospecto</th>
  </tr></thead>
  <tbody>
  <?php foreach ($entradas as $e): ?>
    <tr>
      <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($e['creado_en'])) ?></td>
      <td><?= P::e($e['plataforma']) ?></td>
      <td><span class="badge" style="background:<?= $colorRes($e['resultado']) ?>">
            <?= P::e($e['resultado']) ?></span></td>
      <td>
        <?php if (!empty($e['prospecto_id'])): ?>
          <a href="/prospectos/<?= (int)$e['prospecto_id'] ?>"><?= P::e($e['prospecto'] ?: 'ver') ?></a>
        <?php else: ?>
          <span style="color:#94a3b8">—</span>
        <?php endif ?>
      </td>
    </tr>
  <?php endforeach ?>
  </tbody>
</table>
</div>
<?php endif ?>
