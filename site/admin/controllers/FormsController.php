<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Forms\FormService;
use Core\Http\Request;
use Core\Http\Response;

final class FormsController extends Controller
{
    private function forms(): FormService { return $this->c->get(FormService::class); }
    private function can(): bool
    {
        $u = $this->user();
        return $u !== null && $this->auth()->has($u, 'forms.manage');
    }

    public function index(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $editId = (int) $r->input('edit', 0);
        $editing = $editId ? $this->forms()->find($editId) : null;
        if ($editId > 0 && $editing === null) {
            $this->flash('error', 'That form no longer exists.');
        }
        return $this->page($r, 'forms/index', [
            'forms' => $this->forms()->all(),
            'editing' => $editing,
        ], 'Forms');
    }

    public function save(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $editId = ($v = (int) $r->string('id')) > 0 ? $v : null;
        $fieldsJson = (string) ($r->post['fields_json'] ?? '[]');
        [$id, $error] = $this->forms()->save(
            $editId,
            $r->string('name'), (string) ($r->post['fields_json'] ?? '[]'),
            $r->string('recipient'), $r->string('subject'), $r->string('success_message'),
            $r->string('active') === '1', $this->user()->id, $r->string('submit_label', 'Submit'),
            $r->string('notification_template')
        );
        if ($error !== null) {
            $this->flash('error', $error . ' Your entered settings and fields are still below.');
            return $this->page($r, 'forms/index', [
                'forms' => $this->forms()->all(),
                'editing' => [
                    'id' => $editId ?? 0,
                    'name' => $r->string('name'),
                    'fields_json' => $fieldsJson,
                    'recipient' => $r->string('recipient'),
                    'subject' => $r->string('subject'),
                    'success_message' => $r->string('success_message'),
                    'submit_label' => $r->string('submit_label', 'Submit'),
                    'notification_template' => $r->string('notification_template'),
                    'active' => $r->string('active') === '1' ? 1 : 0,
                ],
            ], 'Forms');
        }
        $this->audit('forms.saved', ['form' => $id]);
        $this->flash('ok', 'Form saved. Add it to a page with a Form block.');
        return $this->redirect($r, '/admin/forms?edit=' . $id);
    }

    public function archive(Request $r): Response
    {
        $r->attributes['state'] = 'archive';
        return $this->lifecycle($r);
    }

