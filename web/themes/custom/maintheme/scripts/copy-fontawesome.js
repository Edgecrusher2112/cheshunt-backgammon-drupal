const fs = require('node:fs');
const path = require('node:path');

const theme = path.resolve(__dirname, '..');
const source = path.join(theme, 'node_modules/@fortawesome/fontawesome-free');
const destination = path.join(theme, 'assets/fontawesome');

fs.mkdirSync(path.join(destination, 'css'), { recursive: true });
for (const file of ['fontawesome.min.css', 'solid.min.css', 'regular.min.css', 'brands.min.css']) {
  fs.copyFileSync(path.join(source, 'css', file), path.join(destination, 'css', file));
}
fs.cpSync(path.join(source, 'webfonts'), path.join(destination, 'webfonts'), { recursive: true });
fs.copyFileSync(path.join(source, 'LICENSE.txt'), path.join(destination, 'LICENSE.txt'));
