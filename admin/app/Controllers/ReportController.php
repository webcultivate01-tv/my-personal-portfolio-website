<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Report;
use App\Models\Lead;
use App\Models\Project;
use App\Models\Client;
use App\Models\ClientPayment;
use App\Models\HostingService;

/**
 * Reports — pick a report, narrow it with filters, print or save it as a PDF.
 *
 * Admins and managers both reach this page, but a report is only offered when
 * its own module is: enquiries and projects are open to managers, while the
 * client, billing and hosting reports stay admin-only, exactly like the pages
 * they draw from. Nothing here writes, so every action is a plain GET.
 *
 * Generating produces a clean, print-ready page (no admin chrome) that opens
 * in a new tab and goes straight to the browser's print dialog — "Save as PDF"
 * there gives the downloadable file. That is the same approach the bill and
 * enquiry exports already use, so the panel needs no PDF library to install.
 */
class ReportController extends Controller
{
    /**
     * Every report the module offers.
     *   admin_only — hidden from managers, matching the source module's own rule
     */
    private const REPORTS = [
        'enquiries' => [
            'label'      => 'Enquiries Report',
            'blurb'      => 'Every enquiry from the website contact form, by status, subject, flag or date.',
            'admin_only' => false,
        ],
        'projects'  => [
            'label'      => 'Projects Report',
            'blurb'      => 'Project pipeline with client, priority, dates, budget and task progress.',
            'admin_only' => false,
        ],
        'clients'   => [
            'label'      => 'Clients Report',
            'blurb'      => 'Client list with invoiced, received and outstanding totals per client.',
            'admin_only' => true,
        ],
        'bills'     => [
            'label'      => 'Billing Report',
            'blurb'      => 'Bills raised, what was collected and what is still due, by client or project.',
            'admin_only' => true,
        ],
        'hosting'   => [
            'label'      => 'Hosting & Domains Report',
            'blurb'      => 'Hosting and domain records with renewal dates, costs and renewal status.',
            'admin_only' => true,
        ],
    ];

    /** The picker page: choose a report, set its filters, generate. */
    public function index(): void
    {
        $this->requireAuth();

        $type = (string) ($_GET['type'] ?? 'enquiries');
        if (!isset(self::REPORTS[$type]) || !$this->canAccess($type)) {
            $type = 'enquiries';
        }

        $isAdmin = Auth::isAdmin();

        $this->view('reports/index', [
            'title'    => 'Reports',
            'active'   => 'reports',
            'csrf'     => $this->csrfToken(),
            'reports'  => array_filter(
                self::REPORTS,
                fn(array $r): bool => $isAdmin || !$r['admin_only']
            ),
            'type'     => $type,
            'filters'  => $this->readFilters($type),
            'statuses' => Lead::STATUSES,
            'subjects' => Lead::SUBJECTS,
            'flags'    => Report::ENQUIRY_FLAGS,
            'projectStatuses'   => Project::STATUSES,
            'projectPriorities' => Project::PRIORITIES,
            'balances'          => Report::CLIENT_BALANCES,
            'methods'           => ClientPayment::METHODS,
            'hostingTypes'      => HostingService::TYPES,
            'hostingStatuses'   => HostingService::STATUSES,
            'hostingLabels'     => HostingService::STATUS_LABELS,
            'cycles'            => array_keys(HostingService::CYCLE_MONTHS),
            // Dropdown sources are admin-only data, so managers never load them.
            'clients'  => $isAdmin ? (new Client())->allForSelect() : [],
            'projects' => $isAdmin ? (new Project())->allForSelect() : [],
            'providers' => $isAdmin ? (new HostingService())->providers() : [],
        ]);
    }

    /** Build one report and render it print-ready (no layout). */
    public function generate(): void
    {
        $this->requireAuth();

        $type = (string) ($_GET['type'] ?? '');
        if (!isset(self::REPORTS[$type])) {
            $this->flash('error', 'Unknown report.');
            $this->redirect('/reports');
        }
        if (!$this->canAccess($type)) {
            http_response_code(403);
            $this->flash('error', 'You do not have permission to run that report.');
            $this->redirect('/reports');
        }

        $filters = $this->readFilters($type);
        $spec    = $this->buildReport($type, $filters);

        $this->view('reports/pdf', [
            'title'         => self::REPORTS[$type]['label'],
            'reportLabel'   => self::REPORTS[$type]['label'],
            'columns'       => $spec['columns'],
            'rows'          => $spec['rows'],
            'summary'       => $spec['summary'],
            'appliedFilters' => $spec['applied'],
            'generatedAt'   => date('M j, Y \a\t g:ia'),
            'generatedBy'   => Auth::name(),
            // ?print=1 (what the Generate button sends) opens the print dialog
            // on load; opening the same URL by hand just shows the report.
            'autoPrint'     => isset($_GET['print']),
        ], null);
    }

