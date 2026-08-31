<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Client;
use App\Models\Project;
use App\Models\HostingService;
use App\Models\HostingRenewal;

/**
 * Hosting & Domain Management — admins only.
 *
 * Keeps one record per hosting plan or domain bought for a client, works out
 * how many days are left before it expires, and surfaces everything that
 * needs renewing so no client's hosting quietly lapses.
 *
 * Every action starts with requireAdmin(), matching Client Management: these
 * records carry costs and provider account references, so managers can
 * neither see the module in the sidebar nor reach its URLs directly.
 *
 * Note on credentials: this module deliberately stores no passwords. It keeps
 * the control-panel URL and a *pointer* to where the login lives (a password
 * manager entry), so a plain-text password never ends up in the database.
 */
class HostingController extends Controller
{
    /** The Hosting dashboard: summary cards, reminders, upcoming renewals, the full list. */
    public function index(): void
    {
        $this->requireAdmin();

        $hosting = new HostingService();

        $filters = [
            'q'        => trim((string) ($_GET['q'] ?? '')),
            'status'   => (string) ($_GET['status'] ?? ''),
            'type'     => (string) ($_GET['type'] ?? ''),
            'provider' => (string) ($_GET['provider'] ?? ''),
            'cycle'    => (string) ($_GET['cycle'] ?? ''),
            'from'     => (string) ($_GET['from'] ?? ''),
            'to'       => (string) ($_GET['to'] ?? ''),
            'sort'     => (string) ($_GET['sort'] ?? 'renewal_date'),
            'dir'      => (string) ($_GET['dir'] ?? 'asc'),
        ];

        $this->view('hosting/index', [
            'title'      => 'Hosting & Domains',
            'active'     => 'hosting',
            'csrf'       => $this->csrfToken(),
            'records'    => $hosting->search($filters),
            'summary'    => $hosting->summary(),
            'upcoming'   => $hosting->needingAttention(),
            'providers'  => $hosting->providers(),
            'clients'    => (new Client())->allWithSummary(),
            'projects'   => (new Project())->allForSelect(),
            'filters'    => $filters,
            'cycles'     => array_keys(HostingService::CYCLE_MONTHS),
            'today'      => date('Y-m-d'),
        ]);
    }

    /** One record in full: client, website, hosting details and renewal history. */
    public function show(): void
    {
        $this->requireAdmin();

        $id      = (int) ($_GET['id'] ?? 0);
        $record  = (new HostingService())->find($id);
        if ($record === null) {
            $this->flash('error', 'That hosting record no longer exists.');
            $this->redirect('/hosting');
        }

        $renewals = new HostingRenewal();

        $this->view('hosting/show', [
            'title'       => $record['domain'] ?: $record['website_name'] ?: $record['client_name'],
            'active'      => 'hosting',
            'csrf'        => $this->csrfToken(),
            'record'      => $record,
            'renewals'    => $renewals->forService($id),
            'renewedSum'  => $renewals->totalForService($id),
            'clients'     => (new Client())->allWithSummary(),
            'projects'    => (new Project())->allForSelect(),
            'cycles'      => array_keys(HostingService::CYCLE_MONTHS),
            'payStatuses' => HostingRenewal::PAYMENT_STATUSES,
            'today'       => date('Y-m-d'),
        ]);
    }

