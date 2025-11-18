<?php

use App\Core\Database;

return new class {
    public function run(): void
    {
        $pdo = Database::connection();

        $roles = array_map(function (string $role): array {
            return [
                'name' => $role,
                'permissions' => json_encode(['*']),
            ];
        }, ['admin', 'hr', 'accounts', 'officer', 'investor', 'customer']);

        $this->seedOnce($pdo, 'roles', $roles, 'name');

        $adminExists = $pdo->query("SELECT COUNT(*) FROM users WHERE email = 'admin@hphrms.test'")->fetchColumn();
        if (!$adminExists) {
            $stmt = $pdo->prepare('INSERT INTO users (name, email, password, role, phone) VALUES (:name, :email, :password, :role, :phone)');
            $stmt->execute([
                'name' => 'System Admin',
                'email' => 'admin@hphrms.test',
                'password' => password_hash('password', PASSWORD_BCRYPT),
                'role' => 'admin',
                'phone' => '01700000000',
            ]);
        }

        $this->seedOnce($pdo, 'customers', [
            [
                'name' => 'Rahim Uddin',
                'NID' => '9876543210000',
                'address' => 'Dhaka',
                'phone' => '01711111111',
                'email' => 'rahim@example.com',
                'status' => 'verified',
                'reference' => 'MD Approval',
                'verified_at' => '2025-01-05T10:00:00+06:00',
                'membership_type' => 'gold',
                'is_investor' => 1,
                'investor_no' => 'INV-2025-0001',
                'bank_info' => 'DBBL-12345',
                'nominee' => 'Rafiq',
                'otp_verified_at' => '2025-01-04T09:00:00+06:00',
                'admin_approved_at' => '2025-01-05T12:00:00+06:00',
            ],
            [
                'name' => 'Karim Ali',
                'NID' => '1234567890123',
                'address' => 'Chattogram',
                'phone' => '01822222222',
                'email' => 'karim@example.com',
                'status' => 'verified',
                'reference' => 'Referral Program',
                'verified_at' => '2025-01-10T10:00:00+06:00',
                'membership_type' => 'platinum',
                'is_investor' => 1,
                'investor_no' => 'INV-2025-0002',
                'bank_info' => 'EBL-54321',
                'nominee' => 'Shila',
            ],
        ], 'NID');

        $this->seedOnce($pdo, 'share_packages', [
            [
                'package_name' => 'VIP Gold Package',
                'package_code' => 'VIP-GOLD',
                'package_price' => 720000,
                'down_payment' => 72000,
                'duration_months' => 24,
                'monthly_installment' => 27000,
                'auto_share_units' => 28,
                'bonus_share_percent' => 10,
                'bonus_share_units' => 3,
                'free_nights' => 6,
                'lifetime_discount' => 20,
                'tour_voucher_value' => 40000,
                'gift_items' => 'Watch, Perfume',
                'status' => 'active',
            ],
            [
                'package_name' => 'Platinum Infinity',
                'package_code' => 'PLATINUM-INF',
                'package_price' => 1250000,
                'down_payment' => 125000,
                'duration_months' => 30,
                'monthly_installment' => 37500,
                'auto_share_units' => 50,
                'bonus_share_percent' => 20,
                'bonus_share_units' => 10,
                'free_nights' => 8,
                'lifetime_discount' => 25,
                'tour_voucher_value' => 60000,
                'gift_items' => 'Smart Watch, Travel Bag',
                'status' => 'active',
            ],
        ], 'package_code');

        $goldPackageId = (int)$pdo->query("SELECT id FROM share_packages WHERE package_code = 'VIP-GOLD' LIMIT 1")->fetchColumn();
        $platinumPackageId = (int)$pdo->query("SELECT id FROM share_packages WHERE package_code = 'PLATINUM-INF' LIMIT 1")->fetchColumn();

        if ($goldPackageId) {
            $this->seedOnce($pdo, 'package_benefits', [
                ['package_id' => $goldPackageId, 'benefit_type' => 'free_night', 'benefit_value' => '6 nights', 'notes' => null],
                ['package_id' => $goldPackageId, 'benefit_type' => 'lifetime_discount', 'benefit_value' => '20%', 'notes' => null],
                ['package_id' => $goldPackageId, 'benefit_type' => 'tour_voucher', 'benefit_value' => 'BDT 40,000', 'notes' => null],
            ], ['package_id', 'benefit_type', 'benefit_value']);
        }

        if ($platinumPackageId) {
            $this->seedOnce($pdo, 'package_benefits', [
                ['package_id' => $platinumPackageId, 'benefit_type' => 'free_night', 'benefit_value' => '8 nights', 'notes' => 'Peak season allowed'],
                ['package_id' => $platinumPackageId, 'benefit_type' => 'vip_services', 'benefit_value' => 'Concierge & Airport pickup', 'notes' => null],
            ], ['package_id', 'benefit_type', 'benefit_value']);
        }

        $goldSnapshot = json_encode([
            'package' => [
                'id' => $goldPackageId,
                'package_name' => 'VIP Gold Package',
                'package_code' => 'VIP-GOLD',
                'package_price' => 720000,
                'duration_months' => 24,
                'monthly_installment' => 27000,
                'bonus_share_percent' => 10,
                'bonus_share_units' => 3,
                'free_nights' => 6,
                'lifetime_discount' => 20,
                'tour_voucher_value' => 40000,
                'gift_items' => 'Watch, Perfume',
            ],
            'benefits' => [
                ['benefit_type' => 'free_night', 'benefit_value' => '6 nights', 'notes' => null],
                ['benefit_type' => 'lifetime_discount', 'benefit_value' => '20%', 'notes' => null],
                ['benefit_type' => 'tour_voucher', 'benefit_value' => 'BDT 40,000', 'notes' => null],
            ],
        ], JSON_UNESCAPED_UNICODE);

        $this->seedOnce($pdo, 'customer_shares', [
            [
                'customer_id' => 1,
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
            ],
            [
                'customer_id' => 2,
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
                'benefits_snapshot' => $goldSnapshot,
            ],
        ], ['customer_id', 'stage']);

        $this->seedOnce($pdo, 'approvals', [
            ['module' => 'share_issue', 'record_id' => 2, 'approver_id' => 1, 'status' => 'pending'],
            ['module' => 'share_issue', 'record_id' => 2, 'approver_id' => 2, 'status' => 'pending'],
            ['module' => 'share_issue', 'record_id' => 2, 'approver_id' => 3, 'status' => 'pending'],
        ], ['module', 'record_id', 'approver_id']);

        $this->seedOnce($pdo, 'transactions', [
            ['share_id' => 1, 'amount' => 50000, 'payment_type' => 'bank', 'date' => '2025-01-10'],
            ['share_id' => 2, 'amount' => 25000, 'payment_type' => 'cash', 'date' => '2025-02-12'],
        ], ['share_id', 'date']);

        $gradeSeeder = require __DIR__ . '/GradeDesignationSeeder.php';
        $gradeSeeder->run($pdo, function (\PDO $pdo, string $table, array $rows, string|array|null $uniqueKey = null): void {
            $this->seedOnce($pdo, $table, $rows, $uniqueKey);
        });

        $employeeSeeder = require __DIR__ . '/EmployeeSeeder.php';
        $employeeSeeder->run($pdo, function (\PDO $pdo, string $table, array $rows, string|array|null $uniqueKey = null): void {
            $this->seedOnce($pdo, $table, $rows, $uniqueKey);
        });

        $this->seedOnce($pdo, 'leads', [
            ['officer_id' => 2, 'name' => 'Corporate Client A', 'contact' => 'clientA@example.com', 'status' => 'prospect'],
            ['officer_id' => 2, 'name' => 'Corporate Client B', 'contact' => 'clientB@example.com', 'status' => 'new'],
        ], 'name');

        $this->seedOnce($pdo, 'bookings', [
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

        $this->seedOnce($pdo, 'accounts', [
            ['type' => 'income', 'description' => 'Room Booking', 'amount' => 40000, 'date' => '2025-02-05'],
            ['type' => 'expense', 'description' => 'Staff Salary', 'amount' => 15000, 'date' => '2025-02-07'],
        ], 'description');
    }

    protected function seedOnce(\PDO $pdo, string $table, array $rows, string|array|null $uniqueKey): void
    {
        foreach ($rows as $row) {
            if ($uniqueKey) {
                $keys = (array)$uniqueKey;
                $conditions = [];
                $params = [];
                $skip = false;
                foreach ($keys as $index => $key) {
                    if (!array_key_exists($key, $row)) {
                        $skip = true;
                        break;
                    }
                    $placeholder = ':value' . $index;
                    $conditions[] = "{$key} = {$placeholder}";
                    $params[$placeholder] = $row[$key];
                }
                if ($skip === false) {
                    $query = "SELECT COUNT(*) FROM {$table} WHERE " . implode(' AND ', $conditions);
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    if ($stmt->fetchColumn()) {
                        continue;
                    }
                }
            }

            $columns = implode(', ', array_keys($row));
            $placeholders = ':' . implode(', :', array_keys($row));
            $stmt = $pdo->prepare("INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})");
            $stmt->execute($row);
        }
    }
};