    // ---------------- report definitions ----------------

    /**
     * Rows, columns, headline numbers and a readable list of the filters used.
     * Row values are pre-formatted here where a cell needs more than one field
     * (task progress, enquiry flags), so the view stays a dumb table printer.
     */
    private function buildReport(string $type, array $f): array
    {
        $report = new Report();

        switch ($type) {
            case 'projects':  return $this->projectsReport($report, $f);
            case 'clients':   return $this->clientsReport($report, $f);
            case 'bills':     return $this->billsReport($report, $f);
            case 'hosting':   return $this->hostingReport($report, $f);
            default:          return $this->enquiriesReport($report, $f);
        }
    }

    private function enquiriesReport(Report $report, array $f): array
    {
        $rows = $report->enquiries($f);

        foreach ($rows as $i => $row) {
            $flags = [];
            if ((int) $row['is_important'] === 1) { $flags[] = 'Important'; }
            if ((int) $row['is_client'] === 1)    { $flags[] = 'Client'; }
            if ((int) $row['is_read'] === 0)      { $flags[] = 'Unread'; }
            $rows[$i]['flags_label'] = $flags ? implode(' · ', $flags) : '—';
            $rows[$i]['subject']     = ($row['subject'] ?? '') !== '' ? $row['subject'] : '(no subject)';
        }

        $byStatus = [];
        foreach ($rows as $row) {
            $byStatus[$row['status']] = ($byStatus[$row['status']] ?? 0) + 1;
        }

        return [
            'columns' => [
                ['label' => 'Received', 'key' => 'created_at', 'format' => 'date'],
                ['label' => 'From',     'key' => 'name',       'sub' => 'email'],
                ['label' => 'Phone',    'key' => 'phone',      'format' => 'text'],
                ['label' => 'Subject',  'key' => 'subject'],
                ['label' => 'Status',   'key' => 'status',     'format' => 'status'],
                ['label' => 'Flags',    'key' => 'flags_label'],
            ],
            'rows'    => $rows,
            'summary' => [
                'Enquiries' => (string) count($rows),
                'Won'       => (string) ($byStatus['won'] ?? 0),
                'Lost'      => (string) ($byStatus['lost'] ?? 0),
                'New'       => (string) ($byStatus['new'] ?? 0),
            ],
            'applied' => array_filter([
                'Status'      => $this->labelFor($f['status'] ?? ''),
                'Flag'        => $this->labelFor($f['flag'] ?? ''),
                'Subject'     => $f['subject'] ?? '',
                'Search'      => $f['q'] ?? '',
                'Date range'  => $this->rangeLabel($f),
            ]),
        ];
    }

    private function projectsReport(Report $report, array $f): array
    {
        $rows = $report->projects($f);

        $budget = 0.0;
        foreach ($rows as $i => $row) {
            $budget += (float) $row['budget'];
            $rows[$i]['task_progress'] = (int) $row['done_count'] . ' / ' . (int) $row['task_count'];
            $rows[$i]['client_name']   = $row['client_name'] ?? '—';
        }

        $byStatus = [];
        foreach ($rows as $row) {
            $byStatus[$row['status']] = ($byStatus[$row['status']] ?? 0) + 1;
        }

        return [
            'columns' => [
                ['label' => 'Project',  'key' => 'name'],
                ['label' => 'Client',   'key' => 'client_name'],
                ['label' => 'Status',   'key' => 'status',   'format' => 'status'],
                ['label' => 'Priority', 'key' => 'priority', 'format' => 'label'],
                ['label' => 'Start',    'key' => 'start_date', 'format' => 'date'],
                ['label' => 'Due',      'key' => 'due_date',   'format' => 'date'],
                ['label' => 'Tasks',    'key' => 'task_progress', 'align' => 'right'],
                ['label' => 'Budget',   'key' => 'budget', 'format' => 'money', 'align' => 'right'],
            ],
            'rows'    => $rows,
            'summary' => [
                'Projects'     => (string) count($rows),
                'In progress'  => (string) ($byStatus['in_progress'] ?? 0),
                'Completed'    => (string) ($byStatus['completed'] ?? 0),
                'Total budget' => $this->money($budget),
            ],
            'applied' => array_filter([
                'Status'     => $this->labelFor($f['status'] ?? ''),
                'Priority'   => $this->labelFor($f['priority'] ?? ''),
                'Client'     => $this->clientLabel((int) ($f['client_id'] ?? 0)),
                'Search'     => $f['q'] ?? '',
                'Created between' => $this->rangeLabel($f),
            ]),
        ];
    }

