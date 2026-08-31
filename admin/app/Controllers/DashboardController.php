<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Lead;
use App\Models\HostingService;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $leads = new Lead();

        // The hosting widget is admin-only, matching the Hosting module itself.
        $hostingAlerts   = null;
        $hostingUpcoming = [];
        if (Auth::isAdmin()) {
            $hosting         = new HostingService();
            $hostingAlerts   = $hosting->alertCounts();
            $hostingUpcoming = array_slice($hosting->needingAttention(), 0, 3);
        }

        $this->view('dashboard/index', [
            'hostingAlerts'   => $hostingAlerts,
            'hostingUpcoming' => $hostingUpcoming,
            'title'        => 'Dashboard',
            'active'       => 'dashboard',
            'userName'     => Auth::name(),
            'csrf'         => $this->csrfToken(),
            'totalLeads'   => $leads->totalCount(),
            'newLeads'     => $leads->countByStatus('new'),
            'wonLeads'     => $leads->countByStatus('won'),
            'recentLeads'  => $leads->recent(5),
        ]);
    }
}
