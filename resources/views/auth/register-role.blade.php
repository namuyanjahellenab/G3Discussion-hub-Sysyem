<x-auth-layout>
    <div class="auth-card" style="max-width: 450px; margin: 0 auto;">
        <!-- Header -->
        <h1 style="font-size: 20px; margin-bottom: 8px;">Register</h1>
        <p class="subtitle">Choose your role to get started</p>

        <!-- Display Errors -->
        @if ($errors->any())
            <div class="alert-error">
                <strong>Error:</strong>
                @foreach ($errors->all() as $error)
                    <div>{{ $error }}</div>
                @endforeach
            </div>
        @endif

        <!-- Role Selection Form -->
        <form action="{{ route('register.role.store') }}" method="POST" id="roleForm">
            @csrf

            <!-- Role Label -->
            <label style="display: block; font-weight: 600; margin-bottom: 15px;">I am a:</label>

            <!-- Role Buttons -->
            <button 
                type="button" 
                class="role-button @if(old('role') === 'student') active @endif" 
                onclick="selectRole('student', this)"
                data-role="student"
            >
                <span class="role-button-icon">🎓</span>
                <span>Student</span>
                <span class="role-button-checkmark">✓</span>
            </button>

            <button 
                type="button" 
                class="role-button @if(old('role') === 'lecturer') active @endif" 
                onclick="selectRole('lecturer', this)"
                data-role="lecturer"
            >
                <span class="role-button-icon">🎤</span>
                <span>Lecturer</span>
                <span class="role-button-checkmark">✓</span>
            </button>

            <button 
                type="button" 
                class="role-button @if(old('role') === 'administrator') active @endif" 
                onclick="selectRole('administrator', this)"
                data-role="administrator"
            >
                <span class="role-button-icon">⚙️</span>
                <span>Administrator</span>
                <span class="role-button-checkmark">✓</span>
            </button>

            <!-- Hidden input for selected role -->
            <input type="hidden" id="selectedRole" name="role" value="{{ old('role', '') }}">

            @error('role')
                <div class="field-error" style="display: block;">{{ $message }}</div>
            @enderror

            <!-- Next Button -->
            <button 
                type="button" 
                class="btn btn-primary" 
                id="nextBtn"
                onclick="submitForm()"
                style="margin-top: 20px;"
            >
                NEXT >
            </button>
        </form>

        <!-- Sign In Link -->
        <div class="text-center">
            Already have an account? <a href="{{ route('login') }}">Sign in</a>
        </div>
    </div>

    <script>
        // Track selected role
        let selectedRoleValue = '{{ old('role', '') }}';

        function selectRole(role, button) {
            // Remove active class from all buttons
            document.querySelectorAll('.role-button').forEach(btn => {
                btn.classList.remove('active');
            });

            // Add active class to clicked button
            button.classList.add('active');

            // Update hidden input
            document.getElementById('selectedRole').value = role;
            selectedRoleValue = role;
        }

        function submitForm() {
            if (!selectedRoleValue) {
                alert('Please select a role to continue.');
                return;
            }
            document.getElementById('roleForm').submit();
        }

        // Allow Enter key to submit
        document.addEventListener('keypress', function(e) {
            if (e.key === 'Enter' && selectedRoleValue) {
                submitForm();
            }
        });

        // Initialize selected role if any
        if (selectedRoleValue) {
            const activeBtn = document.querySelector(`[data-role="${selectedRoleValue}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
        }
    </script>
</x-auth-layout>
