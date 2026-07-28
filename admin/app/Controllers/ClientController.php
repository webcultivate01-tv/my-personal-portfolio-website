<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Client;
use App\Models\ClientMeeting;
use App\Models\ClientInvoice;
use App\Models\ClientPayment;

/**
 * Client Management — admins only.
 *
 * Every action starts with requireAdmin(), so a manager can neither see the
 * module in the sidebar nor reach any of its URLs directly. An admin keeps
 * one page per client holding their details, every meeting held (with date and
 * time), all invoices raised, and all payments received.
 */
class ClientController extends Controller
{
    /** The client list with per-client meeting counts and money totals. */
    public function index(): void
    {
        $this->requireAdmin();

        $clients = (new Client())->allWithSummary();

        $totalCost     = 0.0;
        $totalInvoiced = 0.0;
        $totalPaid     = 0.0;
        $totalMeetings = 0;
        foreach ($clients as $c) {
            $totalCost     += (float) $c['project_cost'];
            $totalInvoiced += (float) $c['total_invoiced'];
            $totalPaid     += (float) $c['total_paid'];
            $totalMeetings += (int) $c['meeting_count'];
        }

        $this->view('clients/index', [
            'title'         => 'Client Management',
            'active'        => 'clients',
            'csrf'          => $this->csrfToken(),
            'clients'       => $clients,
            'totalCost'     => $totalCost,
            'totalInvoiced' => $totalInvoiced,
            'totalPaid'     => $totalPaid,
            'totalMeetings' => $totalMeetings,
        ]);
    }

    /** One client's full record: details, meetings, invoices, payments. */
    public function show(): void
    {
        $this->requireAdmin();

        $id     = (int) ($_GET['id'] ?? 0);
        $client = (new Client())->find($id);
        if ($client === null) {
            $this->flash('error', 'That client no longer exists.');
            $this->redirect('/clients');
        }

        $invoices = (new ClientInvoice())->forClient($id);
        $invoiced = (new ClientInvoice())->totalForClient($id);
        $paid     = (new ClientPayment())->totalForClient($id);

        $this->view('clients/show', [
            'title'    => $client['name'],
            'active'   => 'clients',
            'csrf'     => $this->csrfToken(),
            'client'   => $client,
            'meetings' => (new ClientMeeting())->forClient($id),
            'invoices' => $invoices,
            'payments' => (new ClientPayment())->forClient($id),
            'invoiced' => $invoiced,
            'paid'     => $paid,
            'statuses' => ClientInvoice::STATUSES,
            'methods'  => ClientPayment::METHODS,
        ]);
    }

