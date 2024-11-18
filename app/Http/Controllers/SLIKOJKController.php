<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SLIKOJKController extends Controller
{
    public function slikojk(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('slikojk');
        }
    
        $request->validate([
            'active_employees' => 'required|file|mimes:csv,txt',
            'application_users' => 'required|array',
            'application_users.*' => 'required|file|mimes:csv,txt',
        ]);
    
        try {
            $activeEmployees = $this->parseCSV($request->file('active_employees')->getPathname());
    
            $zip = new \ZipArchive();
            $zipFilename = 'SLIK-OJK_review.zip';
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
                
                    case (preg_match('/nama\s?pengguna/i', $columnName)):
                        $columns['nama_pengguna'] = $index;
                        break;
                
                    case (preg_match('/nomor\s?telepon/i', $columnName)):
                        $columns['nomor_telepon'] = $index;
                        break;
                
                    case (preg_match('/jenis\s?pengguna/i', $columnName)):
                        $columns['jenis_pengguna'] = $index;
                        break;
                
                    case (preg_match('/nama\s?ljk/i', $columnName)):
                        $columns['nama_ljk'] = $index;
                        break;
                
                    case (preg_match('/nama\s?cabang/i', $columnName)):
                        $columns['nama_cabang'] = $index;
                        break;
                
                    case (preg_match('/nama\s?peran/i', $columnName)):
                        $columns['nama_peran'] = $index;
                        break;
                
                    //test with "preg_match"    
                    case (preg_match('/status\s?aktif/i', $columnName)):
                        $columns['status_aktif'] = $index;
                        break;
             
                    // //test without "preg_match"                 
                    // case ($columnName === 'status aktif'):
                    //     $columns['status_aktif'] = $index;
                    //     break; 

                    // case (preg_match('/status\?aktif/i', $columnName)):
                    //     $columns['status_aktif'] = $index;
                    //     break;
                
                    case (preg_match('/dibuat\s?oleh/i', $columnName)):
                        $columns['dibuat_oleh'] = $index;
                        break;
                        
                    case (preg_match('/dibuat\s?tgl/i', $columnName)):
                        $columns['dibuat_tgl'] = $index;
                        break;
                        
                    case (preg_match('/diperbaharui\s?oleh/i', $columnName)):
                        $columns['diperbaharui_oleh'] = $index;
                        break;
                        
                    case (preg_match('/diperbaharui\s?tgl/i', $columnName)):
                        $columns['diperbaharui_tgl'] = $index;
                        break;
                        
                    case (preg_match('/login\s?terakhir/i', $columnName)):
                        $columns['login_terakhir'] = $index;
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
                'Nama Pengguna' => $user[$userCols['nama_pengguna']] ?? '',
                'Nomor Telepon' => $user[$userCols['nomor_telepon']] ?? '',
                'Jenis Pengguna' => $user[$userCols['jenis_pengguna']] ?? '',
                'Nama LJK' => $user[$userCols['nama_ljk']] ?? '',
                'Nama Cabang' => $user[$userCols['nama_cabang']] ?? '',
                'Nama Peran' => $user[$userCols['nama_peran']] ?? '',
                'Status Aktif' => $user[$userCols['status_aktif']] ?? '',
                'Dibuat Oleh' => $user[$userCols['dibuat_oleh']] ?? '',
                'Dibuat Tgl' => $user[$userCols['dibuat_tgl']] ?? '',
                'Diperbaharui Oleh' => $user[$userCols['diperbaharui_oleh']] ?? '',
                'Diperbaharui Tgl' => $user[$userCols['diperbaharui_tgl']] ?? '',
                'Login Terakhir' => $user[$userCols['login_terakhir']] ?? '',
                'Status Review' => $status,
                'Remarks' => $remark
            ];
        }
    
        return $results;
    }
    
    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');
     
        fputcsv($output, ['Login ID', 'Nama Pengguna', 'Nomor Telepon', 'Jenis Pengguna', 'Nama LJK', 'Nama Cabang', 'Nama Peran', 'Status Aktif', 'Dibuat Oleh', 'Dibuat Tgl', 'Diperbaharui Oleh', 'Diperbaharui Tgl', 'Login Terakhir', 'Status Review',  'Remarks']);
    
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