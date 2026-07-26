const fs = require('fs');
const path = require('path');
const { test, expect } = require('@playwright/test');
const {
  getFinanceDirectoryEntryByReg,
  setMemberEmail,
  submitMemberFinanceEntry,
} = require('../helpers/app-actions');
const { loginAs } = require('../helpers/browser');

const EMAIL_LOG_PATH = path.join(process.cwd(), 'logs', 'email_log.txt');

function readEmailLogLines() {
  if (!fs.existsSync(EMAIL_LOG_PATH)) {
    return [];
  }
  return fs.readFileSync(EMAIL_LOG_PATH, 'utf8').split('\n').filter(Boolean);
}

async function getPaymentIdForMember(accountantPage, memberUserId) {
  await accountantPage.goto(`./user_finance_view.php?user_id=${memberUserId}`);
  const stornoPath = await accountantPage.locator('a', { hasText: 'Storno' }).first().getAttribute('href');
  const match = stornoPath && stornoPath.match(/[?&]trn_id=(\d+)/);
  if (!match) {
    throw new Error(`Could not find a payment id for member ${memberUserId}`);
  }
  return match[1];
}

async function submitClaim(page, paymentId, text) {
  const url = `./claim.php?payment_id=${paymentId}&submit=1&claim_text=${encodeURIComponent(text)}`;
  const response = await page.goto(url);
  const bodyText = await page.content();
  expect(response.ok()).toBeTruthy();
  expect(bodyText).not.toMatch(/Nepodařilo se|Chyba při provádění dotazu|Fatal error|Warning:/i);
}

async function closeClaim(page, paymentId) {
  const url = `./claim.php?payment_id=${paymentId}&close=1`;
  const response = await page.goto(url);
  const bodyText = await page.content();
  expect(response.ok()).toBeTruthy();
  expect(bodyText).not.toMatch(/Nepodařilo se|Chyba při provádění dotazu|Fatal error|Warning:/i);
}

test.describe('Claim notification emails', () => {
  test.describe.configure({ mode: 'serial' });

  const memberEmail = 'claim-notify-member@example.test';
  const accountantEmail = 'claim-notify-accountant@example.test';

  test('member raising a claim notifies the payment editor', async ({ browser }) => {
    const memberContext = await browser.newContext();
    const accountantContext = await browser.newContext();
    const clubAdminContext = await browser.newContext();
    const memberPage = await memberContext.newPage();
    const accountantPage = await accountantContext.newPage();
    const clubAdminPage = await clubAdminContext.newPage();

    try {
      await loginAs(accountantPage, 'accountant');
      await loginAs(memberPage, 'member');
      await loginAs(clubAdminPage, 'clubAdmin');

      const memberEntry = await getFinanceDirectoryEntryByReg(accountantPage, '9952', {
        path: './index.php?id=800&subid=1',
      });
      const accountantEntry = await getFinanceDirectoryEntryByReg(accountantPage, '8357', {
        path: './index.php?id=800&subid=1',
      });
      expect(memberEntry).toBeTruthy();
      expect(accountantEntry).toBeTruthy();

      // user_edit.php (used by setMemberEmail) requires small-admin/manager rights,
      // which the accountant test account does not have - use clubAdmin instead.
      await setMemberEmail(clubAdminPage, memberEntry.userId, memberEmail);
      await setMemberEmail(clubAdminPage, accountantEntry.userId, accountantEmail);

      await submitMemberFinanceEntry(accountantPage, memberEntry.userId, 'out', {
        amount: 10,
        note: 'claim-notify-test-payment',
      });
      const paymentId = await getPaymentIdForMember(accountantPage, memberEntry.userId);

      const before = readEmailLogLines().length;
      await submitClaim(memberPage, paymentId, 'Nesouhlasím s touto platbou.');
      const afterCreate = readEmailLogLines();
      const createLines = afterCreate.slice(before);

      expect(createLines.length).toBe(1);
      expect(createLines[0]).toContain(`to '${accountantEmail}'`);

      await submitClaim(accountantPage, paymentId, 'Podíváme se na to.');
      const afterReply = readEmailLogLines();
      const replyLines = afterReply.slice(afterCreate.length);

      expect(replyLines.length).toBe(1);
      expect(replyLines[0]).toContain(`to '${memberEmail}'`);

      await closeClaim(accountantPage, paymentId);
      const afterClose = readEmailLogLines();
      const closeLines = afterClose.slice(afterReply.length);

      expect(closeLines.length).toBe(1);
      expect(closeLines[0]).toContain(`to '${memberEmail}'`);
    } finally {
      await memberContext.close();
      await accountantContext.close();
      await clubAdminContext.close();
    }
  });

  test('accountant raising a claim on their own payment does not self-notify', async ({ browser }) => {
    const accountantContext = await browser.newContext();
    const clubAdminContext = await browser.newContext();
    const accountantPage = await accountantContext.newPage();
    const clubAdminPage = await clubAdminContext.newPage();

    try {
      await loginAs(accountantPage, 'accountant');
      await loginAs(clubAdminPage, 'clubAdmin');

      const accountantEntry = await getFinanceDirectoryEntryByReg(accountantPage, '8357', {
        path: './index.php?id=800&subid=1',
      });
      expect(accountantEntry).toBeTruthy();

      await setMemberEmail(clubAdminPage, accountantEntry.userId, accountantEmail);

      await submitMemberFinanceEntry(accountantPage, accountantEntry.userId, 'out', {
        amount: 5,
        note: 'claim-notify-self-test-payment',
      });
      const paymentId = await getPaymentIdForMember(accountantPage, accountantEntry.userId);

      const before = readEmailLogLines().length;
      await submitClaim(accountantPage, paymentId, 'Reklamace na vlastní platbu.');
      const after = readEmailLogLines();

      expect(after.length).toBe(before);
    } finally {
      await accountantContext.close();
      await clubAdminContext.close();
    }
  });
});
