<?php

return new class {
    public function run(\PDO $pdo, callable $seedOnce): void
    {
        $grades = $this->mapGrades($pdo);
        $designationMap = $this->mapDesignations($pdo, $grades);

        $employees = [
            [
                'name' => 'Anika Chowdhury',
                'father_name' => 'Kamrul Chowdhury',
                'mother_name' => 'Nasima Begum',
                'nid' => '1234512345123',
                'address' => 'Dhanmondi, Dhaka',
                'phone' => '01755555555',
                'email' => 'anika.hr@example.com',
                'education' => 'MBA, HRM',
                'qualifications' => '10+ years leadership',
                'grade_no' => 1,
                'designation_name' => 'Director',
                'join_date' => '2024-01-15',
                'salary' => 80000,
                'document_checklist' => json_encode(['nid', 'police_clearance', 'photo']),
                'photo_path' => 'employees/anika.jpg',
                'status' => 'active',
            ],
            [
                'name' => 'Sharif Ahmed',
                'father_name' => 'Jalal Ahmed',
                'mother_name' => 'Salma Khatun',
                'nid' => '7890678906789',
                'address' => 'Sylhet Sadar, Sylhet',
                'phone' => '01866666666',
                'email' => 'sharif.sales@example.com',
                'education' => 'BBA, Marketing',
                'qualifications' => 'Certified sales professional',
                'grade_no' => 6,
                'designation_name' => 'Marketing Officer',
                'join_date' => '2024-03-20',
                'salary' => 35000,
                'document_checklist' => json_encode(['nid', 'police_clearance', 'photo']),
                'photo_path' => 'employees/sharif.jpg',
                'status' => 'probation',
            ],
            [
                'name' => 'Raisa Sultana',
                'father_name' => 'Mahfuz Sultana',
                'mother_name' => 'Tahmina Rahman',
                'nid' => '5678956789567',
                'address' => 'Mirpur DOHS, Dhaka',
                'phone' => '01677777777',
                'email' => 'raisa.finance@example.com',
                'education' => 'BBA, Finance',
                'qualifications' => 'Certified management accountant',
                'grade_no' => 2,
                'designation_name' => 'Shareholder Director',
                'join_date' => '2024-02-10',
                'salary' => 65000,
                'document_checklist' => json_encode(['nid', 'photo', 'experience_letter']),
                'photo_path' => 'employees/raisa.jpg',
                'status' => 'active',
            ],
            [
                'name' => 'Towhidul Islam',
                'father_name' => 'Lutfor Rahman',
                'mother_name' => 'Shirin Akter',
                'nid' => '6543265432654',
                'address' => 'Khulna City, Khulna',
                'phone' => '01588888888',
                'email' => 'towhid.it@example.com',
                'education' => 'BSc, Computer Science',
                'qualifications' => 'AWS Certified Solutions Architect',
                'grade_no' => 3,
                'designation_name' => 'ED',
                'join_date' => '2024-04-05',
                'salary' => 55000,
                'document_checklist' => json_encode(['nid', 'photo', 'medical_certificate']),
                'photo_path' => 'employees/towhid.jpg',
                'status' => 'active',
            ],
            [
                'name' => 'Lamisa Rahman',
                'father_name' => 'Shamim Rahman',
                'mother_name' => 'Nilufar Yasmin',
                'nid' => '2109821098210',
                'address' => 'Rajshahi Sadar, Rajshahi',
                'phone' => '01799999999',
                'email' => 'lamisa.support@example.com',
                'education' => 'BSc, Hospitality Management',
                'qualifications' => 'Service excellence specialist',
                'grade_no' => 5,
                'designation_name' => 'Assistant Manager',
                'join_date' => '2024-05-12',
                'salary' => 28000,
                'document_checklist' => json_encode(['nid', 'photo']),
                'photo_path' => 'employees/lamisa.jpg',
                'status' => 'probation',
            ],
        ];

        $rows = array_map(function (array $employee) use ($grades, $designationMap): array {
            $gradeNo = (int)$employee['grade_no'];
            $designationName = $employee['designation_name'];
            $gradeData = $grades[$gradeNo] ?? ['id' => null, 'label' => 'Grade ' . $gradeNo];
            $designationId = $designationMap[$gradeNo][$designationName] ?? null;

            return [
                'name' => $employee['name'],
                'father_name' => $employee['father_name'],
                'mother_name' => $employee['mother_name'],
                'nid' => $employee['nid'],
                'address' => $employee['address'],
                'phone' => $employee['phone'],
                'email' => $employee['email'],
                'education' => $employee['education'],
                'qualifications' => $employee['qualifications'],
                'grade_id' => $gradeData['id'],
                'designation_id' => $designationId,
                'grade' => $gradeData['label'],
                'position' => $designationName,
                'join_date' => $employee['join_date'],
                'salary' => $employee['salary'],
                'document_checklist' => $employee['document_checklist'],
                'photo_path' => $employee['photo_path'],
                'status' => $employee['status'],
            ];
        }, $employees);

        $seedOnce($pdo, 'employees', $rows, 'nid');
    }

    private function mapGrades(\PDO $pdo): array
    {
        $stmt = $pdo->query('SELECT id, grade_no, grade_name FROM grades');
        $grades = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $grades[(int)$row['grade_no']] = [
                'id' => (int)$row['id'],
                'label' => $this->formatGradeLabel($row),
            ];
        }

        return $grades;
    }

    private function mapDesignations(\PDO $pdo, array $grades): array
    {
        $gradeIdToNo = [];
        foreach ($grades as $gradeNo => $grade) {
            if (!isset($grade['id'])) {
                continue;
            }
            $gradeIdToNo[$grade['id']] = $gradeNo;
        }

        $stmt = $pdo->query('SELECT id, grade_id, designation_name FROM designations');
        $map = [];
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $gradeNo = $gradeIdToNo[(int)$row['grade_id']] ?? null;
            if ($gradeNo === null) {
                continue;
            }
            $map[$gradeNo][$row['designation_name']] = (int)$row['id'];
        }

        return $map;
    }

    private function formatGradeLabel(array $grade): string
    {
        $label = 'Grade ' . ($grade['grade_no'] ?? '');
        if (!empty($grade['grade_name'])) {
            $label .= ' - ' . $grade['grade_name'];
        }

        return trim($label, ' -');
    }
};
