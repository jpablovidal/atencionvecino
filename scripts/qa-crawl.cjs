// QA de navegador real: por cada perfil inicia sesión (acceso de pruebas), rastrea todos los enlaces internos GET alcanzables
// (máx. 250 por perfil) y reporta: respuestas >= 400, errores de JS/consola, recursos que fallan y formularios POST sin token CSRF.
//   NODE_PATH=/ruta/node_modules CHROME_PATH=/ruta/chromium BASE_URL=http://127.0.0.1:8124 CRED=cred.json node scripts/qa-crawl.cjs
const { chromium } = require('playwright-core');
const fs = require('fs');
const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8124').replace(/\/$/, '');
const CRED = JSON.parse(fs.readFileSync(process.env.CRED || 'cred.json', 'utf8'));
const PERFILES = { admin: '10000001-6', alcalde: '10000002-4', secretario: '10000003-2', funcionario: '10000004-0', auditor: '10000007-5', ciudadano: '20000001-3' };
const SALTAR = /\/(salir|ingreso\/(ciudadano|funcionario)|dev\/)|\.json$|\/pdf$|\/exportar|\/descargar|\/archivos?\/|\/recursos\/|\/marca\/|\/cerrar$|\/logs\/exportar|\/archivo$/;
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined, args: ['--no-sandbox'] });
  const informe = [];
  for (const [perfil, run] of Object.entries(PERFILES)) {
    const ctx = await browser.newContext({ locale: 'es-CL', acceptDownloads: false });
    const page = await ctx.newPage();
    const prob = []; const vistas = new Set(); const cola = [];
    page.on('pageerror', e => prob.push(`JS: ${e.message}`));
    page.on('console', m => { if (m.type() === 'error') prob.push(`consola: ${m.text().slice(0, 160)}`); });
    page.on('requestfailed', r => { if (!/\/(salir)/.test(r.url())) prob.push(`recurso falló: ${r.url().replace(BASE, '')}`); });
    page.on('response', r => { if (r.status() >= 400 && r.url().startsWith(BASE)) prob.push(`HTTP ${r.status()}: ${r.url().replace(BASE, '')}`); });
    await page.goto(BASE + '/ingreso/pruebas');
    await page.fill('#run', run); await page.fill('#password', CRED[run]); await page.click('button:has-text("Ingresar")'); await page.waitForLoadState('load');
    const inicio = page.url().replace(BASE, '');
    if (/ingreso/.test(inicio)) { informe.push({ perfil, error: 'no pudo iniciar sesión' }); await ctx.close(); continue; }
    cola.push(inicio);
    let formsSinCsrf = 0; let n = 0;
    while (cola.length && n < 250) {
      const ruta = cola.shift(); const clave = ruta.split('#')[0];
      if (vistas.has(clave) || SALTAR.test(clave)) continue; vistas.add(clave); n++;
      try { await page.goto(BASE + clave, { waitUntil: 'load', timeout: 20000 }); } catch (e) { prob.push(`navegación: ${clave} ${e.message.slice(0, 80)}`); continue; }
      if (page.url().replace(BASE, '').startsWith('/ingreso')) { prob.push(`sesión perdida en ${clave}`); break; }
      const hrefs = await page.$$eval('a[href]', as => as.map(a => a.getAttribute('href')));
      for (const h of hrefs) { if (!h || h.startsWith('#') || /^(mailto|tel|javascript):/.test(h)) continue; let u; try { u = new URL(h, page.url()); } catch { continue; } if (u.origin !== BASE) continue; const r = u.pathname + u.search; if (!vistas.has(r)) cola.push(r); }
      formsSinCsrf += await page.$$eval('form[method="post" i]', fs => fs.filter(f => !f.querySelector('input[name="_token"]')).length);
    }
    if (formsSinCsrf) prob.push(`${formsSinCsrf} formularios POST sin token CSRF`);
    informe.push({ perfil, paginas: vistas.size, problemas: [...new Set(prob)] });
    await ctx.close();
  }
  await browser.close();
  for (const i of informe) { console.log(`\n== ${i.perfil}: ${i.error || i.paginas + ' páginas'} ==`); (i.problemas || []).forEach(p => console.log('  - ' + p)); }
  fs.writeFileSync(process.env.OUT || 'qa-crawl.json', JSON.stringify(informe, null, 1));
})();
