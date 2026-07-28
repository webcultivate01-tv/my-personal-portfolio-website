<?php
namespace App\Controllers;

use App\Core\Controller;
use App\Core\Auth;
use App\Models\Lead;

class DashboardController extends Controller
{
    public function index(): void
    {
        $this->requireAuth();

        $leads = new Lead();

        $this->view('dashboard/index', [
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
