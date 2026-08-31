<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Bill;
use App\Models\Client;
use App\Models\Project;
use App\Models\ClientPayment;

/**
 * Billing — admins only.
 *
 * A bill is raised the moment a payment comes in from a client: pick the
 * client and the project it's for, list the services being billed, and
 * record what they paid. The project's cost and everything paid toward it
 * so far (including this payment) are frozen onto the bill, so it prints as
 * a real, dated receipt that won't change even if the project budget does
 * later. Each bill also writes a matching row into client_payments, so a
 * client's totals on the Client Management page stay correct.
 */
class BillController extends Controller
{
    /** Bill history, plus this month's collections and the outstanding total. */
    public function index(): void
    {
        $this->requireAdmin();

        $bills = new Bill();

        $filters = [
            'q'              => trim((string) ($_GET['q'] ?? '')),
            'client_id'      => (int) ($_GET['client_id'] ?? 0),
            'project_id'     => (int) ($_GET['project_id'] ?? 0),
            'payment_method' => (string) ($_GET['payment_method'] ?? ''),
            'from'           => (string) ($_GET['from'] ?? ''),
            'to'             => (string) ($_GET['to'] ?? ''),
        ];

        $this->view('bills/index', [
            'title'             => 'Billing',
            'active'            => 'bills',
            'csrf'              => $this->csrfToken(),
            'bills'             => $bills->search($filters),
            'clients'           => (new Client())->allForSelect(),
            'projects'          => (new Project())->allForSelect(),
            'paidByProject'     => $bills->paidByProject(),
            'methods'           => ClientPayment::METHODS,
            'receivedThisMonth' => $bills->receivedThisMonth(),
            'pendingTotal'      => $bills->pendingAmountTotal(),
            'totalCollected'    => $bills->totalCollected(),
            'totalBills'        => $bills->totalCount(),
            'today'             => date('Y-m-d'),
            'filters'           => $filters,
            // "+ Raise a bill" links from a client's own page add ?new=1 so the form opens
            // pre-filled for them; arriving via the filter bar's client picker does not.
            'openCreateForm'    => isset($_GET['new']) && $filters['client_id'] > 0,
        ]);
    }

    /** One bill, rendered full-page and print-ready (no admin chrome) — this is also the "download PDF" view. */
    public function show(): void
    {
        $this->requireAdmin();

        $id   = (int) ($_GET['id'] ?? 0);
        $bill = (new Bill())->find($id);
        if ($bill === null) {
            $this->flash('error', 'That bill no longer exists.');
            $this->redirect('/bills');
        }

        $items = json_decode((string) $bill['items_json'], true);
        if (!is_array($items)) {
            $items = [];
        }

        $this->view('bills/show', [
            'title' => $bill['bill_number'],
            'bill'  => $bill,
            'items' => $items,
        ], null);
    }

    /** Raise a new bill for a payment just received. */
    public function store(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $clientId  = $this->intInput('client_id');
        $projectId = $this->intInput('project_id');
        $billDate  = $this->input('bill_date');
        $amount    = $this->input('amount_paid');
        $method    = $this->input('payment_method');
        $notes     = $this->input('notes');

        $client = (new Client())->find($clientId);
        if ($client === null) {
            $this->flash('error', 'Pick a valid client to bill.');
            $this->redirect('/bills');
        }

        $project = null;
        if ($projectId > 0) {
            $project = (new Project())->find($projectId);
            if ($project === null || (int) $project['client_id'] !== $clientId) {
                $this->flash('error', 'That project does not belong to the selected client.');
                $this->redirect('/bills');
            }
        }

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $billDate)) {
            $this->flash('error', 'A bill needs a valid date.');
            $this->redirect('/bills');
        }
        if (!is_numeric($amount) || (float) $amount <= 0) {
            $this->flash('error', 'Amount paid must be a number greater than zero.');
            $this->redirect('/bills');
        }

        $items = $this->collectItems();
        if (empty($items)) {
            $this->flash('error', 'Add at least one service to the bill.');
            $this->redirect('/bills');
        }

        $amountPaid  = (float) $amount;
        $projectCost = $project !== null && $project['budget'] !== null ? (float) $project['budget'] : null;
        $priorPaid   = $projectId > 0 ? (new Bill())->totalPaidForProject($projectId) : 0.0;
        $totalPaid   = $priorPaid + $amountPaid;
        $balanceDue  = $projectCost !== null ? $projectCost - $totalPaid : null;

        // Log the payment in the shared client ledger first, so its id can be linked from the bill.
        $paymentId = (new ClientPayment())->add($clientId, null, $amountPaid, $billDate, $method, 'Billed — see bill history');

        $billId = (new Bill())->create([
            'client_id'    => $clientId,
            'project_id'   => $projectId,
            'bill_date'    => $billDate,
            'project_cost' => $projectCost,
            'total_paid'   => $totalPaid,
            'amount_paid'  => $amountPaid,
            'balance_due'  => $balanceDue,
            'payment_method' => $method,
            'notes'        => $notes,
            'payment_id'   => $paymentId,
        ], $items, Auth::id());

        $this->flash('success', 'Bill raised for ' . $client['name'] . '.');
        $this->redirect('/bills/view?id=' . $billId);
    }

    /** Delete a bill, and the payment record it created. */
    public function destroy(): void
    {
        $this->requireAdmin();
        $this->verifyCsrf();

        $bills = new Bill();
        $id    = $this->intInput('id');
        $bill  = $bills->find($id);
        if ($bill === null) {
            $this->flash('error', 'That bill no longer exists.');
            $this->redirect('/bills');
        }

        if (!empty($bill['payment_id'])) {
            (new ClientPayment())->delete((int) $bill['payment_id']);
        }
        $bills->delete($id);

        $this->flash('success', 'Bill ' . $bill['bill_number'] . ' deleted.');
        $this->redirect('/bills');
    }

    /** Read the parallel item_desc[] / item_amount[] arrays posted from the bill form into a clean list. */
    private function collectItems(): array
    {
        $descs = $_POST['item_desc'] ?? [];
        $amts  = $_POST['item_amount'] ?? [];
        if (!is_array($descs)) {
            return [];
        }

        $items = [];
        foreach ($descs as $i => $desc) {
            $desc = trim((string) $desc);
            if ($desc === '') {
                continue;
            }
            $amt = $amts[$i] ?? '';
            $items[] = [
                'description' => $desc,
                'amount'      => is_numeric($amt) ? round((float) $amt, 2) : 0.0,
            ];
        }
        return $items;
    }
}
