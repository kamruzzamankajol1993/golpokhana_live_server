<?php
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\DeliveryPartner;
use Illuminate\Http\Request;

class DeliveryPartnerController extends Controller
{
    public function index(Request $request)
    {
        $query = DeliveryPartner::latest();
        $partners = $query->paginate(10);

        if ($request->ajax()) {
            return view('admin.delivery_partner.table', compact('partners'))->render();
        }

        return view('admin.delivery_partner.index');
    }

    public function store(Request $request)
    {
        $request->validate(['name'=>'required|string|max:100']);
        DeliveryPartner::create([
            'name'=>$request->name,
            'status'=>$request->has('status') ? 1 : 0,
        ]);
        return back()->with('success','Delivery partner added');
    }

    public function update(Request $request,$id)
    {
        $request->validate(['name'=>'required|string|max:100']);
        DeliveryPartner::findOrFail($id)->update([
            'name'=>$request->name,
            'status'=>$request->has('status') ? 1 : 0,
        ]);
        return back()->with('success','Delivery partner updated');
    }

    public function destroy($id)
    {
        DeliveryPartner::findOrFail($id)->delete();
        return back()->with('success','Delivery partner deleted');
    }
}
