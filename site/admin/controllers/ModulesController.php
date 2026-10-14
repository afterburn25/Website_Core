<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleRegistry;

final class ModulesController extends Controller
{
    public function index(Request $r): Response
    {
        $user = $this->user();
        if (!$user || !$this->auth()->has($user, 'modules.view')) {
            return $this->forbidden();
        }
        $packages = $this->c->get(ModuleRegistry::class)->all();
        return $this->page($r, 'modules/index', ['packages' => $packages], 'Modules');
    }
}
