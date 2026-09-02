@extends('admin.master.master')
@section('title','Floor / Zone')
@section('body')
<main class="progga-content">
<div class="progga-page-header">
<div><h1 class="progga-page-title">Floor / Zone</h1></div>
<button class="progga-btn progga-btn-primary" data-bs-toggle="modal" data-bs-target="#floorZoneModal" onclick="resetFloorZone()"><i class="bi bi-plus-lg"></i> Add</button>
</div>
<div class="progga-card" id="floorZoneContainer"><div class="text-center py-4">Loading...</div></div>
</main>

<div class="modal fade progga-modal" id="floorZoneModal">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Floor / Zone</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<form id="floorZoneForm">@csrf<input type="hidden" id="floor_zone_id">
<div class="mb-3"><input id="floor_zone_name" name="name" class="progga-form-control" placeholder="Name" required></div>
<label class="progga-toggle"><input type="checkbox" id="floor_zone_status" name="status" checked><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span></label>
<button class="progga-btn progga-btn-primary mt-3" id="floorZoneBtn">Save</button>
</form>
</div></div></div></div>
@endsection
@section('script')
<script>
$(function(){
 loadFloorZones();
 function loadFloorZones(url){
  url=url||"{{route('floor-zone.index')}}";
  $.get(url,function(d){$("#floorZoneContainer").html(d);});
 }
 $('#floorZoneForm').submit(function(e){
  e.preventDefault(); let id=$('#floor_zone_id').val();
  $.ajax({url:id?"{{url('floor-zone')}}/"+id:"{{route('floor-zone.store')}}",type:id?'PUT':'POST',data:$(this).serialize(),success:function(r){if(r.success){$('#floorZoneModal').modal('hide');loadFloorZones();}}});
 });
 window.editFloorZone=function(id){$.get("{{url('floor-zone')}}/"+id+'/edit',function(d){$('#floor_zone_id').val(d.id);$('#floor_zone_name').val(d.name);$('#floor_zone_status').prop('checked',d.status==1);$('#floorZoneBtn').text('Update');$('#floorZoneModal').modal('show');});}
 window.resetFloorZone=function(){$('#floorZoneForm')[0].reset();$('#floor_zone_id').val('');$('#floorZoneBtn').text('Save');}
 $(document).on('click','#floorZoneContainer .progga-pagination a',function(e){e.preventDefault();loadFloorZones($(this).attr('href'));});
let floorSearchTimer; $(document).on('keyup','#floorZoneSearch',function(){clearTimeout(floorSearchTimer); floorSearchTimer=setTimeout(()=>loadFloorZones(),500);});
 window.deleteFloorZone=function(id){Swal.fire({title:'Delete Floor / Zone?',text:'This action cannot be undone.',icon:'warning',showCancelButton:true,confirmButtonText:'Delete',confirmButtonColor:'#c63d3d'}).then(function(r){if(!r.isConfirmed)return;$.ajax({url:"{{url('floor-zone')}}/"+id,type:'DELETE',data:{_token:'{{csrf_token()}}'},success:function(res){Swal.fire({icon:'success',title:'Deleted',text:res.message||'Deleted successfully',timer:1400,showConfirmButton:false});loadFloorZones();},error:function(xhr){Swal.fire('Cannot delete',xhr.responseJSON?.message||'Failed.','error');}});});}
});
</script>
@endsection
