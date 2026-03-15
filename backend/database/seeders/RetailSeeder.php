<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Main\Tenant;
use App\Services\TenantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

/**
 * Seeds a demo retail store "Urban Threads" with users and products.
 *
 * Credentials (password: viber1):
 *   admin@retail.com    — owner
 *   manager@retail.com  — manager
 *   cashier@retail.com  — cashier
 */
class RetailSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Urban Threads (retail)...');

        // ─── 1. Users ────────────────────────────────────────────────────
        $users = [
            ['name' => 'Admin',   'email' => 'admin@retail.com',   'role' => 'owner'],
            ['name' => 'Manager', 'email' => 'manager@retail.com', 'role' => 'manager'],
            ['name' => 'Cashier', 'email' => 'cashier@retail.com', 'role' => 'cashier'],
        ];

        $created = [];
        foreach ($users as $u) {
            $created[$u['role']] = User::updateOrCreate(
                ['email' => $u['email']],
                ['name' => $u['name'], 'password' => 'viber1', 'country_code' => '+91', 'is_active' => true]
            );
            $this->command->info("  User: {$u['email']} ({$u['role']})");
        }

        // ─── 2. Tenant ───────────────────────────────────────────────────
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'urban-threads'],
            [
                'owner_id'      => $created['owner']->id,
                'business_name' => 'Urban Threads',
                'database_name' => 'tenant_retail',
                'business_type' => 'retail',
                'country'       => 'IN',
                'currency'      => 'INR',
                'timezone'      => 'Asia/Kolkata',
                'plan'          => 'trial',
                'status'        => 'active',
                'trial_ends_at' => now()->addDays(30),
            ]
        );

        foreach (['owner', 'manager', 'cashier'] as $role) {
            $tenant->users()->syncWithoutDetaching([
                $created[$role]->id => ['role' => $role, 'is_active' => true],
            ]);
        }

        $this->command->info("  Tenant: {$tenant->business_name}");

        // ─── 3. Provision / connect DB ───────────────────────────────────
        $tenantService = app(TenantService::class);
        if (!$tenantService->databaseExists($tenant)) {
            $tenantService->provisionDatabase($tenant);
            $this->command->info('  DB created & migrated');
        } else {
            DB::purge('tenant');
            Config::set('database.connections.tenant.database', $tenant->database_name);
            DB::reconnect('tenant');
            $this->command->info('  DB already exists, connected');
        }

        // ─── 4. Categories ───────────────────────────────────────────────
        $categories = [
            ['name' => 'Men\'s Clothing',   'slug' => 'mens-clothing',   'sort_order' => 1],
            ['name' => 'Women\'s Clothing', 'slug' => 'womens-clothing', 'sort_order' => 2],
            ['name' => 'Accessories',        'slug' => 'accessories',     'sort_order' => 3],
            ['name' => 'Footwear',           'slug' => 'footwear',        'sort_order' => 4],
            ['name' => 'Bags & Wallets',     'slug' => 'bags-wallets',    'sort_order' => 5],
            ['name' => 'Activewear',         'slug' => 'activewear',      'sort_order' => 6],
        ];

        $catIds = [];
        foreach ($categories as $cat) {
            DB::connection('tenant')->table('categories')->updateOrInsert(
                ['slug' => $cat['slug']],
                array_merge($cat, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
            $catIds[$cat['name']] = DB::connection('tenant')->table('categories')->where('slug', $cat['slug'])->value('id');
        }
        $this->command->info('  Categories: ' . count($categories));

        // ─── 5. Products ─────────────────────────────────────────────────
        $products = [
            // Men's Clothing
            ['cat' => "Men's Clothing", 'name' => 'Classic White Oxford Shirt',    'price' => 1299, 'cost' => 450,  'sku' => 'MC-001', 'tags' => ['bestseller'],   'desc' => '100% cotton slim-fit Oxford shirt'],
            ['cat' => "Men's Clothing", 'name' => 'Slim Fit Chinos – Khaki',       'price' => 1799, 'cost' => 650,  'sku' => 'MC-002', 'tags' => ['new_arrival'],  'desc' => 'Stretch chinos with tapered fit'],
            ['cat' => "Men's Clothing", 'name' => 'Graphic Tee – Urban Print',     'price' => 699,  'cost' => 220,  'sku' => 'MC-003', 'tags' => [],               'desc' => 'Cotton graphic tee with urban art print'],
            ['cat' => "Men's Clothing", 'name' => 'Denim Jacket – Washed Blue',    'price' => 2999, 'cost' => 1100, 'sku' => 'MC-004', 'tags' => ['bestseller'],   'desc' => 'Classic washed denim jacket'],
            ['cat' => "Men's Clothing", 'name' => 'Linen Kurta – Off White',       'price' => 1499, 'cost' => 520,  'sku' => 'MC-005', 'tags' => ['organic'],      'desc' => 'Breathable linen kurta for festive or casual wear'],
            ['cat' => "Men's Clothing", 'name' => 'Cargo Shorts – Olive',          'price' => 1199, 'cost' => 420,  'sku' => 'MC-006', 'tags' => [],               'desc' => 'Multi-pocket cargo shorts'],
            ['cat' => "Men's Clothing", 'name' => 'Formal Blazer – Navy',          'price' => 4999, 'cost' => 1800, 'sku' => 'MC-007', 'tags' => ['limited'],      'desc' => 'Structured single-button blazer'],

            // Women's Clothing
            ["cat" => "Women's Clothing", 'name' => 'Floral Wrap Dress',           'price' => 1899, 'cost' => 650,  'sku' => 'WC-001', 'tags' => ['bestseller', 'new_arrival'], 'desc' => 'Midi wrap dress in floral print'],
            ["cat" => "Women's Clothing", 'name' => 'High-Waist Mom Jeans',        'price' => 2199, 'cost' => 780,  'sku' => 'WC-002', 'tags' => ['bestseller'],   'desc' => 'Relaxed fit high-waist jeans'],
            ["cat" => "Women's Clothing", 'name' => 'Linen Co-ord Set – Sage',     'price' => 2499, 'cost' => 880,  'sku' => 'WC-003', 'tags' => ['organic', 'new_arrival'], 'desc' => 'Two-piece linen set in sage green'],
            ["cat" => "Women's Clothing", 'name' => 'Oversized Blazer – Cream',    'price' => 3499, 'cost' => 1250, 'sku' => 'WC-004', 'tags' => ['limited'],      'desc' => 'Tailored oversized blazer'],
            ["cat" => "Women's Clothing", 'name' => 'Embroidered Kurta Set',       'price' => 2799, 'cost' => 980,  'sku' => 'WC-005', 'tags' => [],               'desc' => 'Ethnic embroidered kurta with palazzos'],
            ["cat" => "Women's Clothing", 'name' => 'Crop Tank Top – White',       'price' => 599,  'cost' => 180,  'sku' => 'WC-006', 'tags' => [],               'desc' => 'Ribbed cotton crop top'],

            // Accessories
            ['cat' => 'Accessories', 'name' => 'Leather Belt – Brown',             'price' => 799,  'cost' => 280,  'sku' => 'AC-001', 'tags' => [],               'desc' => 'Genuine leather belt with brass buckle'],
            ['cat' => 'Accessories', 'name' => 'Aviator Sunglasses',               'price' => 1299, 'cost' => 450,  'sku' => 'AC-002', 'tags' => ['bestseller'],   'desc' => 'UV400 metal frame aviators'],
            ['cat' => 'Accessories', 'name' => 'Silk Pocket Square',               'price' => 499,  'cost' => 160,  'sku' => 'AC-003', 'tags' => [],               'desc' => 'Pure silk pocket square, assorted prints'],
            ['cat' => 'Accessories', 'name' => 'Beaded Bracelet Set',              'price' => 699,  'cost' => 220,  'sku' => 'AC-004', 'tags' => ['new_arrival'],  'desc' => 'Set of 3 handcrafted beaded bracelets'],
            ['cat' => 'Accessories', 'name' => 'Straw Sun Hat',                    'price' => 899,  'cost' => 300,  'sku' => 'AC-005', 'tags' => ['limited'],      'desc' => 'Wide-brim straw hat for summer'],

            // Footwear
            ['cat' => 'Footwear', 'name' => 'White Leather Sneakers',              'price' => 3499, 'cost' => 1250, 'sku' => 'FW-001', 'tags' => ['bestseller'],   'desc' => 'Clean minimal leather sneakers'],
            ['cat' => 'Footwear', 'name' => 'Suede Chelsea Boots',                 'price' => 4999, 'cost' => 1800, 'sku' => 'FW-002', 'tags' => ['limited'],      'desc' => 'Slip-on suede chelsea boots'],
            ['cat' => 'Footwear', 'name' => 'Cork Footbed Sandals',                'price' => 1799, 'cost' => 620,  'sku' => 'FW-003', 'tags' => ['organic'],      'desc' => 'Sustainable cork-footbed sandals'],
            ['cat' => 'Footwear', 'name' => 'Running Shoes – Black',               'price' => 2999, 'cost' => 1050, 'sku' => 'FW-004', 'tags' => ['new_arrival'],  'desc' => 'Lightweight mesh running shoes'],

            // Bags & Wallets
            ['cat' => 'Bags & Wallets', 'name' => 'Canvas Tote Bag',               'price' => 899,  'cost' => 300,  'sku' => 'BW-001', 'tags' => ['bestseller', 'organic'], 'desc' => 'Heavy-duty natural canvas tote'],
            ['cat' => 'Bags & Wallets', 'name' => 'Leather Bifold Wallet',         'price' => 1299, 'cost' => 450,  'sku' => 'BW-002', 'tags' => [],               'desc' => 'Slim genuine leather bifold wallet'],
            ['cat' => 'Bags & Wallets', 'name' => 'Mini Crossbody Bag – Black',    'price' => 1999, 'cost' => 700,  'sku' => 'BW-003', 'tags' => ['new_arrival'],  'desc' => 'Compact PU leather crossbody'],
            ['cat' => 'Bags & Wallets', 'name' => 'Backpack – Olive Green',        'price' => 2499, 'cost' => 880,  'sku' => 'BW-004', 'tags' => ['bestseller'],   'desc' => '20L waterproof casual backpack'],

            // Activewear
            ['cat' => 'Activewear', 'name' => 'Yoga Leggings – Black',             'price' => 1599, 'cost' => 560,  'sku' => 'AW-001', 'tags' => ['bestseller'],   'desc' => 'High-waist 4-way stretch leggings'],
            ['cat' => 'Activewear', 'name' => 'Sports Bra – Dusty Rose',           'price' => 999,  'cost' => 340,  'sku' => 'AW-002', 'tags' => ['new_arrival'],  'desc' => 'Medium support racerback sports bra'],
            ['cat' => 'Activewear', 'name' => 'Gym Shorts – Grey Marl',            'price' => 899,  'cost' => 300,  'sku' => 'AW-003', 'tags' => [],               'desc' => 'Quick-dry gym shorts with liner'],
            ['cat' => 'Activewear', 'name' => 'Zip-Up Track Jacket',               'price' => 2299, 'cost' => 800,  'sku' => 'AW-004', 'tags' => ['new_arrival'],  'desc' => 'Lightweight zip-up running jacket'],
        ];

        $count = 0;
        foreach ($products as $p) {
            DB::connection('tenant')->table('products')->updateOrInsert(
                ['sku' => $p['sku']],
                [
                    'category_id'     => $catIds[$p['cat']],
                    'name'            => $p['name'],
                    'sku'             => $p['sku'],
                    'description'     => $p['desc'],
                    'price'           => $p['price'],
                    'cost_price'      => $p['cost'],
                    'cb_percent'      => 5.00,
                    'tax_type'        => 'inclusive',
                    'tax_rate'        => 18.00,
                    'track_inventory' => true,
                    'stock_quantity'  => rand(10, 100),
                    'is_active'       => true,
                    'tags'            => json_encode($p['tags']),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );
            $count++;
        }
        $this->command->info("  Products: {$count}");

        // ─── 6. Staff ────────────────────────────────────────────────────
        foreach ([
            ['role' => 'manager', 'code' => 'RT-MGR-001', 'salary' => 40000],
            ['role' => 'cashier', 'code' => 'RT-CSH-001', 'salary' => 22000],
        ] as $s) {
            DB::connection('tenant')->table('staff')->updateOrInsert(
                ['employee_code' => $s['code']],
                [
                    'user_id'        => $created[$s['role']]->id,
                    'role'           => $s['role'],
                    'monthly_salary' => $s['salary'],
                    'is_active'      => true,
                    'joined_at'      => now()->toDateString(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]
            );
        }

        $this->command->info('  Staff: 2');
        $this->command->info('');
        $this->command->info('Urban Threads seeding complete!');
        $this->command->info('Login: admin@retail.com / viber1');
    }
}
