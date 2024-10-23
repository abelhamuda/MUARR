<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RiskAppController extends Controller
{
    public function risk_app(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('risk_app');
        }
    
        $request->validate([
            'active_employees' => 'required|file|mimes:csv,txt',
            'application_users' => 'required|array',
            'application_users.*' => 'required|file|mimes:csv,txt',
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
            \Log::error($e->getMessage());  // Add logging
            return redirect()->back()->with('error', 'An error occurred during processing. Please try again.');
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
    
                // Map required columns based on patterns
                if (preg_match('/email/i', $columnName)) {
                    $columns['email'] = $index;
                } elseif (preg_match('/login_account/i', $columnName)) {
                    $columns['login_account'] = $index;
                } elseif (preg_match('/uid/i', $columnName)) {
                    $columns['uid'] = $index;
                } elseif (preg_match('/user_name/i', $columnName)) {
                    $columns['user_name'] = $index;
                } elseif (preg_match('/user_create_time/i', $columnName)) {
                    $columns['user_create_time'] = $index;
                } elseif (preg_match('/user_update_time/i', $columnName)) {
                    $columns['user_update_time'] = $index;
                } elseif (preg_match('/delete_flag/i', $columnName)) {
                    $columns['delete_flag'] = $index;
                } elseif (preg_match('/role_names/i', $columnName)) {
                    $columns['role_names'] = $index;
                } elseif (preg_match('/system_names/i', $columnName)) {
                    $columns['system_names'] = $index;
                } elseif (preg_match('/last_login_time/i', $columnName)) {
                    $columns['last_login_time'] = $index;
                }
            }
    
            // Ensure that the required columns are present
            if (!isset($columns['email']) && !isset($columns['login_account'])) {
                throw new \Exception('No "Full Name" or "login_account" column found in the CSV file.');
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
    
            // Check if the user name column exists and the row is an array
            if (isset($userCols['login_account']) && is_array($user)) {
                $userName = $this->getNameFromRecord($user, $userCols['login_account']);
            } else {
                continue; // Skip this iteration if there's no user name column or row is invalid
            }
    
            foreach ($activeRows as $employee) {
                // Check if the active employee row is an array and full name column exists
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
                'UID' => $user[$userCols['uid']] ?? '', 
                'Login Account' => $userName,
                'User Name' => $user[$userCols['user_name']] ?? '',
                'Create Time' => $user[$userCols['user_create_time']] ?? '',
                'Update Time' => $user[$userCols['user_update_time']] ?? '',
                'Delete Flag' => $user[$userCols['delete_flag']] ?? '',
                'Role Names' => $user[$userCols['role_names']] ?? '',
                'System Names' => $user[$userCols['system_names']] ?? '',
                'Last Login' => $user[$userCols['last_login_time']] ?? '',
                'Status' => $status,
                'Remarks' => $remark
            ];
        }
    
        return $results;
    }
    
    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');
    
        fputcsv($output, ['UID', 'Login Account', 'User Name', 'Create Time', 'Update Time', 'Delete Flag', 'Role Names', 'System Names', 'Last Login', 'Status', 'Remarks']);
    
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
