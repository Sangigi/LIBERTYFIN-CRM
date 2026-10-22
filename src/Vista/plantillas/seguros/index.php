<?php
use LibertyFin\Vista\Plantilla as P;
use LibertyFin\Vista\Widget as W;
use LibertyFin\Dominio\Dinero as D;

if (empty($_SESSION['lf_token'])) $_SESSION['lf_token'] = bin2hex(random_bytes(16));
$token = $_SESSION['lf_token'];

/** El color por días restantes: vencida, esta semana, este mes. */
$colorDias = function ($d) {
    if ($d === null) return '#64748b';
    if ($d < 0)  return '#dc2626';
    if ($d <= 7) return '#d97706';
    return '#16a34a';
};
?>

<?php if ($aviso): ?>
<div class="alert alert-<?= $aviso['tipo']==='error'?'danger':'success' ?>" style="margin-bottom:18px">
  <?= W::icono('alerta','18px') ?><span><?= P::e($aviso['texto']) ?></span></div>
<?php endif ?>

<?php /* Lo que vence va ARRIBA. En una agencia el cliente no se pierde
         cuando se va: se pierde cuando nadie avisó que vencía el 14. */ ?>
<?php if ($porVencer): ?>
<div class="alert alert-warning" style="margin-bottom:18px">
  <?= W::icono('reloj','18px') ?>
  <span><strong><?= count($porVencer) ?></strong>
    <?= count($porVencer)===1?'póliza vence':'pólizas vencen' ?> pronto:
    <?php foreach (array_slice($porVencer,0,5) as $i => $q): ?>
      <?= $i?' · ':'' ?><a href="/seguros/<?= (int)$q['id'] ?>"><?= P::e($q['numero']) ?></a>
      (<?= (int)$q['dias'] < 0 ? 'vencida' : (int)$q['dias'].' d' ?>)
    <?php endforeach ?><?= count($porVencer)>5?' y '.(count($porVencer)-5).' más':'' ?>
  </span>
</div>
<?php endif ?>

<div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(160px,1fr));gap:12px;margin-bottom:20px">
  <div class="card" style="padding:14px"><div style="font-size:12px;color:#64748b">Pólizas vigentes</div>
    <div style="font-size:26px;font-weight:600"><?= (int)($resumen['n'] ?? 0) ?></div></div>
  <div class="card" style="padding:14px"><div style="font-size:12px;color:#64748b">Prima total</div>
    <div style="font-size:26px;font-weight:600"><?= D::pesos((float)($resumen['prima'] ?? 0)) ?></div></div>
  <div class="card" style="padding:14px"><div style="font-size:12px;color:#64748b">Comisión estimada</div>
    <div style="font-size:26px;font-weight:600;color:#16a34a"><?= D::pesos((float)($resumen['comision'] ?? 0)) ?></div></div>
  <div class="card" style="padding:14px"><div style="font-size:12px;color:#64748b">Por vencer (30 d)</div>
    <div style="font-size:26px;font-weight:600;color:#d97706"><?= (int)($resumen['por_vencer'] ?? 0) ?></div></div>
</div>

<form class="lf-filtros" method="get" action="/seguros" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <div class="lf-search"><?= W::icono('buscar','15px') ?>
    <input type="search" name="q" value="<?= P::e($filtros['q']) ?>" placeholder="Póliza, aseguradora o cliente"></div>
  <select class="form-select form-select-sm" name="ramo" style="width:auto" onchange="this.form.submit()">
    <option value="">Todos los ramos</option>
    <?php foreach ($ramos as $k=>$v): ?><option value="<?= P::e($k) ?>" <?= $filtros['ramo']===$k?'selected':'' ?>><?= P::e($v) ?></option><?php endforeach ?>
  </select>
  <select class="form-select form-select-sm" name="estado" style="width:auto" onchange="this.form.submit()">
    <option value="">Todos los estados</option>
    <?php foreach ($estados as $k=>$v): ?><option value="<?= P::e($k) ?>" <?= $filtros['estado']===$k?'selected':'' ?>><?= P::e($v) ?></option><?php endforeach ?>
  </select>
</form>

