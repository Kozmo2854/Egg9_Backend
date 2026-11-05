@php
    $copy = [
        'confirmed' => [
            'icon'  => '✓',
            'title' => 'Pickup confirmed ✓',
            'body'  => 'Thanks! We\'ve marked your eggs as picked up. 🐣',
        ],
        'already' => [
            'icon'  => '✓',
            'title' => 'Already confirmed ✓',
            'body'  => 'This order was already marked as picked up. Nothing more to do!',
        ],
        'not_ready' => [
            'icon'  => '🥚',
            'title' => 'Not ready for pickup',
            'body'  => 'This order isn\'t ready for pickup confirmation yet.',
        ],
        'not_found' => [
            'icon'  => '🔍',
            'title' => 'Order not found',
            'body'  => 'We couldn\'t find that order. The link may be incorrect.',
        ],
        'expired' => [
            'icon'  => '⌛',
            'title' => 'Link expired',
            'body'  => 'This confirmation link is invalid or has expired. You can confirm pickup from the Egg9 app instead.',
        ],
    ];
    $c = $copy[$state] ?? $copy['not_found'];
    $siteUrl = config('app.site_url');
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title>{{ $c['title'] }} · Egg9</title>
    <style>
        :root {
            --bg: #f7f5ef;
            --card: #ffffff;
            --text: #2b2b24;
            --muted: #6b6b60;
            --accent: #c9972b;
            --border: #e7e3d8;
        }
        @media (prefers-color-scheme: dark) {
            :root {
                --bg: #1b1b18;
                --card: #262621;
                --text: #f2efe6;
                --muted: #a9a79c;
                --accent: #e2b85a;
                --border: #38372f;
            }
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            padding: 24px;
        }
        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 16px;
            max-width: 420px;
            width: 100%;
            padding: 40px 32px;
            text-align: center;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.08);
        }
        .brand {
            font-weight: 700;
            letter-spacing: 0.5px;
            color: var(--accent);
            text-transform: uppercase;
            font-size: 14px;
            margin-bottom: 20px;
        }
        .icon { font-size: 44px; line-height: 1; margin-bottom: 12px; }
        h1 { font-size: 22px; margin: 0 0 10px; }
        p { color: var(--muted); font-size: 15px; line-height: 1.5; margin: 0; }
        .footer {
            margin-top: 28px;
            padding-top: 18px;
            border-top: 1px solid var(--border);
            font-size: 13px;
        }
        .footer a { color: var(--accent); text-decoration: none; }
    </style>
</head>
<body>
    <div class="card">
        <div class="brand">Egg9</div>
        <div class="icon">{{ $c['icon'] }}</div>
        <h1>{{ $c['title'] }}</h1>
        <p>{{ $c['body'] }}</p>
        <div class="footer">
            Egg9 · <a href="{{ $siteUrl }}">{{ $siteUrl }}</a>
        </div>
    </div>
</body>
</html>
