<?php
use LibertyFin\Vista\Plantilla as P;
use LibertyFin\Vista\Widget as W;
use LibertyFin\Dominio\Dinero as D;

if (empty($_SESSION['lf_token'])) $_SESSION['lf_token'] = bin2hex(random_bytes(16));
$token = $_SESSION['lf_token'];
$color = function ($s) { return ['verde'=>'#16a34a','amarillo'=>'#d97706','rojo'=>'#dc2626'][$s] ?? '#64748b'; };
$faltan = 0; foreach ($documentos as $d) if (!$d['recibido']) $faltan++;
?>

<?php if ($aviso): ?>
<div class="alert alert-<?= $aviso['tipo']==='error'?'danger':'success' ?>" style="margin-bottom:18px">
  <?= W::icono('alerta','18px') ?><span><?= P::e($aviso['texto']) ?></span></div>
<?php endif ?>

<p style="margin-bottom:14px"><a href="/legal">&larr; Volver a los asuntos</a></p>

<div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:18px;align-items:start">
<div>
  <div class="card" style="padding:18px;border-left:5px solid <?= $color($a['semaforo']) ?>">
    <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px">
      <div><h2 style="margin:0;font-size:19px"><?= P::e($a['titulo']) ?></h2>
        <div style="color:#64748b;font-size:13px"><?= P::e($a['folio']) ?>
          <?= $a['expediente_num'] ? ' · Exp. ' . P::e($a['expediente_num']) : '' ?></div></div>
      <div style="text-align:right">
        <span class="badge" style="background:<?= $color($a['semaforo']) ?>"><?= P::e($semaforos[$a['semaforo']] ?? '') ?></span>
        <div style="font-size:13px;color:#64748b;margin-top:4px"><?= P::e($etapas[$a['etapa']] ?? $a['etapa']) ?></div></div>
    </div>
    <dl style="display:grid;grid-template-columns:auto 1fr;gap:6px 14px;margin:16px 0 0;font-size:14px">
      <dt style="color:#64748b">Cliente</dt><dd style="margin:0"><?= P::e($a['cliente'] ?: '—') ?></dd>
      <dt style="color:#64748b">Juzgado</dt><dd style="margin:0"><?= P::e($a['juzgado'] ?: '—') ?></dd>
      <dt style="color:#64748b">Contraparte</dt><dd style="margin:0"><?= P::e($a['contraparte'] ?: '—') ?></dd>
      <dt style="color:#64748b">Cuantía</dt><dd style="margin:0"><?= D::pesos((float)$a['cuantia']) ?></dd>
      <dt style="color:#64748b">Abogado</dt><dd style="margin:0"><?= P::e($a['responsable'] ?: 'sin asignar') ?></dd>
    </dl>
    <?php if ($a['proxima_fecha']): $v = $a['proxima_fecha'] <= date('Y-m-d'); ?>
      <div class="alert alert-<?= $v?'danger':'warning' ?>" style="margin:14px 0 0">
        <?= W::icono('reloj','16px') ?>
        <strong><?= P::e($a['proximo_paso'] ?: 'Diligencia') ?></strong>
        el <?= date('d/m/Y', strtotime($a['proxima_fecha'])) ?>. Se avisa el día anterior.
      </div>
    <?php endif ?>
  </div>

  <?php /* El expediente ES su historia. Cada cambio de etapa deja
           actuación automática, por eso esta lista no se puede quedar
           vacía mientras el asunto avanza. */ ?>
  <div class="card" style="padding:18px;margin-top:16px">
    <h3 style="font-size:15px;margin:0 0 12px">Actuaciones</h3>
    <?php if ($puedeEditar): ?>
    <form method="post" action="/legal/<?= (int)$a['id'] ?>/anotar" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
      <input type="hidden" name="token" value="<?= P::e($token) ?>">
      <select class="form-select form-select-sm" name="tipo" style="width:auto">
        <option value="nota">Nota</option><option value="promocion">Promoción</option>
        <option value="audiencia">Audiencia</option><option value="acuerdo">Acuerdo</option>
        <option value="notificacion">Notificación</option>
      </select>
      <input class="form-control form-control-sm" type="date" name="fecha" style="width:auto">
      <input class="form-control form-control-sm" name="texto" required maxlength="2000" placeholder="Qué pasó" style="flex:1;min-width:180px">
      <button class="btn btn-primary btn-sm" type="submit">Registrar</button>
    </form>
    <?php endif ?>
    <?php if (!$actuaciones): ?>
      <p data-lf-vacio style="color:var(--lf-tinta-4);font-size:13px;margin:0">Sin actuaciones registradas.</p>
    <?php else: ?>
      <ol style="list-style:none;padding:0;margin:0">
      <?php foreach ($actuaciones as $ac): ?>
        <li style="border-left:2px solid #e2e8f0;padding:0 0 14px 14px">
          <div style="font-size:13px"><?= P::e($ac['texto']) ?></div>
          <div style="font-size:11px;color:#94a3b8;margin-top:2px">
            <?= P::e($ac['tipo']) ?> ·
            <?= $ac['fecha'] ? date('d/m/Y', strtotime($ac['fecha'])) : date('d/m/Y', strtotime($ac['creado_en'])) ?>
            · <?= $ac['usuario'] ? P::e($ac['usuario']) : 'sistema' ?></div>
        </li>
      <?php endforeach ?>
      </ol>
    <?php endif ?>
  </div>
