@extends('admin.master.master')
@section('title','Inventory Units')
@section('body')
<main class="progga-content">
    <div class="progga-page-header">
        <div>
            <h1 class="progga-page-title">Inventory Units</h1>
            <p class="text-muted mb-0">Standard units convert by measurement dimension; package units require an ingredient-specific conversion.</p>
        </div>
        <button class="progga-btn progga-btn-primary" data-bs-toggle="modal" data-bs-target="#unitModal" onclick="resetUnitForm()">
            <i class="bi bi-plus-lg"></i> Add Unit
        </button>
    </div>

    @if(session('success'))<div class="alert alert-success">{{ session('success') }}</div>@endif
    @if($errors->any())
        <div class="alert alert-danger"><strong>Please fix the following:</strong><ul class="mb-0 mt-1">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif

    <div class="progga-card">
        <div class="progga-card-header units-toolbar">
            <div class="units-result-copy">
                <span class="fw-semibold">Unit List</span>
                <small class="text-muted" id="unitsResultCount">{{ $units->total() }} {{ $units->total() === 1 ? 'record' : 'records' }}</small>
            </div>
            <div class="units-search-wrap">
                <i class="bi bi-search units-search-icon"></i>
                <input
                    type="search"
                    id="unitsSearch"
                    class="progga-form-control units-search-input"
                    value="{{ request('search') }}"
                    placeholder="Search unit or symbol..."
                    autocomplete="off"
                    aria-label="Search units">
                <button type="button" id="unitsSearchClear" class="units-search-clear" aria-label="Clear search" title="Clear search">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>

        <div id="unitsTableContent" class="units-table-content">
            @include('admin.inventory.units.partials.table', ['units' => $units])
        </div>
    </div>
</main>

<div class="modal fade progga-modal" id="unitModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="unitModalTitle">Add Unit</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <form id="unitForm" method="POST" action="{{ route('inventory.units.store') }}">
            @csrf
            <input type="hidden" name="_method" id="unitMethod" value="POST">
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6"><label class="progga-form-label">Name</label><input class="progga-form-control" name="name" id="unitName" required maxlength="80"></div>
                    <div class="col-md-3"><label class="progga-form-label">Symbol</label><input class="progga-form-control" name="symbol" id="unitSymbol" required maxlength="20"></div>
                    <div class="col-md-3">
                        <label class="progga-form-label">Dimension</label>
                        <select class="progga-form-control" name="dimension" id="unitDimension" required onchange="toggleUnitFactor()">
                            @foreach(\App\Models\Unit::dimensions() as $dimension)<option value="{{ $dimension }}">{{ $dimension }}</option>@endforeach
                        </select>
                        <small class="text-muted">Type of measurement: WEIGHT, VOLUME, COUNT or PACKAGE.</small>
                    </div>
                    <div class="col-md-6" id="factorWrap">
                        <label class="progga-form-label">Standard to Base Factor</label>
                        <input class="progga-form-control" type="number" step="0.01" min="0.01" name="standard_to_base_factor" id="unitFactor" value="1.00">
                        <small class="text-muted">How many base units equal 1 of this unit. Example: 1 kg = 1000 g, so factor = 1000.</small>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" name="is_base" value="1" id="unitBase" onchange="toggleBaseFactor()">
                            <label class="form-check-label" for="unitBase">Base unit</label>
                            <div><small class="text-muted">Main reference unit for this dimension; its factor must be 1.</small></div>
                        </div>
                    </div>
                    <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="is_active" value="1" id="unitActive" checked><label class="form-check-label" for="unitActive">Active</label></div></div>
                </div>
                <div class="alert alert-info mt-3 mb-0 py-2">Examples: KG = 1000 Gram, Liter = 1000 ML, Dozen = 12 Piece. Packet/Bottle/Bag/Box/Carton should use <strong>PACKAGE</strong>; their factor is set separately for each ingredient.</div>
            </div>
            <div class="modal-footer"><button type="button" class="progga-btn progga-btn-light" data-bs-dismiss="modal">Cancel</button><button class="progga-btn progga-btn-primary">Save Unit</button></div>
        </form>
    </div></div>
