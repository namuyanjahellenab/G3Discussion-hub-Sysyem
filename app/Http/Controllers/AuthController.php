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
    public function login(LoginRequest $request)
{
    $credentials = $request->validated();

    Log::info('Login attempt', ['email' => $request->email]);

    $user = User::where('Email', $credentials['email'])->first();

    if (!$user) {
        Log::warning('Login failed: User not found', ['email' => $request->email]);
        return back()
            ->withErrors(['email' => 'No account found with this email address.'])
            ->withInput($request->only('email'));
    }

    if ($user->Status === 'Blacklisted') {
        Log::warning('Login failed: User blacklisted', ['user_id' => $user->UserID]);
        return back()
            ->withErrors(['email' => 'This account has been blacklisted. Please contact support.'])
            ->withInput($request->only('email'));
    }

    if ($user->Status === 'Suspended') {
        Log::warning('Login failed: User suspended', ['user_id' => $user->UserID]);
        return back()
            ->withErrors(['email' => 'This account has been suspended. Please contact support.'])
            ->withInput($request->only('email'));
    }

    if (!Hash::check($credentials['password'], $user->PasswordHash)) {
        Log::warning('Login failed: Invalid password', ['email' => $request->email]);
        return back()
            ->withErrors(['password' => 'The password is incorrect.'])
            ->withInput($request->only('email'));
    }

    if (!$user->RulesAccepted) {
        Log::warning('Login failed: Rules not accepted', ['user_id' => $user->UserID]);
        return back()
            ->withErrors(['email' => 'Your account registration is incomplete. Please complete the registration process.'])
            ->withInput($request->only('email'));
    }

    $user->update(['LastActive' => now()]);

    Log::info('Login successful', ['user_id' => $user->UserID, 'email' => $user->Email]);

    Auth::login($user, $request->boolean('remember'));

    session()->flash('success', 'Welcome back! You have successfully logged in.');

    return redirect()->route('dashboard');
}
    /**
 * API login for non-browser clients (e.g. JavaFX desktop app).
 * Returns a Sanctum token instead of a session cookie.
 */
/**
 * Desktop-client registration - the web flow is session-based (role
 * selected on a separate screen before the details form), which doesn't
 * translate to a stateless API call. This collects everything in one
 * request instead and always registers as a Student, matching the
 * desktop client's scope. Same validation/creation as AuthController::register().
 */
public function apiRegister(Request $request)
{
    $validated = $request->validate([
        'full_name' => ['required', 'string', 'min:3', 'max:255'],
        'email' => ['required', 'string', 'email', 'max:255', 'unique:User,Email'],
        'username' => ['required', 'string', 'min:3', 'max:255', 'unique:User,Handle', 'alpha_dash'],
        'password' => ['required', 'string', 'min:8', 'confirmed'],
        'rules_accepted' => ['required', 'accepted'],
    ]);

    $user = User::create([
        'UserName' => $validated['full_name'],
        'Username' => $validated['username'],
        'Handle' => $validated['username'],
        'Email' => $validated['email'],
        'PasswordHash' => Hash::make($validated['password']),
        'Role' => 'Student',
        'Status' => 'Active',
        'RulesAccepted' => true,
        'LastActive' => now(),
    ]);

    Log::info('User registered successfully via desktop client', [
        'user_id' => $user->UserID,
        'email' => $user->Email,
    ]);

    return response()->json(['message' => 'Account created successfully! Please log in to access your account.'], 201);
}

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
          'theme_color' => $user->ThemeColor ?? 'luna',
      ],
  ]);
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

      public function register(RegisterRequest $request)
{
    if (!session()->has('registration_role')) {
        return redirect()->route('register.role')
            ->with('error', 'Please select a role first.');
    }

    $validated = $request->validated();
    $role = session('registration_role');

    // Extra safety check: role must be one of the two allowed values
    if (!in_array($role, ['student', 'lecturer'])) {
        return redirect()->route('register.role')
            ->with('error', 'Invalid role selected.');
    }

    try {
        $staffRecord = null;

        if ($role === 'lecturer') {
            $staffRecord = \App\Models\LecturerStaffId::where('StaffIDNumber', $request->staff_id_number)
                ->where('IsUsed', false)
                ->first();

            if (!$staffRecord) {
                return back()
                    ->withErrors(['staff_id_number' => 'This staff ID is invalid or has already been used. Please contact your administrator.'])
                    ->withInput($request->except('password', 'password_confirmation'));
            }
        }

        $user = User::create([
            'UserName' => $validated['full_name'],
            'Username' => $validated['username'],
            'Handle' => $validated['username'],
            'Email' => $validated['email'],
            'PasswordHash' => Hash::make($validated['password']),
            'Role' => ucfirst($role),
            'Status' => 'Active',
            'RulesAccepted' => true,
            'LastActive' => now(),
        ]);

        if ($staffRecord) {
            $staffRecord->update([
                'IsUsed' => true,
                'LinkedUserID' => $user->UserID,
            ]);
        }

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
