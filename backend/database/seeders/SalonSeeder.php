<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Main\Tenant;
use App\Services\TenantService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

/**
 * Seeds a demo salon "Glow Studio" with users and services/products.
 *
 * Credentials (password: viber1):
 *   admin@salon.com    — owner
 *   manager@salon.com  — manager
 *   cashier@salon.com  — cashier
 */
class SalonSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Seeding Glow Studio (salon)...');

        // ─── 1. Users ────────────────────────────────────────────────────
        $users = [
            ['name' => 'Admin',   'email' => 'admin@salon.com',   'role' => 'owner'],
            ['name' => 'Manager', 'email' => 'manager@salon.com', 'role' => 'manager'],
            ['name' => 'Cashier', 'email' => 'cashier@salon.com', 'role' => 'cashier'],
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
            ['slug' => 'glow-studio'],
            [
                'owner_id'      => $created['owner']->id,
                'business_name' => 'Glow Studio',
                'database_name' => 'tenant_salon',
                'business_type' => 'salon',
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
            ['name' => 'Hair Services',    'slug' => 'hair-services',    'sort_order' => 1],
            ['name' => 'Skin & Facials',   'slug' => 'skin-facials',     'sort_order' => 2],
            ['name' => 'Nail Services',    'slug' => 'nail-services',    'sort_order' => 3],
            ['name' => 'Massage & Body',   'slug' => 'massage-body',     'sort_order' => 4],
            ['name' => 'Makeup',           'slug' => 'makeup',           'sort_order' => 5],
            ['name' => 'Retail Products',  'slug' => 'retail-products',  'sort_order' => 6],
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

        // ─── 5. Services & Products ──────────────────────────────────────
        $products = [
            // Hair Services
            ['cat' => 'Hair Services', 'name' => 'Haircut – Women',              'price' => 699,  'cost' => 150, 'sku' => 'HS-001', 'tags' => ['bestseller'],          'desc' => 'Wash, cut and blow-dry'],
            ['cat' => 'Hair Services', 'name' => 'Haircut – Men',                'price' => 349,  'cost' => 80,  'sku' => 'HS-002', 'tags' => ['bestseller'],          'desc' => 'Scissor or clipper cut with wash'],
            ['cat' => 'Hair Services', 'name' => 'Balayage & Highlights',        'price' => 3499, 'cost' => 900, 'sku' => 'HS-003', 'tags' => ['new_arrival'],         'desc' => 'Hand-painted balayage with toner'],
            ['cat' => 'Hair Services', 'name' => 'Keratin Treatment',            'price' => 4999, 'cost' => 1400,'sku' => 'HS-004', 'tags' => ['bestseller'],          'desc' => 'Smoothing keratin for frizz control, lasts 3–4 months'],
            ['cat' => 'Hair Services', 'name' => 'Root Touch-Up',                'price' => 999,  'cost' => 280, 'sku' => 'HS-005', 'tags' => [],                      'desc' => 'Single colour root retouch'],
            ['cat' => 'Hair Services', 'name' => 'Blowout & Styling',            'price' => 499,  'cost' => 100, 'sku' => 'HS-006', 'tags' => [],                      'desc' => 'Professional blowout and style'],
            ['cat' => 'Hair Services', 'name' => 'Scalp Treatment',              'price' => 899,  'cost' => 220, 'sku' => 'HS-007', 'tags' => ['organic'],             'desc' => 'Deep nourishing scalp massage and treatment'],
            ['cat' => 'Hair Services', 'name' => 'Hair Spa – Deep Conditioning', 'price' => 1199, 'cost' => 320, 'sku' => 'HS-008', 'tags' => ['organic'],             'desc' => 'Intensive moisture treatment with steam'],

            // Skin & Facials
            ['cat' => 'Skin & Facials', 'name' => 'Classic Cleanup',             'price' => 799,  'cost' => 180, 'sku' => 'SF-001', 'tags' => ['bestseller'],         'desc' => 'Deep cleanse, steam, exfoliation and massage'],
            ['cat' => 'Skin & Facials', 'name' => 'Gold Facial',                 'price' => 2499, 'cost' => 700, 'sku' => 'SF-002', 'tags' => ['bestseller'],         'desc' => '24K gold leaf brightening facial'],
            ['cat' => 'Skin & Facials', 'name' => 'Hydra Facial',                'price' => 3499, 'cost' => 950, 'sku' => 'SF-003', 'tags' => ['new_arrival'],        'desc' => 'Multi-step hydra-dermabrasion facial'],
            ['cat' => 'Skin & Facials', 'name' => 'Acne Control Facial',         'price' => 1799, 'cost' => 480, 'sku' => 'SF-004', 'tags' => ['fragrance_free'],     'desc' => 'Salicylic and enzyme treatment for acne-prone skin'],
            ['cat' => 'Skin & Facials', 'name' => 'Anti-Ageing Facial',          'price' => 2999, 'cost' => 820, 'sku' => 'SF-005', 'tags' => [],                     'desc' => 'Collagen-boosting facial with lifting massage'],
            ['cat' => 'Skin & Facials', 'name' => 'Eyebrow Threading',           'price' => 99,   'cost' => 20,  'sku' => 'SF-006', 'tags' => ['bestseller'],         'desc' => 'Precise threading for perfect arches'],
            ['cat' => 'Skin & Facials', 'name' => 'Upper Lip Threading',         'price' => 49,   'cost' => 10,  'sku' => 'SF-007', 'tags' => [],                     'desc' => 'Upper lip threading'],
            ['cat' => 'Skin & Facials', 'name' => 'Full Face Threading',         'price' => 299,  'cost' => 60,  'sku' => 'SF-008', 'tags' => [],                     'desc' => 'Complete face threading including chin and forehead'],

            // Nail Services
            ['cat' => 'Nail Services', 'name' => 'Manicure – Classic',           'price' => 499,  'cost' => 120, 'sku' => 'NS-001', 'tags' => ['bestseller'],         'desc' => 'Soak, shape, cuticle care and polish'],
            ['cat' => 'Nail Services', 'name' => 'Pedicure – Classic',           'price' => 699,  'cost' => 180, 'sku' => 'NS-002', 'tags' => ['bestseller'],         'desc' => 'Foot soak, scrub, massage and polish'],
            ['cat' => 'Nail Services', 'name' => 'Gel Manicure',                 'price' => 999,  'cost' => 280, 'sku' => 'NS-003', 'tags' => ['new_arrival'],        'desc' => 'Long-lasting gel colour with UV lamp set'],
            ['cat' => 'Nail Services', 'name' => 'Nail Art – Per Nail',          'price' => 99,   'cost' => 25,  'sku' => 'NS-004', 'tags' => [],                     'desc' => 'Custom nail art design per nail'],
            ['cat' => 'Nail Services', 'name' => 'Acrylic Extensions – Full Set','price' => 2499, 'cost' => 700, 'sku' => 'NS-005', 'tags' => ['limited'],            'desc' => 'Full set acrylic nail extensions'],

            // Massage & Body
            ['cat' => 'Massage & Body', 'name' => 'Swedish Massage – 60 min',    'price' => 1999, 'cost' => 500, 'sku' => 'MB-001', 'tags' => ['bestseller'],         'desc' => 'Relaxing full-body Swedish massage'],
            ['cat' => 'Massage & Body', 'name' => 'Deep Tissue Massage – 60 min','price' => 2499, 'cost' => 650, 'sku' => 'MB-002', 'tags' => [],                     'desc' => 'Therapeutic deep tissue muscle work'],
            ['cat' => 'Massage & Body', 'name' => 'Head & Shoulder Massage',     'price' => 799,  'cost' => 180, 'sku' => 'MB-003', 'tags' => ['bestseller'],         'desc' => '30-min de-stress head, neck and shoulder'],
            ['cat' => 'Massage & Body', 'name' => 'Body Polishing',              'price' => 2999, 'cost' => 820, 'sku' => 'MB-004', 'tags' => ['new_arrival'],        'desc' => 'Full-body scrub and moisturising wrap'],
            ['cat' => 'Massage & Body', 'name' => 'Waxing – Full Legs',          'price' => 699,  'cost' => 160, 'sku' => 'MB-005', 'tags' => [],                     'desc' => 'Full leg waxing with aloe after-care'],
            ['cat' => 'Massage & Body', 'name' => 'Waxing – Full Arms',          'price' => 499,  'cost' => 120, 'sku' => 'MB-006', 'tags' => [],                     'desc' => 'Full arm waxing with soothing gel'],

            // Makeup
            ['cat' => 'Makeup', 'name' => 'Party Makeup',                        'price' => 2499, 'cost' => 650, 'sku' => 'MU-001', 'tags' => ['bestseller'],         'desc' => 'Full glam party look with lashes'],
            ['cat' => 'Makeup', 'name' => 'Bridal Makeup – Base',                'price' => 7999, 'cost' => 2200,'sku' => 'MU-002', 'tags' => ['limited'],            'desc' => 'HD bridal base with contouring'],
            ['cat' => 'Makeup', 'name' => 'Everyday Natural Makeup',             'price' => 999,  'cost' => 250, 'sku' => 'MU-003', 'tags' => ['new_arrival'],        'desc' => 'Soft, no-makeup makeup look'],
            ['cat' => 'Makeup', 'name' => 'Eye Makeup Only',                     'price' => 699,  'cost' => 160, 'sku' => 'MU-004', 'tags' => [],                     'desc' => 'Eyeshadow, liner and lashes'],

            // Retail Products
            ['cat' => 'Retail Products', 'name' => 'Argan Oil Hair Serum – 50ml','price' => 799,  'cost' => 300, 'sku' => 'RP-001', 'tags' => ['organic', 'bestseller'], 'desc' => 'Frizz-taming pure argan oil serum'],
            ['cat' => 'Retail Products', 'name' => 'Vitamin C Brightening Serum','price' => 1299, 'cost' => 480, 'sku' => 'RP-002', 'tags' => ['fragrance_free'],     'desc' => '15% Vitamin C brightening face serum'],
            ['cat' => 'Retail Products', 'name' => 'SPF 50 Sunscreen – 60ml',    'price' => 599,  'cost' => 220, 'sku' => 'RP-003', 'tags' => ['fragrance_free', 'bestseller'], 'desc' => 'Lightweight broad-spectrum SPF 50'],
            ['cat' => 'Retail Products', 'name' => 'Keratin Leave-In Cream',     'price' => 999,  'cost' => 360, 'sku' => 'RP-004', 'tags' => [],                     'desc' => 'Smoothing leave-in keratin conditioner'],
            ['cat' => 'Retail Products', 'name' => 'Rose Water Toner – 100ml',   'price' => 399,  'cost' => 140, 'sku' => 'RP-005', 'tags' => ['organic', 'fragrance_free'], 'desc' => 'Pure rose water pH-balancing toner'],
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
                    'track_inventory' => false,
                    'stock_quantity'  => 0,
                    'is_active'       => true,
                    'tags'            => json_encode($p['tags']),
                    'created_at'      => now(),
                    'updated_at'      => now(),
                ]
            );
            $count++;
        }
        $this->command->info("  Services/Products: {$count}");

        // ─── 6. Staff ────────────────────────────────────────────────────
        foreach ([
            ['role' => 'manager', 'code' => 'SL-MGR-001', 'salary' => 38000],
            ['role' => 'cashier', 'code' => 'SL-CSH-001', 'salary' => 20000],
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
        $this->command->info('Glow Studio seeding complete!');
        $this->command->info('Login: admin@salon.com / viber1');
    }
}
