<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\MonthlyClient;
use App\Models\MonthlyInvoice;
use App\Models\MonthlyPayment;

/**
 * Monthly Clients — admins manage, managers read.
 *
 * The read actions (index, show, invoice, receipt) only call requireAuth(), so
 * a manager can see the retainers, what was invoiced and what is still due.
 * Every action that writes still starts with requireAdmin(), and the views hide
 * those controls from a manager via $canManage.
 *
 * One place for everything about a client on a recurring retainer: their
 * contract, the invoice raised for each billing cycle, every payment taken
 * against those invoices (in full or in parts), the receipt each payment
 * produces, and what is still due or overdue.
 *
 * The rules the module guarantees, all enforced here:
 *
 *   • Two invoices can never cover the same client and the same billing
 *     period — checked before the insert, and backed by a unique key.
 *   • A payment can never be larger than what is still outstanding on its
 *     invoice, so a balance can never go negative.
 *   • A paused or cancelled client gets no new invoices at all.
 *   • Cancelling or pausing never deletes an invoice, a payment or a receipt.
 *   • Amount paid, balance due and whether an invoice is paid, partially paid
 *     or overdue are all derived, never typed in — see the models.
 */
class MonthlyClientController extends Controller
{
    /** Dashboard, due/overdue buckets, recent activity and the searchable client list. */
    public function index(): void
    {
        $this->requireAuth();

        $clients  = new MonthlyClient();
        $invoices = new MonthlyInvoice();

        $filters = [
            'q'      => trim((string) ($_GET['q'] ?? '')),
            'status' => (string) ($_GET['status'] ?? ''),
            'sort'   => (string) ($_GET['sort'] ?? 'name'),
            'dir'    => (string) ($_GET['dir'] ?? 'asc'),
        ];
        if (!in_array($filters['status'], MonthlyClient::FILTERS, true)) {
            $filters['status'] = '';
        }

        // Which slice of the due/overdue panel is on show. Overdue leads,
        // because it is the only bucket that needs chasing today.
        $bucket = (string) ($_GET['bucket'] ?? 'overdue');
        if (!array_key_exists($bucket, MonthlyInvoice::BUCKETS)) {
            $bucket = 'overdue';
        }

        $this->view('monthly/index', [
            'title'         => 'Monthly Clients',
            'active'        => 'monthly',
            'csrf'          => $this->csrfToken(),
            'clients'       => $clients->search($filters),
            'counts'        => $clients->counts(),
            'summary'       => $clients->dashboard(),
            'dueForBilling' => $clients->dueForBilling(),
            'bucket'        => $bucket,
            'bucketRows'    => $invoices->bucket($bucket),
            'bucketCounts'  => $invoices->bucketCounts(),
            'recentInvoices' => $invoices->recent(),
            'recentPayments' => (new MonthlyPayment())->recent(),
            'filters'       => $filters,
            'frequencies'   => array_keys(MonthlyClient::FREQUENCY_MONTHS),
            'methods'       => MonthlyClient::METHODS,
            'terms'         => array_keys(MonthlyClient::TERM_DAYS),
            'today'         => date('Y-m-d'),
            'openCreateForm' => isset($_GET['new']),
            'canManage'     => Auth::isAdmin(),
        ]);
    }

    /** One recurring client in full: details, money, invoices, payments, history. */
    public function show(): void
    {
        $this->requireAuth();

        $id     = (int) ($_GET['id'] ?? 0);
        $client = (new MonthlyClient())->find($id);
        if ($client === null) {
            $this->flash('error', 'That monthly client no longer exists.');
            $this->redirect('/monthly-clients');
        }

        // What the next invoice would come to, so the generate form opens
        // pre-filled with the right figures.
        $preview = MonthlyClient::amountsFor(
            (float) $client['monthly_amount'],
            (string) $client['billing_frequency'],
            (string) $client['discount_type'],
            (float) $client['discount_value'],
            (float) $client['tax_percent']
        );

        // The stored schedule, not the display one — a paused client shows no
        // next billing date, but the form still needs the period it would cover.
        $periodStart = (string) ($client['scheduled_billing_date'] ?: $client['start_date']);

        $this->view('monthly/show', [
            'title'       => $client['client_name'],
            'active'      => 'monthly',
            'csrf'        => $this->csrfToken(),
            'client'      => $client,
            'invoices'    => (new MonthlyInvoice())->forClient($id),
            'payments'    => (new MonthlyPayment())->forClient($id),
            'pauses'      => (new MonthlyClient())->pauses($id),
            'preview'     => $preview,
            'periodStart' => $periodStart,
            'periodEnd'   => MonthlyClient::periodEnd($periodStart, (string) $client['billing_frequency']),
            'dueDate'     => MonthlyClient::dueDateFor(date('Y-m-d'), (string) $client['payment_terms']),
            'frequencies' => array_keys(MonthlyClient::FREQUENCY_MONTHS),
            'methods'     => MonthlyClient::METHODS,
            'terms'       => array_keys(MonthlyClient::TERM_DAYS),
            'payMethods'  => MonthlyPayment::METHODS,
            'today'       => date('Y-m-d'),
            // "Record payment" links from the dashboard carry ?pay=<invoice id>
            // so the payment form opens with that invoice already picked.
            'payInvoiceId' => (int) ($_GET['pay'] ?? 0),
            'canManage'    => Auth::isAdmin(),
        ]);
    }

