<?php
use LibertyFin\Vista\Plantilla as P;
use LibertyFin\Vista\Widget as W;
use LibertyFin\Dominio\Dinero as D;

if (empty($_SESSION['lf_token'])) $_SESSION['lf_token'] = bin2hex(random_bytes(16));
$token = $_SESSION['lf_token'];

$colorDe = function ($s) {
    return ['verde' => '#16a34a', 'amarillo' => '#d97706', 'rojo' => '#dc2626'][$s] ?? '#64748b';
};
$cerrado = in_array($p['etapa'], ['ganado', 'perdido'], true);
?>

<?php if ($aviso): ?>
<div class="alert alert-<?= $aviso['tipo']==='error'?'danger':'success' ?>" style="margin-bottom:18px">
  <?= W::icono('alerta','18px') ?><span><?= P::e($aviso['texto']) ?></span>
</div>
<?php endif ?>

<p style="margin-bottom:14px"><a href="/prospectos">&larr; Volver al embudo</a></p>

<div style="display:grid;grid-template-columns:minmax(0,2fr) minmax(0,1fr);gap:18px;align-items:start">

  <div>
    <div class="card" style="padding:18px;border-left:5px solid <?= $colorDe($p['semaforo']) ?>">
      <div style="display:flex;justify-content:space-between;flex-wrap:wrap;gap:10px">
        <div>
          <h2 style="margin:0;font-size:20px"><?= P::e($p['nombre']) ?></h2>
          <?php if (!empty($p['asunto'])): ?>
            <div style="color:#64748b"><?= P::e($p['asunto']) ?></div>
          <?php endif ?>
        </div>
        <div style="text-align:right">
          <span class="badge" style="background:<?= $colorDe($p['semaforo']) ?>">
            <?= P::e($semaforos[$p['semaforo']] ?? $p['semaforo']) ?></span>
          <div style="font-size:13px;color:#64748b;margin-top:4px">
            <?= P::e($etapas[$p['etapa']] ?? $p['etapa']) ?></div>
        </div>
      </div>

      <?php if ($p['etapa'] === 'perdido' && !empty($p['motivo_cierre'])): ?>
        <?php /* El motivo es lo único que deja aprender del embudo. Va a la
                 vista, no escondido en la bitácora. */ ?>
        <div class="alert alert-danger" style="margin:14px 0 0">
          <strong>Se perdió:</strong> <?= P::e($p['motivo_cierre']) ?>
        </div>
      <?php endif ?>

      <?php if ($p['etapa'] === 'ganado' && !empty($p['cliente_id'])): ?>
        <div class="alert alert-success" style="margin:14px 0 0">
          Se convirtió en cliente.
          <a href="/clientes/<?= (int)$p['cliente_id'] ?>">Ver su ficha</a>
        </div>
      <?php endif ?>

      <dl style="display:grid;grid-template-columns:auto 1fr;gap:6px 14px;margin:16px 0 0;font-size:14px">
        <dt style="color:#64748b">Teléfono</dt>
        <dd style="margin:0"><?= $p['telefono'] ? P::e($p['telefono']) : '—' ?></dd>
        <dt style="color:#64748b">Correo</dt>
        <dd style="margin:0"><?= $p['correo'] ? P::e($p['correo']) : '—' ?></dd>
        <dt style="color:#64748b">Valor estimado</dt>
        <dd style="margin:0"><?= D::pesos((float)$p['valor']) ?></dd>
        <dt style="color:#64748b">Responsable</dt>
        <dd style="margin:0"><?= $p['responsable'] ? P::e($p['responsable']) : '<span style="color:#d97706">sin asignar</span>' ?></dd>
        <dt style="color:#64748b">Registrado</dt>
        <dd style="margin:0"><?= date('d/m/Y H:i', strtotime($p['creado_en'])) ?></dd>
      </dl>
    </div>

    <?php /* La bitácora. Sin esto el seguimiento depende de lo que el
             agente recuerde, que es justo lo que se pierde cuando se va. */ ?>
    <div class="card" style="padding:18px;margin-top:16px">
      <h3 style="font-size:15px;margin:0 0 12px">Seguimiento</h3>

      <?php if ($puedeEditar && !$cerrado): ?>
      <form method="post" action="/prospectos/<?= (int)$p['id'] ?>/anotar" style="margin-bottom:16px">
        <input type="hidden" name="token" value="<?= P::e($token) ?>">
        <div style="display:flex;gap:8px;align-items:flex-start">
          <select class="form-select form-select-sm" name="tipo" style="width:auto" aria-label="Tipo">
            <option value="nota">Nota</option>
            <option value="llamada">Llamada</option>
            <option value="correo">Correo</option>
            <option value="whatsapp">WhatsApp</option>
            <option value="cita">Cita</option>
          </select>
          <input class="form-control form-control-sm" name="texto" required maxlength="2000"
                 placeholder="Quedó de avisar el martes…">
          <button class="btn btn-primary btn-sm" type="submit">Anotar</button>
        </div>
      </form>
      <?php endif ?>

      <?php if (!$notas): ?>
        <p data-lf-vacio style="color:var(--lf-tinta-4);font-size:13px;margin:0">
          Todavía no hay seguimiento anotado.</p>
      <?php else: ?>
        <ol style="list-style:none;padding:0;margin:0">
        <?php foreach ($notas as $n): ?>
          <li style="border-left:2px solid #e2e8f0;padding:0 0 14px 14px;position:relative">
            <div style="font-size:13px"><?= P::e($n['texto']) ?></div>
            <div style="font-size:11px;color:#94a3b8;margin-top:2px">
              <?= P::e($n['tipo']) ?> ·
              <?= $n['usuario'] ? P::e($n['usuario']) : 'sistema' ?> ·
              <?= date('d/m/Y H:i', strtotime($n['creado_en'])) ?>
            </div>
          </li>
        <?php endforeach ?>
        </ol>
      <?php endif ?>
    </div>
  </div>

  <aside>
    <?php if ($puedeEditar && !$cerrado): ?>
    <div class="card" style="padding:16px">
      <h3 style="font-size:15px;margin:0 0 12px">Mover</h3>
      <form method="post" action="/prospectos/<?= (int)$p['id'] ?>/mover">
        <input type="hidden" name="token" value="<?= P::e($token) ?>">
        <label style="display:block;margin-bottom:10px">Etapa
          <select class="form-select form-select-sm" name="etapa">
            <?php foreach ($etapas as $k => $v): if ($k === 'perdido') continue; ?>
              <option value="<?= P::e($k) ?>" <?= $k === $p['etapa'] ? 'selected' : '' ?>><?= P::e($v) ?></option>
            <?php endforeach ?>
          </select></label>
        <label style="display:block;margin-bottom:12px">Semáforo
          <select class="form-select form-select-sm" name="semaforo">
            <?php foreach ($semaforos as $k => $v): ?>
              <option value="<?= P::e($k) ?>" <?= $k === $p['semaforo'] ? 'selected' : '' ?>><?= P::e($v) ?></option>
            <?php endforeach ?>
          </select></label>
        <button class="btn btn-primary btn-sm w-100" type="submit">Guardar</button>
      </form>
    </div>

    <?php /* Perder pide motivo SIEMPRE: `required` aquí y comprobado
             otra vez en el servidor, porque un required se quita con el
             inspector en diez segundos. */ ?>
    <div class="card" style="padding:16px;margin-top:14px">
      <h3 style="font-size:15px;margin:0 0 12px">Marcar como perdido</h3>
      <form method="post" action="/prospectos/<?= (int)$p['id'] ?>/perder"
            onsubmit="return confirm('¿Marcar este prospecto como perdido?')">
        <input type="hidden" name="token" value="<?= P::e($token) ?>">
        <input class="form-control form-control-sm" name="motivo" required maxlength="255"
               placeholder="¿Por qué se perdió?" style="margin-bottom:10px">
        <button class="btn btn-outline-danger btn-sm w-100" type="submit">Marcar perdido</button>
      </form>
    </div>
    <?php endif ?>
  </aside>
</div>
