<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Main\Tenant;
use App\Services\TenantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

/**
 * Seeds the "Kynd" restaurant with users, menu, tables, and staff.
 *
 * Usage:
 *   php artisan db:seed --class=KyndSeeder
 *
 * Credentials (password: viber1):
 *   bkm@flopos.com       — owner
 *   aman@flopos.com       — manager
 *   gurjant@flopos.com    — cashier
 *   jaswinder@flopos.com  — kitchen staff (cook)
 */
class KyndSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Kynd restaurant...');

        // ─── 1. Create Users ─────────────────────────────────────────────
        $users = [
            ['name' => 'BKM',       'email' => 'bkm@flopos.com',       'role' => 'owner'],
            ['name' => 'Aman',      'email' => 'aman@flopos.com',      'role' => 'manager'],
            ['name' => 'Gurjant',   'email' => 'gurjant@flopos.com',   'role' => 'cashier'],
            ['name' => 'Jaswinder', 'email' => 'jaswinder@flopos.com', 'role' => 'cook'],
        ];

        $createdUsers = [];
        foreach ($users as $u) {
            $createdUsers[$u['role']] = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name'         => $u['name'],
                    'password'     => 'viber1',   // Auto-hashed by model
                    'country_code' => '+91',
                    'is_active'    => true,
                ]
            );
            $this->command->info("  User: {$u['email']} ({$u['role']})");
        }

        $owner = $createdUsers['owner'];

        // ─── 2. Create Tenant ────────────────────────────────────────────
        $tenant = Tenant::updateOrCreate(
            ['slug' => 'kynd'],
            [
                'owner_id'      => $owner->id,
                'business_name' => 'Kynd',
                'database_name' => 'tenant_kynd',
                'business_type' => 'restaurant',
                'country'       => 'IN',
                'currency'      => 'INR',
                'timezone'      => 'Asia/Kolkata',
                'plan'          => 'trial',
                'status'        => 'active',
                'trial_ends_at' => now()->addDays(30),
            ]
        );

        // Attach all users to tenant
        $roleMap = ['owner' => 'owner', 'manager' => 'manager', 'cashier' => 'cashier', 'cook' => 'cook'];
        foreach ($roleMap as $key => $tenantRole) {
            $tenant->users()->syncWithoutDetaching([
                $createdUsers[$key]->id => ['role' => $tenantRole, 'is_active' => true],
            ]);
        }

        $this->command->info("  Tenant: {$tenant->business_name} (slug: {$tenant->slug})");

        // ─── 3. Provision tenant DB if needed ────────────────────────────
        $tenantService = app(TenantService::class);

        if (!$tenantService->databaseExists($tenant)) {
            $tenantService->provisionDatabase($tenant);
            $this->command->info('  Tenant DB created & migrated');
        } else {
            // Just switch to the existing DB
            DB::purge('tenant');
            Config::set('database.connections.tenant.database', $tenant->database_name);
            DB::reconnect('tenant');
            $this->command->info('  Tenant DB already exists, connected');
        }

        // ─── 4. Seed Categories ──────────────────────────────────────────
        $categories = [
            ['name' => 'Breakfast',         'slug' => 'breakfast',         'description' => 'Morning favourites',                     'sort_order' => 1],
            ['name' => 'Smoothie Bowls',    'slug' => 'smoothie-bowls',    'description' => 'Acai, tropical and berry bowls',         'sort_order' => 2],
            ['name' => 'Small Plates',      'slug' => 'small-plates',      'description' => 'Starters and shareables',                'sort_order' => 3],
            ['name' => 'Salad Bowls',       'slug' => 'salad-bowls',       'description' => 'Hearty salads and grain bowls',          'sort_order' => 4],
            ['name' => 'Mains',             'slug' => 'mains',             'description' => 'Pasta, curry, ramen and more',           'sort_order' => 5],
            ['name' => 'Burgers & Wraps',   'slug' => 'burgers-wraps',     'description' => 'Burgers, wraps and sandwiches',          'sort_order' => 6],
            ['name' => 'Sides',             'slug' => 'sides',             'description' => 'Sides and extras',                       'sort_order' => 7],
            ['name' => 'Desserts',          'slug' => 'desserts',          'description' => 'Sweet endings',                          'sort_order' => 8],
            ['name' => 'Brunch Cocktails',  'slug' => 'brunch-cocktails',  'description' => 'Classic brunch cocktails',               'sort_order' => 9],
        ];

        $catIds = [];
        foreach ($categories as $cat) {
            $row = DB::connection('tenant')->table('categories')->updateOrInsert(
                ['name' => $cat['name']],
                array_merge($cat, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()])
            );
            $catIds[$cat['name']] = DB::connection('tenant')
                ->table('categories')->where('name', $cat['name'])->value('id');
        }

        $this->command->info('  Categories: ' . count($categories));

        // ─── 5. Seed Products (KYND Community menu) ──────────────────────
        $products = [
            // Breakfast
            ['cat' => 'Breakfast', 'name' => 'Vanilla Berry Bircher Jar', 'price' => 550, 'description' => 'Overnight oats with vanilla and mixed berries'],
            ['cat' => 'Breakfast', 'name' => 'Paradise Pancakes',         'price' => 550, 'description' => 'Fluffy pancakes with tropical fruit toppings'],
            ['cat' => 'Breakfast', 'name' => 'Big Boss Breaky',           'price' => 550, 'description' => 'Full breakfast plate with all the trimmings'],
            ['cat' => 'Breakfast', 'name' => 'Avo Toast 2.0',             'price' => 490, 'description' => 'Smashed avocado on sourdough, upgraded'],
            ['cat' => 'Breakfast', 'name' => 'Beacon Benedict',           'price' => 460, 'description' => 'Plant-based benedict with hollandaise'],
            ['cat' => 'Breakfast', 'name' => 'Fruits Of Life',            'price' => 430, 'description' => 'Fresh seasonal fruit platter'],
            ['cat' => 'Breakfast', 'name' => 'Sunny Ride Burger',         'price' => 490, 'description' => 'Breakfast burger with sunny-side up'],
            ['cat' => 'Breakfast', 'name' => 'Power Porridge',            'price' => 430, 'description' => 'Warm oat porridge with superfoods'],
            ['cat' => 'Breakfast', 'name' => 'Brunch Bowl',               'price' => 550, 'description' => 'Hearty grain bowl with breakfast ingredients'],
            ['cat' => 'Breakfast', 'name' => 'Ghandi Toast',              'price' => 460, 'description' => 'Spiced toast with Indian-inspired toppings'],
            ['cat' => 'Breakfast', 'name' => 'Bakon & Cheese Toastie',    'price' => 460, 'description' => 'Grilled toastie with plant-based bacon and cheese'],
            ['cat' => 'Breakfast', 'name' => 'Mushroom Pesto Melt',       'price' => 460, 'description' => 'Mushroom and pesto grilled sandwich'],
            ['cat' => 'Breakfast', 'name' => 'Shaka-Shuka',               'price' => 460, 'description' => 'Baked eggs in spiced tomato sauce'],

            // Smoothie Bowls
            ['cat' => 'Smoothie Bowls', 'name' => 'Berry Tropical',  'price' => 520, 'description' => 'Mixed berry and tropical fruit smoothie bowl'],
            ['cat' => 'Smoothie Bowls', 'name' => 'Acai Bowl',       'price' => 550, 'description' => 'Classic acai bowl with granola and fresh fruit'],

            // Small Plates
            ['cat' => 'Small Plates', 'name' => 'Buffalo Cauliflower Wings', 'price' => 430, 'description' => 'Crispy cauliflower in buffalo sauce'],
            ['cat' => 'Small Plates', 'name' => 'Potato & Leek Soup',       'price' => 430, 'description' => 'Creamy potato and leek soup'],
            ['cat' => 'Small Plates', 'name' => 'High Rolling',             'price' => 430, 'description' => 'Fresh rice paper rolls'],
            ['cat' => 'Small Plates', 'name' => 'Salt & Pepper Calamari',   'price' => 430, 'description' => 'Crispy salt and pepper calamari'],
            ['cat' => 'Small Plates', 'name' => 'Crispy Chat Potatoes',     'price' => 290, 'description' => 'Spiced crispy potatoes with chutney'],

            // Salad Bowls
            ['cat' => 'Salad Bowls', 'name' => 'Whole Bowl',        'price' => 550, 'description' => 'Wholesome grain and veggie bowl'],
            ['cat' => 'Salad Bowls', 'name' => 'Bli Buddha',        'price' => 550, 'description' => 'Balinese-inspired Buddha bowl'],
            ['cat' => 'Salad Bowls', 'name' => 'Macrobiotic Plate', 'price' => 550, 'description' => 'Balanced macrobiotic plate with seasonal ingredients'],

            // Mains
            ['cat' => 'Mains', 'name' => 'Carbonara',              'price' => 520, 'description' => 'Creamy plant-based carbonara'],
            ['cat' => 'Mains', 'name' => 'Mexican Burrito',         'price' => 550, 'description' => 'Loaded burrito with Mexican spices'],
            ['cat' => 'Mains', 'name' => 'Creamy Tan Tan Ramen',    'price' => 550, 'description' => 'Japanese-style creamy sesame ramen'],
            ['cat' => 'Mains', 'name' => 'Butter Cheeken Curry',    'price' => 640, 'description' => 'Plant-based butter chicken curry'],
            ['cat' => 'Mains', 'name' => 'Vietnamese Pho',          'price' => 520, 'description' => 'Traditional Vietnamese pho with herbs'],
            ['cat' => 'Mains', 'name' => 'Spaghetti Bolognese',     'price' => 520, 'description' => 'Classic bolognese with plant-based mince'],
            ['cat' => 'Mains', 'name' => 'Seafood Risotto',         'price' => 520, 'description' => 'Creamy risotto with plant-based seafood'],
            ['cat' => 'Mains', 'name' => 'Eggplant Parmijana',      'price' => 550, 'description' => 'Baked eggplant with tomato sauce and cheese'],

            // Burgers & Wraps
            ['cat' => 'Burgers & Wraps', 'name' => 'Kynd Bigger Mac', 'price' => 550, 'description' => 'Signature double-stacked plant-based burger'],
            ['cat' => 'Burgers & Wraps', 'name' => 'Mindful',         'price' => 460, 'description' => 'Light and mindful plant-based wrap'],

            // Sides
            ['cat' => 'Sides', 'name' => 'Broccoli Bowl', 'price' => 290, 'description' => 'Steamed broccoli with seasoning'],
            ['cat' => 'Sides', 'name' => 'Loaded Fries',  'price' => 320, 'description' => 'Fries loaded with toppings and sauce'],

            // Desserts
            ['cat' => 'Desserts', 'name' => 'Choc Biscoff Mousse', 'price' => 370, 'description' => 'Chocolate mousse with Biscoff crumble'],
            ['cat' => 'Desserts', 'name' => 'Warm Apple Pie',      'price' => 370, 'description' => 'Classic warm apple pie with cinnamon'],

            // Brunch Cocktails
            ['cat' => 'Brunch Cocktails', 'name' => 'Bloody Mary', 'price' => 750, 'description' => 'Classic bloody mary with a spicy kick'],
            ['cat' => 'Brunch Cocktails', 'name' => 'Mimosa',      'price' => 750, 'description' => 'Sparkling wine with fresh orange juice'],
        ];

        $productCount = 0;
        foreach ($products as $p) {
            DB::connection('tenant')->table('products')->updateOrInsert(
                ['name' => $p['name']],
                [
                    'category_id'     => $catIds[$p['cat']],
                    'price'           => $p['price'],
                    'cost_price'      => round($p['price'] * 0.35, 2),
                    'description'     => $p['description'],
                    'tax_type'        => 'inclusive',
                    'tax_rate'        => 5.00,
                    'track_inventory' => false,
                    'stock_quantity'  => 0,
                    'is_active'       => true,
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );
            $productCount++;
        }

        $this->command->info("  Products: {$productCount}");

        // ─── 6. Seed Tables ──────────────────────────────────────────────
        $tables = [
            ['name' => 'T1',  'capacity' => 2, 'section' => 'Indoor',  'floor' => 'Ground'],
            ['name' => 'T2',  'capacity' => 2, 'section' => 'Indoor',  'floor' => 'Ground'],
            ['name' => 'T3',  'capacity' => 4, 'section' => 'Indoor',  'floor' => 'Ground'],
            ['name' => 'T4',  'capacity' => 4, 'section' => 'Indoor',  'floor' => 'Ground'],
            ['name' => 'T5',  'capacity' => 6, 'section' => 'Indoor',  'floor' => 'Ground'],
            ['name' => 'T6',  'capacity' => 6, 'section' => 'Indoor',  'floor' => 'Ground'],
            ['name' => 'T7',  'capacity' => 4, 'section' => 'Outdoor', 'floor' => 'Ground'],
            ['name' => 'T8',  'capacity' => 4, 'section' => 'Outdoor', 'floor' => 'Ground'],
            ['name' => 'T9',  'capacity' => 2, 'section' => 'Outdoor', 'floor' => 'Ground'],
            ['name' => 'T10', 'capacity' => 8, 'section' => 'Outdoor', 'floor' => 'Ground'],
        ];

        foreach ($tables as $t) {
            DB::connection('tenant')->table('tables')->updateOrInsert(
                ['name' => $t['name']],
                array_merge($t, [
                    'status'    => 'available',
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('  Tables: ' . count($tables));

        // ─── 7. Seed Staff ───────────────────────────────────────────────
        $staffEntries = [
            ['user' => 'manager',  'code' => 'KYND-MGR-001',  'role' => 'manager',  'salary' => 45000],
            ['user' => 'cashier',  'code' => 'KYND-CSH-001',  'role' => 'cashier',  'salary' => 25000],
            ['user' => 'cook',     'code' => 'KYND-KIT-001',  'role' => 'cook',     'salary' => 30000],
        ];

        foreach ($staffEntries as $s) {
            DB::connection('tenant')->table('staff')->updateOrInsert(
                ['employee_code' => $s['code']],
                [
                    'user_id'        => $createdUsers[$s['user']]->id,
                    'role'           => $s['role'],
                    'monthly_salary' => $s['salary'],
                    'is_active'      => true,
                    'joined_at'      => now()->toDateString(),
                    'created_at'     => now(),
                    'updated_at'     => now(),
                ]
            );
        }

        $this->command->info('  Staff: ' . count($staffEntries));

        $this->command->info('');
        $this->command->info('Kynd seeding complete!');
        $this->command->info('Login: bkm@flopos.com / viber1');
    }
}
