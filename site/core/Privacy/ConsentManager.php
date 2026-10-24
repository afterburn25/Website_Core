<?php
declare(strict_types=1);

namespace Core\Privacy;

use Core\Settings\SettingsRepository;

final class ConsentManager
{
    public function __construct(private SettingsRepository $settings) {}

    public function inject(string $html, string $routeBase): string
    {
        if (!(bool) $this->settings->get('privacy.banner_enabled', true) || !stripos($html, '</body>')) return $html;
        $provider=(string)$this->settings->get('analytics.provider','none');
        $siteId=trim((string)$this->settings->get('analytics.site_id',''));
        $privacy=$routeBase.'/privacy/request';
        $payload=json_encode(['provider'=>$provider,'siteId'=>$siteId], JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
        $banner='<aside class="wc-consent" data-wc-consent hidden aria-label="Cookie choices"><div><strong>Your privacy, your choice.</strong><p>We use essential storage to run this site. Optional analytics only load if you allow them. <a href="'.htmlspecialchars($privacy,ENT_QUOTES).'">Privacy request</a></p></div><div><button type="button" data-consent="essential">Essential only</button><button type="button" data-consent="analytics">Allow analytics</button></div></aside>';
        $script='<style>.wc-consent{position:fixed;z-index:9998;left:1rem;right:1rem;bottom:1rem;margin:auto;max-width:920px;padding:1rem 1.15rem;display:flex;gap:1rem;align-items:center;justify-content:space-between;border:1px solid #ffffff2b;border-radius:14px;background:#071722f2;color:#e8f5f3;box-shadow:0 20px 60px #0008;font:500 14px/1.5 system-ui}.wc-consent[hidden]{display:none}.wc-consent p{margin:.25rem 0;color:#b9cbd1}.wc-consent a{color:#99f6e4}.wc-consent div:last-child{display:flex;gap:.5rem;flex-shrink:0}.wc-consent button{border:1px solid #5eead477;border-radius:8px;background:transparent;color:#dff;padding:.65rem .8rem;font-weight:700}.wc-consent button:last-child{background:#5eead4;color:#042f2e}@media(max-width:680px){.wc-consent{flex-direction:column;align-items:stretch}}</style><script>(()=>{const c='.(string)$payload.',k="wc_consent_v1",b=document.querySelector("[data-wc-consent]");const load=()=>{if(!c.siteId)return;if(c.provider==="ga4"&&/^G-[A-Z0-9]+$/.test(c.siteId)){let s=document.createElement("script");s.src="https://www.googletagmanager.com/gtag/js?id="+encodeURIComponent(c.siteId);s.async=true;document.head.appendChild(s);window.dataLayer=window.dataLayer||[];window.gtag=function(){dataLayer.push(arguments)};gtag("js",new Date);gtag("config",c.siteId,{anonymize_ip:true})}if(c.provider==="plausible"&&/^[A-Za-z0-9.-]+$/.test(c.siteId)){let s=document.createElement("script");s.src="https://plausible.io/js/script.js";s.defer=true;s.dataset.domain=c.siteId;document.head.appendChild(s)}};let v=localStorage.getItem(k);if(v==="analytics")load();else if(!v)b.hidden=false;b?.querySelectorAll("[data-consent]").forEach(x=>x.onclick=()=>{v=x.dataset.consent;localStorage.setItem(k,v);b.hidden=true;if(v==="analytics")load()})})();</script>';
        return (string)preg_replace('/<\/body>/i',$banner.$script.'</body>',$html,1);
    }
}
