<?php
declare(strict_types=1);

namespace CajunWebPro\Landscaping;

use Core\Admin\Nav;
use Core\Auth\AuthService;
use Core\Database\Connection;
use Core\Http\Request;
use Core\Http\Response;
use Core\Mail\SystemMail;
use Core\Media\MediaService;
use Core\Modules\ModuleContext;
use Core\Security\Csrf;
use Core\Security\RateLimiter;
use Core\Settings\SettingsRepository;
use Core\View\View;

final class LandscapingController
{
    private const STATUSES = ['new','reviewing','site-visit','estimate-in-progress','estimate-sent','accepted','scheduled','completed','declined','cancelled'];

    public function __construct(private ModuleContext $ctx, private string $ns) {}

    public function home(Request $r): Response
    {
        return $this->page($r, 'home', [
            'services'=>$this->rows('landscaping_services', 'visible = 1 AND featured = 1', 4),
            'projects'=>$this->rows('landscaping_projects', 'visible = 1 AND featured = 1', 3),
            'plans'=>$this->rows('landscaping_plans', 'visible = 1', 3),
            'seasonal'=>$this->rows('landscaping_seasonal_services', 'visible = 1', 3),
        ], 'Home', 'Professional lawn care, landscape installation, and property maintenance.');
    }

    public function services(Request $r): Response
    {
        return $this->page($r, 'services', ['services'=>$this->rows('landscaping_services', 'visible = 1')], 'Services', 'Explore lawn care, landscape installation, beds, trimming, cleanup, and commercial grounds services.');
    }

    public function serviceDetail(Request $r): Response
    {
        $row = $this->db()->fetch('SELECT * FROM `' . $this->db()->table('landscaping_services') . '` WHERE slug = ? AND visible = 1', [(string) ($r->attributes['slug'] ?? '')]);
        return $row ? $this->page($r, 'service-detail', ['service'=>$row], (string) $row['name'], (string) $row['summary']) : Response::notFound();
    }

    public function projects(Request $r): Response
    {
        return $this->page($r, 'projects', ['projects'=>$this->rows('landscaping_projects', 'visible = 1')], 'Projects', 'Before-and-after lawn and landscape transformations.');
    }

    public function plans(Request $r): Response
    {
        return $this->page($r, 'plans', ['plans'=>$this->rows('landscaping_plans', 'visible = 1')], 'Maintenance Plans', 'Recurring property care plans built around your yard and schedule.');
    }

    public function serviceAreas(Request $r): Response
    {
        return $this->page($r, 'service-areas', ['areas'=>$this->rows('landscaping_service_areas', 'visible = 1')], 'Service Areas', 'Cities, ZIP codes, and travel details for landscaping service.');
    }

    public function seasonal(Request $r): Response
    {
        return $this->page($r, 'seasonal', ['seasonal'=>$this->rows('landscaping_seasonal_services', 'visible = 1')], 'Seasonal Services', 'Timely cleanup, lawn, planting, and storm-response services.');
    }

    public function about(Request $r): Response { return $this->page($r, 'about', [], 'About', 'Dependable landscape care built around clear communication and consistent work.'); }
    public function contact(Request $r): Response { return $this->page($r, 'contact', [], 'Contact', 'Contact the lawn care and landscaping team.'); }

    public function estimate(Request $r, ?string $error = null, array $old = []): Response
    {
        return $this->page($r, 'estimate', ['csrf'=>$this->ctx->service(Csrf::class),'services'=>$this->rows('landscaping_services','visible = 1'),'plans'=>$this->rows('landscaping_plans','visible = 1'),'error'=>$error,'sent'=>$r->string('sent') === '1','old'=>$old,'chosen'=>$old['service_interest']??($r->string('service')?:$r->string('plan'))], 'Request an Estimate', 'Share property and service details for a landscaping estimate.', true);
    }

