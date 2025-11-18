<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class GradeDesignationSeeder extends Seeder
{
    public function run(): void
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

        $this->seedOnce('grades', $grades, 'grade_no');

        $gradeMap = DB::table('grades')->pluck('id', 'grade_no');

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
            $this->seedOnce('designations', $designations, ['grade_id', 'designation_name']);
        }
    }

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
