# Discussion Hub Authentication - URL Reference & Flow

## 🌐 Application URLs

### Base URL
```
http://localhost/my-second-app/
```

### Authentication URLs

| URL | Method | Purpose | Controller Method |
|-----|--------|---------|-------------------|
| `/login` | GET | Display login form | AuthController@showLogin |
| `/login` | POST | Process login | AuthController@login |
| `/register` | GET | Display role selection | AuthController@showRegisterRole |
| `/register/role` | POST | Store selected role | AuthController@storeRole |
| `/register/details` | GET | Display registration form | AuthController@showRegisterDetails |
| `/register` | POST | Create user account | AuthController@register |
| `/logout` | POST | Logout user | AuthController@logout |
| `/dashboard` | GET | Main dashboard (protected) | (Closure) |

---

## 📱 User Registration Flow (Step-by-Step)

### STEP 1: Login Page → `/login`
```
URL: http://localhost/my-second-app/login

Display:
- "Discussion Hub" header (blue #0052CC)
- Subtitle: "Welcome back! Please login to your account."
- Email input field (placeholder: user@example.com)
- Password input field (with eye icon toggle)
- "Remember me" checkbox (left)
- "Forgot Password?" link (right)
- "LOGIN >" button (blue gradient)
- "Don't have an account? Register here" link at bottom

Features:
✓ Password visibility toggle works
✓ Loading spinner on submit
✓ Error messages in red box if login fails
✓ Form data persists on error (old() helper)
✓ Responsive on mobile/tablet/desktop

Link Action:
→ "Register here" takes to Step 2
```

### STEP 2: Role Selection → `/register`
```
URL: http://localhost/my-second-app/register

Display:
- "Register" header (blue)
- Subtitle: "Choose your role to get started"
- Label: "I am a:"
- Three role buttons (stacked vertically):
  1. 🎓 Student (default displayed if previously selected)
  2. 🎤 Lecturer
  3. ⚙️ Administrator
- "NEXT >" button (blue gradient, bottom)
- "Already have an account? Sign in" link at bottom

Features:
✓ Only one role can be selected at a time
✓ Selected role shows light blue background + checkmark
✓ Hover effects on buttons
✓ Role persists if validation fails
✓ Visual feedback clear and immediate

Button Actions:
- Select role → Updates form hidden input
- Click "NEXT >" → POST to /register/role
- Click "Sign in" → Redirects to /login
```

### STEP 3: Registration Details → `/register/details`
```
URL: http://localhost/my-second-app/register/details

Display (Two-Column Layout):

LEFT COLUMN: "CREATE AN ACCOUNT"
├─ Subtitle: "Join the Discussion Hub community today."
├─ Full Name input (placeholder: Nakato Vannesah)
├─ Email Address input (placeholder: nakatov@gmail.com)
├─ Username input (placeholder: Nakato V)
├─ Password input (with eye toggle)
│  ├─ Requirement: At least 8 characters
│  ├─ Requirement: Contains uppercase letter
│  ├─ Requirement: Contains lowercase letter
│  ├─ Requirement: Contains number
│  └─ Requirement: Contains special character
├─ Confirm Password input (with eye toggle)
├─ Status Badge: "✓ STATUS: PENDING RULE ACCEPTANCE"
└─ "COMPLETE REGISTRATION" button (DISABLED until rules checked)

RIGHT COLUMN: "PLATFORM RULES & GUIDELINES"
├─ 1. Be Respectful
│  └─ Harassment, hate speech, and personal attacks...
├─ 2. No Spam
│  └─ Do not post promotional content...
├─ 3. Privacy
│  └─ Do not share personal information...
├─ Checkbox: "I have read and agree to abide by the Platform Rules"
└─ Buttons:
   ├─ "ACCEPT RULES >" (DISABLED until checkbox checked)
   └─ "DECLINE" (links to /login)

Features:
✓ Real-time password requirement validation
✓ Requirements turn green when met
✓ Form fields preserve data on error
✓ Complete Registration button enables when:
  - All fields filled
  - Password meets all requirements
  - Password matches confirmation
  - Rules checkbox is checked
✓ Accept Rules button mirrors Complete button state
✓ Two-column layout on desktop, single column on mobile
✓ All form errors display in red below fields

Form Actions:
- Type in password → Requirements update in real-time
- Check rules → Submit buttons enable (turn blue)
- Click "ACCEPT RULES >" → POST to /register
- Click "DECLINE" → Redirects to /login (loses form data)
```

