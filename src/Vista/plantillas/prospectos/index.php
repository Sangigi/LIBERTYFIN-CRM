<?php
use LibertyFin\Vista\Plantilla as P;
use LibertyFin\Vista\Widget as W;
use LibertyFin\Dominio\Dinero as D;

if (empty($_SESSION['lf_token'])) $_SESSION['lf_token'] = bin2hex(random_bytes(16));
$token = $_SESSION['lf_token'];
$total = array_sum(array_map('count', $tablero));

/** El color del semáforo, como se acordó: verde sigue, amarillo pausa, rojo cancelado. */
$colorDe = function ($s) {
    return ['verde' => '#16a34a', 'amarillo' => '#d97706', 'rojo' => '#dc2626'][$s] ?? '#64748b';
};
?>

<?php if ($aviso): ?>
<div class="alert alert-<?= $aviso['tipo']==='error'?'danger':'success' ?>" style="margin-bottom:18px">
  <?= W::icono('alerta','18px') ?><span><?= P::e($aviso['texto']) ?></span>
</div>
<?php endif; ?>

<?php /* Lo vencido va ARRIBA. Era la queja de la junta: la cotización
         que se manda y nadie vuelve a tocar. Si hay que bajar a buscarlo,
         no se busca. */ ?>
<?php if ($pendientes): ?>
<div class="alert alert-warning" style="margin-bottom:18px">
  <?= W::icono('reloj','18px') ?>
  <span><strong><?= count($pendientes) ?></strong>
    <?= count($pendientes) === 1 ? 'prospecto necesita' : 'prospectos necesitan' ?> seguimiento hoy o antes:
    <?php foreach (array_slice($pendientes, 0, 4) as $i => $q): ?>
      <?= $i ? ', ' : '' ?><a href="/prospectos/<?= (int)$q['id'] ?>"><?= P::e($q['nombre']) ?></a>
    <?php endforeach ?><?= count($pendientes) > 4 ? ' y ' . (count($pendientes) - 4) . ' más' : '' ?>.
  </span>
</div>
<?php endif ?>

<div class="lf-tarjetas" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
  <div class="card" style="padding:14px">
    <div style="font-size:12px;color:#64748b">Abiertos</div>
    <div style="font-size:26px;font-weight:600"><?= (int)$resumen['abiertos'] ?></div>
  </div>
  <div class="card" style="padding:14px">
    <div style="font-size:12px;color:#64748b">Ganados esta semana</div>
    <div style="font-size:26px;font-weight:600;color:#16a34a"><?= (int)$resumen['ganados'] ?></div>
  </div>
  <div class="card" style="padding:14px">
    <div style="font-size:12px;color:#64748b">Monto ganado</div>
    <div style="font-size:26px;font-weight:600"><?= D::pesos((float)$resumen['monto_ganado']) ?></div>
  </div>
  <div class="card" style="padding:14px">
    <div style="font-size:12px;color:#64748b">Perdidos esta semana</div>
    <div style="font-size:26px;font-weight:600;color:#dc2626"><?= (int)$resumen['perdidos'] ?></div>
  </div>
</div>

<?php if ($puedeEditar): ?>
<details class="card" style="padding:16px;margin-bottom:20px">
  <summary style="cursor:pointer;font-weight:600"><?= W::icono('mas','15px') ?> Registrar prospecto</summary>
  <form method="post" action="/prospectos/guardar" style="margin-top:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
    <input type="hidden" name="token" value="<?= P::e($token) ?>">
    <label>Nombre <span style="color:#dc2626">*</span>
      <input class="form-control" name="nombre" required maxlength="160"></label>
    <label>Teléfono
      <input class="form-control" name="telefono" maxlength="40"></label>
    <label>Correo
      <input class="form-control" type="email" name="correo" maxlength="160"></label>
    <label>Origen
      <select class="form-select" name="origen">
        <?php foreach ($origenes as $k => $v): ?>
          <option value="<?= P::e($k) ?>"><?= P::e($v) ?></option>
        <?php endforeach ?>
      </select></label>
    <label>Asunto
      <input class="form-control" name="asunto" maxlength="255" placeholder="Divorcio, seguro de auto, local en renta…"></label>
    <label>Valor estimado
      <input class="form-control" type="number" step="0.01" min="0" name="valor" value="0"></label>
    <?php if ($puedeAsignar && $equipo): ?>
    <label>Responsable
      <select class="form-select" name="responsable_id">
        <option value="0">Sin asignar</option>
        <?php foreach ($equipo as $u): ?>
          <option value="<?= (int)$u['id'] ?>"><?= P::e($u['nombre']) ?></option>
        <?php endforeach ?>
      </select></label>
    <?php endif ?>
    <label>Siguiente paso
      <input class="form-control" name="proximo_paso" maxlength="255" placeholder="Llamar para confirmar"></label>
    <label>¿Cuándo?
      <input class="form-control" type="date" name="proxima_fecha"></label>
    <div style="grid-column:1/-1">
      <button class="btn btn-primary" type="submit">Registrar</button>
    </div>
  </form>
