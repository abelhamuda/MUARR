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
            $zipFilename = 'Risk-Application_review.zip';
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
                    case ($columnName === 'email'):
                        $columns['email'] = $index;
                        break;

                    case (preg_match('/login_account/i', $columnName)):
                        $columns['login_account'] = $index;
                        break;

                    case (preg_match('/uid/i', $columnName)):
                        $columns['uid'] = $index;
                        break;
                
                    case (preg_match('/user_name/i', $columnName)):
                        $columns['user_name'] = $index;
                        break;

                        // case ($columnName === 'email'):
                        //     $columns['email'] = $index;
                        //     break;
                
                    case (preg_match('/user_create_time/i', $columnName)):
                        $columns['user_create_time'] = $index;
                        break;
                
                    case (preg_match('/user_update_time/i', $columnName)):
                        $columns['user_update_time'] = $index;
                        break;
                
                    case (preg_match('/delete_flag/i', $columnName)):
                        $columns['delete_flag'] = $index;
                        break;
                
                    case (preg_match('/role_names/i', $columnName)):
                        $columns['role_names'] = $index;
                        break;
                        break;
                
                    case (preg_match('/system_names/i', $columnName)):
                        $columns['system_names'] = $index;
                        break;
                        break;
                
                    case (preg_match('/last_login_time/i', $columnName)):
                        $columns['last_login_time'] = $index;
                        break;
                    }
                    
            }
    
            if (!isset($columns['email']) && !isset($columns['login_account'])) {
                throw new \Exception('No "Email" or "Login Account" column found in the CSV file.');
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
        $activeNameCol = $activeEmployees['columns']['email'];  
        $userRows = $applicationUsers['rows'];
        $userCols = $applicationUsers['columns'];
    
        foreach ($userRows as $user) {
            $status = 'Resign';
            $remark = 'Disable';
    
            if (isset($userCols['login_account']) && is_array($user)) {
                $userName = $this->getNameFromRecord($user, $userCols['login_account']);
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