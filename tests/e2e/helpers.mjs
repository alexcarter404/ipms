/**
 * Locate a form control by the text of its sibling <label> — the app's
 * form fields are wrapped as <div><label>…</label><input|select|textarea></div>.
 */
export const field = (scope, label, control = 'input') =>
    scope.locator(`div:has(> label:has-text("${label}")) ${control}`).first();
