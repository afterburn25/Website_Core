<?php
/** @var array $checks @var array $errors @var array $defaults @var bool $needsToken @var bool $unclaimed @var array $tzList @var string $packageName @var string $logoUrl */
$err = fn (string $key): string => isset($errors[$key])
    ? '<p class="field-error">' . e((string) $errors[$key]) . '</p>'
    : '';
$cls = fn (string $key): string => isset($errors[$key]) ? ' is-invalid' : '';
$val = fn (string $key): string => e_attr((string) ($defaults[$key] ?? ''));
$requiredFailures = array_values(array_filter($checks, fn (array $check): bool => $check['required'] && !$check['ok']));
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="dark">
<title><?= e($packageName) ?> Setup — CajunWebPro</title>
<style>
:root{
    --ink:#06101e;--navy:#09182a;--navy-2:#0d223a;--panel:#102842;--line:#294665;
    --text:#f3f7fc;--muted:#9fb2c8;--gold:#d7aa50;--gold-2:#f0d28f;
    --blue:#3497ff;--green:#3dd598;--red:#ff7a86;--amber:#ffcb70;
    --shadow:0 30px 90px rgba(0,0,0,.42);--radius:22px;
}
*{box-sizing:border-box}
html{scroll-behavior:smooth}
body{margin:0;min-height:100vh;color:var(--text);font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;
    background:
        radial-gradient(circle at 8% 12%,rgba(52,151,255,.18),transparent 29rem),
        radial-gradient(circle at 92% 4%,rgba(215,170,80,.13),transparent 26rem),
        linear-gradient(145deg,#050b14 0%,#081527 48%,#07101e 100%);}
body:before{content:"";position:fixed;inset:0;pointer-events:none;opacity:.18;background-image:linear-gradient(rgba(255,255,255,.035) 1px,transparent 1px),linear-gradient(90deg,rgba(255,255,255,.035) 1px,transparent 1px);background-size:36px 36px;mask-image:linear-gradient(to bottom,#000,transparent 72%)}
a{color:var(--gold-2)}
.skip-link{position:fixed;left:16px;top:-60px;z-index:20;padding:10px 14px;background:#fff;color:#07111f;border-radius:8px}.skip-link:focus{top:16px}
.installer-shell{width:min(1400px,calc(100% - 32px));margin:28px auto;display:grid;grid-template-columns:minmax(280px,.72fr) minmax(0,1.5fr);border:1px solid rgba(109,145,180,.32);border-radius:30px;overflow:hidden;background:rgba(8,20,35,.78);box-shadow:var(--shadow);backdrop-filter:blur(18px)}
.brand-panel{position:relative;padding:38px;min-width:0;background:linear-gradient(165deg,rgba(17,47,76,.97),rgba(7,18,32,.98));border-right:1px solid var(--line)}
.brand-panel:after{content:"";position:absolute;inset:auto -70px -110px auto;width:280px;height:280px;border-radius:50%;border:52px solid rgba(215,170,80,.06)}
.brand-sticky{position:sticky;top:32px;z-index:1}
.logo-card{display:flex;align-items:center;justify-content:flex-start;width:fit-content;max-width:100%;min-height:96px;padding:0;background:transparent;text-decoration:none}
.brand-logo{display:block;width:min(100%,390px);height:auto;max-height:100px;object-fit:contain;object-position:left center;filter:drop-shadow(0 12px 24px rgba(0,0,0,.28))}
.eyebrow{margin:28px 0 8px;color:var(--gold-2);font-size:.75rem;font-weight:850;letter-spacing:.14em;text-transform:uppercase}
.brand-panel h1{margin:0;font:700 clamp(2.25rem,4vw,4.4rem)/.98 Georgia,"Times New Roman",serif;letter-spacing:-.045em}
.brand-copy{max-width:32rem;margin:20px 0 30px;color:#bdd0e3;line-height:1.75}
.steps{display:grid;gap:12px;margin:0;padding:0;list-style:none}
.steps li{display:grid;grid-template-columns:34px minmax(0,1fr);gap:12px;align-items:center;padding:12px 14px;border:1px solid rgba(103,139,174,.27);border-radius:13px;background:rgba(5,15,27,.32);color:#c7d7e7;transition:border-color .2s,background .2s,color .2s,transform .2s}
.step-number{display:grid;place-items:center;width:34px;height:34px;border:1px solid rgba(215,170,80,.52);border-radius:50%;color:var(--gold-2);font-size:.8rem;font-weight:850;transition:background .2s,border-color .2s,color .2s}.step-check{display:none;font-size:1rem}.step-copy strong{display:block;font-size:.88rem}.step-copy small{display:block;margin-top:3px;color:#7f96ad;font-size:.7rem;font-weight:700;letter-spacing:.04em}.steps li.is-current{border-color:rgba(215,170,80,.72);background:rgba(114,77,20,.22);transform:translateX(3px)}.steps li.is-ready{border-color:rgba(61,213,152,.58);background:linear-gradient(135deg,rgba(31,125,91,.27),rgba(7,44,39,.34));color:#dffff1}.steps li.is-ready .step-number{border-color:var(--green);background:var(--green);color:#062519;box-shadow:0 0 0 5px rgba(61,213,152,.1)}.steps li.is-ready .step-number-value{display:none}.steps li.is-ready .step-check{display:block}.steps li.is-ready .step-copy small{color:#75ddb3}
.secure-note{display:flex;gap:10px;align-items:flex-start;margin-top:24px;padding-top:22px;border-top:1px solid rgba(116,148,178,.25);color:#95abc0;font-size:.86rem;line-height:1.55}
.secure-note svg{flex:0 0 18px;color:var(--green);margin-top:2px}
.workspace{min-width:0;padding:40px clamp(22px,4vw,58px) 52px}
.workspace-head{display:flex;justify-content:space-between;gap:24px;align-items:flex-start;margin-bottom:28px}
.workspace-head h2{margin:4px 0 8px;font-size:clamp(1.8rem,3vw,3rem);letter-spacing:-.035em}
.workspace-head p{margin:0;color:var(--muted);line-height:1.65}
.version-badge{flex:0 0 auto;padding:8px 11px;border:1px solid var(--line);border-radius:999px;color:#b8cade;background:#0b1c30;font-size:.75rem;font-weight:750}
.alert{margin:0 0 22px;padding:16px 18px;border-radius:14px;line-height:1.6;overflow-wrap:anywhere}
.alert strong{color:#fff}.alert-warning{border:1px solid #775b27;background:#352b18;color:#f7dfaa}.alert-error{border:1px solid #86414a;background:#351c25;color:#ffd1d6}
.status-card,.form-section{min-width:0;margin-bottom:20px;border:1px solid rgba(88,124,158,.36);border-radius:var(--radius);background:linear-gradient(145deg,rgba(17,42,68,.92),rgba(9,25,43,.92));box-shadow:0 18px 46px rgba(0,0,0,.18)}
.section-head{display:flex;align-items:center;justify-content:space-between;gap:16px;padding:20px 22px;border-bottom:1px solid rgba(82,118,151,.3)}
.section-title{display:flex;align-items:center;gap:12px;min-width:0}.section-index{display:grid;place-items:center;width:34px;height:34px;border-radius:10px;background:linear-gradient(145deg,#d8ac56,#9c6d24);color:#08111d;font-weight:900;font-size:.82rem}
.section-title h3{margin:0;font-size:1rem;letter-spacing:.015em}.section-note{color:var(--muted);font-size:.79rem;text-align:right}
.checks{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:10px;padding:18px;margin:0;list-style:none}
.check{min-width:0;display:grid;grid-template-columns:28px minmax(0,1fr);gap:11px;padding:13px;border:1px solid rgba(86,121,154,.26);border-radius:12px;background:rgba(3,13,24,.25)}
.check-icon{display:grid;place-items:center;width:28px;height:28px;border-radius:9px;font-weight:900}.check.ok .check-icon{background:rgba(61,213,152,.14);color:var(--green)}.check.bad .check-icon{background:rgba(255,122,134,.13);color:var(--red)}.check.warn .check-icon{background:rgba(255,203,112,.12);color:var(--amber)}
.check-copy{min-width:0}.check-name{display:block;margin:1px 0 7px;color:#e8f0f8;font-size:.86rem;font-weight:800;line-height:1.25}.check-detail{display:block;min-width:0;padding-top:7px;border-top:1px solid rgba(116,148,178,.18);color:#91a7bd;font-size:.75rem;line-height:1.5;overflow-wrap:anywhere;word-break:break-word}.check-detail.is-path{font-family:ui-monospace,SFMono-Regular,Consolas,monospace;color:#acc6df}
.section-body{padding:22px}
.field-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:18px}.field-grid.three{grid-template-columns:1.2fr .65fr .8fr}.field.full{grid-column:1/-1}.field{min-width:0}
label{display:flex;justify-content:space-between;gap:10px;margin:0 0 8px;color:#dce7f2;font-size:.83rem;font-weight:750}.optional{color:#7089a2;font-weight:550}
input,select{width:100%;min-width:0;height:48px;padding:0 13px;border:1px solid #355573;border-radius:11px;background:#071729;color:#eef6ff;font:inherit;outline:none;transition:border-color .18s,box-shadow .18s,background .18s}
input:hover,select:hover{border-color:#4a7195}input:focus,select:focus{border-color:var(--blue);box-shadow:0 0 0 4px rgba(52,151,255,.13);background:#091c31}input.is-invalid,select.is-invalid{border-color:var(--red)}
input::placeholder{color:#5f7892}.hint{margin:7px 0 0;color:#7990a8;font-size:.75rem;line-height:1.5;overflow-wrap:anywhere}.field-error{margin:7px 0 0;color:#ffadb5;font-size:.78rem;line-height:1.45}
.db-actions{display:flex;align-items:center;gap:13px;flex-wrap:wrap;margin-top:18px;padding-top:18px;border-top:1px solid rgba(80,116,149,.27)}
button{font:inherit}.test-button{display:inline-flex;align-items:center;gap:9px;min-height:44px;padding:0 16px;border:1px solid #52789c;border-radius:11px;background:#132f4d;color:#edf6ff;font-weight:800;cursor:pointer;transition:transform .18s,background .18s,border-color .18s}.test-button:hover{transform:translateY(-1px);background:#193d62;border-color:#6b95bc}.test-button:disabled{opacity:.62;cursor:wait;transform:none}
.db-status{display:inline-flex;align-items:center;gap:8px;min-width:0;color:#91a8bd;font-size:.82rem;line-height:1.45}.db-status:before{content:"";flex:0 0 auto;width:8px;height:8px;border-radius:50%;background:#667f97;box-shadow:0 0 0 4px rgba(102,127,151,.12)}.db-status.success{color:#8ce8bd}.db-status.success:before{background:var(--green);box-shadow:0 0 0 4px rgba(61,213,152,.12)}.db-status.error{color:#ffadb5}.db-status.error:before{background:var(--red);box-shadow:0 0 0 4px rgba(255,122,134,.12)}
.details{margin-top:18px;border:1px solid rgba(78,113,146,.28);border-radius:12px;background:rgba(4,14,25,.24)}.details summary{padding:13px 15px;cursor:pointer;color:#aec1d4;font-size:.82rem;font-weight:750}.details p{margin:0;padding:0 15px 15px;color:#8299b0;font-size:.8rem;line-height:1.65}
.install-bar{position:sticky;bottom:16px;z-index:4;display:flex;align-items:center;justify-content:space-between;gap:18px;margin-top:24px;padding:14px 14px 14px 18px;border:1px solid rgba(215,170,80,.42);border-radius:17px;background:rgba(8,23,39,.94);box-shadow:0 20px 50px rgba(0,0,0,.44);backdrop-filter:blur(15px)}
.install-copy strong{display:block;font-size:.87rem}.install-copy span{display:block;margin-top:3px;color:#859bb0;font-size:.73rem}.install-button{display:inline-flex;align-items:center;justify-content:center;gap:10px;min-height:50px;padding:0 24px;border:0;border-radius:12px;background:linear-gradient(135deg,#f0d28f,#c99435);color:#07111e;font-weight:900;cursor:pointer;box-shadow:0 10px 28px rgba(215,170,80,.24);transition:transform .18s,filter .18s}.install-button:hover{transform:translateY(-1px);filter:brightness(1.06)}.install-button:disabled{cursor:not-allowed;opacity:.45;filter:saturate(.55);transform:none;box-shadow:none}
.spinner{display:none;width:16px;height:16px;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;border-radius:50%;animation:spin .7s linear infinite}.is-loading .spinner{display:inline-block}.is-loading .test-icon{display:none}@keyframes spin{to{transform:rotate(360deg)}}
@media(max-width:980px){.installer-shell{grid-template-columns:1fr;margin:16px auto}.brand-panel{border-right:0;border-bottom:1px solid var(--line)}.brand-sticky{position:static}.brand-panel h1{font-size:clamp(2.2rem,8vw,4rem)}.steps{grid-template-columns:repeat(3,minmax(0,1fr))}.steps li{grid-template-columns:30px minmax(0,1fr);padding:10px}.step-number{width:30px;height:30px}.secure-note{display:none}}
@media(max-width:680px){.installer-shell{width:100%;margin:0;border:0;border-radius:0}.brand-panel,.workspace{padding:25px 18px}.workspace-head{display:block}.version-badge{display:inline-block;margin-top:14px}.checks,.field-grid,.field-grid.three,.steps{grid-template-columns:1fr}.section-head{align-items:flex-start}.section-note{max-width:42%;overflow-wrap:anywhere}.section-body{padding:18px}.install-bar{align-items:stretch;flex-direction:column;bottom:8px}.install-button{width:100%}}
@media(prefers-reduced-motion:reduce){*{scroll-behavior:auto!important;animation-duration:.001ms!important;transition-duration:.001ms!important}}
</style>
</head>
<body>
<a class="skip-link" href="#setup-form">Skip to setup form</a>
<main class="installer-shell">
    <aside class="brand-panel" aria-label="Installer overview">
        <div class="brand-sticky">
            <a class="logo-card" href="https://cajunwebpro.com" target="_blank" rel="noopener noreferrer" aria-label="Visit CajunWebPro">
                <img class="brand-logo" src="<?= e_attr($logoUrl) ?>" alt="CajunWebPro — Web Design &amp; Development">
            </a>
            <p class="eyebrow">Secure deployment system</p>
            <h1><?= e($packageName) ?></h1>
            <p class="brand-copy">A guided setup for your new website. Verify the server, connect the database, create the owner account, and launch securely.</p>
            <ol class="steps">
                <li id="overview-step-1" class="<?= $requiredFailures === [] ? 'is-ready' : 'is-current' ?>"><span class="step-number"><span class="step-number-value">01</span><span class="step-check" aria-hidden="true">✓</span></span><span class="step-copy"><strong>Server readiness</strong><small><?= $requiredFailures === [] ? 'Ready' : 'Needs attention' ?></small></span></li>
                <li id="overview-step-2"><span class="step-number"><span class="step-number-value">02</span><span class="step-check" aria-hidden="true">✓</span></span><span class="step-copy"><strong>Site and database</strong><small>Complete details</small></span></li>
                <li id="overview-step-3"><span class="step-number"><span class="step-number-value">03</span><span class="step-check" aria-hidden="true">✓</span></span><span class="step-copy"><strong>Owner and launch</strong><small>Complete account</small></span></li>
            </ol>
            <div class="secure-note">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2 4.5 5.2v5.7c0 5.1 3.2 9.8 7.5 11.1 4.3-1.3 7.5-6 7.5-11.1V5.2L12 2Zm3.5 8.1-4.2 4.4a1 1 0 0 1-1.5 0l-2-2.1 1.5-1.4 1.3 1.4 3.5-3.7 1.4 1.4Z"/></svg>
                <span>Credentials are sent only to this installer over the required SSL connection and are never written to logs.</span>
            </div>
        </div>
    </aside>

    <section class="workspace">
        <header class="workspace-head">
            <div><p class="eyebrow">CajunWebPro installer</p><h2>Let’s launch your site</h2><p>Complete the fields below. Setup normally takes less than two minutes.</p></div>
            <span class="version-badge">Core <?= e(defined('WC_VERSION') ? WC_VERSION : 'development') ?></span>
        </header>

        <?php if ($unclaimed): ?>
        <div class="alert alert-warning"><strong>Secure this setup window.</strong> The first browser to open this installer owns the setup session. Keep this page open until installation finishes.</div>
        <?php endif; ?>

        <?php if ($errors !== []): ?>
        <div class="alert alert-error" id="setup-errors" role="alert"><strong>Setup needs attention.</strong> Correct the highlighted fields and any failed required server checks, then try again.</div>
        <?php endif; ?>

        <section class="status-card" aria-labelledby="requirements-title">
            <div class="section-head"><div class="section-title"><span class="section-index">✓</span><h3 id="requirements-title">Server readiness</h3></div><span class="section-note"><?= $requiredFailures === [] ? 'Required checks passed' : count($requiredFailures) . ' required check(s) failed' ?></span></div>
            <ul class="checks">
                <?php foreach ($checks as $check): ?>
                <?php $state = $check['ok'] ? 'ok' : ($check['required'] ? 'bad' : 'warn'); ?>
                <li class="check <?= $state ?>">
                    <span class="check-icon" aria-hidden="true"><?= $check['ok'] ? '✓' : ($check['required'] ? '×' : '!') ?></span>
                    <?php $detailIsPath = str_starts_with((string) $check['detail'], '/'); ?>
                    <div class="check-copy">
                        <div class="check-name"><?= e($check['name']) ?></div>
                        <div class="check-detail<?= $detailIsPath ? ' is-path' : '' ?>"><?= e($check['detail']) ?></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </section>

        <form id="setup-form" method="post" action="install.php">
            <section class="form-section" aria-labelledby="site-title">
                <div class="section-head"><div class="section-title"><span class="section-index">01</span><h3 id="site-title">Website details</h3></div><span class="section-note">Public identity and location</span></div>
                <div class="section-body field-grid">
                    <div class="field full"><label for="site_name">Site name</label><input id="site_name" name="site_name" class="<?= e(trim($cls('site_name'))) ?>" value="<?= $val('site_name') ?>" placeholder="Your catering company" autocomplete="organization" required><?= $err('site_name') ?></div>
                    <div class="field full"><label for="base_url">Secure website URL</label><input id="base_url" name="base_url" type="url" pattern="https://.*" class="<?= e(trim($cls('base_url'))) ?>" value="<?= $val('base_url') ?>" placeholder="https://example.com" inputmode="url" required><?= $err('base_url') ?><p class="hint">SSL is required. The address must begin with <strong>https://</strong>.</p></div>
                    <div class="field"><label for="timezone">Time zone</label><select id="timezone" name="timezone"><?php foreach ($tzList as $timezone): ?><option value="<?= e_attr($timezone) ?>" <?= ($defaults['timezone'] ?? 'UTC') === $timezone ? 'selected' : '' ?>><?= e($timezone) ?></option><?php endforeach; ?></select></div>
                    <div class="field"><label for="storage_path">Private storage path</label><input id="storage_path" name="storage_path" class="<?= e(trim($cls('storage_path'))) ?>" value="<?= $val('storage_path') ?>" required><?= $err('storage_path') ?><p class="hint">The installer will create or repair this directory without using unsafe 0777 permissions.</p></div>
                </div>
            </section>

            <section class="form-section" aria-labelledby="database-title">
                <div class="section-head"><div class="section-title"><span class="section-index">02</span><h3 id="database-title">Database connection</h3></div><span class="section-note">MySQL or MariaDB</span></div>
                <div class="section-body">
                    <div class="field-grid three">
                        <div class="field"><label for="db_host">Database host</label><input id="db_host" name="db_host" data-db-field value="<?= $val('db_host') ?>" placeholder="localhost" required></div>
                        <div class="field"><label for="db_port">Port</label><input id="db_port" name="db_port" data-db-field type="number" min="1" max="65535" value="<?= $val('db_port') ?>" required></div>
                        <div class="field"><label for="db_prefix">Table prefix</label><input id="db_prefix" name="db_prefix" data-db-field value="<?= $val('db_prefix') ?>" maxlength="16" pattern="[A-Za-z0-9_]*"><?= $err('db_prefix') ?></div>
                        <div class="field full"><label for="db_name">Database name</label><input id="db_name" name="db_name" data-db-field class="<?= e(trim($cls('db_name'))) ?>" value="<?= $val('db_name') ?>" autocomplete="off" required><?= $err('db_name') ?></div>
                        <div class="field"><label for="db_user">Database username</label><input id="db_user" name="db_user" data-db-field value="<?= $val('db_user') ?>" autocomplete="username" required></div>
                        <div class="field"><label for="db_pass">Database password</label><input id="db_pass" name="db_pass" data-db-field type="password" autocomplete="current-password"></div>
                    </div>
                    <div class="db-actions">
                        <button class="test-button" id="db-test-button" type="button"><span class="test-icon" aria-hidden="true">↗</span><span class="spinner" aria-hidden="true"></span><span>Test connection</span></button>
                        <span class="db-status" id="db-test-status" role="status" aria-live="polite">Not tested yet</span>
                    </div>
                </div>
            </section>

            <section class="form-section" aria-labelledby="owner-title">
                <div class="section-head"><div class="section-title"><span class="section-index">03</span><h3 id="owner-title">Owner account</h3></div><span class="section-note">Primary administrator</span></div>
                <div class="section-body field-grid">
                    <div class="field"><label for="owner_username">Username</label><input id="owner_username" name="owner_username" class="<?= e(trim($cls('owner_username'))) ?>" value="<?= $val('owner_username') ?>" autocomplete="username" minlength="3" maxlength="60" required><?= $err('owner_username') ?></div>
                    <div class="field"><label for="owner_email">Email address</label><input id="owner_email" name="owner_email" type="email" class="<?= e(trim($cls('owner_email'))) ?>" value="<?= $val('owner_email') ?>" autocomplete="email" required><?= $err('owner_email') ?></div>
                    <div class="field"><label for="owner_password">Password</label><input id="owner_password" name="owner_password" type="password" class="<?= e(trim($cls('owner_password'))) ?>" autocomplete="new-password" minlength="12" required><?= $err('owner_password') ?><p class="hint">Use at least 12 characters.</p></div>
                    <div class="field"><label for="owner_password2">Confirm password</label><input id="owner_password2" name="owner_password2" type="password" class="<?= e(trim($cls('owner_password2'))) ?>" autocomplete="new-password" minlength="12" required><?= $err('owner_password2') ?></div>
                    <?php if ($needsToken): ?><div class="field full"><label for="setup_token">Setup security token</label><input id="setup_token" name="setup_token" class="<?= e(trim($cls('setup_token'))) ?>" autocomplete="off" required><?= $err('setup_token') ?></div><?php endif; ?>
                    <div class="field full"><details class="details"><summary>What happens when I install?</summary><p>The installer creates the database schema and owner account, enables the bundled industry package, writes the private configuration, locks setup, then securely removes both the installer folder and install.php. If cleanup cannot finish, the site stays locked and shows manual removal instructions.</p></details></div>
                </div>
            </section>

            <div class="install-bar">
                <div class="install-copy"><strong>Ready for secure installation</strong><span>Database connectivity is verified again during setup.</span></div>
                <button class="install-button" id="install-button" type="submit"><span>Install website</span><span aria-hidden="true">→</span></button>
            </div>
        </form>
    </section>
</main>
<script>
(() => {
    const form = document.getElementById('setup-form');
    const button = document.getElementById('db-test-button');
    const status = document.getElementById('db-test-status');
    const fields = [...document.querySelectorAll('[data-db-field]')];
    const installButton = document.getElementById('install-button');
    const installTitle = document.querySelector('.install-copy strong');
    const installNote = document.querySelector('.install-copy span');
    const steps = [1, 2, 3].map(number => document.getElementById('overview-step-' + number));
    const serverReady = <?= $requiredFailures === [] ? 'true' : 'false' ?>;
    let controller = null;
    let databaseVerified = false;

    const field = id => document.getElementById(id);
    const hasValue = id => Boolean(field(id)?.value.trim());
    const siteAndDatabaseReady = () => {
        const prefix = field('db_prefix')?.value || '';
        return hasValue('site_name')
            && /^https:\/\//i.test(field('base_url')?.value || '')
            && hasValue('timezone')
            && hasValue('storage_path')
            && hasValue('db_host')
            && Number(field('db_port')?.value) >= 1
            && Number(field('db_port')?.value) <= 65535
            && hasValue('db_name')
            && hasValue('db_user')
            && /^[A-Za-z0-9_]{0,16}$/.test(prefix)
            && databaseVerified;
    };
    const ownerReady = () => {
        const username = field('owner_username')?.value || '';
        const email = field('owner_email')?.value || '';
        const password = field('owner_password')?.value || '';
        const confirmation = field('owner_password2')?.value || '';
        const token = field('setup_token');
        return /^[A-Za-z0-9_.-]{3,60}$/.test(username)
            && /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
            && password.length >= 12
            && password === confirmation
            && (!token || token.value.trim() !== '');
    };
    const markStep = (element, ready, current, waitingText) => {
        if (!element) return;
        element.classList.toggle('is-ready', ready);
        element.classList.toggle('is-current', !ready && current);
        const note = element.querySelector('small');
        if (note) note.textContent = ready ? 'Ready' : waitingText;
    };
    const refreshProgress = () => {
        const secondReady = siteAndDatabaseReady();
        const thirdReady = ownerReady();
        markStep(steps[0], serverReady, !serverReady, 'Needs attention');
        markStep(steps[1], secondReady, serverReady && !secondReady, databaseVerified ? 'Complete details' : 'Test database');
        markStep(steps[2], thirdReady, serverReady && secondReady && !thirdReady, 'Complete account');
        const allReady = serverReady && secondReady && thirdReady;
        installButton.disabled = !allReady;
        installTitle.textContent = allReady ? 'Everything is ready' : 'Finish the highlighted setup step';
        installNote.textContent = allReady
            ? 'All checks and required fields passed. You can install securely.'
            : 'Completed steps turn green automatically.';
    };

    const setStatus = (state, message) => {
        status.className = 'db-status' + (state ? ' ' + state : '');
        status.textContent = message;
    };

    fields.forEach(input => input.addEventListener('input', () => {
        databaseVerified = false;
        setStatus('', 'Connection details changed — test again');
        refreshProgress();
    }));
    [...form.querySelectorAll('input,select')].forEach(input => input.addEventListener('input', refreshProgress));
    [...form.querySelectorAll('select')].forEach(input => input.addEventListener('change', refreshProgress));
    button.addEventListener('click', async () => {
        const required = ['db_host', 'db_port', 'db_name', 'db_user'];
        for (const id of required) {
            const field = document.getElementById(id);
            if (!field.value.trim()) {
                field.focus();
                setStatus('error', 'Complete all required database fields first.');
                return;
            }
        }

        if (controller) controller.abort();
        controller = new AbortController();
        const payload = new URLSearchParams({action: 'test_database'});
        fields.forEach(field => payload.set(field.name, field.value));
        button.disabled = true;
        button.classList.add('is-loading');
        setStatus('', 'Testing secure database connection…');
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8', 'X-Requested-With': 'XMLHttpRequest'},
                body: payload.toString(),
                credentials: 'same-origin',
                signal: controller.signal
            });
            const result = await response.json();
            databaseVerified = Boolean(result.ok);
            setStatus(result.ok ? 'success' : 'error', result.message || (result.ok ? 'Connection successful.' : 'Connection failed.'));
        } catch (error) {
            databaseVerified = false;
            if (error.name !== 'AbortError') setStatus('error', 'The test could not complete. Check SSL and try again.');
        } finally {
            button.disabled = false;
            button.classList.remove('is-loading');
            refreshProgress();
        }
    });

    refreshProgress();

    const errors = document.getElementById('setup-errors');
    if (errors) requestAnimationFrame(() => errors.scrollIntoView({behavior: 'smooth', block: 'center'}));
})();
</script>
</body>
</html>
