<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class EprocController extends Controller
{
    public function eproc(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('eproc');
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
                    case ($columnName === 'email'):
                        $columns['email'] = $index;
                        break;
    
                    // case (preg_match('/e-mail/i', $columnName)):
                    //     $columns['e-mail'] = $index;
                    //     break;

                    case ($columnName === 'nik'):
                        $columns['nik'] = $index;
                        break;

                    case ($columnName === 'e-mail'):
                        $columns['e-mail'] = $index;
                        break;

                    case ($columnName === 'nama'):
                        $columns['nama'] = $index;
                        break;

                    case ($columnName === 'telepon'):
                        $columns['telepon'] = $index;
                        break;

                    case ($columnName === 'jabatan'):
                        $columns['jabatan'] = $index;
                        break;

                    case ($columnName === 'puk1'):
                        $columns['puk1'] = $index;
                        break;

                    case ($columnName === 'kode cost center'):
                        $columns['kode_cost_center'] = $index;
                        break;

                    case ($columnName === 'kode cabang'):
                        $columns['kode_cabang'] = $index;
                        break;

                    case ($columnName === 'aktif'):
                        $columns['aktif'] = $index;
                        break;
                }
            }
    
            if (!isset($columns['email']) && !isset($columns['e-mail'])) {
                throw new \Exception('No "Email"  column found in the CSV file.');
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
    
            if (isset($userCols['e-mail']) && is_array($user)) {
                $userName = $this->getNameFromRecord($user, $userCols['e-mail']);
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
                'NIK' => $user[$userCols['nik']] ?? '',
                'Nama' => $user[$userCols['nama']] ?? '',
                'Email' => $userName,
                'Telepon' => $user[$userCols['telepon']] ?? '',
                'Jabatan' => $user[$userCols['jabatan']] ?? '',
                'PUK1' => $user[$userCols['puk1']] ?? '',
                'Kode Cost Center' => $user[$userCols['kode_cost_center']] ?? '',
                'Kode Cabang' => $user[$userCols['kode_cabang']] ?? '',
                'Aktif' => $user[$userCols['aktif']] ?? '',
                'Status' => $status,
                'Remarks' => $remark
            ];
        }
    
        return $results;
    }
    
    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');
    
        fputcsv($output, ['NIK', 'Nama', 'Email', 'Telepon', 'Jabatan', 'PUK1', 'Kode Cost Center', 'Kode Cabang', 'Aktif', 'Status', 'Remarks']);
    
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
