<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Lead;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\Client;
use App\Models\User;
use App\Models\HostingService;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $leads = new Lead();

        // Project Management is visible to every signed-in user, so its
        // overview cards are gathered for everyone (unlike Hosting/Clients/Team).
        $projects      = new Project();
        $taskProgress  = (new ProjectTask())->overallProgress();

        // The hosting/clients/team widgets are admin-only, matching those modules.
        $hostingAlerts    = null;
        $hostingUpcoming  = [];
        $hostingSummary   = null;
        $clientsTotal     = null;
        $clientFinancials = null;
        $userRoleCounts   = null;
        if (Auth::isAdmin()) {
            $hosting          = new HostingService();
            $hostingAlerts    = $hosting->alertCounts();
            $hostingUpcoming  = array_slice($hosting->needingAttention(), 0, 3);
            $hostingSummary   = $hosting->summary();
            $clientsTotal     = (new Client())->totalCount();
            $clientFinancials = (new Client())->financialSummary();
            $userRoleCounts   = (new User())->roleCounts();
        }

        $this->view('dashboard/index', [
            'hostingAlerts'      => $hostingAlerts,
            'hostingUpcoming'    => $hostingUpcoming,
            'hostingSummary'     => $hostingSummary,
            'clientsTotal'       => $clientsTotal,
            'clientFinancials'   => $clientFinancials,
            'userRoleCounts'     => $userRoleCounts,
            'title'              => 'Dashboard',
            'active'             => 'dashboard',
            'userName'           => Auth::name(),
            'csrf'               => $this->csrfToken(),
            'totalLeads'         => $leads->totalCount(),
            'leadStatusCounts'   => $leads->countsByStatus(),
            'recentLeads'        => $leads->recent(5),
            'projectTotal'       => $projects->totalCount(),
            'projectStatusCounts'=> $projects->countsByStatus(),
            'taskProgress'       => $taskProgress,
        ]);
    }
}
