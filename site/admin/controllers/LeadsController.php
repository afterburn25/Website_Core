<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Leads\InquiryService;
use Core\Leads\QuoteService;
use Core\Media\MediaService;
use Core\Modules\ModuleRegistry;
use Core\View\View;

final class LeadsController extends Controller
{
    public function inquiries(Request $r): Response
    {
        $package=$this->provider('crm.inquiries'); if(!$package) return $this->missing();
        if(!$this->allowed('inquiries.manage')) return $this->forbidden();
        $status=$r->string('status');$search=mb_substr($r->string('q'),0,190);$page=max(1,(int)$r->input('page',1));$perPage=25;
        $service=$this->c->get(InquiryService::class);
        return $this->page($r,'leads/inquiries',['inquiries'=>$service->all($package,$status,$search,$page,$perPage),'total'=>$service->count($package,$status,$search),'status'=>$status,'search'=>$search,'page'=>$page,'perPage'=>$perPage,'statuses'=>InquiryService::STATUSES], 'Inquiries');
    }

    public function inquiry(Request $r): Response
    {
        $package=$this->provider('crm.inquiries'); if(!$package) return $this->missing();
        if(!$this->allowed('inquiries.manage')) return $this->forbidden();
        $id=(int)$r->input('id',0);$service=$this->c->get(InquiryService::class);$error=null;
        if($r->isPost()) {
            if($r->string('action')==='note') $error=$service->addNote($id,$package,$this->user()->id,$r->string('note'));
            else if(!$service->update($id,$package,$r->string('status'),$r->string('priority'),$r->input('assigned_admin_id')!==''?(int)$r->input('assigned_admin_id'):null)) $error='The inquiry could not be updated.';
            if($error===null){$this->audit('crm.inquiry.updated',['id'=>$id]);$this->flash('success','Inquiry updated.');return $this->redirect($r,'/admin/inquiries/view&id='.$id);}
        }
        $inquiry=$service->find($id,$package);if(!$inquiry)return $this->missing();
        $admins=$this->db()->fetchAll('SELECT id,username AS name FROM `'.$this->db()->table('admins').'` WHERE disabled = 0 ORDER BY username');
        return $this->page($r,'leads/inquiry',compact('inquiry','admins','error')+['statuses'=>InquiryService::STATUSES,'priorities'=>InquiryService::PRIORITIES,'quotesEnabled'=>$this->provider('crm.quotes')!==null], 'Inquiry details');
    }

    public function quotes(Request $r): Response
    {
        $package=$this->provider('crm.quotes');if(!$package)return $this->missing();
        if(!$this->allowed('quotes.manage'))return $this->forbidden();
        return $this->page($r,'leads/quotes',['quotes'=>$this->c->get(QuoteService::class)->all($package)],'Quotes');
    }

    public function quote(Request $r): Response
    {
        $package=$this->provider('crm.quotes');if(!$package)return $this->missing();
        if(!$this->allowed('quotes.manage'))return $this->forbidden();
        $id=max(0,(int)$r->input('id',0));$service=$this->c->get(QuoteService::class);$error=null;
        if($r->isPost()) {
            $action=$r->string('action');
            if($id>0&&in_array($action,['attach','remove_attachment','reminder','document'],true)) {
                if($action==='attach')[, $error]=$service->attach($id,$package,(int)$r->input('media_id',0),$r->string('attachment_label'),$this->user()->id);
                elseif($action==='remove_attachment')$error=$service->removeAttachment((int)$r->input('attachment_id',0),$package)?null:'Attachment was not found.';
                elseif($action==='reminder')[, $error]=$service->scheduleReminder($id,$package,$r->string('reminder_due'),$r->string('reminder_recipient'),$r->string('reminder_message'),$r->string('reminder_channel','email'));
                else[, $error]=$service->snapshotDocument($id,$package,$r->string('document_kind'),$r->string('document_title'),(string)$r->input('document_html',''),$this->user()->id);
                if($error===null){$this->audit('crm.quote.'.$action,['id'=>$id]);$this->flash('success','Quote workflow updated.');return $this->redirect($r,'/admin/quotes/edit&id='.$id);}
            } else {
                $descriptions=$r->input('item_description',[]);$quantities=$r->input('item_quantity',[]);$prices=$r->input('item_price',[]);$items=[];
                if(is_array($descriptions))foreach($descriptions as $key=>$description)$items[]=['description'=>$description,'quantity'=>is_array($quantities)?($quantities[$key]??1):1,'unit_price'=>is_array($prices)?($prices[$key]??''):'' ];
                [$saved,$error,$token]=$service->save($package,$r->post,$items,$id?:null);
                if($error===null&&$saved){
                    if($action==='regenerate_link')$token=$service->regenerateResponseToken((int)$saved,$package);
                    $this->audit('crm.quote.saved',['id'=>$saved,'response_link_regenerated'=>$action==='regenerate_link']);
                    if($token)$_SESSION['_wc_quote_link']=$r->routeUrl('/quote/'.$token);
                    $this->flash('success',$token?'Quote saved and its secure customer link is ready below.':'Quote saved.');
                    return $this->redirect($r,'/admin/quotes/edit&id='.$saved);
                }
            }
        }
        $quote=$id?$service->find($id,$package):null;if($id&&!$quote)return $this->missing();
        $inquiryId=(int)$r->input('inquiry_id',0);$inquiry=$inquiryId?$this->c->get(InquiryService::class)->find($inquiryId,$this->provider('crm.inquiries')??$package):null;
        if(!$quote&&$inquiry)$quote=['inquiry_id'=>$inquiryId,'customer_name'=>$inquiry['customer_name'],'customer_email'=>$inquiry['customer_email'],'customer_phone'=>$inquiry['customer_phone'],'currency'=>'USD','status'=>'draft','discount_minor'=>0,'tax_minor'=>0,'valid_until'=>'','customer_notes'=>'','internal_notes'=>'','items'=>[]];
        $link=$_SESSION['_wc_quote_link']??null;unset($_SESSION['_wc_quote_link']);
        return $this->page($r,'leads/quote',['quote'=>$quote,'error'=>$error,'statuses'=>QuoteService::STATUSES,'customerLink'=>$link,
            'mediaItems'=>$id?$this->db()->fetchAll('SELECT id,original_name,is_private FROM `'.$this->db()->table('media').'` WHERE is_private=1 ORDER BY id DESC LIMIT 200'):[]],$quote?'Edit quote':'New quote');
    }

