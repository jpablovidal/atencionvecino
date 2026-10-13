// QA E2E (navegador real, CSRF real) de la gestión de trámites por dirección:
// directora DIDECO crea borrador → vista previa → publica → vecino lo ve y lo solicita con adjunto → llega a la bandeja de DIDECO →
// DOM no lo ve ni puede editarlo → redactor no puede publicar → directora deshabilita → desaparece para el vecino.
//   NODE_PATH=... CHROME_PATH=... BASE_URL=http://127.0.0.1:8124 CRED=cred.json PDF=a.pdf node scripts/qa-tramites.cjs
const { chromium } = require('playwright-core');
const fs = require('fs');
const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8124').replace(/\/$/, '');
const CRED = JSON.parse(fs.readFileSync(process.env.CRED || 'cred.json', 'utf8'));
const PDF = process.env.PDF || 'a.pdf';
const paso = []; const fallas = [];
const ok = (m) => paso.push('OK  ' + m); const ko = (m) => { fallas.push(m); paso.push('FALLA ' + m); };
const exige = (c, m) => (c ? ok(m) : ko(m));
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined, args: ['--no-sandbox'] });
  async function sesion(run) {
    const ctx = await browser.newContext({ locale: 'es-CL', viewport: { width: 1300, height: 950 } }); const page = await ctx.newPage();
    page.on('dialog', d => d.accept()); const errs = []; page.on('pageerror', e => errs.push(e.message)); page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
    await page.goto(BASE + '/ingreso/pruebas'); await page.fill('#run', run); await page.fill('#password', CRED[run]); await page.click('button:has-text("Ingresar")'); await page.waitForLoadState('load');
    return { ctx, page, errs };
  }
  const NOMBRE = 'Certificado E2E ' + Date.now();
  // 1. directora DIDECO
  let s = await sesion('10000009-1'); let p = s.page;
  await p.goto(BASE + '/app/tramites/nuevo');
  await p.fill('#nombre', NOMBRE); await p.fill('#descripcion', 'Certifica que el vecino participa en programas comunitarios de la dirección.'); await p.fill('#instrucciones', 'Presente su cédula vigente.'); await p.fill('#plazo_dias', '7');
  await p.fill('#a-n-0', 'Cédula de identidad'); await p.fill('#a-d-0', 'Ambos lados');
  await p.click('#agregar-anexo'); await p.fill('#a-n-1', 'Comprobante de domicilio'); await p.click('label[for="a-o-1"]');
  exige(await p.locator('.anexo-fila').count() === 2, 'el botón agrega filas de documentos requeridos (JS externo, sin inline)');
  await p.click('button:has-text("Guardar como borrador")'); await p.waitForLoadState('load');
  exige(/\/app\/tramites\/\d+$/.test(p.url()) && (await p.content()).includes('borrador'), 'se guarda como borrador');
  const tid = p.url().match(/tramites\/(\d+)/)[1];
  await p.goto(BASE + '/app/tramites/' + tid + '/vista'); exige((await p.content()).includes('Comprobante de domicilio'), 'vista previa muestra los documentos requeridos');
  await p.goto(BASE + '/app/tramites/' + tid); await p.click('button:has-text("Publicar")');
  await p.waitForLoadState('load');
  exige((await p.content()).includes('publicado'), 'se publica');
  await s.ctx.close();
  // 2. vecino
  s = await sesion('20000001-3'); p = s.page;
  await p.goto(BASE + '/ciudadano'); exige((await p.content()).includes(NOMBRE), 'el vecino ve el trámite publicado, agrupado por dirección');
  await p.goto(BASE + '/ciudadano/nueva/' + tid); exige((await p.content()).includes('Ambos lados'), 've la ayuda de cada documento requerido');
  await p.fill('#correo', 'vecina@example.com'); await p.fill('#detalle', 'Solicito el certificado para postular a un beneficio.');
  for (const el of await p.$$('input[type=file][name^="anexo_"]')) await el.setInputFiles(PDF);
  await p.click('button:has-text("Enviar solicitud")'); await p.waitForLoadState('load');
  const folio = (p.url().match(/SD-\d{4}-[A-Z0-9]+/) || [])[0]; exige(!!folio, 'la solicitud se ingresa con todos los adjuntos y recibe folio ' + folio);
  await s.ctx.close();
  // 3. DIDECO la ve y crea expediente; DOM no
  s = await sesion('10000006-7'); p = s.page; await p.goto(BASE + '/app'); exige((await p.content()).includes(folio), 'la solicitud llega a la bandeja de DIDECO');
  await p.goto(BASE + '/app/solicitudes/' + folio); await p.click('button:has-text("Asignármela")'); await p.waitForLoadState('load');
  exige(/\/app\/expedientes\/\d+/.test(p.url()), 'se genera el expediente'); await s.ctx.close();
  s = await sesion('10000004-0'); p = s.page; await p.goto(BASE + '/app'); exige(!(await p.content()).includes(folio), 'DOM no ve solicitudes de DIDECO');
  const r = await p.goto(BASE + '/app/tramites'); exige(r.status() === 403, 'un funcionario sin permiso no accede a la gestión de trámites'); await s.ctx.close();
  // 4. redactor DOM no ve el trámite de DIDECO
  s = await sesion('10000010-5'); p = s.page; const r2 = await p.goto(BASE + '/app/tramites/' + tid); exige(r2.status() === 404, 'otra dirección: 404 al abrir un trámite ajeno'); await s.ctx.close();
  // 5. deshabilitar
  s = await sesion('10000009-1'); p = s.page; await p.goto(BASE + '/app/tramites/' + tid); await p.click('button:has-text("Deshabilitar")');
  await p.waitForLoadState('load'); exige((await p.content()).includes('deshabilitado'), 'se deshabilita'); await s.ctx.close();
  s = await sesion('20000001-3'); p = s.page; await p.goto(BASE + '/ciudadano'); exige(!(await p.content()).includes('/ciudadano/nueva/' + tid), 'el vecino ya no lo ve entre los trámites disponibles (su solicitud previa sigue en el historial)');
  const r3 = await p.goto(BASE + '/ciudadano/nueva/' + tid); exige(r3.status() === 404, 'y no puede abrirlo por URL directa'); await s.ctx.close();
  await browser.close();
  console.log(paso.join('\n')); console.log(fallas.length ? '\nFALLAS: ' + fallas.length : '\nTODO OK (' + paso.length + ' verificaciones)'); process.exit(fallas.length ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
