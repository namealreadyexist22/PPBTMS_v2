@extends('BackEnd.layouts.master')

@section('content')
<div class="row justify-content-center align-items-center" style="min-height: 75vh;">
    <div class="col-12 col-md-10 col-lg-7 text-center">
        <!-- Content layout rendered directly without a wrapper card background frame -->
        <div class="py-5">
            <div class="mb-4">
                <img src="{{ asset('assets/img/SRALOGO.png') }}"
                     alt="SRA Official Crest Logo"
                     class="img-fluid"
                     style="max-width: 300px; height: auto; filter: drop-shadow(0px 10px 20px rgba(0,0,0,0.05));">
            </div>

            <h3 class="fw-bold text-dark tracking-tight mb-2" style="font-family: 'Inter', sans-serif; font-size: 1.75rem;">
                Sugar Regulatory Administration
            </h3>

            <p class="text-secondary mx-auto mb-0" style="max-width: 480px; font-size: 14px; line-height: 1.6; opacity: 0.85;">
                Welcome
            </p>
        </div>
    </div>
</div>
@endsection
