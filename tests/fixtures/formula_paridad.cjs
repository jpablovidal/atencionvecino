// Evalúa los vectores de tests/fixtures/formulas.json con el motor del navegador (public/assets/js/formulario.js) y escribe los resultados en JSON.
const fs = require('fs');
const path = require('path');
const vm = require('vm');
global.window = global;
vm.runInThisContext(fs.readFileSync(path.join(__dirname, '../../public/assets/js/formulario.js'), 'utf8'));
const F = global.SgdFormula;
const vectores = JSON.parse(fs.readFileSync(path.join(__dirname, 'formulas.json'), 'utf8'));
const salida = vectores.map((v) => {
  try {
    const ast = F.analizar(v.f);
    const vars = {};
    for (const [k, x] of Object.entries(v.v || {})) { vars[k] = typeof x === 'string' ? F.diaDe(x) : x; }
    const hoy = F.diaDe(v.hoy || '2026-10-22');
    return { r: F.evaluar(ast, vars, hoy) };
  } catch (e) {
    return { e: true };
  }
});
process.stdout.write(JSON.stringify(salida));
