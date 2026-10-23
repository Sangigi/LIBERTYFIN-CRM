<?php
use LibertyFin\Vista\Plantilla as P;
use LibertyFin\Vista\Widget as W;
use LibertyFin\Dominio\Dinero as D;

/* El color de cada etapa viene de la empresa; estos son el respaldo. */
$col = ($colores ?? []) + ['nuevo'=>'#b4b0a6','contactado'=>'#4a7fb5',
                           'cita'=>'#6b6bc4','propuesta'=>'#4baa5c'];
$colOrigen = ['whatsapp'=>'#25d366','instagram'=>'#e1306c','facebook'=>'#1877f2',
              'tiktok'=>'#010101','linkedin'=>'#0a66c2','google'=>'#ea4335',
              'web'=>'#8d887d','referido'=>'#8b5cf6','llamada'=>'#4a7fb5','manual'=>'#b0aba0'];

/* El monto más alto marca la altura del embudo. */
$maxM = 0; foreach ($porEtapa as $e) $maxM = max($maxM, $e['monto']);
$maxO = 0; foreach ($origenes as $o) $maxO = max($maxO, (int)$o['n']);

/* El tiempo en palabras. «hace 3 días» se entiende sin restar; una
   fecha obliga a mirar el calendario. */
$enPalabras = function ($fecha) {
    if (!$fecha) return ['Sin fecha', 'sin'];
    $d = (int)floor((strtotime($fecha) - strtotime(date('Y-m-d'))) / 86400);
    if ($d < -1) return ['hace ' . (-$d) . ' días', 'tarde'];
    if ($d === -1) return ['ayer', 'tarde'];
    if ($d === 0) return ['hoy', 'hoy'];
    if ($d === 1) return ['mañana', ''];
    return ['en ' . $d . ' días', ''];
};
?>

<?php if ($aviso): ?>
<div class="alert alert-<?= $aviso['tipo']==='error'?'danger':'success' ?>" style="margin-bottom:18px">
  <?= W::icono('alerta','17px') ?><span><?= P::e($aviso['texto']) ?></span></div>
<?php endif ?>

<?php /* ── EL EMBUDO, CON FORMA DE EMBUDO ──
   La altura de cada tramo es su dinero. Todos los CRM llaman «embudo» a
   cuatro barras iguales; con la forma real se ve de un golpe dónde se
   está atascando el trabajo. El mínimo del 18% existe para que una
   etapa casi vacía siga siendo legible: un tramo de dos píxeles no se
   puede ni señalar. */ ?>
<section class="hy-embudo">
  <div class="hy-eh">
    <h2>Embudo</h2>
    <span class="hy-tot">En juego <b><?= D::pesos((float)$enJuego) ?></b></span>
  </div>
  <div class="hy-flujo">
    <?php $i = 0; foreach ($porEtapa as $k => $e):
            $alt = $maxM > 0 ? max(18, round($e['monto'] / $maxM * 100)) : 18; ?>
      <a class="hy-tramo" href="/prospectos" title="<?= P::e($etapas[$k] ?? $k) ?>">
        <span class="hy-cuerpo" style="height:<?= $alt ?>%;background:<?= $col[$k] ?? '#b4b0a6' ?>;
              animation-delay:<?= $i++ * 70 ?>ms"></span>
        <span class="hy-pie">
          <b><?= P::e($etapas[$k] ?? $k) ?></b>
          <small><?= (int)$e['n'] ?> <?= $e['n'] == 1 ? 'prospecto' : 'prospectos' ?></small>
          <em><?= D::pesos((float)$e['monto']) ?></em>
        </span>
      </a>
    <?php endforeach ?>
  </div>
</section>

<?php /* ── LO QUE TE ESTÁ ESPERANDO ──
   No es «actividad reciente», que es el relleno habitual: es lo que se
   va a caer si nadie lo toca hoy. */ ?>
