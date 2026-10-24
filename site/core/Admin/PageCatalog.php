<?php
declare(strict_types=1);

namespace Core\Admin;

/** Human-facing purpose text shared by every Core administration surface. */
final class PageCatalog
{
    private const PAGES = [
        'Dashboard' => ['dashboard', 'A clear overview of your website, recent activity, and the work that needs attention.'],
        'Search' => ['search', 'Find pages, administrators, media, modules, settings, and module-provided records from one place.'],
        'Notifications' => ['bell', 'Review important website events, warnings, completed work, and recommended follow-up.'],
        'Agency dashboard' => ['sites', 'Monitor connected client websites, versions, health, and secure remote access.'],
        'Content' => ['content', 'Create, review, publish, organize, and recover the pages that make up your website.'],
        'Layout patterns' => ['patterns', 'Save reusable page arrangements and apply them without rebuilding common sections.'],
        'Translations' => ['translations', 'Manage site languages and localized versions of published content.'],
        'Forms' => ['forms', 'Build reusable forms and manage the inquiries they collect.'],
        'Form submissions' => ['forms', 'Review, filter, export, and process messages received from website forms.'],
        'Navigation Menu' => ['navigation', 'Organize the header and footer links visitors use to move through the site.'],
        'SEO' => ['seo', 'Improve search presentation, indexing signals, structured data, and redirect health.'],
        'Media' => ['media', 'Upload, organize, replace, and safely reuse images and documents across the website.'],
        'Appearance' => ['appearance', 'Control the logo, favicon, colors, layouts, footer identity, and social profiles.'],
        'Privacy' => ['privacy', 'Manage visitor consent and respond to privacy-information requests.'],
        'Integrations' => ['integrations', 'Create scoped API access, webhooks, and portable site exports without exposing secrets.'],
        'Administrators' => ['users', 'Manage administrator access while protecting the final owner account.'],
        'My Profile' => ['security', 'Update your account details, password, active sessions, and multi-factor authentication.'],
        'Roles & permissions' => ['roles', 'Create focused permission sets so each administrator sees only the tools they need.'],
        'Security' => ['security', 'Review active sessions, account safeguards, and security-sensitive website state.'],
        'Modules' => ['modules', 'Install and maintain industry modules, add-ons, and themes with safe recovery controls.'],
        'Marketplace & managed updates' => ['marketplace', 'Review trusted package feeds and control managed update behavior.'],
        'Backups' => ['backups', 'Create verified recovery points and restore the site safely when something goes wrong.'],
        'Operations' => ['operations', 'Manage background jobs, queued mail, scheduled work, and maintenance state.'],
        'System health' => ['health', 'Check configuration, storage, database, mail, scheduling, modules, and recovery readiness.'],
        'Core updates' => ['updates', 'Stage, verify, apply, or roll back Core updates with a safety backup.'],
        'Audit log' => ['audit', 'Trace security-sensitive and administrative changes across the website.'],
        'Compare revisions' => ['content', 'Review exactly what changed between two saved versions of a page.'],
        'Content Types' => ['content', 'Manage module-provided structured content from the shared Core workspace.'],
        'Settings' => ['settings', 'Configure the site identity, contact details, mail delivery, locale, and licensing.'],
    ];

    /** @return array{icon:string,description:string} */
    public static function for(string $title): array
    {
        [$icon, $description] = self::PAGES[$title] ?? ['settings', 'Manage this part of your website.'];
        return compact('icon', 'description');
    }
}
