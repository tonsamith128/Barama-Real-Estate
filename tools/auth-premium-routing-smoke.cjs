'use strict';

// Read-only presentation-routing regression for all four auth enhancement layers.
// The fixture intentionally does not submit forms or access a database.
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const scripts = [
  'auth-premium-v117a33.js',
  'auth-copy-v117a34.js',
  'auth-centered-v117a35.js',
  'auth-title-centering-v117a36.js',
];

const cases = [
  ['/login', '', true],
  ['/register', '', true],
  ['/forgot-password', '', true],
  ['/login/', '', true],
  ['/barama/login', '', true],
  ['/index.php', '?_route=%2Flogin', true],
  ['/index.php', '?_route=%2Fregister', true],
  ['/index.php', '?_route=%2Fforgot-password', true],
  ['/barama/index.php', '?_route=%2Flogin', true],
  ['/barama/index.php', '?source=menu&_route=%2Fregister', true],
  ['/contact', '', false],
  ['/index.php', '?_route=%2Fdashboard', false],
  ['/index.php', '?_route=%2Flogin%2Fother', false],
  ['/contact', '?_route=%2Flogin', false],
  ['/index.php', '?_route=https%3A%2F%2Fexample.test', false],
];

let checks = 0;
for (const file of scripts) {
  const script = fs.readFileSync(path.join(root, 'assets', 'js', file), 'utf8');
  for (const [pathname, search, expected] of cases) {
    const handlers = [];
    const context = {
      URLSearchParams,
      location: { pathname, search },
      document: {
        readyState: 'loading',
        currentScript: { getAttribute: () => '' },
        addEventListener(event, callback) {
          if (event === 'DOMContentLoaded') handlers.push(callback);
        },
      },
      console: { warn() {} },
    };
    vm.runInNewContext(script, context, { filename: file, timeout: 1200 });
    assert.equal(
      handlers.length === 1,
      expected,
      `${file}: ${pathname}${search} was ${handlers.length ? 'active' : 'inactive'}`
    );
    checks++;
  }
}
console.log(`[PASS] Premium authentication URL modes: ${checks} script/route checks`);


const a33 = fs.readFileSync(path.join(root, 'assets/js/auth-premium-v117a33.js'), 'utf8');
const a36 = fs.readFileSync(path.join(root, 'assets/js/auth-title-centering-v117a36.js'), 'utf8');
assert.equal(a33.includes('.style.'), false, 'A33 must use CSS classes, not element.style');
assert.equal(a36.includes('.style.'), false, 'A36 must use stylesheet alignment, not element.style');
const authCss = fs.readFileSync(path.join(root, 'assets/css/auth-premium-v117a33.css'), 'utf8');
assert.equal(authCss.includes('.barama-auth-a33__original-name-field {'), true, 'A33 hidden name field needs an external CSS rule');
console.log('[PASS] Premium auth uses external stylesheets for layout.');