<section class="hy-bloque">
  <div class="hy-bh">
    <h2>Te están esperando</h2>
    <?php if ($esperan): ?><span class="hy-n"><?= count($esperan) ?></span><?php endif ?>
    <a href="/prospectos">Ver el embudo &rarr;</a>
  </div>

  <?php if (!$esperan): ?>
    <div class="hy-limpio">
      <b>Todo al día</b>
      No hay prospectos vencidos ni pendientes para hoy.
    </div>
  <?php else: foreach ($esperan as $n => $p):
          list($txt, $cls) = $enPalabras($p['proxima_fecha']);
          $ini = mb_strtoupper(mb_substr(trim($p['nombre']), 0, 2)); ?>
    <a class="hy-fila" href="/prospectos/<?= (int)$p['id'] ?>" style="animation-delay:<?= $n*55 ?>ms">
      <span class="hy-av" style="background:<?= $col[$p['etapa']] ?? '#b4b0a6' ?>"><?= P::e($ini) ?></span>
      <span class="hy-txt">
        <b><?= P::e($p['nombre']) ?></b>
        <small><?= P::e($p['asunto'] ?: ($etapas[$p['etapa']] ?? '')) ?>
          <?= $p['proximo_paso'] ? ' · ' . P::e($p['proximo_paso']) : '' ?></small>
      </span>
      <span class="hy-canal" style="background:<?= $colOrigen[$p['origen']] ?? '#b0aba0' ?>"
            title="<?= P::e($catOrigenes[$p['origen']] ?? $p['origen']) ?>"></span>
      <?php if ((float)$p['valor'] > 0): ?>
        <span class="hy-mon"><?= D::pesos((float)$p['valor']) ?></span>
      <?php endif ?>
      <span class="hy-cuando <?= $cls ?>"><?= P::e($txt) ?></span>
    </a>
  <?php endforeach; endif ?>
</section>

<div class="hy-par">
  <section class="card hy-tj">
    <h3>Hoy en la agenda</h3>
    <?php if (!$citas): ?>
      <p class="hy-vacio">Sin citas para hoy.</p>
    <?php else: foreach (array_slice($citas, 0, 6) as $c): ?>
      <div class="hy-ag">
        <span class="hy-pin" style="background:<?= ($c['estado'] ?? '') === 'cancelada'
              ? 'var(--lf-rojo)' : 'var(--lf-brand)' ?>"></span>
        <span class="hy-h"><?= date('H:i', strtotime($c['inicio'])) ?></span>
        <span class="hy-c">
          <?php /* `servicio` y `cliente_nombre` son los nombres reales de
             las columnas de lf_citas. Inventarlos es el error que no
             falla al revisar y revienta al pintar. */ ?>
          <b><?= P::e($c['servicio'] ?: 'Cita') ?></b>
          <small><?= P::e($c['cliente_nombre'] ?? '') ?>
            <?= !empty($c['especialista']) ? ' · ' . P::e($c['especialista']) : '' ?></small>
        </span>
      </div>
    <?php endforeach; endif ?>
  </section>

  <section class="card hy-tj">
    <h3>De dónde llegaron esta semana</h3>
    <?php if (!$origenes): ?>
      <p class="hy-vacio">Todavía no ha entrado ningún prospecto esta semana.</p>
    <?php else: $j = 0; foreach ($origenes as $o): ?>
      <div class="hy-cn">
        <span class="hy-pt" style="background:<?= $colOrigen[$o['origen']] ?? '#b0aba0' ?>"></span>
        <span class="hy-nm"><?= P::e($catOrigenes[$o['origen']] ?? $o['origen']) ?></span>
        <span class="hy-br"><i style="width:<?= $maxO ? round($o['n']/$maxO*100) : 0 ?>%;
          background:<?= $colOrigen[$o['origen']] ?? '#b0aba0' ?>;
          animation-delay:<?= $j++ * 60 ?>ms"></i></span>
        <span class="hy-vl"><?= (int)$o['n'] ?></span>
      </div>
    <?php endforeach; endif ?>
  </section>
</div>

<style>
.hy-embudo{margin-bottom:32px}
.hy-eh{display:flex;align-items:baseline;gap:12px;margin-bottom:16px}
.hy-eh h2{font-size:12.5px;font-weight:600;letter-spacing:.07em;color:var(--lf-tinta-3);
  text-transform:uppercase}
.hy-tot{margin-left:auto;font-size:13px;color:var(--lf-tinta-3)}
.hy-tot b{color:var(--lf-tinta);font-size:17px;font-weight:650;letter-spacing:-.025em}

.hy-flujo{display:flex;align-items:stretch;gap:5px;height:120px}
.hy-tramo{flex:1;display:flex;flex-direction:column;justify-content:flex-end;
  text-decoration:none;color:inherit}
.hy-cuerpo{border-radius:10px 10px 4px 4px;transform-origin:bottom;
  animation:hyCrece .55s var(--crm-e) both;transition:opacity .16s var(--crm-e)}
