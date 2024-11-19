<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AntasenaController extends Controller
{
    public function antasena(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('antasena');
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
                    case ($columnName === 'full name'):
                        $columns['full_name'] = $index;
                        break;
    
                    case (preg_match('/unnamed:\s?1/i', $columnName)):
                        $columns['id_pengguna'] = $index;
                        break;
    
                    case (preg_match('/unnamed:\s?2/i', $columnName)):
                        $columns['cabang'] = $index;
                        break;
    
                    case (preg_match('/unnamed:\s?3/i', $columnName)):
                        $columns['jangka_waktu'] = $index;
                        break;
    
                    case (preg_match('/unnamed:\s?4/i', $columnName)):
                        $columns['status'] = $index;
                        break;
    
                    case (preg_match('/unnamed:\s?5/i', $columnName)):
                        $columns['otoritas'] = $index;
                        break;

                    // case ($columnName === 'id pengguna'):
                    //     $columns['id_pengguna'] = $index;
                    //     break;

                    // case ($columnName === 'cabang'):
                    //     $columns['cabang'] = $index;
                    //     break;

                    // case ($columnName === 'jangka waktu / kedaluarsa'):
                    //     $columns['jangka_waktu'] = $index;
                    //     break;

                    // case (preg_match('/status/i', $columnName)):
                    //     $columns['status'] = $index;
                    //     break;

                        
                    // case ($columnName === 'otoritas'):
                    //     $columns['otoritas'] = $index;
                    //     break;
                }
            }
    
            if (!isset($columns['full_name']) && !isset($columns['id_pengguna'])) {
                throw new \Exception('No "Full Name" & "Name" column found in the CSV file.');
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
            $status = 'Resign';
            $remark = 'Disable';
    
            if (isset($userCols['id_pengguna']) && is_array($user)) {
                $userName = $this->getNameFromRecord($user, $userCols['id_pengguna']);
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
                'ID Pengguna' => $userName,
                'Cabang' => $user[$userCols['cabang']] ?? '',
                'Jangka Waktu' => $user[$userCols['jangka_waktu']] ?? '',
                'Status Akun' => $user[$userCols['status']] ?? '',
                'Otoritas' => $user[$userCols['otoritas']] ?? '',
                'Status' => $status,
                'Remarks' => $remark
            ];
        }
    
        return $results;
    }
    
    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');
    
        fputcsv($output, ['ID Pengguna', 'Cabang', 'Jangka Waktu', 'Status Akun', 'Otoritas', 'Status', 'Remarks']);
    
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

        return $similarity >= 0.4;
    }
}