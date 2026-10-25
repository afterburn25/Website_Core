<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Business\BusinessProfile;
use Core\Http\Request;
use Core\Http\Response;

final class BusinessController extends Controller
{
    public function index(Request $r): Response
    {
        if (!$this->user() || !$this->auth()->has($this->user(), 'settings.manage')) return $this->forbidden();
        $profile = $this->c->get(BusinessProfile::class); $errors = [];
        if ($r->isPost()) {
            $errors = $profile->save($r->post);
            if ($errors === []) {
                $this->audit('business.profile.updated');
                $this->flash('success', 'Business profile saved. Modules now use the updated contact information.');
                return $this->redirect($r, '/admin/business');
            }
        }
        return $this->page($r, 'business/index', ['profile'=>$r->isPost()?$r->post:$profile->get(),'errors'=>$errors], 'Business profile');
    }
}
