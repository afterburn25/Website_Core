<?php
/** @var array $missing @var array $pages @var array $redirects @var array $audits */
$issueCount = array_sum(array_map(static fn (array $page): int => count($page['issues']), $audits));
$errorCount = array_sum(array_map(
    static fn (array $page): int => count(array_filter(
        $page['issues'],
        static fn (array $issue): bool => ($issue['level'] ?? '') === 'error'
    )),
    $audits
));
$formatSeen = static function (mixed $value): string {
    $timestamp = strtotime((string) $value);
    return $timestamp === false ? (string) $value : date('M j, Y · g:i a', $timestamp);
};
?>
<section class="status-strip" aria-label="SEO overview">
    <div class="status-chip <?= $missing ? 'warn' : 'ok' ?>"><strong><?= count($missing) ?></strong><span>Missing paths</span></div>
    <div class="status-chip <?= $errorCount ? 'bad' : ($issueCount ? 'warn' : 'ok') ?>"><strong><?= $issueCount ?></strong><span>Content issues</span></div>
    <div class="status-chip <?= $redirects ? 'active' : '' ?>"><strong><?= count($redirects) ?></strong><span>Redirects</span></div>
</section>

<section class="card">
    <div class="card-heading">
        <div>
            <p class="section-kicker">Broken-link recovery</p>
            <h2>404 insights</h2>
            <p class="muted">Repeated public URLs often reveal an old campaign, bookmark, or external link that should reach a live page.</p>
        </div>
        <?php if ($missing !== []): ?>
            <form method="post">
                <?= $csrf->field() ?>
                <input type="hidden" name="action" value="clear">
                <button class="btn small secondary" type="submit" data-confirm="Clear all recorded missing paths?">Clear log</button>
            </form>
        <?php endif; ?>
    </div>
    <?php if ($missing === []): ?>
        <div class="empty-state seo-empty-state">
            <strong>No missing public paths</strong>
            <span>Core assets and administration routes are excluded so this report stays focused on visitor-facing links.</span>
        </div>
    <?php else: ?>
        <div class="responsive-table">
            <table>
                <thead><tr><th>Missing path</th><th>Traffic</th><th>Last seen</th><th>Resolution</th></tr></thead>
                <tbody>
                <?php foreach ($missing as $row): ?>
                    <tr>
                        <td>
                            <code class="seo-path"><?= e($row['path']) ?></code>
                            <?php if ($row['last_referrer']): ?>
                                <span class="seo-referrer">Linked from <?= e($row['last_referrer']) ?></span>
                            <?php endif; ?>
                        </td>
                        <td><span class="metric-value"><?= number_format((int) $row['hits']) ?></span><span class="metric-label">visit<?= (int) $row['hits'] === 1 ? '' : 's' ?></span></td>
                        <td><span class="seo-last-seen"><?= e($formatSeen($row['last_seen'])) ?></span></td>
                        <td>
                            <form method="post" class="seo-resolution-form">
                                <?= $csrf->field() ?>
                                <input type="hidden" name="action" value="redirect">
                                <input type="hidden" name="source" value="<?= e_attr($row['path']) ?>">
                                <label class="sr-only" for="redirect-target-<?= e_attr($row['path_hash']) ?>">Redirect destination</label>
                                <select id="redirect-target-<?= e_attr($row['path_hash']) ?>" name="target" required>
                                    <option value="">Choose live page…</option>
                                    <?php foreach ($pages as $page): ?>
                                        <?php $pagePath = $page['slug'] === 'home' ? '/' : '/' . ltrim((string) $page['slug'], '/'); ?><option value="<?= e_attr($pagePath) ?>"><?= e($page['title']) ?> — <?= e($pagePath) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <button class="btn small" type="submit">Create redirect</button>
                            </form>
                            <form method="post" class="seo-dismiss-form">
                                <?= $csrf->field() ?>
                                <input type="hidden" name="action" value="dismiss_missing">
                                <input type="hidden" name="source" value="<?= e_attr($row['path']) ?>">
                                <button class="text-button" type="submit">Dismiss without redirect</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-heading">
        <div>
            <p class="section-kicker">Search discovery</p>
            <h2>Search-engine endpoints</h2>
            <p class="muted">Inspect the live files that tell crawlers what is available and what may be indexed.</p>
        </div>
    </div>
    <div class="seo-endpoints">
        <a class="seo-endpoint" href="<?= e_attr(rtrim($basePath, '/') . '/sitemap.xml') ?>" target="_blank" rel="noopener">
            <strong>Sitemap</strong><span>Published, indexable pages</span><b aria-hidden="true">↗</b>
        </a>
        <a class="seo-endpoint" href="<?= e_attr(rtrim($basePath, '/') . '/robots.txt') ?>" target="_blank" rel="noopener">
            <strong>Robots rules</strong><span>Crawler access instructions</span><b aria-hidden="true">↗</b>
        </a>
    </div>
    <p class="field-note">Per-page canonical URLs, descriptions, social images, and noindex controls are available in the page editor.</p>
</section>

