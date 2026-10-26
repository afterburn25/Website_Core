<?php
declare(strict_types=1);

namespace Core\Analytics;

use Core\Database\Connection;

final class AnalyticsReport
{
    public function __construct(private Connection $db) {}

    public function range(string $from, string $to): array
    {
        $from = $this->date($from, date('Y-m-d', time()-6*86400));
        $to = $this->date($to, date('Y-m-d'));
        if ($from > $to) [$from,$to]=[$to,$from];
        if (strtotime($to)-strtotime($from) > 365*86400) $from=date('Y-m-d',strtotime($to)-365*86400);
        return [$from,$to];
    }

    public function dashboard(string $from, string $to): array
    {
        [$from,$to]=$this->range($from,$to); $start=$from.' 00:00:00'; $end=$to.' 23:59:59';
        $visits=$this->rows($start,$end); $views=$this->viewRows($start,$end);
        $completed=array_values(array_filter($visits,fn($v)=>(strtotime((string)$v['last_seen_at'])?:time())<time()-1800));
        $bounces=count(array_filter($completed,fn($v)=>(int)$v['page_count']===1));
        $live=count(array_filter($visits,fn($v)=>strtotime((string)$v['last_seen_at'])>=time()-60));
        return [
            'from'=>$from,'to'=>$to,
            'summary'=>['visits'=>count($visits),'pageviews'=>count($views),'live'=>$live,
                'bounce_rate'=>count($completed)?round($bounces/count($completed)*100,1):0,
                'avg_active'=>count($visits)?(int)round(array_sum(array_column($visits,'active_seconds'))/count($visits)):0],
            'daily'=>$this->daily($visits,$views,$from,$to),
            'pages'=>$this->rank($views,'page_path',10), 'channels'=>$this->rank($visits,'channel',8),
            'referrers'=>$this->rank($visits,'referrer_domain',8), 'devices'=>$this->rank($visits,'device_family',6),
            'browsers'=>$this->rank($visits,'browser_family',8), 'systems'=>$this->rank($visits,'os_family',8),
            'countries'=>$this->rank($visits,'country_name',8), 'campaigns'=>$this->rank($visits,'utm_campaign',8),
            'search_terms'=>$this->rank($visits,'search_term',8), 'depth'=>$this->depth($visits),
            'entry_pages'=>$this->journeyRank($views,true), 'exit_pages'=>$this->journeyRank($views,false),
            'engagement'=>$this->engagement($visits), 'heatmap'=>$this->heatmap($visits),
            'crawlers'=>$this->crawlerRows($from,$to),
        ];
    }

    public function visitors(string $from,string $to,array $filters=[],int $page=1): array
    {
        [$from,$to]=$this->range($from,$to); $rows=$this->rows($from.' 00:00:00',$to.' 23:59:59');
        foreach (['channel','country_name','device_family','browser_family','os_family','referrer_domain'] as $key) {
            $wanted=trim((string)($filters[$key]??'')); if($wanted!=='')$rows=array_values(array_filter($rows,fn($r)=>(string)($r[$key]??'')===$wanted));
        }
        $status=trim((string)($filters['status']??''));
        if($status==='live')$rows=array_values(array_filter($rows,fn($r)=>(strtotime((string)$r['last_seen_at'])?:0)>=time()-60));
        if($status==='completed')$rows=array_values(array_filter($rows,fn($r)=>(strtotime((string)$r['last_seen_at'])?:time())<time()-1800));
        $pagePath=trim((string)($filters['page_path']??''));
        if($pagePath!==''){$visitIds=array_flip(array_map('intval',array_column($this->db->fetchAll('SELECT DISTINCT visit_id FROM `'.$this->db->table('analytics_pageviews').'` WHERE page_path LIKE ? LIMIT 200000',['%'.$pagePath.'%']),'visit_id')));$rows=array_values(array_filter($rows,fn($r)=>isset($visitIds[(int)$r['id']])));}
        usort($rows,fn($a,$b)=>strcmp((string)$b['started_at'],(string)$a['started_at']));
        $total=count($rows); $page=max(1,$page); $rows=array_slice($rows,($page-1)*25,25);
        foreach($rows as &$row)$row['journey']=$this->db->fetchAll('SELECT page_path,page_title,viewed_at,active_seconds FROM `'.$this->db->table('analytics_pageviews').'` WHERE visit_id=? ORDER BY viewed_at LIMIT 100',[(int)$row['id']]);
        return compact('rows','total','page','from','to');
    }

