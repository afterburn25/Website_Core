'use strict';

const { chromium } = require('playwright');

const baseUrl = process.env.CATERING_PREVIEW_URL;
const outputDirectory = process.env.CATERING_PREVIEW_OUTPUT;
if (!baseUrl || !outputDirectory) {
  throw new Error('Set CATERING_PREVIEW_URL and CATERING_PREVIEW_OUTPUT.');
}

const themes = [
  'heritage-editorial',
  'modern-culinary',
  'garden-celebration',
  'grand-wedding',
  'corporate-table',
  'rustic-gathering',
  'bayou-night',
  'market-kitchen',
  'social-feast',
  'minimal-luxe',
];
const viewports = [
  { name: 'desktop', width: 1440, height: 1000 },
  { name: 'mobile', width: 390, height: 844 },
];

(async () => {
  const browser = await chromium.launch({ headless: true });
  const results = [];

  for (const viewport of viewports) {
    const context = await browser.newContext({ viewport, deviceScaleFactor: 1 });
    for (const theme of themes) {
      const page = await context.newPage();
      const errors = [];
      page.on('console', (message) => {
        if (message.type() === 'error') errors.push(message.text());
      });
      page.on('pageerror', (error) => errors.push(error.message));

      await page.goto(`${baseUrl.replace(/\/$/, '')}/${theme}/`, { waitUntil: 'networkidle' });
      await page.evaluate(() => document.fonts && document.fonts.ready);
      const report = await page.evaluate(() => {
        const header = document.querySelector('[data-site-header]');
        const navigation = document.querySelector('[data-nav]');
        const box = header ? header.getBoundingClientRect() : null;
        return {
          title: document.title,
          headerHeight: box ? Math.round(box.height) : null,
          headerTop: box ? Math.round(box.top) : null,
          navLinks: navigation ? navigation.querySelectorAll('a').length : 0,
          bodyWidth: document.body.scrollWidth,
          viewportWidth: innerWidth,
          brokenImages: [...document.images]
            .filter((image) => !image.complete || image.naturalWidth === 0)
            .map((image) => image.src),
          navDisplay: navigation ? getComputedStyle(navigation).display : null,
        };
      });

      await page.evaluate(() => scrollTo(0, Math.min(900, document.documentElement.scrollHeight - innerHeight)));
      await page.waitForTimeout(120);
      report.stickyTop = await page.locator('[data-site-header]').evaluate((element) =>
        Math.round(element.getBoundingClientRect().top));
      report.errors = errors;

      if (viewport.name === 'mobile') {
        await page.evaluate(() => scrollTo(0, 0));
        const toggle = page.locator('[data-nav-toggle]');
        if (await toggle.count()) {
          await toggle.click();
          report.mobileOpen = await page.locator('[data-nav]').evaluate((element) => ({
            visible: getComputedStyle(element).display !== 'none',
            expanded: document.querySelector('[data-nav-toggle]')?.getAttribute('aria-expanded'),
          }));
        }
      }

      await page.screenshot({
        path: `${outputDirectory}/${theme}-${viewport.name}.png`,
        fullPage: false,
      });
      results.push({ theme, viewport: viewport.name, ...report });
      await page.close();
    }
    await context.close();
  }

  await browser.close();
  const failures = results.filter((result) =>
    result.headerTop !== 0 || result.stickyTop !== 0 || result.headerHeight > 82
      || result.navLinks < 4 || result.bodyWidth > result.viewportWidth + 1
      || result.brokenImages.length > 0 || result.errors.length > 0
      || (result.viewport === 'mobile'
        && (!result.mobileOpen?.visible || result.mobileOpen?.expanded !== 'true')));
  console.log(JSON.stringify({ checked: results.length, failures, results }, null, 2));
  if (failures.length > 0) process.exitCode = 1;
})().catch((error) => {
  console.error(error);
  process.exit(1);
});