</div>

<aside>
  <?php if ($puedeEditar): ?>
  <div class="card" style="padding:16px">
    <h3 style="font-size:15px;margin:0 0 12px">Etapa procesal</h3>
    <form method="post" action="/legal/<?= (int)$a['id'] ?>/mover">
      <input type="hidden" name="token" value="<?= P::e($token) ?>">
      <select class="form-select form-select-sm" name="etapa" style="margin-bottom:10px">
        <?php foreach ($etapas as $k => $v): ?>
          <option value="<?= P::e($k) ?>" <?= $k===$a['etapa']?'selected':'' ?>><?= P::e($v) ?></option>
        <?php endforeach ?>
      </select>
      <select class="form-select form-select-sm" name="semaforo" style="margin-bottom:12px">
        <?php foreach ($semaforos as $k => $v): ?>
          <option value="<?= P::e($k) ?>" <?= $k===$a['semaforo']?'selected':'' ?>><?= P::e($v) ?></option>
        <?php endforeach ?>
      </select>
      <button class="btn btn-primary btn-sm w-100" type="submit">Mover</button>
    </form>
  </div>

  <div class="card" style="padding:16px;margin-top:14px">
    <h3 style="font-size:15px;margin:0 0 12px">Próxima fecha</h3>
    <form method="post" action="/legal/<?= (int)$a['id'] ?>/fecha">
      <input type="hidden" name="token" value="<?= P::e($token) ?>">
      <input class="form-control form-control-sm" name="proximo_paso" maxlength="255"
             value="<?= P::e($a['proximo_paso']) ?>" placeholder="Emplazamiento, audiencia…" style="margin-bottom:10px">
      <input class="form-control form-control-sm" type="date" name="proxima_fecha"
             value="<?= P::e($a['proxima_fecha']) ?>" style="margin-bottom:10px">
      <button class="btn btn-outline-primary btn-sm w-100" type="submit">Fijar</button>
    </form>
  </div>
  <?php endif ?>

  <?php /* Lo que falta pedirle al cliente. Sin esta lista, «faltaba el
           acta» se descubre en ventanilla. */ ?>
  <div class="card" style="padding:16px;margin-top:14px">
    <h3 style="font-size:15px;margin:0 0 4px">Documentos</h3>
    <?php if ($faltan): ?>
      <p style="font-size:12px;color:#d97706;margin:0 0 10px">Faltan <?= $faltan ?>.</p>
    <?php else: ?>
      <p style="font-size:12px;color:#16a34a;margin:0 0 10px">Completos.</p>
    <?php endif ?>
    <?php foreach ($documentos as $d): ?>
      <form method="post" action="/legal/<?= (int)$a['id'] ?>/documento" style="display:flex;align-items:center;gap:8px;margin-bottom:6px">
        <input type="hidden" name="token" value="<?= P::e($token) ?>">
        <input type="hidden" name="doc_id" value="<?= (int)$d['id'] ?>">
        <input type="hidden" name="recibido" value="<?= $d['recibido'] ? '' : '1' ?>">
        <button type="submit" <?= $puedeEditar?'':'disabled' ?>
                style="background:none;border:none;cursor:pointer;padding:0;font-size:15px"
                title="<?= $d['recibido']?'Marcar como pendiente':'Marcar como recibido' ?>">
          <?= $d['recibido'] ? '✅' : '⬜' ?></button>
        <span style="font-size:13px;<?= $d['recibido']?'color:#94a3b8;text-decoration:line-through':'' ?>">
          <?= P::e($d['nombre']) ?></span>
      </form>
    <?php endforeach ?>
  </div>
</aside>
</div>
