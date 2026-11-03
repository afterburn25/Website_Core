<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
session_save_path(sys_get_temp_dir());
session_start();
require __DIR__ . '/bootstrap.php';
if (!extension_loaded('zip')) throw new RuntimeException('Package acceptance requires the PHP zip extension.');
$f = cateringFixture(null, false);
$db = $f['db'];
$latest = json_decode((string) file_get_contents(dirname(__DIR__) . '/releases/LATEST.json'), true, 512, JSON_THROW_ON_ERROR);
$archive = getenv('CATERING_PACKAGE_ZIP') ?: dirname(__DIR__) . '/releases/' . $latest['file'];
$previous = getenv('CATERING_PREVIOUS_ZIP');
if (!$previous || !is_file($previous)) throw new RuntimeException('Set CATERING_PREVIOUS_ZIP to a valid previous module archive.');
$root = sys_get_temp_dir() . '/ct-package-' . bin2hex(random_bytes(5));
foreach (['staging', 'industries', 'addons', 'themes'] as $dir) mkdir($root . '/' . $dir, 0750, true);
$registry = new Core\Modules\ModuleRegistry($db, $root . '/industries', $root . '/addons', $root . '/themes');
$manager = new Core\Modules\PackageManager($db, $registry, new Core\Modules\ArchiveInspector(),
    $root . '/staging', $root . '/industries', $root . '/addons', $root . '/themes', $root . '/operation.lock');
$checks = 0;
function packageCheck(bool $condition, string $description): void {
    global $checks;
    if (!$condition) throw new RuntimeException($description);
    $checks++;
}
$preMigrationResources = [];
foreach ($f['ctx']->adminResources() as $resource) $preMigrationResources[(string) $resource['key']] = $resource;
packageCheck(isset($preMigrationResources['menu-items'], $preMigrationResources['packages']),
    'Register shared catalog resources before module migrations');
$id = 'cajunwebpro/catering';
[, $error] = $manager->stage($previous);
packageCheck($error === null, 'Stage previous module: ' . (string) $error);
[$ok, $error] = $manager->install($id);
packageCheck($ok, 'Install previous module: ' . (string) $error);
[$ok, $error] = $manager->enable($id);
packageCheck($ok, 'Enable previous module: ' . (string) $error);
$itemId = (int) $db->fetchColumn('SELECT id FROM `' . $db->table('catering_menu_items') . '` ORDER BY id LIMIT 1');
$db->update('catering_menu_items', ['name' => 'Customer-owned menu item', 'dietary' => 'Preserved note', 'price_cents' => 1234], 'id = ?', [$itemId]);
$inquiryId = (int) $db->insert('catering_inquiries', ['kind' => 'quote', 'name' => 'Existing customer', 'email' => 'existing@example.test', 'message' => 'Preserve this inquiry']);
$menuCount = (int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('menu_items') . '`');
[, $error] = $manager->stage($archive);
packageCheck($error === null, 'Stage current module archive: ' . (string) $error);
[$ok, $error] = $manager->install($id);
packageCheck($ok, 'Upgrade previous module to current module: ' . (string) $error);
$custom = $db->fetch('SELECT * FROM `' . $db->table('catering_menu_items') . '` WHERE id = ?', [$itemId]);
packageCheck($custom['name'] === 'Customer-owned menu item' && $custom['dietary'] === 'Preserved note' && (int) $custom['price_cents'] === 1234,
    'Upgrade preserves customer menu content and prices');
packageCheck($db->fetchColumn('SELECT message FROM `' . $db->table('catering_inquiries') . '` WHERE id = ?', [$inquiryId]) === 'Preserve this inquiry', 'Upgrade preserves inquiry history');
packageCheck($db->driver() === 'sqlite'
    ? in_array('operations_plan', array_column($db->fetchAll('PRAGMA table_info(`' . $db->table('catering_inquiries') . '`)'), 'name'), true)
    : (bool) $db->fetchColumn('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?',
        [$db->table('catering_inquiries'), 'operations_plan']), 'Upgrade adds the event-operations snapshot column');
packageCheck($db->tableExists('catering_events') && $db->tableExists('catering_event_costs')
    && $db->tableExists('catering_recipe_ingredients'), 'Upgrade adds the commercial event, costing, and production workspace');
