<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title')</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="container mx-auto p-8">
        <nav class="mb-4">
            <ul class="flex space-x-4">
                <li><a href="{{ route('home') }}" class="text-blue-500">Home</a></li>
                <li><a href="{{ route('sso.process') }}" class="text-blue-500">SSO App</a></li>
                <li><a href="{{ route('alphabit') }}" class="text-blue-500">Alphabit</a></li>
                <li><a href="{{ route('icore') }}" class="text-blue-500">Icore</a></li>
                <li><a href="{{ route('other.app') }}" class="text-blue-500">Other APP</a></li>
            </ul>
        </nav>
        <main>
            @yield('content')
        </main>
    </div>
</body>
</html>
