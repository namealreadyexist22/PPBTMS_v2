<?php

namespace App\Http\Controllers\Procurement;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PpmpController extends Controller
{
    public function index(){
        return view('procurement.ppmp.index');
    }

    public function destroy(){
        
    }

}

