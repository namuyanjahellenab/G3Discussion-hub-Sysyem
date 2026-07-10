<?php

namespace App\Http\Controllers;

use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Requests\RoleSelectionRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Display the login form.
     */
    public function showLogin()
    {
        return view('auth.login');
    }

    /**
     * Handle login request.
     */
    public function login(LoginRequest $request)
    {
        $credentials = $request->validated();

        // Log login attempt
        Log::info('Login attempt', ['email' => $request->email]);

        // Check if user exists
        $user = User::where('Email', $credentials['email'])->first();

        if (!$user) {
            Log::warning('Login failed: User not found', ['email' => $request->email]);
            return back()
                ->withErrors(['email' => 'No account found with this email address.'])
                ->withInput($request->only('email'));
        }

        // Check user status (blacklist check)
        if ($user->status === 'blacklisted') {
            Log::warning('Login failed: User blacklisted', ['user_id' => $user->id]);
            return back()
                ->withErrors(['email' => 'This account has been blacklisted. Please contact support.'])
                ->withInput($request->only('email'));
        }

        if ($user->status === 'suspended') {
            Log::warning('Login failed: User suspended', ['user_id' => $user->id]);
            return back()
                ->withErrors(['email' => 'This account has been suspended. Please contact support.'])
                ->withInput($request->only('email'));
        }

        // Verify password
        if (!Hash::check($credentials['password'], $user->password)) {
            Log::warning('Login failed: Invalid password', ['email' => $request->email]);
            return back()
                ->withErrors(['password' => 'The password is incorrect.'])
                ->withInput($request->only('email'));
        }

        // Check if rules were accepted
        if (!$user->rules_accepted) {
            Log::warning('Login failed: Rules not accepted', ['user_id' => $user->id]);
            return back()
                ->withErrors(['email' => 'Your account registration is incomplete. Please complete the registration process.'])
                ->withInput($request->only('email'));
        }

        // Update last active timestamp
        $user->update(['last_active' => now()]);

        // Log successful login
        Log::info('Login successful', ['user_id' => $user->id, 'email' => $user->email]);

        // Login the user
        Auth::login($user, $request->boolean('remember'));

        // Flash success message
        session()->flash('success', 'Welcome back! You have successfully logged in.');

        return redirect()->route('dashboard');
    }
    /**
 * API login for non-browser clients (e.g. JavaFX desktop app).
 * Returns a Sanctum token instead of a session cookie.
 */
public function apiLogin(Request $request)
{
    $credentials = $request->validate([
        'email'    => 'required|email',
        'password' => 'required|string',
    ]);

    $user = User::where('Email', $credentials['email'])->first();

    if (!$user || !Hash::check($credentials['password'], $user->PasswordHash)) {
        return response()->json(['message' => 'Invalid credentials'], 401);
    }

    if ($user->Status === 'blacklisted' || $user->Status === 'suspended') {
        return response()->json(['message' => 'Account is ' . $user->Status], 403);
    }

    $token = $user->createToken('javafx-desktop')->plainTextToken;

  return response()->json([
      'token' => $token,
      'user'  => [
          'id'    => $user->UserID,
          'email' => $user->Email,
          'name'  => $user->UserName,
          'role'  => $user->Role,
      ],
  ]);
}
public function apiRegister(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:User,Email',
            'password' => 'required|string|min:8',
        ]);

        $user = User::create([
            'UserName' => $validated['name'],
            'Email' => $validated['email'],
            'PasswordHash' => Hash::make($validated['password']),
            'Role' => 'Student',
            'Status' => 'Active',
        ]);

        $token = $user->createToken('javafx-desktop')->plainTextToken;

        return response()->json([
            'message' => 'Registration successful.',
            'token' => $token,
            'user' => [
                'id' => $user->UserID,
                'email' => $user->Email,
                'name' => $user->UserName,
            ],
        ], 201);
    }

    /**
     * Display role selection page.
     */
    public function showRegisterRole()
    {
        return view('auth.register-role');
    }

    /**
     * Store selected role in session and redirect to registration details.
     */
    public function storeRole(RoleSelectionRequest $request)
    {
        $validated = $request->validated();

        // Store role in session
        session(['registration_role' => $validated['role']]);

        Log::info('Role selected for registration', ['role' => $validated['role']]);

        return redirect()->route('register.details');
    }

    /**
     * Display registration details form with rules.
     */
    public function showRegisterDetails()
    {
        // Check if role is stored in session
        if (!session()->has('registration_role')) {
            return redirect()->route('register.role')
                ->with('error', 'Please select a role first.');
        }

        return view('auth.register', [
            'role' => session('registration_role'),
        ]);
    }

    /**
     * Handle registration request.
     */
   public function register(RegisterRequest $request)
       {
           if (!session()->has('registration_role')) {
               return redirect()->route('register.role')
                   ->with('error', 'Please select a role first.');
           }

           $validated = $request->validated();
           $role = session('registration_role');

           try {
               $user = User::create([
                   'UserName' => $validated['full_name'],
                   'Email' => $validated['email'],
                   'PasswordHash' => Hash::make($validated['password']),
                   'Role' => ucfirst($role),
                   'Status' => 'Active',
               ]);

               Log::info('User registered successfully', [
                   'user_id' => $user->UserID,
                   'email' => $user->Email,
                   'role' => $role,
               ]);

               session()->forget('registration_role');
               session()->flash('success', 'Account created successfully! Please log in to access your account.');

               return redirect()->route('login')->with('message', 'Registration successful! You can now log in.');
           } catch (\Exception $e) {
               Log::error('Registration failed', [
                   'email' => $validated['email'],
                   'error' => $e->getMessage(),
               ]);

               return back()
                   ->withErrors(['email' => 'An error occurred during registration. Please try again.'])
                   ->withInput($request->except('password', 'password_confirmation'));
           }
       }

    /**
     * Handle logout.
     */
    public function logout(Request $request)
    {
        Log::info('User logged out', ['user_id' => Auth::id()]);

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        session()->flash('success', 'You have been logged out successfully.');

        return redirect()->route('login');
    }
}
