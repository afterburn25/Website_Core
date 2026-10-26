<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Accounts\CustomerAuth;
use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleRegistry;

final class CustomersController extends Controller
{
    public function index(Request $r): Response
    {
        $feature = $this->c->get(ModuleRegistry::class)->feature('accounts.customers');
        if (!$feature) return Response::notFound('Customer accounts are not available.');
        if (!$this->user() || !$this->auth()->has($this->user(), 'customers.manage')) return $this->forbidden();
        $auth = $this->c->get(CustomerAuth::class);
        $package = (string) $feature['package'];
        if ($r->isPost()) {
            $status = $r->string('status');
            if ($auth->setStatus($package, (int)$r->input('id', 0), $status)) {
                $this->audit('customer.status_updated', ['id'=>(int)$r->input('id',0),'status'=>$status]);
                $this->flash('ok', 'Customer access updated.');
            } else $this->flash('error', 'The customer account could not be updated.');
            return $this->redirect($r, '/admin/customers');
        }
        return $this->page($r, 'commerce/customers', ['customers'=>$auth->all($package)], 'Customers');
    }
}
