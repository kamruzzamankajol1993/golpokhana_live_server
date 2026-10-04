@can('inventory-purchase-approval-settings')
<div id="settingsPurchaseApproval" style="display:none;">
    @if(!$purchaseApprovalSetting)
        <div class="progga-card">
            <div class="progga-card-header">
                <div class="progga-card-title"><i class="bi bi-check2-square me-2"></i>Purchase Approval</div>
            </div>
            <div class="progga-card-body">
                <div class="alert alert-warning mb-0">
                    Purchase approval tables are not available yet. Please run <code>php artisan migrate</code> and reload this page.
                </div>
            </div>
        </div>
    @else
        @php
            $approvalRows = old('approvers');
            if ($approvalRows === null) {
                $approvalRows = [];
                foreach ($purchaseApprovalApprovers as $approver) {
                    $approvalRows[] = [
                        'user_id' => $approver->user_id,
                        'approval_order' => $approver->approval_order,
                    ];
                }
            }
            if (empty($approvalRows)) {
                $approvalRows = [
                    ['user_id' => '', 'approval_order' => 1],
                    ['user_id' => '', 'approval_order' => 2],
                    ['user_id' => '', 'approval_order' => 3],
                ];
            }
        @endphp

        <form method="POST" action="{{ route('inventory.purchase-approval-settings.update', ['tab' => 'purchase-approval']) }}" id="approvalSettingsForm">
            @csrf
            @method('PUT')

            <div class="progga-card mb-4">
                <div class="progga-card-header">
                    <div class="progga-card-title"><i class="bi bi-diagram-3 me-2"></i>Purchase Approval Workflow</div>
                </div>
                <div class="progga-card-body">
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="progga-form-label">Approval Workflow</label>
                            <div class="form-check form-switch mt-2">
                                <input type="hidden" name="is_enabled" value="0">
                                <input class="form-check-input" type="checkbox" name="is_enabled" value="1" id="approvalEnabled" @checked(old('is_enabled', $purchaseApprovalSetting->is_enabled))>
                                <label class="form-check-label" for="approvalEnabled">Require Purchase Voucher approval</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="progga-form-label">Minimum Approval Officers</label>
                            <input type="number" min="1" max="10" name="minimum_approvers" class="progga-form-control" value="{{ old('minimum_approvers', $purchaseApprovalSetting->minimum_approvers ?: 3) }}" required>
                            <small class="text-muted">Recommended: 3 or 4 officers.</small>
                        </div>
                        <div class="col-md-4">
                            <div class="alert alert-info mb-0 py-2">Selective approval: choose who receives each voucher now; you can send to other officers later.</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="progga-card mb-4">
                <div class="progga-card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="progga-card-title"><i class="bi bi-people me-2"></i>Approval Officers</div>
                        <div class="small text-muted">Select the users who are allowed to receive Purchase Voucher approval requests. The order is only for display/default sorting; sending is selective.</div>
                    </div>
                    <button type="button" class="progga-btn progga-btn-secondary progga-btn-sm" onclick="addApproverRow()">
                        <i class="bi bi-plus-lg"></i> Add Officer
                    </button>
                </div>
                <div class="progga-card-body" style="padding:0;">
                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th style="width:140px">Display Order</th>
                                    <th>User</th>
                                    <th>Email</th>
                                    <th style="width:70px"></th>
                                </tr>
                            </thead>
                            <tbody id="approverRows">
                                @foreach($approvalRows as $idx => $row)
                                    <tr class="approver-row">
                                        <td>
                                            <input type="number" min="1" max="10" name="approvers[{{ $idx }}][approval_order]" value="{{ $row['approval_order'] ?? ($idx + 1) }}" class="progga-form-control approval-order" required>
                                        </td>
                                        <td>
                                            <select name="approvers[{{ $idx }}][user_id]" class="progga-form-control approver-user" onchange="syncApproverEmail(this)" required>
                                                <option value="">Select user</option>
                                                @foreach($purchaseApprovalUsers as $user)
                                                    <option value="{{ $user->id }}" data-email="{{ $user->email }}" @selected((string)($row['user_id'] ?? '') === (string)$user->id)>
                                                        {{ $user->name }}{{ $user->user_id ? ' · '.$user->user_id : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                        <td class="approver-email text-muted">—</td>
                                        <td>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeApproverRow(this)" title="Remove officer">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="d-flex justify-content-between align-items-center">
                <small class="text-muted">This setting controls Purchase Voucher approval only. Stock increases only when approved supply is received.</small>
                <button class="progga-btn progga-btn-primary">
                    <i class="bi bi-check2-circle"></i> Save Approval Setup
                </button>
            </div>
        </form>
    @endif
</div>
@endcan
