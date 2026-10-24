# Packaging Rules

These rules are permanent release requirements for Website Core and every
industry build distributed with it.

## One download, one ZIP

- A customer downloads exactly one ZIP and extracts it directly into the web
  directory.
- A downloadable ZIP must never contain another ZIP.
- Payload files live at the archive root; no extra wrapper directory.
- GitHub Actions artifacts must upload the extracted payload directory. GitHub
  creates the one customer-facing ZIP around those files.
- Hidden protection files such as `.htaccess` must be included.

## Installer included

Every complete upload, licensed bundle, testing bundle, and repair upload must
contain:

- `install.php`
- `install/index.php`
- `core/Install/Installer.php`
- `core/Install/views/installer.php`

Installed sites remain safe because valid configuration plus `installed.lock`
keeps installation disabled. Packaging must not remove the installer merely
because an archive is intended for repair or upgrade recovery.

## Enforcement

CI must fail when a staged downloadable artifact:

- contains a nested `*.zip` file;
- omits any required installer entry;
- adds a wrapper directory;
- omits hidden server-protection files; or
- contains runtime configuration, credentials, secrets, or customer data.

Do not override these rules in future sessions without an explicit owner
decision recorded in this file, `AGENTS.md`, and `SESSION_HANDOFF.md`.
