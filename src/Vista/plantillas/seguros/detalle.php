<?php
use LibertyFin\Vista\Plantilla as P;
use LibertyFin\Vista\Widget as W;
use LibertyFin\Dominio\Dinero as D;

if (empty($_SESSION['lf_token'])) $_SESSION['lf_token'] = bin2hex(random_bytes(16));
$token = $_SESSION['lf_token'];
$dias = $p['dias'] === null ? null : (int)$p['dias'];
$comision = (float)$p['prima'] * (float)$p['comision_pct'] / 100;
?>

<?php if ($aviso): ?>
<div class="alert alert-<?= $aviso['tipo']==='error'?'danger':'success' ?>" style="margin-bottom:18px">
  <?= W::icono('alerta','18px') ?><span><?= P::e($aviso['texto']) ?></span></div>
<?php endif ?>

<p style="margin-bottom:14px"><a href="/seguros">&larr; Volver a las pólizas</a></p>

<?php if ($dias !== null && $dias <= 30): ?>
<div class="alert alert-<?= $dias < 0 ? 'danger' : 'warning' ?>" style="margin-bottom:18px">
  <?= W::icono('reloj','16px') ?>
  <?php if ($dias < 0): ?>
    <strong>Vencida</strong> desde el <?= date('d/m/Y', strtotime($p['fin_vigencia'])) ?>.
    Todavía se puede rehacer: por eso sigue a la vista.
  <?php else: ?>
    <strong>Vence en <?= $dias ?> días</strong>, el <?= date('d/m/Y', strtotime($p['fin_vigencia'])) ?>.
  <?php endif ?>
</div>
<?php endif ?>

<div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:18px;align-items:start">
<div class="card" style="padding:18px">
  <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <div><h2 style="margin:0;font-size:19px"><?= P::e($p['numero']) ?></h2>
      <div style="color:#64748b;font-size:13px"><?= P::e($p['aseguradora']) ?> ·
        <?= P::e($ramos[$p['ramo']] ?? $p['ramo']) ?></div></div>
    <span class="badge bg-secondary" style="height:fit-content"><?= P::e($estados[$p['estado']] ?? $p['estado']) ?></span>
  </div>

  <dl style="display:grid;grid-template-columns:auto 1fr;gap:6px 14px;margin:16px 0 0;font-size:14px">
    <dt style="color:#64748b">Cliente</dt><dd style="margin:0"><?= P::e($p['cliente'] ?: '—') ?></dd>
    <dt style="color:#64748b">Teléfono</dt><dd style="margin:0"><?= P::e($p['telefono'] ?: '—') ?></dd>
    <dt style="color:#64748b">Prima</dt><dd style="margin:0"><?= D::pesos((float)$p['prima']) ?>
      · <?= P::e($formasPago[$p['forma_pago']] ?? $p['forma_pago']) ?></dd>
    <dt style="color:#64748b">Suma asegurada</dt><dd style="margin:0"><?= D::pesos((float)$p['suma_asegurada']) ?></dd>
    <dt style="color:#64748b">Comisión</dt><dd style="margin:0"><?= rtrim(rtrim(number_format((float)$p['comision_pct'],2),'0'),'.') ?>%
      · <?= D::pesos($comision) ?></dd>
    <dt style="color:#64748b">Vigencia</dt><dd style="margin:0">
      <?= $p['inicio_vigencia'] ? date('d/m/Y', strtotime($p['inicio_vigencia'])) : '—' ?>
      a <?= $p['fin_vigencia'] ? date('d/m/Y', strtotime($p['fin_vigencia'])) : '—' ?></dd>
    <dt style="color:#64748b">Agente</dt><dd style="margin:0"><?= P::e($p['responsable'] ?: 'sin asignar') ?></dd>
  </dl>

  <?php /* Renovar NO sobrescribe: la anterior queda ligada. Si se
           sobrescribiera, un siniestro del año pasado no tendría contra
           qué cotejarse. */ ?>
  <?php if (!empty($p['renovada_de'])): ?>
    <div class="alert alert-info" style="margin:14px 0 0">
      Renueva a la póliza <a href="/seguros/<?= (int)$p['renovada_de'] ?>">anterior</a>.
    </div>
  <?php endif ?>
  <?php if (!empty($p['nota'])): ?>
    <p style="font-size:13px;color:#64748b;margin:14px 0 0"><?= P::e($p['nota']) ?></p>
  <?php endif ?>
</div>

<aside>
  <?php if ($puedeEditar): ?>
  <div class="card" style="padding:16px">
    <h3 style="font-size:15px;margin:0 0 4px">Renovar</h3>
    <p style="font-size:12px;color:#64748b;margin:0 0 10px">
      Nace una póliza nueva. Esta queda en el historial, no se borra.</p>
    <form method="post" action="/seguros/<?= (int)$p['id'] ?>/renovar">
      <input type="hidden" name="token" value="<?= P::e($token) ?>">
      <label style="display:block;margin-bottom:8px;font-size:13px">Número nuevo (si cambia)
        <input class="form-control form-control-sm" name="numero" value="<?= P::e($p['numero']) ?>" maxlength="60"></label>
      <label style="display:block;margin-bottom:8px;font-size:13px">Prima
        <input class="form-control form-control-sm" type="number" step="0.01" min="0" name="prima" value="<?= (float)$p['prima'] ?>"></label>
      <label style="display:block;margin-bottom:8px;font-size:13px">Inicio
        <input class="form-control form-control-sm" type="date" name="inicio_vigencia"></label>
      <label style="display:block;margin-bottom:12px;font-size:13px">Fin
        <input class="form-control form-control-sm" type="date" name="fin_vigencia"></label>
      <button class="btn btn-primary btn-sm w-100" type="submit">Renovar</button>
    </form>
  </div>

  <div class="card" style="padding:16px;margin-top:14px">
    <h3 style="font-size:15px;margin:0 0 12px">Estado</h3>
    <form method="post" action="/seguros/<?= (int)$p['id'] ?>/estado">
      <input type="hidden" name="token" value="<?= P::e($token) ?>">
      <select class="form-select form-select-sm" name="estado" style="margin-bottom:10px">
        <?php foreach ($estados as $k=>$v): ?>
          <option value="<?= P::e($k) ?>" <?= $k===$p['estado']?'selected':'' ?>><?= P::e($v) ?></option>
        <?php endforeach ?>
      </select>
      <input class="form-control form-control-sm" name="nota" maxlength="255" placeholder="Nota (opcional)" style="margin-bottom:10px">
      <button class="btn btn-outline-primary btn-sm w-100" type="submit">Guardar</button>
    </form>
  </div>
  <?php endif ?>
</aside>
</div>