    public function submitEstimate(Request $r): Response
    {
        if (!$this->ctx->service(Csrf::class)->validate($r)) return Response::text('Invalid CSRF token.', 419);
        if ($r->string('website') !== '') return Response::redirect($this->ctx->url($r->basePath, '/request-estimate', ['sent'=>1]));
        if (!$this->ctx->service(RateLimiter::class)->attempt('landscaping-estimate:' . $r->ip(), 4, 900)) return $this->estimate($r, 'Too many submissions. Please wait and try again.', $r->post);
        $data = $this->estimateInput($r);
        $errors = $this->estimateErrors($data);
        if ($errors !== []) return $this->estimate($r, implode(' ', $errors), $data);
        [$photos,$photoError] = $this->storePhotos($r);
        if ($photoError !== null) return $this->estimate($r, $photoError, $data);
        $record = $data + ['status'=>'new','photo_media_ids'=>json_encode($photos),'source_ip'=>$r->ip(),'created_at'=>date('Y-m-d H:i:s'),'updated_at'=>null];
        $id = $this->db()->insert('landscaping_estimates', $record);
        $to = (string) $this->settings()->get('site.contact_email', '');
        if ($to !== '' && filter_var($to, FILTER_VALIDATE_EMAIL)) {
            $lines = [];
            foreach ($record as $key=>$value) if ($value !== null && $value !== '' && $key !== 'source_ip') $lines[] = ucwords(str_replace('_',' ',$key)) . ': ' . (is_scalar($value) ? (string) $value : '');
            $this->ctx->service(SystemMail::class)->queue($to, 'New landscaping estimate request from ' . $data['name'], implode("\n", $lines), 'landscaping-estimate:' . $id);
        }
        return Response::redirect($this->ctx->url($r->basePath, '/request-estimate', ['sent'=>1]));
    }

    public function admin(Request $r): Response
    {
        $screen = basename($r->path);
        $cap = $screen === 'estimates' ? 'landscaping.estimates' : 'landscaping.manage';
        if ($forbidden = $this->guard($cap)) return $forbidden;
        if ($screen === 'estimates') {
            $status = $r->string('status');
            $sql = 'SELECT * FROM `' . $this->db()->table('landscaping_estimates') . '`'; $params=[];
            if (in_array($status, self::STATUSES, true)) { $sql .= ' WHERE status = ?'; $params[]=$status; }
            $sql .= ' ORDER BY id DESC LIMIT 250';
            return $this->adminPage($r, 'admin-estimates', ['rows'=>$this->db()->fetchAll($sql,$params),'statuses'=>self::STATUSES,'selectedStatus'=>$status,'saved'=>$r->string('saved')==='1'], 'Estimate Leads');
        }
        if ($screen === 'settings') {
            return $this->adminPage($r, 'admin-settings', ['values'=>[
                'phone'=>$this->settings()->get('landscaping.phone','(555) 123-4567'),
                'service_radius'=>$this->settings()->get('landscaping.service_radius',''),
                'response_time'=>$this->settings()->get('landscaping.response_time','We normally respond within one business day.'),
                'estimate_notice'=>$this->settings()->get('landscaping.estimate_notice','Submitting a request does not schedule or authorize work.'),
            ],'saved'=>$r->string('saved')==='1'], 'Landscaping Settings');
        }
        $config = $this->recordConfig($screen);
        if ($config === null) return Response::notFound();
        return $this->adminPage($r, 'admin-records', ['screen'=>$screen,'config'=>$config,'rows'=>$this->rows($config['table']),'saved'=>$r->string('saved')==='1'], $config['title']);
    }

    public function adminSave(Request $r): Response
    {
        $screen = basename($r->path);
        $cap = $screen === 'estimates' ? 'landscaping.estimates' : 'landscaping.manage';
        if ($forbidden = $this->guard($cap)) return $forbidden;
        if (!$this->ctx->service(Csrf::class)->validate($r)) return Response::text('Invalid CSRF token.', 419);
        if ($screen === 'estimates') {
            $status = $r->string('status');
            if (in_array($status,self::STATUSES,true)) $this->db()->update('landscaping_estimates',['status'=>$status,'admin_notes'=>mb_substr($r->string('admin_notes'),0,5000),'updated_at'=>date('Y-m-d H:i:s')],'id = ?',[(int)$r->string('id')]);
            return Response::redirect($r->routeUrl('/admin/landscaping/estimates',['saved'=>1]));
        }
        if ($screen === 'settings') {
            foreach (['phone'=>80,'service_radius'=>500,'response_time'=>500,'estimate_notice'=>1000] as $key=>$max) $this->settings()->set('landscaping.'.$key, mb_substr($r->string($key),0,$max));
            return Response::redirect($r->routeUrl('/admin/landscaping/settings',['saved'=>1]));
        }
        $config = $this->recordConfig($screen);
        if ($config === null) return Response::notFound();
        $action = $r->string('action'); $id=(int)$r->string('id');
        if ($action === 'delete') $this->db()->run('DELETE FROM `' . $this->db()->table($config['table']) . '` WHERE id = ?',[$id]);
        elseif ($action === 'toggle') $this->db()->run('UPDATE `' . $this->db()->table($config['table']) . '` SET visible = CASE WHEN visible = 1 THEN 0 ELSE 1 END WHERE id = ?',[$id]);
        elseif (in_array($action,['add','save'],true)) {
            $values = $this->recordValues($r,$config);
            if (($values['name'] ?? '') !== '') {
                $values['updated_at']=date('Y-m-d H:i:s');
                if ($action === 'add') {
                    if ($config['slug']) $values['slug']=$this->uniqueSlug($config['table'],(string)$values['name']);
                    $values['created_at']=date('Y-m-d H:i:s'); $this->db()->insert($config['table'],$values);
                } else $this->db()->update($config['table'],$values,'id = ?',[$id]);
            }
        }
        return Response::redirect($r->routeUrl('/admin/landscaping/'.$screen,['saved'=>1]));
    }

