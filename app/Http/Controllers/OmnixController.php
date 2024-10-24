<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class OmnixController extends Controller
{
    public function omnix(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('omnix');
        }

        $request->validate([
            'active_employees' => 'required|file|mimes:csv,txt',
            'application_users' => 'required|array',
            'application_users.*' => 'file|mimes:csv,txt',
        ]);

        try {
            $activeEmployees = $this->parseCSV($request->file('active_employees')->getPathname());

            $zip = new \ZipArchive();
            $zipFilename = 'employee_status_reports.zip';
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
                if (preg_match('/email/i', $columnName)) {
                    $columns['email'] = $index;
                // For application users, we now want the "Employee Name" column
                } elseif (preg_match('/name/i', $columnName)) {
                    $columns['name'] = $index;
                 } elseif (preg_match('/email/i', $columnName)) {
                    $columns['email'] = $index;
                } elseif (preg_match('/nik/i', $columnName)) {
                    $columns['nik'] = $index;
                } elseif (preg_match('/role/i', $columnName)) {
                    $columns['role'] = $index;
                } elseif (preg_match('/divisi/i', $columnName)) {
                    $columns['divisi'] = $index;
                } elseif (preg_match('/jabatan/i', $columnName)) {
                    $columns['jabatan'] = $index;
                }
            }

            // Ensure that the required columns are present
            if (!isset($columns['email']) && !isset($columns['email'])) {
                throw new \Exception('No "Full Name" or "Name" column found in the CSV file.');
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
        $activeNameCol = $activeEmployees['columns']['email'];  
        $userRows = $applicationUsers['rows'];
        $userCols = $applicationUsers['columns'];

        foreach ($userRows as $user) {
            $status = 'Resign';
            $remark = 'Disable';

            // Check if the "CN" column exists and the row is an array
            if (isset($userCols['email']) && is_array($user)) {
                $userName = $this->getNameFromRecord($user, $userCols['email']);
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
                'Email' => $userName,
                'Name' => $user[$userCols['name']] ?? '',
                'NIK' => $user[$userCols['nik']] ?? '',
                'Role' => $user[$userCols['role']] ?? '',
                'Divisi' => $user[$userCols['divisi']] ?? '',
                'Jabatan' => $user[$userCols['jabatan']] ?? '',
                'Status' => $status,
                'Remarks' => $remark
            ];
        }

        return $results;
    }

    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');

        fputcsv($output, [ 'Email', 'Name', 'NIK', 'Role', 'Divisi', 'Jabatan', 'Status', 'Remarks']);

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

        return $similarity >= 0.8;
    }
}