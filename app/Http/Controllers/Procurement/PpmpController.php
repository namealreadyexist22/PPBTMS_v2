<?php

namespace App\Http\Controllers\Procurement;

use App\DataTables\Procurement\PpmpDataTable;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PpmpController extends Controller
{
    public function index(PpmpDataTable $dataTable)
    {
        return $dataTable->render('procurement.ppmp.index');
    }

    public function destroy(){

    }

}

