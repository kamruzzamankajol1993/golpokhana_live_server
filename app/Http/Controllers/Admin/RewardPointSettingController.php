<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\RewardPointSetting;
use Exception;

class RewardPointSettingController extends Controller
{
    public function index()
    {
        // ডাটাবেসে সেটিং না থাকলে ডিফল্ট একটি তৈরি করে নেবে (নতুন কলামসহ)
        $setting = RewardPointSetting::firstOrCreate(
            ['id' => 1],
            [
                'reward_type'       => 'amount_based',
                'points_per_order'  => 1,
                'amount_to_spend'   => 500.00,
                'points_per_amount' => 1,
                'points_to_redeem'  => 100, // ডিফল্ট ১০০ পয়েন্ট
                'discount_amount'   => 10.00, // ডিফল্ট ১০ টাকা
            ]
        );

        return view('admin.customer.reward_points', compact('setting'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'reward_type'       => 'required|in:order_based,amount_based',
            'points_per_order'  => 'required|numeric|min:0',
            'amount_to_spend'   => 'required|numeric|min:0',
            'points_per_amount' => 'required|numeric|min:0',
            'points_to_redeem'  => 'required|numeric|min:1', // নতুন ভ্যালিডেশন
            'discount_amount'   => 'required|numeric|min:0', // নতুন ভ্যালিডেশন
        ]);

        try {
            $setting = RewardPointSetting::first();
            $setting->update([
                'reward_type'       => $request->reward_type,
                'points_per_order'  => $request->points_per_order,
                'amount_to_spend'   => $request->amount_to_spend,
                'points_per_amount' => $request->points_per_amount,
                'points_to_redeem'  => $request->points_to_redeem, // নতুন আপডেট
                'discount_amount'   => $request->discount_amount, // নতুন আপডেট
            ]);

            return back()->with('success', 'Reward point rules updated successfully!');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to update reward settings!');
        }
    }
}
