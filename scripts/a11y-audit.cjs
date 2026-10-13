// Auditoría de accesibilidad en navegador real (axe-core). Requiere el sitio en marcha con datos DEMO (SOLO DESARROLLO):
//   php artisan migrate:fresh --seed && php artisan serve &   |   npm i playwright-core axe-core (fuera del repo, NODE_PATH)
//   CHROME_PATH=/ruta/chromium BASE_URL=http://127.0.0.1:8000 node scripts/a11y-audit.js
const { chromium } = require('playwright-core');
const fs = require('fs');
const axeSrc = fs.readFileSync(process.env.AXE_PATH || require.resolve('axe-core/axe.min.js'), 'utf8');
const BASE = process.env.BASE_URL || 'http://127.0.0.1:8000';
const OUT = process.env.OUT_DIR || 'docs/capturas';
function dv(c) { let s = 0, m = 2; for (let i = c.length - 1; i >= 0; i--) { s += +c[i] * m; m = m === 7 ? 2 : m + 1; } const r = 11 - (s % 11); return r === 11 ? '0' : r === 10 ? 'K' : String(r); }
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined, args: ['--no-sandbox'] });
  const resumen = []; const detalle = []; const otros = [];
  async function ctx(vw = 1280, vh = 900) { const c = await browser.newContext({ viewport: { width: vw, height: vh }, locale: 'es-CL' }); return c; }
  async function login(page, via, run) {
    await page.goto(`${BASE}/ingreso/${via}`); await page.fill('#run', run); await page.fill('#nombres', 'Persona'); await page.fill('#apellidos', 'Simulada');
    await page.click('button:has-text("Continuar")'); await page.waitForLoadState('load');
  }
  async function axe(page, nombre, modo) {
    await page.evaluate(axeSrc);
    const r = await page.evaluate(async () => await window.axe.run(document, { runOnly: { type: 'tag', values: ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa', 'best-practice'] } }));
    const v = r.violations;
    resumen.push({ pagina: nombre, modo, violaciones: v.length, reglas: r.passes.length });
    v.forEach(x => detalle.push({ pagina: nombre, modo, regla: x.id, impacto: x.impact, ayuda: x.help, nodos: x.nodes.slice(0, 3).map(n => n.target.join(' ') + ' :: ' + (n.failureSummary || '').split('\n').slice(0, 2).join(' | ')) }));
  }
  // ---- público + navegación por teclado + barra
  let c = await ctx(); let page = await c.newPage();
  page.on('pageerror', e => otros.push('pageerror ' + e.message));
  page.on('console', m => { if (m.type() === 'error') otros.push('console ' + m.text()); });
  for (const [n, u] of [['inicio', '/'], ['ingreso', '/ingreso'], ['publico', '/publico'], ['validar', '/validar'], ['accesibilidad', '/accesibilidad'], ['ayuda', '/ayuda'], ['manual-ciudadano', '/ayuda/ciudadano'], ['manual-funcionario', '/ayuda/funcionario'], ['manual-administrador', '/ayuda/administrador']]) {
    await page.goto(BASE + u); await axe(page, n, 'normal');
  }
  await page.goto(BASE + '/');
  await page.keyboard.press('Tab');
  const skip = await page.evaluate(() => { const a = document.activeElement; const r = a.getBoundingClientRect(); return { texto: a.textContent.trim(), visible: r.width > 0 && r.left >= 0 && r.top >= 0 }; });
  otros.push('Primer Tab: ' + JSON.stringify(skip));
  await page.keyboard.press('Enter');
  otros.push('Tras Enter en "Saltar al contenido", foco en: #' + await page.evaluate(() => document.activeElement.id));
  const ring = await page.evaluate(() => { document.querySelector('.a11y-bar button').focus(); const s = getComputedStyle(document.activeElement); return s.outlineStyle + ' ' + s.outlineWidth + ' ' + s.outlineColor; });
  otros.push('Foco visible (outline) en botón de accesibilidad: ' + ring);
  // barra: tamaño, contraste y persistencia
  await page.click('[data-a11y-size="up"]'); await page.click('[data-a11y-size="up"]');
  await page.click('[data-a11y-toggle="contrast"]'); await page.click('[data-a11y-toggle="legible"]'); await page.click('[data-a11y-toggle="links"]');
  const cls = await page.evaluate(() => document.documentElement.className);
  otros.push('Clases tras activar opciones: ' + cls);
  otros.push('aria-pressed contraste: ' + await page.getAttribute('[data-a11y-toggle="contrast"]', 'aria-pressed'));
  otros.push('Anuncio en región aria-live: ' + JSON.stringify(await page.textContent('#a11y-anuncio')));
  await page.screenshot({ path: `${OUT}/a11y-01-alto-contraste-texto-grande.png`, fullPage: true });
  await page.reload();
  otros.push('Persistencia tras recargar: ' + await page.evaluate(() => document.documentElement.className));
  for (const [n, u] of [['inicio', '/'], ['ingreso', '/ingreso'], ['manual-ciudadano', '/ayuda/ciudadano']]) { await page.goto(BASE + u); await axe(page, n, 'alto-contraste+150%'); }
  await page.goto(BASE + '/ayuda/ciudadano'); await page.screenshot({ path: `${OUT}/a11y-02-manual-alto-contraste.png` });
  await page.click('#a11y-reset'); otros.push('Tras Restablecer: ' + JSON.stringify(await page.evaluate(() => document.documentElement.className)));
  await c.close();
  // ---- reflow móvil 320 px y zoom 400% equivalente
  c = await ctx(320, 700); page = await c.newPage();
  for (const [n, u] of [['inicio', '/'], ['ingreso', '/ingreso'], ['accesibilidad', '/accesibilidad'], ['manual-funcionario', '/ayuda/funcionario'], ['publico', '/publico']]) {
    await page.goto(BASE + u); const w = await page.evaluate(() => ({ sw: document.documentElement.scrollWidth, cw: document.documentElement.clientWidth }));
    otros.push(`Reflow 320px ${n}: scrollWidth=${w.sw} clientWidth=${w.cw} ${w.sw <= w.cw + 1 ? 'OK' : 'DESBORDE HORIZONTAL'}`);
  }
  await page.goto(BASE + '/'); await page.screenshot({ path: `${OUT}/a11y-03-movil-320.png`, fullPage: true });
  await c.close();
  // ---- ciudadano
  c = await ctx(); page = await c.newPage(); page.on('pageerror', e => otros.push('pageerror ' + e.message));
  await login(page, 'ciudadano', '20000001-3');
  await page.goto(BASE + '/ciudadano'); await axe(page, 'ciudadano-inicio', 'normal');
  await page.goto(BASE + '/ciudadano/nueva/1'); await axe(page, 'ciudadano-nueva', 'normal');
  // error de validación: foco y resumen
  await page.fill('#correo', 'no-es-correo'); await page.fill('#detalle', 'corto');
  await page.evaluate(() => { document.querySelectorAll('input[type=file]').forEach(i => i.removeAttribute('required')); document.querySelector('#correo').type = 'text'; document.querySelector('#detalle').removeAttribute('minlength'); document.querySelectorAll('[required]').forEach(e => e.removeAttribute('required')); });
  await page.click('button:has-text("Enviar solicitud")'); await page.waitForLoadState('load');
  otros.push('Tras error de validación el foco está en: #' + await page.evaluate(() => document.activeElement.id) + ' ; aria-invalid en #correo: ' + await page.getAttribute('#correo', 'aria-invalid'));
  await axe(page, 'ciudadano-errores', 'normal');
  await page.screenshot({ path: `${OUT}/a11y-04-resumen-errores.png`, fullPage: true });
  await c.close();
  // ---- funcionario
  c = await ctx(); page = await c.newPage(); page.on('pageerror', e => otros.push('pageerror ' + e.message));
  await login(page, 'funcionario', '10000004-' + dv('10000004'));
  await page.goto(BASE + '/app'); await axe(page, 'bandeja', 'normal');
  await page.goto(BASE + '/app/vigentes'); await axe(page, 'vigentes', 'normal');
  await page.goto(BASE + '/app/comunicaciones'); await axe(page, 'comunicaciones', 'normal');
  await c.close();
  // ---- admin
  c = await ctx(); page = await c.newPage();
  await login(page, 'funcionario', '10000001-' + dv('10000001'));
  for (const [n, u] of [['admin-usuarios', '/app/admin/usuarios'], ['admin-tramite', '/app/tramites/1'], ['tramites', '/app/tramites'], ['tramite-nuevo', '/app/tramites/nuevo'], ['tramite-vista', '/app/tramites/1/vista'], ['tramite-historial', '/app/tramites/1/historial'], ['admin-plantilla', '/app/admin/plantillas/1'], ['codificador', '/app/admin/codificador'], ['diagnostico', '/app/diagnostico'], ['seguridad', '/app/seguridad'], ['apariencia', '/app/admin/apariencia'], ['menu', '/app/admin/menu'], ['api', '/app/admin/api'], ['folios', '/app/admin/folios'], ['firmas', '/app/admin/firmas'], ['repositorio', '/app/admin/repositorio'], ['roles', '/app/admin/roles'], ['rol-nuevo', '/app/admin/roles/nuevo'], ['usuario-nuevo', '/app/admin/usuarios/nuevo'], ['logs', '/app/logs'], ['sesiones', '/app/sesiones'], ['conexiones', '/app/sistema/conexiones'], ['conexiones-30d-ent', '/app/sistema/conexiones?rango=30d&direccion=entrante'], ['recursos', '/app/sistema/recursos'], ['recursos-7d', '/app/sistema/recursos?rango=7d'], ['componentes', '/app/sistema/componentes'], ['formatos', '/app/formatos'], ['formato', '/app/formatos/1'], ['plantillas', '/app/admin/plantillas'], ['plantilla-nueva', '/app/admin/plantillas/nuevo'], ['plantilla-versiones', '/app/admin/plantillas/1/versiones']]) {
    await page.goto(BASE + u); if (n === 'admin-plantilla') await page.waitForSelector('.tox-tinymce', { timeout: 20000 }); await axe(page, n, 'normal');
  }
  await page.goto(BASE + '/app/admin/plantillas/1'); await page.waitForSelector('.tox-tinymce'); await page.screenshot({ path: `${OUT}/a11y-05-editor.png` });
  await c.close();
  await browser.close();
  fs.writeFileSync(process.env.RESULT_JSON || 'a11y-result.json', JSON.stringify({ resumen, detalle, otros }, null, 1));
  console.log('PÁGINAS/MODOS AUDITADOS:', resumen.length, ' VIOLACIONES TOTALES:', detalle.length);
  detalle.forEach(d => console.log(`- [${d.pagina}|${d.modo}] ${d.regla} (${d.impacto}): ${d.ayuda}\n    ${d.nodos.join('\n    ')}`));
  console.log('\nOTROS:'); otros.forEach(o => console.log(' ·', o));
})().catch(e => { console.error('FALLO', e); process.exit(1); });
