// QA E2E (navegador real, CSRF real) del archivo municipal: la archivista registra una microforma, carga archivos, indexa y la envía a
// revisión → el ministro de fe la certifica con acta → la auditoría verifica integridad, descarga y emite la constancia →
// se solicita y autoriza la disposición de originales (doble control).
//   NODE_PATH=... CHROME_PATH=... BASE_URL=http://127.0.0.1:8124 CRED=cred.json PDF=a.pdf ACTA=acta.pdf node scripts/qa-archivo.cjs
const { chromium } = require('playwright-core');
const fs = require('fs');
const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8124').replace(/\/$/, '');
const CRED = JSON.parse(fs.readFileSync(process.env.CRED || 'cred.json', 'utf8'));
const PDF = process.env.PDF || 'a.pdf'; const ACTA = process.env.ACTA || PDF;
const paso = []; const fallas = [];
const ok = (m) => paso.push('OK  ' + m); const ko = (m) => { fallas.push(m); paso.push('FALLA ' + m); };
const exige = (c, m) => (c ? ok(m) : ko(m));
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined, args: ['--no-sandbox'] });
  async function sesion(run) {
    const ctx = await browser.newContext({ locale: 'es-CL', viewport: { width: 1300, height: 950 }, acceptDownloads: true }); const page = await ctx.newPage();
    page.on('dialog', d => d.accept());
    await page.goto(BASE + '/ingreso/pruebas'); await page.fill('#run', run); await page.fill('#password', CRED[run]); await page.click('button:has-text("Ingresar")'); await page.waitForLoadState('load');
    return { ctx, page };
  }
  const TIT = 'Decretos alcaldicios E2E ' + Date.now();
  // 1. archivista
  let s = await sesion('10000011-3'); let p = s.page;
  await p.goto(BASE + '/app'); exige((await p.content()).includes('Archivo municipal'), 'el menú muestra «Archivo municipal» a quien tiene el permiso');
  await p.goto(BASE + '/app/archivo/nueva'); await p.fill('#titulo', TIT); await p.selectOption('#tipo_soporte', 'microfilm_16mm'); await p.fill('#serie', 'Decretos'); await p.fill('#ubicacion', 'Bodega 1, estante 3');
  await p.fill('#fecha_desde', '2019-01-01'); await p.fill('#fecha_hasta', '2020-12-31'); await p.fill('#n_documentos', '40');
  await p.click('button:has-text("Guardar")'); await p.waitForLoadState('load');
  exige(/\/app\/archivo\/\d+$/.test(p.url()) && /MF-\d{4}-\d{6}/.test(await p.content()), 'se registra con código MF-AAAA-NNNNNN');
  const id = p.url().match(/archivo\/(\d+)/)[1];
  await p.setInputFiles('#archivos', [PDF]); await p.click('button:has-text("Cargar y cifrar")'); await p.waitForLoadState('load');
  exige((await p.content()).includes('Archivos maestros (1)'), 'se carga el archivo maestro (cifrado) con su SHA-256');
  await p.fill('#referencia', 'Decreto N° 15/2019'); await p.fill('#fojas', '1-3'); await p.fill('#posicion', 'F-001'); await p.click('button:has-text("Agregar")'); await p.waitForLoadState('load');
  exige((await p.content()).includes('Decreto N° 15/2019'), 'se indexa un documento');
  await p.screenshot({ path: process.env.SHOT_DIR ? process.env.SHOT_DIR + '/archivo-borrador.png' : '/tmp/archivo-borrador.png', fullPage: true });
  await p.click('button:has-text("Enviar a revisión")'); await p.waitForLoadState('load');
  exige((await p.content()).includes('En revisión'), 'se envía a revisión');
  exige((await p.content()).includes('Separación de funciones') === false && !(await p.$('button:has-text("Certificar")')), 'la archivista no ve la acción de certificar');
  await s.ctx.close();
  // 2. ministro de fe
  s = await sesion('10000012-1'); p = s.page; await p.goto(BASE + '/app/archivo/' + id);
  await p.fill('#acta_numero', 'ACTA-E2E-1'); await p.setInputFiles('#acta', [ACTA]); await p.click('button:has-text("Certificar")'); await p.waitForLoadState('load');
  const html = await p.content(); exige(html.includes('Certificada') && html.includes('ACTA-E2E-1'), 'el ministro de fe certifica con acta');
  exige(/Hash del conjunto/.test(html), 'se fija el hash del conjunto');
  await s.ctx.close();
  // 3. auditoría
  s = await sesion('10000007-5'); p = s.page; await p.goto(BASE + '/app/archivo/' + id);
  exige(!(await p.$('button:has-text("Certificar")')) && !(await p.$('#archivos')), 'la auditoría no tiene acciones de escritura');
  await p.click('button:has-text("Verificar integridad")'); await p.waitForLoadState('load'); exige((await p.content()).includes('Integridad verificada'), 'verificación de integridad correcta');
  const [dl] = await Promise.all([p.waitForEvent('download'), p.click('a:has-text("Descargar")')]); exige((await dl.suggestedFilename()).length > 0, 'descarga del archivo maestro');
  const r = await p.goto(BASE + '/app/archivo/' + id + '/constancia'); exige(r.status() === 200 && (r.headers()['content-type'] || '').includes('pdf'), 'constancia de registro en PDF');
  const r2 = await p.goto(BASE + '/app/archivo/nueva'); exige(r2.status() === 403, 'sin permiso de gestión: 403 al registrar');
  await s.ctx.close();
  // 4. disposición con doble control
  s = await sesion('10000011-3'); p = s.page; await p.goto(BASE + '/app/archivo/' + id);
  await p.fill('#motivo_disp', 'Plazo de conservación de originales cumplido según la tabla de retención documental.'); await p.click('button:has-text("Solicitar disposición")'); await p.waitForLoadState('load');
  exige((await p.content()).includes('Pendiente de autorización'), 'se solicita la disposición de originales'); await s.ctx.close();
  s = await sesion('10000012-1'); p = s.page; await p.goto(BASE + '/app/archivo/' + id); await p.fill('#acta_disp', 'DA-E2E-1'); await p.click('button:has-text("Autorizar")'); await p.waitForLoadState('load');
  exige((await p.content()).includes('Autorizada, falta ejecutar'), 'otra persona autoriza (doble control)'); await p.screenshot({ path: process.env.SHOT_DIR ? process.env.SHOT_DIR + '/archivo-certificada.png' : '/tmp/archivo-certificada.png', fullPage: true }); await s.ctx.close();
  await browser.close();
  console.log(paso.join('\n')); console.log(fallas.length ? '\nFALLAS: ' + fallas.length : '\nTODO OK (' + paso.length + ' verificaciones)'); process.exit(fallas.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
