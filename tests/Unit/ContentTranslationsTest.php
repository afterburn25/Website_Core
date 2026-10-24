<?php
declare(strict_types=1);
namespace Tests\Unit;
use Core\Database\Connection;use Core\Database\Migrator;use Core\I18n\ContentTranslations;use PHPUnit\Framework\TestCase;
final class ContentTranslationsTest extends TestCase{
 public function testSavesAndResolvesPublishedTranslation():void{$db=Connection::sqlite();(new Migrator($db,WC_ROOT.'/database/migrations'))->migrate();$page=(int)$db->insert('pages',['slug'=>'about','title'=>'About','body'=>'Hello','blocks_json'=>'[]','status'=>'published','created_at'=>date('Y-m-d H:i:s'),'updated_at'=>date('Y-m-d H:i:s')]);$svc=new ContentTranslations($db);self::assertNull($svc->addLanguage('fr','Français'));self::assertNull($svc->save($page,'fr',['title'=>'À propos','slug'=>'a-propos','body'=>'Bonjour','blocks_json'=>'[]','meta_title'=>'À propos','meta_description'=>'Présentation','schema_json'=>null]));$translated=$svc->findPublished('fr','a-propos');self::assertSame('À propos',$translated['title']);self::assertSame('Bonjour',$translated['body']);self::assertCount(1,$svc->alternates($page));}
 public function testRejectsInvalidLocale():void{$db=Connection::sqlite();(new Migrator($db,WC_ROOT.'/database/migrations'))->migrate();self::assertNotNull((new ContentTranslations($db))->addLanguage('../fr','Bad'));}
}
