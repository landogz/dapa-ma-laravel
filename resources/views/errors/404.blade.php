<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>404 · Not Found · DAPE-MA</title>
    <link rel="icon" type="image/svg+xml" href="{{ asset('ddb.svg') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --blue: #055498;
            --nile: #123a60;
            --gold: #FBD116;
            --ink: #0f172a;
            --muted: #475569;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; min-height: 100%; }
        body {
            min-height: 100vh;
            min-height: 100dvh;
            font-family: Montserrat, system-ui, sans-serif;
            color: var(--ink);
            background:
                radial-gradient(900px 480px at 8% -10%, rgba(251, 209, 22, 0.28), transparent 55%),
                radial-gradient(1000px 520px at 110% 0%, rgba(5, 84, 152, 0.28), transparent 50%),
                linear-gradient(165deg, #f8fafc 0%, #eef4fb 48%, #e7eef8 100%);
        }
        .page {
            min-height: 100vh;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px 32px;
        }
        .card {
            width: min(920px, 100%);
            display: grid;
            gap: 28px;
            align-items: center;
            background: #fff;
            border: 1px solid rgba(5, 84, 152, 0.08);
            border-radius: 28px;
            box-shadow: 0 24px 70px rgba(18, 58, 96, 0.12);
            padding: 28px 20px 24px;
            overflow: hidden;
            position: relative;
        }
        .card::before {
            content: "";
            position: absolute;
            inset: 0 auto auto 0;
            height: 6px;
            width: 100%;
            background: linear-gradient(90deg, var(--blue), var(--nile) 55%, var(--gold));
        }
        .hero {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 14px;
        }
        .mascot-wrap {
            width: min(220px, 62vw);
        }
        .mascot-wrap img {
            display: block;
            width: 100%;
            height: auto;
            aspect-ratio: 1;
            object-fit: contain;
            filter: drop-shadow(0 18px 28px rgba(5, 84, 152, 0.22));
            animation: floaty 3.6s ease-in-out infinite;
        }
        .bubble {
            margin: 12px 0 0;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 600;
            line-height: 1.4;
            color: var(--nile);
        }
        .copy { min-width: 0; }
        .eyebrow {
            margin: 0;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--blue);
        }
        .code {
            margin: 6px 0 0;
            font-size: clamp(64px, 16vw, 112px);
            line-height: 0.9;
            font-weight: 700;
            letter-spacing: -0.04em;
            background: linear-gradient(135deg, var(--blue) 0%, var(--nile) 100%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        h1 {
            margin: 4px 0 0;
            font-size: clamp(28px, 5vw, 40px);
            line-height: 1.15;
            font-weight: 700;
        }
        .lead {
            margin: 12px auto 0;
            max-width: 34rem;
            color: var(--muted);
            font-size: 15px;
            line-height: 1.55;
        }
        .path {
            margin: 16px auto 0;
            max-width: 100%;
            display: inline-block;
            padding: 8px 12px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #334155;
            font-size: 13px;
            line-height: 1.4;
            word-break: break-all;
        }
        .actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 22px;
            width: 100%;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 48px;
            padding: 12px 18px;
            border-radius: 14px;
            font-size: 15px;
            font-weight: 700;
            text-decoration: none;
            border: 0;
            cursor: pointer;
            font-family: inherit;
        }
        .btn-primary {
            color: #fff;
            background: linear-gradient(135deg, var(--blue) 0%, var(--nile) 100%);
            box-shadow: 0 12px 24px rgba(5, 84, 152, 0.28);
        }
        .btn-primary:hover { filter: brightness(1.05); }
        .btn-ghost {
            color: var(--nile);
            background: #fff;
            border: 1px solid #dbe3ee;
        }
        .btn-ghost:hover { background: #f8fafc; }
        .brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-top: 8px;
            color: #64748b;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.04em;
        }
        .brand img { width: 28px; height: 28px; object-fit: contain; }
        @keyframes floaty {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-8px); }
        }
        @media (min-width: 768px) {
            .card {
                grid-template-columns: 280px 1fr;
                gap: 12px 28px;
                padding: 40px 44px 32px;
            }
            .hero { align-items: flex-start; }
            .copy { text-align: left; }
            .lead, .path { margin-left: 0; margin-right: 0; }
            .actions { flex-direction: row; flex-wrap: wrap; }
            .btn { min-width: 180px; width: auto; }
            .brand { justify-content: flex-start; }
            .bubble { max-width: 220px; }
        }
        @media (prefers-reduced-motion: reduce) {
            .mascot-wrap img { animation: none; }
        }
    </style>
</head>
<body>
    <main class="page">
        <section class="card" aria-labelledby="not-found-title">
            <div class="hero">
                <div class="mascot-wrap">
                    <img src="{{ asset('assets/kid-listo/kid-listo.png') }}" alt="Kid Listo" width="220" height="220">
                    <p class="bubble">Let’s find a page that exists.</p>
                </div>
            </div>
            <div class="copy">
                <p class="eyebrow">DAPE-MA</p>
                <p class="code">404</p>
                <h1 id="not-found-title">Not Found</h1>
                <p class="lead">
                    Kid Listo looked everywhere, but this page isn’t available. The link may be outdated, or the address may have a typo.
                </p>
                <p class="path">{{ request()->path() === '/' ? '/' : '/'.request()->path() }}</p>
                <div class="actions">
                    <a class="btn btn-primary" href="{{ url('/') }}">Back to home</a>
                    <button class="btn btn-ghost" type="button" onclick="history.length > 1 ? history.back() : (location.href='{{ url('/') }}')">Go back</button>
                </div>
                <p class="brand">
                    <img src="{{ asset('ddb.svg') }}" alt="">
                    Dangerous Drugs Board
                </p>
            </div>
        </section>
    </main>
</body>
</html>
