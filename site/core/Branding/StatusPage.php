<?php
declare(strict_types=1);

namespace Core\Branding;

/**
 * Branded, dependency-free public status and error pages. The complete style
 * and artwork are inline so the page survives maintenance, theme and asset
 * failures; the optional logo is the only progressive enhancement.
 */
final class StatusPage
{
    public static function render(
        int $code,
        string $title,
        string $message,
        string $basePath = '',
        string $siteName = 'Website',
        ?string $reference = null,
        string $actionLabel = 'Return home',
        string $actionUrl = '/',
        bool $adminEntry = true,
    ): string {
        $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $routeRoot = rtrim($basePath, '/');
        $assetRoot = str_contains($routeRoot, '/index.php?p=')
            ? (string) strstr($routeRoot, '/index.php?p=', true) : $routeRoot;
        $href = $actionUrl === '' ? '' : ($actionUrl[0] === '/' ? $routeRoot . $actionUrl : $actionUrl);
        $admin = $routeRoot . '/admin/login';
        $logo = rtrim($assetRoot, '/') . '/assets/images/cajunwebpro-logo.png';
        $label = match ($code) {
            403 => 'Private area', 404 => 'Lost in the bayou', 500 => 'A snag in the works',
            503 => 'A quick tune-up', default => 'Website notice',
        };
        $referenceHtml = $reference === null ? ''
            : '<p class="reference">Support reference <code>' . $e($reference) . '</code></p>';
        $adminHtml = !$adminEntry ? '' : '<a class="admin-entry" href="' . $e($admin)
            . '" aria-label="Administrator sign in" title="Administrator sign in">'
            . '<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M7 10V8a5 5 0 0 1 10 0v2m-8 0V8a3 3 0 0 1 6 0v2m-9 0h12v10H6z"/></svg></a>';
        return '<!doctype html><html lang="en"><head><meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><title>' . $e($title) . ' — ' . $e($siteName) . '</title>'
            . '<style>' . self::css() . '</style></head><body><main class="status-shell">'
            . '<section class="status-copy"><span class="eyebrow">' . $e((string) $code) . ' · ' . $e($label) . '</span>'
            . '<h1>' . $e($title) . '</h1><p class="message">' . $e($message) . '</p>' . $referenceHtml
            . '<div class="actions"><a class="primary" href="' . $e($href) . '">' . $e($actionLabel) . '</a>'
            . '<a class="quiet" href="https://cajunwebpro.com" target="_blank" rel="noopener noreferrer">Website support</a></div>'
            . '<p class="site-name">' . $e($siteName) . '</p></section>'
            . '<section class="status-art" aria-hidden="true">' . self::art($code) . '</section>'
            . '</main><footer class="status-attribution" data-cajunwebpro-attribution="required">'
            . '<a class="cwp" href="https://cajunwebpro.com" target="_blank" rel="noopener noreferrer" aria-label="Cajun Web Pro"><img src="' . $e($logo) . '" alt="Cajun Web Pro"></a>'
            . '<p>Website Design &amp; Development by <a href="https://cajunwebpro.com" rel="noopener">Cajun Web Pro LLC</a><br>Want a website like this for your business? <a href="https://cajunwebpro.com" rel="noopener">Let’s build yours.</a></p>'
            . '</footer>' . $adminHtml . '</body></html>';
    }

    private static function art(int $code): string
    {
        $sun = $code === 503 ? '#f59e0b' : ($code === 404 ? '#38bdf8' : '#fb7185');
        return '<svg viewBox="0 0 680 680" role="img"><defs><linearGradient id="sky" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#12385a"/><stop offset="1" stop-color="#07131f"/></linearGradient><filter id="glow"><feGaussianBlur stdDeviation="20"/></filter></defs>'
            . '<circle cx="340" cy="340" r="286" fill="url(#sky)" stroke="#ffffff18"/>'
            . '<circle cx="420" cy="205" r="82" fill="' . $sun . '" opacity=".2" filter="url(#glow)"/><circle cx="420" cy="205" r="55" fill="' . $sun . '"/>'
            . '<path d="M62 478c95-55 146-62 218-23 93 51 174 8 338-49v184H62z" fill="#092b31"/>'
            . '<path d="M62 524c105-42 190-28 282 7 93 36 174 15 274-18v77H62z" fill="#0d4550"/>'
            . '<path d="M194 454c18-58 29-116 32-175m-2 95c-42-37-66-54-94-60m96 21c39-43 69-62 103-69" stroke="#132f26" stroke-width="15" stroke-linecap="round"/>'
            . '<path d="M117 560c82-23 121-21 183 0m78-14c65-18 112-13 171 8" stroke="#5eead455" stroke-width="5" stroke-linecap="round"/>'
            . '<g fill="none" stroke="#d9f99d" stroke-width="6" stroke-linecap="round"><path d="M462 391c38-27 82-5 77 34-4 31-42 36-62 18-18-16-8-38 12-41 17-2 25 14 16 24"/><path d="M536 395l34-17m-32 48 38 12"/></g>'
            . '<text x="340" y="625" text-anchor="middle" fill="#ffffff90" font-size="24" font-family="system-ui,sans-serif">' . $code . '</text></svg>';
    }

