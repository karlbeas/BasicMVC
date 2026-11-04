// Run: node tests/js/expressions.test.cjs (also run by tests/Unit/ExpressionParityTest.php when node exists)
'use strict';
const path = require('path');
const expr = require(path.join(__dirname, '../../public/assets/js/f2s-expr.js'));
const cases = require(path.join(__dirname, '../fixtures/expressions.json'));
let failed = 0;
for (const c of cases) {
    let got, error = false;
    try { got = expr.evaluate(c.expr, c.values || {}, c.current || ''); } catch (e) { error = true; }
    const ok = c.error ? error : (!error && (typeof c.expect === 'number' ? Math.abs(got - c.expect) < 1e-9 : got === c.expect));
    if (!ok) { failed++; console.error('FAIL', JSON.stringify(c), '=>', error ? 'error' : JSON.stringify(got)); }
}
console.log(failed === 0 ? `OK ${cases.length} cases` : `${failed} failure(s)`);
process.exit(failed === 0 ? 0 : 1);
