<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign in · DCS-SMS</title>
    <link rel="icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ filemtime(public_path('favicon.ico')) }}">
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('favicon-32x32.png') }}?v={{ filemtime(public_path('favicon-32x32.png')) }}">
    @include('partials.pwa_head')
    <link rel="stylesheet" href="{{ asset('css/login.css') }}?v={{ filemtime(public_path('css/login.css')) }}">
</head>

<body>
    <main class="signin">
        <header class="signin-head">
            @if ($logo = groupLogoUrl())
                <img class="signin-logo" src="{{ $logo }}" alt="Deus Caritas Schools" width="112" height="112">
            @endif
            <h1 class="signin-name">DEUS CARITAS SCHOOLS</h1>
            <p class="signin-full">School Management System</p>
        </header>

        <section class="signin-card" aria-labelledby="signin-title">
            <h2 class="signin-title" id="signin-title">Sign in</h2>

            @if (session('status'))
                <div class="signin-alert" role="alert">{{ session('status') }}</div>
            @endif

            @if (session('error'))
                <div class="signin-alert" role="alert">{{ session('error') }}</div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf

                <div class="signin-field">
                    <label class="signin-label" for="username">Username or email</label>
                    <input id="username" type="text" name="username" value="{{ old('username') }}"
                        class="signin-input @error('username') is-invalid @enderror"
                        autocomplete="username" required autofocus
                        @error('username') aria-invalid="true" aria-describedby="username-error" @enderror>
                    @error('username')
                        <div id="username-error" class="signin-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="signin-field">
                    <div class="signin-label-row">
                        <label class="signin-label" for="password">Password</label>
                        <button type="button" class="signin-show" id="toggle-password" aria-controls="password" aria-pressed="false">Show</button>
                    </div>
                    <input id="password" type="password" name="password"
                        class="signin-input @error('password') is-invalid @enderror"
                        autocomplete="current-password" required
                        @error('password') aria-invalid="true" aria-describedby="password-error" @enderror>
                    @error('password')
                        <div id="password-error" class="signin-error" role="alert">{{ $message }}</div>
                    @enderror
                </div>

                <div class="signin-row">
                    <label class="signin-remember" for="remember">
                        <input type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                        Keep me signed in
                    </label>

                    @if (Route::has('password.request'))
                        <a class="signin-link" href="{{ route('password.request') }}">Forgot password?</a>
                    @endif
                </div>

                <button type="submit" class="signin-submit">Sign in</button>
            </form>
        </section>

      

        <p class="signin-copyright">&copy; {{ date('Y') }} {{ groupName() }}</p>
    </main>

    <script>
        (function () {
            var toggle = document.getElementById('toggle-password');
            var input = document.getElementById('password');

            toggle.addEventListener('click', function () {
                var reveal = input.type === 'password';
                input.type = reveal ? 'text' : 'password';
                toggle.textContent = reveal ? 'Hide' : 'Show';
                toggle.setAttribute('aria-pressed', reveal ? 'true' : 'false');
            });
        })();
    </script>

    @include('partials.pwa_register')
</body>

</html>
