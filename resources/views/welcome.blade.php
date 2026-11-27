<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickScanPos</title>
</head>
<body>
    <h1>QuickScanPos</h1>
    <p>Welcome to the Laravel POS application.</p>
    @auth
        <a href="{{ route('customers.index') }}">Manage Customers</a>
    @else
        <a href="{{ route('login') }}">Log in</a>
        <a href="{{ route('login') }}">Sign in</a>
    @endauth
</body>
</html>