    public function lifecycle(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) ($r->attributes['id'] ?? 0);
        $state = (string) ($r->attributes['state'] ?? '');
        $active = $state === 'activate';
        $ok = in_array($state, ['activate', 'archive'], true) && $this->forms()->setActive($id, $active);
        if ($ok) $this->audit('forms.' . ($active ? 'activated' : 'archived'), ['form' => $id]);
        $this->flash(
            $ok ? 'ok' : 'error',
            $ok
                ? ($active ? 'Form activated and ready to accept submissions.' : 'Form archived; existing submissions were preserved.')
                : 'The form could not be found.'
        );
        return $this->redirect($r, '/admin/forms');
    }

    public function submissions(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $formId = (int) $r->input('form', 0);
        $status = in_array($r->string('status'), ['new', 'read', 'archived'], true) ? $r->string('status') : '';
        $search = mb_substr($r->string('q'), 0, 190);
        $perPage = 50;
        $total = $this->forms()->submissionCount($formId ?: null, $status, $search);
        $pages = max(1, (int) ceil($total / $perPage));
        $page = max(1, min($pages, (int) $r->input('page', 1)));
        return $this->page($r, 'forms/submissions', [
            'submissions' => $this->forms()->submissions($formId ?: null, $perPage, $status, $search, ($page - 1) * $perPage),
            'forms' => $this->forms()->all(), 'formId' => $formId,
            'status' => $status, 'search' => $search, 'total' => $total, 'page' => $page, 'pages' => $pages,
            'counts' => [
                'new' => $this->forms()->submissionCount($formId ?: null, 'new'),
                'read' => $this->forms()->submissionCount($formId ?: null, 'read'),
                'archived' => $this->forms()->submissionCount($formId ?: null, 'archived'),
            ],
        ], 'Form submissions');
    }

    public function markRead(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $this->forms()->markRead((int) $r->attributes['id']);
        return $this->redirect($r, '/admin/forms/submissions');
    }

    public function submissionStatus(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) ($r->attributes['id'] ?? 0);
        $status = (string) ($r->attributes['status'] ?? '');
        $ok = $this->forms()->setSubmissionStatus($id, $status);
        if ($ok) $this->audit('forms.submission_status', ['submission' => $id, 'status' => $status]);
        $this->flash($ok ? 'ok' : 'error', $ok ? 'Submission status updated.' : 'Submission or status not found.');
        return $this->redirectToSubmissions($r);
    }

    public function deleteSubmission(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) ($r->attributes['id'] ?? 0);
        $ok = $this->forms()->deleteSubmission($id);
        if ($ok) $this->audit('forms.submission_deleted', ['submission' => $id]);
        $this->flash($ok ? 'ok' : 'error', $ok ? 'Submission permanently deleted.' : 'Submission not found.');
        return $this->redirectToSubmissions($r);
    }

    public function bulkSubmissions(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $ids = is_array($r->post['submission_ids'] ?? null) ? $r->post['submission_ids'] : [];
        $action = $r->string('bulk_action');
        $result = $this->forms()->bulkSubmissionAction($ids, $action);
        if ($result['updated'] > 0) {
            $this->audit('forms.submissions_bulk', [
                'action' => $action,
                'count' => $result['updated'],
            ]);
        }
        $validAction = in_array($action, ['new', 'read', 'archived', 'delete'], true);
        $this->flash(
            $validAction && $result['updated'] > 0 ? 'ok' : 'error',
            !$validAction ? 'Choose a valid bulk action.'
                : ($result['updated'] > 0
                    ? $result['updated'] . ' submission' . ($result['updated'] === 1 ? '' : 's') . ' updated.'
                    : 'Select at least one available submission.')
        );
        return $this->redirectToSubmissions($r);
    }

    public function export(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) $r->input('form', 0);
        $status = in_array($r->string('status'), ['new', 'read', 'archived'], true) ? $r->string('status') : '';
        $search = mb_substr($r->string('q'), 0, 190);
        $rows = $this->forms()->submissions($id ?: null, 1000, $status, $search);
        $keys = [];
        foreach ($rows as $row) {
            foreach (array_keys(json_decode((string) $row['data_json'], true) ?: []) as $key) {
                $keys[(string) $key] = true;
            }
        }
        $keys = array_keys($keys);
        $stream = fopen('php://temp', 'w+');
        if ($stream === false) {
            $this->flash('error', 'The CSV export could not be created on this server.');
            return $this->redirect($r, '/admin/forms/submissions');
        }
        fputcsv($stream, ['id', 'form', 'status', 'submitted_at', ...$keys, 'consent_evidence', 'notification'], ',', '"', '');
        foreach ($rows as $row) {
            $data = json_decode((string) $row['data_json'], true) ?: [];
            $consent = json_decode((string) ($row['consent_json'] ?? ''), true) ?: [];
            $values = [];
            foreach ($keys as $key) $values[] = $this->csvCell((string) ($data[$key] ?? ''));
            $consentSummary = [];
            foreach ($consent as $item) {
                if (!is_array($item)) continue;
                $consentSummary[] = (string) ($item['label'] ?? 'Consent') . ': '
                    . (!empty($item['accepted']) ? 'accepted' : 'not accepted')
                    . (!empty($item['recorded_at']) ? ' at ' . $item['recorded_at'] : '');
            }
            fputcsv($stream, [
                $row['id'], $this->csvCell((string) $row['form_name']), $row['status'], $row['created_at'],
                ...$values, $this->csvCell(implode('; ', $consentSummary)),
                $row['notification_status'] ?: ($row['notification_message_id'] ? 'unknown' : 'not configured'),
            ], ',', '"', '');
        }
        rewind($stream); $csv = (string) stream_get_contents($stream); fclose($stream);
        $this->audit('forms.submissions_exported', ['form' => $id ?: null, 'status' => $status, 'count' => count($rows)]);
        return Response::text("\xEF\xBB\xBF" . $csv)->header('Content-Type', 'text/csv; charset=utf-8')
            ->header('Content-Disposition', 'attachment; filename="form-submissions-' . date('Ymd-His') . '.csv"');
    }

    public function submit(Request $r): Response
    {
        $id = (int) ($r->attributes['id'] ?? 0);
        if (!$this->csrf()->validate($r)) return $this->publicResult($r, 'This form expired. Go back, reload the page and try again.', false);
        if ($r->string('company_url') !== '') return $this->publicResult($r, 'Thank you. Your message has been received.', true);
        $limiter = $this->c->get(\Core\Security\RateLimiter::class);
        if (!$limiter->attempt('form:' . $id . ':' . $r->ip(), 8, 900)) return $this->publicResult($r, 'Too many submissions. Please wait and try again.', false);
        $key = (string) $this->c->get(\Core\Config::class)->get('security.app_key', 'website-core');
        [$submissionId, $errors] = $this->forms()->submit(
            $id,
            $r->post,
            hash_hmac('sha256', $r->ip(), $key),
            $r->string('_form_token'),
        );
        if ($errors !== []) return $this->publicResult($r, implode(' ', $errors), false);
        $form = $this->forms()->find($id);
        if ($submissionId !== null) {
            $this->forms()->queueNotification((int) $submissionId, $this->c->get(\Core\Mail\SystemMail::class));
        }
        return $this->publicResult($r, (string) ($form['success_message'] ?? 'Thank you. Your message has been received.'), true);
    }

    private function publicResult(Request $r, string $message, bool $ok): Response
    {
        return Response::html(\Core\Branding\StatusPage::render(
            $ok ? 200 : 422,
            $ok ? 'Thank you' : 'Please check the form',
            $message,
            $r->routeBase(),
            (string) $this->settings()->get('site.name', 'Website'),
            null,
            'Return to the website',
            '/',
            false,
        ), $ok ? 200 : 422)
            ->header('Cache-Control', 'private, no-store');
    }

    private function redirectToSubmissions(Request $r): Response
    {
        $return = array_filter([
            'form' => max(0, (int) $r->string('return_form', '0')) ?: null,
            'status' => in_array($r->string('return_status'), ['new', 'read', 'archived'], true)
                ? $r->string('return_status') : null,
            'q' => mb_substr($r->string('return_q'), 0, 190) ?: null,
            'page' => max(1, (int) $r->string('return_page', '1')),
        ], static fn ($value) => $value !== null && $value !== '');
        return $this->redirect($r, '/admin/forms/submissions?' . http_build_query($return));
    }

    private function csvCell(string $value): string
    {
        return preg_match('/^[=+\-@]/', ltrim($value)) ? "'" . $value : $value;
    }
}
