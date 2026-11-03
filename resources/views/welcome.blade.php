@php
    $loginUrl = \Illuminate\Support\Facades\Route::has('filament.admin.auth.login')
        ? route('filament.admin.auth.login')
        : url('/admin/login');
@endphp
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#070d18">
    <meta name="description" content="نظام المتأخرات — منظومة متكاملة لإدارة المواطنين والعقود والأقساط ومتابعة المتأخرات وتصديرها، ضمن أعمال تقنين الأراضي.">

    <title>نظام المتأخرات | إدارة الأقساط وتقنين الأراضي</title>

    <link rel="icon" href="{{ asset('favicon.ico') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <script>document.documentElement.classList.add('js');</script>

    <style>
        /* ============================================================
           Design tokens
           ============================================================ */
        :root {
            --bg: #070d18;
            --bg-soft: #0b1322;
            --surface: rgba(255, 255, 255, 0.045);
            --surface-strong: rgba(255, 255, 255, 0.075);
            --line: rgba(148, 163, 184, 0.14);
            --line-strong: rgba(245, 185, 60, 0.32);
            --text: #e9eff9;
            --muted: #95a3b8;
            --muted-soft: #6f7f96;
            --gold: #f5b93c;
            --gold-light: #fcd34d;
            --gold-deep: #d98a0b;
            --green: #34d399;
            --red: #fb7185;
            --radius: 20px;
            --radius-sm: 14px;
            --shadow: 0 40px 90px -35px rgba(0, 0, 0, 0.9);
            --container: 1180px;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
        }

        html {
            scroll-behavior: smooth;
            scroll-padding-top: 90px;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg);
            color: var(--text);
            font-family: 'IBM Plex Sans Arabic', 'Segoe UI', Tahoma, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.7;
            -webkit-font-smoothing: antialiased;
            overflow-x: hidden;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -2;
            background:
                radial-gradient(900px 520px at 82% -8%, rgba(245, 185, 60, 0.13), transparent 60%),
                radial-gradient(760px 480px at 8% 12%, rgba(56, 189, 248, 0.07), transparent 65%),
                radial-gradient(900px 600px at 50% 110%, rgba(245, 185, 60, 0.06), transparent 60%);
        }

        body::after {
            content: "";
            position: fixed;
            inset: 0;
            z-index: -1;
            pointer-events: none;
            background-image:
                linear-gradient(rgba(148, 163, 184, 0.045) 1px, transparent 1px),
                linear-gradient(90deg, rgba(148, 163, 184, 0.045) 1px, transparent 1px);
            background-size: 64px 64px;
            mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, #000 35%, transparent 78%);
            -webkit-mask-image: radial-gradient(ellipse 80% 60% at 50% 0%, #000 35%, transparent 78%);
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        h1, h2, h3, h4, p {
            margin: 0;
        }

        ul {
            margin: 0;
            padding: 0;
            list-style: none;
        }

        svg {
            display: block;
            flex-shrink: 0;
        }

        ::selection {
            background: rgba(245, 185, 60, 0.28);
            color: #fff;
        }

        :focus-visible {
            outline: 2px solid var(--gold);
            outline-offset: 3px;
            border-radius: 6px;
        }

        .container {
            width: 100%;
            max-width: var(--container);
            margin-inline: auto;
            padding-inline: 24px;
        }

        .num {
            direction: ltr;
            unicode-bidi: isolate;
            font-variant-numeric: tabular-nums;
            display: inline-block;
        }

        .skip-link {
            position: fixed;
            top: -100px;
            right: 24px;
            z-index: 100;
            padding: 10px 18px;
            border-radius: 10px;
            background: var(--gold);
            color: #1b1405;
            font-weight: 600;
            transition: top 0.2s ease;
        }

        .skip-link:focus {
            top: 16px;
        }

        /* ============================================================
           Header
           ============================================================ */
        .site-header {
            position: sticky;
            top: 0;
            z-index: 50;
            border-bottom: 1px solid transparent;
            background: rgba(7, 13, 24, 0.72);
            backdrop-filter: blur(18px);
            -webkit-backdrop-filter: blur(18px);
        }

        .site-header.is-scrolled {
            border-bottom-color: var(--line);
            box-shadow: 0 18px 40px -30px rgba(0, 0, 0, 0.9);
        }

        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 24px;
            min-height: 76px;
        }

        .brand {
            display: inline-flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        .brand-mark {
            display: grid;
            place-items: center;
            width: 44px;
            height: 44px;
            border-radius: 13px;
            color: #241804;
            background: linear-gradient(145deg, var(--gold-light), var(--gold) 55%, var(--gold-deep));
            box-shadow: 0 12px 30px -12px rgba(245, 185, 60, 0.75), inset 0 1px 0 rgba(255, 255, 255, 0.45);
        }

        .brand-text {
            display: flex;
            flex-direction: column;
            line-height: 1.35;
        }

        .brand-name {
            font-size: 1.02rem;
            font-weight: 700;
            letter-spacing: 0.2px;
        }

        .brand-sub {
            font-size: 0.68rem;
            color: var(--muted-soft);
            font-weight: 400;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 28px;
            font-size: 0.9rem;
            color: var(--muted);
        }

        .nav-links a {
            position: relative;
            padding-block: 6px;
            transition: color 0.2s ease;
        }

        .nav-links a::after {
            content: "";
            position: absolute;
            inset-inline: 0;
            bottom: 0;
            height: 2px;
            border-radius: 2px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            transform: scaleX(0);
            transition: transform 0.25s ease;
        }

        .nav-links a:hover {
            color: var(--text);
        }

        .nav-links a:hover::after {
            transform: scaleX(1);
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-shrink: 0;
        }

        /* ============================================================
           Buttons
           ============================================================ */
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            padding: 13px 24px;
            border: 1px solid transparent;
            border-radius: 12px;
            font-family: inherit;
            font-size: 0.92rem;
            font-weight: 600;
            line-height: 1.2;
            cursor: pointer;
            transition: transform 0.2s ease, box-shadow 0.2s ease, background 0.2s ease, border-color 0.2s ease;
            white-space: nowrap;
        }

        .btn svg {
            transition: transform 0.2s ease;
        }

        .btn:hover {
            transform: translateY(-2px);
        }

        .btn-primary {
            color: #241804;
            background: linear-gradient(145deg, var(--gold-light), var(--gold) 55%, var(--gold-deep));
            box-shadow: 0 16px 36px -16px rgba(245, 185, 60, 0.85), inset 0 1px 0 rgba(255, 255, 255, 0.45);
        }

        .btn-primary:hover {
            box-shadow: 0 20px 44px -16px rgba(245, 185, 60, 0.95), inset 0 1px 0 rgba(255, 255, 255, 0.45);
        }

        .btn-primary:hover svg {
            transform: translateX(-4px);
        }

        .btn-ghost {
            color: var(--text);
            border-color: var(--line);
            background: rgba(255, 255, 255, 0.03);
        }

        .btn-ghost:hover {
            border-color: var(--line-strong);
            background: rgba(255, 255, 255, 0.06);
        }

        .btn-lg {
            padding: 16px 30px;
            font-size: 1rem;
            border-radius: 14px;
        }

        /* ============================================================
           Hero
           ============================================================ */
        .hero {
            position: relative;
            padding-block: 76px 64px;
        }

        .hero-grid {
            display: grid;
            grid-template-columns: 1.06fr 0.94fr;
            align-items: center;
            gap: 56px;
        }

        .hero-copy {
            animation: rise 0.9s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .eyebrow {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 7px 14px 7px 12px;
            border: 1px solid rgba(245, 185, 60, 0.22);
            border-radius: 999px;
            background: rgba(245, 185, 60, 0.07);
            color: var(--gold-light);
            font-size: 0.78rem;
            font-weight: 500;
            letter-spacing: 0.2px;
        }

        .eyebrow .dot {
            position: relative;
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 0 4px rgba(245, 185, 60, 0.16);
        }

        .eyebrow .dot::after {
            content: "";
            position: absolute;
            inset: -4px;
            border-radius: 50%;
            border: 1px solid rgba(245, 185, 60, 0.5);
            animation: ping 2.4s ease-out infinite;
        }

        .hero h1 {
            margin-top: 22px;
            font-size: clamp(2.25rem, 4.6vw, 3.7rem);
            font-weight: 700;
            line-height: 1.32;
            letter-spacing: -0.6px;
        }

        .hero h1 .accent {
            background: linear-gradient(100deg, var(--gold-light), var(--gold) 45%, #ffd97a);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero-lead {
            margin-top: 20px;
            max-width: 560px;
            color: var(--muted);
            font-size: 1.04rem;
            font-weight: 300;
            line-height: 2;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 14px;
            margin-top: 32px;
        }

        .hero-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 10px 22px;
            margin-top: 28px;
            color: var(--muted-soft);
            font-size: 0.8rem;
        }

        .hero-meta li {
            display: inline-flex;
            align-items: center;
            gap: 7px;
        }

        .hero-meta svg {
            color: var(--green);
        }

        /* ============================================================
           Dashboard mockup
           ============================================================ */
        .hero-visual {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 500px;
            animation: rise 1.1s 0.15s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .visual-glow {
            position: absolute;
            inset: 6% 2%;
            border-radius: 50%;
            background: radial-gradient(circle at 55% 45%, rgba(245, 185, 60, 0.2), transparent 62%);
            filter: blur(48px);
        }

        .orbit {
            position: absolute;
            width: 470px;
            height: 470px;
            border: 1px dashed rgba(245, 185, 60, 0.16);
            border-radius: 50%;
            animation: spin 60s linear infinite;
        }

        .orbit::before,
        .orbit::after {
            content: "";
            position: absolute;
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--gold);
            box-shadow: 0 0 18px rgba(245, 185, 60, 0.8);
        }

        .orbit::before {
            top: 42px;
            right: 84px;
        }

        .orbit::after {
            bottom: 58px;
            left: 62px;
            opacity: 0.5;
        }

        .dashboard {
            position: relative;
            width: 100%;
            max-width: 448px;
            padding: 20px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            border-radius: 24px;
            background: linear-gradient(180deg, rgba(20, 31, 51, 0.96), rgba(10, 17, 31, 0.99));
            box-shadow: var(--shadow), inset 0 1px 0 rgba(255, 255, 255, 0.07);
            transform: rotate(1.2deg);
        }

        .dashboard-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding-bottom: 14px;
            border-bottom: 1px solid var(--line);
        }

        .dashboard-title {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 0.82rem;
            font-weight: 600;
        }

        .dashboard-title .live {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: var(--green);
            box-shadow: 0 0 0 4px rgba(52, 211, 153, 0.14);
        }

        .dashboard-top .stamp {
            font-size: 0.62rem;
            color: var(--muted-soft);
        }

        .dash-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin-top: 14px;
        }

        .dash-stat {
            padding: 11px 10px;
            border: 1px solid var(--line);
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.03);
        }

        .dash-stat span {
            display: block;
            color: var(--muted-soft);
            font-size: 0.6rem;
            font-weight: 400;
            white-space: nowrap;
        }

        .dash-stat strong {
            display: block;
            margin-top: 3px;
            font-size: 0.94rem;
            font-weight: 600;
            letter-spacing: 0.2px;
        }

        .dash-stat.is-danger strong {
            color: var(--red);
        }

        .dash-stat.is-gold strong {
            color: var(--gold-light);
        }

        .dash-chart {
            margin-top: 14px;
            padding: 13px 13px 10px;
            border: 1px solid var(--line);
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.02);
        }

        .chart-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.63rem;
            color: var(--muted-soft);
        }

        .chart-head b {
            color: var(--muted);
            font-weight: 500;
        }

        .bars {
            display: flex;
            align-items: flex-end;
            gap: 9px;
            height: 66px;
            margin-top: 12px;
        }

        .bar {
            flex: 1;
            min-width: 0;
            border-radius: 5px 5px 2px 2px;
            background: linear-gradient(180deg, rgba(245, 185, 60, 0.95), rgba(245, 185, 60, 0.28));
            transform-origin: bottom;
            animation: grow 1s 0.5s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        .bar.is-muted {
            background: linear-gradient(180deg, rgba(148, 163, 184, 0.5), rgba(148, 163, 184, 0.12));
        }

        .chart-labels {
            display: flex;
            gap: 9px;
            margin-top: 6px;
            color: var(--muted-soft);
            font-size: 0.52rem;
            text-align: center;
        }

        .chart-labels span {
            flex: 1;
        }

        .dash-rows {
            display: flex;
            flex-direction: column;
            gap: 7px;
            margin-top: 14px;
        }

        .dash-row {
            display: grid;
            grid-template-columns: 32px 1fr auto;
            align-items: center;
            gap: 10px;
            padding: 9px 10px;
            border: 1px solid transparent;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.025);
            transition: border-color 0.2s ease, background 0.2s ease;
        }

        .dash-row:hover {
            border-color: var(--line);
            background: rgba(255, 255, 255, 0.045);
        }

        .avatar {
            display: grid;
            place-items: center;
            width: 32px;
            height: 32px;
            border-radius: 9px;
            background: rgba(245, 185, 60, 0.12);
            color: var(--gold-light);
            font-size: 0.72rem;
            font-weight: 600;
        }

        .dash-row .who {
            min-width: 0;
        }

        .dash-row .who b {
            display: block;
            overflow: hidden;
            font-size: 0.72rem;
            font-weight: 500;
            white-space: nowrap;
            text-overflow: ellipsis;
        }

        .dash-row .who small {
            display: block;
            color: var(--muted-soft);
            font-size: 0.58rem;
            direction: ltr;
            text-align: right;
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 3px 8px;
            border-radius: 999px;
            font-size: 0.57rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .badge-danger {
            color: #fecdd3;
            background: rgba(251, 113, 133, 0.14);
            border: 1px solid rgba(251, 113, 133, 0.24);
        }

        .badge-warn {
            color: #fde68a;
            background: rgba(245, 185, 60, 0.12);
            border: 1px solid rgba(245, 185, 60, 0.24);
        }

        .float-card {
            position: absolute;
            display: flex;
            align-items: center;
            gap: 9px;
            padding: 10px 13px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 13px;
            background: rgba(13, 22, 39, 0.94);
            box-shadow: 0 20px 45px -22px rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            font-size: 0.68rem;
            font-weight: 500;
            animation: float 6s ease-in-out infinite;
            z-index: 2;
        }

        .float-card .icon {
            display: grid;
            place-items: center;
            width: 28px;
            height: 28px;
            border-radius: 8px;
        }

        .float-card small {
            display: block;
            color: var(--muted-soft);
            font-size: 0.55rem;
            font-weight: 400;
        }

        .float-export {
            top: 9%;
            left: -14px;
        }

        .float-export .icon {
            color: var(--gold-light);
            background: rgba(245, 185, 60, 0.14);
        }

        .float-paid {
            right: -16px;
            bottom: 11%;
            animation-delay: 1.4s;
        }

        .float-paid .icon {
            color: var(--green);
            background: rgba(52, 211, 153, 0.14);
        }

        /* ============================================================
           Trust strip
           ============================================================ */
        .trust {
            border-block: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.015);
        }

        .trust-list {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 16px 30px;
            padding-block: 20px;
        }

        .trust-list li {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: var(--muted);
            font-size: 0.82rem;
        }

        .trust-list svg {
            color: var(--gold);
        }

        /* ============================================================
           Sections
           ============================================================ */
        .section {
            padding-block: 92px;
        }

        .section-head {
            max-width: 660px;
            margin-bottom: 46px;
        }

        .section-head.center {
            margin-inline: auto;
            text-align: center;
        }

        .kicker {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            color: var(--gold);
            font-size: 0.78rem;
            font-weight: 600;
            letter-spacing: 0.4px;
        }

        .kicker::before {
            content: "";
            width: 26px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold));
        }

        .section-head.center .kicker::before {
            display: none;
        }

        .section-head h2 {
            margin-top: 14px;
            font-size: clamp(1.65rem, 3.2vw, 2.45rem);
            font-weight: 700;
            line-height: 1.4;
            letter-spacing: -0.3px;
        }

        .section-head p {
            margin-top: 14px;
            color: var(--muted);
            font-size: 0.95rem;
            font-weight: 300;
            line-height: 1.95;
        }

        /* ============================================================
           Features
           ============================================================ */
        .features-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .feature {
            position: relative;
            overflow: hidden;
            padding: 28px 24px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: linear-gradient(160deg, rgba(255, 255, 255, 0.05), rgba(255, 255, 255, 0.018));
            transition: transform 0.28s ease, border-color 0.28s ease, background 0.28s ease;
        }

        .feature::before {
            content: "";
            position: absolute;
            top: 0;
            inset-inline: 18%;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--gold), transparent);
            opacity: 0;
            transition: opacity 0.28s ease;
        }

        .feature:hover {
            transform: translateY(-5px);
            border-color: rgba(245, 185, 60, 0.24);
            background: linear-gradient(160deg, rgba(255, 255, 255, 0.07), rgba(255, 255, 255, 0.025));
        }

        .feature:hover::before {
            opacity: 1;
        }

        .feature-icon {
            display: grid;
            place-items: center;
            width: 48px;
            height: 48px;
            border: 1px solid rgba(245, 185, 60, 0.18);
            border-radius: 14px;
            background: rgba(245, 185, 60, 0.1);
            color: var(--gold-light);
        }

        .feature h3 {
            margin-top: 20px;
            font-size: 1.03rem;
            font-weight: 600;
        }

        .feature p {
            margin-top: 9px;
            color: var(--muted);
            font-size: 0.85rem;
            font-weight: 300;
            line-height: 1.9;
        }

        .feature .tag {
            display: inline-block;
            margin-top: 16px;
            padding: 3px 9px;
            border: 1px solid var(--line);
            border-radius: 999px;
            color: var(--muted-soft);
            font-size: 0.63rem;
        }

        /* ============================================================
           Workflow
           ============================================================ */
        .workflow {
            border-block: 1px solid var(--line);
            background:
                radial-gradient(600px 300px at 50% 0%, rgba(245, 185, 60, 0.06), transparent 70%),
                rgba(255, 255, 255, 0.012);
        }

        .steps-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            counter-reset: step;
        }

        .step {
            position: relative;
            padding: 26px 22px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: rgba(255, 255, 255, 0.025);
            transition: border-color 0.25s ease, transform 0.25s ease;
        }

        .step:hover {
            transform: translateY(-4px);
            border-color: rgba(245, 185, 60, 0.22);
        }

        .step-num {
            display: inline-grid;
            place-items: center;
            width: 34px;
            height: 34px;
            border: 1px solid rgba(245, 185, 60, 0.28);
            border-radius: 10px;
            background: rgba(245, 185, 60, 0.09);
            color: var(--gold-light);
            font-size: 0.8rem;
            font-weight: 600;
        }

        .step h3 {
            margin-top: 18px;
            font-size: 0.95rem;
            font-weight: 600;
        }

        .step p {
            margin-top: 8px;
            color: var(--muted);
            font-size: 0.8rem;
            font-weight: 300;
            line-height: 1.9;
        }

        /* ============================================================
           Roles
           ============================================================ */
        .roles-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        .role-card {
            position: relative;
            padding: 28px 24px;
            border: 1px solid var(--line);
            border-radius: var(--radius);
            background: linear-gradient(170deg, rgba(255, 255, 255, 0.045), rgba(255, 255, 255, 0.015));
        }

        .role-card.is-featured {
            border-color: rgba(245, 185, 60, 0.34);
            background: linear-gradient(170deg, rgba(245, 185, 60, 0.09), rgba(255, 255, 255, 0.02));
            box-shadow: 0 24px 60px -40px rgba(245, 185, 60, 0.6);
        }

        .role-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }

        .role-card h3 {
            font-size: 1.02rem;
            font-weight: 600;
        }

        .role-pill {
            padding: 3px 9px;
            border: 1px solid rgba(245, 185, 60, 0.3);
            border-radius: 999px;
            color: var(--gold-light);
            font-size: 0.6rem;
            font-weight: 500;
            white-space: nowrap;
        }

        .role-card ul {
            display: flex;
            flex-direction: column;
            gap: 9px;
            margin-top: 18px;
        }

        .role-card li {
            display: flex;
            align-items: flex-start;
            gap: 8px;
            color: var(--muted);
            font-size: 0.82rem;
            font-weight: 300;
            line-height: 1.8;
        }

        .role-card li svg {
            margin-top: 4px;
            color: var(--gold);
        }

        /* ============================================================
           CTA
           ============================================================ */
        .cta-section {
            padding-block: 30px 96px;
        }

        .cta-panel {
            position: relative;
            overflow: hidden;
            padding: 58px 40px;
            border: 1px solid rgba(245, 185, 60, 0.24);
            border-radius: 28px;
            text-align: center;
            background:
                radial-gradient(600px 280px at 50% -20%, rgba(245, 185, 60, 0.22), transparent 70%),
                linear-gradient(160deg, rgba(245, 185, 60, 0.07), rgba(255, 255, 255, 0.02) 45%, rgba(7, 13, 24, 0.4));
            box-shadow: var(--shadow);
        }

        .cta-panel::before,
        .cta-panel::after {
            content: "";
            position: absolute;
            border: 1px solid rgba(245, 185, 60, 0.14);
            border-radius: 50%;
            pointer-events: none;
        }

        .cta-panel::before {
            width: 360px;
            height: 360px;
            top: -220px;
            right: -100px;
        }

        .cta-panel::after {
            width: 460px;
            height: 460px;
            bottom: -320px;
            left: -140px;
        }

        .cta-panel h2 {
            position: relative;
            z-index: 1;
            font-size: clamp(1.6rem, 3.4vw, 2.4rem);
            font-weight: 700;
            line-height: 1.45;
        }

        .cta-panel p {
            position: relative;
            z-index: 1;
            max-width: 560px;
            margin: 14px auto 0;
            color: var(--muted);
            font-size: 0.94rem;
            font-weight: 300;
            line-height: 1.95;
        }

        .cta-actions {
            position: relative;
            z-index: 1;
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 12px;
            margin-top: 30px;
        }

        .cta-note {
            position: relative;
            z-index: 1;
            margin-top: 18px;
            color: var(--muted-soft);
            font-size: 0.72rem;
        }

        /* ============================================================
           Footer
           ============================================================ */
        .site-footer {
            border-top: 1px solid var(--line);
            background: rgba(4, 9, 17, 0.6);
        }

        .footer-inner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 22px;
            padding-block: 30px;
        }

        .footer-brand {
            display: flex;
            align-items: center;
            gap: 11px;
        }

        .footer-brand .brand-mark {
            width: 36px;
            height: 36px;
            border-radius: 10px;
        }

        .footer-brand p {
            font-size: 0.8rem;
            font-weight: 600;
        }

        .footer-brand small {
            display: block;
            color: var(--muted-soft);
            font-size: 0.65rem;
            font-weight: 400;
        }

        .footer-links {
            display: flex;
            flex-wrap: wrap;
            gap: 20px;
            color: var(--muted);
            font-size: 0.78rem;
        }

        .footer-links a {
            transition: color 0.2s ease;
        }

        .footer-links a:hover {
            color: var(--gold-light);
        }

        .footer-copy {
            color: var(--muted-soft);
            font-size: 0.7rem;
        }

        /* ============================================================
           Motion
           ============================================================ */
        .js .reveal {
            opacity: 0;
            transform: translateY(20px);
            transition: opacity 0.7s cubic-bezier(0.22, 1, 0.36, 1), transform 0.7s cubic-bezier(0.22, 1, 0.36, 1);
            transition-delay: var(--delay, 0s);
        }

        .js .reveal.is-visible {
            opacity: 1;
            transform: none;
        }

        @keyframes rise {
            from {
                opacity: 0;
                transform: translateY(26px);
            }
            to {
                opacity: 1;
                transform: none;
            }
        }

        @keyframes ping {
            0% {
                transform: scale(0.7);
                opacity: 0.9;
            }
            70%, 100% {
                transform: scale(2.1);
                opacity: 0;
            }
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes float {
            0%, 100% {
                transform: translateY(0);
            }
            50% {
                transform: translateY(-9px);
            }
        }

        @keyframes grow {
            from {
                transform: scaleY(0.08);
                opacity: 0.3;
            }
            to {
                transform: scaleY(1);
                opacity: 1;
            }
        }

        /* ============================================================
           Responsive
           ============================================================ */
        @media (max-width: 1024px) {
            .hero-grid {
                gap: 36px;
            }

            .features-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .steps-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .trust-list {
                justify-content: flex-start;
                gap: 14px 28px;
            }
        }

        @media (max-width: 900px) {
            .nav-links {
                display: none;
            }

            .hero {
                padding-block: 56px 48px;
            }

            .hero-grid {
                grid-template-columns: 1fr;
                gap: 48px;
            }

            .hero-copy {
                text-align: center;
            }

            .hero-lead {
                margin-inline: auto;
            }

            .hero-actions,
            .hero-meta {
                justify-content: center;
            }

            .hero-visual {
                min-height: 440px;
            }

            .dashboard {
                transform: none;
            }

            .orbit {
                width: 400px;
                height: 400px;
            }

            .roles-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 720px) {
            .section {
                padding-block: 66px;
            }

            .section-head {
                margin-bottom: 32px;
            }

            .features-grid {
                grid-template-columns: 1fr;
            }

            .float-export {
                left: -6px;
            }

            .float-paid {
                right: -6px;
            }

            .cta-panel {
                padding: 44px 24px;
                border-radius: 22px;
            }

            .footer-inner {
                justify-content: center;
                text-align: center;
            }

            .footer-links {
                justify-content: center;
            }
        }

        @media (max-width: 560px) {
            .container {
                padding-inline: 18px;
            }

            .header-inner {
                min-height: 68px;
            }

            .brand-sub {
                display: none;
            }

            .header-actions .btn {
                padding: 10px 16px;
                font-size: 0.82rem;
            }

            .hero h1 {
                font-size: 2.05rem;
            }

            .hero-lead {
                font-size: 0.94rem;
            }

            .hero-actions .btn {
                width: 100%;
            }

            .hero-visual {
                min-height: 400px;
            }

            .orbit {
                display: none;
            }

            .dashboard {
                padding: 14px;
                border-radius: 20px;
            }

            .dash-stats {
                gap: 6px;
            }

            .dash-stat {
                padding: 9px 7px;
            }

            .dash-stat strong {
                font-size: 0.8rem;
            }

            .float-card {
                font-size: 0.62rem;
                padding: 8px 10px;
            }

            .float-export {
                top: 2%;
            }

            .float-paid {
                bottom: 2%;
            }

            .steps-grid {
                grid-template-columns: 1fr;
            }

            .trust-list {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
        }

        @media (prefers-reduced-motion: reduce) {
            html {
                scroll-behavior: auto;
            }

            *,
            *::before,
            *::after {
                animation-duration: 0.001ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.001ms !important;
            }

            .js .reveal {
                opacity: 1;
                transform: none;
            }
        }
    </style>
</head>
<body>
    <a class="skip-link" href="#main">تخطَّ إلى المحتوى</a>

    {{-- ============================ Header ============================ --}}
    <header class="site-header" id="siteHeader">
        <div class="container header-inner">
            <a href="{{ url('/') }}" class="brand" aria-label="نظام المتأخرات — الصفحة الرئيسية">
                <span class="brand-mark" aria-hidden="true">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2.8 4.8 5.7v5.6c0 4.4 3 8.1 7.2 9.9 4.2-1.8 7.2-5.5 7.2-9.9V5.7L12 2.8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M8.6 11.9h2.3l1.1-2.6 1.9 5 1.1-2.4h1.9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="brand-text">
                    <span class="brand-name">نظام المتأخرات</span>
                    <span class="brand-sub">إدارة تقنين الأراضي والأقساط</span>
                </span>
            </a>

            <nav class="nav-links" aria-label="التنقل الرئيسي">
                <a href="#features">المزايا</a>
                <a href="#workflow">آلية العمل</a>
                <a href="#roles">الصلاحيات</a>
                <a href="#access">الدخول</a>
            </nav>

            <div class="header-actions">
                <a href="{{ $loginUrl }}" class="btn btn-primary">
                    تسجيل الدخول
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                        <path d="M19 12H5M11 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </a>
            </div>
        </div>
    </header>

    <main id="main">
        {{-- ============================ Hero ============================ --}}
        <section class="hero">
            <div class="container hero-grid">
                <div class="hero-copy">
                    <span class="eyebrow">
                        <span class="dot" aria-hidden="true"></span>
                        منظومة متكاملة لإدارة التقنين
                    </span>

                    <h1>
                        كل متأخراتك وأقساطك<br>
                        <span class="accent">في مكان واحد</span>
                    </h1>

                    <p class="hero-lead">
                        نظام مركزي لإدارة المواطنين والعقود وجداول الأقساط، ومتابعة المتأخرات لحظة بلحظة،
                        مع استيراد وتصدير Excel وصلاحيات دقيقة وسجل عمليات كامل.
                    </p>

                    <div class="hero-actions">
                        <a href="{{ $loginUrl }}" class="btn btn-primary btn-lg">
                            دخول لوحة التحكم
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M19 12H5M11 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                        <a href="#features" class="btn btn-ghost btn-lg">
                            تعرّف على المزايا
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M12 5v14M5 12l7 7 7-7" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                    </div>

                    <ul class="hero-meta">
                        <li>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            صلاحيات حسب الدور
                        </li>
                        <li>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            سجل عمليات كامل
                        </li>
                        <li>
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                            استيراد وتصدير Excel
                        </li>
                    </ul>
                </div>

                <div class="hero-visual" aria-hidden="true">
                    <div class="visual-glow"></div>
                    <div class="orbit"></div>

                    <div class="dashboard">
                        <div class="dashboard-top">
                            <div class="dashboard-title">
                                <span class="live"></span>
                                ملخص المتأخرات
                            </div>
                            <span class="stamp">نموذج توضيحي</span>
                        </div>

                        <div class="dash-stats">
                            <div class="dash-stat is-gold">
                                <span>إجمالي المتبقي</span>
                                <strong><span class="num">312,500</span> ج.م</strong>
                            </div>
                            <div class="dash-stat is-danger">
                                <span>أقساط متأخرة</span>
                                <strong><span class="num">128</span></strong>
                            </div>
                            <div class="dash-stat">
                                <span>عقود نشطة</span>
                                <strong><span class="num">46</span></strong>
                            </div>
                        </div>

                        <div class="dash-chart">
                            <div class="chart-head">
                                <span>المتأخرات حسب مدة التأخير</span>
                                <b>آخر 7 أشهر</b>
                            </div>
                            <div class="bars">
                                <span class="bar is-muted" style="height: 38%"></span>
                                <span class="bar is-muted" style="height: 54%"></span>
                                <span class="bar" style="height: 46%"></span>
                                <span class="bar" style="height: 72%"></span>
                                <span class="bar is-muted" style="height: 60%"></span>
                                <span class="bar" style="height: 88%"></span>
                                <span class="bar" style="height: 64%"></span>
                            </div>
                            <div class="chart-labels">
                                <span>1</span><span>2</span><span>3</span><span>4</span><span>5</span><span>6</span><span>7</span>
                            </div>
                        </div>

                        <div class="dash-rows">
                            <div class="dash-row">
                                <span class="avatar">ص</span>
                                <span class="who">
                                    <b>صبري محمد</b>
                                    <small>DEMO-001 · 3 سنوات</small>
                                </span>
                                <span class="badge badge-danger">متأخر 94 يوم</span>
                            </div>
                            <div class="dash-row">
                                <span class="avatar">س</span>
                                <span class="who">
                                    <b>سامح أبو العلا</b>
                                    <small>DEMO-002 · 5 سنوات</small>
                                </span>
                                <span class="badge badge-warn">متأخر 61 يوم</span>
                            </div>
                            <div class="dash-row">
                                <span class="avatar">م</span>
                                <span class="who">
                                    <b>محمود إبراهيم</b>
                                    <small>DEMO-003 · 7 سنوات</small>
                                </span>
                                <span class="badge badge-warn">متأخر 38 يوم</span>
                            </div>
                        </div>
                    </div>

                    <div class="float-card float-export">
                        <span class="icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                                <path d="M12 3v12m0 0 4.5-4.5M12 15l-4.5-4.5M4 17v2.5A1.5 1.5 0 0 0 5.5 21h13a1.5 1.5 0 0 0 1.5-1.5V17" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>
                            تصدير المتأخرات
                            <small>Excel · حسب الفلاتر</small>
                        </span>
                    </div>

                    <div class="float-card float-paid">
                        <span class="icon">
                            <svg width="15" height="15" viewBox="0 0 24 24" fill="none">
                                <path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <span>
                            تم تسجيل السداد
                            <small>القسط 3 · عقد DEMO-002</small>
                        </span>
                    </div>
                </div>
            </div>
        </section>

        {{-- ============================ Trust strip ============================ --}}
        <div class="trust">
            <div class="container">
                <ul class="trust-list">
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 7h16M4 12h10M4 17h7" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                        كاش أو تقسيط 3 / 5 / 7 سنوات
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 3v18M5 8h14M7 8v9a5 5 0 0 0 10 0V8" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        متابعة المتأخرات بدون فترة سماح
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 3 4.5 6v6c0 4.4 3.1 7.9 7.5 9 4.4-1.1 7.5-4.6 7.5-9V6L12 3Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                        </svg>
                        صلاحيات مشرف / مسئول تقنين / قيادة
                    </li>
                    <li>
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M12 8v5l3 2M21 12a9 9 0 1 1-9-9 9 9 0 0 1 9 9Z" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        تحديث لحظي لمواعيد الاستحقاق
                    </li>
                </ul>
            </div>
        </div>

        {{-- ============================ Features ============================ --}}
        <section class="section" id="features">
            <div class="container">
                <div class="section-head center reveal">
                    <span class="kicker">المزايا</span>
                    <h2>كل ما تحتاجه لإدارة التقنين</h2>
                    <p>
                        من تسجيل المواطن ونائبه، إلى إنشاء العقد وتوليد جدول الأقساط، ثم متابعة المتأخرات
                        وتسجيل السداد وتصدير التقارير — في منظومة واحدة مترابطة.
                    </p>
                </div>

                <div class="features-grid">
                    <article class="feature reveal" style="--delay: 0.02s">
                        <span class="feature-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <path d="M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                <circle cx="10" cy="7.5" r="3.5" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M17 11.5a3 3 0 0 0 0-5.8M20 19v-1.4a3.4 3.4 0 0 0-2.6-3.3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                            </svg>
                        </span>
                        <h3>المواطنون والنواب</h3>
                        <p>
                            ملف مواطن كامل بالرقم القومي والمركز والعنوان وأرقام الهاتف، مع تتبع النائب
                            الحالي والسجل الزمني للنواب السابقين.
                        </p>
                        <span class="tag">بيانات موحّدة</span>
                    </article>

                    <article class="feature reveal" style="--delay: 0.06s">
                        <span class="feature-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <path d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                <path d="M14 3v5h5M9 13h6M9 17h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <h3>العقود وجداول الأقساط</h3>
                        <p>
                            إنشاء عقود كاش أو تقسيط 3 / 5 / 7 سنوات، مع توليد مواعيد الاستحقاق تلقائيًا
                            من تاريخ الدفعة المقدمة.
                        </p>
                        <span class="tag">توليد تلقائي</span>
                    </article>

                    <article class="feature reveal" style="--delay: 0.1s">
                        <span class="feature-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <path d="M12 9v4M12 17h.01M10.3 3.9 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.9a2 2 0 0 0-3.4 0Z" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <h3>متابعة المتأخرات لحظيًا</h3>
                        <p>
                            شاشة مخصصة تعرض الأقساط المتأخرة وأيام التأخير والمبلغ المتبقي، مع فلترة
                            حسب المركز ومدة التأخير ونوع السداد.
                        </p>
                        <span class="tag">قراءة فقط</span>
                    </article>

                    <article class="feature reveal" style="--delay: 0.02s">
                        <span class="feature-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <rect x="3" y="6" width="18" height="13" rx="2" stroke="currentColor" stroke-width="1.7"/>
                                <path d="M3 10h18M7 15h4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round"/>
                                <circle cx="16.5" cy="15" r="1.3" fill="currentColor"/>
                            </svg>
                        </span>
                        <h3>تسجيل سداد دقيق</h3>
                        <p>
                            سداد القسط بقيمته كاملة دون دفع جزئي أو مكرر، مع إمكانية إلغاء السداد
                            للمشرف العام عند وجود خطأ في الإدخال.
                        </p>
                        <span class="tag">حماية من التكرار</span>
                    </article>

                    <article class="feature reveal" style="--delay: 0.06s">
                        <span class="feature-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <path d="M12 3v12m0 0 4.5-4.5M12 15l-4.5-4.5M4 17v2.5A1.5 1.5 0 0 0 5.5 21h13a1.5 1.5 0 0 0 1.5-1.5V17" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <h3>استيراد وتصدير Excel</h3>
                        <p>
                            استيراد العقود والأقساط من قالب جاهز مع تقرير أخطاء تفصيلي، وتصدير
                            المتأخرات بنفس أعمدة الشاشة والفلاتر المطبقة.
                        </p>
                        <span class="tag">قالب جاهز</span>
                    </article>

                    <article class="feature reveal" style="--delay: 0.1s">
                        <span class="feature-icon" aria-hidden="true">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none">
                                <path d="M12 3 4.5 6v6c0 4.4 3.1 7.9 7.5 9 4.4-1.1 7.5-4.6 7.5-9V6L12 3Z" stroke="currentColor" stroke-width="1.7" stroke-linejoin="round"/>
                                <path d="m9 12 2 2 4-4" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </span>
                        <h3>صلاحيات وسجل عمليات</h3>
                        <p>
                            ثلاثة أدوار واضحة بصلاحيات منفصلة، وسجل تدقيق يوثّق الإضافة والتعديل
                            والسداد وإلغاء السداد على مستوى كل سجل.
                        </p>
                        <span class="tag">تدقيق كامل</span>
                    </article>
                </div>
            </div>
        </section>

        {{-- ============================ Workflow ============================ --}}
        <section class="section workflow" id="workflow">
            <div class="container">
                <div class="section-head reveal">
                    <span class="kicker">آلية العمل</span>
                    <h2>أربع خطوات من البيانات إلى القرار</h2>
                    <p>
                        مسار واضح يبدأ بإدخال البيانات وينتهي بتقرير متأخرات دقيق جاهز للمشاركة.
                    </p>
                </div>

                <div class="steps-grid">
                    <article class="step reveal" style="--delay: 0.02s">
                        <span class="step-num">01</span>
                        <h3>أدخل أو استورد العقود</h3>
                        <p>سجّل المواطن والعقد يدويًا، أو استورد ملف Excel بالقالب المعتمد للنظام.</p>
                    </article>
                    <article class="step reveal" style="--delay: 0.06s">
                        <span class="step-num">02</span>
                        <h3>توليد جدول الأقساط</h3>
                        <p>يحسب النظام مواعيد الاستحقاق من تاريخ المقدم ويوزّع الأقساط حسب نوع السداد.</p>
                    </article>
                    <article class="step reveal" style="--delay: 0.1s">
                        <span class="step-num">03</span>
                        <h3>تابع المتأخرات</h3>
                        <p>راجع الأقساط المتأخرة وأيام التأخير والمبالغ المتبقية، وفلتر حسب المركز أو المدة.</p>
                    </article>
                    <article class="step reveal" style="--delay: 0.14s">
                        <span class="step-num">04</span>
                        <h3>سجّل السداد وصدّر</h3>
                        <p>سجّل سداد الأقساط أولًا بأول، وصدّر تقرير المتأخرات إلى Excel بضغطة واحدة.</p>
                    </article>
                </div>
            </div>
        </section>

        {{-- ============================ Roles ============================ --}}
        <section class="section" id="roles">
            <div class="container">
                <div class="section-head reveal">
                    <span class="kicker">الصلاحيات</span>
                    <h2>لكل دور مسؤولياته بوضوح</h2>
                    <p>
                        يتحكم النظام في الوصول حسب الدور، مع منع الحسابات الموقوفة من الدخول إلى لوحة التحكم.
                    </p>
                </div>

                <div class="roles-grid">
                    <article class="role-card is-featured reveal" style="--delay: 0.02s">
                        <div class="role-head">
                            <h3>مشرف عام</h3>
                            <span class="role-pill">كل الصلاحيات</span>
                        </div>
                        <ul>
                            <li>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                إدارة المستخدمين والأدوار
                            </li>
                            <li>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                إضافة وتعديل وحذف البيانات
                            </li>
                            <li>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                إلغاء السداد عند الخطأ
                            </li>
                        </ul>
                    </article>

                    <article class="role-card reveal" style="--delay: 0.06s">
                        <div class="role-head">
                            <h3>مسئول تقنين</h3>
                            <span class="role-pill">تشغيل يومي</span>
                        </div>
                        <ul>
                            <li>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                إضافة وتعديل المواطنين
                            </li>
                            <li>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                إنشاء العقود ومتابعة الأقساط
                            </li>
                            <li>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                تسجيل السداد والملاحظات
                            </li>
                        </ul>
                    </article>

                    <article class="role-card reveal" style="--delay: 0.1s">
                        <div class="role-head">
                            <h3>قيادة</h3>
                            <span class="role-pill">متابعة</span>
                        </div>
                        <ul>
                            <li>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                عرض جميع الشاشات والتقارير
                            </li>
                            <li>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                تصدير المتأخرات إلى Excel
                            </li>
                            <li>
                                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" aria-hidden="true"><path d="m5 12.5 4.5 4.5L19 7.5" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>
                                متابعة دون تعديل البيانات
                            </li>
                        </ul>
                    </article>
                </div>
            </div>
        </section>

        {{-- ============================ CTA ============================ --}}
        <section class="cta-section" id="access">
            <div class="container">
                <div class="cta-panel reveal">
                    <h2>جاهز تبدأ العمل على نظامك؟</h2>
                    <p>
                        ادخل إلى لوحة التحكم لمتابعة المتأخرات، إدارة العقود، وتسجيل السداد.
                        الدخول متاح للحسابات المفعّلة فقط.
                    </p>
                    <div class="cta-actions">
                        <a href="{{ $loginUrl }}" class="btn btn-primary btn-lg">
                            تسجيل الدخول الآن
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                <path d="M19 12H5M11 18l-6-6 6-6" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </a>
                        <a href="#features" class="btn btn-ghost btn-lg">استعراض المزايا</a>
                    </div>
                    <p class="cta-note">هل تحتاج حسابًا؟ تواصل مع المشرف العام للنظام.</p>
                </div>
            </div>
        </section>
    </main>

    {{-- ============================ Footer ============================ --}}
    <footer class="site-footer">
        <div class="container footer-inner">
            <div class="footer-brand">
                <span class="brand-mark" aria-hidden="true">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none">
                        <path d="M12 2.8 4.8 5.7v5.6c0 4.4 3 8.1 7.2 9.9 4.2-1.8 7.2-5.5 7.2-9.9V5.7L12 2.8Z" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M8.6 11.9h2.3l1.1-2.6 1.9 5 1.1-2.4h1.9" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span>
                    <p>نظام المتأخرات</p>
                    <small>إدارة تقنين الأراضي والأقساط</small>
                </span>
            </div>

            <nav class="footer-links" aria-label="روابط التذييل">
                <a href="#features">المزايا</a>
                <a href="#workflow">آلية العمل</a>
                <a href="#roles">الصلاحيات</a>
                <a href="{{ $loginUrl }}">تسجيل الدخول</a>
            </nav>

            <p class="footer-copy">© <span class="num">{{ date('Y') }}</span> نظام المتأخرات — جميع الحقوق محفوظة.</p>
        </div>
    </footer>

    <script>
        (function () {
            var header = document.getElementById('siteHeader');
            var onScroll = function () {
                header.classList.toggle('is-scrolled', window.scrollY > 12);
            };
            onScroll();
            window.addEventListener('scroll', onScroll, { passive: true });

            var items = document.querySelectorAll('.reveal');
            if (!('IntersectionObserver' in window)) {
                items.forEach(function (item) { item.classList.add('is-visible'); });
                return;
            }

            var observer = new IntersectionObserver(function (entries) {
                entries.forEach(function (entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('is-visible');
                        observer.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.12, rootMargin: '0px 0px -40px 0px' });

            items.forEach(function (item) { observer.observe(item); });
        })();
    </script>
</body>
</html>
