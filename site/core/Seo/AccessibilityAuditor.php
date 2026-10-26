<?php
declare(strict_types=1);
namespace Core\Seo;

final class AccessibilityAuditor
{
    public function audit(array $page): array
    {
        $issues = [];
        $title = trim((string) (($page['meta_title'] ?? '') ?: ($page['title'] ?? '')));
        $description = trim((string) ($page['meta_description'] ?? ''));
        $body = (string) ($page['body'] ?? '');
        if ($title === '') $issues[] = $this->issue('error', 'Search title is missing', 'Add a distinct title that identifies this page.');
        elseif (mb_strlen($title) < 15) $issues[] = $this->issue('warning', 'Title is very short', 'Use a descriptive search title of roughly 30–60 characters.');
        elseif (mb_strlen($title) > 60) $issues[] = $this->issue('warning', 'Title may truncate', 'Keep the search title at 60 characters or fewer.');
        if ($description === '') $issues[] = $this->issue('error', 'Meta description is missing', 'Write a useful summary for search and link previews.');
        elseif (mb_strlen($description) > 160) $issues[] = $this->issue('warning', 'Meta description may truncate', 'Keep it at 160 characters or fewer.');
        $canonical = trim((string) ($page['canonical'] ?? ''));
        if ($canonical !== '' && (!preg_match('#^https?://#i', $canonical) || filter_var($canonical, FILTER_VALIDATE_URL) === false)) $issues[] = $this->issue('error', 'Canonical URL is invalid', 'Use a complete http(s) address or leave the canonical field blank.');
        if (!empty($page['noindex'])) $issues[] = $this->issue('warning', 'Published page is hidden from search', 'Confirm that noindex is intentional for this published page.');
        $schemaJson = trim((string) ($page['schema_json'] ?? ''));
        if ($schemaJson !== '') {
            try { json_decode($schemaJson, true, 64, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { $issues[] = $this->issue('error', 'Structured data is invalid', 'Correct or remove the page schema JSON.'); }
        }
        if (preg_match('/<img\b(?![^>]*\balt=)[^>]*>/i', $body)) $issues[] = $this->issue('error', 'Image is missing alt text', 'Add concise alternative text to every meaningful image.');
        if (preg_match('/<a\b[^>]*>\s*(click here|read more|learn more)\s*<\/a>/i', $body)) $issues[] = $this->issue('warning', 'Link text lacks context', 'Describe the destination instead of using generic link text.');
        $blocks = json_decode((string) ($page['blocks_json'] ?? '[]'), true);
        if (!is_array($blocks)) $issues[] = $this->issue('error', 'Visual-block data is invalid', 'Open the page editor and correct its advanced layout data.');
        else foreach ($blocks as $block) {
            $data = is_array($block['data'] ?? null) ? $block['data'] : [];
            if (($block['type'] ?? '') === 'image_text' && !empty($data['media_id']) && trim((string) ($data['alt'] ?? '')) === '') {
                $issues[] = $this->issue('error', 'Block image is missing alt text', 'Add alt text in the image/text block.');
            }
        }
        return $issues;
    }

    private function issue(string $level, string $title, string $help): array
    {
        return compact('level', 'title', 'help');
    }
}