    private function recordConfig(string $screen): ?array
    {
        $common = ['visible'=>['Visible','bool',1],'sort_order'=>['Sort order','int',50]];
        $configs = [
            'services'=>['title'=>'Services','table'=>'landscaping_services','slug'=>true,'fields'=>['name'=>['Name','text',''],'category'=>['Category','text',''],'summary'=>['Summary','textarea',''],'details'=>['Details','textarea',''],'audience'=>['Audience','select:residential|commercial|both','both'],'image_url'=>['Image URL','url',''],'featured'=>['Featured','bool',0]]+$common],
            'projects'=>['title'=>'Projects','table'=>'landscaping_projects','slug'=>true,'fields'=>['name'=>['Name','text',''],'location_label'=>['Location label','text',''],'summary'=>['Summary','textarea',''],'services_used'=>['Services used','textarea',''],'before_image_url'=>['Before image URL','url',''],'after_image_url'=>['After image URL','url',''],'completed_on'=>['Completion date','date',''],'featured'=>['Featured','bool',0]]+$common],
            'plans'=>['title'=>'Maintenance Plans','table'=>'landscaping_plans','slug'=>true,'fields'=>['name'=>['Name','text',''],'description'=>['Description','textarea',''],'cadence'=>['Cadence','text',''],'seasonal_notes'=>['Seasonal notes','textarea',''],'price_cents'=>['Starting price','money',0],'price_note'=>['Price note','text','Custom estimate'],'inclusions'=>['Inclusions, one per line','textarea',''],'featured'=>['Featured','bool',0]]+$common],
            'areas'=>['title'=>'Service Areas','table'=>'landscaping_service_areas','slug'=>false,'fields'=>['name'=>['Name','text',''],'zip_codes'=>['ZIP codes','textarea',''],'travel_note'=>['Travel note','textarea',''],'map_copy'=>['Map/area copy','textarea','']]+$common],
            'seasonal'=>['title'=>'Seasonal Services','table'=>'landscaping_seasonal_services','slug'=>false,'fields'=>['name'=>['Name','text',''],'season'=>['Season','text',''],'description'=>['Description','textarea',''],'availability_note'=>['Availability note','text',''],'cta_label'=>['Button label','text','Request an estimate']]+$common],
        ];
        return $configs[$screen] ?? null;
    }

    private function recordValues(Request $r, array $config): array
    {
        $values=[];
        foreach ($config['fields'] as $field=>$meta) {
            $type=$meta[1];
            if ($type === 'bool') $values[$field]=$r->string($field)==='1'?1:0;
            elseif ($type === 'int') $values[$field]=(int)$r->string($field,(string)$meta[2]);
            elseif ($type === 'money') $values[$field]=max(0,(int)round(((float)$r->string($field,'0'))*100));
            elseif ($type === 'url') { $url=mb_substr($r->string($field),0,1000); $values[$field]=$url===''||filter_var($url,FILTER_VALIDATE_URL)?$url:''; }
            else $values[$field]=mb_substr($r->string($field),(int)0,$type==='textarea'?5000:255);
        }
        return $values;
    }

