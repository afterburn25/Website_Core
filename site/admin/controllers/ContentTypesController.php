<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Content\ContentItems;
use Core\Http\Request;
use Core\Http\Response;

/**
 * Generated CRUD for package-declared content types. The field set a
 * package registers via ctx->contentType() is the whole contract — this
 * controller renders forms and delegates validation/storage to
 * ContentItems, so a package gets a working admin list without writing
 * its own controller.
 */
final class ContentTypesController extends Controller
{
    private function items(): ContentItems
    {
        return $this->c->get(ContentItems::class);
    }

    public function index(Request $r): Response
    {
        if (!$this->can()) {
            return $this->forbidden();
        }
        $types = [];
        foreach ($this->items()->types() as $key => $def) {
            $types[$key] = $def + ['items' => $this->items()->items($def['package'], $def['type'])];
        }
        return $this->page($r, 'content/index', ['types' => $types], 'Content Types');
    }

    public function list(Request $r): Response
    {
        if (!$this->can()) {
            return $this->forbidden();
        }
        [$def, $resp] = $this->resolve($r);
        if ($def === null) {
            return $resp;
        }
        return $this->page($r, 'content/list', [
            'def' => $def,
            'items' => $this->items()->items($def['package'], $def['type']),
        ], $def['label']);
    }

    public function edit(Request $r): Response
    {
        if (!$this->can()) {
            return $this->forbidden();
        }
        [$def, $resp] = $this->resolve($r);
        if ($def === null) {
            return $resp;
        }
        $item = null;
        $id = (int) $r->string('id');
        if ($id > 0) {
            $item = $this->items()->get($id);
            if (!$item || $item['package'] !== $def['package'] || $item['type'] !== $def['type']) {
                $this->flash('error', 'Item not found.');
                return $this->redirect($r, '/admin/content');
            }
        }
        return $this->page($r, 'content/edit', [
            'def' => $def, 'item' => $item,
            'mediaOptions' => $this->items()->mediaOptions(),
        ], ($item ? 'Edit' : 'New') . ' ' . $def['label']);
    }

    public function save(Request $r): Response
    {
        if (!$this->can()) {
            return $this->forbidden();
        }
        [$def, $resp] = $this->resolve($r);
        if ($def === null) {
            return $resp;
        }
        $id = (int) $r->string('id');
        [$ok, $err] = $this->items()->save(
            $id > 0 ? $id : null,
            $def['package'],
            $def['type'],
            $r->post,
            $this->user()->id
        );
        if ($ok) {
            $this->flash('ok', 'Saved.');
            return $this->redirect($r, $this->listPath($def));
        }
        $this->flash('error', (string) $err);
        $item = $id > 0 ? $this->items()->get($id) : null;
        return $this->page($r, 'content/edit', [
            'def' => $def, 'item' => $item, 'input' => $r->post,
            'mediaOptions' => $this->items()->mediaOptions(),
        ], ($item ? 'Edit' : 'New') . ' ' . $def['label']);
    }

    public function delete(Request $r): Response
    {
        if (!$this->can()) {
            return $this->forbidden();
        }
        [$def, $resp] = $this->resolve($r);
        if ($def === null) {
            return $resp;
        }
        $deleted = $this->items()->delete((int) $r->string('id'), $def['package'], $def['type']);
        $this->flash($deleted ? 'ok' : 'error', $deleted ? 'Deleted.' : 'Item not found.');
        return $this->redirect($r, $this->listPath($def));
    }

    /** @return array{0:?array,1:?Response} */
    private function resolve(Request $r): array
    {
        $pkg = $r->string('pkg');
        $type = $r->string('type');
        $def = $this->items()->definition($pkg, $type);
        if ($def === null) {
            $this->flash('error', 'Unknown content type.');
            return [null, $this->redirect($r, '/admin/content')];
        }
        return [$def, null];
    }

    private function listPath(array $def): string
    {
        return '/admin/content/list?pkg=' . rawurlencode($def['package'])
            . '&type=' . rawurlencode($def['type']);
    }

    private function can(): bool
    {
        $u = $this->user();
        return $u !== null && $this->auth()->has($u, 'content.manage');
    }
}
