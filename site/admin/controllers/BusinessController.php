<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Business\BusinessProfile;
use Core\Business\ServiceAreaService;
use Core\Modules\ModuleRegistry;
use Core\Http\Request;
use Core\Http\Response;

final class BusinessController extends Controller
{
    public function index(Request $r): Response
    {
        if (!$this->user() || !$this->auth()->has($this->user(), 'settings.manage')) return $this->forbidden();
        $profile = $this->c->get(BusinessProfile::class); $errors = [];
        $feature = $this->c->get(ModuleRegistry::class)->feature('business.service_area');
        $areaService = $this->c->get(ServiceAreaService::class);
        $areaPackage = (string) ($feature['package'] ?? '');
        $providers = $areaPackage !== '' ? $areaService->providers($areaPackage) : [];
        if ($r->isPost()) {
            $errors = $profile->save($r->post);
            if ($errors === [] && $areaPackage !== ''
                && ($r->string('service_area_center_city') !== '' || $r->string('service_area_radius_miles') !== '')) {
                [$configured, $areaError] = $areaService->configureRegistered($areaPackage, [
                    'city' => $r->string('service_area_center_city'),
                    'region' => $r->string('service_area_center_region'),
                    'country' => $r->string('service_area_center_country'),
                    'radius_miles' => $r->string('service_area_radius_miles'),
                ], $r->string('service_area_provider'));
                if (!$configured) $errors[] = (string) $areaError;
            }
            if ($errors === []) {
                $this->audit('business.profile.updated');
                $this->flash('success', 'Business profile saved. Modules now use the updated contact information.');
                return $this->redirect($r, '/admin/business');
            }
        }
        return $this->page($r, 'business/index', [
            'profile'=>$r->isPost()?$r->post:$profile->get(),'errors'=>$errors,
            'serviceAreaEnabled' => $areaPackage !== '', 'serviceAreaProviders' => $providers,
            'serviceAreaState' => $areaService->profile(),
        ], 'Business profile');
    }
}
