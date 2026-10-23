<?php
use LibertyFin\Vista\Plantilla as P;
use LibertyFin\Vista\Widget as W;
use LibertyFin\Dominio\Dinero as D;

if (empty($_SESSION['lf_token'])) $_SESSION['lf_token'] = bin2hex(random_bytes(16));
$token = $_SESSION['lf_token'];
$total = array_sum(array_map('count', $tablero));
$hoy   = date('Y-m-d');

/* El color de cada columna. El embudo se lee de izquierda a derecha, y
   el color acompaña el avance: frío al entrar, cálido al cerrar. */
$colorEtapa = [
    'nuevo'      => '#64748b',
    'contactado' => '#0ea5e9',
    'cita'       => '#6366f1',
    'propuesta'  => '#8b5cf6',
];
$iconoOrigen = [
    'whatsapp' => '💬', 'instagram' => '📷', 'facebook' => '👤',
    'tiktok'   => '🎵', 'linkedin'  => '💼', 'google'   => '🔍',
    'web'      => '🌐', 'referido'  => '🤝', 'llamada'  => '📞', 'manual' => '✍️',
];
?>

<?php if ($aviso): ?>
<div class="alert alert-<?= $aviso['tipo']==='error'?'danger':'success' ?>" style="margin-bottom:14px">
  <?= W::icono('alerta','18px') ?><span><?= P::e($aviso['texto']) ?></span></div>
<?php endif ?>

<div class="pz-barra">
  <div class="pz-cifras">
    <span><b><?= (int)$resumen['abiertos'] ?></b> abiertos</span>
    <span class="ok"><b><?= (int)$resumen['ganados'] ?></b> ganados</span>
    <span><b><?= D::pesos((float)$resumen['monto_ganado']) ?></b> esta semana</span>
    <?php if ($pendientes): ?>
      <span class="urge"><b><?= count($pendientes) ?></b> sin atender hoy</span>
    <?php endif ?>
  </div>
  <?php if ($puedeEditar): ?>
    <button class="btn btn-primary btn-sm" type="button" onclick="document.getElementById('pzAlta').showModal()">
      <?= W::icono('mas','14px') ?> Nuevo prospecto</button>
  <?php endif ?>
</div>

<?php /* ── EL TABLERO ──
   Las tarjetas se arrastran de columna a columna. Cada soltada manda un
   POST y actualiza la etapa; si falla, la tarjeta vuelve a su sitio en
   vez de quedarse donde el navegador la dejó, que es como se acaba
   viendo un tablero que no coincide con la base. */ ?>
<div class="pz-tablero" id="pzTablero">
<?php foreach ($tablero as $clave => $lista):
        $suma = 0; foreach ($lista as $x) $suma += (float)$x['valor']; ?>
  <section class="pz-col" data-etapa="<?= P::e($clave) ?>">
    <header style="--c:<?= $colorEtapa[$clave] ?? '#64748b' ?>">
      <span class="pz-tit"><?= P::e($etapas[$clave] ?? $clave) ?></span>
      <span class="pz-n"><?= count($lista) ?></span>
      <small><?= $suma > 0 ? D::pesos($suma) : '&nbsp;' ?></small>
    </header>
    <div class="pz-pila">
      <?php foreach ($lista as $p):
              $venc = $p['proxima_fecha'] && $p['proxima_fecha'] <= $hoy;
              $sinTarea = empty($p['proxima_fecha']); ?>
        <article class="pz-tar<?= $p['semaforo']!=='verde' ? ' s-'.P::e($p['semaforo']) : '' ?>"
                 draggable="<?= $puedeEditar ? 'true' : 'false' ?>"
                 data-id="<?= (int)$p['id'] ?>">
          <button type="button" class="pz-nom" data-abrir="<?= (int)$p['id'] ?>"><?= P::e($p['nombre']) ?></button>
          <?php if (!empty($p['asunto'])): ?>
            <div class="pz-asunto"><?= P::e($p['asunto']) ?></div>
          <?php endif ?>
          <?php if ((float)$p['valor'] > 0): ?>
            <div class="pz-val"><?= D::pesos((float)$p['valor']) ?></div>
          <?php endif ?>

          <?php /* La tarea. En Kommo un lead sin siguiente paso es un error
                   visible, no una tarjeta más: por eso se marca. */ ?>
          <?php if ($sinTarea): ?>
            <div class="pz-tarea falta">Sin siguiente paso</div>
          <?php else: ?>
            <div class="pz-tarea<?= $venc ? ' venc' : '' ?>">
              <?= W::icono('reloj','11px') ?>
              <?= P::e($p['proximo_paso'] ?: 'Seguimiento') ?> ·
              <?= date('d/m', strtotime($p['proxima_fecha'])) ?>
            </div>
          <?php endif ?>

          <footer>
            <span class="pz-canal" title="<?= P::e($origenes[$p['origen']] ?? $p['origen']) ?>">
              <?= $iconoOrigen[$p['origen']] ?? '•' ?></span>
            <span class="pz-resp"><?= $p['responsable']
                 ? P::e($p['responsable']) : '<em>sin asignar</em>' ?></span>
          </footer>
        </article>
      <?php endforeach ?>
      <?php if (!$lista): ?><p class="pz-vacio">Arrastra aquí</p><?php endif ?>
    </div>
  </section>