</div>
@endsection

@section('script')
<style>
    .units-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        flex-wrap: wrap;
    }
    .units-result-copy {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .units-search-wrap {
        position: relative;
        width: min(100%, 360px);
        margin-left: auto;
    }
    .units-search-input {
        width: 100%;
        padding-left: 40px !important;
        padding-right: 38px !important;
    }
    .units-search-icon {
        position: absolute;
        left: 14px;
        top: 50%;
        transform: translateY(-50%);
        color: #6c757d;
        pointer-events: none;
        z-index: 2;
    }
    .units-search-clear {
        position: absolute;
        right: 9px;
        top: 50%;
        transform: translateY(-50%);
        border: 0;
        background: transparent;
        color: #6c757d;
        width: 28px;
        height: 28px;
        display: none;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        padding: 0;
    }
    .units-search-clear:hover { background: rgba(108,117,125,.12); }
    .units-table-content { position: relative; min-height: 160px; }
    .units-table-content.is-loading { opacity: .55; pointer-events: none; }

    /* Match the existing Order List pagination design exactly. */
    .progga-units-pagination-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        padding: 14px 16px;
        border-top: 1px solid var(--progga-border-light);
        flex-wrap: wrap;
    }
    .progga-pagination-wrap { display: flex; justify-content: flex-end; }
    .progga-pagination {
        display: flex;
        align-items: center;
        gap: 6px;
        flex-wrap: wrap;
    }
    .progga-page-btn,
    .progga-page-num {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 5px;
        min-height: 34px;
        padding: 7px 11px;
        border: 1px solid var(--progga-border-light);
        border-radius: 8px;
        background: #fff;
        color: var(--progga-text);
        font-size: 12px;
        font-weight: 800;
        text-decoration: none;
        transition: all .15s ease;
    }
    .progga-page-num { min-width: 34px; padding-left: 9px; padding-right: 9px; }
    .progga-page-btn:hover,
    .progga-page-num:hover {
        border-color: var(--progga-primary);
        color: var(--progga-primary);
        background: rgba(33, 53, 42, 0.05);
    }
    .progga-page-num.active {
        background: var(--progga-primary);
        border-color: var(--progga-primary);
        color: #fff;
        cursor: default;
    }
    .progga-page-btn.disabled {
        opacity: .45;
        pointer-events: none;
        cursor: not-allowed;
        background: #f8f9fa;
    }
    .progga-page-ellipsis {
        padding: 0 4px;
        color: var(--progga-text-muted);
        font-weight: 800;
    }
    @media (max-width: 767.98px) {
        .units-search-wrap { width: 100%; }
        .progga-units-pagination-footer {
            justify-content: center;
            text-align: center;
        }
        .progga-pagination {
            justify-content: center;
        }
    }
</style>
<script>
const unitsIndexUrl = {{ \Illuminate\Support\Js::from(route('inventory.units.index')) }};
let unitsSearchTimer = null;
let unitsRequestController = null;

function resetUnitForm(){
    const form=document.getElementById('unitForm');
    form.reset();
    form.action={{ \Illuminate\Support\Js::from(route('inventory.units.store')) }};
    document.getElementById('unitMethod').value='POST';
    document.getElementById('unitModalTitle').textContent='Add Unit';
    document.getElementById('unitDimension').value='WEIGHT';
    document.getElementById('unitFactor').value='1.00';
    document.getElementById('unitBase').checked=false;
    document.getElementById('unitActive').checked=true;
    toggleUnitFactor();
}

function editUnit(button){
    const form=document.getElementById('unitForm');
    const data=button.dataset;

    form.action={{ \Illuminate\Support\Js::from(url('inventory/units')) }}+'/'+data.id;
    document.getElementById('unitMethod').value='PUT';
    document.getElementById('unitModalTitle').textContent='Edit Unit';
    document.getElementById('unitName').value=data.name || '';
    document.getElementById('unitSymbol').value=data.symbol || '';
    document.getElementById('unitDimension').value=data.dimension || 'WEIGHT';
    document.getElementById('unitBase').checked=data.isBase === '1';
    document.getElementById('unitFactor').value=data.factor || '';
    document.getElementById('unitActive').checked=data.isActive === '1';
    toggleUnitFactor();

    bootstrap.Modal.getOrCreateInstance(document.getElementById('unitModal')).show();
}

