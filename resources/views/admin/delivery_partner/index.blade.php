@extends('admin.master.master')
@section('title', 'Delivery Partners — ' . $restaurantSettingName)
@section('body')
<main class="progga-content">
<div class="progga-page-header">
<div><h1 class="progga-page-title">Delivery Partners</h1></div>
<button class="progga-btn progga-btn-primary" data-bs-toggle="modal" data-bs-target="#deliveryPartnerModal" onclick="resetDeliveryPartner()"><i class="bi bi-plus-lg"></i> Add Partner</button>
</div>
<div class="progga-card" id="deliveryPartnerContainer"><div class="text-center py-4">Loading...</div></div>
</main>
<div class="modal fade progga-modal" id="deliveryPartnerModal">
<div class="modal-dialog"><div class="modal-content">
<div class="modal-header"><h5 class="modal-title">Delivery Partner</h5><button class="btn-close" data-bs-dismiss="modal"></button></div>
<div class="modal-body">
<form id="deliveryPartnerForm">@csrf<input type="hidden" id="delivery_partner_id">
<div class="mb-3"><input id="delivery_partner_name" name="name" class="progga-form-control" placeholder="Partner Name" required></div>
<label class="progga-toggle"><input type="checkbox" id="delivery_partner_status" name="status" checked><span class="progga-toggle-track"><span class="progga-toggle-thumb"></span></span></label>
<button class="progga-btn progga-btn-primary mt-3">Save</button>
</form>
</div></div></div></div>
@endsection
@section('script')
<script>
$(function(){
 loadDeliveryPartners();
 function loadDeliveryPartners(url){ url=url||"{{route('delivery-partner.index')}}"; $.get(url,function(d){$("#deliveryPartnerContainer").html(d);}); }
 $('#deliveryPartnerForm').submit(function(e){e.preventDefault();let id=$('#delivery_partner_id').val();$.ajax({url:id?"{{url('delivery-partner')}}/"+id:"{{route('delivery-partner.store')}}",type:id?'PUT':'POST',data:$(this).serialize(),success:function(){ $('#deliveryPartnerModal').modal('hide');loadDeliveryPartners();}});});
 window.editDeliveryPartner=function(id){$.get("{{url('delivery-partner')}}/"+id+"/edit",function(d){$('#delivery_partner_id').val(d.id);$('#delivery_partner_name').val(d.name);$('#delivery_partner_status').prop('checked',d.status==1);$('#deliveryPartnerModal').modal('show');});}
 window.resetDeliveryPartner=function(){$('#deliveryPartnerForm')[0].reset();$('#delivery_partner_id').val('');}
 $(document).on('click','#deliveryPartnerContainer .progga-pagination a',function(e){e.preventDefault();loadDeliveryPartners($(this).attr('href'));});
 window.deleteDeliveryPartner=function(id){Swal.fire({title:'Delete Delivery Partner?',text:'This action cannot be undone.',icon:'warning',showCancelButton:true,confirmButtonText:'Delete',confirmButtonColor:'#c63d3d'}).then(function(r){if(!r.isConfirmed)return;$.ajax({url:"{{url('delivery-partner')}}/"+id,type:'DELETE',data:{_token:'{{csrf_token()}}'},success:function(res){Swal.fire({icon:'success',title:'Deleted',timer:1200,showConfirmButton:false});loadDeliveryPartners();}});});}
});
</script>
@endsection
