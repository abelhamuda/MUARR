<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdExchangeController extends Controller
{
    public function adexchange(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('adexchange');
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
                if (preg_match('/full\s?name/i', $columnName)) {
                    $columns['full_name'] = $index;
                // For application users, we now want the "CN" column
                } elseif (preg_match('/cn/i', $columnName)) {
                    $columns['cn'] = $index;
                } elseif (preg_match('/department/i', $columnName)) {
                    $columns['department'] = $index;
                } elseif (preg_match('/email\s?address/i', $columnName)) {
                    $columns['email_address'] = $index;
                } elseif (preg_match('/last\s?logon\s?date/i', $columnName)) {
                    $columns['last_logon_date'] = $index;
                }
            }

            // Ensure that the required columns are present
            if (!isset($columns['full_name']) && !isset($columns['cn'])) {
                throw new \Exception('No "Full Name" or "Common Name" column found in the CSV file.');
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
            if (isset($userCols['cn']) && is_array($user)) {
                $cn = $this->getNameFromRecord($user, $userCols['cn']);
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

                if ($this->compareNames($cn, $employeeName)) {
                    $status = 'Active';
                    $remark = 'Keep';
                    break;
                }
            }

            $results[] = [
                'Common Names' => $cn,
                'Department' => $user[$userCols['department']] ?? '',
                'EmailAddress' => $user[$userCols['email_address']] ?? '',
                'LastLogonDate' => $user[$userCols['last_logon_date']] ?? '',
                'Status' => $status,
                'Remarks' => $remark
            ];
        }

        return $results;
    }

    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');

        fputcsv($output, ['Common Names', 'Department', 'EmailAddress', 'LastLogonDate', 'Status', 'Remarks']);

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