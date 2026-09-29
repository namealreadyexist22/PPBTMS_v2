<?php

namespace App\Core\Http\Controllers;

use App\Core\DataTables\LogsDataTable;
use App\Http\Controllers\Controller;
use Spatie\Activitylog\Models\Activity;

class LogController extends Controller
{
    public function index(LogsDataTable $dataTable)
    {
        return $dataTable->render('admin.logs.index');
    }

    public function clear()
    {
        $count = Activity::count();
        Activity::truncate();

        activity()
            ->causedBy(auth()->user())
            ->log("cleared {$count} activity log entries");

        return response()->json(['status' => 'success', 'message' => 'Activity log cleared.']);
    }
}