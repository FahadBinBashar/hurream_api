<?php

return new class {
    public function run(\PDO $pdo, callable $seedOnce): void
    {
        $grades = [
            [
                'grade_no' => 1,
                'grade_name' => 'Director / MD / Chairman',
                'description' => 'Board and executive leadership roles',
                'status' => 'active',
            ],
            [
                'grade_no' => 2,
                'grade_name' => 'Shareholder Director',
                'description' => 'Equity partners and directors',
                'status' => 'active',
            ],
            [
                'grade_no' => 3,
                'grade_name' => 'ED / SED',
                'description' => 'Executive and senior executive directors',
                'status' => 'active',
            ],
            [
                'grade_no' => 4,
                'grade_name' => 'AGM / DGM / GM',
                'description' => 'General management leadership',
                'status' => 'active',
            ],
            [
                'grade_no' => 5,
                'grade_name' => 'Assistant & Deputy Manager',
                'description' => 'Mid level managers overseeing daily ops',
                'status' => 'active',
            ],
            [
                'grade_no' => 6,
                'grade_name' => 'Marketing / Officer Cadre',
                'description' => 'Officers and executives handling frontline tasks',
                'status' => 'active',
            ],
        ];

        $seedOnce($pdo, 'grades', $grades, 'grade_no');

        $gradeStmt = $pdo->query('SELECT id, grade_no FROM grades');
        $gradeMap = [];
        foreach ($gradeStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $gradeMap[(int)$row['grade_no']] = (int)$row['id'];
        }

        $designationSeed = [
            ['grade_no' => 1, 'designation_name' => 'Director'],
            ['grade_no' => 1, 'designation_name' => 'MD'],
            ['grade_no' => 1, 'designation_name' => 'Chairman'],
            ['grade_no' => 2, 'designation_name' => 'Shareholder Director'],
            ['grade_no' => 3, 'designation_name' => 'ED'],
            ['grade_no' => 3, 'designation_name' => 'SED'],
            ['grade_no' => 4, 'designation_name' => 'AGM'],
            ['grade_no' => 4, 'designation_name' => 'DGM'],
            ['grade_no' => 4, 'designation_name' => 'GM'],
            ['grade_no' => 5, 'designation_name' => 'Assistant Manager'],
            ['grade_no' => 5, 'designation_name' => 'Deputy Manager'],
            ['grade_no' => 6, 'designation_name' => 'Marketing Officer'],
            ['grade_no' => 6, 'designation_name' => 'Officer-1'],
            ['grade_no' => 6, 'designation_name' => 'Executive Officer'],
        ];

        $designations = [];
        foreach ($designationSeed as $designation) {
            $gradeId = $gradeMap[$designation['grade_no']] ?? null;
            if (!$gradeId) {
                continue;
            }

            $designations[] = [
                'grade_id' => $gradeId,
                'designation_name' => $designation['designation_name'],
                'status' => 'active',
            ];
        }

        if (!empty($designations)) {
            $seedOnce($pdo, 'designations', $designations, ['grade_id', 'designation_name']);
        }
    }
};