    private function page(Request $r, string $template, array $data, string $title, string $description, bool $noIndex=false): Response
    {
        $view=$this->ctx->service(View::class); $settings=$this->settings();
        $theme=(string)$settings->get('theme.active','base'); $supported=['landscaping-bold-outdoors','landscaping-estate-green','landscaping-neighborhood-lawn','landscaping-earth-stone'];
        $theme=in_array($theme,$supported,true)?$theme:'landscaping-bold-outdoors';
        $shared=['basePath'=>$this->ctx->routeBase($r->basePath),'assetBase'=>$this->ctx->assetBase($r->basePath),'currentPath'=>rtrim($r->path,'/')?:'/','siteName'=>(string)$settings->get('site.name','Landscaping Co.'),'tagline'=>(string)$settings->get('site.tagline','Beautiful yards. Brighter days.'),'phone'=>(string)$settings->get('landscaping.phone','(555) 123-4567'),'contactEmail'=>(string)$settings->get('site.contact_email','hello@example.com'),'serviceRadius'=>(string)$settings->get('landscaping.service_radius',''),'responseTime'=>(string)$settings->get('landscaping.response_time','We normally respond within one business day.'),'estimateNotice'=>(string)$settings->get('landscaping.estimate_notice','Submitting a request does not schedule or authorize work.'),'navigation'=>$this->ctx->navigationTree(),'footerNavigation'=>$this->ctx->navigationTree('footer'),'brandLogoUrl'=>$this->ctx->brandAssetUrl($r,'logo',256),'faviconUrl'=>$this->ctx->brandAssetUrl($r,'favicon'),'copyrightNotice'=>$this->ctx->copyrightNotice((string)$settings->get('site.name','Landscaping Co.')),'headerSocialLinks'=>$this->ctx->socialLinks('header'),'footerSocialLinks'=>$this->ctx->socialLinks('footer'),'activeTheme'=>$theme,'page'=>['title'=>$title,'meta_description'=>$description,'noindex'=>$noIndex]];
        foreach ($shared as $key=>$value) $view->share($key,$value);
        $content=$view->render($this->ns.':'.$template,array_merge($shared,$data));
        return Response::html($view->render($this->ns.':layout',array_merge($shared,['content'=>$content])));
    }

    private function adminPage(Request $r,string $template,array $data,string $title): Response
    {
        $auth=$this->ctx->service(AuthService::class); $view=$this->ctx->service(View::class);
        foreach (['user'=>$auth->user(),'csrf'=>$this->ctx->service(Csrf::class),'basePath'=>$r->routeBase(),'assetBase'=>$this->ctx->assetBase($r->basePath),'frontController'=>rtrim($r->basePath,'/').'/index.php','currentPath'=>$r->path,'nav'=>$this->ctx->service(Nav::class)->visible($auth->user()),'siteName'=>$this->settings()->get('site.name','Website Core'),'brandLogoUrl'=>$this->ctx->brandAssetUrl($r,'logo',480),'faviconUrl'=>$this->ctx->brandAssetUrl($r,'favicon'),'pageTitle'=>$title,'flash'=>null] as $key=>$value) $view->share($key,$value);
        return Response::html($view->renderInLayout($this->ns.':'.$template,'admin:layouts/admin',$data));
    }

    private function estimateInput(Request $r): array
    {
        return ['name'=>mb_substr($r->string('name'),0,190),'email'=>mb_substr($r->string('email'),0,190),'phone'=>mb_substr($r->string('phone'),0,80),'address'=>mb_substr($r->string('address'),0,255),'city'=>mb_substr($r->string('city'),0,120),'state'=>mb_substr($r->string('state'),0,80),'zip'=>mb_substr($r->string('zip'),0,20),'property_type'=>mb_substr($r->string('property_type'),0,80),'lot_size'=>mb_substr($r->string('lot_size'),0,80),'service_interest'=>mb_substr($r->string('service_interest'),0,3000),'schedule_preference'=>mb_substr($r->string('schedule_preference'),0,120),'budget'=>mb_substr($r->string('budget'),0,100),'message'=>mb_substr($r->string('message'),0,5000),'consent'=>$r->string('consent')==='1'?1:0];
    }

