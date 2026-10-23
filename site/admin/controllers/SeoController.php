<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Content\RedirectService;
use Core\Http\Request;
use Core\Http\Response;
use Core\Seo\NotFoundLog;

final class SeoController extends Controller
{
    public function index(Request $r): Response
    {
        $u = $this->user();
        if (!$u || !$this->auth()->has($u, 'navigation.manage')) return $this->forbidden();
        $log = $this->c->get(NotFoundLog::class);
        if ($r->isPost()) {
            if ($r->string('action') === 'redirect') {
                [$ok, $error] = (new RedirectService($this->db()))->save(
                    null, $r->string('source'), $r->string('target'), 301, $u->id
                );
                $this->flash($ok ? 'ok' : 'error', $error ?? 'Permanent redirect created.');
            } elseif ($r->string('action') === 'clear') {
                $count = $log->clear();
                $this->flash('ok', "Cleared {$count} logged path(s).");
            } elseif ($r->string('action') === 'import' && isset($r->files['csv'])) {
                $result=$this->importRedirects($r->files['csv'],$u->id);
                $this->flash($result['errors']?'error':'ok',"Imported {$result['saved']} redirect(s); {$result['errors']} row(s) skipped.");
            }
            return $this->redirect($r, '/admin/seo');
        }
        return $this->page($r, 'seo/index', [
            'missing' => $log->top(),
            'pages' => $this->db()->fetchAll(
                'SELECT slug, title FROM `' . $this->db()->table('pages')
                . "` WHERE deleted_at IS NULL AND status = 'published' ORDER BY title"
            ),
            'redirects' => (new RedirectService($this->db()))->all(),
            'audits' => array_map(function(array $page):array{$page['issues']=(new \Core\Seo\AccessibilityAuditor())->audit($page);return $page;},$this->db()->fetchAll('SELECT * FROM `'.$this->db()->table('pages')."` WHERE deleted_at IS NULL AND status='published' ORDER BY title")),
        ], 'SEO');
    }

    public function export(Request $r): Response
    {
        $u=$this->user();if(!$u||!$this->auth()->has($u,'navigation.manage'))return $this->forbidden();
        $fp=fopen('php://temp','w+');fputcsv($fp,['source','target','status_code']);foreach((new RedirectService($this->db()))->all() as $row)fputcsv($fp,[$row['source'],$row['target'],$row['status_code']]);rewind($fp);$csv=(string)stream_get_contents($fp);fclose($fp);
        return Response::html('')->header('Content-Type','text/csv; charset=utf-8')->header('Content-Disposition','attachment; filename="redirects.csv"')->setBody($csv);
    }

    private function importRedirects(array $file,int $userId):array
    {
        if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK||($file['size']??0)>2*1024*1024)return['saved'=>0,'errors'=>1];
        $fp=fopen((string)$file['tmp_name'],'rb');if(!$fp)return['saved'=>0,'errors'=>1];$saved=0;$errors=0;$first=true;$service=new RedirectService($this->db());
        while(($row=fgetcsv($fp,2000))!==false){if($first&&strtolower((string)($row[0]??''))==='source'){$first=false;continue;}$first=false;[$ok]= $service->save(null,(string)($row[0]??''),(string)($row[1]??''),(int)($row[2]??301),$userId);$ok?$saved++:$errors++;if($saved+$errors>=2000)break;}fclose($fp);return compact('saved','errors');
    }
}
