<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    public function index()
    {
        return view('home'); // Home view
    }

    public function ssoprocess(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('sso'); // SSO Process form
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
    

    public function alphabit(Request $request)
    {
        return view('alphabit'); // Alphabit
    }

    public function icore(Request $request)
    {
        return view('icore'); // Icore
    }

    public function otherApp(Request $request)
    {
        return view('other_app'); // Other App
    }

    private function parseCSV($filepath)
    {
        $rows = [];
        $columns = [];
    
        if (($handle = fopen($filepath, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
    
            foreach ($header as $index => $columnName) {
                $columnName = strtolower(trim($columnName));
                if (preg_match('/user\s?id/i', $columnName)) {
                    $columns['user_id'] = $index;
                } elseif (preg_match('/nip/i', $columnName)) {
                    $columns['nip'] = $index;
                } elseif (preg_match('/email/i', $columnName)) {
                    $columns['email'] = $index;
                } elseif (preg_match('/role/i', $columnName)) {
                    $columns['role'] = $index;
                } elseif (preg_match('/name/i', $columnName)) {
                    $columns['name'] = $index;
                }
            }
    
            if (!isset($columns['name'])) {
                throw new \Exception('No "Name" column found in the CSV file.');
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
        $activeNameCol = $activeEmployees['columns']['name'];
        $userRows = $applicationUsers['rows'];
        $userCols = $applicationUsers['columns'];
    
        foreach ($userRows as $user) {
            $status = 'Resign';
            $remark = 'Disable'; 
            $userName = $this->getNameFromRecord($user, $userCols['name']);
    
            foreach ($activeRows as $employee) {
                $employeeName = $this->getNameFromRecord($employee, $activeNameCol);
                if ($this->compareNames($userName, $employeeName)) {
                    $status = 'Active';
                    $remark = 'Keep'; 
                    break;
                }
            }
    
            $results[] = [
                'User ID' => $user[$userCols['user_id']] ?? '',
                'NIP' => $user[$userCols['nip']] ?? '',        
                'Email' => $user[$userCols['email']] ?? '',    
                'Role' => $user[$userCols['role']] ?? '',      
                'Name' => $userName,
                'Status' => $status,
                'Remarks' => $remark
            ];
        }
    
        return $results;
    }
    
    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');
    
        fputcsv($output, ['User ID', 'NIP', 'Email', 'Role', 'Name', 'Status', 'Remarks']);
    
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
