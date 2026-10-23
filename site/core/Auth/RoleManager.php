<?php
declare(strict_types=1);
namespace Core\Auth;
use Core\Database\Connection;
final class RoleManager{
 public function __construct(private Connection $db,private Permissions $permissions){}
 public function all():array{return $this->db->fetchAll('SELECT * FROM `'.$this->db->table('custom_roles').'` ORDER BY label');}
 public function save(string $slug,string $label,array $capabilities):?string{$slug=strtolower(trim(preg_replace('/[^a-z0-9_-]+/','-',$slug),'-'));$label=trim(strip_tags($label));if($slug===''||strlen($slug)>20||$label==='')return'Role slug and label are required.';if(in_array($slug,['owner','admin','editor'],true))return'Built-in roles cannot be replaced.';$allowed=array_flip($this->permissions->allCapabilities());$caps=array_values(array_unique(array_filter(array_map('strval',$capabilities),fn($c)=>isset($allowed[$c]))));$data=['label'=>mb_substr($label,0,80),'capabilities_json'=>json_encode($caps,JSON_UNESCAPED_SLASHES)];$exists=$this->db->fetchColumn('SELECT COUNT(*) FROM `'.$this->db->table('custom_roles').'` WHERE slug=?',[$slug]);if($exists)$this->db->update('custom_roles',$data,'slug = ?',[$slug]);else{$data=['slug'=>$slug]+$data+['created_at'=>date('Y-m-d H:i:s')];$this->db->insert('custom_roles',$data);}return null;}
 public function delete(string $slug):?string{if($this->db->fetchColumn('SELECT COUNT(*) FROM `'.$this->db->table('admins').'` WHERE role=?',[$slug]))return'Assign affected administrators to another role first.';$this->db->run('DELETE FROM `'.$this->db->table('custom_roles').'` WHERE slug=?',[$slug]);return null;}
}
