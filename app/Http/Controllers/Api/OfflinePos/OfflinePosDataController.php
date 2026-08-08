<?php

namespace App\Http\Controllers\Api\OfflinePos;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class OfflinePosDataController extends Controller
{
    private array $columnsCache = [];

    public function bootstrap(): JsonResponse
    {
        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            'bootstrap' => [
                'users' => $this->usersData(),
                'roles_permissions' => $this->rolesPermissionsData(),
                'restaurant_settings' => $this->restaurantSettingsData(),
                'pos_settings' => $this->posSettingsData(),
                'tax_settings' => $this->taxSettingsData(),
                'invoice_settings' => $this->invoiceSettingsData(),
                'zones' => $this->zonesData(),
                'tables' => $this->tablesData(),
                'waiters' => $this->waitersData(),
                'customers' => $this->customersData(),
                'food_categories' => $this->foodCategoriesData(),
                'food_items' => $this->foodItemsData(),
                'food_addons' => $this->foodAddonsData(),
                'payment_methods' => $this->paymentMethodsData(),
                'active_orders' => $this->activeOrdersData(),
                'media_manifest' => $this->mediaManifestData(),
            ],
        ]);
    }

    public function users(): JsonResponse
    {
        return $this->respond('users', $this->usersData());
    }

    public function rolesPermissions(): JsonResponse
    {
        return $this->respond('roles_permissions', $this->rolesPermissionsData());
    }

    public function restaurantSettings(): JsonResponse
    {
        return $this->respond('restaurant_settings', $this->restaurantSettingsData());
    }

    public function posSettings(): JsonResponse
    {
        return $this->respond('pos_settings', $this->posSettingsData());
    }

    public function taxSettings(): JsonResponse
    {
        return $this->respond('tax_settings', $this->taxSettingsData());
    }

    public function invoiceSettings(): JsonResponse
    {
        return $this->respond('invoice_settings', $this->invoiceSettingsData());
    }

    public function zones(): JsonResponse
    {
        return $this->respond('zones', $this->zonesData());
    }

    public function tables(): JsonResponse
    {
        return $this->respond('tables', $this->tablesData());
    }

    public function waiters(): JsonResponse
    {
        return $this->respond('waiters', $this->waitersData());
    }

    public function customers(): JsonResponse
    {
        return $this->respond('customers', $this->customersData());
    }

    public function foodCategories(): JsonResponse
    {
        return $this->respond('food_categories', $this->foodCategoriesData());
    }

    public function foodItems(): JsonResponse
    {
        return $this->respond('food_items', $this->foodItemsData());
    }

    public function foodAddons(): JsonResponse
    {
        return $this->respond('food_addons', $this->foodAddonsData());
    }

    public function paymentMethods(): JsonResponse
    {
        return $this->respond('payment_methods', $this->paymentMethodsData());
    }

    public function activeOrders(): JsonResponse
    {
        return $this->respond('active_orders', $this->activeOrdersData());
    }

    public function mediaManifest(): JsonResponse
    {
        return $this->respond('media_manifest', $this->mediaManifestData());
    }

    private function respond(string $key, mixed $payload): JsonResponse
    {
        return response()->json([
            'status' => true,
            'server_time' => now()->toDateTimeString(),
            $key => $payload,
            'meta' => [
                'count' => is_countable($payload) ? count($payload) : null,
            ],
        ]);
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
            $this->columnsCache[$table] = $this->tableExists($table)
                ? Schema::getColumnListing($table)
                : [];
        }

        return $this->columnsCache[$table];
    }

    private function hasColumn(string $table, string $column): bool
    {
        return in_array($column, $this->columns($table), true);
    }

    private function firstRow(string $table): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        $row = DB::table($table)->first();

        return $row ? $this->rowToArray($row) : [];
    }

    private function rowToArray(object|array $row): array
    {
        $data = (array) $row;
        if (array_key_exists('id', $data)) {
            $data['server_id'] = $data['id'];
        }

        return $data;
    }

    private function rows(string $table, ?callable $callback = null): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        $query = DB::table($table);

        if ($callback) {
            $callback($query);
        }

        return $query->get()->map(fn ($row) => $this->rowToArray($row))->values()->all();
    }

    private function activeStatusFilter($query, string $table): void
    {
        if ($this->hasColumn($table, 'status')) {
            $query->where(function ($q) use ($table) {
                $q->where($table . '.status', 1)
                  ->orWhere($table . '.status', '1')
                  ->orWhere($table . '.status', 'active')
                  ->orWhere($table . '.status', 'Active');
            });
        }
    }

    private function urlFor(?string $path, ?string $folder = null): ?string
    {
        if (!$path) {
            return null;
        }

        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://'])) {
            return $path;
        }

        $path = ltrim($path, '/');
        $path = preg_replace('#^public/#', '', $path);

        if ($folder && !Str::contains($path, '/')) {
            $path = trim($folder, '/') . '/' . $path;
        }

        return asset('public/' . $path);
    }

    private function usersData(): array
    {
        if (!$this->tableExists('users')) {
            return [];
        }

        $roleMap = $this->userRoleMap();
        $permissionMap = $this->userPermissionMap($roleMap);

        return DB::table('users')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($row) use ($roleMap, $permissionMap) {
                $data = (array) $row;
                $userId = $data['id'] ?? null;

                return [
                    'server_id' => $userId,
                    'user_id' => $data['user_id'] ?? null,
                    'name' => $data['name'] ?? null,
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'email' => $data['email'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'image' => $data['image'] ?? null,
                    'image_url' => $this->urlFor($data['image'] ?? null, 'uploads/users'),
                    'password_hash' => $data['password'] ?? null,
                    'roles' => $roleMap[$userId]['names'] ?? [],
                    'role_ids' => $roleMap[$userId]['ids'] ?? [],
                    'permissions' => $permissionMap[$userId] ?? [],
                    'status' => $data['status'] ?? 'active',
                    'last_login' => $data['last_login'] ?? null,
                    'created_at' => $data['created_at'] ?? null,
                    'updated_at' => $data['updated_at'] ?? null,
                    'deleted_at' => $data['deleted_at'] ?? null,
                ];
            })
            ->values()
            ->all();
    }

    private function rolesPermissionsData(): array
    {
        $roles = [];
        $permissions = [];

        if ($this->tableExists('permissions')) {
            $permissions = DB::table('permissions')
                ->orderBy('id', 'asc')
                ->get()
                ->map(fn ($row) => $this->rowToArray($row))
                ->values()
                ->all();
        }

        $permissionsById = collect($permissions)->keyBy('id');

        if ($this->tableExists('roles')) {
            $rolePermissionIds = [];
            if ($this->tableExists('role_has_permissions')) {
                $rolePermissionIds = DB::table('role_has_permissions')
                    ->get()
                    ->groupBy('role_id')
                    ->map(fn ($items) => $items->pluck('permission_id')->values()->all())
                    ->all();
            }

            $roles = DB::table('roles')
                ->orderBy('id', 'asc')
                ->get()
                ->map(function ($row) use ($rolePermissionIds, $permissionsById) {
                    $data = $this->rowToArray($row);
                    $roleId = $data['id'] ?? null;
                    $permissionIds = $rolePermissionIds[$roleId] ?? [];
                    $data['permission_ids'] = $permissionIds;
                    $data['permissions'] = collect($permissionIds)
                        ->map(fn ($permissionId) => $permissionsById[$permissionId]['name'] ?? null)
                        ->filter()
                        ->values()
                        ->all();

                    return $data;
                })
                ->values()
                ->all();
        }

        return [
            'roles' => $roles,
            'permissions' => $permissions,
        ];
    }

    private function userRoleMap(): array
    {
        if (!$this->tableExists('model_has_roles') || !$this->tableExists('roles')) {
            return [];
        }

        $rolesById = DB::table('roles')->get()->keyBy('id');
        $map = [];

        DB::table('model_has_roles')
            ->get()
            ->each(function ($row) use (&$map, $rolesById) {
                $userId = $row->model_id ?? null;
                $roleId = $row->role_id ?? null;
                if (!$userId || !$roleId) {
                    return;
                }

                $roleName = $rolesById[$roleId]->name ?? null;
                $map[$userId]['ids'][] = $roleId;
                if ($roleName) {
                    $map[$userId]['names'][] = $roleName;
                }
            });

        return $map;
    }

    private function userPermissionMap(array $roleMap): array
    {
        $map = [];

        if ($this->tableExists('permissions') && $this->tableExists('role_has_permissions')) {
            $permissionsById = DB::table('permissions')->pluck('name', 'id');
            $permissionIdsByRole = DB::table('role_has_permissions')
                ->get()
                ->groupBy('role_id')
                ->map(fn ($items) => $items->pluck('permission_id')->values()->all())
                ->all();

            foreach ($roleMap as $userId => $roles) {
                $names = [];
                foreach (($roles['ids'] ?? []) as $roleId) {
                    foreach (($permissionIdsByRole[$roleId] ?? []) as $permissionId) {
                        if (isset($permissionsById[$permissionId])) {
                            $names[] = $permissionsById[$permissionId];
                        }
                    }
                }
                $map[$userId] = array_values(array_unique($names));
            }
        }

        if ($this->tableExists('model_has_permissions') && $this->tableExists('permissions')) {
            $permissionsById = DB::table('permissions')->pluck('name', 'id');
            DB::table('model_has_permissions')
                ->get()
                ->each(function ($row) use (&$map, $permissionsById) {
                    $userId = $row->model_id ?? null;
                    $permissionId = $row->permission_id ?? null;
                    if ($userId && isset($permissionsById[$permissionId])) {
                        $map[$userId][] = $permissionsById[$permissionId];
                        $map[$userId] = array_values(array_unique($map[$userId]));
                    }
                });
        }

        return $map;
    }

    private function restaurantSettingsData(): array
    {
        $data = $this->firstRow('restaurant_settings');
        $data['logo_url'] = $this->urlFor($data['logo'] ?? null);
        $data['icon_url'] = $this->urlFor($data['icon_name'] ?? null);

        return $data;
    }

    private function posSettingsData(): array
    {
        return $this->firstRow('pos_settings');
    }

    private function taxSettingsData(): array
    {
        return $this->firstRow('tax_settings');
    }

    private function invoiceSettingsData(): array
    {
        return $this->firstRow('invoice_settings');
    }

    private function zonesData(): array
    {
        return $this->rows('zones', function ($query) {
            if ($this->hasColumn('zones', 'status')) {
                $this->activeStatusFilter($query, 'zones');
            }
            $query->orderBy($this->hasColumn('zones', 'name') ? 'name' : 'id', 'asc');
        });
    }

    private function tablesData(): array
    {
        if (!$this->tableExists('tables')) {
            return [];
        }

        $zones = $this->tableExists('zones')
            ? DB::table('zones')->pluck('name', 'id')
            : collect();

        $activeTableOrderCounts = [];
        if ($this->tableExists('orders') && $this->hasColumn('orders', 'table_id') && $this->hasColumn('orders', 'status')) {
            $activeTableOrderCounts = DB::table('orders')
                ->select('table_id', DB::raw('COUNT(*) as total'))
                ->whereNotNull('table_id')
                ->whereIn('status', ['Pending', 'Processing', 'Waiter_Hold', 'Cooking', 'Ready'])
                ->groupBy('table_id')
                ->pluck('total', 'table_id')
                ->all();
        }

        return DB::table('tables')
            ->orderBy($this->hasColumn('tables', 'table_number') ? 'table_number' : 'id', 'asc')
            ->get()
            ->map(function ($row) use ($zones, $activeTableOrderCounts) {
                $data = $this->rowToArray($row);
                $zoneId = $data['zone_id'] ?? null;
                $initialStatus = strtolower((string) ($data['initial_status'] ?? 'available'));
                $data['zone_name'] = $zoneId ? ($zones[$zoneId] ?? null) : null;
                $data['dynamic_status'] = !empty($activeTableOrderCounts[$data['id'] ?? null]) ? 'occupied' : $initialStatus;

                return $data;
            })
            ->values()
            ->all();
    }

    private function waitersData(): array
    {
        if (!$this->tableExists('waiters')) {
            return [];
        }

        $zones = $this->tableExists('zones') ? DB::table('zones')->pluck('name', 'id') : collect();
        $shifts = $this->tableExists('shifts') ? DB::table('shifts')->pluck('name', 'id') : collect();

        $query = DB::table('waiters');
        if ($this->hasColumn('waiters', 'status')) {
            $this->activeStatusFilter($query, 'waiters');
        }

        return $query
            ->orderBy($this->hasColumn('waiters', 'name') ? 'name' : 'id', 'asc')
            ->get()
            ->map(function ($row) use ($zones, $shifts) {
                $data = $this->rowToArray($row);
                $data['zone_name'] = isset($data['zone_id']) ? ($zones[$data['zone_id']] ?? null) : null;
                $data['shift_name'] = isset($data['shift_id']) ? ($shifts[$data['shift_id']] ?? null) : null;
                $data['image_url'] = $this->urlFor($data['image'] ?? null, 'uploads/waiters');

                return $data;
            })
            ->values()
            ->all();
    }

    private function customersData(): array
    {
        if (!$this->tableExists('customers')) {
            return [];
        }

        return DB::table('customers')
            ->orderBy($this->hasColumn('customers', 'name') ? 'name' : 'id', 'asc')
            ->get()
            ->map(fn ($row) => $this->rowToArray($row))
            ->values()
            ->all();
    }

    private function foodCategoriesData(): array
    {
        if (!$this->tableExists('food_categories')) {
            return [];
        }

        $categoryNames = DB::table('food_categories')->pluck('name', 'id');

        $query = DB::table('food_categories');
        if ($this->hasColumn('food_categories', 'status')) {
            $this->activeStatusFilter($query, 'food_categories');
        }

        return $query
            ->orderBy($this->hasColumn('food_categories', 'sort_order') ? 'sort_order' : 'id', 'asc')
            ->get()
            ->map(function ($row) use ($categoryNames) {
                $data = $this->rowToArray($row);
                $data['parent_name'] = isset($data['parent_category_id']) ? ($categoryNames[$data['parent_category_id']] ?? null) : null;
                $data['image_url'] = $this->urlFor($data['image'] ?? null, 'uploads/categories');

                return $data;
            })
            ->values()
            ->all();
    }

    private function foodItemsData(): array
    {
        if (!$this->tableExists('food_items')) {
            return [];
        }

        $categories = $this->tableExists('food_categories') ? DB::table('food_categories')->pluck('name', 'id') : collect();
        $cuisines = $this->tableExists('cuisine_types') ? DB::table('cuisine_types')->pluck('name', 'id') : collect();
        $courses = $this->tableExists('course_types') ? DB::table('course_types')->pluck('name', 'id') : collect();
        $addons = collect($this->foodAddonsData())->groupBy('food_item_id');
        $galleryImages = $this->galleryImagesByFood();

        // Offline POS must keep a full local copy of food_items.
        // Do not filter by is_available, is_draft, or status here.
        // The POS UI will filter available/active items locally when displaying the food grid.
        $query = DB::table('food_items');

        return $query
            ->orderBy($this->hasColumn('food_items', 'name') ? 'name' : 'id', 'asc')
            ->get()
            ->map(function ($row) use ($categories, $cuisines, $courses, $addons, $galleryImages) {
                $data = $this->rowToArray($row);
                $foodId = $data['id'] ?? null;
                $data['category_name'] = isset($data['food_category_id']) ? ($categories[$data['food_category_id']] ?? null) : null;
                $data['sub_category_name'] = isset($data['sub_category_id']) ? ($categories[$data['sub_category_id']] ?? null) : null;
                $data['cuisine_type_name'] = isset($data['cuisine_type_id']) ? ($cuisines[$data['cuisine_type_id']] ?? null) : null;
                $data['course_type_name'] = isset($data['course_type_id']) ? ($courses[$data['course_type_id']] ?? null) : null;
                $data['main_image_url'] = $this->urlFor($data['main_image'] ?? null, 'uploads/foods');
                $data['addons'] = ($addons[$foodId] ?? collect())->values()->all();
                $data['addons_count'] = count($data['addons']);
                $data['gallery_images'] = $galleryImages[$foodId] ?? [];

                return $data;
            })
            ->values()
            ->all();
    }

    private function foodAddonsData(): array
    {
        if (!$this->tableExists('food_addons')) {
            return [];
        }

        return DB::table('food_addons')
            ->orderBy($this->hasColumn('food_addons', 'name') ? 'name' : 'id', 'asc')
            ->get()
            ->map(fn ($row) => $this->rowToArray($row))
            ->values()
            ->all();
    }

    private function galleryImagesByFood(): array
    {
        if (!$this->tableExists('food_images')) {
            return [];
        }

        return DB::table('food_images')
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($row) {
                $data = $this->rowToArray($row);
                $data['image_url'] = $this->urlFor($data['image'] ?? null, 'uploads/foods');

                return $data;
            })
            ->groupBy('food_item_id')
            ->map(fn ($items) => $items->values()->all())
            ->all();
    }

    private function paymentMethodsData(): array
    {
        foreach (['payment_methods', 'payment_types'] as $table) {
            if ($this->tableExists($table)) {
                $query = DB::table($table);
                if ($this->hasColumn($table, 'status')) {
                    $this->activeStatusFilter($query, $table);
                }

                return $query->orderBy($this->hasColumn($table, 'sort_order') ? 'sort_order' : 'id', 'asc')
                    ->get()
                    ->map(fn ($row) => $this->rowToArray($row))
                    ->values()
                    ->all();
            }
        }

        return [
            ['server_id' => 1, 'name' => 'Cash', 'code' => 'cash', 'is_active' => true, 'requires_transaction_id' => false, 'sort_order' => 1],
            ['server_id' => 2, 'name' => 'Card', 'code' => 'card', 'is_active' => true, 'requires_transaction_id' => true, 'sort_order' => 2],
            ['server_id' => 3, 'name' => 'Mobile Banking', 'code' => 'mobile_banking', 'is_active' => true, 'requires_transaction_id' => true, 'sort_order' => 3],
            ['server_id' => 4, 'name' => 'Split', 'code' => 'split', 'is_active' => true, 'requires_transaction_id' => false, 'sort_order' => 4],
        ];
    }

    private function activeOrdersData(): array
    {
        if (!$this->tableExists('orders')) {
            return [];
        }

        $query = DB::table('orders');
        if ($this->hasColumn('orders', 'status')) {
            $query->whereIn('status', ['Pending', 'Processing', 'Waiter_Hold', 'Cooking', 'Ready']);
        }

        $orders = $query->orderBy('id', 'desc')->limit(500)->get();
        $orderIds = $orders->pluck('id')->values()->all();

        if (empty($orderIds)) {
            return [];
        }

        $detailsByOrder = $this->orderDetailsByOrder($orderIds);
        $kotsByOrder = $this->orderKotsByOrder($orderIds);
        $customers = $this->lookupRows('customers');
        $waiters = $this->lookupRows('waiters');
        $tables = $this->lookupRows('tables');

        return $orders
            ->map(function ($row) use ($detailsByOrder, $kotsByOrder, $customers, $waiters, $tables) {
                $data = $this->rowToArray($row);
                $orderId = $data['id'] ?? null;
                $data['customer'] = isset($data['customer_id']) ? ($customers[$data['customer_id']] ?? null) : null;
                $data['waiter'] = isset($data['waiter_id']) ? ($waiters[$data['waiter_id']] ?? null) : null;
                $data['table'] = isset($data['table_id']) ? ($tables[$data['table_id']] ?? null) : null;
                $data['order_details'] = $detailsByOrder[$orderId] ?? [];
                $data['kots'] = $kotsByOrder[$orderId] ?? [];

                return $data;
            })
            ->values()
            ->all();
    }

    private function orderDetailsByOrder(array $orderIds): array
    {
        if (!$this->tableExists('order_details')) {
            return [];
        }

        return DB::table('order_details')
            ->whereIn('order_id', $orderIds)
            ->orderBy('id', 'asc')
            ->get()
            ->map(function ($row) {
                $data = $this->rowToArray($row);
                if (isset($data['addons']) && is_string($data['addons'])) {
                    $decoded = json_decode($data['addons'], true);
                    $data['addons_decoded'] = json_last_error() === JSON_ERROR_NONE ? $decoded : [];
                }

                return $data;
            })
            ->groupBy('order_id')
            ->map(fn ($items) => $items->values()->all())
            ->all();
    }

    private function orderKotsByOrder(array $orderIds): array
    {
        $kotTable = null;
        foreach (['order_kots', 'kots'] as $candidate) {
            if ($this->tableExists($candidate)) {
                $kotTable = $candidate;
                break;
            }
        }

        if (!$kotTable) {
            return [];
        }

        return DB::table($kotTable)
            ->whereIn('order_id', $orderIds)
            ->orderBy('id', 'asc')
            ->get()
            ->map(fn ($row) => $this->rowToArray($row))
            ->groupBy('order_id')
            ->map(fn ($items) => $items->values()->all())
            ->all();
    }

    private function lookupRows(string $table): array
    {
        if (!$this->tableExists($table)) {
            return [];
        }

        return DB::table($table)
            ->get()
            ->map(fn ($row) => $this->rowToArray($row))
            ->keyBy('id')
            ->all();
    }

    private function mediaManifestData(): array
    {
        $items = [];

        $restaurant = $this->restaurantSettingsData();
        $this->addMedia($items, 'restaurant_logo', 'restaurant_settings', $restaurant['server_id'] ?? null, $restaurant['logo'] ?? null);
        $this->addMedia($items, 'restaurant_icon', 'restaurant_settings', $restaurant['server_id'] ?? null, $restaurant['icon_name'] ?? null);

        foreach ($this->foodCategoriesData() as $category) {
            $this->addMedia($items, 'food_category_image', 'food_categories', $category['server_id'] ?? null, $category['image'] ?? null, 'uploads/categories');
        }

        if ($this->tableExists('food_items')) {
            DB::table('food_items')->get()->each(function ($row) use (&$items) {
                $data = (array) $row;
                $this->addMedia($items, 'food_main_image', 'food_items', $data['id'] ?? null, $data['main_image'] ?? null, 'uploads/foods');
            });
        }

        if ($this->tableExists('food_images')) {
            DB::table('food_images')->get()->each(function ($row) use (&$items) {
                $data = (array) $row;
                $this->addMedia($items, 'food_gallery_image', 'food_images', $data['id'] ?? null, $data['image'] ?? null, 'uploads/foods', [
                    'food_item_id' => $data['food_item_id'] ?? null,
                ]);
            });
        }

        if ($this->tableExists('waiters')) {
            DB::table('waiters')->get()->each(function ($row) use (&$items) {
                $data = (array) $row;
                $this->addMedia($items, 'waiter_image', 'waiters', $data['id'] ?? null, $data['image'] ?? null, 'uploads/waiters');
            });
        }

        if ($this->tableExists('users')) {
            DB::table('users')->get()->each(function ($row) use (&$items) {
                $data = (array) $row;
                $this->addMedia($items, 'user_image', 'users', $data['id'] ?? null, $data['image'] ?? null, 'uploads/users');
            });
        }

        return array_values($items);
    }

    private function addMedia(array &$items, string $type, string $model, mixed $modelId, ?string $path, ?string $folder = null, array $extra = []): void
    {
        if (!$path) {
            return;
        }

        $relativePath = ltrim(preg_replace('#^public/#', '', trim($path)), '/');
        if ($folder && !Str::contains($relativePath, '/')) {
            $relativePath = trim($folder, '/') . '/' . $relativePath;
        }

        $items[] = array_merge([
            'type' => $type,
            'model' => $model,
            'model_id' => $modelId,
            'path' => $relativePath,
            'url' => $this->urlFor($path, $folder),
            'checksum' => md5($type . '|' . $model . '|' . $modelId . '|' . $relativePath),
            'updated_at' => null,
        ], $extra);
    }
}