packageCheck((int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('menu_items') . '`') === $menuCount, 'Upgrade preserves navigation');
$installed = glob($root . '/industries/*/addon.json');
packageCheck(count($installed) === 1 && json_decode((string) file_get_contents($installed[0]), true)['version'] === $latest['version'], 'Installed manifest version matches release');
[$ok, $error] = $manager->disable($id);
packageCheck($ok, 'Disable module: ' . (string) $error);
[$ok, $error] = $manager->enable($id);
packageCheck($ok, 'Re-enable module: ' . (string) $error);
[$ok, $error] = $manager->disable($id);
packageCheck($ok, 'Disable before uninstall: ' . (string) $error);
[$ok, $error] = $manager->uninstall($id);
packageCheck($ok, 'Uninstall without data purge: ' . (string) $error);
packageCheck($db->tableExists('catering_menu_items') && $db->tableExists('catering_inquiries')
    && $db->tableExists('catering_events'), 'Uninstall retains catering records');
[, $error] = $manager->stage($archive);
packageCheck($error === null, 'Restage current module for reinstall: ' . (string) $error);
[$ok, $error] = $manager->install($id);
packageCheck($ok, 'Reinstall current module with retained records: ' . (string) $error);
packageCheck((int) $db->fetchColumn('SELECT COUNT(*) FROM `' . $db->table('catering_menu_items') . '`') === 10, 'Reinstall does not duplicate starter content');
packageCheck($db->fetchColumn('SELECT name FROM `' . $db->table('catering_menu_items') . '` WHERE id = ?', [$itemId]) === 'Customer-owned menu item',
    'Data-preserving reinstall retains customer catalog edits');
packageCheck($db->fetchColumn('SELECT message FROM `' . $db->table('catering_inquiries') . '` WHERE id = ?', [$inquiryId]) === 'Preserve this inquiry',
    'Data-preserving reinstall retains inquiry history for backup/restore continuity');
$fresh = cateringFixture(null, false);
$freshRoot = $root . '/fresh';
foreach (['staging', 'industries', 'addons', 'themes'] as $dir) mkdir($freshRoot . '/' . $dir, 0750, true);
$freshRegistry = new Core\Modules\ModuleRegistry($fresh['db'], $freshRoot . '/industries', $freshRoot . '/addons', $freshRoot . '/themes');
$freshManager = new Core\Modules\PackageManager($fresh['db'], $freshRegistry, new Core\Modules\ArchiveInspector(),
    $freshRoot . '/staging', $freshRoot . '/industries', $freshRoot . '/addons', $freshRoot . '/themes', $freshRoot . '/operation.lock');
[, $error] = $freshManager->stage($archive);
packageCheck($error === null, 'Stage current ZIP on a fresh site: ' . (string) $error);
[$ok, $error] = $freshManager->install($id);
packageCheck($ok, 'Install current ZIP on a fresh site: ' . (string) $error);
[$ok, $error] = $freshManager->enable($id);
packageCheck($ok, 'Enable current ZIP on a fresh site: ' . (string) $error);
packageCheck((int) $fresh['db']->fetchColumn('SELECT COUNT(*) FROM `' . $fresh['db']->table('catering_menu_items') . '` WHERE pricing_basis = ?', ['per_guest']) === 10, 'Fresh install seeds usable planner pricing');
packageCheck($fresh['db']->tableExists('catering_events') && $fresh['db']->tableExists('catering_tastings')
    && $fresh['db']->tableExists('catering_ingredients'), 'Fresh install creates the event and production workspace');
foreach ([$db, $fresh['db']] as $dbToClean) {
    if ($dbToClean->driver() !== 'mysql') continue;
    $dbToClean->pdo()->exec('SET FOREIGN_KEY_CHECKS=0');
    foreach ($dbToClean->fetchAll('SELECT table_name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name LIKE ?', [$dbToClean->prefix() . '%']) as $table) {
        $name = (string) array_values($table)[0];
        if (str_starts_with($name, $dbToClean->prefix())) $dbToClean->pdo()->exec('DROP TABLE `' . str_replace('`', '``', $name) . '`');
    }
    $dbToClean->pdo()->exec('SET FOREIGN_KEY_CHECKS=1');
}
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
foreach ($files as $file) $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
rmdir($root);
echo "Packaged upgrade/lifecycle ({$db->driver()}): {$checks} checks passed.\n";