<?php endforeach ?>

  <?php /* Las dos salidas del embudo, al final y más angostas: se usan
           para soltar, casi nunca para mirar. */ ?>
  <section class="pz-col pz-salida" data-etapa="ganado">
    <header style="--c:#16a34a"><span class="pz-tit">Ganado</span></header>
    <div class="pz-pila"><p class="pz-vacio">Suelta para cerrar</p></div>
  </section>
  <section class="pz-col pz-salida" data-etapa="perdido">
    <header style="--c:#dc2626"><span class="pz-tit">Perdido</span></header>
    <div class="pz-pila"><p class="pz-vacio">Suelta para descartar</p></div>
  </section>
</div>

<?php if (!$total): ?>
  <p class="pz-nada">Todavía no hay prospectos. Cuando entre un mensaje por
    WhatsApp, Instagram o Facebook, aparecerá aquí solo.</p>
<?php endif ?>

<?php /* ── Alta ── */ ?>
<?php if ($puedeEditar): ?>
<dialog id="pzAlta" class="pz-dlg">
  <form method="post" action="/prospectos/guardar">
    <input type="hidden" name="token" value="<?= P::e($token) ?>">
    <h3>Nuevo prospecto</h3>
    <div class="pz-campos">
      <label>Nombre <span>*</span><input class="form-control" name="nombre" required maxlength="160"></label>
      <label>Teléfono<input class="form-control" name="telefono" maxlength="40"></label>
      <label>Correo<input class="form-control" type="email" name="correo" maxlength="160"></label>
      <label>Origen<select class="form-select" name="origen">
        <?php foreach ($origenes as $k=>$v): ?><option value="<?= P::e($k) ?>"><?= P::e($v) ?></option><?php endforeach ?>
      </select></label>
      <label>Asunto<input class="form-control" name="asunto" maxlength="255" placeholder="Divorcio, seguro de auto…"></label>
      <label>Valor<input class="form-control" type="number" step="0.01" min="0" name="valor" value="0"></label>
      <?php if ($puedeAsignar && $equipo): ?>
      <label>Responsable<select class="form-select" name="responsable_id"><option value="0">Sin asignar</option>
        <?php foreach ($equipo as $u): ?><option value="<?= (int)$u['id'] ?>"><?= P::e($u['nombre']) ?></option><?php endforeach ?>
      </select></label>
      <?php endif ?>
      <label>Siguiente paso<input class="form-control" name="proximo_paso" placeholder="Llamar para confirmar"></label>
      <label>¿Cuándo?<input class="form-control" type="date" name="proxima_fecha" value="<?= $hoy ?>"></label>
    </div>
    <div class="pz-acc">
      <button class="btn btn-sm" type="button" onclick="this.closest('dialog').close()">Cancelar</button>
      <button class="btn btn-primary btn-sm" type="submit">Registrar</button>
    </div>
  </form>
</dialog>
<?php endif ?>

<?php /* ── EL PANEL LATERAL ──
   En Kommo abrir un lead no te saca del embudo: se despliega a la
   derecha y el tablero sigue detrás. Se llena por JSON para no recargar
   nada, y se cierra con Escape o pulsando fuera. */ ?>
