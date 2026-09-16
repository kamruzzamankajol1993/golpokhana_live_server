<div class="modal fade progga-modal" id="linkWaiterUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-md">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-link-45deg me-2"></i>Link Existing User</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <form action="{{ route('waiter.link_user') }}" method="POST">
                @csrf
                <input type="hidden" name="waiter_id" id="link_waiter_id">

                <div class="modal-body">
                    <div class="alert alert-info" style="font-size:12px;">
                        Link <strong id="link_waiter_name">this waiter</strong> with an existing user account that already has the Waiter role.
                        Email, phone and name are used only to suggest the closest exact match; you can still choose the correct user manually.
                    </div>

                    <div class="progga-form-group">
                        <label class="progga-form-label">Waiter User Account <span class="progga-required">*</span></label>
                        <select name="user_id" id="link_waiter_user_id" class="progga-select" required>
                            <option value="">Select Waiter User</option>
                            @foreach($waiterUsers as $user)
                                <option value="{{ $user->id }}"
                                        data-user-id="{{ $user->id }}"
                                        data-name="{{ $user->name }}"
                                        data-email="{{ $user->email }}"
                                        data-phone="{{ $user->phone }}">
                                    {{ $user->name }}@if($user->email) — {{ $user->email }}@elseif($user->phone) — {{ $user->phone }}@endif
                                </option>
                            @endforeach
                        </select>

                        @if($waiterUsers->isEmpty())
                            <div class="text-muted mt-2" style="font-size:11px;">
                                No unlinked Waiter-role user is currently available. Create the user from User Management first, then return here.
                            </div>
                        @endif
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="progga-btn progga-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="progga-btn progga-btn-primary" {{ $waiterUsers->isEmpty() ? 'disabled' : '' }}>
                        <i class="bi bi-link-45deg"></i> Link User
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
