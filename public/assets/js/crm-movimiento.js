/* ════════════════════════════════════════════════════════
   LIBERTYFIN CRM · la capa de movimiento
   ════════════════════════════════════════════════════════

   Lo que hace que una interfaz se sienta viva no son animaciones
   grandes: son muchas pequeñas que responden al instante. Aquí van las
   que el CSS no puede hacer solo porque necesitan saber algo —cuánto
   vale un número, dónde se pulsó, cuándo terminó de cargar.

   REGLAS QUE SE SIGUEN EN TODO EL ARCHIVO

   · Nada pasa de 400 ms. Por encima de eso deja de leerse como
     respuesta y empieza a leerse como espera.
   · Si la persona pidió menos movimiento, esto casi no hace nada. No es
     un detalle de cortesía: una animación mal puesta marea de verdad.
   · Nada aquí es necesario para usar el sistema. Si este archivo no
     carga, todo sigue funcionando igual, solo más seco.
   ════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var QUIETO = window.matchMedia &&
               window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  /* ──────────────────────────────────────────────
     1 · Los números suben
     ──────────────────────────────────────────────
     Un 47 que aparece es un dato. Un 47 que sube desde 0 es un dato que
     se mira. Solo en las cifras del encabezado: hacerlo en una tabla
     sería mareante y además ilegible mientras cambia. */
  function contar(el) {
    var texto = el.textContent.trim();
    var destino = parseFloat(texto.replace(/[^0-9.-]/g, ''));
    if (!isFinite(destino) || destino === 0) return;

    // El formato se conserva: lo que había antes y después del número
    // (el signo de pesos, un «%») vuelve a su sitio en cada paso.
    var antes = texto.slice(0, texto.search(/[0-9-]/));
    var despues = texto.slice(texto.search(/[0-9][^0-9]*$/) + 1).replace(/^[0-9.,]*/, '');
    var decimales = (texto.split('.')[1] || '').replace(/[^0-9]/g, '').length;

    var ini = performance.now(), dur = 620;
    function paso(t) {
      var k = Math.min((t - ini) / dur, 1);
      k = 1 - Math.pow(1 - k, 3);            // frena al final
      var v = destino * k;
      el.textContent = antes + v.toLocaleString('es-MX', {
        minimumFractionDigits: decimales, maximumFractionDigits: decimales
      }) + despues;
      if (k < 1) requestAnimationFrame(paso);
    }
    requestAnimationFrame(paso);
  }

  /* ──────────────────────────────────────────────
     2 · Lo que entra en pantalla, entra
     ──────────────────────────────────────────────
     Con IntersectionObserver y no con un temporizador: animar lo que
     todavía no se ve gasta cuadros en algo que nadie mira, y cuando la
     persona llega abajo la animación ya pasó. */
  var mirador = ('IntersectionObserver' in window) ? new IntersectionObserver(
    function (entradas) {
      entradas.forEach(function (e) {
        if (!e.isIntersecting) return;
        e.target.classList.add('crm-visible');
        if (e.target.dataset.crmContar !== undefined) contar(e.target);
        mirador.unobserve(e.target);
      });
    }, { rootMargin: '0px 0px -40px 0px', threshold: .15 }) : null;

  /* ──────────────────────────────────────────────
     3 · La onda al pulsar
     ──────────────────────────────────────────────
     Nace DONDE se tocó, no en el centro: así el botón responde al dedo
     en vez de limitarse a parpadear. Es lo que separa un botón que se
     siente pulsado de uno que solo cambia de color. */
  function onda(e) {
    var b = e.target.closest('.btn, .lf-nav a');
    if (!b || QUIETO) return;
    var caja = b.getBoundingClientRect();
    var d = Math.max(caja.width, caja.height) * 2;
    var s = document.createElement('span');
    s.className = 'crm-onda';
    s.style.cssText = 'width:' + d + 'px;height:' + d + 'px;left:' +
      (e.clientX - caja.left - d / 2) + 'px;top:' + (e.clientY - caja.top - d / 2) + 'px';
    // El botón tiene que recortar la onda, o se sale por las esquinas.
    if (getComputedStyle(b).position === 'static') b.style.position = 'relative';
    b.style.overflow = 'hidden';
    b.appendChild(s);
    setTimeout(function () { s.remove(); }, 560);
  }

  /* ──────────────────────────────────────────────
     4 · Avisos que se asoman
     ──────────────────────────────────────────────
     El aviso de «guardado» vivía dentro de la página y empujaba el
     contenido hacia abajo: justo después de guardar, todo se movía un
     centímetro. Pasa a flotar abajo a la derecha y se va solo. */
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
      setTimeout(function () { a.remove(); }, 260);
    }, tipo === 'error' ? 5200 : 2900);
  };

  /* ──────────────────────────────────────────────
     5 · Arranque
     ────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', function () {

    // Las cifras del encabezado del embudo.
    document.querySelectorAll('.pz-cifras b').forEach(function (el) {
      if (QUIETO) return;
      el.dataset.crmContar = '';
      if (mirador) mirador.observe(el); else contar(el);
    });

    // Lo que aparece al bajar: tarjetas y filas de tabla.
    if (mirador && !QUIETO) {
      document.querySelectorAll('.card, .table tbody tr').forEach(function (el, i) {
        el.classList.add('crm-aparece');
        // El escalonado se corta a los diez: con cincuenta filas, la
        // última tardaría dos segundos en llegar.
        el.style.transitionDelay = Math.min(i, 10) * 28 + 'ms';
        mirador.observe(el);
      });
    }

    document.addEventListener('pointerdown', onda);

    // El aviso que venía del servidor pasa a flotante.
    var viejo = document.querySelector('.alert[data-crm-flota]');
    if (viejo) {
      window.crmAviso(viejo.textContent.trim(),
                      viejo.classList.contains('alert-danger') ? 'error' : 'ok');
      viejo.remove();
    }

    // En móvil el menú se abre con el botón de siempre.
    var abrir = document.getElementById('lfAbrirMenu') || document.querySelector('[data-lf-menu]');
    var side  = document.getElementById('lfSide');
    if (abrir && side) {
      abrir.addEventListener('click', function () { side.classList.toggle('abierto'); });
      document.addEventListener('click', function (e) {
        if (!side.classList.contains('abierto')) return;
        if (side.contains(e.target) || abrir.contains(e.target)) return;
        side.classList.remove('abierto');
      });
    }
  });

  /* ──────────────────────────────────────────────
     6 · Al salir de la página
     ──────────────────────────────────────────────
     Un fundido corto antes de navegar. Sin esto, entre dos páginas hay
     un parpadeo en blanco que rompe la sensación de continuidad, y es
     lo que más delata que son páginas sueltas y no una aplicación.

     Solo en enlaces internos y sin teclas: un clic con Ctrl abre otra
     pestaña, y desvanecer esta sería desconcertante. */
  if (!QUIETO) {
    document.addEventListener('click', function (e) {
      var a = e.target.closest('a[href]');
      if (!a) return;
      if (a.target === '_blank' || a.hasAttribute('download')) return;
      if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
      var u = a.getAttribute('href');
      if (!u || u.charAt(0) === '#' || /^(mailto|tel|https?):/i.test(u)) return;
      document.body.classList.add('crm-saliendo');
    });
    // Al volver con el botón de atrás, la página sale de la caché con la
    // clase puesta y se vería en blanco.
    window.addEventListener('pageshow', function () {
      document.body.classList.remove('crm-saliendo');
    });
  }
})();
