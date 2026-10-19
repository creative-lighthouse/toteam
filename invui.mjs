import { chromium } from 'playwright-core'
import fs from 'fs'
const [tokAdmin, tokSteffen] = fs.readFileSync('tokens2.txt', 'utf8').trim().split('\n')
const base = 'https://toteam.ddev.site'
const browser = await chromium.launch({ executablePath: process.env.HOME + '/.cache/ms-playwright/chromium-1140/chrome-linux/chrome', args: ['--ignore-certificate-errors'] })
async function shot(token, url, out, sel) {
  const check = await (await fetch(base + '/api/v1/auth/check', { headers: { Authorization: 'Bearer ' + token } })).json()
  const page = await browser.newPage({ ignoreHTTPSErrors: true, viewport: { width: 1300, height: 900 } })
  page.on('pageerror', e => console.log('PAGEERROR', e.message))
  await page.route('**/api/v1/auth/refresh', r => r.fulfill({ json: { success: true, accessToken: token, user: check.user } }))
  await page.goto(base + url, { waitUntil: 'networkidle' })
  await page.waitForTimeout(1500)
  const el = page.locator(sel).first()
  console.log(out, await el.count())
  if (await el.count()) await el.screenshot({ path: out })
  await page.close()
}
await shot(tokAdmin, '/app/dashboard', 'dash_inv.png', '.infobox--invitations')
await shot(tokSteffen, '/app/organizations/der_verein/manage', 'manage_inv.png', '.org-manage_section:last-of-type')
await browser.close()
