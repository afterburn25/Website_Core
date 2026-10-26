<?php
declare(strict_types=1);
namespace Tests\Unit;
use Core\Seo\AccessibilityAuditor;use PHPUnit\Framework\TestCase;
final class AccessibilityAuditorTest extends TestCase{
 public function testFindsMissingMetadataAltTextAndGenericLinks():void{$issues=(new AccessibilityAuditor())->audit(['title'=>'Hi','meta_description'=>'','body'=>'<img src="x"><a href="/x">Click here</a>','blocks_json'=>'[{"type":"image_text","data":{"media_id":1}}]']);$titles=array_column($issues,'title');self::assertContains('Meta description is missing',$titles);self::assertContains('Image is missing alt text',$titles);self::assertContains('Link text lacks context',$titles);self::assertContains('Block image is missing alt text',$titles);}
 public function testCleanPageHasNoErrors():void{$issues=(new AccessibilityAuditor())->audit(['title'=>'A useful and descriptive page title','meta_description'=>'A concise summary that explains what this page offers to visitors.','body'=>'<img src="x" alt="Chef plating dinner"><a href="/menu">View our menu</a>','blocks_json'=>'[]']);self::assertSame([],array_values(array_filter($issues,fn($i)=>$i['level']==='error')));}
 public function testFindsInvalidCanonicalSchemaAndBlockData():void{$issues=(new AccessibilityAuditor())->audit(['title'=>'A useful and descriptive page title','meta_description'=>'A concise summary that explains what this page offers to visitors.','canonical'=>'not a url','schema_json'=>'{bad','blocks_json'=>'{bad','noindex'=>1]);$titles=array_column($issues,'title');self::assertContains('Canonical URL is invalid',$titles);self::assertContains('Structured data is invalid',$titles);self::assertContains('Visual-block data is invalid',$titles);self::assertContains('Published page is hidden from search',$titles);}
}
