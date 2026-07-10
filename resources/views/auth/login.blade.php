<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Login — B2B CRM Master</title>
    <style>
        body { font-family: system-ui, sans-serif; background: #1e3a5f; min-height: 100vh; display: flex; align-items: center; justify-content: center; margin: 0; }
        .box { background: #fff; padding: 32px; border-radius: 10px; width: 100%; max-width: 380px; }
        label { display: block; margin-top: 12px; font-weight: 600; }
        input { width: 100%; padding: 10px; margin-top: 4px; border: 1px solid #ccc; border-radius: 6px; }
        button { margin-top: 20px; width: 100%; padding: 10px; background: #1e3a5f; color: #fff; border: 0; border-radius: 6px; cursor: pointer; }
        .error { color: #c00; font-size: 14px; margin-top: 8px; }
    </style>
</head>
<body>
<div class="box">
    <h1 style="margin:0 0 8px">Master Portal</h1>
    <p style="color:#666;margin:0 0 16px">Manage companies & tenant databases</p>
    <form method="POST" action="{{ route('login', [], false) }}">
        @csrf
        <label>Email</label>
        <input type="email" name="email" value="{{ old('email') }}" required autofocus>
        @error('email')<div class="error">{{ $message }}</div>@enderror
        <label>Password</label>
        <input type="password" name="password" required>
        <label><input type="checkbox" name="remember"> Remember me</label>
        <button type="submit">Sign in</button>
    </form>
</div>
</body>
</html>
