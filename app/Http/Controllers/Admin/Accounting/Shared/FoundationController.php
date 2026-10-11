<?php
namespace App\Http\Controllers\Admin\Accounting\Shared;

use App\Http\Controllers\Controller;
use App\Services\Accounting\JournalPostingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Carbon\Carbon;

abstract class FoundationController extends Controller
{
    use LogsAccountingExceptions;

    protected function canDo(string $permission): void { abort_unless(auth()->user()->can($permission),403); }


















}
