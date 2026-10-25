<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Database\Connection;
use Core\Database\Migrator;
use Core\I18n\ContentTranslations;
use PHPUnit\Framework\TestCase;

final class ContentTranslationsTest extends TestCase
{
    private Connection $db;
    private ContentTranslations $service;
    private int $pageId;

    protected function setUp(): void
    {
        $this->db = Connection::sqlite();
        (new Migrator($this->db, WC_ROOT . '/database/migrations'))->migrate();
        $this->pageId = (int) $this->db->insert('pages', [
            'slug' => 'about', 'title' => 'About', 'body' => 'Hello', 'blocks_json' => '[]',
            'status' => 'published', 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
        ]);
        $this->service = new ContentTranslations($this->db);
    }

    public function testSavesAndResolvesPublishedTranslation(): void
    {
        self::assertNull($this->service->addLanguage('fr', 'Français'));
        self::assertNull($this->saveFrench());
        $translated = $this->service->findPublished('fr', 'a-propos');
        self::assertSame('À propos', $translated['title']);
        self::assertSame('Bonjour', $translated['body']);
        self::assertCount(1, $this->service->alternates($this->pageId));
        self::assertSame(100, $this->service->progress()[0]['percent']);
    }

    public function testHiddenLanguagePreservesWorkButBlocksPublicResolution(): void
    {
        $this->service->addLanguage('fr', 'Français');
        $this->saveFrench();
        self::assertNull($this->service->setLanguageActive('fr', false));
        self::assertNotNull($this->service->forPage($this->pageId, 'fr'));
        self::assertNull($this->service->findPublished('fr', 'a-propos'));
        self::assertSame(0, $this->service->progress()[0]['active']);
        self::assertNull($this->service->setLanguageActive('fr', true));
        self::assertNotNull($this->service->findPublished('fr', 'a-propos'));
    }

    public function testRejectsInvalidJsonAndCanDeleteTranslation(): void
    {
        $this->service->addLanguage('fr', 'Français');
        $data = $this->translationData();
        $data['blocks_json'] = '{broken';
        self::assertStringContainsString('invalid JSON', (string) $this->service->save($this->pageId, 'fr', $data));
        self::assertNull($this->service->forPage($this->pageId, 'fr'));
        self::assertNull($this->saveFrench());
        self::assertTrue($this->service->delete($this->pageId, 'fr'));
        self::assertFalse($this->service->delete($this->pageId, 'fr'));
    }

    public function testRejectsInvalidLocale(): void
    {
        self::assertNotNull($this->service->addLanguage('../fr', 'Bad'));
    }

    private function saveFrench(): ?string
    {
        return $this->service->save($this->pageId, 'fr', $this->translationData());
    }

    private function translationData(): array
    {
        return ['title' => 'À propos', 'slug' => 'a-propos', 'body' => 'Bonjour', 'blocks_json' => '[]',
            'meta_title' => 'À propos', 'meta_description' => 'Présentation', 'schema_json' => null];
    }
}
