<?php

namespace App\Http\Middleware;

use App\Support\BranchContext;
use App\Support\BranchViewData;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveBranchContext
{
    public function __construct(
        private BranchContext $context,
        private BranchViewData $viewData
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->context->resolve($request);
        $this->viewData->share();

        return $next($request);
    }
}
