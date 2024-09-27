<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AlphabitController extends Controller
{
    public function alphabit(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('alphabit'); // Alphabit Process form
        }
    
        $request->validate([
            'active_employees' => 'required|file|mimes:csv,txt',
            'application_users' => 'required|array',
            'application_users.*' => 'file|mimes:csv,txt',
        ]);
    
        try {
            $activeEmployees = $this->parseCSV($request->file('active_employees')->getPathname(), 'employee');
    
            $zip = new \ZipArchive();
            $zipFilename = 'alphabit_comparison_reports.zip';
            $zipPath = storage_path($zipFilename);
    
            if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) === TRUE) {
                foreach ($request->file('application_users') as $applicationFile) {
                    $applicationUsers = $this->parseCSV($applicationFile->getPathname(), 'application');
                    $results = $this->compareData($activeEmployees, $applicationUsers);
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

    private function parseCSV($filepath, $fileType)
    {
        $rows = [];
        $columns = [];
    
        if (($handle = fopen($filepath, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
    
            foreach ($header as $index => $columnName) {
                $columnName = strtolower(trim($columnName));
                if ($fileType === 'employee' && preg_match('/name/i', $columnName)) {
                    $columns['name'] = $index;
                } elseif ($fileType === 'application') {
                    if ($columnName === 'opdesc') {
                        $columns['opdesc'] = $index;
                    } elseif ($columnName === 'opcode') {
                        $columns['opcode'] = $index;
                    } elseif ($columnName === 'opprog') {
                        $columns['opprog'] = $index;
                    } elseif ($columnName === 'opautu') {
                        $columns['opautu'] = $index;
                    } elseif ($columnName === 'opdtlc') {
                        $columns['opdtlc'] = $index;
                    }
                }
            }
    
            if ($fileType === 'employee' && !isset($columns['name'])) {
                throw new \Exception('No "Name" column found in the employee CSV file.');
            } elseif ($fileType === 'application' && !isset($columns['opdesc'])) {
                throw new \Exception('No "OPDESC" column found in the application CSV file.');
            }

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $rows[] = $data;
            }
            fclose($handle);
        }
    
        return ['rows' => $rows, 'columns' => $columns];
    }
    
    private function compareData($activeEmployees, $applicationUsers)
    {
        $results = [];
        $activeRows = $activeEmployees['rows'];
        $activeNameCol = $activeEmployees['columns']['name'];
        $userRows = $applicationUsers['rows'];
        $userCols = $applicationUsers['columns'];
    
        foreach ($userRows as $user) {
            $status = 'Resign';
            $remark = 'Disable'; 
            $userOpdesc = $user[$userCols['opdesc']];
    
            foreach ($activeRows as $employee) {
                $employeeName = $employee[$activeNameCol];
                if ($this->compareNames($userOpdesc, $employeeName)) {
                    $status = 'Active';
                    $remark = 'Keep'; 
                    break;
                }
            }
    
            $results[] = [
                'OPCODE' => $user[$userCols['opcode']] ?? '',
                'OPPROG' => $user[$userCols['opprog']] ?? '',
                'OPAUTU' => $user[$userCols['opautu']] ?? '',
                'OPDTLC' => $user[$userCols['opdtlc']] ?? '',
                'OPDESC' => $userOpdesc,
                'Status' => $status,
                'Remarks' => $remark
            ];
        }
    
        return $results;
    }
    
    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');
    
        fputcsv($output, ['OPCODE', 'OPPROG', 'OPAUTU', 'OPDTLC', 'OPDESC', 'Status', 'Remarks']);
    
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
    
        rewind($output);
        $csvContent = stream_get_contents($output);
        fclose($output);
    
        return $csvContent;
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