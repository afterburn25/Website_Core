<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;

final class PrivacyController extends Controller
{
    public function index(Request $r): Response
    {
        $u=$this->user(); if(!$u||!$this->auth()->has($u,'settings.manage')) return $this->forbidden();
        if($r->isPost()){
            $action=$r->string('action');
            if($action==='settings'){
                $provider=in_array($r->string('provider'),['none','ga4','plausible'],true)?$r->string('provider'):'none';
                $id=$r->string('site_id');
                if(($provider==='ga4'&&!preg_match('/^G-[A-Z0-9]+$/i',$id))||($provider==='plausible'&&!preg_match('/^[A-Za-z0-9.-]+$/',$id))){$this->flash('error','Enter a valid analytics measurement ID or domain.');return $this->redirect($r,'/admin/privacy');}
                $this->settings()->set('privacy.banner_enabled',$r->string('banner_enabled')==='1');
                $this->settings()->set('analytics.provider',$provider);$this->settings()->set('analytics.site_id',$id);
                $this->audit('privacy.settings_saved',['analytics_provider'=>$provider,'banner_enabled'=>$r->string('banner_enabled')==='1']);
                $this->flash('ok','Privacy and analytics settings saved.');
            }elseif($action==='complete'){
                $this->db()->update('privacy_requests',['status'=>'completed','completed_at'=>date('Y-m-d H:i:s')],'id = ?',[(int)$r->string('id')]);
                $this->audit('privacy.request_completed',['request'=>(int)$r->string('id')]);
                $this->flash('ok','Privacy request marked completed.');
            }
            return $this->redirect($r,'/admin/privacy');
        }
        return $this->page($r,'privacy/index',['requests'=>$this->db()->fetchAll('SELECT * FROM `'.$this->db()->table('privacy_requests').'` ORDER BY id DESC LIMIT 200'),'privacy'=>[
            'banner_enabled'=>(bool)$this->settings()->get('privacy.banner_enabled',true),'provider'=>(string)$this->settings()->get('analytics.provider','none'),'site_id'=>(string)$this->settings()->get('analytics.site_id','')]],'Privacy');
    }

    public function request(Request $r): Response
    {
        $message='';
        if($r->isPost()){
            if(!$this->csrf()->validate($r)) return Response::text('Invalid CSRF token.',419);
            if($r->string('website')!=='') return Response::html($this->publicForm($r,'Thank you.'));
            $limiter=$this->c->get(\Core\Security\RateLimiter::class);
            if(!$limiter->allow('privacy:'.hash('sha256',$r->ip()),3,3600)) return Response::html($this->publicForm($r,'Please wait before sending another request.'),429);
            $type=in_array($r->string('request_type'),['access','delete','correct'],true)?$r->string('request_type'):'access';
            $email=strtolower($r->string('email'));
            if(!filter_var($email,FILTER_VALIDATE_EMAIL)) $message='Enter a valid email address.';
            else{$this->db()->insert('privacy_requests',['request_type'=>$type,'email'=>$email,'details'=>mb_substr(strip_tags($r->string('details')),0,5000),'ip_hash'=>hash('sha256',$r->ip()),'status'=>'new','created_at'=>date('Y-m-d H:i:s')]);$message='Your privacy request has been received. The site owner will verify your identity before making changes.';}
        }
        return Response::html($this->publicForm($r,$message))->header('Cache-Control','private, no-store')->header('X-Robots-Tag','noindex');
    }

    public function export(Request $r): Response
    {
        $u=$this->user(); if(!$u||!$this->auth()->has($u,'settings.manage')) return $this->forbidden();
        $rows=$this->db()->fetchAll('SELECT id,request_type,email,details,status,created_at,completed_at FROM `'.$this->db()->table('privacy_requests').'` ORDER BY id DESC');
        $stream=fopen('php://temp','w+'); fputcsv($stream,['id','request_type','email','details','status','created_at','completed_at']);
        foreach($rows as $row) fputcsv($stream,array_values($row)); rewind($stream); $csv=(string)stream_get_contents($stream); fclose($stream);
        return Response::html('')->header('Content-Type','text/csv; charset=utf-8')->header('Content-Disposition','attachment; filename="privacy-requests.csv"')->setBody($csv);
    }

    private function publicForm(Request $r,string $message): string
    {
        $e=fn(string $v)=>htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');$action=$e($r->routeUrl('/privacy/request'));$home=$e($r->routeUrl('/'));
        return '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Privacy request</title><style>body{margin:0;background:#071722;color:#e8f5f3;font:16px/1.6 system-ui}.wrap{max-width:680px;margin:8vh auto;padding:2rem}.card{background:#102733;border:1px solid #ffffff1f;border-radius:18px;padding:2rem;box-shadow:0 30px 80px #0008}label{display:block;margin-top:1rem;font-weight:700}input,select,textarea{width:100%;box-sizing:border-box;padding:.8rem;border-radius:8px;border:1px solid #ffffff2b;background:#071722;color:white}button{margin-top:1rem;padding:.8rem 1rem;border:0;border-radius:8px;background:#5eead4;color:#042f2e;font-weight:800}.notice{padding:.8rem;background:#0f766e44;border-radius:8px}a{color:#99f6e4}.trap{position:absolute;left:-9999px}</style></head><body><main class="wrap"><a href="'.$home.'">← Return to site</a><div class="card"><h1>Privacy request</h1><p>Ask to access, correct, or delete personal information connected with this email address.</p>'.($message!==''?'<p class="notice">'.$e($message).'</p>':'').'<form method="post" action="'.$action.'">'.$this->csrf()->field().'<div class="trap"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div><label>Request<select name="request_type"><option value="access">Access my data</option><option value="correct">Correct my data</option><option value="delete">Delete my data</option></select></label><label>Email<input type="email" name="email" required></label><label>Details<textarea name="details" rows="5"></textarea></label><button>Send request</button></form></div></main></body></html>';
    }
}
