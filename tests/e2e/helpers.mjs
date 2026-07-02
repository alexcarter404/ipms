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
