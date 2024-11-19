<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EdapemController extends Controller
{
    public function edapem(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('edapem');
        }
    
        $request->validate([
            'active_employees' => 'required|file|mimes:csv,txt',
            'application_users' => 'required|array',
            'application_users.*' => 'required|file|mimes:csv,txt',
        ]);
    
        try {
            $activeEmployees = $this->parseCSV($request->file('active_employees')->getPathname());
    
            $zip = new \ZipArchive();
            $zipFilename = 'Edapem_review.zip';
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
                    case (preg_match('/email/i', $columnName)):
                        $columns['email'] = $index;
                        break;

                    case (preg_match('/login\s?id/i', $columnName)):
                        $columns['login_id'] = $index;
                        break;
                
                    case (preg_match('/nama\s?user/i', $columnName)):
                        $columns['nama_user'] = $index;
                        break;
                
                    case (preg_match('/nik/i', $columnName)):
                        $columns['nik'] = $index;
                        break;
                
                    case (preg_match('/role/i', $columnName)):
                        $columns['role'] = $index;
                        break;
                
                    case (preg_match('/nm\s?cabang/i', $columnName)):
                        $columns['nm_cabang'] = $index;
                        break;
                
                    case (preg_match('/status/i', $columnName)):
                        $columns['status'] = $index;
                        break;
                
                    case (preg_match('/last\s?login/i', $columnName)):
                        $columns['last_login'] = $index;
                        break;
                    }
                    
            }
    
            if (!isset($columns['email']) && !isset($columns['login_id'])) {
                throw new \Exception('No "Full Name" & "Login ID" column found in the CSV file.');
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
    
            if (isset($userCols['login_id']) && is_array($user)) {
                $userName = $this->getNameFromRecord($user, $userCols['login_id']);
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
                'Login ID' => $userName,
                'Nama User' => $user[$userCols['nama_user']] ?? '',
                'NIK' => $user[$userCols['nik']] ?? '',
                'Role' => $user[$userCols['role']] ?? '',
                'NM Cabang' => $user[$userCols['nm_cabang']] ?? '',
                'Status' => $user[$userCols['status']] ?? '',
                'Last Login' => $user[$userCols['last_login']] ?? '',
                'Status Review' => $status,
                'Remarks' => $remark
            ];
        }
    
        return $results;
    }
    
    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');
     
        fputcsv($output, ['Login ID', 'Nama User', 'NIK', 'Role', 'NM Cabang', 'Status', 'Last Login', 'Status Review', 'Remarks']);
    
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