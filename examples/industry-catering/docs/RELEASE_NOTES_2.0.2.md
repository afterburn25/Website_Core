# Catering 2.0.2 Release Notes

Catering 2.0.2 repairs the administration navigation and hardens page-route
acceptance for Website Core 2.6.2.

## Changes

- Menus, packages, menu collections, events and tastings, production,
  settings, and both quick managers now use eight distinct Core icons.
- The former generic layout-variant interface is replaced by
  **Catering Themes**: ten real layout thumbnails, a color-palette dropdown on
  each card, optional image overrides, and one-click theme activation.
- The integration suite opens every static public destination, every service
  detail, every Catering administration destination, and an event detail.
- The operator guide now calls out Core's **Finalize update** action when
  module files are replaced through a hosting file manager.

## Upgrade

1. Update Website Core to 2.6.2.
2. Install `industry-catering-2.0.2.zip` through Core's Module Manager.
3. If the module files were already copied directly, use **Modules → Catering
   Website → Finalize update** instead. This runs pending migrations and keeps
   existing menus, inquiries, events, settings, and media relationships.

Do not copy the combined fresh-install bundle over an existing site as a
substitute for the Module Manager or Finalize update workflow.