### STEP 4: Success → Redirect to `/login`
```
After successful registration:
- Flash message: "Account created successfully! 
                   Please log in to access your account."
- Redirected to login page
- Can now login with:
  - Email: [entered email]
  - Password: [entered password]
```

### STEP 5: Dashboard → `/dashboard` (Protected)
```
URL: http://localhost/my-second-app/dashboard

Access:
✓ Only logged-in users can access
✓ Redirects to /login if not authenticated
✓ Shows main application dashboard
```

---

## 🔐 Login Status Checks

When user attempts login, system checks:

```
1. User exists?
   ✗ NO  → Error: "No account found with this email address."
   ✓ YES → Continue

2. Password correct?
   ✗ NO  → Error: "The password is incorrect."
   ✓ YES → Continue

3. User status is "blacklisted"?
   ✓ YES → Error: "This account has been blacklisted. 
                   Please contact support."
   ✗ NO  → Continue

4. User status is "suspended"?
   ✓ YES → Error: "This account has been suspended. 
                   Please contact support."
   ✗ NO  → Continue

5. Rules accepted?
   ✗ NO  → Error: "Your account registration is incomplete. 
                   Please complete the registration process."
   ✓ YES → Continue

6. All checks passed?
   ✓ YES → Login successful!
          - Update last_active timestamp
          - Create session
          - Redirect to /dashboard
          - Display success message
```

---

## 📋 Form Validation Rules

### Login Form
```
Email:
  - Required
  - Valid email format
  
Password:
  - Required
  - Minimum 8 characters
```

### Role Selection Form
```
Role:
  - Required
  - Must be: student, lecturer, or administrator
```

### Registration Form
```
Full Name:
  - Required
  - 3-255 characters
  
Email:
  - Required
  - Valid email format
  - Unique (not already registered)
  
Username:
  - Required
  - 3+ characters
  - Unique (not already registered)
  - Only alphanumeric, dashes, underscores
  
Password:
  - Required
  - Minimum 8 characters
  - Must contain uppercase letter (A-Z)
  - Must contain lowercase letter (a-z)
  - Must contain number (0-9)
  - Must contain special character (!@#$%^&*)
  
Confirm Password:
  - Required
  - Must match Password field
  
Rules Acceptance:
  - Required (checkbox must be checked)
```

---

## 🎨 Form Field Styling

### All Input Fields
```
Background: Light blue #F0F7FF
Border: 1px solid #E5E7EB
Border Radius: 5px
Padding: 12px 15px
Font Size: 14px

On Focus:
  Border Color: #0052CC (primary blue)
  Shadow: 0 0 0 3px rgba(0, 82, 204, 0.1)
```

### Password Toggle (Eye Icon)
```
Icon: 👁️ (closed eye)
Color: #0052CC (primary blue)
Position: Right side of password field
Function:
  - Click to show password (changes to 👁️‍🗨️)
  - Click again to hide password (back to 👁️)
```

### Checkbox
```
Size: 20px × 20px
Color when checked: #0052CC
Label Font Size: 13px
Line Height: 1.5
Cursor: pointer
```

### Buttons
```
Height: 48px (optimal for mobile touch)
Border Radius: 5-8px
Font Weight: 600
Font Size: 16px

Primary Button (Login, Register):
  Background: Linear gradient #0052CC → #1473E6
  Text Color: White
  Hover: Shadow effect + slight lift
  
Secondary Button (Decline):
  Background: White
  Border: 2px solid #0052CC
  Text Color: #0052CC
  Hover: Light blue background
```