<aside id="pzPanel" class="pz-panel" hidden aria-label="Ficha del prospecto">
  <header>
    <div>
      <h3 id="pzpNombre">—</h3>
      <small id="pzpMeta"></small>
    </div>
    <button type="button" class="pz-x" onclick="pzCerrar()" aria-label="Cerrar">&times;</button>
  </header>
  <div class="pz-panel-cuerpo">
    <dl id="pzpDatos"></dl>

    <div class="pz-bloque">
      <h4>Siguiente paso</h4>
      <form id="pzpTarea">
        <input type="hidden" name="token" value="<?= P::e($token) ?>">
        <input class="form-control form-control-sm" name="proximo_paso" placeholder="Llamar, mandar propuesta…">
        <div class="pz-fila">
          <input class="form-control form-control-sm" type="date" name="proxima_fecha">
          <button class="btn btn-primary btn-sm" type="submit">Fijar</button>
        </div>
      </form>
    </div>

    <div class="pz-bloque">
      <h4>Seguimiento</h4>
      <form id="pzpNota">
        <input type="hidden" name="token" value="<?= P::e($token) ?>">
        <div class="pz-fila">
          <select class="form-select form-select-sm" name="tipo" style="width:auto">
            <option value="nota">Nota</option><option value="llamada">Llamada</option>
            <option value="correo">Correo</option><option value="whatsapp">WhatsApp</option>
            <option value="cita">Cita</option>
          </select>
          <input class="form-control form-control-sm" name="texto" required placeholder="Qué pasó" style="flex:1">
          <button class="btn btn-primary btn-sm" type="submit">Anotar</button>
        </div>
      </form>
      <ol id="pzpNotas" class="pz-notas"></ol>
    </div>
  </div>
</aside>
<div id="pzVelo" class="pz-velo" hidden onclick="pzCerrar()"></div>

<style>
.pz-barra{display:flex;justify-content:space-between;align-items:center;gap:14px;
  flex-wrap:wrap;margin-bottom:14px}
.pz-cifras{display:flex;gap:18px;flex-wrap:wrap;font-size:13px;color:var(--lf-tinta-3)}
.pz-cifras b{color:var(--lf-tinta);font-size:15px}
.pz-cifras .ok b{color:var(--lf-success)}
.pz-cifras .urge b{color:var(--lf-danger)}

/* El tablero rueda en horizontal: un embudo con seis etapas no cabe en
   una pantalla, y apilarlo en vertical le quita lo que lo hace útil. */
.pz-tablero{display:flex;gap:12px;overflow-x:auto;padding-bottom:14px;
  align-items:flex-start;scroll-snap-type:x proximity}
.pz-col{flex:0 0 268px;scroll-snap-align:start;background:var(--lf-surface-2);
  border:1px solid var(--lf-border);border-radius:12px;display:flex;flex-direction:column;
  max-height:calc(100vh - 230px)}
.pz-col.pz-salida{flex:0 0 150px}
.pz-col>header{padding:10px 12px;border-bottom:1px solid var(--lf-border);
  border-top:3px solid var(--c);border-radius:12px 12px 0 0;display:flex;
  align-items:center;gap:8px;flex-wrap:wrap}
.pz-tit{font-weight:600;font-size:13px;flex:1}
.pz-n{background:var(--lf-surface-3);border-radius:999px;padding:1px 8px;font-size:11px}
.pz-col>header small{width:100%;font-size:11px;color:var(--lf-tinta-3)}
.pz-pila{padding:10px;display:flex;flex-direction:column;gap:9px;overflow-y:auto;flex:1;min-height:70px}

.pz-tar{background:var(--lf-surface);border:1px solid var(--lf-border);
  border-left:3px solid var(--lf-success);border-radius:9px;padding:10px;
  cursor:grab;transition:box-shadow .12s,transform .12s}
.pz-tar:hover{box-shadow:0 2px 10px rgba(0,0,0,.09)}
.pz-tar.s-amarillo{border-left-color:var(--lf-warning)}
.pz-tar.s-rojo{border-left-color:var(--lf-danger)}
.pz-tar.arrastrando{opacity:.4;transform:rotate(1.5deg)}
.pz-nom{font-weight:600;font-size:13px;text-decoration:none;color:var(--lf-tinta);
  display:block;line-height:1.3}