    /** Add a new client. */
    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $data  = $this->collectClientInput();
        $error = $this->validateClient($data);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/clients');
        }

        $id = (new Client())->create($data, Auth::id());
        $this->flash('success', 'Client "' . $data['name'] . '" was added.');
        $this->redirect('/clients/view?id=' . $id);
    }

    /** Save changes to a client's details. */
    public function update(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id      = $this->intInput('id');
        $clients = new Client();
        if ($clients->find($id) === null) {
            $this->flash('error', 'That client no longer exists.');
            $this->redirect('/clients');
        }

        $data  = $this->collectClientInput();
        $error = $this->validateClient($data);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/clients/view?id=' . $id);
        }

        $clients->update($id, $data);
        $this->flash('success', 'Client details updated.');
        $this->redirect('/clients/view?id=' . $id);
    }

    /** Delete a client along with their meetings, invoices and payments. */
    public function destroy(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id      = $this->intInput('id');
        $clients = new Client();
        $client  = $clients->find($id);
        if ($client === null) {
            $this->flash('error', 'That client no longer exists.');
            $this->redirect('/clients');
        }

        $clients->delete($id);
        $this->flash('success', 'Client "' . $client['name'] . '" and all their records were removed.');
        $this->redirect('/clients');
    }

    // ---------- Meetings ----------

    /** Log a meeting held with a client. */
    public function storeMeeting(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $clientId = $this->intInput('client_id');
        $date     = $this->input('meeting_date');
        $time     = $this->input('meeting_time');
        $duration = $this->input('duration_minutes');
        $notes    = $this->input('notes');

        if ((new Client())->find($clientId) === null) {
            $this->flash('error', 'That client no longer exists.');
            $this->redirect('/clients');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !preg_match('/^\d{2}:\d{2}$/', $time)) {
            $this->flash('error', 'A meeting needs a valid date and time.');
            $this->redirect('/clients/view?id=' . $clientId);
        }

        (new ClientMeeting())->add(
            $clientId,
            $date . ' ' . $time . ':00',
            $duration === '' ? null : max(0, (int) $duration),
            $notes,
            Auth::id()
        );
        $this->flash('success', 'Meeting logged.');
        $this->redirect('/clients/view?id=' . $clientId);
    }

    /** Remove a logged meeting. */
    public function destroyMeeting(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $clientId = $this->intInput('client_id');
        (new ClientMeeting())->delete($this->intInput('id'));
        $this->flash('success', 'Meeting removed.');
        $this->redirect('/clients/view?id=' . $clientId);
    }

    // ---------- Invoices ----------

    /** Raise a new invoice for a client. */
    public function storeInvoice(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $clientId = $this->intInput('client_id');
        $number   = $this->input('invoice_number');
        $amount   = $this->input('amount');
        $issue    = $this->input('issue_date');
        $due      = $this->input('due_date');
        $notes    = $this->input('notes');

        if ((new Client())->find($clientId) === null) {
            $this->flash('error', 'That client no longer exists.');
            $this->redirect('/clients');
        }
        if ($number === '') {
            $this->flash('error', 'An invoice needs an invoice number.');
            $this->redirect('/clients/view?id=' . $clientId);
        }
        if (!is_numeric($amount) || (float) $amount <= 0) {
            $this->flash('error', 'Invoice amount must be a number greater than zero.');
            $this->redirect('/clients/view?id=' . $clientId);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $issue)) {
            $this->flash('error', 'An invoice needs a valid issue date.');
            $this->redirect('/clients/view?id=' . $clientId);
        }
        if ($due !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $due)) {
            $this->flash('error', 'The due date is not a valid date.');
            $this->redirect('/clients/view?id=' . $clientId);
        }

        (new ClientInvoice())->add($clientId, $number, (float) $amount, $issue, $due, $notes);
        $this->flash('success', 'Invoice ' . $number . ' created.');
        $this->redirect('/clients/view?id=' . $clientId);
    }

    /** Move an invoice to a new status. */
    public function updateInvoiceStatus(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $clientId = $this->intInput('client_id');
        (new ClientInvoice())->updateStatus($this->intInput('id'), $this->input('status'));
        $this->flash('success', 'Invoice status updated.');
        $this->redirect('/clients/view?id=' . $clientId);
    }

    /** Delete an invoice. */
    public function destroyInvoice(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $clientId = $this->intInput('client_id');
        (new ClientInvoice())->delete($this->intInput('id'));
        $this->flash('success', 'Invoice deleted.');
        $this->redirect('/clients/view?id=' . $clientId);
    }

    // ---------- Payments ----------

    /** Record a payment received from a client. */
    public function storePayment(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $clientId  = $this->intInput('client_id');
        $invoiceId = $this->intInput('invoice_id');
        $amount    = $this->input('amount');
        $date      = $this->input('payment_date');
        $method    = $this->input('method');
        $notes     = $this->input('notes');

        if ((new Client())->find($clientId) === null) {
            $this->flash('error', 'That client no longer exists.');
            $this->redirect('/clients');
        }
        if (!is_numeric($amount) || (float) $amount <= 0) {
            $this->flash('error', 'Payment amount must be a number greater than zero.');
            $this->redirect('/clients/view?id=' . $clientId);
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $this->flash('error', 'A payment needs a valid date.');
            $this->redirect('/clients/view?id=' . $clientId);
        }

        // Only accept an invoice link when that invoice really belongs to this client.
        if ($invoiceId > 0) {
            $invoice = (new ClientInvoice())->find($invoiceId);
            if ($invoice === null || (int) $invoice['client_id'] !== $clientId) {
                $invoiceId = 0;
            }
        }

        (new ClientPayment())->add(
            $clientId,
            $invoiceId > 0 ? $invoiceId : null,
            (float) $amount,
            $date,
            $method,
            $notes
        );
        $this->flash('success', 'Payment recorded.');
        $this->redirect('/clients/view?id=' . $clientId);
    }

    /** Remove a recorded payment. */
    public function destroyPayment(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $clientId = $this->intInput('client_id');
        (new ClientPayment())->delete($this->intInput('id'));
        $this->flash('success', 'Payment removed.');
        $this->redirect('/clients/view?id=' . $clientId);
    }

    // ---------- Helpers ----------

    private function collectClientInput(): array
    {
        return [
            'name'         => $this->input('name'),
            'company'      => $this->input('company'),
            'email'        => strtolower($this->input('email')),
            'phone'        => $this->input('phone'),
            'address'      => $this->input('address'),
            'services'     => $this->input('services'),
            'project_cost' => $this->input('project_cost'),
            'notes'        => $this->input('notes'),
        ];
    }

    /** Returns an error message, or null when the client details are fine. */
    private function validateClient(array $d): ?string
    {
        if ($d['name'] === '') {
            return 'A client needs a name.';
        }
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            return 'That email address is not valid.';
        }
        if ($d['phone'] !== '' && !preg_match('/^\d{10}$/', $d['phone'])) {
            return 'Phone number must be exactly 10 digits.';
        }
        if ($d['project_cost'] !== '' && (!is_numeric($d['project_cost']) || (float) $d['project_cost'] < 0)) {
            return 'Total project cost must be a number that is zero or more.';
        }
        return null;
    }
}
