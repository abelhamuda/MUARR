<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SKNBIController extends Controller
{
    public function sknbi(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('sknbi');
        }
    
        $request->validate([
            'active_employees' => 'required|file|mimes:csv,txt',
            'application_users' => 'required|array',
            'application_users.*' => 'required|file|mimes:csv,txt',
        ]);
    
        try {
            $activeEmployees = $this->parseCSV($request->file('active_employees')->getPathname());
    
            $zip = new \ZipArchive();
            $zipFilename = 'SKN-BI_review.zip';
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
                    case (preg_match('/trans\s?password\s?status/i', $columnName)):
                        $columns['trans_password_status'] = $index;
                        break;
                
                    case ($columnName === 'full name'):
                        $columns['full_name'] = $index;
                        break;
                
                    case (preg_match('/nama\s?pengguna/i', $columnName)):
                        $columns['nama_pengguna'] = $index;
                        break;
                
                    case (preg_match('/nama\s?lengkap/i', $columnName)):
                        $columns['nama_lengkap'] = $index;
                        break;
                
                    case (preg_match('/status/i', $columnName)):
                        $columns['status'] = $index;
                        break;
                
                    case ($columnName === 'jabatan'):
                        $columns['jabatan'] = $index;
                        break;
                
                    case (preg_match('/satuan\s?kerja/i', $columnName)):
                        $columns['satuan_kerja'] = $index;
                        break;
                
                    case (preg_match('/grup\s?pengguna/i', $columnName)):
                        $columns['grup_pengguna'] = $index;
                        break;
                
                    case (preg_match('/grup\s?region/i', $columnName)):
                        $columns['grup_region'] = $index;
                        break;
                }
                
            }
    
            if (!isset($columns['full_name']) && !isset($columns['nama_lengkap'])) {
                throw new \Exception('No "Full Name" & "Nama Lengkap" column found in the CSV file.');
            }
    
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rows[] = $data;
            }
        } catch (\Exception $e) {
            // Log the exception or handle it as needed
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
            $userstats = 'Resign';
            $remark = 'Disable';
    
            if (isset($userCols['nama_lengkap']) && is_array($user)) {
                $userName = $this->getNameFromRecord($user, $userCols['nama_lengkap']);
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
                    $userstats = 'Active';
                    $remark = 'Keep';
                    break;
                }
            }
    
            $results[] = [
                'Nama Pengguna' => $user[$userCols['nama_pengguna']] ?? '',
                'Nama Lengkap' => $userName,
                'Status Akun' => $user[$userCols['status']] ?? '',
                'Jabatan' => $user[$userCols['jabatan']] ?? '',
                'Satuan Kerja' => $user[$userCols['satuan_kerja']] ?? '',
                'Trans Password Status' => $user[$userCols['trans_password_status']] ?? '',
                'Grup Pengguna' => $user[$userCols['grup_pengguna']] ?? '',
                'Grup Region' => $user[$userCols['grup_region']] ?? '',
                'User Stats' => $userstats,
                'Remarks' => $remark
            ];
        }
    
        return $results;
    }
    
    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');
     
        fputcsv($output, ['Nama Pengguna', 'Nama Lengkap', 'Status Akun', 'Jabatan', 'Satuan Kerja', 'Trans Password Status', 'Grup Pengguna', 'Grup Region', 'User Stats', 'Remarks']);
    
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

        return $similarity >= 0.7;
    }
}