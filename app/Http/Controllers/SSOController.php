<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SSOController extends Controller
{
    public function ssoprocess(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('sso');
        }
    
        $request->validate([
            'active_employees' => 'required|file|mimes:csv,txt',
            'application_users' => 'required|array',
            'application_users.*' => 'required|file|mimes:csv,txt',
        ]);
    
        try {
            $activeEmployees = $this->parseCSV($request->file('active_employees')->getPathname());
    
            $zip = new \ZipArchive();
            $zipFilename = 'SSO-Application_review.zip';
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

                    case (preg_match('/nama\s?lengkap/i', $columnName)):
                        $columns['nama_lengkap'] = $index;
                        break;

                    case (preg_match('/user\s?id/i', $columnName)):
                        $columns['user_id'] = $index;
                        break;
                
                    case (preg_match('/nip/i', $columnName)):
                        $columns['nip'] = $index;
                        break;

                        case ($columnName === 'email'):
                            $columns['email'] = $index;
                            break;
                
                    case (preg_match('/last\s?login/i', $columnName)):
                        $columns['last_login'] = $index;
                        break;
                
                    case (preg_match('/last\s?seen/i', $columnName)):
                        $columns['last_seen'] = $index;
                        break;
                
                    case (preg_match('/status/i', $columnName)):
                        $columns['status_akun'] = $index;
                        break;
                
                    case (preg_match('/role/i', $columnName)):
                        $columns['role'] = $index;
                        break;
                    }
                    
            }
    
            if (!isset($columns['email'])) {
                throw new \Exception('No "Email" column found in the CSV file.');
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
    
            if (isset($userCols['email']) && is_array($user)) {
                $userName = $this->getNameFromRecord($user, $userCols['email']);
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
                'NIP' => $user[$userCols['nip']] ?? '',
                'Email' => $userName,
                'Nama Lengkap' => $user[$userCols['nama_lengkap']] ?? '',
                'Status Akun' => $user[$userCols['status_akun']] ?? '',
                'Role' => $user[$userCols['role']] ?? '',
                'Last Login' => $user[$userCols['last_login']] ?? '',
                'Last Seen' => $user[$userCols['last_seen']] ?? '',
                'Status Review' => $status,
                'Remarks' => $remark
            ];
        }
    
        return $results;
    }
    
    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');
     
        fputcsv($output, ['User ID', 'NIP', 'Email', 'Nama Lengkap', 'Status Akun', 'Role', 'Last Login', 'Last Seen', 'Status Review', 'Remarks']);
    
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