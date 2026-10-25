<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Accounts\CustomerAuth;
use Core\Commerce\CartService;
use Core\Commerce\OrderService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleRegistry;
use Core\Security\RateLimiter;
use Core\View\View;

final class AccountController extends Controller
{
    public function login(Request $r): Response
    {
        if (!$this->feature()) return $this->missing($r);
        if ($this->customer()) return Response::redirect($r->routeUrl('/account'));
        $error=null;
        if ($r->isPost()) {
            if (!$this->limit($r, 'login', 10)) $error='Too many attempts. Please wait and try again.';
            else $error=$this->authCustomer()->login($this->package(), $r->string('email'), (string)$r->input('password',''));
            if ($error===null) return Response::redirect($r->routeUrl('/account'));
        }
        return $this->render($r,'commerce/account-login',['error'=>$error]);
    }

    public function register(Request $r): Response
    {
        $feature=$this->feature(); if (!$feature) return $this->missing($r);
        if (($feature['options']['registration']??true)!==true) return $this->missing($r);
        if ($this->customer()) return Response::redirect($r->routeUrl('/account'));
        $error=null;
        if ($r->isPost()) {
            if ((string)$r->input('password','') !== (string)$r->input('password_confirmation','')) $error='Passwords do not match.';
            elseif (!$this->limit($r,'register',5)) $error='Too many attempts. Please wait and try again.';
            else [, $error]=$this->authCustomer()->register($this->package(),$r->post);
            if ($error===null) return Response::redirect($r->routeUrl('/account'));
        }
        return $this->render($r,'commerce/account-register',['error'=>$error,'input'=>$r->post]);
    }

    public function profile(Request $r): Response
    {
        if (!$this->feature()) return $this->missing($r);
        $user=$this->customer(); if (!$user) return Response::redirect($r->routeUrl('/account/login'));
        $error=null;
        if ($r->isPost()) {
            $error=$this->authCustomer()->update($this->package(),(int)$user['id'],$r->post);
            if ($error===null) { $_SESSION['_wc_store_flash']='Profile updated.'; return Response::redirect($r->routeUrl('/account')); }
        }
        $orders=[];
        if ($this->c->get(ModuleRegistry::class)->supportsFeature('commerce.orders')) {
            $orders=$this->c->get(OrderService::class)->forCustomer($this->package(),(int)$user['id'],(string)$user['email']);
        }
        return $this->render($r,'commerce/account-profile',['customer'=>$user,'orders'=>$orders,'error'=>$error]);
    }

    public function logout(Request $r): Response
    {
        if (!$this->feature()) return $this->missing($r);
        $this->authCustomer()->logout();
        return Response::redirect($r->routeUrl('/'));
    }

    public function forgot(Request $r): Response
    {
        if (!$this->feature()) return $this->missing($r);
        $sent=false;
        if ($r->isPost()) {
            if ($this->limit($r,'forgot',4)) {
                $scheme=$r->isSecure()?'https':'http';
                $this->authCustomer()->requestReset($this->package(),$r->string('email'),$scheme.'://'.$r->host().$r->routeUrl('/account/reset',['token'=>'']));
            }
            $sent=true;
        }
        return $this->render($r,'commerce/account-forgot',['sent'=>$sent]);
    }

    public function reset(Request $r): Response
    {
        if (!$this->feature()) return $this->missing($r);
        $token=$r->string('token'); $error=null; $done=false;
        if ($r->isPost()) {
            if ((string)$r->input('password','') !== (string)$r->input('password_confirmation','')) $error='Passwords do not match.';
            else $error=$this->authCustomer()->reset($this->package(),$token,(string)$r->input('password',''));
            $done=$error===null;
        }
        return $this->render($r,'commerce/account-reset',compact('token','error','done'));
    }

    private function render(Request $r,string $view,array $data): Response
    {
        $flash=$_SESSION['_wc_store_flash']??null; unset($_SESSION['_wc_store_flash']);
        $registry=$this->c->get(ModuleRegistry::class); $commerce=$registry->supportsFeature('commerce.catalog');
        $data += ['basePath'=>$r->routeBase(),'siteBasePath'=>rtrim($r->basePath,'/'),'siteName'=>(string)$this->settings()->get('site.name','Website'),
            'storeName'=>'Customer account','csrf'=>$this->csrf(),'flash'=>$flash,'cartCount'=>$commerce?$this->c->get(CartService::class)->count():0,
            'commerceAvailable'=>$commerce,'accountAvailable'=>true,'customer'=>$this->customer()];
        return Response::html($this->c->get(View::class)->renderInLayout('admin:'.$view,'admin:commerce/store-layout',$data));
    }

    private function feature(): ?array { return $this->c->get(ModuleRegistry::class)->feature('accounts.customers'); }
    private function package(): string { return (string)($this->feature()['package']??''); }
    private function customer(): ?array { return $this->feature()?$this->authCustomer()->user($this->package()):null; }
    private function authCustomer(): CustomerAuth { return $this->c->get(CustomerAuth::class); }
    private function limit(Request $r,string $action,int $max): bool { return $this->c->get(RateLimiter::class)->attempt('customer:'.$action.':'.$r->ip(),$max,900); }
    private function missing(Request $r): Response { return Response::html(\Core\Branding\StatusPage::render(404,'Accounts not available','This website does not use public customer accounts.',$r->routeBase(),(string)$this->settings()->get('site.name','Website')),404); }
}
