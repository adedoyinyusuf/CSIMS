# CSIMS UI Utilities Guide

This guide documents shared UI utilities for icons and components, to ensure consistency and remove repeated inline styles.

## Icon Utilities (assets/css/icons.css)
Use these classes on icon elements (e.g., `<i class="fas fa-...">`).

- Colors
  - `icon-primary` → `color: var(--primary-color, var(--true-blue))`
  - `icon-secondary` → `color: var(--secondary-color, var(--lapis-lazuli))`
  - `icon-accent` → `color: var(--accent-color)`
  - `icon-success` → `color: var(--success)`
  - `icon-warning` → `color: var(--warning)`
  - `icon-error` → `color: var(--error)`
  - `icon-info` → `color: var(--info)`
  - `icon-muted` → `color: var(--text-muted)`
  - `icon-lapis` → `color: var(--lapis-lazuli)`
  - `icon-orange` → `color: var(--persian-orange)`
  - `icon-jasper` → `color: var(--jasper)`
  - `icon-text-primary` → `color: var(--text-primary)`

- Sizes
  - `icon-sm` → `font-size: 0.875rem`
  - `icon-md` → `font-size: 1rem`
  - `icon-lg` → `font-size: 1.25rem`

### Examples
- Replace inline `style="color: var(--success);"`:
  - Before: `<i class="fas fa-check-circle" style="color: var(--success);"></i>`
  - After: `<i class="fas fa-check-circle icon-success"></i>`

- Replace inline `style="color: var(--accent-color);"`:
  - Before: `<i class="fas fa-shield-alt" style="color: var(--accent-color);"></i>`
  - After: `<i class="fas fa-shield-alt icon-accent"></i>`

## Component Utilities (assets/css/components.css)
Common UI elements for consistent structure and styling.

- Status badges
  - `status-badge`, `status-enabled`, `status-disabled`
- Steps
  - `step-indicator`, `step`, `active`, `completed`
- QR container
  - `qr-container`
- Security info
  - `security-info` (banner block)
- Page header
  - `page-header` (title layout, spacing)

### Usage Tips
- Prefer utility classes over inline styles for color/sizing.
- If a page needs a new palette token, add a corresponding `icon-*` utility in `icons.css`.
- For icons inside gradient circles, keep `text-white` or relevant contrasting class; use `icon-*` only when the icon color should reflect the token directly.
- Keep utilities global by ensuring `views/includes/header.php` links `assets/css/icons.css` and `assets/css/components.css`.

## Migration Checklist
- Search for `<i ... style="color: var(--...);">` and replace with `icon-*`.
- Verify pages visually (alerts, headers, quick actions, cards).
- Avoid changing icon markup where gradient backgrounds require white icons.