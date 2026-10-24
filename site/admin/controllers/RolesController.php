<?php
declare(strict_types=1);
namespace Admin\Controllers;
use Core\Auth\Permissions;use Core\Auth\RoleManager;use Core\Http\Request;use Core\Http\Response;
final class RolesController extends Controller{
 public function index(Request $r):Response{$u=$this->user();if(!$u||!$u->isOwner())return$this->forbidden();$permissions=$this->c->get(Permissions::class);$roles=new RoleManager($this->db(),$permissions);if($r->isPost()){$action=$r->string('action');if($action==='delete')$error=$roles->delete($r->string('slug'));else{$caps=$r->input('capabilities',[]);$error=$roles->save($r->string('slug'),$r->string('label'),is_array($caps)?$caps:[]);}if(!$error)$this->audit($action==='delete'?'roles.deleted':'roles.saved',['role'=>$r->string('slug')]);$this->flash($error?'error':'ok',$error??($action==='delete'?'Role deleted.':'Role saved.'));return$this->redirect($r,'/admin/roles');}return$this->page($r,'roles/index',['customRoles'=>$roles->all(),'capabilities'=>$permissions->allCapabilities()],'Roles & permissions');}
}