<?php if ($puedeEditar): ?>
<details class="card" style="padding:16px;margin-bottom:20px">
  <summary style="cursor:pointer;font-weight:600"><?= W::icono('mas','15px') ?> Registrar póliza</summary>
  <form method="post" action="/seguros/guardar" style="margin-top:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:12px">
    <input type="hidden" name="token" value="<?= P::e($token) ?>">
    <label>Número <span style="color:#dc2626">*</span><input class="form-control" name="numero" required maxlength="60"></label>
    <label>Aseguradora <span style="color:#dc2626">*</span><input class="form-control" name="aseguradora" required maxlength="120"></label>
    <label>Ramo <select class="form-select" name="ramo">
      <?php foreach ($ramos as $k=>$v): ?><option value="<?= P::e($k) ?>"><?= P::e($v) ?></option><?php endforeach ?></select></label>
    <label>Cliente <select class="form-select" name="cliente_id"><option value="0">Sin ligar</option>
      <?php foreach ($clientes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= P::e($c['nombre']) ?></option><?php endforeach ?></select></label>
    <label>Prima <input class="form-control" type="number" step="0.01" min="0" name="prima" value="0"></label>
    <label>Suma asegurada <input class="form-control" type="number" step="0.01" min="0" name="suma_asegurada" value="0"></label>
    <label>Comisión % <input class="form-control" type="number" step="0.01" min="0" max="100" name="comision_pct" value="0"></label>
    <label>Forma de pago <select class="form-select" name="forma_pago">
      <?php foreach ($formasPago as $k=>$v): ?><option value="<?= P::e($k) ?>"><?= P::e($v) ?></option><?php endforeach ?></select></label>
    <label>Inicio vigencia <input class="form-control" type="date" name="inicio_vigencia"></label>
    <label>Fin vigencia <input class="form-control" type="date" name="fin_vigencia"></label>
    <?php if ($equipo): ?>
    <label>Agente <select class="form-select" name="responsable_id"><option value="0">Sin asignar</option>
      <?php foreach ($equipo as $u): ?><option value="<?= (int)$u['id'] ?>"><?= P::e($u['nombre']) ?></option><?php endforeach ?></select></label>
    <?php endif ?>
    <div style="grid-column:1/-1"><button class="btn btn-primary" type="submit">Registrar</button></div>
  </form>
</details>
<?php endif ?>

<?php if (!$polizas): ?>
  <p data-lf-vacio style="text-align:center;color:var(--lf-tinta-4);font-size:13px;padding:34px">
    No hay pólizas con ese filtro.</p>
<?php else: ?>
<div style="overflow-x:auto"><table class="table table-sm" style="font-size:13px">
  <thead><tr><th>Póliza</th><th>Cliente</th><th>Ramo</th><th>Prima</th><th>Vence</th><th>Estado</th><th>Agente</th></tr></thead>
  <tbody>
  <?php foreach ($polizas as $p): $d = $p['dias']===null?null:(int)$p['dias']; ?>
    <tr>
      <td><a href="/seguros/<?= (int)$p['id'] ?>"><?= P::e($p['numero']) ?></a>
        <br><small style="color:#94a3b8"><?= P::e($p['aseguradora']) ?></small></td>
      <td><?= P::e($p['cliente'] ?: '—') ?></td>
      <td><?= P::e($ramos[$p['ramo']] ?? $p['ramo']) ?></td>
      <td><?= D::pesos((float)$p['prima']) ?></td>
      <td style="color:<?= $colorDias($d) ?>">
        <?php if ($p['fin_vigencia']): ?><?= date('d/m/Y', strtotime($p['fin_vigencia'])) ?>
          <br><small><?= $d < 0 ? 'vencida' : $d.' días' ?></small><?php else: ?>—<?php endif ?></td>
      <td><span class="badge bg-secondary"><?= P::e($estados[$p['estado']] ?? $p['estado']) ?></span></td>
      <td><?= $p['responsable'] ? P::e($p['responsable']) : '<span style="color:#d97706">sin asignar</span>' ?></td>
    </tr>
  <?php endforeach ?>
  </tbody></table></div>
<?php endif ?>