    private function clientsReport(Report $report, array $f): array
    {
        $rows = $report->clients($f);

        $invoiced = $paid = $outstanding = 0.0;
        foreach ($rows as $i => $row) {
            $invoiced    += (float) $row['total_invoiced'];
            $paid        += (float) $row['total_paid'];
            $outstanding += max((float) $row['outstanding'], 0);
            $rows[$i]['company'] = ($row['company'] ?? '') !== '' ? $row['company'] : '—';
            $rows[$i]['phone']   = ($row['phone'] ?? '') !== '' ? $row['phone'] : '—';
        }

        return [
            'columns' => [
                ['label' => 'Client',      'key' => 'name', 'sub' => 'email'],
                ['label' => 'Company',     'key' => 'company'],
                ['label' => 'Phone',       'key' => 'phone'],
                ['label' => 'Projects',    'key' => 'project_count', 'align' => 'right'],
                ['label' => 'Invoiced',    'key' => 'total_invoiced', 'format' => 'money', 'align' => 'right'],
                ['label' => 'Received',    'key' => 'total_paid',     'format' => 'money', 'align' => 'right'],
                ['label' => 'Outstanding', 'key' => 'outstanding',    'format' => 'money', 'align' => 'right'],
                ['label' => 'Client since', 'key' => 'created_at', 'format' => 'date'],
            ],
            'rows'    => $rows,
            'summary' => [
                'Clients'     => (string) count($rows),
                'Invoiced'    => $this->money($invoiced),
                'Received'    => $this->money($paid),
                'Outstanding' => $this->money($outstanding),
            ],
            'applied' => array_filter([
                'Balance'       => $this->labelFor($f['balance'] ?? ''),
                'Search'        => $f['q'] ?? '',
                'Added between' => $this->rangeLabel($f),
            ]),
        ];
    }

    private function billsReport(Report $report, array $f): array
    {
        $rows = $report->bills($f);

        $collected = $due = 0.0;
        $seenProjects = [];
        foreach ($rows as $i => $row) {
            $collected += (float) $row['amount_paid'];

            // balance_due is frozen onto each bill, so adding it up across a
            // project's bills would count the same debt more than once. Rows
            // arrive newest first, so the first bill seen for a project is its
            // latest one — that is the balance still standing.
            $projectId = (int) ($row['project_id'] ?? 0);
            if ($projectId > 0 && !isset($seenProjects[$projectId])) {
                $seenProjects[$projectId] = true;
                $due += max((float) $row['balance_due'], 0);
            }

            $rows[$i]['project_name'] = ($row['project_name'] ?? '') !== '' ? $row['project_name'] : '—';
            $rows[$i]['method_label'] = ucwords(str_replace('_', ' ', (string) $row['payment_method']));
        }

        return [
            'columns' => [
                ['label' => 'Bill #',      'key' => 'bill_number'],
                ['label' => 'Date',        'key' => 'bill_date', 'format' => 'date'],
                ['label' => 'Client',      'key' => 'client_name', 'sub' => 'client_company'],
                ['label' => 'Project',     'key' => 'project_name'],
                ['label' => 'Method',      'key' => 'method_label'],
                ['label' => 'Amount paid', 'key' => 'amount_paid', 'format' => 'money', 'align' => 'right'],
                ['label' => 'Balance due', 'key' => 'balance_due', 'format' => 'money', 'align' => 'right'],
            ],
            'rows'    => $rows,
            'summary' => [
                'Bills'        => (string) count($rows),
                'Collected'    => $this->money($collected),
                'Still due'    => $this->money($due),
                'Average bill' => $this->money($rows ? $collected / count($rows) : 0.0),
            ],
            'applied' => array_filter([
                'Client'     => $this->clientLabel((int) ($f['client_id'] ?? 0)),
                'Project'    => $this->projectLabel((int) ($f['project_id'] ?? 0)),
                'Method'     => $this->labelFor($f['payment_method'] ?? ''),
                'Search'     => $f['q'] ?? '',
                'Bill date between' => $this->rangeLabel($f),
            ]),
        ];
    }