---

## 🔗 Navigation Flow

```
START
  ↓
[Login Page] (/login)
  ├─ Click "Register here" → [Role Selection]
  ├─ Enter credentials → [Dashboard] (success)
  └─ Stay at login (error)
  
[Role Selection] (/register)
  ├─ Select role & click "Next >" → [Registration Details]
  ├─ Click "Sign in" → [Login Page]
  └─ Validation error → [Role Selection] (show error)
  
[Registration Details] (/register/details)
  ├─ Fill form & click "Accept Rules >" → [Login Page] (success)
  ├─ Click "Decline" → [Login Page] (no redirect, form clears)
  ├─ Validation error → [Registration Details] (show error, keep data)
  └─ Session expired → [Role Selection]
  
[Dashboard] (/dashboard)
  ├─ Click logout → [Login Page]
  └─ Session timeout → [Login Page]
```

---

## 🌈 Color Reference

| Element | Color | Hex |
|---------|-------|-----|
| Primary Blue | Blue | #0052CC |
| Primary Blue Dark | Lighter Blue | #1473E6 |
| Light Blue Background | Light BG | #F0F7FF |
| Gradient Light | Gradient Light | #E8F1FF |
| Gradient Dark | Gradient Dark | #D4E3FF |
| Borders | Gray | #E5E7EB |
| Text | Gray | #6B7280 |
| Dark Text | Dark Gray | #4B5563 |
| Error | Red | #EF4444 |
| Success | Green | #22C55E |
| Light Gray | Gray | #D1D5DB |

---

## 📱 Responsive Breakpoints

```
Mobile (< 640px):
  - Single column layout
  - 90% width
  - 20px padding
  
Tablet (640px - 1023px):
  - Single column or adjusted layout
  - 500px max-width
  - 30px padding
  
Desktop (1024px+):
  - Two column layout (registration)
  - 1000px max-width
  - 30px padding
```

---

## 🔔 Error Messages

| Scenario | Message |
|----------|---------|
| Email not found | "No account found with this email address." |
| Wrong password | "The password is incorrect." |
| Account blacklisted | "This account has been blacklisted. Please contact support." |
| Account suspended | "This account has been suspended. Please contact support." |
| Rules not accepted | "Your account registration is incomplete. Please complete the registration process." |
| Email already exists | "This email address is already registered." |
| Username already exists | "This username is already taken." |
| Invalid email format | "Please enter a valid email address." |
| Weak password | Shows specific requirement failures in real-time |
| Passwords don't match | "Passwords do not match." |
| Rules not checked | "You must accept the platform rules to proceed." |
| No role selected | "Please select a role." |

---

## 🎯 Success Messages

| Action | Message | Redirect |
|--------|---------|----------|
| Registration complete | "Account created successfully! Please log in to access your account." | /login |
| Login successful | "Welcome back! You have successfully logged in." | /dashboard |
| Logout | "You have been logged out successfully." | /login |

---

## 📊 Database Queries Reference

```sql
-- Check user registration
SELECT email, username, role, status, rules_accepted 
FROM users 
WHERE email = 'user@example.com';

-- Update user status
UPDATE users 
SET status = 'suspended' 
WHERE id = 1;

-- Count registrations by role
SELECT role, COUNT(*) as count 
FROM users 
GROUP BY role;

-- Check blacklisted users
SELECT email, status, warnings 
FROM users 
WHERE status = 'blacklisted';
```

---

## 🚀 Quick Reference

**New User Registration**: /login → register → role → details → login

**Existing User**: /login → enter credentials → dashboard

**Logout**: Click logout → /login

**Error**: Shows error message, stays on same page, form data preserved

**Session Timeout**: Redirect to /login automatically
