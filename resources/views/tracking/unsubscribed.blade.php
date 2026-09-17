@extends('layouts.app')

@section('content')
<div class="row justify-content-center align-items-center" style="min-height: 80vh;">
    <div class="col-md-6 col-lg-5">
        <div class="card p-2">
            <div class="card-body p-5 text-center">
                @if ($success ?? false)
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-emerald-50 text-emerald-600 mb-3" style="width: 56px; height: 56px;">
                        <i class="bi bi-check-lg fs-3"></i>
                    </div>
                    <h5 class="fw-semibold text-zinc-900 mb-2">Unsubscribe Confirmed</h5>
                    <p class="text-zinc-600 mb-2" style="font-size: 0.875rem;">
                        The email address <strong class="text-zinc-900">{{ $email }}</strong> has been added to our global suppression list.
                    </p>
                    <p class="text-zinc-400 mb-0" style="font-size: 0.775rem;">
                        You will no longer receive automated bulk outreach from this platform.
                    </p>
                @else
                    <div class="d-inline-flex align-items-center justify-content-center rounded-circle bg-amber-50 text-amber-600 mb-3" style="width: 56px; height: 56px;">
                        <i class="bi bi-question-lg fs-3"></i>
                    </div>
                    <h5 class="fw-semibold text-zinc-900 mb-2">Confirm Unsubscribe</h5>
                    <p class="text-zinc-600 mb-4" style="font-size: 0.875rem;">
                        Are you sure you want to unsubscribe <strong class="text-zinc-900">{{ $email }}</strong> from future outreach?
                    </p>
                    
                    <form action="{{ url('/track/unsubscribe/' . ($token ?? '')) }}" method="POST">
                        @csrf
                        <div class="d-flex justify-content-center gap-2">
                            <button type="submit" class="btn btn-primary btn-sm">
                                Confirm Unsubscribe
                            </button>
                            <a href="{{ route('dashboard') }}" class="btn btn-outline-secondary btn-sm">
                                Cancel
                            </a>
                        </div>
                    </form>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
