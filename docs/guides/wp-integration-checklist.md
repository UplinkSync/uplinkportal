# UplinkPortal WP Integration Checklist

Use this after pulling changes to validate admin UX and core plugin wiring in a real WordPress install.

## Preconditions

- WordPress admin user with `msp_manage` capability.
- Plugin activated from `uplinkportal.php`.
- Browser cache disabled (or hard refresh after asset changes).
- Optional: at least 1 user assigned to a UplinkPortal client role.

## 1. Modules Tab + Admin Navigation

1. Go to `UplinkPortal > Configuration > Modules`.
2. Enable `IT Support` and save.
3. Confirm module card state updates and no diagnostics panels are shown in Modules.
4. Disable `IT Support` and save.
5. Confirm no standalone module submenu appears (`Support Queue`, `Client Portfolio`, `Revenue & Billing`).
6. Open `UplinkPortal > Operations Dashboard`.
7. Confirm the page loads without errors.

Expected:

- Module state persists after refresh.
- Unexpected module menu scatter does not reappear.

## 2. Service Catalog Plans UX

1. Open `UplinkPortal > Service Catalog`.
2. Click gear icon on an existing plan card.
3. Update title/description/icon, toggle modules, click `Done`.
4. Confirm card preview updates live.
5. Click outside modal and confirm it closes.
6. Press `Esc` while modal is open and confirm it closes.
7. Click `+ Create Plan`.
8. Confirm a new plan modal opens with default values.
9. Save all plan templates.
10. Refresh page and confirm changes persist.

Expected:

- No modal/window lock state.
- Only one modal remains open at a time.
- New plan persists with selected module checkboxes.

## 2.1 Tools & Diagnostics Placement

1. Open `UplinkPortal > Tools & Diagnostics`.
2. Confirm these sections render on this page:
3. `Integration Status Board`
4. `Integration Policy Lint` (`<details>` collapsible)
5. `Integration Profile Test Harness` (`<details>` collapsible)
6. `Secret Lease Alerts`
7. Return to `Configuration > Modules` and confirm these sections are not displayed there.

Expected:

- Diagnostics content is centralized under `Tools & Diagnostics`.
- Modules tab remains focused on module catalog/configuration.

## 2.2 Enterprise UI Consistency Check

1. Open `Configuration > Modules`.
2. Confirm module tiles have equal visual weight and clean spacing.
3. Confirm each tile shows only: name, short description, status, primary action.
4. Confirm icon boxes are aligned (fixed size) and labels align consistently.
5. Open `Tools & Diagnostics` and verify card spacing/heading hierarchy matches Modules.
6. Open `Configuration > Security` and verify monitoring warning is inline in the field area (not global banner).
7. Resize browser to narrow admin widths and confirm:
8. Tables scroll inside local containers, with no page-level horizontal overflow.
9. Long values wrap or truncate safely inside cards/rows.

Expected:

- Module tiles align and read as one consistent grid.
- No overflow in diagnostics/status tables.
- Icons align with labels across module cards and tabs.
- Consistent vertical padding between sections/cards.

## 3. Service Catalog + Client Request Direction

1. In `Service Catalog`, review `Services` rows.
2. Confirm active/disabled badges match `Configuration > Modules` values.
3. Confirm no `Create Fulfillment Task` or `Request Add-on` action buttons are present in MSP admin.
4. Sign in as a client role and open the page containing `[uplinkportal]`.
5. In `My Services`, submit a service request form.
6. Confirm the portal shows a success notice after redirect.
7. Open `UplinkPortal > Operations Dashboard` as MSP admin and confirm the request appears in `Manual Fulfillment Requests`.

Expected:

- Services status is consistent with module toggles.
- MSP provider/admin users cannot originate fulfillment requests from admin pages.
- Client portal users can submit requests that create provider-facing queue records.

## 4. Agreements & Records Browser

1. Open `UplinkPortal > Agreements & Records`.
2. Confirm the initial view is folder cards/list only (documents workspace hidden).
3. Click different client/org folders.
4. Confirm documents workspace appears with:
5. left document list panel, right preview panel, and a `Back to Folders` control above the list.
6. Click documents and verify preview panel updates title/type/status/date/summary.
7. Click `Back to Folders` and confirm folder list view returns.
8. Test a folder with no docs (if available).

Expected:

- Folder-first flow works without stale content.
- Folder navigation updates list and preview reliably.
- Back control always returns to folder list.
- Empty folders show a safe placeholder message.

## 5. Operations Dashboard

1. Open `UplinkPortal > Operations Dashboard`.
2. Confirm metric cards render (Enabled Modules, Plan Templates, Client Accounts, API Connections Ready, License Health, Open Fulfillment Requests).
3. Toggle modules in Configuration, return to dashboard, refresh.

Expected:

- Metrics update based on saved plugin state.
- Module metrics section reflects current enabled/disabled state.

## 6. Onboarding Completion Redirect

1. Run onboarding to final step (`Security Baseline`).
2. Submit final step.

Expected:

- Redirect lands on `UplinkPortal > Operations Dashboard` (`page=uplinkportal`).

## 7. Regression Smoke

1. `Configuration > Client Access`: open/close client override modal.
2. `Configuration > Modules`: save backend/integration settings.
3. `Configuration > Modules > Database > Licensing`: validate license action still posts successfully.
4. `Client Portal` shortcode page loads.

Expected:

- No fatal errors, JS console errors, or broken admin navigation.

## 8. Company Info Logo Picker UX

1. Open `UplinkPortal > Configuration > Company Info`.
2. Confirm `Company Logo` shows preview area and a `Change Logo` button (not attachment ID text input).
3. Click `Change Logo` and select a valid PNG or SVG (up to 200x200, under 200KB).
4. Save and confirm success notice and persisted preview.
5. Repeat with invalid selections:
6. Image larger than 200x200 dimensions.
7. Image larger than 200KB.
8. Non-PNG/SVG image.

Expected:

- Media Library picker opens from the button.
- Friendly validation alerts appear for invalid selections.
- Invalid selections are blocked client-side.
- Server-side validation also rejects invalid logo IDs on save.