function toggleUnitFactor(){
    const isPackage=document.getElementById('unitDimension').value==='PACKAGE';
    const factor=document.getElementById('unitFactor');
    const base=document.getElementById('unitBase');

    document.getElementById('factorWrap').style.display=isPackage?'none':'';
    factor.disabled=isPackage;
    base.disabled=isPackage;

    if(isPackage){
        base.checked=false;
        factor.value='';
        factor.readOnly=false;
    } else {
        if(!factor.value) factor.value='1.00';
        toggleBaseFactor();
    }
}

function toggleBaseFactor(){
    const base=document.getElementById('unitBase');
    const factor=document.getElementById('unitFactor');

    if(base.checked && !base.disabled){
        factor.value='1.00';
        factor.readOnly=true;
    } else {
        factor.readOnly=false;
    }
}

function confirmDeleteUnit(button){
    const form=button.closest('form');
    const unitName=button.dataset.unitName || 'this unit';

    Swal.fire({
        title:'Delete this unit?',
        text:`Delete ${unitName}? This action cannot be undone.`,
        icon:'warning',
        showCancelButton:true,
        confirmButtonColor:'#dc3545',
        cancelButtonColor:'#6c757d',
        confirmButtonText:'Yes, delete it!',
        cancelButtonText:'Cancel',
        allowOutsideClick:false
    }).then((result)=>{
        if(result.isConfirmed){
            form.submit();
        }
    });
}

function updateUnitsSearchClear(){
    const input=document.getElementById('unitsSearch');
    const clear=document.getElementById('unitsSearchClear');
    clear.style.display=input.value.length ? 'inline-flex' : 'none';
}

function buildUnitsUrl(page=1){
    const url=new URL(unitsIndexUrl, window.location.origin);
    const search=document.getElementById('unitsSearch').value.trim();

    if(search) url.searchParams.set('search',search);
    if(page>1) url.searchParams.set('page',page);

    return url;
}

async function loadUnits(page=1){
    const container=document.getElementById('unitsTableContent');
    const url=buildUnitsUrl(page);

    if(unitsRequestController) unitsRequestController.abort();
    const requestController=new AbortController();
    unitsRequestController=requestController;
    container.classList.add('is-loading');

    try{
        const response=await fetch(url.toString(),{
            method:'GET',
            headers:{
                'X-Requested-With':'XMLHttpRequest',
                'Accept':'application/json'
            },
            signal:requestController.signal
        });

        if(!response.ok) throw new Error('Unable to load units.');

        const data=await response.json();
        container.innerHTML=data.html;
        document.getElementById('unitsResultCount').textContent=data.total+' '+(data.total===1?'record':'records');
        history.replaceState({},'',url.pathname+url.search);
    }catch(error){
        if(error.name!=='AbortError'){
            console.error(error);
        }
    }finally{
        if(unitsRequestController===requestController){
            container.classList.remove('is-loading');
        }
    }
}

function queueUnitsSearch(){
    updateUnitsSearchClear();
    clearTimeout(unitsSearchTimer);
    unitsSearchTimer=setTimeout(()=>loadUnits(1),300);
}

document.addEventListener('DOMContentLoaded',()=>{
    toggleUnitFactor();
    updateUnitsSearchClear();

    const searchInput=document.getElementById('unitsSearch');
    searchInput.addEventListener('input',queueUnitsSearch);

    document.getElementById('unitsSearchClear').addEventListener('click',()=>{
        searchInput.value='';
        updateUnitsSearchClear();
        searchInput.focus();
        loadUnits(1);
    });

    document.getElementById('unitsTableContent').addEventListener('click',(event)=>{
        const link=event.target.closest('.progga-pagination a[data-units-page]');
        if(!link || link.classList.contains('disabled')) return;
        event.preventDefault();
        loadUnits(Number(link.dataset.unitsPage || 1));
    });
});
</script>
@endsection