    public function document(Request $r): Response
    {
        $package=$this->provider('crm.quotes');if(!$package)return $this->missing();
        if(!$this->allowed('quotes.manage'))return $this->forbidden();
        $document=$this->c->get(QuoteService::class)->document((int)$r->input('id',0),$package);
        if(!$document)return $this->missing();
        $html='<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.e((string)$document['title']).'</title></head><body><main>'.(string)$document['html_snapshot'].'</main></body></html>';
        return Response::html($html)->header('Content-Security-Policy', "default-src 'none'; style-src 'unsafe-inline'; img-src 'self' data:; base-uri 'none'; form-action 'none'; frame-ancestors 'self'")
            ->header('Cache-Control','private, no-store');
    }

    public function attachment(Request $r): Response
    {
        $package=$this->provider('crm.quotes');if(!$package)return $this->missing();
        if(!$this->allowed('quotes.manage'))return $this->forbidden();
        $attachment=$this->c->get(QuoteService::class)->attachment((int)$r->input('id',0),$package);
        if(!$attachment)return $this->missing();
        $path=$this->c->get(MediaService::class)->pathFor($attachment);
        if(!is_file($path))return $this->missing();
        $name=preg_replace('/[^A-Za-z0-9._-]/','_', (string)$attachment['original_name']) ?: 'attachment';
        return Response::html('')->header('Content-Type',(string)$attachment['mime'])
            ->header('Content-Length',(string)filesize($path))
            ->header('Content-Disposition','attachment; filename="'.$name.'"')
            ->header('X-Content-Type-Options','nosniff')->header('Cache-Control','private, no-store')
            ->setBody((string)file_get_contents($path));
    }

    public function publicQuote(Request $r): Response
    {
        $package=$this->provider('crm.quotes');if(!$package)return $this->missing();
        $token=(string)($r->attributes['token']??'');$service=$this->c->get(QuoteService::class);$quote=$service->byToken($token);
        if(!$quote||$quote['owner_package']!==$package)return $this->missing();
        $message=null;if($r->isPost()){$accept=$r->string('decision')==='accept';$ok=$service->respond($token,$accept);$message=$ok?($accept?'Quote accepted. The business will contact you with the next steps.':'Quote declined. The business has been notified.'):'This quote link is no longer available.';}
        return Response::html($this->c->get(View::class)->render('admin:leads/public-quote',['quote'=>$quote,'message'=>$message,'token'=>$token,'csrf'=>$this->csrf(),'basePath'=>$r->routeBase(),'siteName'=>$this->settings()->get('site.name','Website')]));
    }

    private function provider(string $feature): ?string { return $this->c->get(ModuleRegistry::class)->feature($feature)['package']??null; }
    private function allowed(string $capability): bool { return $this->user()!==null&&$this->auth()->has($this->user(),$capability); }
    private function missing(): Response { return Response::html(\Core\Branding\StatusPage::render(404,'Not available','This website has not enabled that shared business feature.','',(string)$this->settings()->get('site.name','Website')),404); }
}
