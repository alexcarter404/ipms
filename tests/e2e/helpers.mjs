import { createHmac } from 'node:crypto';

/**
 * Locate a form control by the text of its sibling <label> — the app's
 * form fields are wrapped as <div><label>…</label><control></div>.
 * Inputs/textareas are native elements; selects are PrimeVue Selects
 * (use pickOption to choose a value).
 */
export const field = (scope, label, control = 'input') => {
    const selector = control === 'select' ? '.p-select' : control;

    return scope.locator(`div:has(> label:has-text("${label}")) ${selector}`).first();
};

/**
 * Choose an option in a PrimeVue Select: open the labelled select, then
 * click the overlay option matching `optionText` (string = substring,
 * regex supported). The overlay is portalled to <body>, hence `page`.
 */
export const pickOption = async (page, scope, label, optionText) => {
    await field(scope, label, 'select').click();
    await page
        .locator('.p-select-overlay [role="option"]', { hasText: optionText })
        .first()
        .click();
};

/** Choose an option in a PrimeVue Select located directly (no label). */
export const pickOptionIn = async (page, selectLocator, optionText) => {
    await selectLocator.click();
    await page
        .locator('.p-select-overlay [role="option"]', { hasText: optionText })
        .first()
        .click();
};

/** RFC 6238 TOTP (SHA-1, 6 digits, 30s) from a base32 secret — enough to
 *  act as an authenticator app in E2E tests. */
export const totp = (secret, time = Date.now()) => {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    let bits = '';
    for (const c of secret.replace(/=+$/, '').toUpperCase()) {
        bits += alphabet.indexOf(c).toString(2).padStart(5, '0');
    }
    const bytes = [];
    for (let i = 0; i + 8 <= bits.length; i += 8) {
        bytes.push(parseInt(bits.slice(i, i + 8), 2));
    }

    const counter = Buffer.alloc(8);
    counter.writeBigUInt64BE(BigInt(Math.floor(time / 1000 / 30)));

    const digest = createHmac('sha1', Buffer.from(bytes)).update(counter).digest();
    const offset = digest[digest.length - 1] & 0xf;
    return ((digest.readUInt32BE(offset) & 0x7fffffff) % 1_000_000)
        .toString()
        .padStart(6, '0');
};

/** Milliseconds until the next 30s TOTP window opens. */
export const msToNextTotpWindow = (time = Date.now()) =>
    30_000 - (time % 30_000) + 250;