<section class="card">
    <div class="card-heading">
        <div>
            <p class="section-kicker">Published content</p>
            <h2>Content quality checks</h2>
            <p class="muted">Automated checks flag common search and accessibility omissions. They support—not replace—a human review.</p>
        </div>
        <?php if ($issueCount > 0): ?><span class="badge <?= $errorCount ? 'warn' : '' ?>"><?= $issueCount ?> item<?= $issueCount === 1 ? '' : 's' ?> to review</span><?php endif; ?>
    </div>
    <?php if ($audits === []): ?>
        <div class="empty-state"><strong>No published pages to audit</strong><span>Publish a page and its checks will appear here.</span></div>
    <?php else: ?>
        <div class="responsive-table seo-audit-table">
            <table>
                <thead><tr><th>Page</th><th>Review</th></tr></thead>
                <tbody>
                <?php foreach ($audits as $page): ?>
                    <tr>
                        <td><a class="seo-page-link" href="<?= e_attr($basePath) ?>/admin/pages/edit/<?= (int) $page['id'] ?>"><?= e($page['title']) ?><span>Open editor →</span></a></td>
                        <td>
                            <?php if (!$page['issues']): ?>
                                <span class="badge ok">No common issues</span>
                            <?php else: ?>
                                <div class="seo-issue-list">
                                    <?php foreach ($page['issues'] as $issue): ?>
                                        <div class="seo-issue">
                                            <span class="badge <?= $issue['level'] === 'error' ? 'warn' : '' ?>"><?= e($issue['level']) ?></span>
                                            <div><strong><?= e($issue['title']) ?></strong><span><?= e($issue['help']) ?></span></div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-heading">
        <div>
            <p class="section-kicker">Traffic recovery</p>
            <h2>Redirect manager</h2>
            <p class="muted">Maintain old addresses in one place. Permanent redirects are best for moved pages; temporary codes preserve the original address.</p>
        </div>
        <span class="badge"><?= number_format(array_sum(array_map(static fn (array $row): int => (int) $row['hits'], $redirects))) ?> forwarded visits</span>
    </div>
    <form method="post" class="redirect-create-form">
        <?= $csrf->field() ?>
        <input type="hidden" name="action" value="redirect_save">
        <div><label for="new-redirect-source">Old path</label><input id="new-redirect-source" name="source" maxlength="500" placeholder="/old-page" required><span class="field-note">Site-relative and beginning with /.</span></div>
        <div><label for="new-redirect-target">Destination</label><input id="new-redirect-target" name="target" maxlength="500" placeholder="/new-page or https://example.com" required><span class="field-note">A site path or external http(s) URL.</span></div>
        <div><label for="new-redirect-code">Type</label><select id="new-redirect-code" name="status_code"><option value="301">301 · Permanent</option><option value="308">308 · Permanent, preserve method</option><option value="302">302 · Temporary</option><option value="307">307 · Temporary, preserve method</option></select></div>
        <button class="btn" data-busy-label="Creating…">Create redirect</button>
    </form>
    <?php if ($redirects === []): ?>
        <div class="empty-state seo-empty-state"><strong>No redirects configured</strong><span>Create one above or resolve a recorded missing path.</span></div>
    <?php else: ?>
        <div class="responsive-table redirect-manager-table"><table>
            <thead><tr><th>Old path</th><th>Destination</th><th>Type</th><th>Traffic</th><th><span class="sr-only">Actions</span></th></tr></thead>
            <tbody><?php foreach ($redirects as $redirect): ?><tr>
                <td><code class="seo-path"><?= e($redirect['source']) ?></code></td>
                <td><code class="seo-path"><?= e($redirect['target']) ?></code></td>
                <td><span class="badge"><?= (int) $redirect['status_code'] ?></span></td>
                <td><span class="metric-value"><?= number_format((int) $redirect['hits']) ?></span><span class="metric-label">visit<?= (int) $redirect['hits'] === 1 ? '' : 's' ?></span></td>
                <td><div class="table-actions">
                    <details class="row-editor"><summary class="btn small secondary">Edit</summary><form method="post" class="row-editor-panel"><?= $csrf->field() ?><input type="hidden" name="action" value="redirect_save"><input type="hidden" name="id" value="<?= (int) $redirect['id'] ?>"><label for="redirect-<?= (int) $redirect['id'] ?>-source">Old path</label><input id="redirect-<?= (int) $redirect['id'] ?>-source" name="source" maxlength="500" value="<?= e_attr($redirect['source']) ?>" required><label for="redirect-<?= (int) $redirect['id'] ?>-target">Destination</label><input id="redirect-<?= (int) $redirect['id'] ?>-target" name="target" maxlength="500" value="<?= e_attr($redirect['target']) ?>" required><label for="redirect-<?= (int) $redirect['id'] ?>-code">Status</label><select id="redirect-<?= (int) $redirect['id'] ?>-code" name="status_code"><?php foreach ([301, 308, 302, 307] as $code): ?><option value="<?= $code ?>" <?= (int) $redirect['status_code'] === $code ? 'selected' : '' ?>><?= $code ?></option><?php endforeach; ?></select><button class="btn small">Save redirect</button></form></details>
                    <form method="post" data-confirm="Remove this redirect? Requests to the old path will stop forwarding."><?= $csrf->field() ?><input type="hidden" name="action" value="redirect_delete"><input type="hidden" name="id" value="<?= (int) $redirect['id'] ?>"><button class="btn small danger">Remove</button></form>
                </div></td>
            </tr><?php endforeach; ?></tbody>
        </table></div>
    <?php endif; ?>
</section>

<section class="card">
    <div class="card-heading">
        <div><p class="section-kicker">Migration tools</p><h2>Redirect portability</h2><p class="muted">Move redirect maps between sites with a three-column CSV: source, target, status_code.</p></div>
        <a class="btn small secondary" href="<?= e_attr($basePath) ?>/admin/seo/redirects.csv">Export <?= count($redirects) ?> redirects</a>
    </div>
    <form method="post" enctype="multipart/form-data" class="seo-import-form">
        <?= $csrf->field() ?><input type="hidden" name="action" value="import">
        <div><label for="redirect-csv">Redirect CSV</label><input id="redirect-csv" type="file" name="csv" accept=".csv,text/csv" required><span class="field-note">Up to 2 MB and 2,000 redirect rows. Invalid or duplicate rows are skipped with a useful first-error summary.</span></div>
        <button class="btn" type="submit">Import redirects</button>
    </form>
</section>
