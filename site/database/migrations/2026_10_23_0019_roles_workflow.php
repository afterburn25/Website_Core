<?php
declare(strict_types=1);
use Core\Database\Connection;use Core\Database\Migration;
return new class extends Migration{
 public function id():string{return '2026_10_23_0019';}public function description():string{return 'Custom roles and editorial review workflow';}
 public function up(Connection $db):void{$s=$db->driver()==='sqlite';$now=$s?"TEXT NOT NULL DEFAULT (datetime('now'))":'TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP';$engine=$s?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';$r=$db->table('custom_roles');$db->pdo()->exec("CREATE TABLE IF NOT EXISTS `{$r}` (slug VARCHAR(20) NOT NULL PRIMARY KEY,label VARCHAR(80) NOT NULL,capabilities_json TEXT NOT NULL,created_at {$now}){$engine}");$p=$db->table('pages');foreach(['review_status'=>"VARCHAR(20) NOT NULL DEFAULT 'draft'",'review_note'=>'TEXT NULL','reviewed_by'=>($s?'INTEGER':'BIGINT UNSIGNED').' NULL','reviewed_at'=>($s?'TEXT':'TIMESTAMP').' NULL DEFAULT NULL'] as $name=>$type){$exists=$s?in_array($name,array_column($db->fetchAll("PRAGMA table_info(`{$p}`)"),'name'),true):(bool)$db->fetchColumn('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[$p,$name]);if(!$exists)$db->pdo()->exec("ALTER TABLE `{$p}` ADD COLUMN `{$name}` {$type}");}}
};