    private function hostingReport(Report $report, array $f): array
    {
        $rows = $report->hosting($f);

        $cost     = 0.0;
        $byStatus = [];
        foreach ($rows as $i => $row) {
            $renewalCost = $row['renewal_cost'] !== null ? (float) $row['renewal_cost'] : (float) $row['cost'];
            $cost       += $renewalCost;
            $rows[$i]['renewal_amount'] = $renewalCost;
            $rows[$i]['service_label']  = ($row['website_name'] ?? '') !== ''
                ? $row['website_name']
                : (($row['domain'] ?? '') !== '' ? $row['domain'] : '—');
            $rows[$i]['type_label']  = ucfirst((string) $row['service_type']);
            $rows[$i]['cycle_label'] = ucwords(str_replace('_', ' ', (string) $row['billing_cycle']));
            $rows[$i]['provider']    = ($row['provider'] ?? '') !== '' ? $row['provider'] : '—';
            $byStatus[$row['status']] = ($byStatus[$row['status']] ?? 0) + 1;
        }

        return [
            'columns' => [
                ['label' => 'Client',   'key' => 'client_name', 'sub' => 'company'],
                ['label' => 'Service',  'key' => 'service_label', 'sub' => 'domain'],
                ['label' => 'Type',     'key' => 'type_label'],
                ['label' => 'Provider', 'key' => 'provider'],
                ['label' => 'Cycle',    'key' => 'cycle_label'],
                ['label' => 'Renews',   'key' => 'renewal_date', 'format' => 'date'],
                ['label' => 'Status',   'key' => 'status', 'format' => 'status', 'text_key' => 'status_label'],
                ['label' => 'Cost',     'key' => 'renewal_amount', 'format' => 'money', 'align' => 'right'],
            ],
            'rows'    => $rows,
            'summary' => [
                'Records'       => (string) count($rows),
                'Expired'       => (string) ($byStatus['expired'] ?? 0),
                'Renewal due'   => (string) ($byStatus['due'] ?? 0),
                'Renewal value' => $this->money($cost),
            ],
            'applied' => array_filter([
                'Type'     => $this->labelFor($f['type'] ?? ''),
                'Status'   => HostingService::STATUS_LABELS[$f['status'] ?? ''] ?? '',
                'Provider' => $f['provider'] ?? '',
                'Cycle'    => $this->labelFor($f['cycle'] ?? ''),
                'Search'   => $f['q'] ?? '',
                'Renewal date between' => $this->rangeLabel($f),
            ]),
        ];
    }

    // ---------------- input + formatting helpers ----------------

    /**
     * Read this report's filters off the query string. Values are only
     * whitelisted/validated inside the model, so an unknown value simply
     * drops out and the report comes back unfiltered rather than erroring.
     */
    private function readFilters(string $type): array
    {
        $get = static fn(string $key): string => trim((string) ($_GET[$key] ?? ''));

        $common = [
            'q'    => $get('q'),
            'from' => $this->validDate($get('from')),
            'to'   => $this->validDate($get('to')),
        ];

        switch ($type) {
            case 'projects':
                return $common + [
                    'status'    => $get('status'),
                    'priority'  => $get('priority'),
                    'client_id' => (int) ($_GET['client_id'] ?? 0),
                ];
            case 'clients':
                return $common + ['balance' => $get('balance')];
            case 'bills':
                return $common + [
                    'client_id'      => (int) ($_GET['client_id'] ?? 0),
                    'project_id'     => (int) ($_GET['project_id'] ?? 0),
                    'payment_method' => $get('payment_method'),
                ];
            case 'hosting':
                return $common + [
                    // Posted as service_type: plain 'type' is already taken by the
                    // query param that picks which report to run.
                    'type'     => $get('service_type'),
                    'status'   => $get('status'),
                    'provider' => $get('provider'),
                    'cycle'    => $get('cycle'),
                ];
            default:
                return $common + [
                    'status'  => $get('status'),
                    'flag'    => $get('flag'),
                    'subject' => $get('subject'),
                ];
        }
    }

    /** Managers only get the reports whose source module they can already open. */
    private function canAccess(string $type): bool
    {
        return !self::REPORTS[$type]['admin_only'] || Auth::isAdmin();
    }

    /** '' unless the value is a real 'YYYY-MM-DD' date. */
    private function validDate(string $value): string
    {
        if ($value === '') {
            return '';
        }
        $d = \DateTime::createFromFormat('Y-m-d', $value);
        return ($d !== false && $d->format('Y-m-d') === $value) ? $value : '';
    }

    /** 'in_progress' -> 'In Progress', for the "filters applied" line. */
    private function labelFor(string $value): string
    {
        return $value === '' ? '' : ucwords(str_replace('_', ' ', $value));
    }

    /** A human date range for the "filters applied" line, or '' when unbounded. */
    private function rangeLabel(array $f): string
    {
        $from = (string) ($f['from'] ?? '');
        $to   = (string) ($f['to'] ?? '');

        if ($from !== '' && $to !== '') {
            return date('M j, Y', strtotime($from)) . ' – ' . date('M j, Y', strtotime($to));
        }
        if ($from !== '') {
            return 'From ' . date('M j, Y', strtotime($from));
        }
        if ($to !== '') {
            return 'Up to ' . date('M j, Y', strtotime($to));
        }
        return '';
    }

    private function clientLabel(int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        $client = (new Client())->find($id);
        return $client === null ? '' : (string) $client['name'];
    }

    private function projectLabel(int $id): string
    {
        if ($id <= 0) {
            return '';
        }
        $project = (new Project())->find($id);
        return $project === null ? '' : (string) $project['name'];
    }

    private function money(float $n): string
    {
        return '₹' . number_format($n, 2);
    }
}
