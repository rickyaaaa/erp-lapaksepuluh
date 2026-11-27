<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class EnsureBranchAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $branchId = $request->integer('branch_id');
        abort_if($branchId < 1, 422, 'branch_id wajib diisi.');

        $user = $request->user();
        $hasAccess = $user->canAccessBranch($branchId);

        abort_unless($hasAccess, 403, 'Anda tidak memiliki akses ke cabang ini.');

        $request->attributes->set('authorized_branch_id', $branchId);

        return $next($request);
    }
}

