<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Analytics\AnalyticsReport;
use Core\Analytics\AnalyticsService;
use Core\Http\Request;
use Core\Http\Response;

final class AnalyticsController extends Controller
{
    public function index(Request $r): Response
    {
        if (!$this->allowed()) return $this->forbidden();
        if ($r->isPost()) {
            $action=$r->string('action');
            if ($action==='settings') {
                $this->settings()->set('analytics.enabled',$r->input('enabled')==='1');
                $this->settings()->set('analytics.crawlers_enabled',$r->input('crawlers_enabled')==='1');
                $this->settings()->set('analytics.geography_enabled',$r->input('geography_enabled')==='1');
                $this->settings()->set('analytics.retention_days',max(30,min(365,(int)$r->input('retention_days',90))));
                $this->audit('analytics.settings_updated'); $this->flash('success','Analytics settings saved.');
            } elseif ($action==='cleanup') {
                $count=$this->service()->cleanup(); $this->audit('analytics.retention_run',['visits'=>$count]);
                $this->flash('success',$count.' expired visits removed.');
            }
            return $this->redirect($r,'/admin/analytics');
        }
        [$from,$to]=$this->dates($r);
        return $this->page($r,'analytics/index',[
            'report'=>$this->report()->dashboard($from,$to),
            'settings'=>['enabled'=>(bool)$this->settings()->get('analytics.enabled',true),'crawlers'=>(bool)$this->settings()->get('analytics.crawlers_enabled',true),'geography'=>(bool)$this->settings()->get('analytics.geography_enabled',true),'retention'=>(int)$this->settings()->get('analytics.retention_days',90)],
            'searchConsole'=>$this->report()->searchConsole(),
        ],'Analytics');
    }

    public function visitors(Request $r): Response
    {
        if(!$this->allowed())return $this->forbidden(); [$from,$to]=$this->dates($r);
        $filters=[];foreach(['channel','country_name','device_family','browser_family','os_family','referrer_domain','status','page_path'] as $key)$filters[$key]=$r->string($key);
        return $this->page($r,'analytics/visitors',['data'=>$this->report()->visitors($from,$to,$filters,(int)$r->input('page',1)),'filters'=>$filters],'Visitor Explorer');
    }

    public function geography(Request $r): Response
    {
        if(!$this->allowed())return $this->forbidden(); [$from,$to]=$this->dates($r);
        return $this->page($r,'analytics/geography',['data'=>$this->report()->geography($from,$to),'enabled'=>(bool)$this->settings()->get('analytics.geography_enabled',true)],'Analytics Geography');
    }

    public function live(Request $r): Response
    {
        if(!$this->allowed())return Response::json(['error'=>'Forbidden'],403);
        return Response::json(['visitors'=>$this->report()->live(),'generated_at'=>date('c')])->header('Cache-Control','private, no-store');
    }

    public function export(Request $r): Response
    {
        if(!$this->allowed())return $this->forbidden(); [$from,$to]=$this->dates($r);
        $rows=$this->db()->fetchAll('SELECT started_at,last_seen_at,page_count,active_seconds,channel,referrer_domain,utm_source,utm_medium,utm_campaign,browser_family,os_family,device_family,country_code,country_name,region_name,city_name FROM `'.$this->db()->table('analytics_visits').'` WHERE started_at BETWEEN ? AND ? ORDER BY started_at DESC LIMIT 25000',[$from.' 00:00:00',$to.' 23:59:59']);
        $stream=fopen('php://temp','w+');$head=['started_at','last_seen_at','page_count','active_seconds','channel','referrer_domain','utm_source','utm_medium','utm_campaign','browser','os','device','country_code','country','region','city'];fputcsv($stream,$head,',','"','');
        foreach($rows as $row)fputcsv($stream,array_map([$this,'csvCell'],array_values($row)),',','"','');rewind($stream);$csv=(string)stream_get_contents($stream);fclose($stream);
        $this->audit('analytics.exported',['rows'=>count($rows),'from'=>$from,'to'=>$to]);
        return Response::text($csv)->header('Content-Type','text/csv; charset=utf-8')->header('Content-Disposition','attachment; filename="website-analytics-'.$from.'-'.$to.'.csv"')->header('Cache-Control','private, no-store');
    }

    public function searchConsole(Request $r): Response
    {
        if(!$this->allowed())return $this->forbidden();
        if($r->string('action')==='remove'){$this->db()->run('DELETE FROM `'.$this->db()->table('analytics_search_console').'`');$this->flash('success','Search Console import removed.');return $this->redirect($r,'/admin/analytics');}
        $error=$this->uploadError($r,'search_csv');$file=$r->files['search_csv']??[];
        if($error===null && (int)($file['size']??0)>2097152)$error='CSV must be 2 MiB or smaller.';
        $count=0;
        if($error===null){$handle=@fopen((string)$file['tmp_name'],'rb');if($handle===false){$error='The uploaded CSV could not be opened.';$header=[];}else{$header=fgetcsv($handle,0,',','"','');}$normalized=array_map(fn($v)=>strtolower(trim((string)$v)),$header?:[]);$required=['top queries','clicks','impressions','ctr','position'];
            foreach($required as $column)if(!in_array($column,$normalized,true))$error='CSV must contain Top queries, Clicks, Impressions, CTR, and Position columns.';
            if($error===null&&$handle!==false){$rangeStart=$r->string('range_start');$rangeEnd=$r->string('range_end');$rangeStart=preg_match('/^\d{4}-\d{2}-\d{2}$/',$rangeStart)?$rangeStart:null;$rangeEnd=preg_match('/^\d{4}-\d{2}-\d{2}$/',$rangeEnd)?$rangeEnd:null;$pending=[];while(($row=fgetcsv($handle,0,',','"',''))!==false&&count($pending)<5000){$cells=array_slice(array_pad($row,count($normalized),''),0,count($normalized));$values=array_combine($normalized,$cells);$query=mb_substr(trim((string)($values['top queries']??'')),0,500);if($query==='')continue;$ctr=rtrim(trim((string)($values['ctr']??'0')),'%');$pending[]=['query_text'=>$query,'clicks'=>max(0,(int)str_replace(',','',(string)$values['clicks'])),'impressions'=>max(0,(int)str_replace(',','',(string)$values['impressions'])),'ctr'=>max(0,(float)$ctr)/100,'average_position'=>max(0,(float)$values['position']),'range_start'=>$rangeStart?:null,'range_end'=>$rangeEnd?:null,'imported_at'=>date('Y-m-d H:i:s')];}
                $this->db()->transaction(function($db)use($pending,&$count){$db->run('DELETE FROM `'.$db->table('analytics_search_console').'`');foreach($pending as $item){$db->insert('analytics_search_console',$item);$count++;}});
            }if($handle!==false)fclose($handle);
        }
        $this->flash($error===null?'success':'error',$error??($count.' Search Console queries imported.'));if($error===null)$this->audit('analytics.search_console_imported',['rows'=>$count]);return $this->redirect($r,'/admin/analytics');
    }

    private function allowed(): bool { $u=$this->user();return $u!==null&&$this->auth()->has($u,'analytics.view'); }
    private function report(): AnalyticsReport { return $this->c->get(AnalyticsReport::class); }
    private function service(): AnalyticsService { return $this->c->get(AnalyticsService::class); }
    private function dates(Request $r): array { return $this->report()->range($r->string('from',date('Y-m-d',time()-6*86400)),$r->string('to',date('Y-m-d'))); }
    private function csvCell(mixed $v): string { $v=(string)($v??'');return preg_match('/^[=+\-@]/',ltrim($v))?"'".$v:$v; }
}

