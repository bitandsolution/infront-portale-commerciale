/*
 * Puppeteer configuration (D-308). Browsershot launches Chromium in "shell" headless mode, so only
 * chrome-headless-shell of Chrome for Testing is used: never the full Chrome, never the Ubuntu
 * "chromium" package (a snap stub that does not work in Docker).
 *
 * .npmrc has ignore-scripts=true, so `npm ci` never downloads it: the browser is installed explicitly
 * with `npx puppeteer browsers install chrome-headless-shell`, which takes the version pinned by
 * puppeteer and does nothing if it is already there.
 *
 * The browser goes into .cache/puppeteer inside the project (ignored by git), so the Sail container,
 * the CI runner and a release built on the server find it next to node_modules. PUPPETEER_CACHE_DIR
 * moves it elsewhere (for example a shared folder on the server, outside the releases).
 */
const { join } = require('node:path');

module.exports = {
    cacheDirectory: process.env.PUPPETEER_CACHE_DIR || join(__dirname, '.cache', 'puppeteer'),
    chrome: { skipDownload: true },
    'chrome-headless-shell': { skipDownload: false },
    firefox: { skipDownload: true },
};
