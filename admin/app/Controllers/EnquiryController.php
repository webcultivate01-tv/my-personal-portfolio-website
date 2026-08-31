<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Lead;
use App\Models\LeadNote;

/**
 * Enquiry Management — enquiries sent through the public contact form.
 *
 * Both admins and managers can view enquiries, star them as Important,
 * mark them as a Client enquiry (shown in green), and move them through
 * the status pipeline. Deleting an enquiry is restricted to admins.
 */
class EnquiryController extends Controller
{
    /** Filters offered in the toolbar. */
    private const FILTERS = ['all', 'important', 'client', 'unread', 'new', 'contacted', 'quoted', 'won', 'lost', 'spam'];

    /** List every enquiry (admins and managers). */
    public function index(): void
    {
        $this->requireAuth();

        $filter = $_GET['filter'] ?? 'all';
        if (!in_array($filter, self::FILTERS, true)) {
            $filter = 'all';
        }

        $dateFrom = $this->validDate($_GET['date_from'] ?? '');
        $dateTo   = $this->validDate($_GET['date_to'] ?? '');

        $leads = new Lead();

        $this->view('enquiries/index', [
            'title'         => 'Enquiries',
            'active'        => 'enquiries',
            'csrf'          => $this->csrfToken(),
            'filter'        => $filter,
            'dateFrom'      => $dateFrom ?? '',
            'dateTo'        => $dateTo ?? '',
            'enquiries'     => $leads->list($filter, $dateFrom, $dateTo),
            'total'         => $leads->totalCount(),
            'newCount'      => $leads->countByStatus('new'),
            'unreadCount'   => $leads->countUnread(),
            'importantCount'=> $leads->countFlag('is_important'),
            'clientCount'   => $leads->countFlag('is_client'),
            'statuses'      => Lead::STATUSES,
        ]);
    }

    /**
     * Printable PDF-style export of the enquiry list (admins only).
     * Honours the same filter + date range as the list page; the browser's
     * "Print → Save as PDF" turns it into an actual PDF download.
     */
    public function exportPdf(): void
    {
        $this->requireAdmin();

        $filter = $_GET['filter'] ?? 'all';
        if (!in_array($filter, self::FILTERS, true)) {
            $filter = 'all';
        }

        $dateFrom = $this->validDate($_GET['date_from'] ?? '');
        $dateTo   = $this->validDate($_GET['date_to'] ?? '');

        $leads = new Lead();

        $this->view('enquiries/pdf', [
            'title'       => 'Enquiries Export',
            'filter'      => $filter,
            'dateFrom'    => $dateFrom ?? '',
            'dateTo'      => $dateTo ?? '',
            'enquiries'   => $leads->list($filter, $dateFrom, $dateTo),
            'generatedAt' => date('M j, Y \a\t g:ia'),
        ], null);
    }

    /** Validate a 'YYYY-MM-DD' date string; returns null when blank or malformed. */
    private function validDate(string $value): ?string
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return ($d !== false && $d->format('Y-m-d') === $value) ? $value : null;
    }

    /** Read one enquiry in full. */
    public function show(): void
    {
        $this->requireAuth();

        $id      = (int) ($_GET['id'] ?? 0);
        $leads   = new Lead();
        $enquiry = $leads->find($id);

        if ($enquiry === null) {
            $this->flash('error', 'That enquiry no longer exists.');
            $this->redirect('/enquiries');
        }

        // Opening it clears the red "new" dot.
        $leads->markRead($id);

        $this->view('enquiries/show', [
            'title'    => 'Enquiry from ' . $enquiry['name'],
            'active'   => 'enquiries',
            'csrf'     => $this->csrfToken(),
            'enquiry'  => $enquiry,
            'statuses' => Lead::STATUSES,
            'notes'    => (new LeadNote())->forLead($id),
        ]);
    }

    /** Toggle the Important star. */
    public function toggleImportant(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $id = $this->intInput('id');
        (new Lead())->toggleImportant($id);
        $this->redirectBack();
    }

    /** Toggle the "this is a client" flag (green). */
    public function toggleClient(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $id = $this->intInput('id');
        (new Lead())->toggleClient($id);
        $this->redirectBack();
    }

    /** Move an enquiry to a new status. */
    public function updateStatus(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $id     = $this->intInput('id');
        $status = $this->input('status');
        (new Lead())->updateStatus($id, $status);
        $this->flash('success', 'Status updated.');
        $this->redirectBack();
    }

    /** Append a note to an enquiry's timeline. */
    public function addNote(): void
    {
        $this->requireAuth();
        $this->verifyCsrf();

        $id   = $this->intInput('id');
        $note = $this->input('note');

        if ($note !== '') {
            (new LeadNote())->add($id, Auth::id(), $note);
            $this->flash('success', 'Note added.');
        }
        $this->redirect('/enquiries/view?id=' . $id);
    }

    /** Permanently delete an enquiry (admins only). */
    public function destroy(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id = $this->intInput('id');
        (new Lead())->delete($id);
        $this->flash('success', 'Enquiry deleted.');
        $this->redirect('/enquiries');
    }

    /**
     * Return to the list, preserving the active filter when we came from it.
     * Falls back to the plain list.
     */
    private function redirectBack(): void
    {
        $ref = $_SERVER['HTTP_REFERER'] ?? '';
        // Only trust a same-app referer to avoid open-redirect surprises.
        if ($ref !== '' && strpos($ref, BASE_PATH . '/enquiries') !== false) {
            header('Location: ' . $ref);
            exit;
        }
        $this->redirect('/enquiries');
    }
}
