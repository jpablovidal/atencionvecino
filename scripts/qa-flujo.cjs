// QA E2E en navegador real (con CSRF real): ciudadano ingresa solicitud → funcionario la asigna y redacta un oficio con el editor →
// secretaria firma (FirmaGob simulado, OTP de 6 dígitos) → el documento queda vigente → validación pública. Requiere ambiente de pruebas.
//   NODE_PATH=... CHROME_PATH=... BASE_URL=http://127.0.0.1:8124 CRED=cred.json PDF=a.pdf node scripts/qa-flujo.cjs
const { chromium } = require('playwright-core');
const fs = require('fs');
const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8124').replace(/\/$/, '');
const CRED = JSON.parse(fs.readFileSync(process.env.CRED || 'cred.json', 'utf8'));
const PDF = process.env.PDF || 'a.pdf';
const paso = []; const fallas = [];
const ok = (m) => { paso.push('OK  ' + m); }; const ko = (m) => { fallas.push(m); paso.push('FALLA ' + m); };
const exige = (c, m) => (c ? ok(m) : ko(m));
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined, args: ['--no-sandbox'] });
  async function sesion(run) {
    const ctx = await browser.newContext({ locale: 'es-CL', viewport: { width: 1300, height: 950 } }); const page = await ctx.newPage();
    const errs = []; page.on('pageerror', e => errs.push(e.message)); page.on('console', m => { if (m.type() === 'error') errs.push(m.text()); });
    await page.goto(BASE + '/ingreso/pruebas'); await page.fill('#run', run); await page.fill('#password', CRED[run]); await page.click('button:has-text("Ingresar")'); await page.waitForLoadState('load');
    return { ctx, page, errs };
  }
  // 1. ciudadano
  let s = await sesion('20000001-3'); let p = s.page;
  exige(!/ingreso/.test(p.url()), 'ciudadano inicia sesión con usuario y contraseña');
  await p.goto(BASE + '/ciudadano/nueva/1'); await p.fill('#correo', 'vecina@example.com'); await p.fill('#detalle', 'Solicito permiso de edificación para obra nueva en mi terreno.');
  for (const el of await p.$$('input[type=file][name^="anexo_"]')) await el.setInputFiles(PDF);
  await p.click('button:has-text("Enviar solicitud")'); await p.waitForLoadState('load');
  const folio = ((await p.content()).match(/SD-\d{4}-[A-Z0-9]+/) || [])[0];
  exige(!!folio, 'la solicitud se ingresa y recibe folio ' + folio);
  const compr = await p.$('a[href*="comprobante"]'); exige(!!compr, 'se entrega comprobante electrónico');
  await s.ctx.close();
  // 2. funcionario DOM
  s = await sesion('10000004-0'); p = s.page;
  await p.goto(BASE + '/app'); exige((await p.content()).includes(folio), 'la solicitud aparece en la bandeja de la unidad');
  await p.goto(BASE + '/app/solicitudes/' + folio); await p.click('button:has-text("Asignármela")'); await p.waitForLoadState('load');
  exige(/\/app\/expedientes\/\d+/.test(p.url()), 'se asigna y se crea el expediente');
  const expUrl = p.url();
  await p.selectOption('#tipo_documento_id', { label: 'Oficio' }); await p.selectOption('#plantilla_id', await p.$eval('#plantilla_id', sel => [...sel.options].find(o => /Oficio/.test(o.textContent)).value)); await p.fill('#titulo', 'Oficio de respuesta E2E');
  await p.click('button:has-text("Crear")'); await p.waitForLoadState('load');
  exige(/\/app\/documentos\/\d+/.test(p.url()), 'se crea el documento desde una plantilla');
  const docUrl = p.url();
  await p.waitForSelector('.tox-tinymce'); exige(true, 'el editor TinyMCE completo carga');
  await p.evaluate(() => tinymce.activeEditor.insertContent('<p style="color:#003366"><strong>Se resuelve favorablemente.</strong></p>'));
  await p.selectOption('#nivel_acceso', 'publico');
  await p.click('button:has-text("Guardar nueva versión")'); await p.waitForLoadState('load');
  exige((await p.content()).includes('Se resuelve favorablemente'), 'se guarda una nueva versión con el contenido del editor');
  await p.selectOption('#flujo', 'agil'); await p.selectOption('#fir1', await p.$eval('#fir1', sel => [...sel.options].find(o => /Secretaria/.test(o.textContent)).value));
  await p.click('button:has-text("Iniciar flujo")'); await p.waitForLoadState('load');
  exige(/en flujo/.test(await p.textContent('body')), 'se inicia el flujo de firma');
  await s.ctx.close();
  // 3. secretaria firma
  s = await sesion('10000003-2'); p = s.page;
  await p.goto(docUrl); await p.fill('#otp', '123456'); await p.click('button:has-text("Firmar")'); await p.waitForLoadState('load');
  const html = await p.content(); const codigo = (html.match(/código <strong>([A-Z0-9-]+)<\/strong>/) || [])[1];
  exige(/vigente/.test(await p.textContent('body')) && !!codigo, 'la firma deja el documento vigente con número y código ' + codigo);
  await s.ctx.close();
  // 4. público
  const ctx = await browser.newContext({ locale: 'es-CL' }); const pub = await ctx.newPage();
  await pub.goto(BASE + '/validar/' + codigo); exige((await pub.textContent('body')).includes('Oficio'), 'validación pública del documento por código');
  await pub.goto(BASE + '/publico'); exige((await pub.textContent('body')).includes('Oficio de respuesta E2E'), 'el documento público aparece en el listado');
  await ctx.close();
  // 5. auditor y admin ven la trazabilidad
  s = await sesion('10000007-5'); p = s.page; await p.goto(BASE + '/app/logs?accion=documento'); exige((await p.textContent('body')).includes('documento.firmar'), 'el auditor ve la firma en el registro de auditoría');
  await p.goto(BASE + '/app/seguridad'); await p.click('button:has-text("Verificar cadena")'); await p.waitForLoadState('load'); exige(/Cadena íntegra/.test(await p.textContent('body')), 'la cadena de auditoría HMAC es íntegra');
  await s.ctx.close();
  await browser.close();
  console.log(paso.join('\n')); console.log(fallas.length ? '\nFALLAS: ' + fallas.length : '\nTODO OK');
  process.exit(fallas.length ? 1 : 0);
})().catch(e => { console.log(paso.join('\n')); console.error('EXCEPCIÓN', e.message.split('\n')[0]); process.exit(2); });
