<?php
declare(strict_types=1);

namespace Admin\Controllers;

use Core\Forms\FormService;
use Core\Http\Request;
use Core\Http\Response;

final class FormsController extends Controller
{
    private function forms(): FormService { return new FormService($this->db()); }
    private function can(): bool
    {
        $u = $this->user();
        return $u !== null && $this->auth()->has($u, 'forms.manage');
    }

    public function index(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $editId = (int) $r->input('edit', 0);
        return $this->page($r, 'forms/index', [
            'forms' => $this->forms()->all(),
            'editing' => $editId ? $this->forms()->find($editId) : null,
        ], 'Forms');
    }

    public function save(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        [$id, $error] = $this->forms()->save(
            ($v = (int) $r->string('id')) > 0 ? $v : null,
            $r->string('name'), (string) ($r->post['fields_json'] ?? '[]'),
            $r->string('recipient'), $r->string('subject'), $r->string('success_message'),
            $r->string('active') === '1', $this->user()->id
        );
        if (!$error) $this->c->get(\Core\Audit\AuditLog::class)->record('forms.saved', $this->user()->id, ['form' => $id]);
        $this->flash($error ? 'error' : 'ok', $error ?? 'Form saved. Use form ID ' . $id . ' in a page Form block.');
        return $this->redirect($r, '/admin/forms' . ($error ? '' : '?edit=' . $id));
    }

    public function archive(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) $r->attributes['id'];
        $this->forms()->archive($id);
        $this->c->get(\Core\Audit\AuditLog::class)->record('forms.archived', $this->user()->id, ['form' => $id]);
        $this->flash('ok', 'Form archived; existing submissions were preserved.');
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
        if ($ok) $this->c->get(\Core\Audit\AuditLog::class)->record('forms.submission_status', $this->user()->id, ['submission' => $id, 'status' => $status]);
        $this->flash($ok ? 'ok' : 'error', $ok ? 'Submission status updated.' : 'Submission or status not found.');
        $return = array_filter([
            'form' => max(0, (int) $r->string('return_form', '0')) ?: null,
            'status' => in_array($r->string('return_status'), ['new','read','archived'], true) ? $r->string('return_status') : null,
            'q' => mb_substr($r->string('return_q'), 0, 190) ?: null,
            'page' => max(1, (int) $r->string('return_page', '1')),
        ], static fn ($value) => $value !== null && $value !== '');
        return $this->redirect($r, '/admin/forms/submissions?' . http_build_query($return));
    }

    public function export(Request $r): Response
    {
        if (!$this->can()) return $this->forbidden();
        $id = (int) $r->input('form', 0);
        $status = in_array($r->string('status'), ['new', 'read', 'archived'], true) ? $r->string('status') : '';
        $search = mb_substr($r->string('q'), 0, 190);
        $rows = $this->forms()->submissions($id ?: null, 1000, $status, $search);
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, ['id','form','status','submitted_at','data']);
        foreach ($rows as $row) fputcsv($stream, [$row['id'],$row['form_name'],$row['status'],$row['created_at'],$row['data_json']]);
        rewind($stream); $csv = (string) stream_get_contents($stream); fclose($stream);
        return Response::text($csv)->header('Content-Type', 'text/csv; charset=utf-8')
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
        [$submissionId, $errors, $data] = $this->forms()->submit($id, $r->post, hash_hmac('sha256', $r->ip(), $key));
        if ($errors !== []) return $this->publicResult($r, implode(' ', $errors), false);
        $form = $this->forms()->find($id);
        if ($form && !empty($form['recipient'])) {
            $lines = [];
            foreach ($data as $name => $value) $lines[] = ucwords(str_replace('_',' ',$name)) . ': ' . $value;
            $this->c->get(\Core\Mail\SystemMail::class)->queue(
                (string) $form['recipient'], (string) ($form['subject'] ?: 'New website form submission'),
                implode("\n", $lines), 'form:' . $submissionId
            );
        }
        return $this->publicResult($r, (string) ($form['success_message'] ?? 'Thank you. Your message has been received.'), true);
    }

    private function publicResult(Request $r, string $message, bool $ok): Response
    {
        return Response::html('<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Form response</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#07111d;color:#e5eef7;font-family:system-ui;padding:1rem}.card{max-width:36rem;padding:2rem;border-radius:1rem;background:#102235;border:1px solid #ffffff20}a{color:#7dd3fc}</style></head><body><main class="card"><h1>' . ($ok ? 'Thank you' : 'Please check the form') . '</h1><p>' . e($message) . '</p><p><a href="' . e_attr(rtrim($r->basePath, '/') . '/') . '">Return to the website</a></p></main></body></html>', $ok ? 200 : 422)
            ->header('Cache-Control', 'private, no-store');
    }
}
