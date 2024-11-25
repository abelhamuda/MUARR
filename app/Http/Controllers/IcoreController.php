<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class IcoreController extends Controller
{
    public function icore(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('icore');
        }
    
        $request->validate([
            'active_employees' => 'required|file|mimes:csv,txt',
            'application_users' => 'required|array',
            'application_users.*' => 'required|file|mimes:csv,txt',
        ]);
    
        try {
            $activeEmployees = $this->parseCSV($request->file('active_employees')->getPathname());
    
            $zip = new \ZipArchive();
            $zipFilename = 'Icore_review.zip';
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
            \Log::error($e->getMessage());
            return redirect()->back()->with('error', 'An error occurred during processing. Please try again.');
        }
    }

    private function parseCSV($filepath)
    {
        $rows = [];
        $columns = [];
    
        try {
            if (($handle = fopen($filepath, "r")) === FALSE) {
                throw new \Exception("Unable to open the file: $filepath");
            }
    
            $header = fgetcsv($handle, 1000, ",");
    
            foreach ($header as $index => $columnName) {
                $columnName = strtolower(trim($columnName));
    
                switch (true) {
                    // case ($columnName === 'full name'):
                    //     $columns['full__name'] = $index;
                    //     break;

                    case (preg_match('/full\s?name/i', $columnName)):
                        $columns['full_name'] = $index;
                        break;

                    case (preg_match('/user\s?name/i', $columnName)):
                        $columns['user_name'] = $index;
                        break;

                    case (preg_match('/user\s?status/i', $columnName)):
                        $columns['user_status'] = $index;
                        break;

                    case (preg_match('/user\s?id/i', $columnName)):
                        $columns['user_id'] = $index;
                        break;
                
                    case (preg_match('/date\s?create\s?time/i', $columnName)):
                        $columns['date_create_time'] = $index;
                        break;
                
                    case (preg_match('/last_login_time/i', $columnName)):
                        $columns['last_login_time'] = $index;
                        break;
                
                    case (preg_match('/role\s?id/i', $columnName)):
                        $columns['role_id'] = $index;
                        break;
                
                    case (preg_match('/status\s?user/i', $columnName)):
                        $columns['status_user'] = $index;
                        break;
                    }    
            }
    
            if (!isset($columns['full_name']) && !isset($columns['user_name'])) {
                throw new \Exception('No "Full Name" or "User Name" column found in the CSV file.');
            }
    
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rows[] = $data;
            }
        } catch (\Exception $e) {
            echo "Error: " . $e->getMessage();
        } finally {
            if (isset($handle) && $handle !== FALSE) {
                fclose($handle);
            }
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
    
            if (isset($userCols['user_name']) && is_array($user)) {
                $userName = $this->getNameFromRecord($user, $userCols['user_name']);
            } else {
                continue; 
            }
    
            foreach ($activeRows as $employee) {
                
                if (is_array($employee) && isset($activeNameCol)) {
                    $employeeName = $this->getNameFromRecord($employee, $activeNameCol);
                } else {
                    continue;
                }
    
                if ($this->compareNames($userName, $employeeName)) {
                    $status = 'Active';
                    $remark = 'Keep';
                    break;
                }
            }
    
            $results[] = [
                'User ID' => $user[$userCols['user_id']] ?? '', 
                'User Name' => $userName,
                'User Status' => $user[$userCols['user_status']] ?? '', 
                'Role ID' => $user[$userCols['role_id']] ?? '', 
                'Date Create Time' => $user[$userCols['date_create_time']] ?? '',
                'Last Login' => $user[$userCols['last_login_time']] ?? '',
                'Status User' => $user[$userCols['status_user']] ?? '',
                'Status Review' => $status,
                'Remarks' => $remark
            ];
        }
    
        return $results;
    }
    
    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');
     
        fputcsv($output, ['User ID', 'User Name', 'User Status', 'Role ID', 'Date Create Time', 'Last Login', 'Status User', 'Status Review', 'Remarks']);
    
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

        return $similarity >= 0.5;
    }
}