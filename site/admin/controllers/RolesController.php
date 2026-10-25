<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Auth\Permissions;
use Core\Auth\RoleManager;
use Core\Http\Request;
use Core\Http\Response;

final class RolesController extends Controller
{
    public function index(Request $r): Response
    {
        $user = $this->user();
        if (!$user || !$user->isOwner()) return $this->forbidden();
        $permissions = $this->c->get(Permissions::class);
        $roles = new RoleManager($this->db(), $permissions);
        if ($r->isPost()) {
            $action = $r->string('action');
            $slug = $r->string('slug');
            if ($action === 'delete') {
                $error = $roles->delete($slug);
            } else {
                $capabilities = $r->input('capabilities', []);
                $error = $roles->save($slug, $r->string('label'), is_array($capabilities) ? $capabilities : []);
            }
            if (!$error) $this->audit($action === 'delete' ? 'roles.deleted' : 'roles.saved', ['role' => $slug]);
            $this->flash($error ? 'error' : 'ok', $error ?? ($action === 'delete' ? 'Role deleted.' : 'Role saved.'));
            return $this->redirect($r, '/admin/roles');
        }

        $custom = $roles->all();
        $assignments = $this->db()->fetchAll(
            'SELECT role, COUNT(*) total FROM `' . $this->db()->table('admins') . '` GROUP BY role'
        );
        $assignmentMap = array_column($assignments, 'total', 'role');
        foreach ($custom as &$role) $role['assignments'] = (int) ($assignmentMap[$role['slug']] ?? 0);
        unset($role);
        return $this->page($r, 'roles/index', [
            'customRoles' => $custom,
            'capabilityCatalog' => $permissions->capabilityCatalog(),
            'builtInAssignments' => [
                'owner' => (int) ($assignmentMap['owner'] ?? 0),
                'admin' => (int) ($assignmentMap['admin'] ?? 0),
                'editor' => (int) ($assignmentMap['editor'] ?? 0),
            ],
        ], 'Roles & permissions');
    }
}
