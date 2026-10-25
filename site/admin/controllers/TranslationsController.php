<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Http\Request;
use Core\Http\Response;
use Core\I18n\ContentTranslations;

final class TranslationsController extends Controller
{
    public function index(Request $r): Response
    {
        $user = $this->user();
        if (!$user || !$this->auth()->has($user, 'content.manage')) return $this->forbidden();

        $service = new ContentTranslations($this->db());
        $pageId = (int) $r->string('page_id');
        $locale = ContentTranslations::locale($r->string('locale'));
        $submittedTranslation = null;

        if ($r->isPost()) {
            $action = $r->string('action');
            $error = null;
            $message = '';

            if (in_array($action, ['language', 'language_add'], true)) {
                $error = $service->addLanguage($locale, $r->string('name'));
                $message = 'Language added and enabled.';
                if (!$error) $this->audit('translations.language_added', ['locale' => $locale]);
            } elseif ($action === 'language_toggle') {
                $active = $r->string('active') === '1';
                $error = $service->setLanguageActive($locale, $active);
                $message = $active ? 'Language enabled on the public site.' : 'Language hidden from the public site.';
                if (!$error) $this->audit('translations.language_toggled', ['locale' => $locale, 'active' => $active]);
            } elseif ($action === 'delete') {
                $deleted = $service->delete($pageId, $locale);
                $error = $deleted ? null : 'That translation was already removed.';
                $message = 'Translation removed. The source page was not changed.';
                if ($deleted) $this->audit('translations.page_deleted', ['locale' => $locale, 'page_id' => $pageId]);
            } elseif ($action === 'save') {
                $error = $service->save($pageId, $locale, $r->post);
                $message = 'Translation saved.';
                if (!$error) $this->audit('translations.page_saved', ['locale' => $locale, 'page_id' => $pageId]);
                else $submittedTranslation = $r->post;
            } else {
                $error = 'That translation action is not available.';
            }

            $this->flash($error ? 'error' : 'ok', $error ?? $message);
            if (!$error || $action !== 'save') {
                $query = $pageId > 0 && $locale !== ''
                    ? '?page_id=' . $pageId . '&locale=' . rawurlencode($locale) : '';
                return $this->redirect($r, '/admin/translations' . $query);
            }
        }

        $pages = $this->db()->fetchAll(
            'SELECT p.id, p.title, p.slug, p.body, p.blocks_json, p.meta_title, p.meta_description, p.schema_json, p.status,'
            . ' COUNT(t.id) AS translation_count FROM `' . $this->db()->table('pages') . '` p'
            . ' LEFT JOIN `' . $this->db()->table('page_translations') . '` t ON t.page_id = p.id'
            . ' WHERE p.deleted_at IS NULL GROUP BY p.id, p.title, p.slug, p.body, p.blocks_json, p.meta_title,'
            . ' p.meta_description, p.schema_json, p.status ORDER BY p.title'
        );
        $translation = $submittedTranslation;
        if ($translation === null && $pageId > 0 && $locale !== '') $translation = $service->forPage($pageId, $locale);

        return $this->page($r, 'translations/index', [
            'languages' => $service->languages(false),
            'progress' => $service->progress(),
            'pages' => $pages,
            'selectedPage' => $pageId,
            'selectedLocale' => $locale,
            'translation' => $translation,
            'baseLocale' => (string) $this->settings()->get('site.locale', 'en'),
        ], 'Translations');
    }
}
