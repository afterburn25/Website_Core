<?php
declare(strict_types=1);
namespace Admin\Controllers;
use Core\Analytics\AnalyticsService;
use Core\Http\Request;
use Core\Http\Response;

final class AnalyticsPublicController extends Controller
{
    public function heartbeat(Request $r): Response
    {
        if(strlen($r->rawBody)>1024)return new Response('',204);
        try{$this->c->get(AnalyticsService::class)->heartbeat($r);}catch(\Throwable $e){error_log('[website-core] analytics heartbeat: '.$e->getMessage());}
        return (new Response('',204))->header('Cache-Control','no-store')->header('X-Robots-Tag','noindex, nofollow')->header('Referrer-Policy','no-referrer');
    }
}