</details>
<?php endif ?>

<?php if (!$total): ?>
  <p data-lf-vacio style="text-align:center;color:var(--lf-tinta-4);font-size:13px;padding:34px">
    Todavía no hay prospectos. Cuando registres uno, o cuando entre un
    mensaje por WhatsApp, aparecerá aquí.</p>
<?php else: ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:14px;align-items:start">
<?php foreach ($tablero as $clave => $lista): ?>
  <section class="card" style="padding:12px">
    <header style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px">
      <strong style="font-size:14px"><?= P::e($etapas[$clave] ?? $clave) ?></strong>
      <span class="badge bg-secondary"><?= count($lista) ?></span>
    </header>

    <?php if (!$lista): ?>
      <p style="color:#94a3b8;font-size:13px;margin:0">Nada aquí.</p>
    <?php endif ?>

    <?php foreach ($lista as $p): ?>
      <article style="border:1px solid #e2e8f0;border-left:4px solid <?= $colorDe($p['semaforo']) ?>;border-radius:8px;padding:10px;margin-bottom:8px">
        <a href="/prospectos/<?= (int)$p['id'] ?>" style="font-weight:600;text-decoration:none"><?= P::e($p['nombre']) ?></a>
        <?php if (!empty($p['asunto'])): ?>
          <div style="font-size:12px;color:#64748b"><?= P::e($p['asunto']) ?></div>
        <?php endif ?>
        <?php if ((float)$p['valor'] > 0): ?>
          <div style="font-size:12px;font-weight:600"><?= D::pesos((float)$p['valor']) ?></div>
        <?php endif ?>
        <div style="font-size:11px;color:#94a3b8;margin-top:4px">
          <?= P::e($origenes[$p['origen']] ?? $p['origen']) ?>
          <?php if (!empty($p['responsable'])): ?> · <?= P::e($p['responsable']) ?>
          <?php else: ?> · <span style="color:#d97706">sin asignar</span><?php endif ?>
        </div>
        <?php if (!empty($p['proxima_fecha'])):
                $vencido = $p['proxima_fecha'] <= date('Y-m-d'); ?>
          <div style="font-size:11px;margin-top:4px;color:<?= $vencido ? '#dc2626' : '#64748b' ?>">
            <?= W::icono('reloj','11px') ?>
            <?= P::e($p['proximo_paso'] ?: 'Seguimiento') ?> ·
            <?= date('d/m', strtotime($p['proxima_fecha'])) ?>
          </div>
        <?php endif ?>

        <?php if ($puedeEditar): ?>
        <form method="post" action="/prospectos/<?= (int)$p['id'] ?>/mover" style="margin-top:8px;display:flex;gap:6px">
          <input type="hidden" name="token" value="<?= P::e($token) ?>">
          <select class="form-select form-select-sm" name="etapa" onchange="this.form.submit()" aria-label="Etapa">
            <?php foreach ($etapas as $k => $v): if ($k === 'perdido') continue; ?>
              <option value="<?= P::e($k) ?>" <?= $k === $p['etapa'] ? 'selected' : '' ?>><?= P::e($v) ?></option>
            <?php endforeach ?>
          </select>
          <select class="form-select form-select-sm" name="semaforo" onchange="this.form.submit()" aria-label="Semáforo" style="width:auto">
            <?php foreach ($semaforos as $k => $v): ?>
              <option value="<?= P::e($k) ?>" <?= $k === $p['semaforo'] ? 'selected' : '' ?>><?= P::e($v) ?></option>
            <?php endforeach ?>
          </select>
        </form>
        <?php endif ?>
      </article>
    <?php endforeach ?>
  </section>
<?php endforeach ?>
</div>
<?php endif ?>
