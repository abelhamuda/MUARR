<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OfficeAutomationController extends Controller
{
    public function officeautomation(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('officeautomation');
        }

        $request->validate([
            'active_employees' => 'required|file|mimes:csv,txt',
            'application_users' => 'required|array',
            'application_users.*' => 'file|mimes:csv,txt',
        ]);

        try {
            $activeEmployees = $this->parseCSV($request->file('active_employees')->getPathname());

            $zip = new \ZipArchive();
            $zipFilename = 'Office-Automation_reports.zip';
            $zipPath = storage_path($zipFilename);

            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                foreach ($request->file('application_users') as $applicationFile) {
                    $applicationUsers = $this->parseCSV($applicationFile->getPathname());
                    $results = $this->compareEmployees($activeEmployees, $applicationUsers);
                    $csvContent = $this->generateCSV($results);
                    $outputFilename = pathinfo($applicationFile->getClientOriginalName(), PATHINFO_FILENAME) . '_Reviewed.csv';
                    $zip->addFromString($outputFilename, $csvContent);
                }
                $zip->close();
            }

            return response()->download($zipPath)->deleteFileAfterSend(true);

        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    private function parseCSV($filepath)
    {
        $rows = [];
        $columns = [];

        if (($handle = fopen($filepath, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");

            foreach ($header as $index => $columnName) {
                $columnName = strtolower(trim($columnName));
                // For active employees, we want the "Full Name" column
                if (preg_match('/full\s?name/i', $columnName)) {
                    $columns['full_name'] = $index;
                // For application users, we now want the "Employee Name" column
                } elseif (preg_match('/employee\s?name/i', $columnName)) {
                    $columns['employee_name'] = $index;
                //  } elseif (preg_match('/employee\a?no/i', $columnName)) {
                //     $columns['employee_no.'] = $index;
                } elseif (preg_match('/user\s?name/i', $columnName)) {
                    $columns['user_name'] = $index;
                } elseif (preg_match('/division/i', $columnName)) {
                    $columns['division'] = $index;
                } elseif (preg_match('/position/i', $columnName)) {
                    $columns['position'] = $index;
                } elseif (preg_match('/last\s?modified\s?date\s?time/i', $columnName)) {
                    $columns['last_modified_date_time'] = $index;
                }
            }

            // Ensure that the required columns are present
            if (!isset($columns['full_name']) && !isset($columns['employee_name'])) {
                throw new \Exception('No "Full Name" or "Employee Name" column found in the CSV file.');
            }

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rows[] = $data;
            }
            fclose($handle);
        }

        return ['rows' => $rows, 'columns' => $columns];
    }

    private function compareEmployees($activeEmployees, $applicationUsers)
    {
        $results = [];
        $activeRows = $activeEmployees['rows'];
        $activeNameCol = $activeEmployees['columns']['full_name'];  
        $userRows = $applicationUsers['rows'];
        $userCols = $applicationUsers['columns'];

        foreach ($userRows as $user) {
            $status = 'Resign';
            $remark = 'Disable';

            // Check if the "CN" column exists and the row is an array
            if (isset($userCols['employee_name']) && is_array($user)) {
                $userName = $this->getNameFromRecord($user, $userCols['employee_name']);
            } else {
                continue; // Skip this iteration if there's no "CN" column or row is invalid
            }

            foreach ($activeRows as $employee) {
                // Check if the active employee row is an array and "full_name" column exists
                if (is_array($employee) && isset($activeNameCol)) {
                    $employeeName = $this->getNameFromRecord($employee, $activeNameCol);
                } else {
                    continue; // Skip this iteration if row/column is invalid
                }

                if ($this->compareNames($userName, $employeeName)) {
                    $status = 'Active';
                    $remark = 'Keep';
                    break;
                }
            }

            $results[] = [
                'Name' => $userName,
                'Username' => $user[$userCols['user_name']] ?? '',
                // 'Employee No' => $user[$userCols['employee_no']] ?? '',
                'Division' => $user[$userCols['division']] ?? '',
                'Position' => $user[$userCols['position']] ?? '',
                'Last Modified Date' => $user[$userCols['last_modified_date_time']] ?? '',
                'Status' => $status,
                'Remarks' => $remark
            ];
        }

        return $results;
    }

    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');

        fputcsv($output, ['Names', 'Username', 'Division', 'Position', 'Last Modified Date', 'Status', 'Remarks']);

        foreach ($data as $row) {
            fputcsv($output, $row);
        }

        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);

        return $csvContent;
    }

    private function getNameFromRecord($record, $nameColumnIndex)
    {
        return $record[$nameColumnIndex] ?? '';
    }

    private function compareNames($name1, $name2)
    {
        $name1 = strtolower(trim($name1));
        $name2 = strtolower(trim($name2));

        if ($name1 === $name2) {
            return true;
        }

        $similarity = 1 - (levenshtein($name1, $name2) / max(strlen($name1), strlen($name2)));

        return $similarity >= 0.45;
    }
}