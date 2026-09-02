<?php

namespace App\Services;

use App\Models\Branch;
use App\Models\Order;
use App\Models\PosSession;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class SuperAdminPosSessionManager
{
    public function __construct(private AuditLogger $audit)
    {
    }

    /**
     * Ensure the Super Admin has exactly one open POS session and that it
     * belongs to the currently selected branch.
     */
    public function ensureActiveForBranch(User $user, int $branchId): PosSession
    {
        $this->assertSuperAdmin($user);

        $branch = Branch::query()->active()->find($branchId);
        if (!$branch) {
            throw new AccessDeniedHttpException('The selected branch is inactive or unavailable.');
        }

        return DB::transaction(function () use ($user, $branchId) {
            $openSessions = PosSession::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('status', 'Open')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            $matching = $openSessions->first(function (PosSession $session) use ($branchId) {
                return (int) $session->branch_id === $branchId;
            });

            // Preserve the current session when the same branch is selected again,
            // but clean up any impossible/stale extra open sessions.
            if ($matching) {
                foreach ($openSessions as $session) {
                    if ((int) $session->id !== (int) $matching->id) {
                        $this->closeSession($session, 'super_admin_session.stale_closed');
                    }
                }

                return $matching;
            }

            foreach ($openSessions as $session) {
                $this->closeSession($session, 'super_admin_session.branch_changed');
            }

            return PosSession::create([
                'branch_id' => $branchId,
                'user_id' => $user->id,
                'weekday' => Carbon::now()->format('l'),
                'start_time' => Carbon::now(),
                'status' => 'Open',
            ]);
        });
    }

    /**
     * Close every open POS session owned by this Super Admin.
     * Used before switching branch/all-branch context and on logout.
     */
    public function closeAllForUser(User $user, string $reason = 'super_admin_session.closed'): void
    {
        $this->assertSuperAdmin($user);

        DB::transaction(function () use ($user, $reason) {
            $openSessions = PosSession::withoutGlobalScopes()
                ->where('user_id', $user->id)
                ->where('status', 'Open')
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            foreach ($openSessions as $session) {
                $this->closeSession($session, $reason);
            }
        });
    }

    private function closeSession(PosSession $session, string $auditAction): void
    {
        $startTime = Carbon::parse($session->start_time);
        $endTime = Carbon::now();

        $orders = Order::withoutGlobalScopes()
            ->where('branch_id', $session->branch_id)
            ->whereBetween('created_at', [$startTime, $endTime])
            ->whereNotIn('status', ['Cancelled', 'cancelled'])
            ->get();

        $salesTotal = (float) $orders->sum('subtotal');
        $serviceCharge = (float) $orders->sum('service_charge');
        $vatTotal = (float) $orders->sum('vat_tax');
        $grandTotal = (float) $orders->sum('grand_total');

        $cash = 0.0;
        $card = 0.0;
        $mfc = 0.0;

        foreach ($orders as $order) {
            if ($order->payment_type === 'Split') {
                $cash += (float) ($order->paid_in_cash ?? 0);
                $card += (float) ($order->paid_in_card ?? 0);
                $mfc += (float) ($order->paid_in_mfc ?? 0);
            } else {
                $paid = (float) ($order->total_paid_amount ?? 0);
                if ($order->payment_type === 'Cash') {
                    $cash += $paid;
                } elseif ($order->payment_type === 'Card') {
                    $card += $paid;
                } elseif ($order->payment_type === 'Mobile Banking') {
                    $mfc += $paid;
                }
            }
        }

        $duration = $endTime
            ->diffAsCarbonInterval($startTime)
            ->cascade()
            ->forHumans(['short' => true]);

        $before = [
            'status' => $session->status,
            'branch_id' => (int) $session->branch_id,
            'start_time' => (string) $session->start_time,
        ];

        // Use a direct update because branch switching may currently be resolving
        // from All Branches or the old branch. This is an internal lifecycle action,
        // not a user-supplied cross-branch write.
        DB::table('pos_sessions')
            ->where('id', $session->id)
            ->where('status', 'Open')
            ->update([
                'end_time' => $endTime,
                'duration' => $duration,
                'status' => 'Closed',
                'sales_total' => $salesTotal,
                'service_charge' => $serviceCharge,
                'vat_total' => $vatTotal,
                'grand_total' => $grandTotal,
                'incomes_summary' => json_encode([
                    'Cash' => $cash,
                    'Card' => $card,
                    'MFC' => $mfc,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => $endTime,
            ]);

        $this->audit->log(
            action: $auditAction,
            branchId: (int) $session->branch_id,
            description: 'Super Admin POS session #' . $session->id . ' closed automatically',
            before: $before,
            after: [
                'status' => 'Closed',
                'end_time' => $endTime->toDateTimeString(),
                'grand_total' => $grandTotal,
            ],
            auditableType: PosSession::class,
            auditableId: (int) $session->id,
            metadata: ['automatic' => true]
        );
    }

    private function assertSuperAdmin(User $user): void
    {
        if (!$user->isSuperAdmin()) {
            throw new AccessDeniedHttpException('This POS session lifecycle is reserved for Super Admin.');
        }
    }
}
