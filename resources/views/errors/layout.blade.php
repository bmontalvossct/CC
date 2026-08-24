<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Error') - {{ config('app.name', 'ClassCheck') }}</title>
    <style>
        *, ::after, ::before {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: Tahoma, -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            color: #0f172a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1.5rem;
            -webkit-font-smoothing: antialiased;
        }
        .card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 1rem;
            padding: 2.5rem;
            max-width: 32rem;
            width: 100%;
            text-align: center;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
            position: relative;
            overflow: hidden;
        }
        .accent-bar {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: #14532d;
        }
        .badge {
            display: inline-block;
            background: #1e293b;
            color: #ffffff;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-family: monospace;
            font-size: 0.75rem;
            font-weight: 500;
            margin-bottom: 1.25rem;
        }
        h1 {
            font-size: 1.5rem;
            font-weight: 500;
            color: #0f172a;
            margin-bottom: 0.5rem;
            letter-spacing: -0.01em;
        }
        p {
            font-size: 0.875rem;
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .actions {
            display: flex;
            gap: 0.75rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #14532d;
            color: #ffffff;
            padding: 0.625rem 1.25rem;
            border-radius: 0.75rem;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.15s ease;
        }
        .btn-primary:hover {
            background: #166534;
        }
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #1e293b;
            color: #ffffff;
            padding: 0.625rem 1.25rem;
            border-radius: 0.75rem;
            text-decoration: none;
            font-size: 0.875rem;
            font-weight: 500;
            transition: background 0.15s ease;
            border: none;
            cursor: pointer;
        }
        .btn-secondary:hover {
            background: #334155;
        }
        .footer {
            margin-top: 2rem;
            padding-top: 1rem;
            border-top: 1px solid #f1f5f9;
            font-size: 0.75rem;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="accent-bar"></div>
        <div class="badge">@yield('code', 'Error')</div>
        <h1>@yield('title')</h1>
        <p>@yield('message')</p>
        <div class="actions">
            <a href="/" class="btn-primary">Go to Homepage</a>
            <a href="/dashboard" class="btn-secondary">Go to Dashboard</a>
        </div>
        <div class="footer">
            ClassCheck · Classroom & Attendance Management
        </div>
    </div>
</body>
</html>