    private function estimateErrors(array $d): array
    {
        $e=[]; if ($d['name']==='') $e[]='Your name is required.'; if (!filter_var($d['email'],FILTER_VALIDATE_EMAIL)) $e[]='A valid email is required.'; if ($d['phone']==='') $e[]='A phone number is required.'; if ($d['address']===''||$d['city']===''||$d['state']===''||$d['zip']==='') $e[]='The property address is required.'; if ($d['service_interest']==='') $e[]='Choose or describe at least one service.'; if (!$d['consent']) $e[]='Please confirm permission to contact you about this request.'; return $e;
    }

    private function storePhotos(Request $r): array
    {
        $file=$r->files['photos']??null; if (!is_array($file)||!isset($file['name'])) return [[],null];
        $names=is_array($file['name'])?$file['name']:[$file['name']]; $ids=[];
        if (count(array_filter($names, fn ($name): bool => (string) $name !== '')) > 3) return [[], 'You may attach up to three property photos.'];
        for ($i=0;$i<min(3,count($names));$i++) {
            $one=['name'=>(string)$names[$i],'tmp_name'=>(string)(is_array($file['tmp_name'])?$file['tmp_name'][$i]:$file['tmp_name']),'size'=>(int)(is_array($file['size'])?$file['size'][$i]:$file['size']),'error'=>(int)(is_array($file['error'])?$file['error'][$i]:$file['error'])];
            if ($one['error']===UPLOAD_ERR_NO_FILE) continue;
            $ext=strtolower(pathinfo($one['name'],PATHINFO_EXTENSION)); if (!in_array($ext,['jpg','jpeg','png','webp'],true)) { $media=$this->ctx->service(MediaService::class); foreach ($ids as $storedId) $media->delete($storedId); return [[], 'Property photos must be JPG, PNG, or WebP images.']; }
            $media=$this->ctx->service(MediaService::class); [$id,$error]=$media->store($one,null,true,'Landscaping Estimates');
            if ($error!==null||$id===null) { foreach ($ids as $storedId) $media->delete($storedId); return [[], $error??'A property photo could not be saved.']; }
            $ids[]=$id;
        }
        return [$ids,null];
    }

    public function estimatePhoto(Request $r): Response
    {
        if ($forbidden = $this->guard('landscaping.estimates')) return $forbidden;
        $id=(int)($r->attributes['id']??0); $allowed=false;
        foreach ($this->db()->fetchAll('SELECT photo_media_ids FROM `'.$this->db()->table('landscaping_estimates').'` WHERE photo_media_ids IS NOT NULL') as $row) {
            $ids=json_decode((string)$row['photo_media_ids'],true);
            if (is_array($ids) && in_array($id,array_map('intval',$ids),true)) { $allowed=true; break; }
        }
        $media=$this->ctx->service(MediaService::class)->find($id);
        if (!$allowed || !$media || (int)$media['is_private']!==1 || (string)$media['folder']!=='Landscaping Estimates') return Response::notFound();
        $path=$this->ctx->service(MediaService::class)->pathFor($media); if (!is_file($path)) return Response::notFound();
        return Response::html('')->header('Content-Type',(string)$media['mime'])->header('Content-Length',(string)filesize($path))->header('X-Content-Type-Options','nosniff')->header('Content-Disposition','inline; filename="'.preg_replace('/[^A-Za-z0-9._-]/','_', (string)$media['original_name']).'"')->header('Cache-Control','private, no-store')->setBody((string)file_get_contents($path));
    }

    private function rows(string $table,string $where='1=1',?int $limit=null): array { return $this->db()->fetchAll('SELECT * FROM `'.$this->db()->table($table).'` WHERE '.$where.' ORDER BY sort_order, id'.($limit!==null?' LIMIT '.$limit:'')); }
    private function uniqueSlug(string $table,string $name): string { $base=strtolower(trim((string)preg_replace('/[^a-z0-9]+/i','-',$name),'-'))?:'item'; $slug=$base;$n=2; while ($this->db()->fetchColumn('SELECT COUNT(*) FROM `'.$this->db()->table($table).'` WHERE slug = ?',[$slug])) $slug=$base.'-'.$n++; return $slug; }
    private function guard(string $cap): ?Response { $auth=$this->ctx->service(AuthService::class);$user=$auth->user();return $user===null||!$auth->has($user,$cap)?Response::html('<h1>403 — Forbidden</h1>',403):null; }
    private function db(): Connection { return $this->ctx->service(Connection::class); }
    private function settings(): SettingsRepository { return $this->ctx->service(SettingsRepository::class); }
}
