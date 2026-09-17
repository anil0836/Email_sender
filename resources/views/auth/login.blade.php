@extends('layouts.app')

@section('content')
<div class="d-flex justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="col-12 col-sm-10 col-md-8 col-lg-5 col-xl-4">
        <!-- Modern Login Card -->
        <div class="card p-2">
            <div class="card-body p-4 p-sm-5">
                
                <!-- Logo & Brand Header -->
                <div class="text-center mb-4">
                    <div class="d-inline-flex align-items-center justify-content-center rounded-3 bg-zinc-900 text-white mb-3" style="width: 44px; height: 44px;">
                        <i class="bi bi-envelope-fill fs-5"></i>
                    </div>
                    <h5 class="fw-semibold text-zinc-900 tracking-tight mb-1">Sign in to MailEngine</h5>
                    <p class="text-zinc-500 mb-0" style="font-size: 0.8125rem;">B2B Outreach & Compliance Console</p>
                </div>
                
                <!-- Form Inputs -->
                <form action="{{ route('login') }}" method="POST">
                    @csrf
                    <div class="mb-3">
                        <label for="email" class="form-label mb-1">Email Address</label>
                        <input type="email" class="form-control" id="email" name="email" placeholder="name@company.com" required value="{{ old('email') }}">
                    </div>
                    
                    <div class="mb-4">
                        <label for="password" class="form-label mb-1">Password</label>
                        <input type="password" class="form-control" id="password" name="password" placeholder="••••••••" required>
                    </div>
                    
                    <button type="submit" class="btn btn-primary w-100 py-2">
                        Sign In <i class="bi bi-arrow-right ms-1"></i>
                    </button>
                </form>

                <!-- Demo Accounts Panel -->
                <div class="mt-4 pt-4 border-top">
                    <div class="p-3 bg-zinc-50 rounded-3 border" style="font-size: 0.775rem;">
                        <span class="stat-label d-block text-zinc-900 mb-2"><i class="bi bi-key me-1"></i>Demo Accounts</span>
                        <div class="d-flex justify-content-between text-zinc-600 mb-1">
                            <span>Admin:</span>
                            <code class="text-zinc-900 font-monospace">admin@b2bbulkmail.com / admin</code>
                        </div>
                        <div class="d-flex justify-content-between text-zinc-600 mb-1">
                            <span>Manager:</span>
                            <code class="text-zinc-900 font-monospace">manager@b2bbulkmail.com / manager</code>
                        </div>
                        <div class="d-flex justify-content-between text-zinc-600">
                            <span>User:</span>
                            <code class="text-zinc-900 font-monospace">user@b2bbulkmail.com / user</code>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
