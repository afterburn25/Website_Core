<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\Modules\ModuleRegistry;

final class ResourceController extends Controller
{
    public function index(Request $r): Response
    {
        $resource = $this->resource($r);
        if (!$resource) return $this->notFound();
        if (!$this->user() || !$this->auth()->has($this->user(), (string)$resource['capability'])) return $this->forbidden();
        $id = max(0, (int)$r->input('id', 0));
        $search = mb_substr(trim($r->string('q')), 0, 190);
        $page = max(1, (int)$r->input('page', 1));
        $perPage = 25;
        $error = null;
        if ($r->isPost()) {
            $action = $r->string('action', 'save');
            if ($action === 'delete') {
                if ($id < 1 || !is_callable($resource['delete'])) $error = 'This record cannot be deleted.';
                else {
                    $result = ($resource['delete'])($id);
                    if ($result === true || $result === null) {
                        $this->audit('module.resource.deleted', ['package'=>$resource['package'],'resource'=>$resource['key'],'id'=>$id]);
                        $this->flash('success', 'Record deleted.');
                        return $this->redirect($r, $resource['route']);
                    }
                    $error = is_string($result) ? $result : 'The record could not be deleted.';
                }
            } elseif ($action === 'reorder') {
                [$ids, $error] = $this->recordIds($r->post['ordered_ids'] ?? null, 'There are no records to reorder.');
                if ($error === null && !is_callable($resource['reorder'] ?? null)) {
                    $error = 'This resource cannot be reordered.';
                }
                if ($error === null) {
                    $result = ($resource['reorder'])($ids);
                    $error = $this->actionError($result, 'The record order could not be saved.');
                    if ($error === null) {
                        $this->audit('module.resource.reordered', [
                            'package'=>$resource['package'],'resource'=>$resource['key'],'ids'=>$ids,
                        ]);
                        $this->flash('success', 'Record order saved.');
                        return $this->redirect($r, $this->listRoute($resource, $search, $page));
                    }
                }
            } elseif ($action === 'bulk') {
                $bulkAction = $r->string('bulk_action');
                [$ids, $error] = $this->recordIds($r->post['selected_ids'] ?? null, 'Select at least one record.');
                if ($error === null && (!is_callable($resource['bulk'] ?? null)
                    || !array_key_exists($bulkAction, (array)($resource['bulk_actions'] ?? [])))) {
                    $error = 'Select a valid bulk action.';
                }
                if ($error === null) {
                    $result = ($resource['bulk'])($ids, $bulkAction);
                    $error = $this->actionError($result, 'The bulk action could not be completed.');
                    if ($error === null) {
                        $this->audit('module.resource.bulk', [
                            'package'=>$resource['package'],'resource'=>$resource['key'],
                            'action'=>$bulkAction,'ids'=>$ids,
                        ]);
                        $this->flash('success', 'Bulk action completed.');
                        return $this->redirect($r, $this->listRoute($resource, $search, $page));
                    }
                }
            } elseif ($action === 'save') {
                [$data, $error] = $this->validated($resource['fields'], $r->post);
                if ($error === null) {
                    $result = ($resource['save'])($id > 0 ? $id : null, $data);
                    [$savedId, $saveError] = is_array($result) ? [$result[0]??null,$result[1]??null] : [$result,null];
                    if ($saveError === null && is_numeric($savedId)) {
                        $this->audit('module.resource.saved', ['package'=>$resource['package'],'resource'=>$resource['key'],'id'=>(int)$savedId]);
                        $this->flash('success', 'Record saved.');
                        return $this->redirect($r, $resource['route'] . '&id=' . (int)$savedId);
                    }
                    $error = is_string($saveError) ? $saveError : 'The record could not be saved.';
                }
            } else {
                $error = 'Select a valid resource action.';
            }
        }
        $record = $id > 0 ? ($resource['find'])($id) : null;
        if ($id > 0 && !is_array($record)) return $this->notFound();
        $result = ($resource['list'])($search, $page, $perPage);
        $rows = is_array($result) && isset($result['rows']) ? (array)$result['rows'] : (array)$result;
        $total = is_array($result) && isset($result['total']) ? (int)$result['total'] : count($rows);
        return $this->page($r, 'resources/index', compact('resource','record','rows','total','search','page','perPage','error'), (string)$resource['label']);
    }

    private function resource(Request $r): ?array
    {
        $key = (string)($r->attributes['owner']??'') . '/' . (string)($r->attributes['resource']??'');
        return $this->c->get(ModuleRegistry::class)->adminResources()[$key] ?? null;
    }

    /** @return array{0:array,1:?string} */
    private function validated(array $fields, array $input): array
    {
        $data = [];
        foreach ($fields as $field) {
            $name=(string)$field['name']; $type=(string)($field['type']??'text');
            $value=$type==='checkbox' ? (!empty($input[$name]) ? 1 : 0) : trim((string)($input[$name]??''));
            if (!empty($field['required']) && $value==='') return [$data, (string)$field['label'] . ' is required.'];
            if ($type==='email' && $value!=='' && !filter_var($value,FILTER_VALIDATE_EMAIL)) return [$data, (string)$field['label'] . ' must be a valid email address.'];
            if ($type==='url' && $value!=='' && !filter_var($value,FILTER_VALIDATE_URL)) return [$data, (string)$field['label'] . ' must be a valid URL.'];
            if ($type==='number' && $value!=='' && !is_numeric($value)) return [$data, (string)$field['label'] . ' must be a number.'];
            if ($type==='date' && $value!=='' && !preg_match('/^\d{4}-\d{2}-\d{2}$/',$value)) return [$data, (string)$field['label'] . ' must be a valid date.'];
            if ($type==='select' && $value!=='' && !array_key_exists($value,(array)($field['options']??[]))) return [$data, 'Select a valid ' . strtolower((string)$field['label']) . '.'];
            $data[$name]=is_string($value)?mb_substr(strip_tags($value),0,$type==='textarea'?12000:1000):$value;
        }
        return [$data,null];
    }

    /** @return array{0:list<int>,1:?string} */
    private function recordIds(mixed $input, string $emptyError): array
    {
        if (!is_array($input) || $input === []) return [[], $emptyError];
        if (count($input) > 100) return [[], 'No more than 100 records may be changed at once.'];
        $ids = [];
        foreach ($input as $value) {
            if (!is_int($value) && !is_string($value)) return [[], 'Select valid records.'];
            $id = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
            if ($id === false) return [[], 'Select valid records.'];
            $ids[(int)$id] = (int)$id;
        }
        return [array_values($ids), [] === $ids ? $emptyError : null];
    }

    private function actionError(mixed $result, string $fallback): ?string
    {
        if ($result === true || $result === null) return null;
        return is_string($result) && trim($result) !== '' ? trim($result) : $fallback;
    }

    private function listRoute(array $resource, string $search, int $page): string
    {
        $query = [];
        if ($search !== '') $query['q'] = $search;
        if ($page > 1) $query['page'] = $page;
        return (string)$resource['route'] . ($query === [] ? '' : '&' . http_build_query($query, '', '&', PHP_QUERY_RFC3986));
    }

    private function notFound(): Response
    {
        return Response::html(\Core\Branding\StatusPage::render(404,'Resource not found','This module resource is not available.', '', (string)$this->settings()->get('site.name','Website')),404);
    }
}
