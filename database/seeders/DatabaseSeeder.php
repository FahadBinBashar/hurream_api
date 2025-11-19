<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = array_map(fn(string $role): array => [
            'name' => $role,
            'permissions' => json_encode(['*']),
        ], ['admin', 'hr', 'accounts', 'officer', 'investor', 'customer']);

        $this->seedOnce('roles', $roles, 'name');

        if (!DB::table('users')->where('email', 'admin@hphrms.test')->exists()) {
            DB::table('users')->insert([
                'name' => 'System Admin',
                'email' => 'admin@hphrms.test',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'phone' => '01700000000',
            ]);
        }

        $this->seedOnce('projects', [
            [
                'project_code' => 'BAY-VIEW',
                'project_name' => 'Bay View Resort',
                'location' => 'Cox’s Bazar',
                'description' => 'Luxury seaside investment property.',
                'status' => 'active',
                'certificate_prefix' => 'BAY',
            ],
            [
                'project_code' => 'HILL-RETREAT',
                'project_name' => 'Hilltop Retreat',
                'location' => 'Bandarban',
                'description' => 'Eco-friendly hillside villas.',
                'status' => 'active',
                'certificate_prefix' => 'HILL',
            ],
        ], 'project_code');

        $bayViewId = DB::table('projects')->where('project_code', 'BAY-VIEW')->value('id');
        $hillRetreatId = DB::table('projects')->where('project_code', 'HILL-RETREAT')->value('id');

        $this->seedOnce('customers', [
            [
                'name' => 'Rahim Uddin',
                'NID' => '9876543210000',
                'address' => 'Dhaka',
                'phone' => '01711111111',
                'email' => 'rahim@example.com',
                'status' => 'verified',
                'reference' => 'MD Approval',
                'verified_at' => '2025-01-05 10:00:00',
                'membership_type' => 'gold',
                'is_investor' => 1,
                'investor_no' => 'INV-2025-0001',
                'bank_info' => 'DBBL-12345',
                'nominee' => 'Rafiq',
                'otp_verified_at' => '2025-01-04 09:00:00',
                'admin_approved_at' => '2025-01-05 12:00:00',
            ],
            [
                'name' => 'Karim Ali',
                'NID' => '1234567890123',
                'address' => 'Chattogram',
                'phone' => '01822222222',
                'email' => 'karim@example.com',
                'status' => 'verified',
                'reference' => 'Referral Program',
                'verified_at' => '2025-01-10 10:00:00',
                'membership_type' => 'platinum',
                'is_investor' => 1,
                'investor_no' => 'INV-2025-0002',
                'bank_info' => 'EBL-54321',
                'nominee' => 'Shila',
            ],
        ], 'NID');

        $shareBatches = [];
        if ($bayViewId) {
            $shareBatches[] = [
                'project_id' => $bayViewId,
                'batch_name' => 'Bay View Launch',
                'share_price' => 25000,
                'total_shares' => 500,
                'available_shares' => 500,
                'certificate_start_no' => 100000,
                'certificate_end_no' => 100499,
                'status' => 'active',
            ];
        }
        if ($hillRetreatId) {
            $shareBatches[] = [
                'project_id' => $hillRetreatId,
                'batch_name' => 'Hilltop Founder Batch',
                'share_price' => 30000,
                'total_shares' => 300,
                'available_shares' => 300,
                'certificate_start_no' => 200000,
                'certificate_end_no' => 200299,
                'status' => 'active',
            ];
        }
        $this->seedOnce('share_batches', $shareBatches, ['project_id', 'batch_name']);

        $this->seedOnce('share_packages', [
            [
                'project_id' => $bayViewId,
                'package_name' => 'VIP Gold Package',
                'package_code' => 'VIP-GOLD',
                'package_price' => 720000,
                'down_payment' => 72000,
                'total_shares_included' => 28,
                'bonus_shares' => 3,
                'installment_months' => 24,
                'benefits' => json_encode([
                    'free_nights' => 6,
                    'discount_percent' => 20,
                    'voucher_value' => 40000,
                    'gifts' => 'Watch, Perfume',
                ]),
                'status' => 'active',
            ],
            [
                'project_id' => $hillRetreatId,
                'package_name' => 'Platinum Infinity',
                'package_code' => 'PLATINUM-INF',
                'package_price' => 1250000,
                'down_payment' => 125000,
                'total_shares_included' => 50,
                'bonus_shares' => 10,
                'installment_months' => 30,
                'benefits' => json_encode([
                    'free_nights' => 8,
                    'discount_percent' => 25,
                    'voucher_value' => 60000,
                    'gifts' => 'Smart Watch, Travel Bag',
                ]),
                'status' => 'active',
            ],
        ], 'package_code');

        $goldPackageId = DB::table('share_packages')->where('package_code', 'VIP-GOLD')->value('id');
        $platinumPackageId = DB::table('share_packages')->where('package_code', 'PLATINUM-INF')->value('id');

        if ($goldPackageId) {
            $this->seedOnce('package_benefits', [
                ['package_id' => $goldPackageId, 'benefit_type' => 'free_night', 'benefit_value' => '6 nights', 'notes' => null],
                ['package_id' => $goldPackageId, 'benefit_type' => 'lifetime_discount', 'benefit_value' => '20%', 'notes' => null],
                ['package_id' => $goldPackageId, 'benefit_type' => 'tour_voucher', 'benefit_value' => 'BDT 40,000', 'notes' => null],
            ], ['package_id', 'benefit_type', 'benefit_value']);
        }

        if ($platinumPackageId) {
            $this->seedOnce('package_benefits', [
                ['package_id' => $platinumPackageId, 'benefit_type' => 'free_night', 'benefit_value' => '8 nights', 'notes' => 'Peak season allowed'],
                ['package_id' => $platinumPackageId, 'benefit_type' => 'vip_services', 'benefit_value' => 'Concierge & Airport pickup', 'notes' => null],
            ], ['package_id', 'benefit_type', 'benefit_value']);
        }

        $this->seedOnce('customer_shares', [
            [
                'customer_id' => 1,
                'project_id' => $bayViewId,
                'share_type' => 'single',
                'package_id' => null,
                'unit_price' => 25000,
                'share_units' => 4,
                'bonus_units' => 0,
                'total_units' => 4,
                'amount' => 100000,
                'down_payment' => 100000,
                'monthly_installment' => null,
                'installment_months' => null,
                'payment_mode' => 'one_time',
                'stage' => 'MÖW-5',
                'reinvest_flag' => 1,
                'status' => 'active',
                'approval_status' => 'approved',
                'approver_gate_triggered' => 0,
                'benefits_snapshot' => null,
                'certificate_no' => 'BAY-000001',
                'invoice_no' => 'BAY-202501010101',
            ],
            [
                'customer_id' => 2,
                'project_id' => $bayViewId,
                'share_type' => 'package',
                'package_id' => $goldPackageId ?: null,
                'unit_price' => 25000,
                'share_units' => 28,
                'bonus_units' => 3,
                'total_units' => 31,
                'amount' => 720000,
                'down_payment' => 72000,
                'monthly_installment' => 27000,
                'installment_months' => 24,
                'payment_mode' => 'installment',
                'stage' => 'MÖW-6',
                'reinvest_flag' => 0,
                'status' => 'active',
                'approval_status' => 'pending',
                'approver_gate_triggered' => 1,
                'benefits_snapshot' => json_encode([
                    'free_nights' => 6,
                    'discount_percent' => 20,
                    'voucher_value' => 40000,
                ]),
                'certificate_no' => 'BAY-000100',
                'invoice_no' => 'BAY-202501010102',
            ],
        ], ['customer_id', 'stage']);

        if ($bayViewId) {
            $saleId = DB::table('share_sales')->insertGetId([
                'customer_id' => 1,
                'project_id' => $bayViewId,
                'package_id' => null,
                'sale_type' => 'single',
                'total_shares' => 4,
                'bonus_shares' => 0,
                'share_price' => 25000,
                'total_amount' => 100000,
                'down_payment' => 100000,
                'installment_months' => 0,
                'installment_amount' => 0,
                'payment_mode' => 'one_time',
                'invoice_no' => 'BAY-202501010101',
                'certificate_no' => 'BAY-000001',
                'certificate_start' => 1,
                'certificate_end' => 4,
                'status' => 'completed',
                'sale_source' => 'seeder',
                'metadata' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $primaryBatchId = DB::table('share_batches')->where('project_id', $bayViewId)->value('id');
            if ($primaryBatchId) {
                DB::table('share_sale_batches')->insert([
                    'share_sale_id' => $saleId,
                    'batch_id' => $primaryBatchId,
                    'shares_deducted' => 4,
                    'certificate_from' => 100000,
                    'certificate_to' => 100003,
                    'share_price' => 25000,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                DB::table('share_batches')->where('id', $primaryBatchId)->decrement('available_shares', 4);
            }
        }

        $this->seedOnce('approvals', [
            ['module' => 'share_issue', 'record_id' => 2, 'approver_id' => 1, 'status' => 'pending'],
            ['module' => 'share_issue', 'record_id' => 2, 'approver_id' => 2, 'status' => 'pending'],
            ['module' => 'share_issue', 'record_id' => 2, 'approver_id' => 3, 'status' => 'pending'],
        ], ['module', 'record_id', 'approver_id']);

        $this->seedOnce('transactions', [
            ['share_id' => 1, 'amount' => 50000, 'payment_type' => 'bank', 'date' => '2025-01-10'],
            ['share_id' => 2, 'amount' => 25000, 'payment_type' => 'cash', 'date' => '2025-02-12'],
        ], ['share_id', 'date']);

        // The other seeders are now standard Laravel seeders, so we call them with `call`.
        $this->call([
            GradeDesignationSeeder::class,
            EmployeeSeeder::class,
        ]);

        $this->seedOnce('leads', [
            ['officer_id' => 2, 'name' => 'Corporate Client A', 'contact' => 'clientA@example.com', 'status' => 'prospect'],
            ['officer_id' => 2, 'name' => 'Corporate Client B', 'contact' => 'clientB@example.com', 'status' => 'new'],
        ], 'name');

        $this->seedOnce('bookings', [
            [
                'customer_id' => 1,
                'room_type' => 'Deluxe',
                'guest_count' => 2,
                'check_in' => '2025-02-01',
                'check_out' => '2025-02-05',
                'price_plan' => 'Winter Promo',
                'payment_method' => 'card',
                'amount' => 40000,
                'payment_status' => 'paid',
            ],
            [
                'customer_id' => 2,
                'room_type' => 'Suite',
                'guest_count' => 3,
                'check_in' => '2025-03-10',
                'check_out' => '2025-03-12',
                'price_plan' => 'Weekend Saver',
                'payment_method' => 'bkash',
                'amount' => 30000,
                'payment_status' => 'pending',
            ],
        ], ['customer_id', 'check_in']);

        $this->seedOnce('accounts', [
            ['type' => 'income', 'description' => 'Room Booking', 'amount' => 40000, 'date' => '2025-02-05'],
            ['type' => 'expense', 'description' => 'Staff Salary', 'amount' => 15000, 'date' => '2025-02-07'],
        ], 'description');
    }

    /**
     * Seed a table only if the unique key doesn't exist.
     */
    protected function seedOnce(string $table, array $rows, string|array|null $uniqueKey): void
    {
        foreach ($rows as $row) {
            if ($uniqueKey) {
                $keys = (array)$uniqueKey;
                $query = DB::table($table);

                foreach ($keys as $key) {
                    $query->where($key, $row[$key]);
                }

                if ($query->exists()) {
                    continue;
                }
            }

            DB::table($table)->insert($row);
        }
    }
}