    // ---------------- Client ----------------

    /** Add a recurring client. Billing starts from their start date. */
    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $data  = $this->collectClientInput();
        $error = $this->validateClient($data);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/monthly-clients?new=1');
        }

        $id = (new MonthlyClient())->create($data, Auth::id());
        $this->flash('success', $data['client_name'] . ' was added as a monthly client.');
        $this->redirect('/monthly-clients/view?id=' . $id);
    }

    /** Save changes to a client's details, contract or rate. */
    public function update(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id      = $this->intInput('id');
        $clients = new MonthlyClient();
        if ($clients->findRaw($id) === null) {
            $this->flash('error', 'That monthly client no longer exists.');
            $this->redirect('/monthly-clients');
        }

        $data  = $this->collectClientInput();
        $error = $this->validateClient($data);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/monthly-clients/view?id=' . $id);
        }

        $clients->update($id, $data);
        $this->flash('success', 'Details updated. Existing invoices keep the rate they were raised at.');
        $this->redirect('/monthly-clients/view?id=' . $id);
    }

    /** Pause billing — no new invoice is generated while a client is paused. */
    public function pause(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id      = $this->intInput('id');
        $clients = new MonthlyClient();
        $client  = $clients->findRaw($id);
        if ($client === null) {
            $this->flash('error', 'That monthly client no longer exists.');
            $this->redirect('/monthly-clients');
        }
        if ($client['status'] === 'cancelled') {
            $this->flash('error', 'A cancelled client is already off billing — there is nothing to pause.');
            $this->redirect('/monthly-clients/view?id=' . $id);
        }

        $pausedOn   = $this->input('paused_on');
        $resumeDate = $this->input('resume_date');
        if (!$this->isDate($pausedOn)) {
            $this->flash('error', 'A pause needs a valid pause date.');
            $this->redirect('/monthly-clients/view?id=' . $id);
        }
        if ($resumeDate !== '' && !$this->isDate($resumeDate)) {
            $this->flash('error', 'That resume date is not a valid date.');
            $this->redirect('/monthly-clients/view?id=' . $id);
        }

        $clients->pause($id, $pausedOn, $this->input('pause_reason'), $resumeDate ?: null, $this->input('pause_notes'), Auth::id());

        $this->flash('success', 'Billing paused. No new invoice will be generated until you resume it.');
        $this->redirect('/monthly-clients/view?id=' . $id);
    }

    /** Resume billing, picking up from the date given. */
    public function resume(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id      = $this->intInput('id');
        $clients = new MonthlyClient();
        $client  = $clients->findRaw($id);
        if ($client === null || $client['status'] !== 'paused') {
            $this->flash('error', 'That client is not currently paused.');
            $this->redirect('/monthly-clients' . ($client !== null ? '/view?id=' . $id : ''));
        }

        $resumedOn = $this->input('resumed_on');
        $nextDate  = $this->input('next_billing_date');
        if (!$this->isDate($resumedOn)) {
            $this->flash('error', 'A resume needs a valid date.');
            $this->redirect('/monthly-clients/view?id=' . $id);
        }
        // Billing picks up where it left off unless a new start is given.
        if (!$this->isDate($nextDate)) {
            $nextDate = (string) $client['next_billing_date'];
        }

        $clients->resume($id, $resumedOn, $nextDate);
        $this->flash('success', 'Billing resumed. The next invoice covers the period from ' . date('j M Y', strtotime($nextDate)) . '.');
        $this->redirect('/monthly-clients/view?id=' . $id);
    }

    /**
     * Cancel a client. Future billing stops; every invoice, payment and
     * receipt they already have is kept exactly as it is.
     */
    public function cancel(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id      = $this->intInput('id');
        $clients = new MonthlyClient();
        $client  = $clients->findRaw($id);
        if ($client === null) {
            $this->flash('error', 'That monthly client no longer exists.');
            $this->redirect('/monthly-clients');
        }

        $cancelledOn = $this->input('cancelled_on');
        if (!$this->isDate($cancelledOn)) {
            $this->flash('error', 'A cancellation needs a valid date.');
            $this->redirect('/monthly-clients/view?id=' . $id);
        }

        $clients->cancel($id, $cancelledOn, $this->input('cancellation_reason'), $this->input('cancellation_notes'));

        $this->flash('success', 'Client cancelled. Their invoices, payments and receipts are all still here.');
        $this->redirect('/monthly-clients/view?id=' . $id);
    }

    /** Put a cancelled client back on recurring billing. */
    public function reactivate(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id      = $this->intInput('id');
        $clients = new MonthlyClient();
        $client  = $clients->findRaw($id);
        if ($client === null || $client['status'] !== 'cancelled') {
            $this->flash('error', 'That client is not cancelled.');
            $this->redirect('/monthly-clients' . ($client !== null ? '/view?id=' . $id : ''));
        }

        $nextDate = $this->input('next_billing_date');
        if (!$this->isDate($nextDate)) {
            $nextDate = (string) $client['next_billing_date'];
        }

        $clients->reactivate($id, $nextDate);
        $this->flash('success', 'Client is active again, billing from ' . date('j M Y', strtotime($nextDate)) . '.');
        $this->redirect('/monthly-clients/view?id=' . $id);
    }

    // ---------------- Invoices ----------------

    /** Generate the invoice for one billing cycle. */
    public function storeInvoice(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $clientId = $this->intInput('monthly_client_id');
        $clients  = new MonthlyClient();
        $client   = $clients->findRaw($clientId);
        if ($client === null) {
            $this->flash('error', 'That monthly client no longer exists.');
            $this->redirect('/monthly-clients');
        }

        // A paused or cancelled client receives no new billing records at all.
        if ($client['status'] !== 'active') {
            $this->flash('error', $client['status'] === 'paused'
                ? 'Billing is paused for this client — resume it before generating an invoice.'
                : 'This client is cancelled, so no new invoice can be raised for them.');
            $this->redirect('/monthly-clients/view?id=' . $clientId);
        }

        $invoiceDate = $this->input('invoice_date');
        $periodStart = $this->input('period_start');
        if (!$this->isDate($invoiceDate) || !$this->isDate($periodStart)) {
            $this->flash('error', 'An invoice needs a valid invoice date and billing period.');
            $this->redirect('/monthly-clients/view?id=' . $clientId);
        }

        // One invoice per client per billing period, never two.
        if ((new MonthlyInvoice())->findForPeriod($clientId, $periodStart) !== null) {
            $this->flash('error', 'An invoice already covers the period starting ' . date('j M Y', strtotime($periodStart)) . '.');
            $this->redirect('/monthly-clients/view?id=' . $clientId);
        }

        $frequency = (string) $client['billing_frequency'];
        $periodEnd = MonthlyClient::periodEnd($periodStart, $frequency);

        $dueDate = $this->input('due_date');
        if (!$this->isDate($dueDate)) {
            $dueDate = MonthlyClient::dueDateFor($invoiceDate, (string) $client['payment_terms']);
        }

        $recurring = $this->input('recurring_amount');
        if (!is_numeric($recurring) || (float) $recurring <= 0) {
            $this->flash('error', 'The recurring amount must be a number greater than zero.');
            $this->redirect('/monthly-clients/view?id=' . $clientId);
        }
        $recurring = round((float) $recurring, 2);

        $discount = $this->input('discount_amount');
        $discount = is_numeric($discount) ? round((float) $discount, 2) : 0.0;
        $discount = max(0.0, min($discount, $recurring));

        $taxPercent = $this->input('tax_percent');
        $taxPercent = is_numeric($taxPercent) ? max(0.0, round((float) $taxPercent, 2)) : 0.0;

        $taxable   = round($recurring - $discount, 2);
        $taxAmount = round($taxable * ($taxPercent / 100), 2);
        $total     = round($taxable + $taxAmount, 2);

        $invoiceId = (new MonthlyInvoice())->create([
            'monthly_client_id'   => $clientId,
            'invoice_date'        => $invoiceDate,
            'due_date'            => $dueDate,
            'period_start'        => $periodStart,
            'period_end'          => $periodEnd,
            'service_name'        => $this->input('service_name') ?: (string) $client['service_name'],
            'service_description' => $this->input('service_description') ?: (string) ($client['service_description'] ?? ''),
            'recurring_amount'    => $recurring,
            'discount_amount'     => $discount,
            'tax_percent'         => $taxPercent,
            'tax_amount'          => $taxAmount,
            'total_amount'        => $total,
            'status'              => $this->input('status'),
            'notes'               => $this->input('notes'),
        ], Auth::id());

        // Move billing on to the period after the one just invoiced. A
        // back-dated invoice never rewinds a client's schedule, so max() wins.
        $after = MonthlyClient::addMonths($periodStart, MonthlyClient::cycleMonths($frequency));
        if ($after !== null && $after > (string) $client['next_billing_date']) {
            $clients->advanceBilling($clientId, $after);
        }

        $this->flash('success', 'Invoice generated for ' . date('j M Y', strtotime($periodStart)) . ' – ' . date('j M Y', strtotime($periodEnd)) . '.');
        $this->redirect('/monthly-clients/invoice?id=' . $invoiceId);
    }

    /** Move an invoice between draft, sent and cancelled. */
    public function updateInvoiceStatus(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $invoices = new MonthlyInvoice();
        $id       = $this->intInput('id');
        $invoice  = $invoices->find($id);
        if ($invoice === null) {
            $this->flash('error', 'That invoice no longer exists.');
            $this->redirect('/monthly-clients');
        }

        $status = $this->input('status');
        if (!in_array($status, MonthlyInvoice::LIFECYCLE, true)) {
            $this->flash('error', 'Paid, partially paid and overdue are worked out from payments — they cannot be set by hand.');
            $this->redirect('/monthly-clients/view?id=' . (int) $invoice['monthly_client_id']);
        }
        if ($status === 'cancelled' && (float) $invoice['amount_paid'] > 0) {
            $this->flash('error', 'This invoice already has payments against it, so it cannot be cancelled.');
            $this->redirect('/monthly-clients/view?id=' . (int) $invoice['monthly_client_id']);
        }

        $invoices->updateStatus($id, $status);
        $this->flash('success', 'Invoice ' . $invoice['invoice_number'] . ' marked as ' . MonthlyInvoice::STATUS_LABELS[$status] . '.');
        $this->redirect('/monthly-clients/view?id=' . (int) $invoice['monthly_client_id']);
    }

    /** One invoice, rendered full-page and print-ready — this is also the "download PDF" view. */
    public function invoice(): void
    {
        $this->requireAuth();

        $id      = (int) ($_GET['id'] ?? 0);
        $invoice = (new MonthlyInvoice())->find($id);
        if ($invoice === null) {
            $this->flash('error', 'That invoice no longer exists.');
            $this->redirect('/monthly-clients');
        }

        $this->view('monthly/invoice', [
            'title'    => $invoice['invoice_number'],
            'invoice'  => $invoice,
            'payments' => (new MonthlyPayment())->forInvoice($id),
        ], null);
    }

    // ---------------- Payments ----------------

    /** Record a payment — in full or in part — against an invoice. */
    public function storePayment(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $invoices  = new MonthlyInvoice();
        $invoiceId = $this->intInput('invoice_id');
        $invoice   = $invoices->find($invoiceId);
        if ($invoice === null) {
            $this->flash('error', 'That invoice no longer exists.');
            $this->redirect('/monthly-clients');
        }

        $clientId = (int) $invoice['monthly_client_id'];
        $back     = '/monthly-clients/view?id=' . $clientId;

        if ($invoice['display_status'] === 'cancelled') {
            $this->flash('error', 'That invoice is cancelled — no payment can be recorded against it.');
            $this->redirect($back);
        }

        $date = $this->input('payment_date');
        if (!$this->isDate($date)) {
            $this->flash('error', 'A payment needs a valid payment date.');
            $this->redirect($back);
        }

        $amount = $this->input('amount');
        if (!is_numeric($amount) || (float) $amount <= 0) {
            $this->flash('error', 'The payment amount must be a number greater than zero.');
            $this->redirect($back);
        }
        $amount = round((float) $amount, 2);

        // A payment — partial or otherwise — can never exceed what is left.
        $outstanding = (float) $invoice['balance_due'];
        if ($outstanding <= 0.004) {
            $this->flash('error', 'Invoice ' . $invoice['invoice_number'] . ' is already fully paid.');
            $this->redirect($back);
        }
        if ($amount - $outstanding > 0.004) {
            $this->flash('error', 'That is more than the ₹' . number_format($outstanding, 2) . ' still outstanding on ' . $invoice['invoice_number'] . '.');
            $this->redirect($back);
        }

        $balanceAfter = round($outstanding - $amount, 2);

        $paymentId = (new MonthlyPayment())->create([
            'invoice_id'        => $invoiceId,
            'monthly_client_id' => $clientId,
            'payment_date'      => $date,
            'amount'            => $amount,
            'method'            => $this->input('method'),
            'reference'         => $this->input('reference'),
            'notes'             => $this->input('notes'),
        ], $balanceAfter, Auth::id());

        // A draft that has been paid has clearly gone out — move it to sent so
        // its derived status reads correctly from here on.
        if ($invoice['status'] === 'draft') {
            $invoices->updateStatus($invoiceId, 'sent');
        }

        $this->flash('success', $balanceAfter > 0.004
            ? 'Partial payment recorded. ₹' . number_format($balanceAfter, 2) . ' is still outstanding on ' . $invoice['invoice_number'] . '.'
            : 'Payment recorded. ' . $invoice['invoice_number'] . ' is now fully paid.');
        $this->redirect('/monthly-clients/receipt?id=' . $paymentId);
    }

    /** A payment receipt, rendered full-page and print-ready. */
    public function receipt(): void
    {
        $this->requireAuth();

        $id      = (int) ($_GET['id'] ?? 0);
        $payment = (new MonthlyPayment())->find($id);
        if ($payment === null) {
            $this->flash('error', 'That receipt no longer exists.');
            $this->redirect('/monthly-clients');
        }

        $this->view('monthly/receipt', [
            'title'   => $payment['receipt_number'],
            'payment' => $payment,
        ], null);
    }

    // ---------------- Helpers ----------------

    /** Read the add/edit client form into one array. */
    private function collectClientInput(): array
    {
        return [
            'client_name'         => $this->input('client_name'),
            'company'             => $this->input('company'),
            'email'               => $this->input('email'),
            'mobile'              => $this->input('mobile'),
            'billing_address'     => $this->input('billing_address'),
            'service_name'        => $this->input('service_name'),
            'service_description' => $this->input('service_description'),
            'monthly_amount'      => $this->input('monthly_amount'),
            'billing_frequency'   => $this->input('billing_frequency'),
            'discount_type'       => $this->input('discount_type', 'none'),
            'discount_value'      => $this->input('discount_value'),
            'tax_percent'         => $this->input('tax_percent'),
            'payment_method'      => $this->input('payment_method'),
            'payment_terms'       => $this->input('payment_terms'),
            'start_date'          => $this->input('start_date'),
            'contract_end_date'   => $this->input('contract_end_date'),
            'renewal_date'        => $this->input('renewal_date'),
            'contract_notes'      => $this->input('contract_notes'),
            'notes'               => $this->input('notes'),
        ];
    }

    /** Whatever is wrong with the submitted client details, or null when it's fine. */
    private function validateClient(array $d): ?string
    {
        if ($d['client_name'] === '') {
            return 'A client name is required.';
        }
        if ($d['service_name'] === '') {
            return 'Give the recurring service a name — it is what the invoice bills for.';
        }
        if (!is_numeric($d['monthly_amount']) || (float) $d['monthly_amount'] <= 0) {
            return 'The monthly amount must be a number greater than zero.';
        }
        if (!$this->isDate($d['start_date'])) {
            return 'A start date is required — billing runs from it.';
        }
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
            return 'That email address does not look right.';
        }
        if ($d['contract_end_date'] !== '') {
            if (!$this->isDate($d['contract_end_date'])) {
                return 'That contract end date is not a valid date.';
            }
            if ($d['contract_end_date'] < $d['start_date']) {
                return 'The contract cannot end before it starts.';
            }
        }
        if ($d['renewal_date'] !== '' && !$this->isDate($d['renewal_date'])) {
            return 'That renewal date is not a valid date.';
        }
        if ($d['discount_type'] === 'percent' && (float) $d['discount_value'] > 100) {
            return 'A percentage discount cannot be more than 100%.';
        }
        if ($d['discount_type'] !== 'none' && (float) $d['discount_value'] < 0) {
            return 'A discount cannot be negative.';
        }
        return null;
    }

    /** True when a string is a usable Y-m-d date. */
    private function isDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}