@keyframes hyCrece{from{transform:scaleY(.05);opacity:.25}to{transform:scaleY(1);opacity:1}}
.hy-tramo:hover .hy-cuerpo{opacity:.82}
.hy-pie{padding-top:9px;display:flex;flex-direction:column;gap:1px}
.hy-pie b{font-size:12px;font-weight:600}
.hy-pie small{font-size:11.5px;color:var(--lf-tinta-3)}
.hy-pie em{font-size:13.5px;font-weight:650;letter-spacing:-.02em;font-style:normal;margin-top:2px}

.hy-bloque{margin-bottom:28px}
.hy-bh{display:flex;align-items:center;gap:10px;margin-bottom:12px}
.hy-bh h2{font-size:16px;font-weight:650;letter-spacing:-.02em}
.hy-n{font-size:11.5px;font-weight:650;color:#fff;background:var(--lf-rojo);
  padding:1px 8px;border-radius:999px}
.hy-bh a{margin-left:auto;font-size:13px;color:var(--lf-tinta-3);text-decoration:none}
.hy-bh a:hover{color:var(--lf-brand)}

.hy-fila{display:flex;align-items:center;gap:14px;padding:13px 16px;
  background:var(--lf-sup);border:1px solid var(--lf-linea);border-radius:13px;
  margin-bottom:7px;text-decoration:none;color:inherit;
  animation:crmSube .34s var(--crm-e) both;
  transition:border-color .14s var(--crm-e),transform .14s var(--crm-e),
             box-shadow .14s var(--crm-e)}
.hy-fila:hover{border-color:var(--lf-linea-f);transform:translateX(3px);
  box-shadow:0 2px 12px #1918140f}
.hy-av{width:34px;height:34px;border-radius:10px;flex:0 0 auto;display:grid;
  place-items:center;font-size:12px;font-weight:650;color:#fff}
.hy-txt{flex:1;min-width:0}
.hy-txt b{display:block;font-size:14.5px;font-weight:600;letter-spacing:-.012em}
.hy-txt small{color:var(--lf-tinta-3);font-size:12.5px}
.hy-canal{width:7px;height:7px;border-radius:50%;flex:0 0 auto}
.hy-mon{font-size:14.5px;font-weight:650;letter-spacing:-.02em;white-space:nowrap}
.hy-cuando{font-size:11.5px;font-weight:600;padding:4px 9px;border-radius:7px;white-space:nowrap;
  background:var(--lf-sup-2);color:var(--lf-tinta-3)}
.hy-cuando.tarde{background:color-mix(in srgb,var(--lf-rojo) 10%,transparent);color:var(--lf-rojo)}
.hy-cuando.hoy{background:color-mix(in srgb,var(--lf-amb) 12%,transparent);color:var(--lf-amb)}

.hy-limpio{text-align:center;padding:38px 20px;border-radius:13px;
  background:color-mix(in srgb,var(--lf-brand) 7%,transparent);
  color:var(--lf-brand-2);font-size:14px}
.hy-limpio b{display:block;font-size:17px;font-weight:650;letter-spacing:-.02em;margin-bottom:3px}

.hy-par{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:30px}
@media(max-width:860px){.hy-par{grid-template-columns:1fr}}
.hy-tj{padding:20px 22px}
.hy-tj h3{font-size:14px;font-weight:650;letter-spacing:-.015em;margin:0 0 14px}
.hy-vacio{color:var(--lf-tinta-4);font-size:13px;margin:0}
.hy-ag{display:flex;gap:12px;padding:11px 0;border-bottom:1px solid var(--lf-linea)}
.hy-ag:last-child{border:none;padding-bottom:0}
.hy-pin{width:3px;border-radius:999px;flex:0 0 auto}
.hy-h{font-size:13px;font-weight:650;color:var(--lf-tinta-2);width:44px;flex:0 0 auto}
.hy-c b{font-size:14px;font-weight:600;display:block}
.hy-c small{color:var(--lf-tinta-3);font-size:12.5px}
.hy-cn{display:flex;align-items:center;gap:11px;font-size:13.5px;margin-bottom:11px}
.hy-cn:last-child{margin:0}
.hy-pt{width:9px;height:9px;border-radius:50%;flex:0 0 auto}
.hy-nm{flex:1;color:var(--lf-tinta-2)}
.hy-br{width:88px;height:5px;border-radius:3px;background:var(--lf-sup-2);overflow:hidden}
.hy-br i{display:block;height:100%;border-radius:3px;animation:hyAncho .7s var(--crm-e) both}
@keyframes hyAncho{from{width:0}}
.hy-vl{font-weight:650;font-size:13px;width:24px;text-align:right;letter-spacing:-.02em}
</style>
