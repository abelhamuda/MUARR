<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="flex">
        <!-- Sidebar -->
        <div class="w-64 h-full bg-gray-800 text-white p-6">
            <nav class="space-y-2">
                <a href="{{ route('home') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Home</a>
                <a href="{{ route('sso.process') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">SSO App</a>
                <a href="{{ route('risk_app') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Risk App</a>
                <a href="{{ route('alphabit') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Alphabit</a>
                <a href="{{ route('icore') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Icore</a>
                <a href="{{ route('gitlab') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Gitlab</a>
                <a href="{{ route('adexchange') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">AD & Exchange</a>
                <a href="{{ route('officeautomation') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Office Automation</a>
                <a href="{{ route('jira') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Jira</a>
                <a href="{{ route('omnix') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Omnix</a>
                <a href="{{ route('rtgs') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">RTGS</a>
                <a href="{{ route('sensordata') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Sensor Data</a>
                <a href="{{ route('landsat') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Landsat</a>
                <a href="{{ route('magic') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Magic Cube</a>
                <a href="{{ route('jumpserver') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Jumpserver</a>
                <a href="{{ route('tableau') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Tableau</a>
                <a href="{{ route('ipscape') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">IP Scape</a>
                <a href="{{ route('medallion') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Medallion</a>
                <a href="{{ route('superset') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Superset</a>
                <a href="{{ route('zoom') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Zoom</a>
                <a href="{{ route('eproc') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Eproc</a>
                <a href="{{ route('collection') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Collection Console</a>
                <a href="{{ route('antasena') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Antasena</a>
                <a href="{{ route('sknbi') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">SKN BI</a>
                <a href="{{ route('other.app') }}" class="block text-blue-300 hover:bg-gray-700 px-3 py-2 rounded">Other APP</a>
            </nav>
        </div>

        <div class="flex-1 p-8">
            <main>
                @yield('content')
            </main>
        </div>
    </div>
</body>
</html>
