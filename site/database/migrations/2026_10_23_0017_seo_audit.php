<?php
declare(strict_types=1);
use Core\Database\Connection;use Core\Database\Migration;
return new class extends Migration{
 public function id():string{return'2026_10_23_0017';}public function description():string{return'Per-page schema markup and SEO audit data';}
 public function up(Connection $db):void{$t=$db->table('pages');$s=$db->driver()==='sqlite';$exists=$s?in_array('schema_json',array_column($db->fetchAll("PRAGMA table_info(`{$t}`)"),'name'),true):(bool)$db->fetchColumn('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',[$t,'schema_json']);if(!$exists)$db->pdo()->exec("ALTER TABLE `{$t}` ADD COLUMN schema_json TEXT NULL");}
};
