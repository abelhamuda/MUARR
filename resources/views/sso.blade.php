@extends('layout')

@section('title', 'SSO Process')

@section('content')
    <h1 class="text-3xl font-bold mb-1">SSO Process</h1>

    {{-- Clickable SSO Information --}}
    <h2 id="sso-info" class="font-regular mb-5 cursor-pointer text-blue-600 hover:underline">
        SSO Application Information
    </h2>

    {{-- Error Handling: Display an alert if there is an error --}}
    @if (session('error'))
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            alert('{!! session('error') !!}');
        });
    </script>
    @endif

    <form action="{{ route('sso.process') }}" method="POST" enctype="multipart/form-data" class="bg-white shadow-md rounded px-8 pt-6 pb-8 mb-4">
        @csrf
        <div class="mb-4">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="active_employees">
                Active Employee List
            </label>
            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                   id="active_employees" 
                   type="file" 
                   name="active_employees" 
                   accept=".csv,.txt" required>
        </div>

        <div class="mb-6">
            <label class="block text-gray-700 text-sm font-bold mb-2" for="application_users">
                Application User List
            </label>
            <input class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" 
                   id="application_users" 
                   type="file" 
                   name="application_users[]" 
                   accept=".csv,.txt" 
                   multiple>
        </div>

        <div class="mb-6">
            <table class="min-w-full bg-white border border-gray-300 rounded-lg shadow">
                <thead class="bg-gray-200">
                    <tr>
                        <th class="py-2 px-4 border-b text-left text-gray-600">File Name</th>
                        <th class="py-2 px-4 border-b text-left text-gray-600">Actions</th>
                    </tr>
                </thead>
                <tbody id="file-list-body">
                </tbody>
            </table>
        </div>

        <div class="flex items-center justify-between">
            <button class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline" 
                    type="submit">
                Process SSO
            </button>
        </div>
    </form>

    <script>
        document.getElementById('sso-info').addEventListener('click', function () {
            alert('The SSO Application includes: \n- BNC Account Registration \n- BNC Debt Collection Agency \n- BNC Dukcapil Reporting \n- BNC OJK BOX \n- BNC Reporting \n- BNC Signature Specimen \n- BNC SLIK \n- BNC SVC \n- BNC Insurance \n- BNC Portal \n- BNC PPATK/APUPPT \n- SSO AUTH');
        });

        const input = document.getElementById('application_users');
        let files = [];

        input.addEventListener('change', function (event) {
            const fileList = Array.from(event.target.files);
            const fileTableBody = document.getElementById('file-list-body');

            files = files.concat(fileList);

            renderFileList();
        });

        function renderFileList() {
            const fileTableBody = document.getElementById('file-list-body');
            fileTableBody.innerHTML = '';

            files.forEach((file, index) => {
                const row = document.createElement('tr');

                const fileNameCell = document.createElement('td');
                fileNameCell.className = 'py-2 px-4 border-b';
                fileNameCell.textContent = file.name;

                const actionCell = document.createElement('td');
                actionCell.className = 'py-2 px-4 border-b';

                const deleteButton = document.createElement('button');
                deleteButton.className = 'bg-red-500 hover:bg-red-700 text-white font-bold py-1 px-3 rounded';
                deleteButton.textContent = 'Delete';
                deleteButton.type = 'button';

                deleteButton.addEventListener('click', function () {
                    files.splice(index, 1);
                    renderFileList();
                    updateFileInput();
                });

                actionCell.appendChild(deleteButton);
                row.appendChild(fileNameCell);
                row.appendChild(actionCell);

                fileTableBody.appendChild(row);
            });
        }

        function updateFileInput() {
            const dataTransfer = new DataTransfer();

            files.forEach(file => dataTransfer.items.add(file));

            input.files = dataTransfer.files;
        }
    </script>
@endsection