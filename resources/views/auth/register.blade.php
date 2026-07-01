<x-auth-layout>
    <div class="two-column-layout" style="max-width: 1000px; margin: 0 auto; width: 100%;">
        <!-- LEFT COLUMN: CREATE AN ACCOUNT -->
        <div class="column">
            <!-- Header -->
            <div class="column-title" style="color: var(--primary-blue); font-size: 18px; margin-bottom: 8px;">CREATE AN ACCOUNT</div>
            <p class="subtitle" style="margin-bottom: 20px;">Join the Discussion Hub community today.</p>
            <!-- Selected Role Badge / Change Role -->
            @if (isset($role) && $role)
                <div style="margin-bottom:12px;">
                    <strong>Selected role:</strong>
                    <span style="display:inline-block; padding:4px 8px; margin-left:8px; background:#eef6ff; border-radius:6px;">{{ ucfirst($role) }}</span>
                    <a href="{{ route('register.role') }}" style="margin-left:12px; font-size:13px;">Change</a>
                </div>
            @else
                <div style="margin-bottom:12px;">
                    <a href="{{ route('register.role') }}">Choose a role</a> before continuing.
                </div>
            @endif

            <!-- Display Errors -->
            @if ($errors->any())
                <div class="alert-error" style="margin-bottom: 20px;">
                    <strong>Registration Failed:</strong>
                    @foreach ($errors->all() as $error)
                        <div style="font-size: 13px; margin-top: 4px;">{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <!-- Registration Form -->
            <form action="{{ route('register') }}" method="POST" id="registerForm">
                @csrf

                <!-- Full Name -->
                <div class="form-group">
                    <label for="full_name">Full Name:</label>
                    <input 
                        type="text" 
                        id="full_name" 
                        name="full_name" 
                        placeholder="Nakato Vannesah"
                        value="{{ old('full_name') }}"
                        required
                    >
                    @error('full_name')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Email Address -->
                <div class="form-group">
                    <label for="email">Email Address:</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        placeholder="nakatov@gmail.com"
                        value="{{ old('email') }}"
                        required
                    >
                    @error('email')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Username -->
                <div class="form-group">
                    <label for="username">Username:</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        placeholder="Nakato V"
                        value="{{ old('username') }}"
                        required
                    >
                    @error('username')
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
                            onkeyup="checkPasswordRequirements()"
                        >
                        <button 
                            type="button" 
                            class="password-toggle" 
                            id="passwordToggle"
                            onclick="togglePassword('password', 'passwordToggle')"
                        >
                            👁️
                        </button>
                    </div>
                    @error('password')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                    
                    <!-- Password Requirements -->
                    <div class="password-requirements">
                        <div class="requirement unmet" id="req-length">
                            <div class="requirement-icon">✓</div>
                            <span>At least 8 characters</span>
                        </div>
                        <div class="requirement unmet" id="req-uppercase">
                            <div class="requirement-icon">✓</div>
                            <span>Contains uppercase letter</span>
                        </div>
                        <div class="requirement unmet" id="req-lowercase">
                            <div class="requirement-icon">✓</div>
                            <span>Contains lowercase letter</span>
                        </div>
                        <div class="requirement unmet" id="req-number">
                            <div class="requirement-icon">✓</div>
                            <span>Contains number</span>
                        </div>
                        <div class="requirement unmet" id="req-special">
                            <div class="requirement-icon">✓</div>
                            <span>Contains special character (!@#$%^&*)</span>
                        </div>
                    </div>
                </div>

                <!-- Confirm Password -->
                <div class="form-group">
                    <label for="password_confirmation">Confirm Password:</label>
                    <div class="password-field-wrapper">
                        <input 
                            type="password" 
                            id="password_confirmation" 
                            name="password_confirmation" 
                            placeholder="••••••••"
                            required
                        >
                        <button 
                            type="button" 
                            class="password-toggle" 
                            id="passwordConfirmToggle"
                            onclick="togglePassword('password_confirmation', 'passwordConfirmToggle')"
                        >
                            👁️
                        </button>
                    </div>
                    @error('password_confirmation')
                        <div class="field-error">{{ $message }}</div>
                    @enderror
                </div>

                <!-- Status Badge -->
                <div class="status-badge">
                    <span>✓</span>
                    <span>STATUS: PENDING RULE ACCEPTANCE</span>
                </div>

                <!-- Hidden rules checkbox for validation -->
                <input type="hidden" id="rulesHidden" name="rules_accepted" value="0">
                <!-- Include selected role so it's visible on form submit (controller also reads session) -->
                <input type="hidden" name="role" value="{{ $role ?? '' }}">

                <!-- Accept Rules Button -->
                <button 
                    type="submit" 
                    class="btn btn-primary" 
                    id="submitBtn"
                    disabled
                    style="background: var(--light-gray); color: var(--text-gray); cursor: not-allowed; margin-top: 15px;"
                >
                    COMPLETE REGISTRATION
                </button>
            </form>
        </div>

        <!-- RIGHT COLUMN: PLATFORM RULES & GUIDELINES -->
        <div class="column rules-column">
            <div class="column-title" style="color: var(--dark-gray); font-size: 16px; margin-bottom: 15px;">PLATFORM RULES & GUIDELINES</div>

            <!-- Rules List -->
            <ol class="rules-list">
                <li>
                    <strong>Be Respectful:</strong> Harassment, hate speech, and personal attacks are strictly prohibited. Treat all members with courtesy and maintain a professional tone in all discussions.
                </li>
                <li>
                    <strong>No Spam:</strong> Do not post promotional content, redundant topics, or malicious links. Keep discussions relevant to the category and contribute meaningful value to the community.
                </li>
                <li>
                    <strong>Privacy:</strong> Do not share personal information or breach confidentiality.
                </li>
            </ol>

            <!-- Rules Checkbox -->
            <div class="checkbox-wrapper" style="margin-top: 20px;background-color: light blue ; padding: 15px; border-radius: 6px;">
    
                <input 
                    type="checkbox" 
                    id="rules_accepted" 
                    name="rules_agreement"
                    onchange="toggleSubmitButton()"
                >
                <label class="checkbox-label" for="rules_accepted">
                    I have read and agree to abide by the <span style="color:blue ;">Platform Rules and guidelines</span>.. I understand that Violations may result in account termination.
                </label>
            </div>

            <!-- Action Buttons -->
            <div class="button-group" style="margin-top: 30px;">
                <button 
                    type="submit" 
                    class="btn btn-primary" 
                    id="acceptRulesBtn"
                    onclick="document.getElementById('rulesHidden').value = '1'; document.getElementById('registerForm').submit();"
                    disabled
                    style="background: var(--blue); color: var(--white); cursor: not-allowed;"
                >
                    ACCEPT RULES 
                </button>
                <a href="{{ route('login') }}" class="btn btn-secondary">
                    DECLINE
                </a>
            </div>
        </div>
    </div>

    <script>
        // Password visibility toggle
        function togglePassword(fieldId, toggleBtnId) {
            const field = document.getElementById(fieldId);
            const btn = document.getElementById(toggleBtnId);
            
            if (field.type === 'password') {
                field.type = 'text';
                btn.textContent = '👁️‍🗨️';
            } else {
                field.type = 'password';
                btn.textContent = '👁️';
            }
        }

        // Check password requirements
        function checkPasswordRequirements() {
            const password = document.getElementById('password').value;
            
            // Check length
            const hasLength = password.length >= 8;
            updateRequirement('req-length', hasLength);
            
            // Check uppercase
            const hasUppercase = /[A-Z]/.test(password);
            updateRequirement('req-uppercase', hasUppercase);
            
            // Check lowercase
            const hasLowercase = /[a-z]/.test(password);
            updateRequirement('req-lowercase', hasLowercase);
            
            // Check number
            const hasNumber = /[0-9]/.test(password);
            updateRequirement('req-number', hasNumber);
            
            // Check special character
            const hasSpecial = /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password);
            updateRequirement('req-special', hasSpecial);
        }

        function updateRequirement(elementId, isMet) {
            const element = document.getElementById(elementId);
            if (isMet) {
                element.classList.remove('unmet');
                element.classList.add('met');
            } else {
                element.classList.remove('met');
                element.classList.add('unmet');
            }
        }

        // Toggle submit button based on rules checkbox
        function toggleSubmitButton() {
            const rulesCheckbox = document.getElementById('rules_accepted');
            const submitBtn = document.getElementById('submitBtn');
            const acceptRulesBtn = document.getElementById('acceptRulesBtn');
            
            if (rulesCheckbox.checked) {
                submitBtn.disabled = false;
                submitBtn.style.background = 'linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-dark) 100%)';
                submitBtn.style.color = 'var(--white)';
                submitBtn.style.cursor = 'pointer';
                
                acceptRulesBtn.disabled = false;
                acceptRulesBtn.style.background = 'linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-dark) 100%)';
                acceptRulesBtn.style.color = 'var(--white)';
                acceptRulesBtn.style.cursor = 'pointer';
            } else {
                submitBtn.disabled = true;
                submitBtn.style.background = 'var(--primary-blue)';
                submitBtn.style.color = 'var(--white)';
                submitBtn.style.cursor = 'not-allowed';
                
                acceptRulesBtn.disabled = true;
                acceptRulesBtn.style.background = 'var(--primary-blue)';
                acceptRulesBtn.style.color = 'var(--white)';
                acceptRulesBtn.style.cursor = 'not-allowed';
            }
        }

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            const rulesCheckbox = document.getElementById('rules_accepted');
            if (rulesCheckbox && rulesCheckbox.checked) {
                toggleSubmitButton();
            }
        });
    </script>
</x-auth-layout>
