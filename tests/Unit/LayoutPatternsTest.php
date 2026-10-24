<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Content\LayoutPatterns;
use Core\Database\Connection;
use Core\Database\Migrator;
use PHPUnit\Framework\TestCase;

final class LayoutPatternsTest extends TestCase
{
    public function testCapturesAndAppliesARevisionSafePattern(): void
    {
        $db=Connection::sqlite(); (new Migrator($db, WC_ROOT.'/database/migrations'))->migrate();
        $source=(int)$db->insert('pages',['slug'=>'source','title'=>'Source','body'=>'','blocks_json'=>'[{"id":"a","type":"hero","data":{"heading":"Hi"}}]','status'=>'draft','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        $target=(int)$db->insert('pages',['slug'=>'target','title'=>'Target','body'=>'Old','blocks_json'=>'[]','status'=>'published','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);
        $patterns=new LayoutPatterns($db); [$id,$error]=$patterns->saveFromPage('Landing',$source,1);
        self::assertNull($error); self::assertNotNull($id); self::assertCount(1,$patterns->all());
        self::assertNull($patterns->apply($id,$target,1));
        $page=$db->fetch('SELECT * FROM `'.$db->table('pages').'` WHERE id=?',[$target]);
        self::assertSame('draft',$page['status']); self::assertStringContainsString('hero',$page['blocks_json']);
        self::assertSame(1,(int)$db->fetchColumn('SELECT COUNT(*) FROM `'.$db->table('page_revisions').'` WHERE page_id=?',[$target]));
    }
}
