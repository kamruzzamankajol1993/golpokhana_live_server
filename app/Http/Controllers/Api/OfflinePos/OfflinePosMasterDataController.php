<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\FoodCategory;
use App\Models\FoodItem;
use App\Models\Table as RestaurantTable;
use App\Models\User;
use App\Models\Waiter;
use App\Models\Zone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OfflinePosMasterDataController extends Controller
{
    private function guard(Request $request)
    {
        $validKey = config('offline_pos.sync_key') ?: config('services.offline_pos.sync_key') ?: env('OFFLINE_POS_SYNC_KEY');
        $givenKey = $request->header('X-OFFLINE-POS-KEY');

        if (!$validKey || !$givenKey || !hash_equals($validKey, $givenKey)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized offline POS request.',
            ], 401);
        }

        return null;
    }

    private function success(string $key, $data)
    {
        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            $key => $data,
        ]);
    }

    private function tableName(string $modelClass): string
    {
        return (new $modelClass)->getTable();
    }

    private function hasColumn(string $modelClass, string $column): bool
    {
        return Schema::hasColumn($this->tableName($modelClass), $column);
    }

    private function existingColumns(string $modelClass, array $columns): array
    {
        $tableColumns = Schema::getColumnListing($this->tableName($modelClass));

        return array_values(array_intersect($columns, $tableColumns));
    }

    private function applyLastSyncedAt($query, string $modelClass, Request $request)
    {
        $lastSyncedAt = $request->query('last_synced_at');

        if ($lastSyncedAt && $this->hasColumn($modelClass, 'updated_at')) {
            $query->where('updated_at', '>', $lastSyncedAt);
        }

        return $query;
    }

    private function applyStatusFilter($query, string $modelClass, Request $request)
    {
        if ((string) $request->query('include_inactive', '0') === '1') {
            return $query;
        }

        if ($this->hasColumn($modelClass, 'status')) {
            $query->where(function ($statusQuery) {
                $statusQuery->where('status', 1)
                    ->orWhere('status', '1')
                    ->orWhere('status', 'active')
                    ->orWhere('status', 'Active');
            });
        }

        return $query;
    }

    private function modelArray($model): array
    {
        $data = $model->toArray();
        $data['server_id'] = $model->getKey();

        return $data;
    }

    private function publicUrl(?string $path, ?string $prefix = null): ?string
    {
        if (!$path) {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $path = ltrim($path, '/');

        if ($prefix && !Str::contains($path, '/')) {
            $path = trim($prefix, '/') . '/' . $path;
        }

        return asset('public/' . $path);
    }

    public function users(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        $columns = $this->existingColumns(User::class, [
            'id', 'name', 'email', 'phone', 'password', 'status', 'created_at', 'updated_at'
        ]);

        $query = User::query()->select($columns);
        $query = $this->applyLastSyncedAt($query, User::class, $request);
        $query = $this->applyStatusFilter($query, User::class, $request);

        $users = $query->orderBy('id', 'asc')->get()->map(function ($user) {
            $roles = method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values() : [];
            $permissions = method_exists($user, 'getAllPermissions')
                ? $user->getAllPermissions()->pluck('name')->values()
                : [];

            return [
                'server_id' => $user->id,
                'name' => $user->name ?? null,
                'email' => $user->email ?? null,
                'phone' => $user->phone ?? null,
                'password_hash' => $user->password ?? null,
                'status' => $user->status ?? 'active',
                'roles' => $roles,
                'permissions' => $permissions,
                'created_at' => optional($user->created_at)->toDateTimeString(),
                'updated_at' => optional($user->updated_at)->toDateTimeString(),
            ];
        });

        return $this->success('users', $users);
    }

    public function zones(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        $columns = $this->existingColumns(Zone::class, [
            'id', 'name', 'status', 'created_at', 'updated_at'
        ]);

        $query = Zone::query()->select($columns);
        $query = $this->applyLastSyncedAt($query, Zone::class, $request);
        $query = $this->applyStatusFilter($query, Zone::class, $request);

        $zones = $query->orderBy('id', 'asc')->get()->map(fn ($zone) => $this->modelArray($zone));

        return $this->success('zones', $zones);
    }

    public function tables(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        $columns = $this->existingColumns(RestaurantTable::class, [
            'id', 'zone_id', 'table_number', 'name', 'seating_capacity', 'initial_status', 'status', 'created_at', 'updated_at'
        ]);

        $query = RestaurantTable::query()
            ->select($columns)
            ->with('zone');

        $query = $this->applyLastSyncedAt($query, RestaurantTable::class, $request);
        $query = $this->applyStatusFilter($query, RestaurantTable::class, $request);

        $tables = $query->orderBy('id', 'asc')->get()->map(function ($table) {
            $data = $this->modelArray($table);
            $data['zone_server_id'] = $table->zone_id ?? null;
            $data['zone_name'] = $table->zone->name ?? null;

            return $data;
        });

        return $this->success('tables', $tables);
    }

    public function waiters(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        $columns = $this->existingColumns(Waiter::class, [
            'id', 'user_id', 'name', 'phone', 'email', 'address', 'status', 'created_at', 'updated_at'
        ]);

        $query = Waiter::query()->select($columns);
        $query = $this->applyLastSyncedAt($query, Waiter::class, $request);
        $query = $this->applyStatusFilter($query, Waiter::class, $request);

        $waiters = $query->orderBy('name', 'asc')->get()->map(function ($waiter) {
            $data = $this->modelArray($waiter);
            $data['user_server_id'] = $waiter->user_id ?? null;

            return $data;
        });

        return $this->success('waiters', $waiters);
    }

    public function customers(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        $columns = $this->existingColumns(Customer::class, [
            'id', 'name', 'phone', 'email', 'address', 'customer_type', 'total_points', 'status', 'created_at', 'updated_at'
        ]);

        $query = Customer::query()->select($columns);
        $query = $this->applyLastSyncedAt($query, Customer::class, $request);
        $query = $this->applyStatusFilter($query, Customer::class, $request);

        $customers = $query->orderBy('name', 'asc')->get()->map(fn ($customer) => $this->modelArray($customer));

        return $this->success('customers', $customers);
    }

    public function foodCategories(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        $columns = $this->existingColumns(FoodCategory::class, [
            'id', 'parent_category_id', 'name', 'slug', 'image', 'icon', 'sort_order', 'status', 'created_at', 'updated_at'
        ]);

        $query = FoodCategory::query()->select($columns);
        $query = $this->applyLastSyncedAt($query, FoodCategory::class, $request);
        $query = $this->applyStatusFilter($query, FoodCategory::class, $request);

        if ($this->hasColumn(FoodCategory::class, 'sort_order')) {
            $query->orderBy('sort_order', 'asc');
        }

        $categories = $query->orderBy('id', 'asc')->get()->map(function ($category) {
            $data = $this->modelArray($category);
            $data['parent_category_server_id'] = $category->parent_category_id ?? null;
            $data['image_url'] = $this->publicUrl($category->image ?? null, 'uploads/food-categories');
            $data['icon_url'] = $this->publicUrl($category->icon ?? null, 'uploads/food-categories');

            return $data;
        });

        return $this->success('food_categories', $categories);
    }

    public function foodItems(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        $columns = $this->existingColumns(FoodItem::class, [
            'id', 'food_category_id', 'category_id', 'sub_category_id', 'name', 'slug', 'sku', 'base_price', 'discount_price', 'price',
            'main_image', 'image', 'description', 'is_available', 'status', 'sort_order', 'preparation_time', 'created_at', 'updated_at'
        ]);

        $query = FoodItem::query()
            ->select($columns)
            ->with('addons');

        $query = $this->applyLastSyncedAt($query, FoodItem::class, $request);

        if ((string) $request->query('include_unavailable', '0') !== '1') {
            if ($this->hasColumn(FoodItem::class, 'is_available')) {
                $query->where('is_available', 1);
            }
            $query = $this->applyStatusFilter($query, FoodItem::class, $request);
        }

        if ($this->hasColumn(FoodItem::class, 'sort_order')) {
            $query->orderBy('sort_order', 'asc');
        }

        $foods = $query->orderBy('id', 'asc')->get()->map(function ($food) {
            $data = $this->modelArray($food);
            $data['category_server_id'] = $food->food_category_id ?? $food->category_id ?? null;
            $data['sub_category_server_id'] = $food->sub_category_id ?? null;
            $data['main_image_url'] = $this->publicUrl($food->main_image ?? null, 'uploads/foods');
            $data['image_url'] = $this->publicUrl($food->image ?? null, 'uploads/foods');
            $data['addons'] = $food->addons->map(function ($addon) {
                $addonData = $addon->toArray();
                $addonData['server_id'] = $addon->id;
                $addonData['pivot'] = $addon->pivot ? $addon->pivot->toArray() : null;

                return $addonData;
            })->values();

            return $data;
        });

        return $this->success('food_items', $foods);
    }

    public function foodAddons(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        $query = FoodItem::query()
            ->select($this->existingColumns(FoodItem::class, ['id', 'name', 'updated_at']))
            ->with('addons');

        $query = $this->applyLastSyncedAt($query, FoodItem::class, $request);

        $addons = $query->get()->flatMap(function ($food) {
            return $food->addons->map(function ($addon) use ($food) {
                $addonData = $addon->toArray();

                return [
                    'food_item_server_id' => $food->id,
                    'addon_server_id' => $addon->id,
                    'server_id' => $addon->id,
                    'name' => $addon->name ?? null,
                    'price' => $addon->price ?? ($addon->pivot->price ?? 0),
                    'status' => $addon->status ?? 'active',
                    'pivot' => $addon->pivot ? $addon->pivot->toArray() : null,
                    'data' => $addonData,
                    'created_at' => isset($addon->created_at) ? optional($addon->created_at)->toDateTimeString() : null,
                    'updated_at' => isset($addon->updated_at) ? optional($addon->updated_at)->toDateTimeString() : null,
                ];
            });
        })->values();

        return $this->success('food_addons', $addons);
    }

    public function masterData(Request $request)
    {
        if ($response = $this->guard($request)) {
            return $response;
        }

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'users' => $this->users($request)->getData(true)['users'],
            'zones' => $this->zones($request)->getData(true)['zones'],
            'tables' => $this->tables($request)->getData(true)['tables'],
            'waiters' => $this->waiters($request)->getData(true)['waiters'],
            'customers' => $this->customers($request)->getData(true)['customers'],
            'food_categories' => $this->foodCategories($request)->getData(true)['food_categories'],
            'food_items' => $this->foodItems($request)->getData(true)['food_items'],
            'food_addons' => $this->foodAddons($request)->getData(true)['food_addons'],
        ]);
    }
}
