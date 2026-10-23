<?php
declare(strict_types=1);
use Core\Database\Connection;use Core\Database\Migration;
return new class extends Migration{
 public function id():string{return '2026_10_23_0018';}public function description():string{return 'Configured languages and translated page variants';}
 public function up(Connection $db):void{$s=$db->driver()==='sqlite';$auto=$s?'INTEGER PRIMARY KEY AUTOINCREMENT':'BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY';$int=$s?'INTEGER':'BIGINT UNSIGNED';$now=$s?"TEXT NOT NULL DEFAULT (datetime('now'))":'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';$engine=$s?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';$l=$db->table('site_languages');$t=$db->table('page_translations');
  $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$l}` (locale VARCHAR(12) NOT NULL PRIMARY KEY,name VARCHAR(80) NOT NULL,active TINYINT NOT NULL DEFAULT 1,created_at {$now}){$engine}");
  $db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$t}` (id {$auto},page_id {$int} NOT NULL,locale VARCHAR(12) NOT NULL,slug VARCHAR(190) NOT NULL,title VARCHAR(190) NOT NULL,body TEXT NULL,blocks_json TEXT NULL,meta_title VARCHAR(190) NULL,meta_description VARCHAR(500) NULL,schema_json TEXT NULL,created_at {$now},updated_at {$now},UNIQUE (page_id,locale),UNIQUE (locale,slug)){$engine}");
 }
};