    /** Add a hosting plan or a domain. */
    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $data  = $this->collectInput();
        $error = $this->validate($data);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/hosting');
        }

        $id = (new HostingService())->create($data, Auth::id());
        $this->flash('success', ucfirst($data['service_type']) . ' record for "' . $data['client_name'] . '" was added.');
        $this->redirect('/hosting/view?id=' . $id);
    }

    /** Save changes to a record. */
    public function update(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id      = $this->intInput('id');
        $hosting = new HostingService();
        if ($hosting->find($id) === null) {
            $this->flash('error', 'That hosting record no longer exists.');
            $this->redirect('/hosting');
        }

        $data  = $this->collectInput();
        $error = $this->validate($data);
        if ($error !== null) {
            $this->flash('error', $error);
            $this->redirect('/hosting/view?id=' . $id);
        }

        $hosting->update($id, $data);
        $this->flash('success', 'Hosting details updated.');
        $this->redirect('/hosting/view?id=' . $id);
    }

    /** Delete a record and its renewal history. */
    public function destroy(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id      = $this->intInput('id');
        $hosting = new HostingService();
        $record  = $hosting->find($id);
        if ($record === null) {
            $this->flash('error', 'That hosting record no longer exists.');
            $this->redirect('/hosting');
        }

        $hosting->delete($id);
        $this->flash('success', 'Hosting record for "' . $record['client_name'] . '" was removed.');
        $this->redirect('/hosting');
    }

    /**
     * Mark a record as renewed.
     *
     * Moves the expiry date forward, stamps when it was renewed (which takes
     * it straight back out of the urgent list and into "Renewed this month"),
     * and files the renewal in the record's history.
     */
    public function renew(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $id      = $this->intInput('id');
        $hosting = new HostingService();
        $record  = $hosting->find($id);
        if ($record === null) {
            $this->flash('error', 'That hosting record no longer exists.');
            $this->redirect('/hosting');
        }

        $renewalDate = $this->input('renewal_date');
        $newExpiry   = $this->input('new_expiry');
        $amount      = $this->input('amount');

        if (!$this->isDate($renewalDate)) {
            $this->flash('error', 'A renewal needs a valid renewal date.');
            $this->redirect('/hosting/view?id=' . $id);
        }

        // Blank new expiry: work it out from the current expiry + billing cycle.
        if ($newExpiry === '') {
            $newExpiry = HostingService::addCycle(
                (string) $record['renewal_date'],
                (string) $record['billing_cycle'],
                $record['custom_cycle_months'] !== null ? (int) $record['custom_cycle_months'] : null
            ) ?? '';
        }
        if (!$this->isDate($newExpiry)) {
            $this->flash('error', 'Enter the new expiry date (it could not be calculated from the billing cycle).');
            $this->redirect('/hosting/view?id=' . $id);
        }
        if ($amount !== '' && (!is_numeric($amount) || (float) $amount < 0)) {
            $this->flash('error', 'Renewal amount must be a number that is zero or more.');
            $this->redirect('/hosting/view?id=' . $id);
        }

        (new HostingRenewal())->add($id, [
            'renewal_date'      => $renewalDate,
            'previous_expiry'   => (string) $record['renewal_date'],
            'new_expiry'        => $newExpiry,
            'amount'            => $amount,
            'payment_status'    => $this->input('payment_status', 'paid'),
            'payment_reference' => $this->input('payment_reference'),
            'notes'             => $this->input('notes'),
        ], Auth::id());

        $hosting->applyRenewal($id, $newExpiry, $renewalDate);

        $this->flash('success', 'Renewed — next expiry is ' . date('j M Y', strtotime($newExpiry)) . '.');
        $this->redirect('/hosting/view?id=' . $id);
    }

    /** Remove a renewal-history entry (does not move the expiry date back). */
    public function destroyRenewal(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $hostingId = $this->intInput('hosting_id');
        (new HostingRenewal())->delete($this->intInput('id'));
        $this->flash('success', 'Renewal entry removed. Check the expiry date is still correct.');
        $this->redirect('/hosting/view?id=' . $hostingId);
    }

    // ---------- Helpers ----------

    /**
     * Read the add/edit form.
     *
     * When the admin leaves the renewal date blank, it is calculated from
     * purchase date + billing cycle (15 Sep 2026 + yearly => 15 Sep 2027).
     * The form fills the same value in through JavaScript, so this is the
     * server-side backstop — and either way the admin can overwrite it.
     */
    private function collectInput(): array
    {
        $type   = $this->input('service_type', 'hosting');
        $cycle  = $this->input('billing_cycle', 'yearly');
        $custom = $this->intInput('custom_cycle_months');

        $data = [
            'service_type'        => in_array($type, HostingService::TYPES, true) ? $type : 'hosting',
            'client_id'           => $this->intInput('client_id'),
            'client_name'         => $this->input('client_name'),
            'company'             => $this->input('company'),
            'project_id'          => $this->intInput('project_id'),
            'website_name'        => $this->input('website_name'),
            'website_url'         => $this->input('website_url'),
            'domain'              => $this->input('domain'),
            'provider'            => $this->input('provider'),
            'plan'                => $this->input('plan'),
            'account_ref'         => $this->input('account_ref'),
            'purchase_date'       => $this->input('purchase_date'),
            'renewal_date'        => $this->input('renewal_date'),
            'cost'                => $this->input('cost'),
            'renewal_cost'        => $this->input('renewal_cost'),
            'billing_cycle'       => array_key_exists($cycle, HostingService::CYCLE_MONTHS) ? $cycle : 'yearly',
            'custom_cycle_months' => $custom,
            'login_url'           => $this->input('login_url'),
            'credential_ref'      => $this->input('credential_ref'),
            'notes'               => $this->input('notes'),
            'internal_notes'      => $this->input('internal_notes'),
        ];

        if ($data['renewal_date'] === '' && $data['purchase_date'] !== '') {
            $data['renewal_date'] = HostingService::addCycle(
                $data['purchase_date'],
                $data['billing_cycle'],
                $custom > 0 ? $custom : null
            ) ?? '';
        }

        return $data;
    }

    /** Returns an error message, or null when the record is fine to save. */
    private function validate(array $d): ?string
    {
        if ($d['client_name'] === '') {
            return 'A hosting record needs a client name.';
        }
        if ($d['purchase_date'] !== '' && !$this->isDate($d['purchase_date'])) {
            return 'The purchase date is not a valid date.';
        }
        // Checked before the renewal date: a custom cycle with no month count
        // is *why* the renewal date could not be calculated, so saying so is
        // more useful than complaining about the missing date.
        if ($d['billing_cycle'] === 'custom' && $d['custom_cycle_months'] <= 0) {
            return 'A custom billing cycle needs a length in months.';
        }
        if (!$this->isDate($d['renewal_date'])) {
            return 'A hosting record needs a renewal / expiry date — enter one, or set a purchase date and billing cycle so it can be calculated.';
        }
        if ($d['purchase_date'] !== '' && $d['renewal_date'] < $d['purchase_date']) {
            return 'The renewal date cannot be earlier than the purchase date.';
        }
        foreach (['cost' => 'Hosting cost', 'renewal_cost' => 'Renewal cost'] as $key => $label) {
            if ($d[$key] !== '' && (!is_numeric($d[$key]) || (float) $d[$key] < 0)) {
                return $label . ' must be a number that is zero or more.';
            }
        }
        foreach (['website_url' => 'website URL', 'login_url' => 'hosting login URL'] as $key => $label) {
            if ($d[$key] !== '' && !filter_var($d[$key], FILTER_VALIDATE_URL)) {
                return 'That ' . $label . ' is not valid — include https:// at the start.';
            }
        }
        return null;
    }

    private function isDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }
}
