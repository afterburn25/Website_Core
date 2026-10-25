<?php
declare(strict_types=1);

namespace Core\Analytics;

use Core\Config;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Settings\SettingsRepository;

/** Privacy-first, first-party collection shared by Core and every module. */
final class AnalyticsService
{
    private const SESSION_IDLE = 1800;

    public function __construct(
        private Connection $db,
        private SettingsRepository $settings,
        private Config $config,
    ) {}

    public function inject(string $html, Request $request, int $status, bool $staff): string
    {
        if (!$this->shouldCount($request, $status, $staff) || !str_contains(strtolower($html), '</body>')) {
            return $html;
        }
        if ($this->isCrawler($request->userAgent(), $crawler)) {
            if ((bool) $this->settings->get('analytics.crawlers_enabled', true)) {
                $this->recordCrawler($crawler, $request->path);
            }
            return $html;
        }

        $title = null;
        if (preg_match('/<title[^>]*>(.*?)<\/title>/is', $html, $m)) {
            $title = mb_substr(trim(html_entity_decode(strip_tags($m[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 0, 255);
        }
        $token = $this->recordPageview($request, $title);
        if ($token === null) return $html;

        $endpoint = htmlspecialchars($request->routeUrl('/analytics/heartbeat'), ENT_QUOTES, 'UTF-8');
        $asset = htmlspecialchars($request->routeUrl('/_a/core/js/analytics.js') . '&v=' . rawurlencode(defined('WC_VERSION') ? WC_VERSION : 'development'), ENT_QUOTES, 'UTF-8');
        $marker = '<span hidden data-core-analytics data-token="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
            . '" data-endpoint="' . $endpoint . '"></span><script src="' . $asset . '" defer></script>';
        return str_ireplace('</body>', $marker . '</body>', $html);
    }

    public function heartbeat(Request $request): void
    {
        if (!(bool) $this->settings->get('analytics.enabled', true) || $this->privacySignal($request)) return;
        if (!$this->sameOrigin($request)) return;
        $payload = json_decode($request->rawBody, true);
        if (!is_array($payload)) return;
        $token = (string) ($payload['token'] ?? '');
        $seconds = max(0, min(14400, (int) ($payload['active_seconds'] ?? 0)));
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) return;

        $views = $this->db->table('analytics_pageviews');
        $row = $this->db->fetch("SELECT id, visit_id, active_seconds, last_seen_at FROM `{$views}` WHERE token_hash=?", [hash('sha256', $token)]);
        if (!$row) return;
        $now = date('Y-m-d H:i:s');
        $elapsed = max(0, time() - (strtotime((string) $row['last_seen_at']) ?: time()));
        $accepted = min($seconds, (int) $row['active_seconds'] + $elapsed + 20);
        $delta = max(0, $accepted - (int) $row['active_seconds']);
        $this->db->transaction(function (Connection $db) use ($views, $row, $accepted, $delta, $now): void {
            $db->update('analytics_pageviews', ['active_seconds' => $accepted, 'last_seen_at' => $now], 'id=?', [(int) $row['id']]);
            $visits = $db->table('analytics_visits');
            $db->run("UPDATE `{$visits}` SET active_seconds=active_seconds+?, last_seen_at=? WHERE id=?", [$delta, $now, (int) $row['visit_id']]);
        });
    }

    public function cleanup(): int
    {
        $days = max(30, min(365, (int) $this->settings->get('analytics.retention_days', 90)));
        $cutoff = date('Y-m-d H:i:s', time() - ($days * 86400));
        $visits = $this->db->table('analytics_visits');
        $views = $this->db->table('analytics_pageviews');
        $ids = array_map('intval', array_column($this->db->fetchAll("SELECT id FROM `{$visits}` WHERE started_at < ? LIMIT 5000", [$cutoff]), 'id'));
        $this->db->run('DELETE FROM `'.$this->db->table('analytics_crawlers').'` WHERE hit_date < ?',[substr($cutoff,0,10)]);
        if ($ids === []) return 0;
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $deleted=$this->db->transaction(function (Connection $db) use ($visits, $views, $ids, $marks): int {
            $db->run("DELETE FROM `{$views}` WHERE visit_id IN ({$marks})", $ids);
            return $db->run("DELETE FROM `{$visits}` WHERE id IN ({$marks})", $ids)->rowCount();
        });
        return $deleted;
    }

    private function recordPageview(Request $request, ?string $title): ?string
    {
        $now = date('Y-m-d H:i:s');
        $last = (int) ($_SESSION['_wc_analytics_seen'] ?? 0);
        $key = is_string($_SESSION['_wc_analytics_visit'] ?? null) ? $_SESSION['_wc_analytics_visit'] : '';
        if ($key !== '' && $last < time() - self::SESSION_IDLE) {
            $this->db->update('analytics_visits', ['completed_at'=>date('Y-m-d H:i:s',$last)], 'visit_key=?', [hash('sha256',$key)]);
            $key = '';
        }
        if ($key === '') $key = bin2hex(random_bytes(32));
        $_SESSION['_wc_analytics_visit'] = $key;
        $_SESSION['_wc_analytics_seen'] = time();
        $visitKey = hash('sha256', $key);
        $visits = $this->db->table('analytics_visits');
        $visit = $this->db->fetch("SELECT id FROM `{$visits}` WHERE visit_key=?", [$visitKey]);
        if (!$visit) {
            [$browser, $os, $device] = $this->clientFamilies($request->userAgent());
            [$domain, $term, $channel] = $this->referral($request->header('Referer'), $request->host());
            [$countryCode, $country, $region, $city] = $this->geography($request);
            $secret = (string) $this->config->get('security.app_key', 'website-core');
            $data = [
                'visit_key' => $visitKey,
                'anonymous_hash' => hash_hmac('sha256', $key . '|' . $request->ip(), $secret),
                'started_at' => $now, 'last_seen_at' => $now, 'completed_at' => null,
                'page_count' => 0, 'active_seconds' => 0, 'channel' => $this->campaignChannel($request, $channel),
                'referrer_domain' => $domain, 'search_term' => $term,
                'utm_source' => $this->cleanCampaign($request->query['utm_source'] ?? null, 120),
                'utm_medium' => $this->cleanCampaign($request->query['utm_medium'] ?? null, 120),
                'utm_campaign' => $this->cleanCampaign($request->query['utm_campaign'] ?? null, 180),
                'browser_family' => $browser, 'os_family' => $os, 'device_family' => $device,
                'country_code' => $countryCode, 'country_name' => $country,
                'region_name' => $region, 'city_name' => $city,
            ];
            try {
                $visit = ['id' => (int) $this->db->insert('analytics_visits', $data)];
            } catch (\Throwable) {
                // Concurrent tabs can race to create the same session.
                $visit = $this->db->fetch("SELECT id FROM `{$visits}` WHERE visit_key=?", [$visitKey]);
                if (!$visit) return null;
            }
        }
        $token = bin2hex(random_bytes(24));
        $path = mb_substr('/' . ltrim((string) parse_url($request->path, PHP_URL_PATH), '/'), 0, 500);
        $this->db->transaction(function (Connection $db) use ($visit, $path, $title, $token, $now): void {
            $db->insert('analytics_pageviews', [
                'visit_id' => (int) $visit['id'], 'page_path' => $path, 'page_title' => $title,
                'token_hash' => hash('sha256', $token), 'viewed_at' => $now, 'last_seen_at' => $now,
                'active_seconds' => 0,
            ]);
            $table = $db->table('analytics_visits');
            $db->run("UPDATE `{$table}` SET page_count=page_count+1,last_seen_at=?,completed_at=? WHERE id=?", [$now, $now, (int) $visit['id']]);
        });
        return $token;
    }

    private function shouldCount(Request $request, int $status, bool $staff): bool
    {
        if ($status !== 200 || $staff || !(bool) $this->settings->get('analytics.enabled', true)) return false;
        if ($this->privacySignal($request) || $request->method !== 'GET') return false;
        $path = rtrim($request->path, '/') ?: '/';
        foreach (['/admin', '/preview', '/analytics', '/api', '/media', '/_a', '/forms', '/privacy'] as $blocked) {
            if ($path === $blocked || str_starts_with($path, $blocked . '/')) return false;
        }
        $purpose = strtolower($request->header('Purpose') . ' ' . $request->header('Sec-Purpose'));
        return !str_contains($purpose, 'prefetch');
    }

    private function privacySignal(Request $request): bool
    {
        return trim($request->header('DNT')) === '1' || trim($request->header('Sec-GPC')) === '1';
    }

    private function sameOrigin(Request $request): bool
    {
        $origin = $request->header('Origin');
        if ($origin === '') return true;
        $host = strtolower((string) parse_url($origin, PHP_URL_HOST));
        return $host !== '' && hash_equals($request->host(), $host);
    }

    private function isCrawler(string $ua, ?string &$family): bool
    {
        $map = ['googlebot'=>'Googlebot','bingbot'=>'Bingbot','duckduckbot'=>'DuckDuckBot','yandexbot'=>'YandexBot','baiduspider'=>'Baidu','facebookexternalhit'=>'Facebook','twitterbot'=>'X/Twitter','linkedinbot'=>'LinkedIn','slurp'=>'Yahoo','crawler'=>'Other crawler','spider'=>'Other crawler','bot'=>'Other bot'];
        $lower = strtolower($ua);
        foreach ($map as $needle => $label) if (str_contains($lower, $needle)) { $family = $label; return true; }
        $family = null; return false;
    }

    private function recordCrawler(string $family, string $path): void
    {
        $table = $this->db->table('analytics_crawlers');
        $date = date('Y-m-d'); $now = date('Y-m-d H:i:s'); $path = mb_substr($path, 0, 500);
        $row = $this->db->fetch("SELECT id FROM `{$table}` WHERE crawler_family=? AND page_path=? AND hit_date=?", [$family, $path, $date]);
        if ($row) $this->db->run("UPDATE `{$table}` SET hits=hits+1,last_seen_at=? WHERE id=?", [$now, (int) $row['id']]);
        else $this->db->insert('analytics_crawlers', ['crawler_family'=>$family,'page_path'=>$path,'hit_date'=>$date,'hits'=>1,'last_seen_at'=>$now]);
    }

    /** @return array{string,string,string} */
    private function clientFamilies(string $ua): array
    {
        $browser = match (true) { str_contains($ua,'Edg/')=>'Edge', str_contains($ua,'OPR/')=>'Opera', str_contains($ua,'Chrome/')=>'Chrome', str_contains($ua,'Firefox/')=>'Firefox', str_contains($ua,'Safari/')=>'Safari', default=>'Unknown' };
        $os = match (true) { str_contains($ua,'Windows')=>'Windows', str_contains($ua,'Android')=>'Android', str_contains($ua,'iPhone')||str_contains($ua,'iPad')=>'iOS', str_contains($ua,'Mac OS')=>'macOS', str_contains($ua,'Linux')=>'Linux', default=>'Unknown' };
        $device = match (true) { str_contains($ua,'iPad')||str_contains($ua,'Tablet')=>'Tablet', preg_match('/Mobile|Android|iPhone/i',$ua)===1=>'Mobile', default=>'Desktop' };
        return [$browser,$os,$device];
    }

    /** @return array{?string,?string,string} */
    private function referral(string $referer, string $siteHost): array
    {
        $host = strtolower((string) parse_url($referer, PHP_URL_HOST));
        if ($host === '') return [null,null,'Direct'];
        if ($host === strtolower($siteHost)) return [null,null,'Direct'];
        $term = null; $channel = 'Referral';
        foreach (['google.'=>'Organic search','bing.com'=>'Organic search','duckduckgo.com'=>'Organic search','yahoo.com'=>'Organic search'] as $needle=>$type) {
            if (str_contains($host,$needle)) { $channel=$type; parse_str((string) parse_url($referer, PHP_URL_QUERY),$q); $term=$this->cleanCampaign($q['q']??$q['p']??null,255); break; }
        }
        return [mb_substr($host,0,255),$term,$channel];
    }

    private function campaignChannel(Request $r, string $fallback): string
    {
        $medium = strtolower((string) ($r->query['utm_medium'] ?? ''));
        if ($medium === '') return $fallback;
        if (str_contains($medium,'email')) return 'Email';
        if (preg_match('/cpc|ppc|paid/', $medium)) return 'Paid';
        if (preg_match('/social|facebook|instagram|linkedin/', $medium)) return 'Social';
        return 'Campaign';
    }

    private function cleanCampaign(mixed $value, int $length): ?string
    {
        if (!is_string($value)) return null;
        $value = trim(preg_replace('/[\x00-\x1F\x7F]/u','',$value) ?? '');
        return $value === '' ? null : mb_substr($value,0,$length);
    }

    /** @return array{?string,?string,?string,?string} */
    private function geography(Request $r): array
    {
        if (!(bool) $this->settings->get('analytics.geography_enabled', true)) return [null,null,null,null];
        $code = strtoupper(trim((string) ($r->server['HTTP_CF_IPCOUNTRY'] ?? $r->server['GEOIP_COUNTRY_CODE'] ?? '')));
        if (!preg_match('/^[A-Z]{2}$/',$code) || $code === 'XX') $code = null;
        $country = $this->cleanCampaign($r->server['GEOIP_COUNTRY_NAME'] ?? null,120);
        $region = $this->cleanCampaign($r->server['GEOIP_REGION_NAME'] ?? null,160);
        $city = $this->cleanCampaign($r->server['GEOIP_CITY'] ?? null,160);
        return [$code,$country,$region,$city];
    }
}
