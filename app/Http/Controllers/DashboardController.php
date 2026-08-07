<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Show the dashboard.
     *
     * The KPI tiles, trend chart and recent activity arrive in P7.T11, once
     * there are transactions to derive them from.
     */
    public function index(): Response
    {
        return Inertia::render('dashboard');
    }
}
