<style>
.custom-pagination-wrapper{display:flex;align-items:center;gap:6px;}
.custom-pagination-wrapper a,.custom-pagination-wrapper span{width:34px;height:34px;display:inline-flex;align-items:center;justify-content:center;border:1px solid #e5ddd0;border-radius:8px;background:#fff;color:#21352a;text-decoration:none;font-size:13px;font-weight:700;transition:.2s;}
.custom-pagination-wrapper a:hover{background:#21352a;color:#fff;}
.custom-pagination-wrapper a.active{background:#21352a;color:#fff;border-color:#21352a;}
.custom-pagination-wrapper .disabled{color:#b8b8b8;background:#f7f5ef;cursor:not-allowed;}
.progga-card-footer{margin-top:0!important;border-top:1px solid #eee7db;}
.progga-page-info{font-size:13px;font-weight:600;}
</style>
<div class="progga-table-wrapper">
<table class="progga-table">
<thead><tr><th width="70">SL</th><th>Name</th><th>Status</th><th width="120">Action</th></tr></thead>
<tbody>
@forelse($floorZones as $key=>$item)
<tr>
<td>{{ $floorZones->firstItem()+$key }}</td>
<td>{{ $item->name }}</td>
<td><span class="progga-badge">{{ $item->status?'Active':'Inactive' }}</span></td>
<td>
<button class="progga-btn progga-btn-outline progga-btn-icon progga-btn-sm" title="Edit" onclick="editFloorZone({{ $item->id }})"><i class="bi bi-pencil"></i></button>
<button class="progga-btn progga-btn-danger progga-btn-icon progga-btn-sm" title="Delete" onclick="deleteFloorZone({{ $item->id }})"><i class="bi bi-trash"></i></button>
</td>
</tr>
@empty
<tr><td colspan="4" class="text-center">No floor zone found</td></tr>
@endforelse
</tbody>
</table>
</div>
<div class="progga-card-footer" style="display:flex;align-items:center;justify-content:space-between;padding:15px;">
<span class="progga-page-info" style="font-size:13px;color:var(--progga-text-muted);">Showing {{ $floorZones->firstItem() ?? 0 }}–{{ $floorZones->lastItem() ?? 0 }} of {{ $floorZones->total() }}</span>
@if($floorZones->hasPages())
<div class="progga-pagination custom-pagination-wrapper">
@if($floorZones->onFirstPage())<span class="disabled"><i class="bi bi-chevron-left"></i></span>@else<a href="{{ $floorZones->previousPageUrl() }}"><i class="bi bi-chevron-left"></i></a>@endif
@foreach($floorZones->getUrlRange(max(1,$floorZones->currentPage()-2),min($floorZones->lastPage(),$floorZones->currentPage()+2)) as $page=>$url)
<a class="{{ $page==$floorZones->currentPage()?'active':'' }}" href="{{ $url }}">{{ $page }}</a>
@endforeach
@if($floorZones->hasMorePages())<a href="{{ $floorZones->nextPageUrl() }}"><i class="bi bi-chevron-right"></i></a>@else<span class="disabled"><i class="bi bi-chevron-right"></i></span>@endif
</div>
@endif
</div>
