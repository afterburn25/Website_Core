<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Analytics\AnalyticsReport;
use Core\Analytics\AnalyticsService;
use Core\Config;
use Core\Database\Connection;
use Core\Database\Migrator;
use Core\Http\Request;
use Core\Settings\SettingsRepository;
use PHPUnit\Framework\TestCase;

final class AnalyticsTest extends TestCase
{
    private Connection $db;
    private AnalyticsService $service;

    protected function setUp(): void
    {
        $_SESSION=[];
        $this->db=Connection::sqlite();
        (new Migrator($this->db,WC_ROOT.'/database/migrations'))->migrate();
        $this->service=new AnalyticsService($this->db,new SettingsRepository($this->db),new Config('/file/does/not/exist.php'));
    }

    public function testSuccessfulPublicPageCreatesAnonymousVisitAndTrackingMarker(): void
    {
        $request=$this->request('/menu',['REMOTE_ADDR'=>'203.0.113.72','HTTP_USER_AGENT'=>'Mozilla/5.0 (iPhone) AppleWebKit/605.1.15 Version/17.0 Mobile Safari/604.1','HTTP_REFERER'=>'https://www.google.com/search?q=cajun+catering']);
        $html=$this->service->inject('<!doctype html><html><head><title>Cajun Menu</title></head><body>Menu</body></html>',$request,200,false);
        self::assertStringContainsString('data-core-analytics',$html);
        self::assertStringContainsString('/analytics/heartbeat',$html);
        $visit=$this->db->fetch('SELECT * FROM `analytics_visits` LIMIT 1');
        self::assertNotNull($visit);
        self::assertSame('Organic search',$visit['channel']);
        self::assertSame('Mobile',$visit['device_family']);
        self::assertSame('cajun catering',$visit['search_term']);
        self::assertStringNotContainsString('203.0.113.72',json_encode($visit,JSON_THROW_ON_ERROR));
        self::assertSame(1,(int)$this->db->fetchColumn('SELECT COUNT(*) FROM `analytics_pageviews`'));
    }

    public function testPrivacySignalsStaffAndUnsuccessfulResponsesAreNotTracked(): void
    {
        $dnt=$this->request('/', ['HTTP_DNT'=>'1']);
        self::assertSame('<html><body>Hi</body></html>',$this->service->inject('<html><body>Hi</body></html>',$dnt,200,false));
        self::assertSame('<html><body>Hi</body></html>',$this->service->inject('<html><body>Hi</body></html>',$this->request('/'),200,true));
        self::assertSame('<html><body>Hi</body></html>',$this->service->inject('<html><body>Hi</body></html>',$this->request('/missing'),404,false));
        self::assertSame(0,(int)$this->db->fetchColumn('SELECT COUNT(*) FROM `analytics_visits`'));
    }

    public function testCrawlersStaySeparateFromHumanReports(): void
    {
        $bot=$this->request('/menu',['HTTP_USER_AGENT'=>'Mozilla/5.0 compatible Googlebot/2.1']);
        $this->service->inject('<html><body>Menu</body></html>',$bot,200,false);
        self::assertSame(0,(int)$this->db->fetchColumn('SELECT COUNT(*) FROM `analytics_visits`'));
        self::assertSame(1,(int)$this->db->fetchColumn('SELECT hits FROM `analytics_crawlers`'));
    }

    public function testDashboardReportContainsCoreDimensions(): void
    {
        $this->service->inject('<html><head><title>Home</title></head><body>Hi</body></html>',$this->request('/'),200,false);
        $report=(new AnalyticsReport($this->db))->dashboard(date('Y-m-d'),date('Y-m-d'));
        self::assertSame(1,$report['summary']['visits']);
        self::assertSame(1,$report['summary']['pageviews']);
        self::assertNotEmpty($report['pages']);
        self::assertCount(7,$report['heatmap']);
        self::assertCount(24,$report['heatmap'][0]);
    }

    private function request(string $path,array $server=[]): Request
    {
        return new Request('GET',$path,'/catering',[],[],[],[],array_merge(['HTTP_HOST'=>'demo.example.com','REMOTE_ADDR'=>'127.0.0.1','HTTP_USER_AGENT'=>'Mozilla/5.0 Chrome/130.0'],$server),'');
    }
}
