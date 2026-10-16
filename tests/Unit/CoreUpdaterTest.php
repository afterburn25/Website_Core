<?php
declare(strict_types=1);

namespace Tests\Unit;

use Core\Modules\ArchiveInspector;
use Core\Update\CoreUpdater;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class CoreUpdaterTest extends TestCase
{
    private string $root;
    private string $storage;
    private CoreUpdater $updater;

    protected function setUp(): void
    {
        if (!extension_loaded('zip')) {
            $this->markTestSkipped('needs zip');
        }
        $this->root = \WcProcessTmpDirs::make('wc-upd-root-');
        $this->storage = \WcProcessTmpDirs::make('wc-upd-store-');
        foreach (['includes', 'config', 'storage', 'upload', 'modules/addons'] as $d) {
            @mkdir($this->root . '/' . $d, 0777, true);
        }
        file_put_contents($this->root . '/includes/bootstrap.php',
            "<?php define('WC_VERSION', '0.1.0-dev');");
        file_put_contents($this->root . '/config/config.php', 'SITE-OWNED-CONFIG');
        file_put_contents($this->root . '/index.php', 'OLD-INDEX');
        file_put_contents($this->root . '/upload/existing.jpg', 'UPLOAD');
        @mkdir($this->root . '/themes/terra', 0777, true);
        @mkdir($this->root . '/themes/base', 0777, true);
        file_put_contents($this->root . '/themes/terra/theme.json', 'INSTALLED-TERRA');
        file_put_contents($this->root . '/themes/base/theme.json', 'OLD-BASE');
        $this->updater = new CoreUpdater($this->root, $this->storage, new ArchiveInspector());
    }

    private function updateZip(array $members, ?array $manifest = null): string
    {
        $path = \WcProcessTmpDirs::make('wc-upd-') . '.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        foreach ($members as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        if ($manifest !== null) {
            $zip->addFromString('update.json', json_encode($manifest));
        }
        $zip->close();
        return $path;
    }

    private function validMembers(): array
    {
        return [
            'includes/bootstrap.php' => "<?php define('WC_VERSION', '9.9.9');",
            'index.php'              => 'NEW-INDEX',
            'includes/new-file.php'  => 'NEW-FILE',
            'config/config.php'      => 'EVIL-OVERWRITE',
            'upload/existing.jpg'    => 'EVIL-UPLOAD',
            'themes/terra/theme.json' => 'EVIL-THEME-OVERWRITE',
            'themes/base/theme.json' => 'NEW-BASE',
            'update.json'            => '{}',
        ];
    }

    private function manifestFor(array $members): array
    {
        $files = [];
        foreach ($members as $name => $contents) {
            if ($name !== 'update.json') {
                $files[$name] = hash('sha256', $contents);
            }
        }
        return ['product' => 'website-core', 'version' => '9.9.9', 'files' => $files];
    }

    public function testInspectRejectsNonUpdateArchives(): void
    {
        $zip = $this->updateZip(['random.txt' => 'x']);
        [$info, $err] = $this->updater->inspect($zip);
        $this->assertNull($info);
        $this->assertStringContainsString('Website Core update archive', $err);
    }

    public function testApplyWritesBacksUpAndProtectsSiteOwnedPaths(): void
    {
        $members = $this->validMembers();
        $zip = $this->updateZip($members, $this->manifestFor($members));

        [$info, $err] = $this->updater->inspect($zip);
        $this->assertNull($err);
        $this->assertSame('9.9.9', $info['version']);
        $this->assertFalse($info['signed']);

        [$ok, $err, $backup] = $this->updater->apply($zip);
        $this->assertTrue($ok, (string) $err);
        $this->assertFileExists($backup);

        // New files written, site-owned paths untouched.
        $this->assertStringContainsString('9.9.9', file_get_contents($this->root . '/includes/bootstrap.php'));
        $this->assertSame('NEW-INDEX', file_get_contents($this->root . '/index.php'));
        $this->assertSame('NEW-FILE', file_get_contents($this->root . '/includes/new-file.php'));
        $this->assertSame('SITE-OWNED-CONFIG', file_get_contents($this->root . '/config/config.php'));
        $this->assertSame('UPLOAD', file_get_contents($this->root . '/upload/existing.jpg'));
        // Installed themes are site-owned; the shipped base theme updates.
        $this->assertSame('INSTALLED-TERRA', file_get_contents($this->root . '/themes/terra/theme.json'));
        $this->assertSame('NEW-BASE', file_get_contents($this->root . '/themes/base/theme.json'));

        // Backup contains the replaced originals.
        $bz = new ZipArchive();
        $bz->open($backup, ZipArchive::RDONLY);
        $this->assertSame('OLD-INDEX', $bz->getFromName('index.php'));
        $this->assertStringContainsString('0.1.0-dev', $bz->getFromName('includes/bootstrap.php'));
        $bz->close();

        // Journal recorded the operation.
        $this->assertNotSame([], $this->updater->journalTail());
        $this->assertStringContainsString('9.9.9', implode('', $this->updater->journalTail()));
    }

    public function testExpiredAndTooNewManifestsAreRefused(): void
    {
        $members = $this->validMembers();

        $manifest = $this->manifestFor($members);
        $manifest['expires'] = '2020-01-01T00:00:00Z';
        $zip = $this->updateZip($members, $manifest);
        [$info, $err] = $this->updater->inspect($zip);
        $this->assertNull($info);
        $this->assertStringContainsString('expired', $err);

        $manifest = $this->manifestFor($members);
        $manifest['min_core'] = '99.0.0';
        $zip = $this->updateZip($members, $manifest);
        [$info, $err] = $this->updater->inspect($zip);
        $this->assertNull($info);
        $this->assertStringContainsString('requires core', $err);
    }

    public function testDowngradeArchiveIsRefused(): void
    {
        $members = $this->validMembers();
        $members['includes/bootstrap.php'] = "<?php define('WC_VERSION', '0.0.1');";
        $manifest = $this->manifestFor($members);
        $manifest['version'] = '0.0.1';
        $zip = $this->updateZip($members, $manifest);
        [$info, $err] = $this->updater->inspect($zip);
        $this->assertNull($info);
        $this->assertStringContainsString('downgrade', $err);
    }

    public function testManifestVersionMustMatchBootstrap(): void
    {
        $members = $this->validMembers();
        $manifest = $this->manifestFor($members);
        $manifest['version'] = '8.8.8'; // bootstrap says 9.9.9
        $zip = $this->updateZip($members, $manifest);
        [$info, $err] = $this->updater->inspect($zip);
        $this->assertNull($info);
        $this->assertStringContainsString('does not match', $err);
    }

    public function testApplyRefusesWhileAnotherUpdateHoldsTheLock(): void
    {
        @mkdir($this->storage, 0777, true);
        $lock = fopen($this->storage . '/update.lock', 'c');
        $this->assertNotFalse($lock);
        $this->assertTrue(flock($lock, LOCK_EX | LOCK_NB));

        $members = $this->validMembers();
        $zip = $this->updateZip($members, $this->manifestFor($members));
        [$ok, $err] = $this->updater->apply($zip);
        $this->assertFalse($ok);
        $this->assertStringContainsString('already in progress', (string) $err);
        // The held lock must mean zero writes happened.
        $this->assertSame('OLD-INDEX', file_get_contents($this->root . '/index.php'));

        flock($lock, LOCK_UN);
        fclose($lock);
    }

    public function testManifestHashMismatchAbortsApply(): void
    {
        $members = $this->validMembers();
        $manifest = $this->manifestFor($members);
        $manifest['files']['index.php'] = str_repeat('0', 64); // corrupted hash
        $zip = $this->updateZip($members, $manifest);

        [$ok, $err] = $this->updater->apply($zip);
        $this->assertFalse($ok);
        $this->assertStringContainsString('Integrity check failed', $err);
        // Partial writes must not have clobbered the original.
        $this->assertSame('OLD-INDEX', file_get_contents($this->root . '/index.php'));
    }
}
