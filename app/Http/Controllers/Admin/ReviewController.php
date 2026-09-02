<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function index()
    {
        // সব রিভিউ সর্বশেষ সময় অনুযায়ী ডাটাবেজ থেকে আনা হচ্ছে
        $reviews = Review::with(['order.customer', 'order.waiter', 'order.user', 'order.table'])->orderByDesc('updated_at')->paginate(15);
        return view('admin.review.index', compact('reviews'));
    }
}
