@extends('admin.master.master')
@section('title', 'Table QR Code Builder — TableTrack RMS')

@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Table QR Code Builder</h1>
            <div class="progga-breadcrumb">
                <a href="{{ route('home') }}" class="progga-breadcrumb-item">Dashboard</a>
                <span class="progga-breadcrumb-sep">/</span>
                <span class="progga-breadcrumb-item active">QR Code Builder</span>
            </div>
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger" style="font-size: 13px;">
            <i class="bi bi-exclamation-triangle-fill me-2"></i>{{ $errors->first() }}
        </div>
    @endif

    <div class="row">
        <div class="col-md-5">
            <div class="progga-card">
                <div class="progga-card-header">
                    <div class="progga-card-title">Select Zone</div>
                </div>
                <div class="progga-card-body">
                    <div class="progga-form-group">
                        <label class="progga-form-label">Floor / Zone</label>
                        <select class="progga-select" id="zoneSelect">
                            <option value="">-- Select a Zone --</option>
                            @foreach($zones as $zone)
                                <option value="{{ $zone->id }}">{{ $zone->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-3">
                        <small class="text-muted"><i class="bi bi-info-circle"></i> The QR code will redirect to:<br> <strong>{{ rtrim($restaurant->website ?? url('/'), '/') }}/{base64_table_id}</strong></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-7">
            <div class="progga-card">
                <div class="progga-card-header" style="display:flex; justify-content:space-between; align-items:center;">
                    <div class="progga-card-title">Tables in Zone</div>
                    <label class="progga-toggle" style="font-size: 12px; font-weight: bold; cursor:pointer; display:none;" id="selectAllContainer">
                        <input type="checkbox" id="selectAllTables">
                        <span class="ms-2 text-primary">Select All</span>
                    </label>
                </div>

                <form action="{{ route('qrcode.generate_pdf') }}" method="POST" target="_blank">
                    @csrf
                    <div class="progga-card-body" id="tablesContainer" style="min-height: 200px;">
                        <div class="text-center text-muted py-5" id="emptyState">
                            <i class="bi bi-layout-wtf" style="font-size: 3rem; opacity: 0.3;"></i>
                            <p class="mt-2">Select a zone to load tables</p>
                        </div>

                        <div class="row g-3" id="tablesGrid" style="display: none;">
                            </div>
                    </div>
                    <div class="progga-card-footer" style="text-align: right; display: none;" id="formFooter">
                        <button type="submit" class="progga-btn progga-btn-primary"><i class="bi bi-qr-code-scan"></i> Generate QR PDF</button>
                    </div>
                </form>

            </div>
        </div>
    </div>
</main>
@endsection

@section('script')
<script>
$(document).ready(function() {
    $('#zoneSelect').on('change', function() {
        let zoneId = $(this).val();
        let grid = $('#tablesGrid');
        let emptyState = $('#emptyState');
        let footer = $('#formFooter');
        let selectAll = $('#selectAllContainer');

        if(!zoneId) {
            grid.hide().empty();
            footer.hide();
            selectAll.hide();
            emptyState.show();
            return;
        }

        // Show loading state
        emptyState.html('<div class="spinner-border text-primary"></div><p class="mt-2">Loading tables...</p>').show();
        grid.hide().empty();
        footer.hide();
        selectAll.hide();

        // AJAX Call
        $.ajax({
            url: "{{ url('/qr-code-builder/get-tables') }}/" + zoneId,
            type: "GET",
            success: function(res) {
                if(res.tables.length > 0) {
                    let html = '';
                    res.tables.forEach(function(table) {
                        html += `
                        <div class="col-md-4 col-sm-6">
                            <label class="w-100" style="cursor:pointer;">
                                <div style="border:1px solid #e0e0e0; border-radius:8px; padding:12px; display:flex; align-items:center; gap:10px; transition:all 0.2s;" onmouseover="this.style.borderColor='var(--progga-primary)'" onmouseout="this.style.borderColor='#e0e0e0'">
                                    <input type="checkbox" name="table_ids[]" value="${table.id}" class="form-check-input table-checkbox" style="width:18px;height:18px;">
                                    <div>
                                        <div style="font-weight:700; color:var(--progga-text); font-size:14px;">${table.table_number}</div>
                                        <div style="font-size:11px; color:#888;">Capacity: ${table.seating_capacity}</div>
                                    </div>
                                </div>
                            </label>
                        </div>`;
                    });

                    grid.html(html).show();
                    emptyState.hide();
                    footer.show();
                    selectAll.show();
                    $('#selectAllTables').prop('checked', false);
                } else {
                    emptyState.html('<i class="bi bi-x-circle" style="font-size: 3rem; opacity: 0.3;"></i><p class="mt-2">No tables found in this zone.</p>').show();
                }
            }
        });
    });

    // Select All logic
    $('#selectAllTables').on('change', function() {
        $('.table-checkbox').prop('checked', $(this).prop('checked'));
    });
});
</script>
@endsection
