// Nebula · modo claro u oscuro elegido por el visitante (data-theme en <html>). Se carga en el <head>, antes de
// pintar la página, para que no parpadee; sin elección, manda el del sistema (prefers-color-scheme).
(function () {
  try {
    var t = localStorage.getItem('nb-theme');
    if (t === 'dark' || t === 'light') document.documentElement.setAttribute('data-theme', t);
  } catch (e) { /* almacenamiento bloqueado: el del sistema */ }
})();
