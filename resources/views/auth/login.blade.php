<x-auth-layout>
    <div class="auth-card" style="max-width: 400px; margin: 0 auto;">
        <!-- Header -->
        <h1 style="font-size: 24px; margin-bottom: 8px;">Discussion Hub</h1>
        <p class="subtitle">Welcome back! Please login to your account.</p>

        <!-- Display Errors -->
        @if ($errors->any())
            <div class="alert-error">
                <strong>Login Failed:</strong>
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <!-- Login Form -->
        <form action="{{ route('login') }}" method="POST" id="loginForm">
            @csrf

            <!-- Email Address -->
            <div class="form-group">
                <label for="email">Email Address:</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="user@example.com"
                    value="{{ old('email') }}"
                    required
                >
                @error('email')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <!-- Password -->
            <div class="form-group">
                <label for="password">Password:</label>
                <div class="password-field-wrapper">
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        placeholder="••••••••"
                        required
                    >
                    <button 
                        type="button" 
                        class="password-toggle" 
                        id="passwordToggle"
                        onclick="togglePassword()"
                    >
                        👁️
                    </button>
                </div>
                @error('password')
                    <div class="field-error">{{ $message }}</div>
                @enderror
            </div>

            <!-- Remember Me & Forgot Password -->
            <div class="remember-section">
                <label class="checkbox-label" style="display: flex; align-items: center; gap: 8px; margin: 0;">
                    <input type="checkbox" name="remember" id="remember">
                    Remember me
                </label>
                <a href="#" class="forgot-password-link">Forgot Password?</a>
            </div>

            <!-- Login Button -->
            <button 
                type="submit" 
                class="btn btn-primary" 
                id="loginBtn"
                style="margin-bottom: 20px;"
            >
                <span id="btnText">LOGIN</span>
                <span id="btnLoader" class="loader" style="display: none;"></span>
            </button>
        </form>

        <!-- Register Link -->
        <div class="text-center">
            
            Don't have an account? <a href="{{ route('register') }}">Register here</a>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordField = document.getElementById('password');
            const toggleBtn = document.getElementById('passwordToggle');
            
            if (passwordField.type === 'password') {
                passwordField.type = 'text';
                toggleBtn.textContent = '👁️‍🗨️';
            } else {
                passwordField.type = 'password';
                toggleBtn.textContent = '👁️';
            }
        }

        // Form submission handler
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const btn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            const btnLoader = document.getElementById('btnLoader');
            
            btn.disabled = true;
            btnText.style.display = 'none';
            btnLoader.style.display = 'inline-block';
        });
    </script>
</x-auth-layout>
