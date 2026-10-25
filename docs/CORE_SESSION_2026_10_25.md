# AllWebPro Core continuation — 2026-10-25

The owner requested that Core development continue in a new chat using the
decisions from “Identify AllWebPro Core” and current GitHub state. Relevant
decisions were recovered and checked against this repository; this is a
development handoff, not a verbatim transcript of the old conversation.

## Verified starting state

- Repository: `afterburn25/Website_Core`.
- Implementation branch: `devin/website-core`; `main` is the old scaffold.
- Starting head: `3329b0fda756c95e983ed36c8ef868ef0a30c7b2`.
- Core version: 2.2.0; CI run 533 (`40211464011`) passed all six jobs.
- 2.1 delivered dormant module-activated commerce and customer accounts.
- 2.2 delivered business profile, reusable module administration, and opt-in
  inquiries/quotations. Packaged-host and two-industry adoption remain open.
- Latest recorded task: CORE-OPS-1, actionable SMTP provider-host discovery
  after the owner reported port 465 answering without a completed handshake.

## Rules carried forward

- Build shared, industry-neutral behavior once in Core. Features unsupported
  by the active module must remain absent and unreachable.
- Manual payments support Venmo, PayPal, and Cash App; no cash option.
  Receipt must be verified by staff; choosing a method is not payment proof.
- Website Identity shows the effective Base URL as a grey, read-only field.
- Catering development remains separate in `Core_Mods:module/catering`.
  Combine its latest module with Core only when the owner explicitly asks;
  record genuine missing shared services on the Core roadmap.
- Each downloadable complete/bundle/repair upload is one directly usable ZIP
  with the browser installer and hidden `.htaccess`; no nested ZIP or wrapper.
- Preserve existing customer content, inquiry history, IDs and customizations.
- Do not merge PR #7 or publish a release without the recorded owner approval.

## This continuation

2.2.1 implements the Core-only SMTP diagnostic candidate. It performs
credential-free, certificate-verified host/port checks and forward-confirmed
provider discovery. It does not synchronize or edit any industry snapshot.
See `2.2.1_RELEASE_NOTES.md` for behavior, tests and timeout limitations.
The continuation workspace now has a temporary PHP 8.4.13 CLI runtime, so local
checks are possible here; old handoff claims about PHP being unavailable do
not describe this workspace. Native MySQL/MariaDB acceptance remains in CI.

## Next work

1. Verify the new candidate through the complete CI matrix and packaged-host
   workflow; record the actual CI evidence without claiming live mail success.
2. Continue remaining CORE-REUSE items in `ROADMAP.md`, starting with module
   content/media integration and record-editor ordering/bulk actions.
3. Keep packaged browser acceptance and cross-industry adoption visibly open.
