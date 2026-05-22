<!doctype html>
<title>Set Password</title>
<style>
    * { box-sizing: border-box; }
    body { margin: 0; font-family: Arial, Helvetica, sans-serif; background: #f3f4f6; color: #111827; }
    .wrap { min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; }
    .card { width: 100%; max-width: 460px; background: #fff; border: 1px solid #d1d5db; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); }
    .head { padding: 22px 24px; border-bottom: 1px solid #e5e7eb; background: #111827; color: #fff; }
    .head h1 { margin: 0; font-size: 20px; line-height: 28px; }
    .head p { margin: 6px 0 0; font-size: 13px; color: #d1d5db; }
    .body { padding: 24px; }
    label { display: block; margin-bottom: 6px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .08em; color: #4b5563; }
    input { width: 100%; height: 42px; border: 1px solid #d1d5db; padding: 0 12px; font-size: 14px; outline: none; }
    input:focus { border-color: #111827; }
    .field { margin-bottom: 16px; }
    .error { margin: 0 0 16px; padding: 10px 12px; border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; font-size: 13px; line-height: 20px; }
    .help { margin: 8px 0 0; font-size: 12px; color: #6b7280; line-height: 18px; }
    button { width: 100%; height: 44px; border: 1px solid #111827; background: #111827; color: #fff; font-size: 14px; font-weight: 700; cursor: pointer; }
    button:hover { background: #000; }
    .user { margin-bottom: 18px; padding: 12px; background: #f9fafb; border: 1px solid #e5e7eb; font-size: 13px; line-height: 20px; color: #374151; }
</style>
</head>
<body>
<div class="wrap">
    <div class="card">
        <div class="head">
            <h1>Set your password</h1>
            <p>Create your password to activate your account.</p>
        </div>

        <div class="body">
            <div class="user">
                <strong>{{ $user->full_name }}</strong><br>
                {{ $user->email }}
            </div>

            @if ($errors->any())
                <div class="error">
                    @foreach ($errors->all() as $error)
                        <div>{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <form method="POST" action="{{ route('auth.password.setup.complete', ['token' => $token]) }}">
                @csrf

                <div class="field">
                    <label for="password">Password</label>
                    <input id="password" name="password" type="password" autocomplete="new-password" required autofocus>
                    <p class="help">Use at least 8 characters, including letters and numbers.</p>
                </div>

                <div class="field">
                    <label for="password_confirmation">Confirm Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                </div>

                <button type="submit">Save Password</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