    public function geography(string $from,string $to): array
    {
        [$from,$to]=$this->range($from,$to); $rows=$this->rows($from.' 00:00:00',$to.' 23:59:59');
        return ['from'=>$from,'to'=>$to,'countries'=>$this->rank($rows,'country_name',50),'regions'=>$this->rank($rows,'region_name',50),'cities'=>$this->rank($rows,'city_name',50)];
    }

    public function live(): array
    {
        $cut=date('Y-m-d H:i:s',time()-60); $table=$this->db->table('analytics_visits');
        $rows=$this->db->fetchAll("SELECT id,last_seen_at,page_count,channel,device_family,country_name FROM `{$table}` WHERE last_seen_at>=? ORDER BY last_seen_at DESC LIMIT 100",[$cut]);
        foreach($rows as &$row)$row['current_page']=$this->db->fetchColumn('SELECT page_path FROM `'.$this->db->table('analytics_pageviews').'` WHERE visit_id=? ORDER BY viewed_at DESC LIMIT 1',[(int)$row['id']])?:'/';
        return $rows;
    }

    public function searchConsole(): array
    {
        return $this->db->fetchAll('SELECT * FROM `'.$this->db->table('analytics_search_console').'` ORDER BY clicks DESC,impressions DESC LIMIT 250');
    }

    private function rows(string $start,string $end): array { return $this->db->fetchAll('SELECT * FROM `'.$this->db->table('analytics_visits').'` WHERE started_at BETWEEN ? AND ? ORDER BY started_at LIMIT 200000',[$start,$end]); }
    private function viewRows(string $start,string $end): array { return $this->db->fetchAll('SELECT page_path,page_title,viewed_at,active_seconds,visit_id FROM `'.$this->db->table('analytics_pageviews').'` WHERE viewed_at BETWEEN ? AND ? ORDER BY viewed_at LIMIT 200000',[$start,$end]); }
    private function crawlerRows(string $from,string $to): array { return $this->db->fetchAll('SELECT crawler_family label,SUM(hits) value FROM `'.$this->db->table('analytics_crawlers').'` WHERE hit_date BETWEEN ? AND ? GROUP BY crawler_family ORDER BY value DESC LIMIT 20',[$from,$to]); }
    private function rank(array $rows,string $key,int $limit): array { $out=[];foreach($rows as $r){$label=trim((string)($r[$key]??''));if($label==='')$label='Not supplied';$out[$label]=($out[$label]??0)+1;}arsort($out);$result=[];foreach(array_slice($out,0,$limit,true) as $label=>$value)$result[]=compact('label','value');return $result; }
    private function depth(array $rows): array { $b=['1 page'=>0,'2 pages'=>0,'3–5 pages'=>0,'6+ pages'=>0];foreach($rows as $r){$n=(int)$r['page_count'];$b[$n<=1?'1 page':($n===2?'2 pages':($n<=5?'3–5 pages':'6+ pages'))]++;}$out=[];foreach($b as $label=>$value)$out[]=compact('label','value');return$out; }
    private function daily(array $visits,array $views,string $from,string $to): array { $out=[];for($t=strtotime($from);$t<=strtotime($to);$t+=86400){$d=date('Y-m-d',$t);$out[$d]=['date'=>$d,'visits'=>0,'pageviews'=>0];}foreach($visits as $r){$d=substr((string)$r['started_at'],0,10);if(isset($out[$d]))$out[$d]['visits']++;}foreach($views as $r){$d=substr((string)$r['viewed_at'],0,10);if(isset($out[$d]))$out[$d]['pageviews']++;}return array_values($out); }
    private function journeyRank(array $views,bool $entry): array { $by=[];foreach($views as $row)$by[(int)$row['visit_id']][]=$row;$picked=[];foreach($by as $journey){$row=$entry?$journey[0]:$journey[count($journey)-1];$picked[]=['page_path'=>$row['page_path']];}return $this->rank($picked,'page_path',8); }
    private function engagement(array $rows): array { $bins=['Under 10 sec'=>0,'10–29 sec'=>0,'30–59 sec'=>0,'1–4 min'=>0,'5+ min'=>0];foreach($rows as $r){$n=(int)$r['active_seconds'];$bins[$n<10?'Under 10 sec':($n<30?'10–29 sec':($n<60?'30–59 sec':($n<300?'1–4 min':'5+ min')))]++;}$out=[];foreach($bins as $label=>$value)$out[]=compact('label','value');return$out; }
    private function heatmap(array $rows): array { $map=array_fill(0,7,array_fill(0,24,0));foreach($rows as $r){$t=strtotime((string)$r['started_at']);if($t!==false)$map[(int)date('w',$t)][(int)date('G',$t)]++;}return$map; }
    private function date(string $value,string $fallback): string { return preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)&&strtotime($value)!==false?$value:$fallback; }
}