.pz-asunto{font-size:12px;color:var(--lf-tinta-3);margin-top:1px}
.pz-val{font-size:12px;font-weight:600;margin-top:3px}
.pz-tarea{font-size:11px;margin-top:6px;color:var(--lf-tinta-3);display:flex;
  align-items:center;gap:4px}
.pz-tarea.venc{color:var(--lf-danger);font-weight:600}
.pz-tarea.falta{color:var(--lf-warning)}
.pz-tar>footer{display:flex;align-items:center;gap:6px;margin-top:8px;
  padding-top:7px;border-top:1px solid var(--lf-border);font-size:11px}
.pz-resp{color:var(--lf-tinta-3);flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.pz-canal{font-size:13px;line-height:1}
.pz-vacio{font-size:11px;color:var(--lf-tinta-4);text-align:center;margin:auto;padding:12px}
.pz-nada{text-align:center;color:var(--lf-tinta-4);font-size:13px;padding:40px}

/* La columna que recibe se enciende: sin eso, soltar es adivinar. */
.pz-col.encima{outline:2px dashed var(--lf-brand);outline-offset:-3px}

.pz-dlg{border:none;border-radius:14px;padding:22px;max-width:620px;width:94vw;
  background:var(--lf-surface);color:var(--lf-tinta)}
.pz-dlg::backdrop{background:rgba(15,23,42,.45)}
.pz-dlg h3{margin:0 0 14px;font-size:17px}
.pz-campos{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:11px}
.pz-campos label{font-size:12px;color:var(--lf-tinta-2);display:flex;flex-direction:column;gap:3px}
.pz-campos span{color:var(--lf-danger)}
.pz-acc{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}
@media (max-width:640px){ .pz-col{flex-basis:84vw} .pz-col.pz-salida{flex-basis:46vw} }
/* ── El panel lateral ── */
.pz-panel{position:fixed;top:0;right:0;height:100%;width:430px;max-width:94vw;
  background:var(--lf-surface);border-left:1px solid var(--lf-border);
  box-shadow:-8px 0 28px rgba(0,0,0,.16);z-index:60;display:flex;flex-direction:column;
  animation:pzEntra .16s ease-out}
@keyframes pzEntra{from{transform:translateX(26px);opacity:.4}to{transform:none;opacity:1}}
.pz-panel>header{display:flex;align-items:flex-start;gap:10px;padding:16px 18px;
  border-bottom:1px solid var(--lf-border)}
.pz-panel h3{margin:0;font-size:17px;line-height:1.25}
.pz-panel header small{color:var(--lf-tinta-3);font-size:12px}
.pz-x{background:none;border:none;font-size:26px;line-height:1;cursor:pointer;
  color:var(--lf-tinta-3);padding:0 2px}
.pz-panel-cuerpo{padding:16px 18px;overflow-y:auto;flex:1}
.pz-panel dl{display:grid;grid-template-columns:auto 1fr;gap:5px 12px;margin:0 0 18px;font-size:13px}
.pz-panel dt{color:var(--lf-tinta-3)}
.pz-panel dd{margin:0}
.pz-bloque{border-top:1px solid var(--lf-border);padding-top:14px;margin-top:14px}
.pz-bloque h4{margin:0 0 9px;font-size:13px;color:var(--lf-tinta-2)}
.pz-fila{display:flex;gap:7px;margin-top:7px}
.pz-notas{list-style:none;padding:0;margin:12px 0 0}
.pz-notas li{border-left:2px solid var(--lf-border);padding:0 0 12px 12px;font-size:12.5px}
.pz-notas b{display:block;font-weight:400}
.pz-notas small{color:var(--lf-tinta-4);font-size:11px}
.pz-velo{position:fixed;inset:0;background:rgba(15,23,42,.28);z-index:59}
.pz-nom{background:none;border:none;padding:0;text-align:left;cursor:pointer;
  font:inherit;font-weight:600;font-size:13px;color:var(--lf-tinta);display:block;line-height:1.3}
.pz-nom:hover{color:var(--lf-brand)}
@media (max-width:640px){ .pz-panel{width:100%} }
</style>

<script>
(function () {
  var tablero = document.getElementById('pzTablero');
  if (!tablero) return;
  var token = <?= json_encode($token) ?>;
  var arrastrada = null, origen = null;

  tablero.addEventListener('dragstart', function (e) {
    var t = e.target.closest('.pz-tar');
    if (!t) return;
    arrastrada = t;
    origen = t.closest('.pz-col');          // para devolverla si falla
    t.classList.add('arrastrando');
    e.dataTransfer.effectAllowed = 'move';
    e.dataTransfer.setData('text/plain', t.dataset.id);
  });

  tablero.addEventListener('dragend', function () {
    if (arrastrada) arrastrada.classList.remove('arrastrando');
    tablero.querySelectorAll('.encima').forEach(function (c) { c.classList.remove('encima'); });
  });

  tablero.addEventListener('dragover', function (e) {
    var col = e.target.closest('.pz-col');
    if (!col || !arrastrada) return;
    e.preventDefault();                      // sin esto no se puede soltar
    e.dataTransfer.dropEffect = 'move';
    tablero.querySelectorAll('.encima').forEach(function (c) { c.classList.remove('encima'); });
    col.classList.add('encima');
  });

  tablero.addEventListener('drop', function (e) {
    var col = e.target.closest('.pz-col');
    if (!col || !arrastrada) return;
    e.preventDefault();
    col.classList.remove('encima');

    var tarjeta = arrastrada, desde = origen, etapa = col.dataset.etapa;
    if (desde === col) return;

    // Perdido pide motivo. Un embudo donde la mitad se pierde «sin
    // motivo» no deja mejorar nada.
    var motivo = null;
    if (etapa === 'perdido') {
      motivo = prompt('¿Por qué se perdió?');
      if (!motivo) return;                   // cancelar no mueve nada
    }

    // Se mueve YA en pantalla: esperar al servidor para redibujar hace
    // que arrastrar se sienta roto. Si falla, vuelve sola.
    col.querySelector('.pz-pila').appendChild(tarjeta);
    var hueco = col.querySelector('.pz-vacio');
    if (hueco) hueco.style.display = 'none';

    var cuerpo = new URLSearchParams();
    cuerpo.set('token', token);
    if (motivo) cuerpo.set('motivo', motivo);
    else cuerpo.set('etapa', etapa);

    var url = '/prospectos/' + tarjeta.dataset.id + (motivo ? '/perder' : '/mover');

    fetch(url, {
      method: 'POST',
      headers: { 'X-LF-Json': '1', 'X-Requested-With': 'XMLHttpRequest',
                 'Content-Type': 'application/x-www-form-urlencoded' },
      body: cuerpo.toString(),
      credentials: 'same-origin'
    }).then(function (r) {
      // `perder` redirige en vez de contestar JSON: un 200 o un 302 son
      // los dos un sí.
      if (!r.ok) throw new Error('HTTP ' + r.status);
      if (motivo || etapa === 'ganado') location.reload();
    }).catch(function () {
      desde.querySelector('.pz-pila').appendChild(tarjeta);
      alert('No se pudo mover. La tarjeta volvió a su lugar.');
    });
  });
/* ── El panel lateral ── */
var pzPanel = document.getElementById('pzPanel');
var pzVelo  = document.getElementById('pzVelo');
var pzId    = null;

window.pzCerrar = function () {
  pzPanel.hidden = true; pzVelo.hidden = true; pzId = null;
};

function pzEscapar(s) {
  var d = document.createElement('div'); d.textContent = s == null ? '' : s;
  return d.innerHTML;
}

function pzPintar(d) {
  var p = d.p;
  document.getElementById('pzpNombre').textContent = p.nombre;
  document.getElementById('pzpMeta').textContent =
    (d.etapas[p.etapa] || p.etapa) + ' · ' + p.origen + ' · desde ' + p.creado;

  /* El teléfono y el correo van como enlaces: en un CRM se abren, no se
     leen. Desde el móvil, tocar el teléfono marca. */
  var filas = [];
  if (p.telefono) filas.push(['Teléfono',
    '<a href="tel:' + pzEscapar(p.telefono) + '">' + pzEscapar(p.telefono) + '</a>' +
    ' · <a href="https://wa.me/' + pzEscapar(p.telefono.replace(/[^0-9]/g, '')) +
    '" target="_blank" rel="noopener">WhatsApp</a>']);
  if (p.correo) filas.push(['Correo',
    '<a href="mailto:' + pzEscapar(p.correo) + '">' + pzEscapar(p.correo) + '</a>']);
  if (p.asunto) filas.push(['Asunto', pzEscapar(p.asunto)]);
  if (p.valor > 0) filas.push(['Valor', '$' + p.valor.toLocaleString('es-MX',
    {minimumFractionDigits: 2, maximumFractionDigits: 2})]);
  filas.push(['Responsable', p.responsable ? pzEscapar(p.responsable) :
    '<em style="color:var(--lf-warning)">sin asignar</em>']);

  document.getElementById('pzpDatos').innerHTML = filas.map(function (f) {
    return '<dt>' + f[0] + '</dt><dd>' + f[1] + '</dd>';
  }).join('');

  var ft = document.getElementById('pzpTarea');
  ft.proximo_paso.value  = p.proximo_paso || '';
  ft.proxima_fecha.value = p.proxima_fecha || '';

  document.getElementById('pzpNotas').innerHTML = d.notas.length
    ? d.notas.map(function (n) {
        return '<li><b>' + pzEscapar(n.texto) + '</b><small>' +
               pzEscapar(n.tipo) + ' · ' + pzEscapar(n.usuario) + ' · ' +
               pzEscapar(n.cuando) + '</small></li>';
      }).join('')
    : '<li style="border:none;padding-left:0;color:var(--lf-tinta-4)">Sin seguimiento todavía.</li>';
}

tablero.addEventListener('click', function (e) {
  var b = e.target.closest('[data-abrir]');
  if (!b) return;
  pzId = b.dataset.abrir;
  pzPanel.hidden = false; pzVelo.hidden = false;
  document.getElementById('pzpNombre').textContent = 'Cargando…';
  document.getElementById('pzpDatos').innerHTML = '';
  document.getElementById('pzpNotas').innerHTML = '';

  fetch('/prospectos/' + pzId + '/ficha', {
    headers: { 'X-LF-Json': '1', 'X-Requested-With': 'XMLHttpRequest' },
    credentials: 'same-origin'
  }).then(function (r) { return r.json(); })
    .then(function (d) { if (d.ok) pzPintar(d); else pzCerrar(); })
    .catch(function () {
      document.getElementById('pzpNombre').textContent = 'No se pudo cargar';
    });
});

document.addEventListener('keydown', function (e) {
  if (e.key === 'Escape' && !pzPanel.hidden) pzCerrar();
});

/* Los dos formularios del panel mandan sin recargar y vuelven a pintar.
   Recargar la página cerraría el panel y perdería el sitio del tablero,
   que es justo lo que este panel existe para evitar. */
function pzEnviar(form, ruta, despues) {
  form.addEventListener('submit', function (e) {
    e.preventDefault();
    if (!pzId) return;
    var datos = new URLSearchParams(new FormData(form));
    fetch('/prospectos/' + pzId + ruta, {
      method: 'POST',
      headers: { 'X-LF-Json': '1', 'X-Requested-With': 'XMLHttpRequest',
                 'Content-Type': 'application/x-www-form-urlencoded' },
      body: datos.toString(), credentials: 'same-origin'
    }).then(function (r) {
      if (!r.ok) throw new Error();
      if (despues) despues(form);
      return fetch('/prospectos/' + pzId + '/ficha', {
        headers: { 'X-LF-Json': '1', 'X-Requested-With': 'XMLHttpRequest' },
        credentials: 'same-origin'
      }).then(function (r2) { return r2.json(); })
        .then(function (d) { if (d.ok) pzPintar(d); });
    }).catch(function () { alert('No se pudo guardar.'); });
  });
}
pzEnviar(document.getElementById('pzpTarea'), '/tarea', null);
pzEnviar(document.getElementById('pzpNota'), '/anotar', function (f) { f.texto.value = ''; });
})();
</script>
