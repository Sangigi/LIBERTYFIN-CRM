/* ════════════════════════════════════════════════════════
   LA PALETA DE COMANDOS · Ctrl+K
   ════════════════════════════════════════════════════════

   Escribes y vas. A una sección, a un prospecto, a un asunto, a una
   póliza. Sin menú, sin volver atrás, sin acordarte de dónde estaba.

   POR QUÉ ESTO Y NO OTRA COSA

   Un CRM se usa ocho horas seguidas y casi siempre para lo mismo:
   buscar a alguien y abrir su ficha. Hacerlo por el menú son tres
   clics y una página intermedia. Aquí son dos teclas y el nombre.

   La diferencia no es de estilo, es de cuánto tarda la herramienta en
   quitarse de en medio.

   TRES DECISIONES

   · Las SECCIONES se filtran en el navegador, sin pedir nada: están
     ahí desde que carga la página. Que aparezcan antes de levantar el
     dedo de la tecla es lo que hace que se sienta instantáneo.
   · Los REGISTROS sí se piden al servidor, con 160 ms de espera desde
     la última tecla. Sin esa pausa, escribir «Vega» dispara cuatro
     búsquedas y llegan desordenadas.
   · Lo que llega tarde SE TIRA. Cada petición lleva número; si vuelve
     una vieja cuando ya hay otra más nueva, se descarta. Sin eso, la
     respuesta de «Ve» puede pisar la de «Vega» y la lista muestra lo
     que no se buscó.
   ════════════════════════════════════════════════════════ */
