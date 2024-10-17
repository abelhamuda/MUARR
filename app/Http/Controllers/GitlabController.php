<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class GitlabController extends Controller
{
    public function gitlab(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('gitlab');
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
                if (preg_match('/full\s?name/i', $columnName)) {
                    $columns['full_name'] = $index;
                } elseif (preg_match('/name\.1/i', $columnName)) { // Change to match "Name.1"
                    $columns['name.1'] = $index; 
                } elseif (preg_match('/uid/i', $columnName)) {
                    $columns['uid'] = $index;
                } elseif (preg_match('/email/i', $columnName)) { // Added to capture "Email"
                    $columns['email'] = $index;
                } elseif (preg_match('/projects/i', $columnName)) { // Added to capture "Projects"
                    $columns['projects'] = $index;
                } elseif (preg_match('/groups/i', $columnName)) { // Added to capture "Groups"
                    $columns['groups'] = $index;
                } elseif (preg_match('/created on/i', $columnName)) { // Added to capture "Created on"
                    $columns['created_on'] = $index;
                } elseif (preg_match('/last activity/i', $columnName)) { // Added to capture "Last activity"
                    $columns['last_activity'] = $index;
                }
            }
    
            // Ensure that the required columns are present
            if (!isset($columns['full_name']) && !isset($columns['name.1'])) {
                throw new \Exception('No "Full Name" or "Name.1" column found in the CSV file.');
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
    
            // Check if the Name.1 column exists and the row is an array
            if (isset($userCols['name.1']) && is_array($user)) {
                $userName = $this->getNameFromRecord($user, $userCols['name.1']);
            } else {
                continue; // Skip this iteration if there's no Name.1 column or row is invalid
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
                'Name.1' => $userName,
                'Email' => $user[$userCols['email']] ?? '',
                'Projects' => $user[$userCols['projects']] ?? '',
                'Groups' => $user[$userCols['groups']] ?? '',
                'Created on' => $user[$userCols['created_on']] ?? '',
                'Last activity' => $user[$userCols['last_activity']] ?? '',
                'Status' => $status,
                'Remarks' => $remark
            ];
        }
    
        return $results;
    }
    
    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');
    
        fputcsv($output, ['Name.1', 'Email', 'Projects', 'Groups', 'Created on', 'Last activity', 'Status', 'Remarks']);
    
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