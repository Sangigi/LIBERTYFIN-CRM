<?php
use LibertyFin\Vista\Plantilla as P;
use LibertyFin\Vista\Widget as W;
use LibertyFin\Dominio\Dinero as D;

if (empty($_SESSION['lf_token'])) $_SESSION['lf_token'] = bin2hex(random_bytes(16));
$token = $_SESSION['lf_token'];
$total = array_sum(array_map('count', $tablero));
$color = function ($s) { return ['verde'=>'#16a34a','amarillo'=>'#d97706','rojo'=>'#dc2626'][$s] ?? '#64748b'; };
?>

<?php if ($aviso): ?>
<div class="alert alert-<?= $aviso['tipo']==='error'?'danger':'success' ?>" style="margin-bottom:18px">
  <?= W::icono('alerta','18px') ?><span><?= P::e($aviso['texto']) ?></span></div>
<?php endif ?>

<?php /* Las fechas procesales van ARRIBA y en rojo. Un emplazamiento que
         se pasa no se recupera: fue petición expresa de la junta. */ ?>
<?php if ($proximos): ?>
<div class="alert alert-danger" style="margin-bottom:18px">
  <?= W::icono('reloj','18px') ?>
  <span><strong>Fechas encima:</strong>
  <?php foreach ($proximos as $i => $q): ?>
    <?= $i ? ' · ' : '' ?><a href="/legal/<?= (int)$q['id'] ?>"><?= P::e($q['folio']) ?></a>
    <?= P::e($q['proximo_paso'] ?: 'diligencia') ?>
    el <?= date('d/m', strtotime($q['proxima_fecha'])) ?>
  <?php endforeach ?></span>
</div>
<?php endif ?>

<form class="lf-filtros" method="get" action="/legal" style="margin-bottom:16px;display:flex;gap:8px;flex-wrap:wrap">
  <div class="lf-search"><?= W::icono('buscar','15px') ?>
    <input type="search" name="q" value="<?= P::e($filtros['q']) ?>" placeholder="Folio, título, cliente o expediente"></div>
  <select class="form-select form-select-sm" name="materia" style="width:auto" onchange="this.form.submit()">
    <option value="">Todas las materias</option>
    <?php foreach ($materias as $k => $v): ?>
      <option value="<?= P::e($k) ?>" <?= $filtros['materia']===$k?'selected':'' ?>><?= P::e($v) ?></option>
    <?php endforeach ?>
  </select>
</form>

<?php if ($puedeEditar): ?>
<details class="card" style="padding:16px;margin-bottom:20px">
  <summary style="cursor:pointer;font-weight:600"><?= W::icono('mas','15px') ?> Abrir asunto</summary>
  <form method="post" action="/legal/guardar" style="margin-top:14px;display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:12px">
    <input type="hidden" name="token" value="<?= P::e($token) ?>">
    <label>Título <span style="color:#dc2626">*</span>
      <input class="form-control" name="titulo" required maxlength="255" placeholder="Divorcio incausado Vega"></label>
    <label>Materia
      <select class="form-select" name="materia">
        <?php foreach ($materias as $k => $v): ?><option value="<?= P::e($k) ?>"><?= P::e($v) ?></option><?php endforeach ?>
      </select></label>
    <label>Cliente
      <select class="form-select" name="cliente_id">
        <option value="0">Sin ligar</option>
        <?php foreach ($clientes as $c): ?><option value="<?= (int)$c['id'] ?>"><?= P::e($c['nombre']) ?></option><?php endforeach ?>
      </select></label>
    <label>Juzgado <input class="form-control" name="juzgado" maxlength="160"></label>
    <label>Núm. expediente <input class="form-control" name="expediente_num" maxlength="60"></label>
    <label>Contraparte <input class="form-control" name="contraparte" maxlength="160"></label>
    <label>Cuantía <input class="form-control" type="number" step="0.01" min="0" name="cuantia" value="0"></label>
    <?php if ($equipo): ?>
    <label>Abogado
      <select class="form-select" name="responsable_id">
        <option value="0">Sin asignar</option>
        <?php foreach ($equipo as $u): ?><option value="<?= (int)$u['id'] ?>"><?= P::e($u['nombre']) ?></option><?php endforeach ?>
      </select></label>
    <?php endif ?>
    <div style="grid-column:1/-1"><button class="btn btn-primary" type="submit">Abrir asunto</button>
      <small style="color:#64748b;margin-left:10px">Se creará la lista de documentos de esa materia.</small></div>
  </form>
</details>
<?php endif ?>

<?php if (!$total): ?>
  <p data-lf-vacio style="text-align:center;color:var(--lf-tinta-4);font-size:13px;padding:34px">
    No hay asuntos abiertos con ese filtro.</p>
<?php else: ?>
<?php foreach ($tablero as $mat => $lista): if (!$lista) continue; ?>
  <h3 style="font-size:15px;margin:18px 0 10px"><?= P::e($materias[$mat] ?? $mat) ?>
    <span class="badge bg-secondary"><?= count($lista) ?></span></h3>
  <div style="overflow-x:auto"><table class="table table-sm" style="font-size:13px">
    <thead><tr><th>Folio</th><th>Asunto</th><th>Cliente</th><th>Etapa</th><th>Abogado</th><th>Siguiente</th></tr></thead>
    <tbody>
    <?php foreach ($lista as $a):
            $etapas = \LibertyFin\Datos\AsuntoRepo::etapasDe($a['materia']);
            $venc = $a['proxima_fecha'] && $a['proxima_fecha'] <= date('Y-m-d'); ?>
      <tr style="border-left:4px solid <?= $color($a['semaforo']) ?>">
        <td><a href="/legal/<?= (int)$a['id'] ?>"><?= P::e($a['folio']) ?></a></td>
        <td><?= P::e($a['titulo']) ?>
            <?php if ($a['expediente_num']): ?><br><small style="color:#94a3b8"><?= P::e($a['expediente_num']) ?></small><?php endif ?></td>
        <td><?= P::e($a['cliente'] ?: '—') ?></td>
        <td><?= P::e($etapas[$a['etapa']] ?? $a['etapa']) ?></td>
        <td><?= $a['responsable'] ? P::e($a['responsable']) : '<span style="color:#d97706">sin asignar</span>' ?></td>
        <td style="color:<?= $venc ? '#dc2626' : '#64748b' ?>">
          <?php if ($a['proxima_fecha']): ?>
            <?= P::e($a['proximo_paso'] ?: 'Diligencia') ?><br>
            <small><?= date('d/m/Y', strtotime($a['proxima_fecha'])) ?></small>
          <?php else: ?>—<?php endif ?></td>
      </tr>
    <?php endforeach ?>
    </tbody></table></div>
<?php endforeach ?>
<?php endif ?>
