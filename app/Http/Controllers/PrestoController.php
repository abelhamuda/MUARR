<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PrestoController extends Controller
{
    public function presto(Request $request)
    {
        if ($request->isMethod('get')) {
            return view('presto');
        }

        $request->validate([
            'active_employees' => 'required|file|mimes:csv,txt',
            'application_users' => 'required|array',
            'application_users.*' => 'required|file|mimes:csv,txt',
        ]);

        try {
            $activeEmployees = $this->parseCSV($request->file('active_employees')->getPathname());

            $zip = new \ZipArchive();
            $zipFilename = 'Presto_review.zip';
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

                    case (preg_match('/permission\s?subject/i', $columnName)):
                        $columns['permission_subject'] = $index;
                        break;

                    case (preg_match('/resource\s?name/i', $columnName)):
                        $columns['resource_name'] = $index;
                        break;

                    case (preg_match('/behavior/i', $columnName)):
                        $columns['behavior'] = $index;
                        break;

                    case (preg_match('/allow/i', $columnName)):
                        $columns['allow'] = $index;
                        break;

                    case (preg_match('/operation/i', $columnName)):
                        $columns['operation'] = $index;
                        break;
                }
            }

            if (!isset($columns['email']) && !isset($columns['permission_subject'])) {
                throw new \Exception('No "Email" & "Permission Subject" column found in the CSV file.');
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

            if (isset($userCols['permission_subject']) && is_array($user)) {
                $userName = $this->normalizeName($this->getNameFromRecord($user, $userCols['permission_subject']));
            } else {
                continue;
            }

            foreach ($activeRows as $employee) {
                if (is_array($employee) && isset($activeNameCol)) {
                    $employeeName = $this->normalizeName($this->getNameFromRecord($employee, $activeNameCol));
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
                'Permission Subject' => $userName,
                'Resource Name' => $user[$userCols['resource_name']] ?? '',
                'Behavior' => $user[$userCols['behavior']] ?? '',
                'Allow' => $user[$userCols['allow']] ?? '',
                'Operation' => $user[$userCols['operation']] ?? '',
                'Status' => $status,
                'Remarks' => $remark
            ];
        }

        return $results;
    }

    private function generateCSV($data)
    {
        $output = fopen('php://temp', 'w');

        fputcsv($output, ['Permission Subject', 'Resource Name', 'Behavior', 'Allow', 'Operation', 'Status', 'Remarks']);

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

    private function normalizeName($name)
    {
        // Normalize by removing domain parts and making it lowercase
        $name = strtolower(trim($name));
        return explode('@', $name)[0];
    }

    private function compareNames($name1, $name2)
    {
        if ($name1 === $name2) {
            return true;
        }

        $similarity = 1 - (levenshtein($name1, $name2) / max(strlen($name1), strlen($name2)));

        return $similarity >= 0.7;
    }
}
