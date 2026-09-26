<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OfflinePosMasterDataController extends Controller
{
    private array $columnsCache = [];

    public function users(Request $request): JsonResponse
    {
        if (!$this->tableExists('users')) {
            return $this->success('users', []);
        }

        $columns = $this->availableColumns('users', [
            'id', 'user_id', 'name', 'first_name', 'last_name', 'email', 'phone', 'image',
            'password', 'status', 'created_at', 'updated_at',
        ]);

        $query = User::query()->select($columns);
        if ($this->since($request) && $this->hasColumn('users', 'updated_at')) {
            $query->where('updated_at', '>', $this->since($request));
        }

        $users = $query->orderBy('id')->get()->map(function (User $user) {
            return [
                'server_id' => $user->id,
                'user_id' => $user->user_id ?? null,
                'name' => $user->name ?? trim(($user->first_name ?? '') . ' ' . ($user->last_name ?? '')),
                'first_name' => $user->first_name ?? null,
                'last_name' => $user->last_name ?? null,
                'email' => $user->email ?? null,
                'phone' => $user->phone ?? null,
                'image' => $user->image ?? null,
                'image_url' => $this->publicUrl($user->image ?? null, 'uploads/users'),
                'password_hash' => $user->password ?? null,
                'status' => $user->status ?? 'active',
                'roles' => method_exists($user, 'getRoleNames') ? $user->getRoleNames()->values()->all() : [],
                'permissions' => method_exists($user, 'getAllPermissions')
                    ? $user->getAllPermissions()->pluck('name')->values()->all()
                    : [],
                'created_at' => $user->created_at?->toDateTimeString(),
                'updated_at' => $user->updated_at?->toDateTimeString(),
            ];
        })->values()->all();

        return $this->success('users', $users);
    }

    public function floorZones(Request $request): JsonResponse
    {
        $table = $this->tableExists('floor_zones') ? 'floor_zones' : ($this->tableExists('zones') ? 'zones' : null);
        if (!$table) {
            return $this->success('floor_zones', []);
        }

        $query = DB::table($table)->select($this->availableColumns($table, [
            'id', 'name', 'description', 'status', 'created_at', 'updated_at',
        ]));
        $this->applySince($query, $table, $request);

        $rows = $query->orderBy($this->hasColumn($table, 'name') ? 'name' : 'id')
            ->get()
            ->map(fn ($row) => $this->row($row))
            ->values()
            ->all();

        return $this->success('floor_zones', $rows);
    }

    public function zones(Request $request): JsonResponse
    {
        $data = $this->floorZones($request)->getData(true);
        return $this->success('zones', $data['floor_zones'] ?? []);
    }

    public function tables(Request $request): JsonResponse
    {
        if (!$this->tableExists('tables')) {
            return $this->success('tables', []);
        }

        $zoneTable = $this->tableExists('floor_zones') ? 'floor_zones' : ($this->tableExists('zones') ? 'zones' : null);
        $zoneNames = $zoneTable ? DB::table($zoneTable)->pluck('name', 'id') : collect();

        $query = DB::table('tables')->select($this->availableColumns('tables', [
            'id', 'table_number', 'qr_token', 'seating_capacity', 'zone_id', 'floor_zone_id',
            'initial_status', 'notes', 'created_at', 'updated_at',
        ]));
        $this->applySince($query, 'tables', $request);

        $rows = $query->orderBy($this->hasColumn('tables', 'table_number') ? 'table_number' : 'id')
            ->get()
            ->map(function ($row) use ($zoneNames) {
                $data = $this->row($row);
                $zoneId = $data['floor_zone_id'] ?? $data['zone_id'] ?? null;
                $data['zone_server_id'] = $zoneId;
                $data['zone_name'] = $zoneId ? ($zoneNames[$zoneId] ?? null) : null;
                return $data;
            })
            ->values()
            ->all();

        return $this->success('tables', $rows);
    }

    public function waiters(Request $request): JsonResponse
    {
        if (!$this->tableExists('waiters')) {
            return $this->success('waiters', []);
        }

        $zoneTable = $this->tableExists('floor_zones') ? 'floor_zones' : ($this->tableExists('zones') ? 'zones' : null);
        $zoneNames = $zoneTable ? DB::table($zoneTable)->pluck('name', 'id') : collect();
        $shiftNames = $this->tableExists('shifts') ? DB::table('shifts')->pluck('name', 'id') : collect();

        $query = DB::table('waiters')->select($this->availableColumns('waiters', [
            'id', 'user_id', 'hr_employee_id', 'zone_id', 'shift_id', 'employee_id', 'name',
            'phone', 'email', 'image', 'join_date', 'notes', 'status', 'created_at', 'updated_at',
        ]));
        $this->applySince($query, 'waiters', $request);

        $rows = $query->orderBy($this->hasColumn('waiters', 'name') ? 'name' : 'id')
            ->get()
            ->map(function ($row) use ($zoneNames, $shiftNames) {
                $data = $this->row($row);
                $zoneId = $data['zone_id'] ?? null;
                $data['zone_name'] = $zoneId ? ($zoneNames[$zoneId] ?? null) : null;
                $data['shift_name'] = isset($data['shift_id']) ? ($shiftNames[$data['shift_id']] ?? null) : null;
                $data['image_url'] = $this->publicUrl($data['image'] ?? null, 'uploads/waiters');
                return $data;
            })
            ->values()
            ->all();

        return $this->success('waiters', $rows);
    }

    public function customers(Request $request): JsonResponse
    {
        if (!$this->tableExists('customers')) {
            return $this->success('customers', []);
        }

        $query = DB::table('customers')->select($this->availableColumns('customers', [
            'id', 'offline_uuid', 'name', 'phone', 'email', 'dob', 'address', 'points', 'total_orders',
            'synced_at', 'created_at', 'updated_at',
        ]));
        $this->applySince($query, 'customers', $request);

        $rows = $query->orderBy($this->hasColumn('customers', 'name') ? 'name' : 'id')
            ->get()
            ->map(fn ($row) => $this->row($row))
            ->values()
            ->all();

        return $this->success('customers', $rows);
    }

    public function foodCategories(Request $request): JsonResponse
    {
        if (!$this->tableExists('food_categories')) {
            return $this->success('food_categories', []);
        }

        $names = DB::table('food_categories')->pluck('name', 'id');
        $query = DB::table('food_categories')->select($this->availableColumns('food_categories', [
            'id', 'name', 'parent_category_id', 'image', 'slug', 'status', 'sort_order', 'created_at', 'updated_at',
        ]));
        $this->applySince($query, 'food_categories', $request);

        $rows = $query
            ->orderBy($this->hasColumn('food_categories', 'sort_order') ? 'sort_order' : 'id')
            ->get()
            ->map(function ($row) use ($names) {
                $data = $this->row($row);
                $data['parent_name'] = isset($data['parent_category_id'])
                    ? ($names[$data['parent_category_id']] ?? null)
                    : null;
                $data['image_url'] = $this->publicUrl($data['image'] ?? null, 'uploads/categories');
                return $data;
            })
            ->values()
            ->all();

        return $this->success('food_categories', $rows);
    }

    public function foodSubCategories(Request $request): JsonResponse
    {
        if (!$this->tableExists('food_categories')) {
            return $this->success('food_subcategories', []);
        }

        $query = DB::table('food_categories')
            ->whereNotNull('parent_category_id')
            ->select($this->availableColumns('food_categories', [
                'id', 'name', 'parent_category_id', 'image', 'slug', 'status', 'sort_order', 'created_at', 'updated_at',
            ]));

        if ($request->filled('category_id')) {
            $query->where('parent_category_id', $request->category_id);
        }

        $this->applySince($query, 'food_categories', $request);

        $rows = $query->orderBy($this->hasColumn('food_categories', 'sort_order') ? 'sort_order' : 'id')
            ->get()
            ->map(function ($row) {
                $data = $this->row($row);
                $data['image_url'] = $this->publicUrl($data['image'] ?? null, 'uploads/categories');
                return $data;
            })
            ->values()
            ->all();

        return $this->success('food_subcategories', $rows);
    }

    public function foodItems(Request $request): JsonResponse
    {
        if (!$this->tableExists('food_items')) {
            return $this->success('food_items', []);
        }

        $categoryNames = $this->tableExists('food_categories') ? DB::table('food_categories')->pluck('name', 'id') : collect();
        $cuisineNames = $this->tableExists('cuisine_types') ? DB::table('cuisine_types')->pluck('name', 'id') : collect();
        $courseNames = $this->tableExists('course_types') ? DB::table('course_types')->pluck('name', 'id') : collect();

        $addons = $this->tableExists('food_addons')
            ? DB::table('food_addons')
                ->select($this->availableColumns('food_addons', ['id', 'food_item_id', 'name', 'price', 'created_at', 'updated_at']))
                ->orderBy('id')->get()->map(fn ($row) => $this->row($row))->groupBy('food_item_id')
            : collect();

        $gallery = $this->tableExists('food_images')
            ? DB::table('food_images')
                ->select($this->availableColumns('food_images', ['id', 'food_item_id', 'image', 'created_at', 'updated_at']))
                ->orderBy('id')->get()->map(function ($row) {
                    $data = $this->row($row);
                    $data['image_url'] = $this->publicUrl($data['image'] ?? null, 'uploads/foods');
                    return $data;
                })->groupBy('food_item_id')
            : collect();

        $query = DB::table('food_items')->select($this->availableColumns('food_items', [
            'id', 'name', 'bengali_name', 'slug', 'short_description', 'description',
            'food_category_id', 'sub_category_id', 'cuisine_type_id', 'course_type_id',
            'spice_level', 'serving_size', 'base_price', 'discount_price', 'tax_rate',
            'preparation_time', 'calories', 'allergens', 'allergen_notes', 'main_image',
            'is_available', 'is_featured', 'is_chefs_special', 'is_dine_in', 'is_takeaway',
            'is_draft', 'inventory_tracking', 'point', 'active_days', 'start_time', 'end_time',
            'created_at', 'updated_at',
        ]));
        $this->applySince($query, 'food_items', $request);

        $rows = $query->orderBy($this->hasColumn('food_items', 'name') ? 'name' : 'id')
            ->get()
            ->map(function ($row) use ($categoryNames, $cuisineNames, $courseNames, $addons, $gallery) {
                $data = $this->row($row);
                $foodId = $data['server_id'] ?? null;
                $data['category_name'] = isset($data['food_category_id']) ? ($categoryNames[$data['food_category_id']] ?? null) : null;
                $data['sub_category_name'] = isset($data['sub_category_id']) ? ($categoryNames[$data['sub_category_id']] ?? null) : null;
                $data['cuisine_type_name'] = isset($data['cuisine_type_id']) ? ($cuisineNames[$data['cuisine_type_id']] ?? null) : null;
                $data['course_type_name'] = isset($data['course_type_id']) ? ($courseNames[$data['course_type_id']] ?? null) : null;
                $data['main_image_url'] = $this->publicUrl($data['main_image'] ?? null, 'uploads/foods');
                $data['addons'] = isset($addons[$foodId]) ? $addons[$foodId]->values()->all() : [];
                $data['gallery_images'] = isset($gallery[$foodId]) ? $gallery[$foodId]->values()->all() : [];
                return $data;
            })
            ->values()
            ->all();

        return $this->success('food_items', $rows);
    }

    public function foodAddons(Request $request): JsonResponse
    {
        if (!$this->tableExists('food_addons')) {
            return $this->success('food_addons', []);
        }

        $query = DB::table('food_addons')->select($this->availableColumns('food_addons', [
            'id', 'food_item_id', 'name', 'price', 'created_at', 'updated_at',
        ]));
        $this->applySince($query, 'food_addons', $request);

        $rows = $query->orderBy('id')
            ->get()
            ->map(function ($row) {
                $data = $this->row($row);
                $data['food_item_server_id'] = $data['food_item_id'] ?? null;
                return $data;
            })
            ->values()
            ->all();

        return $this->success('food_addons', $rows);
    }

    public function occasions(Request $request): JsonResponse
    {
        if (!$this->tableExists('occasions')) {
            return $this->success('occasions', []);
        }

        $query = DB::table('occasions')->select($this->availableColumns('occasions', [
            'id', 'name', 'status', 'created_at', 'updated_at',
        ]));
        $this->applySince($query, 'occasions', $request);

        $rows = $query->orderBy($this->hasColumn('occasions', 'name') ? 'name' : 'id')
            ->get()->map(fn ($row) => $this->row($row))->values()->all();

        return $this->success('occasions', $rows);
    }

    public function deliveryPartners(Request $request): JsonResponse
    {
        if (!$this->tableExists('delivery_partners')) {
            return $this->success('delivery_partners', []);
        }

        $query = DB::table('delivery_partners')->select($this->availableColumns('delivery_partners', [
            'id', 'name', 'status', 'created_at', 'updated_at',
        ]));
        $this->applySince($query, 'delivery_partners', $request);

        $rows = $query->orderBy($this->hasColumn('delivery_partners', 'name') ? 'name' : 'id')
            ->get()
            ->map(fn ($row) => $this->row($row))
            ->values()
            ->all();

        return $this->success('delivery_partners', $rows);
    }

    public function masterData(Request $request): JsonResponse
    {
        $floorZones = $this->floorZones($request)->getData(true)['floor_zones'] ?? [];

        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            'requested_last_synced_at' => $this->since($request),
            'users' => $this->users($request)->getData(true)['users'] ?? [],
            'floor_zones' => $floorZones,
            'zones' => $floorZones,
            'tables' => $this->tables($request)->getData(true)['tables'] ?? [],
            'waiters' => $this->waiters($request)->getData(true)['waiters'] ?? [],
            'customers' => $this->customers($request)->getData(true)['customers'] ?? [],
            'food_categories' => $this->foodCategories($request)->getData(true)['food_categories'] ?? [],
            'food_subcategories' => $this->foodSubCategories($request)->getData(true)['food_subcategories'] ?? [],
            'food_items' => $this->foodItems($request)->getData(true)['food_items'] ?? [],
            'food_addons' => $this->foodAddons($request)->getData(true)['food_addons'] ?? [],
            'delivery_partners' => $this->deliveryPartners($request)->getData(true)['delivery_partners'] ?? [],
            'occasions' => $this->occasions($request)->getData(true)['occasions'] ?? [],
        ]);
    }

    private function success(string $key, array $data): JsonResponse
    {
        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'sync_token' => now()->toIso8601String(),
            $key => $data,
            'meta' => ['count' => count($data)],
        ]);
    }

    private function since(Request $request): ?string
    {
        $value = trim((string) $request->query('last_synced_at', ''));
        return $value !== '' ? $value : null;
    }

    private function applySince(Builder $query, string $table, Request $request): void
    {
        $since = $this->since($request);
        if ($since && $this->hasColumn($table, 'updated_at')) {
            $query->where($table . '.updated_at', '>', $since);
        }
    }

    private function tableExists(string $table): bool
    {
        try {
            return Schema::hasTable($table);
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function columns(string $table): array
    {
        if (!isset($this->columnsCache[$table])) {
            $this->columnsCache[$table] = $this->tableExists($table) ? Schema::getColumnListing($table) : [];
        }
        return $this->columnsCache[$table];
    }

    private function availableColumns(string $table, array $wanted): array
    {
        $columns = array_values(array_intersect($wanted, $this->columns($table)));
        return $columns ?: ['id'];
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    private function row(object|array $row): array
    {
        $data = (array) $row;
        if (array_key_exists('id', $data)) {
            $data['server_id'] = $data['id'];
        }
        return $data;
    }

    private function publicUrl(?string $path, ?string $folder = null): ?string
    {
        $path = trim((string) $path);
        if ($path === '') {
            return null;
        }
        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }
        $path = preg_replace('#^/?public/#', '', ltrim($path, '/'));
        if ($folder && !Str::contains($path, '/')) {
            $path = trim($folder, '/') . '/' . $path;
        }
        return asset('public/' . $path);
    }
}
