const chromium = require('@sparticuz/chromium');
const puppeteer = require('puppeteer-core');
const sleep = ms => new Promise(r => setTimeout(r, ms));
(async () => {
  const browser = await puppeteer.launch({executablePath: await chromium.executablePath(), headless: true,
    args: [...chromium.args, '--use-fake-ui-for-media-stream', '--use-fake-device-for-media-stream', '--no-sandbox'],
    defaultViewport: {width: 1400, height: 900}});
  const page = await browser.newPage();
  page.on('pageerror', e => console.log('PAGEERROR', e.message));
  await page.goto((process.env.EP_URL || 'http://localhost') + '/login/index.php');
  await page.type('#username', 'student1'); await page.type('#password', 'Pass#1234');
  await Promise.all([page.waitForNavigation(), page.click('#loginbtn')]);
  await page.goto((process.env.EP_URL || 'http://localhost') + '/mod/quiz/view.php?id=' + (process.env.EP_CMID || 4)); await page.waitForSelector('.quizstartbuttondiv button, .quizstartbuttondiv input[type=submit]', {timeout: 15000});
  await Promise.all([page.waitForNavigation(), page.click('.quizstartbuttondiv button, .quizstartbuttondiv input[type=submit]')]);
  await page.waitForSelector('#id_examproctorconsent'); await page.click('#id_examproctorconsent');
  await Promise.all([page.waitForNavigation(), page.click('#id_submitbutton')]);
  const info = async (sel) => page.evaluate((sel) => {
    const el = document.querySelector(sel); if (!el) { return 'NO ' + sel; }
    const r = el.getBoundingClientRect(); const top = document.elementFromPoint(r.x + r.width / 2, r.y + r.height / 2);
    const ov = document.getElementById('examproctor-overlay-text');
    const d = x => x ? x.tagName.toLowerCase() + (x.id ? '#' + x.id : '') + (typeof x.className === 'string' && x.className ? '.' + x.className.trim().split(/\s+/).slice(0, 2).join('.') : '') : 'none';
    return 'fs=' + !!document.fullscreenElement + ' overlay=' + (ov ? getComputedStyle(ov.parentElement).display : '-') + ' | under ' + sel + ': ' + d(top) + (el.contains(top) || top === el ? ' (OK)' : ' (BLOCKED)');
  }, sel);
  const enterFs = async () => { const b = await page.evaluateHandle(() => { const t = document.getElementById('examproctor-overlay-text'); return t && getComputedStyle(t.parentElement).display !== 'none' ? t.parentElement.querySelector('button') : null; }); if (b.asElement()) { await b.asElement().click(); await sleep(1200); } };
  await sleep(4000); await enterFs();
  console.log('P1  ', await info('.answer input[type=radio]'));
  await page.click('.answer input[type=radio]'); await sleep(300);
  console.log('P1  ', await info('#mod_quiz-next-nav'));
  await Promise.all([page.waitForNavigation(), page.click('#mod_quiz-next-nav')]);
  await sleep(4000);
  console.log('SUMMARY fs=', await page.evaluate(() => !!document.fullscreenElement));
  const sub = await page.evaluateHandle(() => [...document.querySelectorAll('button, input[type=submit]')].find(b => /Submit all and finish/i.test(b.textContent || b.value)));
  await sub.asElement().evaluate(b => b.id = b.id || 'ep-submitall');
  console.log('SUMMARY', await info('#' + (await sub.asElement().evaluate(b => b.id)).replace(/:/g, '\\:')));
  await sub.asElement().click(); await sleep(1500);
  const conf = await page.evaluateHandle(() => [...document.querySelectorAll('.modal.show button')].find(b => /Submit all and finish|Submit/i.test(b.textContent)));
  if (conf.asElement()) {
    await conf.asElement().evaluate(b => b.id = 'ep-confirm');
    console.log('CONFIRM', await info('#ep-confirm'));
    await Promise.all([page.waitForNavigation({timeout: 10000}).catch(() => null), conf.asElement().click()]);
  } else { console.log('CONFIRM modal not found'); }
  await sleep(1500);
  console.log('AFTER', page.url().split('/').pop());
  await page.screenshot({path: '/tmp/rb/summary.png'});
  await browser.close();
})().catch(e => { console.log('SCRIPT ERROR', e.message); process.exit(1); });
