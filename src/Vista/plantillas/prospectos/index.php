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
/* El color viene de la empresa (migración 30). Los de aquí son el
   respaldo para una empresa que todavía no los configuró. */
$colorEtapa = ($colores ?? []) + [
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
<?php $i = 0; foreach ($tablero as $clave => $lista):
        $suma = 0; foreach ($lista as $x) $suma += (float)$x['valor']; ?>
  <section class="pz-col" data-etapa="<?= P::e($clave) ?>" style="--i:<?= $i++ ?>">
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
            <span class="pz-sem" title="<?= P::e($semaforos[$p['semaforo']] ?? '') ?>"></span>
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
  <section class="pz-col pz-salida" data-etapa="ganado" style="--i:<?= $i++ ?>">
    <header style="--c:#16a34a"><span class="pz-tit">Ganado</span></header>
    <div class="pz-pila"><p class="pz-vacio">Suelta para cerrar</p></div>
  </section>
  <section class="pz-col pz-salida" data-etapa="perdido" style="--i:<?= $i++ ?>">
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
.pz-tablero{display:flex;gap:14px;overflow-x:auto;padding:4px 4px 16px;
  align-items:flex-start;scroll-snap-type:x proximity;scroll-behavior:smooth}

/* Las columnas entran escalonadas: la primera antes que la última, con
   40 ms de diferencia. El ojo lo lee como «se está armando» en vez de
   como «apareció de golpe», y cuesta una variable. */
.pz-col{flex:0 0 272px;scroll-snap-align:start;background:var(--lf-sup-2);
  border:none;border-radius:var(--lf-r);display:flex;flex-direction:column;
  max-height:calc(100vh - 230px);
  animation:pzCol .34s var(--crm-ease) both;animation-delay:calc(var(--i,0) * 40ms);
  transition:background-color .18s var(--crm-ease),transform .18s var(--crm-ease)}
@keyframes pzCol{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
.pz-col.pz-salida{flex:0 0 148px;background:transparent;
  box-shadow:inset 0 0 0 2px var(--lf-linea)}
.pz-col>header{padding:13px 14px 10px;display:flex;align-items:center;gap:8px;flex-wrap:wrap}

/* El color de la etapa es un punto, no una franja. Una línea de 3px
   cruzando cada columna compite con el contenido; un punto de 7px dice
   lo mismo y desaparece cuando no lo buscas. */
.pz-tit{font-weight:650;font-size:13.5px;flex:1;letter-spacing:-.01em;
  display:flex;align-items:center;gap:7px}
.pz-tit::before{content:"";width:7px;height:7px;border-radius:50%;
  background:var(--c);flex:0 0 auto}
.pz-n{background:var(--lf-vidrio);border-radius:999px;padding:2px 9px;
  font-size:11px;font-weight:600;color:var(--lf-tinta-3)}
.pz-col>header small{width:100%;font-size:11px;color:var(--lf-tinta-4);
  letter-spacing:.02em}
.pz-pila{padding:0 10px 10px;display:flex;flex-direction:column;gap:8px;
  overflow-y:auto;flex:1;min-height:76px}

/* ── La tarjeta ──
   Sin borde. El semáforo es un punto en el pie, no una barra lateral:
   lo que define a este lead es su nombre, no su color. */
.pz-tar{background:var(--lf-sup);border:none;border-radius:var(--lf-r-sm);
  padding:11px 12px;cursor:grab;box-shadow:var(--crm-e1);
  transition:box-shadow .18s var(--crm-ease),transform .18s var(--crm-ease);
  animation:pzTar .28s var(--crm-ease) both;will-change:transform}
@keyframes pzTar{from{opacity:0;transform:scale(.97)}to{opacity:1;transform:none}}
.pz-tar:hover{box-shadow:var(--crm-e2);transform:translateY(-2px)}
.pz-tar:active{cursor:grabbing}

/* Al arrastrar: se inclina y se levanta, como una ficha que se despega
   de la mesa. Es el momento en que la interfaz tiene que sentirse
   física, porque el gesto lo es. */
.pz-tar.arrastrando{opacity:.92;transform:rotate(2.5deg) scale(1.04);
  box-shadow:var(--crm-e3);cursor:grabbing;transition:none}

.pz-nom{background:none;border:none;padding:0;text-align:left;cursor:pointer;
  font:inherit;font-weight:650;font-size:13.5px;color:var(--lf-tinta);
  display:block;line-height:1.3;letter-spacing:-.01em;
  transition:color .14s var(--crm-ease)}
.pz-nom:hover{color:var(--lf-brand-2)}
.pz-asunto{font-size:12px;color:var(--lf-tinta-3);margin-top:2px;line-height:1.35}
.pz-val{font-size:12.5px;font-weight:650;margin-top:5px;letter-spacing:-.01em}

/* La tarea: una pastilla, no una línea de texto. Lo vencido se nota sin
   leerlo. */
.pz-tarea{font-size:11px;margin-top:8px;display:inline-flex;align-items:center;
  gap:5px;padding:3px 9px;border-radius:999px;
  background:var(--lf-vidrio);color:var(--lf-tinta-3)}
.pz-tarea.venc{background:color-mix(in srgb, var(--lf-rojo) 13%, transparent);
  color:var(--lf-rojo);font-weight:600}
.pz-tarea.falta{background:color-mix(in srgb, var(--lf-amb) 15%, transparent);
  color:color-mix(in srgb, var(--lf-amb) 78%, #000)}

.pz-tar>footer{display:flex;align-items:center;gap:7px;margin-top:9px;
  padding-top:8px;box-shadow:inset 0 1px 0 var(--lf-linea);font-size:11px}
.pz-resp{color:var(--lf-tinta-4);flex:1;overflow:hidden;text-overflow:ellipsis;
  white-space:nowrap}
.pz-canal{font-size:13px;line-height:1}
/* El semáforo, al final y como punto. */
.pz-sem{width:7px;height:7px;border-radius:50%;background:var(--lf-brand);flex:0 0 auto}
.pz-tar.s-amarillo .pz-sem{background:var(--lf-amb)}
.pz-tar.s-rojo .pz-sem{background:var(--lf-rojo)}

.pz-vacio{font-size:11px;color:var(--lf-tinta-4);text-align:center;margin:auto;
  padding:14px;transition:color .18s var(--crm-ease)}
.pz-nada{text-align:center;color:var(--lf-tinta-4);font-size:13px;padding:46px}

/* La columna que recibe: se tiñe y se levanta. Un contorno punteado es
   lo que hace todo el mundo y además se pelea con el redondeo. */
.pz-col.encima{background:color-mix(in srgb, var(--lf-brand) 9%, var(--lf-sup-2));
  transform:translateY(-3px)}
.pz-col.pz-salida.encima{box-shadow:inset 0 0 0 2px var(--lf-brand)}
.pz-col.encima .pz-vacio{color:var(--lf-brand-2)}

.pz-barra{display:flex;justify-content:space-between;align-items:center;gap:16px;
  flex-wrap:wrap;margin-bottom:16px}
.pz-cifras{display:flex;gap:22px;flex-wrap:wrap;font-size:12.5px;color:var(--lf-tinta-4)}
.pz-cifras b{color:var(--lf-tinta);font-size:17px;font-weight:650;
  letter-spacing:-.02em;margin-right:4px}
.pz-cifras .ok b{color:var(--lf-brand)}
.pz-cifras .urge b{color:var(--lf-rojo)}

.pz-dlg{border:none;border-radius:14px;padding:22px;max-width:620px;width:94vw;
  background:var(--lf-surface);color:var(--lf-tinta)}
.pz-dlg::backdrop{background:rgba(15,23,42,.45)}
.pz-dlg h3{margin:0 0 14px;font-size:17px}
.pz-campos{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:11px}
.pz-campos label{font-size:12px;color:var(--lf-tinta-2);display:flex;flex-direction:column;gap:3px}
.pz-campos span{color:var(--lf-danger)}
.pz-acc{display:flex;justify-content:flex-end;gap:8px;margin-top:16px}
@media (max-width:640px){ .pz-col{flex-basis:84vw} .pz-col.pz-salida{flex-basis:46vw} }
/* ── El panel lateral ──
   Entra deslizando desde el borde, no apareciendo. La diferencia entre
   las dos cosas es si el ojo entiende DE DÓNDE salió el panel: si surge
   de la nada, hay que volver a buscar dónde estabas. */
.pz-panel{position:fixed;top:0;right:0;height:100%;width:440px;max-width:94vw;
  background:var(--lf-sup);border-left:none;box-shadow:var(--crm-e3);
  z-index:60;display:flex;flex-direction:column;
  animation:pzEntra .3s var(--crm-ease) both;will-change:transform}
@keyframes pzEntra{from{transform:translateX(100%)}to{transform:none}}
@keyframes pzSale{from{transform:none}to{transform:translateX(100%)}}
.pz-panel>header{display:flex;align-items:flex-start;gap:12px;padding:20px 22px 16px}
.pz-panel h3{margin:0;font-size:19px;line-height:1.2;letter-spacing:-.02em;font-weight:650}
.pz-panel header small{color:var(--lf-tinta-4);font-size:12px;letter-spacing:.01em}
.pz-x{background:none;border:none;font-size:22px;line-height:1;cursor:pointer;
  color:var(--lf-tinta-4);padding:4px 7px;border-radius:999px;
  transition:background-color .16s var(--crm-ease),color .16s var(--crm-ease),
             transform .16s var(--crm-ease)}
.pz-x:hover{background:var(--lf-vidrio);color:var(--lf-tinta);transform:rotate(90deg)}
.pz-panel-cuerpo{padding:0 22px 22px;overflow-y:auto;flex:1}

/* Cada bloque entra un poco después del anterior: el panel se lee de
   arriba abajo y así se presenta en ese orden. */
.pz-panel-cuerpo > *{animation:pzBloque .3s var(--crm-ease) both}
.pz-panel-cuerpo > *:nth-child(2){animation-delay:.05s}
.pz-panel-cuerpo > *:nth-child(3){animation-delay:.1s}
@keyframes pzBloque{from{opacity:0;transform:translateY(8px)}to{opacity:1;transform:none}}

.pz-panel dl{display:grid;grid-template-columns:auto 1fr;gap:7px 14px;
  margin:0 0 20px;font-size:13px}
.pz-panel dt{color:var(--lf-tinta-4);font-size:12px}
.pz-panel dd{margin:0}
.pz-bloque{padding-top:18px;margin-top:18px;box-shadow:inset 0 1px 0 var(--lf-linea)}
.pz-bloque h4{margin:0 0 10px;font-size:11.5px;letter-spacing:.055em;
  text-transform:uppercase;color:var(--lf-tinta-4);font-weight:600}
.pz-fila{display:flex;gap:8px;margin-top:8px}

/* La bitácora: una línea de tiempo, con el punto de cada entrada sobre
   la línea. Sin el punto son párrafos sangrados; con él, una secuencia. */
.pz-notas{list-style:none;padding:0;margin:14px 0 0;position:relative}
.pz-notas li{padding:0 0 15px 18px;font-size:12.5px;position:relative}
.pz-notas li::before{content:"";position:absolute;left:0;top:5px;
  width:7px;height:7px;border-radius:50%;background:var(--lf-linea-f)}
.pz-notas li::after{content:"";position:absolute;left:3px;top:14px;bottom:0;
  width:1px;background:var(--lf-linea)}
.pz-notas li:last-child::after{display:none}
.pz-notas b{display:block;font-weight:400;line-height:1.45}
.pz-notas small{color:var(--lf-tinta-4);font-size:11px}
.pz-velo{position:fixed;inset:0;background:rgba(16,32,24,.3);z-index:59;
  backdrop-filter:blur(2px);animation:pzVelo .22s var(--crm-ease) both}
@keyframes pzVelo{from{opacity:0}to{opacity:1}}
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
    //
    // Y se mueve DESLIZÁNDOSE, no saltando. La técnica es medir dónde
    // estaba, cambiarlo de sitio, devolverlo visualmente a donde estaba
    // con un `transform`, y quitarlo en el cuadro siguiente: el
    // navegador anima la diferencia. Mover el nodo y ya daría un salto
    // seco justo en el gesto que más tiene que sentirse físico.
    var antes = tarjeta.getBoundingClientRect();
    col.querySelector('.pz-pila').appendChild(tarjeta);
    var hueco = col.querySelector('.pz-vacio');
    if (hueco) hueco.style.display = 'none';

    var despues = tarjeta.getBoundingClientRect();
    var dx = antes.left - despues.left, dy = antes.top - despues.top;
    if (dx || dy) {
      tarjeta.style.transition = 'none';
      tarjeta.style.transform  = 'translate(' + dx + 'px,' + dy + 'px)';
      requestAnimationFrame(function () {
        tarjeta.style.transition = 'transform .3s var(--crm-ease)';
        tarjeta.style.transform  = '';
        setTimeout(function () {
          tarjeta.style.transition = ''; tarjeta.style.transform = '';
        }, 320);
      });
    }

    // La columna de origen puede quedarse vacía: su texto vuelve.
    var vacioOrigen = desde.querySelector('.pz-vacio');
    if (vacioOrigen && !desde.querySelectorAll('.pz-tar').length) {
      vacioOrigen.style.display = '';
    }

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

/* Cerrar también se anima. Un panel que desaparece de golpe deja al ojo
   preguntándose si se cerró o si falló algo; uno que se va por donde
   vino no deja duda. */
window.pzCerrar = function () {
  if (pzPanel.hidden) return;
  pzPanel.style.animation = 'pzSale .22s var(--crm-ease) forwards';
  pzVelo.style.animation  = 'pzVelo .22s var(--crm-ease) reverse forwards';
  setTimeout(function () {
    pzPanel.hidden = true; pzVelo.hidden = true;
    pzPanel.style.animation = ''; pzVelo.style.animation = '';
    pzId = null;
  }, 200);
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
  pzPanel.style.animation = ''; pzVelo.style.animation = '';
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
