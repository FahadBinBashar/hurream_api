<?php

use App\Core\Database;

return new class {
    public function run(): void
    {
        $pdo = Database::connection();

        $roles = ['admin', 'hr', 'accounts', 'officer', 'investor', 'customer'];
        foreach ($roles as $role) {
            $stmt = $pdo->prepare('INSERT OR IGNORE INTO roles (name, permissions) VALUES (:name, :permissions)');
            $stmt->execute([
                'name' => $role,
                'permissions' => json_encode(['*']),
            ]);
        }

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
            ],
        ], 'NID');

        $this->seedOnce($pdo, 'investors', [
            [
                'name' => 'Nazmul Hasan',
                'NID' => '3216549870123',
                'phone' => '01733333333',
                'email' => 'nazmul@invest.com',
                'address' => 'Banani, Dhaka',
                'bank_info' => 'DBBL-12345',
                'nominee' => 'Rafiq',
                'status' => 'active',
                'otp_verified_at' => '2025-01-08T09:30:00+06:00',
                'admin_approved_at' => '2025-01-09T11:15:00+06:00',
            ],
            [
                'name' => 'Farhana Akter',
                'NID' => '9876543210987',
                'phone' => '01844444444',
                'email' => 'farhana@invest.com',
                'address' => 'Agrabad, Chattogram',
                'bank_info' => 'EBL-54321',
                'nominee' => 'Shila',
                'status' => 'pending',
            ],
        ], 'NID');

        $this->seedOnce($pdo, 'shares', [
            [
                'investor_id' => 1,
                'unit_price' => 25000,
                'quantity' => 4,
                'amount' => 100000,
                'payment_mode' => 'one_time',
                'stage' => 'MÖW-5',
                'reinvest_flag' => 1,
                'approval_status' => 'approved',
                'approver_gate_triggered' => 0,
            ],
            [
                'investor_id' => 2,
                'unit_price' => 25000,
                'quantity' => 25,
                'amount' => 625000,
                'payment_mode' => 'installment',
                'stage' => 'MÖW-6',
                'reinvest_flag' => 0,
                'approval_status' => 'pending',
                'approver_gate_triggered' => 1,
            ],
        ], ['investor_id', 'stage']);

        $this->seedOnce($pdo, 'approvals', [
            ['module' => 'share_issue', 'record_id' => 2, 'approver_id' => 1, 'status' => 'pending'],
            ['module' => 'share_issue', 'record_id' => 2, 'approver_id' => 2, 'status' => 'pending'],
            ['module' => 'share_issue', 'record_id' => 2, 'approver_id' => 3, 'status' => 'pending'],
        ], ['module', 'record_id', 'approver_id']);

        $this->seedOnce($pdo, 'transactions', [
            ['share_id' => 1, 'amount' => 50000, 'payment_type' => 'bank', 'date' => '2025-01-10'],
            ['share_id' => 2, 'amount' => 25000, 'payment_type' => 'cash', 'date' => '2025-02-12'],
        ], ['share_id', 'date']);

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
