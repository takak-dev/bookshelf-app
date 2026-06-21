<?php

namespace App\Http\Controllers;

use App\Services\ReadingReportService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth'); // PG14 は認証必須
    }

    public function index(Request $request, ReadingReportService $service): View
    {
        $stats = $service->generate($request->user());

        return view('reports.index', compact('stats'));
    }
}
