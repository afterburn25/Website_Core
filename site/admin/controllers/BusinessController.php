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
            $profileInput = $r->post;
            foreach (['service_area_center_city', 'service_area_center_region', 'service_area_center_country', 'service_area_radius_miles'] as $field) {
                unset($profileInput[$field]);
            }
            $errors = $profile->save($profileInput);
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

    public function serviceArea(Request $r): Response
    {
        if (!$this->user() || !$this->auth()->has($this->user(), 'settings.manage')) return $this->forbidden();
        $profile = $this->c->get(BusinessProfile::class);
        $areaService = $this->c->get(ServiceAreaService::class);
        $feature = $this->c->get(ModuleRegistry::class)->feature('business.service_area');
        $areaPackage = (string) ($feature['package'] ?? '');
        if ($areaPackage === '') return $this->redirect($r, '/admin/business');

        $providers = $areaService->providers($areaPackage);
        $values = $profile->get();
        $errors = [];
        if ($r->isPost()) {
            $input = [
                'service_area_center_city' => $r->string('service_area_center_city'),
                'service_area_center_region' => $r->string('service_area_center_region'),
                'service_area_center_country' => $r->string('service_area_center_country'),
                'service_area_radius_miles' => $r->string('service_area_radius_miles'),
            ];
            $values = array_replace($values, $input);
            [$configured, $areaError] = $areaService->configureRegistered($areaPackage, [
                'city' => $input['service_area_center_city'],
                'region' => $input['service_area_center_region'],
                'country' => $input['service_area_center_country'],
                'radius_miles' => $input['service_area_radius_miles'],
            ], $r->string('service_area_provider'));
            if (!$configured) $errors[] = (string) $areaError;
            if ($errors === []) {
                $this->audit('business.service_area.updated');
                $this->flash('success', 'Geo service area saved and verified.');
                return $this->redirect($r, '/admin/service-area');
            }
        }

        return $this->page($r, 'business/service-area', [
            'profile' => $values,
            'errors' => $errors,
            'serviceAreaProviders' => $providers,
            'serviceAreaState' => $areaService->profile(),
        ], 'Geo service area');
    }
}
