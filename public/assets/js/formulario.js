/* Campos calculados del formulario de un trámite (vista previa en el navegador).
   Sigue exactamente las reglas de App\Support\Formula (PHP): el servidor siempre recalcula y es el que vale.
   Sin eval: analizador propio con operadores + - * / % ^, comparaciones, && || ! y funciones fijas. */
(function (root) {
  'use strict';
  var FUNCIONES = { si: [3, 3], min: [1, null], max: [1, null], abs: [1, 1], redondear: [1, 2], piso: [1, 1], techo: [1, 1], raiz: [1, 1], suma: [1, null], promedio: [1, null], potencia: [2, 2], dias: [2, 2], anios: [2, 2] };
  var MAX_LARGO = 400, MAX_NODOS = 150, MAX_PROF = 25;

  function ErrorFormula(m) { this.message = m; }

  function analizar(src) {
    src = String(src).trim();
    if (!src) { throw new ErrorFormula('La fórmula está vacía.'); }
    if (src.length > MAX_LARGO) { throw new ErrorFormula('La fórmula es demasiado larga.'); }
    var t = tokenizar(src), pos = 0, nodos = 0;
    function err(m) { throw new ErrorFormula(m); }
    function sig() { return t[pos] || null; }
    function es(tipo, valor) { var s = sig(); return !!s && s[0] === tipo && (valor === undefined || s[1] === valor); }
    function esOp(lista) { var s = sig(); return !!s && s[0] === 'o' && lista.indexOf(s[1]) !== -1; }
    function nodo(p) { if (++nodos > MAX_NODOS) { err('La fórmula es demasiado compleja.'); } if (p > MAX_PROF) { err('Demasiados paréntesis anidados.'); } }
    function o(p) { var a = y(p); while (es('o', '||')) { pos++; a = ['b', '||', a, y(p)]; nodo(p); } return a; }
    function y(p) { var a = cmp(p); while (es('o', '&&')) { pos++; a = ['b', '&&', a, cmp(p)]; nodo(p); } return a; }
    function cmp(p) { var a = sum(p); if (esOp(['<', '<=', '>', '>=', '==', '!='])) { var op = sig()[1]; pos++; a = ['b', op, a, sum(p)]; nodo(p); } return a; }
    function sum(p) { var a = mul(p); while (esOp(['+', '-'])) { var op = sig()[1]; pos++; a = ['b', op, a, mul(p)]; nodo(p); } return a; }
    function mul(p) { var a = un(p); while (esOp(['*', '/', '%'])) { var op = sig()[1]; pos++; a = ['b', op, a, un(p)]; nodo(p); } return a; }
    function un(p) { if (esOp(['-', '+', '!'])) { var op = sig()[1]; pos++; nodo(p); return ['u', op, un(p + 1)]; } return pot(p); }
    function pot(p) { var a = prim(p); if (es('o', '^')) { pos++; a = ['b', '^', a, un(p + 1)]; nodo(p); } return a; }
    function prim(p) {
      var s = sig();
      if (!s) { err('La fórmula está incompleta.'); }
      nodo(p);
      if (s[0] === 'n') { pos++; return ['n', s[1]]; }
      if (s[0] === 'v') { pos++; return ['v', s[1]]; }
      if (s[0] === '(') { pos++; var a = o(p + 1); if (!es(')')) { err('Falta cerrar el paréntesis.'); } pos++; return a; }
      if (s[0] === 'i') {
        pos++;
        if (!es('(')) { return ['v', s[1]]; }
        if (!FUNCIONES[s[1]]) { err('La función «' + s[1] + '» no existe.'); }
        pos++;
        var args = [];
        if (!es(')')) { do { args.push(o(p + 1)); var sigue = es(','); if (sigue) { pos++; } } while (sigue); }
        if (!es(')')) { err('Falta cerrar el paréntesis de «' + s[1] + '».'); }
        pos++;
        var lim = FUNCIONES[s[1]];
        if (args.length < lim[0] || (lim[1] !== null && args.length > lim[1])) { err('«' + s[1] + '» recibe una cantidad de argumentos incorrecta.'); }
        return ['f', s[1], args];
      }
      err('No se esperaba «' + s[1] + '».');
    }
    var ast = o(0);
    if (pos < t.length) { err('Sobra «' + t[pos][1] + '».'); }
    return ast;
  }

  function tokenizar(s) {
    var t = [], i = 0, n = s.length, m;
    while (i < n) {
      var c = s.charAt(i);
      if (/\s/.test(c)) { i++; continue; }
      if (/[0-9]/.test(c) || (c === '.' && i + 1 < n && /[0-9]/.test(s.charAt(i + 1)))) {
        m = /^\d*\.?\d+/.exec(s.slice(i)); t.push(['n', parseFloat(m[0])]); i += m[0].length; continue;
      }
      if (c === '{') {
        m = /^\{\s*([A-Za-z][A-Za-z0-9_]*)\s*\}/.exec(s.slice(i));
        if (!m) { throw new ErrorFormula('Referencia inválida: use {clave}.'); }
        t.push(['v', m[1].toLowerCase()]); i += m[0].length; continue;
      }
      if (/[A-Za-z_]/.test(c)) { m = /^[A-Za-z_][A-Za-z0-9_]*/.exec(s.slice(i)); t.push(['i', m[0].toLowerCase()]); i += m[0].length; continue; }
      var dos = s.substr(i, 2);
      if (['<=', '>=', '==', '!=', '<>', '&&', '||'].indexOf(dos) !== -1) { t.push(['o', dos === '<>' ? '!=' : dos]); i += 2; continue; }
      if ('+-*/%^<>!=(),;'.indexOf(c) !== -1) {
        var c2 = c === ';' ? ',' : c;
        t.push([['(', ')', ','].indexOf(c2) !== -1 ? c2 : 'o', c === '=' ? '==' : c2]); i++; continue;
      }
      throw new ErrorFormula('Carácter no permitido «' + c + '».');
    }
    return t;
  }

  function variables(ast) {
    var o = {};
    (function rec(n) {
      if (n[0] === 'v') { if (n[1] !== 'hoy') { o[n[1]] = true; } }
      else if (n[0] === 'u') { rec(n[2]); }
      else if (n[0] === 'b') { rec(n[2]); rec(n[3]); }
      else if (n[0] === 'f') { n[2].forEach(rec); }
    })(ast);
    return Object.keys(o);
  }

  function diaDe(iso) {
    var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(iso || '');
    if (!m) { return null; }
    var d = new Date(Date.UTC(+m[1], +m[2] - 1, +m[3]));
    if (d.getUTCFullYear() !== +m[1] || d.getUTCMonth() !== +m[2] - 1 || d.getUTCDate() !== +m[3]) { return null; }
    return Math.round(d.getTime() / 86400000);
  }

  function hoyChile() {
    var f = new Intl.DateTimeFormat('en-CA', { timeZone: 'America/Santiago', year: 'numeric', month: '2-digit', day: '2-digit' }).format(new Date());
    return diaDe(f);
  }

  function redondear(x, n) {
    n = Math.max(0, Math.min(8, Math.trunc(n || 0)));
    var f = Math.pow(10, n), v = parseFloat((Math.abs(x) * f).toPrecision(15));
    return (x < 0 ? -1 : 1) * Math.round(v) / f;
  }

  function potencia(a, b) {
    if (Math.abs(b) > 100 || (a === 0 && b < 0)) { return null; }
    var r = Math.pow(a, b);
    return isFinite(r) ? r : null;
  }

  function anios(a, b) {
    var signo = 1;
    if (b < a) { var x = a; a = b; b = x; signo = -1; }
    var d1 = new Date(Math.trunc(a) * 86400000), d2 = new Date(Math.trunc(b) * 86400000);
    var y1 = d1.getUTCFullYear(), m1 = d1.getUTCMonth() + 1, dd1 = d1.getUTCDate(), y2 = d2.getUTCFullYear(), m2 = d2.getUTCMonth() + 1, dd2 = d2.getUTCDate();
    return signo * (y2 - y1 - ((m2 < m1 || (m2 === m1 && dd2 < dd1)) ? 1 : 0));
  }

  function ev(n, v, hoy) {
    var a, b, x;
    switch (n[0]) {
      case 'n': return n[1];
      case 'v':
        if (n[1] === 'hoy') { return hoy; }
        return Object.prototype.hasOwnProperty.call(v, n[1]) && v[n[1]] !== null && v[n[1]] !== undefined ? Number(v[n[1]]) : null;
      case 'u':
        x = ev(n[2], v, hoy);
        if (x === null) { return null; }
        return n[1] === '-' ? -x : (n[1] === '+' ? x : (x === 0 ? 1 : 0));
      case 'b':
        if (n[1] === '&&' || n[1] === '||') {
          a = ev(n[2], v, hoy);
          if (a === null) { return null; }
          if (n[1] === '&&' && a === 0) { return 0; }
          if (n[1] === '||' && a !== 0) { return 1; }
          b = ev(n[3], v, hoy);
          return b === null ? null : (b !== 0 ? 1 : 0);
        }
        a = ev(n[2], v, hoy); b = ev(n[3], v, hoy);
        if (a === null || b === null) { return null; }
        switch (n[1]) {
          case '+': return a + b;
          case '-': return a - b;
          case '*': return a * b;
          case '/': return b === 0 ? null : a / b;
          case '%': return b === 0 ? null : a % b;
          case '^': return potencia(a, b);
          case '<': return a < b ? 1 : 0;
          case '<=': return a <= b ? 1 : 0;
          case '>': return a > b ? 1 : 0;
          case '>=': return a >= b ? 1 : 0;
          case '==': return Math.abs(a - b) < 1e-9 ? 1 : 0;
          default: return Math.abs(a - b) < 1e-9 ? 0 : 1;
        }
      default:
        if (n[1] === 'si') { var c = ev(n[2][0], v, hoy); return c === null ? null : ev(c !== 0 ? n[2][1] : n[2][2], v, hoy); }
        var xs = [];
        for (var i = 0; i < n[2].length; i++) { var r = ev(n[2][i], v, hoy); if (r === null) { return null; } xs.push(r); }
        switch (n[1]) {
          case 'min': return Math.min.apply(null, xs);
          case 'max': return Math.max.apply(null, xs);
          case 'abs': return Math.abs(xs[0]);
          case 'suma': return xs.reduce(function (s, e) { return s + e; }, 0);
          case 'promedio': return xs.reduce(function (s, e) { return s + e; }, 0) / xs.length;
          case 'piso': return Math.floor(xs[0]);
          case 'techo': return Math.ceil(xs[0]);
          case 'raiz': return xs[0] < 0 ? null : Math.sqrt(xs[0]);
          case 'redondear': return redondear(xs[0], xs.length > 1 ? xs[1] : 0);
          case 'potencia': return potencia(xs[0], xs[1]);
          case 'dias': return xs[1] - xs[0];
          case 'anios': return anios(xs[0], xs[1]);
        }
        return null;
    }
  }

  function evaluar(ast, vars, hoy) {
    var r = ev(ast, vars, hoy === undefined ? hoyChile() : hoy);
    return r === null || !isFinite(r) ? null : r;
  }

  /** Número con formato chileno (1.234,56) y prefijo o sufijo. */
  function formatear(x, dec, pre, suf) {
    if (x === null || x === undefined) { return '—'; }
    var s = redondear(x, dec).toFixed(dec).split('.');
    s[0] = s[0].replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return (pre ? pre + ' ' : '') + s.join(',') + (suf ? ' ' + suf : '');
  }

  root.SgdFormula = { analizar: analizar, evaluar: evaluar, variables: variables, diaDe: diaDe, formatear: formatear, redondear: redondear, ErrorFormula: ErrorFormula };

  // ---- Formulario con campos calculados en vivo
  function valorDe(form, el) {
    var tipo = el.getAttribute('data-tipo');
    var k = el.getAttribute('data-campo');
    if (tipo === 'numero') {
      var i = form.querySelector('[name="campos[' + k + ']"]');
      if (!i || i.value === '') { return 0; }
      var n = parseFloat(i.value);
      return isFinite(n) ? n : 0;
    }
    if (tipo === 'fecha') { var f = form.querySelector('[name="campos[' + k + ']"]'); return f ? diaDe(f.value) : null; }
    if (tipo === 'si_no') { var r = form.querySelector('[name="campos[' + k + ']"]:checked'); return r && r.value === '1' ? 1 : 0; }
    if (tipo === 'lista') {
      var s = form.querySelector('[name="campos[' + k + ']"]');
      if (!s || s.value === '') { return null; }
      var op = s.selectedOptions && s.selectedOptions[0];
      var num = op ? op.getAttribute('data-valor') : s.value;
      return num !== null && num !== '' && isFinite(Number(num)) ? Number(num) : null;
    }
    return null;
  }

  function recalcular(form) {
    var campos = {};
    form.querySelectorAll('[data-campo]').forEach(function (el) { campos[el.getAttribute('data-campo')] = el; });
    var hoy = hoyChile(), vars = {};
    Object.keys(campos).forEach(function (k) { if (campos[k].getAttribute('data-tipo') !== 'calculo') { vars[k] = valorDe(form, campos[k]); } });
    var calc = Object.keys(campos).filter(function (k) { return campos[k].getAttribute('data-tipo') === 'calculo'; });
    var asts = {};
    calc.forEach(function (k) { try { asts[k] = analizar(campos[k].getAttribute('data-formula') || ''); } catch (e) { asts[k] = null; } });
    for (var pasada = 0; pasada <= calc.length; pasada++) {
      calc.forEach(function (k) { vars[k] = asts[k] ? evaluar(asts[k], vars, hoy) : null; });
    }
    calc.forEach(function (k) {
      var out = campos[k].querySelector('[data-resultado]');
      if (out) { out.textContent = formatear(vars[k], +campos[k].getAttribute('data-decimales') || 0, campos[k].getAttribute('data-prefijo') || '', campos[k].getAttribute('data-sufijo') || ''); }
    });
  }

  if (typeof document === 'undefined') { return; }
  document.addEventListener('input', function (e) { var f = e.target.closest ? e.target.closest('form[data-formulario]') : null; if (f) { recalcular(f); } });
  document.addEventListener('change', function (e) { var f = e.target.closest ? e.target.closest('form[data-formulario]') : null; if (f) { recalcular(f); } });
  document.addEventListener('DOMContentLoaded', function () { document.querySelectorAll('form[data-formulario]').forEach(recalcular); });
})(typeof window !== 'undefined' ? window : globalThis);

/* La vista previa del trámite no envía nada. */
if (typeof document !== 'undefined') {
  document.addEventListener('submit', function (e) { if (e.target.closest && e.target.closest('form[data-solo-vista]')) { e.preventDefault(); } });
}
