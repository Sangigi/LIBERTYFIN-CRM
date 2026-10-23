/* ════════════════════════════════════════════════════════
   LIBERTYFIN CRM · movimiento
   ════════════════════════════════════════════════════════

   LO QUE ESTE ARCHIVO NO HACE, Y POR QUÉ

   Tenía ondas al pulsar, números que subían desde cero y un fundido
   entre páginas. Se quitaron los tres.

   · La onda es de hace diez años y no dice nada que el estado activo no
     diga ya. Adornar un botón que ya responde es ruido.
   · Los números que cuentan son un truco de demo: la primera vez
     sorprenden, la número veinte estorban para leer la cifra.
   · El fundido ponía `opacity:0` antes de navegar, y cualquier clic que
     no acabara en una carga nueva dejaba la pantalla en blanco. Un
     parpadeo molesta; una pantalla en blanco es un fallo.

   Lo que queda es lo que hace falta y nada más. El movimiento que no
   comunica algo es decoración, y la decoración es lo que hace que una
   interfaz se vea barata.
   ════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var QUIETO = window.matchMedia &&
               window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ──────────────────────────────────────────────
     Avisos
     ──────────────────────────────────────────────
     Flotan abajo a la derecha. Antes vivían dentro de la página y la
     empujaban un centímetro justo después de guardar. */
  window.crmAviso = function (texto, tipo) {
    var caja = document.getElementById('crmAvisos');
    if (!caja) {
      caja = document.createElement('div');
      caja.id = 'crmAvisos'; caja.className = 'crm-avisos';
      caja.setAttribute('role', 'status');
      caja.setAttribute('aria-live', 'polite');
      document.body.appendChild(caja);
    }
    var a = document.createElement('div');
    a.className = 'crm-aviso' + (tipo === 'error' ? ' malo' : '');
    a.textContent = texto;
    caja.appendChild(a);
    setTimeout(function () {
      a.classList.add('yendose');
      setTimeout(function () { a.remove(); }, 240);
    }, tipo === 'error' ? 5200 : 2800);
  };

  document.addEventListener('DOMContentLoaded', function () {

    /* El escalonado de entrada.
       La animación vive en CSS y se dispara sola: esto solo reparte el
       retraso. Si este archivo no carga, todo entra a la vez, que está
       bien. Lo que NO puede pasar es que algo se quede invisible
       esperando a un script. */
    if (!QUIETO) {
      var n = 0;
      document.querySelectorAll('.card, .pz-col').forEach(function (el) {
        // Se corta a los ocho: con cuarenta tarjetas, la última
        // tardaría más de un segundo en aparecer y se leería como lento.
        el.style.animationDelay = Math.min(n++, 8) * 30 + 'ms';
      });
    }

    /* El aviso que viene del servidor pasa a flotante. */
    var fijo = document.querySelector('.alert[data-crm-flota]');
    if (fijo) {
      window.crmAviso(fijo.textContent.trim(),
                      fijo.classList.contains('alert-danger') ? 'error' : 'ok');
      fijo.remove();
    }

    /* El menú en móvil. */
    var abrir = document.getElementById('lfAbrirMenu') || document.querySelector('[data-lf-menu]');
    var side  = document.getElementById('lfSide');
    if (abrir && side) {
      abrir.addEventListener('click', function (e) {
        e.stopPropagation();
        side.classList.toggle('abierto');
      });
      document.addEventListener('click', function (e) {
        if (!side.classList.contains('abierto')) return;
        if (side.contains(e.target)) return;
        side.classList.remove('abierto');
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') side.classList.remove('abierto');
      });
    }
  });
})();