(function () {
  'use strict';

  var caja, campo, lista, velo;
  var abierta = false, sel = 0, items = [], reloj = null, serie = 0;

  /* Las secciones salen del menú que ya está pintado: así la paleta
     conoce exactamente lo que esta persona puede abrir, sin repetir la
     matriz de permisos en el navegador. */
  function secciones() {
    var r = [];
    document.querySelectorAll('.lf-nav a[href]').forEach(function (a) {
      var t = (a.querySelector('span') || a).textContent.trim() || a.getAttribute('title') || '';
      if (t) r.push({ t: t, s: 'Ir a la sección', u: a.getAttribute('href'), k: 'ir' });
    });
    return r;
  }

  /* Sin acentos y en minúsculas: quien escribe rápido no pone tildes, y
     «polizas» tiene que encontrar «Pólizas». */
  function llano(s) {
    return (s || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  }

  function construir() {
    velo = document.createElement('div');
    velo.className = 'cmdk-velo';
    velo.addEventListener('click', cerrar);

    caja = document.createElement('div');
    caja.className = 'cmdk';
    caja.setAttribute('role', 'dialog');
    caja.setAttribute('aria-modal', 'true');
    caja.setAttribute('aria-label', 'Buscar e ir');
    caja.innerHTML =
      '<div class="cmdk-barra">' +
        '<svg class="cmdk-lupa" viewBox="0 0 24 24" fill="none" stroke="currentColor" ' +
          'stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/>' +
          '<path d="m20 20-3.5-3.5"/></svg>' +
        '<input type="text" placeholder="Busca un prospecto, cliente, asunto o sección…" ' +
          'autocomplete="off" spellcheck="false" aria-label="Buscar">' +
        '<kbd>esc</kbd>' +
      '</div>' +
      '<div class="cmdk-lista" role="listbox"></div>' +
      '<div class="cmdk-pie">' +
        '<span><kbd>↑</kbd><kbd>↓</kbd> moverse</span>' +
        '<span><kbd>↵</kbd> abrir</span>' +
        '<span class="cmdk-marca">LibertyFin CRM</span>' +
      '</div>';

    document.body.appendChild(velo);
    document.body.appendChild(caja);
    campo = caja.querySelector('input');
    lista = caja.querySelector('.cmdk-lista');

    campo.addEventListener('input', function () {
      clearTimeout(reloj);
      var q = campo.value.trim();
      // Las secciones, ya. Los registros, en un momento.
      pintar(filtrarSecciones(q), q.length >= 2);
      if (q.length >= 2) reloj = setTimeout(function () { pedir(q); }, 160);
    });

    campo.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown') { e.preventDefault(); mover(1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); mover(-1); }
      else if (e.key === 'Enter') { e.preventDefault(); abrir(); }
      else if (e.key === 'Escape') { e.preventDefault(); cerrar(); }
    });
  }

  function filtrarSecciones(q) {
    var todas = secciones();
    if (!q) return [{ titulo: 'Ir a', items: todas.slice(0, 7) }];
    var n = llano(q);
    var hit = todas.filter(function (s) { return llano(s.t).indexOf(n) !== -1; });
    return hit.length ? [{ titulo: 'Ir a', items: hit }] : [];
  }

  function pedir(q) {
    var mio = ++serie;
    fetch('/buscar?q=' + encodeURIComponent(q), {
      headers: { 'X-LF-Json': '1', 'X-Requested-With': 'XMLHttpRequest' },
      credentials: 'same-origin'
    }).then(function (r) { return r.json(); })
      .then(function (d) {
        // Llegó tarde: ya se escribió algo más nuevo. Se tira.
        if (mio !== serie || !abierta) return;
        pintar(filtrarSecciones(q).concat(d.grupos || []), false);
      })
      .catch(function () { /* sin red: quedan las secciones, que ya sirven */ });
  }

  function pintar(grupos, buscando) {
    items = [];
    var html = '';
    grupos.forEach(function (g) {
      if (!g.items || !g.items.length) return;
      html += '<div class="cmdk-grupo">' + esc(g.titulo) + '</div>';
      g.items.forEach(function (it) {
        var i = items.length;
        items.push(it);
        html += '<a class="cmdk-it" role="option" data-i="' + i + '" href="' + esc(it.u) + '">' +
                  '<span class="cmdk-t">' + esc(it.t) + '</span>' +
                  '<span class="cmdk-s">' + esc(it.s || '') + '</span>' +
                '</a>';
      });
    });

    if (!html) {
      html = buscando
        ? '<div class="cmdk-nada"><span class="cmdk-pulso"></span>Buscando…</div>'
        : '<div class="cmdk-nada">Nada coincide.</div>';
    }
    lista.innerHTML = html;
    sel = 0; marcar();
  }

  function esc(s) {
    var d = document.createElement('div'); d.textContent = s == null ? '' : s;
    return d.innerHTML;
  }

  function marcar() {
    var els = lista.querySelectorAll('.cmdk-it');
    els.forEach(function (e, i) {
      var on = i === sel;
      e.classList.toggle('on', on);
      e.setAttribute('aria-selected', on ? 'true' : 'false');
      // `nearest` y no `center`: centrar la selección en cada flecha
      // hace que la lista salte entera y marea.
      if (on) e.scrollIntoView({ block: 'nearest' });
    });
  }

  function mover(d) {
    if (!items.length) return;
    // Da la vuelta: desde el último, abajo lleva al primero.
    sel = (sel + d + items.length) % items.length;
    marcar();
  }

  function abrir() {
    if (!items[sel]) return;
    window.location.href = items[sel].u;
  }

  function mostrar() {
    if (!caja) construir();
    abierta = true;
    velo.classList.add('on'); caja.classList.add('on');
    campo.value = '';
    pintar(filtrarSecciones(''), false);
    campo.focus();
    document.body.style.overflow = 'hidden';
  }

  function cerrar() {
    if (!abierta) return;
    abierta = false;
    velo.classList.remove('on'); caja.classList.remove('on');
    document.body.style.overflow = '';
  }

  document.addEventListener('keydown', function (e) {
    // Ctrl+K en Windows y Linux, ⌘K en Mac. Y la barra «/», que es lo
    // que teclea quien viene de otras herramientas, pero solo si no
    // está escribiendo en un campo.
    var esK = (e.ctrlKey || e.metaKey) && (e.key === 'k' || e.key === 'K');
    var enCampo = /^(INPUT|TEXTAREA|SELECT)$/.test(document.activeElement.tagName) ||
                  document.activeElement.isContentEditable;
    if (esK || (e.key === '/' && !enCampo && !abierta)) {
      e.preventDefault();
      abierta ? cerrar() : mostrar();
    }
  });

  document.addEventListener('click', function (e) {
    if (e.target.closest('[data-cmdk]')) { e.preventDefault(); mostrar(); }
  });

  window.crmPaleta = mostrar;
})();
