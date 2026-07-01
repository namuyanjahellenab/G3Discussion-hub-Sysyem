<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Discussion Hub') }}</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-L+3pC0XhEIkQd5xNA6LrK+ZaQO7CK5w5KkxBfDIsE8IhttFTP0RB5BR4g8F8Q9cL" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.3/css/all.min.css" integrity="sha512-C8Be6suO0Xo2LcZ46C7YcX6I5r3TUi3t6bsh+Vwo4V9rBQZO3D8kQKPlN5vD4E9CW9fZCrFWMmZDH8vhI2Q==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">

    <style>
        :root {
            --primary-blue: #0052CC;
            --primary-blue-dark: #1473E6;
            --light-blue-bg: #F0F7FF;
            --gradient-light: #E8F1FF;
            --gradient-dark: #D4E3FF;
            --border-gray: #E5E7EB;
            --text-gray: #6B7280;
            --dark-gray: #4B5563;
            --error-red: #EF4444;
            --light-gray: #D1D5DB;
            --very-light-gray: #FAFBFC;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, var(--gradient-light) 0%, var(--gradient-dark) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .auth-container {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 20px;
        }

        .auth-card {
            background: white;
            border-radius: 10px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            padding: 30px;
            width: 100%;
        }

        @media (max-width: 640px) {
            .auth-card {
                padding: 20px;
                border-radius: 8px;
            }
        }

        /* Typography */
        h1 {
            color: var(--primary-blue);
            font-weight: 700;
            margin: 0;
        }

        h2 {
            color: var(--primary-blue);
            font-weight: 600;
        }

        .subtitle {
            color: var(--text-gray);
            font-size: 14px;
            margin-top: 8px;
            margin-bottom: 20px;
        }

        /* Form Elements */
        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            color: var(--dark-gray);
            font-size: 14px;
            font-weight: 500;
            margin-bottom: 8px;
        }

        input[type="text"],
        input[type="email"],
        input[type="password"],
        input[type="number"] {
            width: 100%;
            padding: 12px 15px;
            background-color: var(--light-blue-bg);
            border: 1px solid var(--border-gray);
            border-radius: 5px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 150ms ease, box-shadow 150ms ease;
        }

        input[type="text"]:focus,
        input[type="email"]:focus,
        input[type="password"]:focus,
        input[type="number"]:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 3px rgba(0, 82, 204, 0.1);
        }

        input[type="text"]::placeholder,
        input[type="email"]::placeholder,
        input[type="password"]::placeholder {
            color: #999;
        }

        /* Checkbox */
        .checkbox-wrapper {
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 15px 0;
        }

        input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--primary-blue);
            flex-shrink: 0;
        }

        .checkbox-label {
            font-size: 13px;
            color: var(--dark-gray);
            margin: 0;
            cursor: pointer;
            line-height: 1.5;
        }

        /* Buttons */
        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 5px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 200ms ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            text-align: center;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-blue) 0%, var(--primary-blue-dark) 100%);
            color: white;
            width: 100%;
            height: 48px;
            font-size: 16px;
            border: none;
        }

        .btn-primary:hover:not(:disabled) {
            box-shadow: 0 4px 12px rgba(0, 82, 204, 0.3);
            transform: translateY(-2px);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-secondary {
            background: white;
            border: 2px solid var(--primary-blue);
            color: var(--primary-blue);
            font-weight: 600;
        }

        .btn-secondary:hover {
            background-color: var(--light-blue-bg);
        }

        .btn-small {
            padding: 8px 16px;
            font-size: 13px;
        }

        /* Links */
        a {
            color: var(--primary-blue);
            text-decoration: none;
            transition: color 150ms ease;
        }

        a:hover {
            color: var(--primary-blue-dark);
        }

        /* Error Messages */
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            border: 1px solid var(--error-red);
            color: var(--error-red);
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        .field-error {
            color: var(--error-red);
            font-size: 12px;
            margin-top: 4px;
        }

        /* Success Messages */
        .alert-success {
            background-color: rgba(34, 197, 94, 0.1);
            border: 1px solid #22C55E;
            color: #15803D;
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }

        /* Status Badge */
        .status-badge {
            background-color: #EBF4FF;
            border: 1px solid var(--primary-blue);
            color: var(--primary-blue);
            padding: 10px 15px;
            border-radius: 5px;
            font-size: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
            margin: 15px 0;
        }

        /* Role Buttons */
        .role-button {
            width: 100%;
            padding: 12px;
            height: 56px;
            margin-bottom: 12px;
            background-color: white;
            border: 2px solid var(--border-gray);
            border-radius: 8px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 15px;
            font-weight: 500;
            color: var(--text-gray);
            transition: all 200ms ease;
            position: relative;
        }

        .role-button:hover {
            border-color: var(--primary-blue);
            box-shadow: 0 2px 8px rgba(0, 82, 204, 0.1);
        }

        .role-button.active {
            background-color: var(--light-blue-bg);
            border-color: var(--primary-blue);
            color: var(--primary-blue);
            font-weight: 600;
        }

        .role-button-icon {
            font-size: 20px;
            flex-shrink: 0;
        }

        .role-button-checkmark {
            position: absolute;
            right: 15px;
            color: var(--primary-blue);
            font-size: 20px;
            display: none;
        }

        .role-button.active .role-button-checkmark {
            display: block;
        }

        /* Two Column Layout */
        .two-column-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            max-width: 1000px;
        }

        @media (max-width: 1023px) {
            .two-column-layout {
                grid-template-columns: 1fr;
                gap: 20px;
            }
        }

        .column {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .column.rules-column {
            background-color: var(--very-light-gray);
        }

        .column-title {
            color: var(--dark-gray);
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 15px;
            text-transform: uppercase;
        }

        .rules-list {
            list-style: decimal;
            margin-left: 20px;
        }

        .rules-list li {
            color: var(--dark-gray);
            font-size: 13px;
            line-height: 1.6;
            margin-bottom: 12px;
        }

        .button-group {
            display: flex;
            gap: 20px;
            margin-top: 20px;
        }

        .button-group button,
        .button-group a {
            flex: 1;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 5px;
            font-weight: 600;
            text-decoration: none;
            border: none;
            cursor: pointer;
            transition: all 200ms ease;
        }

        @media (max-width: 768px) {
            .button-group {
                flex-direction: column;
                gap: 12px;
            }

            .button-group button,
            .button-group a {
                width: 100%;
            }
        }

        /* Password strength indicator */
        .password-requirements {
            font-size: 12px;
            color: var(--text-gray);
            margin-top: 8px;
            line-height: 1.6;
        }

        .requirement {
            display: flex;
            align-items: center;
            gap: 6px;
            margin: 4px 0;
        }

        .requirement-icon {
            width: 16px;
            height: 16px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 10px;
            flex-shrink: 0;
        }

        .requirement.met .requirement-icon {
            background-color: #22C55E;
            color: white;
        }

        .requirement.unmet .requirement-icon {
            background-color: var(--light-gray);
            color: var(--text-gray);
        }

        /* Loader */
        .loader {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top: 2px solid white;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        /* Password toggle */
        .password-field-wrapper {
            position: relative;
            width: 100%;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--primary-blue);
            font-size: 16px;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle:hover {
            opacity: 0.7;
        }

        /* Text Links */
        .text-center {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: var(--text-gray);
        }

        .text-center a {
            font-weight: 600;
            text-decoration: none;
            color: var(--primary-blue);
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0;
            color: var(--text-gray);
            font-size: 14px;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background-color: var(--border-gray);
        }

        /* Remember me section */
        .remember-section {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 20px;
            flex-wrap: wrap;
        }

        .forgot-password-link {
            font-size: 14px;
            color: var(--primary-blue);
            text-decoration: none;
        }

        .forgot-password-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="auth-container">
        {{ $slot }}
    </div>
</body>
</html>