    private static function css(): string
    {
        return ':root{color-scheme:dark;--ink:#edf7f5;--muted:#a9bcc4;--line:#ffffff1a;--aqua:#5eead4;--deep:#06111c}*{box-sizing:border-box}body{margin:0;min-height:100vh;background:radial-gradient(circle at 78% 14%,#164e63 0,transparent 32%),linear-gradient(145deg,#07111d,#0a1f2b 60%,#08242a);color:var(--ink);font-family:Inter,ui-sans-serif,system-ui,-apple-system,sans-serif;overflow-x:hidden}.status-shell{width:min(1180px,100%);min-height:calc(100vh - 92px);margin:auto;padding:clamp(2rem,6vw,6rem);display:grid;grid-template-columns:minmax(0,1fr) minmax(300px,.9fr);gap:clamp(2rem,5vw,5rem);align-items:center}.status-copy{position:relative;z-index:2}.eyebrow{display:inline-flex;padding:.42rem .72rem;border:1px solid #5eead44d;border-radius:999px;color:#99f6e4;background:#0f766e24;font-size:.76rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase}h1{font-family:Georgia,serif;font-size:clamp(3rem,8vw,6.6rem);line-height:.92;letter-spacing:-.055em;margin:1.25rem 0 1.4rem;max-width:780px;text-wrap:balance}.message{max-width:650px;color:var(--muted);font-size:clamp(1.05rem,2vw,1.28rem);line-height:1.72}.reference{color:#8ca4ae;font-size:.9rem}code{color:#a7f3d0;background:#02070b80;border:1px solid var(--line);padding:.28rem .5rem;border-radius:.45rem}.actions{display:flex;gap:.85rem;align-items:center;flex-wrap:wrap;margin-top:2rem}.actions a{text-decoration:none;font-weight:800}.primary{padding:.86rem 1.1rem;background:var(--aqua);color:#042f2e;border-radius:.7rem;box-shadow:0 12px 30px #2dd4bf2b}.quiet{color:#d7e6e9;padding:.7rem}.site-name{margin-top:3.5rem;color:#ffffff65;font-size:.78rem;text-transform:uppercase;letter-spacing:.18em}.status-art{position:relative}.status-art:before{content:"";position:absolute;inset:12%;background:#22d3ee24;filter:blur(55px);border-radius:50%}.status-art svg{position:relative;width:100%;height:auto;filter:drop-shadow(0 38px 60px #0009);animation:float 8s ease-in-out infinite}.status-attribution{min-height:92px;padding:1rem 5rem 1rem 1.2rem;border-top:1px solid var(--line);display:flex;align-items:center;justify-content:center;gap:1.2rem;color:#8299a2;text-align:left;font-size:.72rem;line-height:1.55}.status-attribution p{margin:0}.status-attribution a{color:#9ccbd0}.cwp{opacity:.6;transition:.2s}.cwp:hover,.cwp:focus{opacity:1}.cwp img{display:block;width:106px;height:auto}.admin-entry{position:fixed;right:1.15rem;bottom:1.05rem;width:34px;height:34px;display:grid;place-items:center;border-radius:50%;color:#b9cbd1;opacity:.32;border:1px solid var(--line);transition:.2s}.admin-entry:hover,.admin-entry:focus{opacity:1;background:#ffffff0d}.admin-entry svg{width:16px;fill:none;stroke:currentColor;stroke-width:1.6}@keyframes float{50%{transform:translateY(-12px) rotate(.5deg)}}@media(max-width:760px){.status-shell{grid-template-columns:1fr;padding:4rem 1.4rem}.status-art{order:-1;max-width:310px;margin:auto}h1{font-size:clamp(2.7rem,15vw,4.8rem)}.status-attribution{justify-content:flex-start}.cwp img{width:92px}}@media(prefers-reduced-motion:reduce){.status-art svg{animation:none}}';
    }
}
