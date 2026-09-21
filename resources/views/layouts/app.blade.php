<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>B2B Outbound Mail &bull; Salesforce Linked</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Google Fonts: Inter & Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap"
        rel="stylesheet">

    <script>
        const themes = {
            indigo: {
                name: 'Linear Indigo (Default)',
                color: '#4f46e5',
                vars: {
                    '--primary-color': '#4f46e5',
                    '--primary-hover': '#4338ca',
                    '--primary-gradient': 'linear-gradient(180deg, #6366f1 0%, #4f46e5 100%)',
                    '--primary-gradient-hover': 'linear-gradient(180deg, #6d70f7 0%, #4338ca 100%)',
                    '--accent-color': '#6366f1',
                    '--accent-glow': 'rgba(99, 102, 241, 0.15)',
                    '--ambient-glow': 'radial-gradient(ellipse 80% 55% at 50% -15%, rgba(99, 102, 241, 0.10), rgba(255, 255, 255, 0)), radial-gradient(circle 500px at 90% 10%, rgba(16, 185, 129, 0.03), transparent)',
                    '--selection-bg': '#6366f1',
                    '--brand-icon-bg': 'linear-gradient(135deg, #6366f1 0%, #4338ca 100%)',
                    '--active-pill-bg': 'linear-gradient(90deg, rgba(238, 242, 255, 0.9) 0%, rgba(245, 247, 255, 0.4) 100%)',
                    '--active-pill-border': 'rgba(199, 210, 254, 0.7)',
                    '--active-pill-color': '#4338ca',
                    '--focus-ring': 'rgba(99, 102, 241, 0.22)',
                    '--title-gradient': 'linear-gradient(135deg, #09090b 25%, #27272a 65%, #4f46e5 100%)',
                    '--badge-primary-bg': '#eef2ff',
                    '--badge-primary-color': '#4338ca',
                    '--badge-primary-border': 'rgba(199, 210, 254, 0.8)',
                    '--shadow-btn-primary': '0 1px 2px 0 rgba(79, 70, 229, 0.25), inset 0 1px 0 0 rgba(255, 255, 255, 0.25)',
                    '--shadow-btn-primary-hover': '0 4px 16px 0 rgba(99, 102, 241, 0.38), inset 0 1px 0 0 rgba(255, 255, 255, 0.3)'
                }
            },
            emerald: {
                name: 'Emerald & Mint',
                color: '#059669',
                vars: {
                    '--primary-color': '#059669',
                    '--primary-hover': '#047857',
                    '--primary-gradient': 'linear-gradient(180deg, #10b981 0%, #059669 100%)',
                    '--primary-gradient-hover': 'linear-gradient(180deg, #34d399 0%, #047857 100%)',
                    '--accent-color': '#10b981',
                    '--accent-glow': 'rgba(16, 185, 129, 0.15)',
                    '--ambient-glow': 'radial-gradient(ellipse 80% 55% at 50% -15%, rgba(16, 185, 129, 0.10), rgba(255, 255, 255, 0)), radial-gradient(circle 500px at 90% 10%, rgba(6, 182, 212, 0.03), transparent)',
                    '--selection-bg': '#10b981',
                    '--brand-icon-bg': 'linear-gradient(135deg, #10b981 0%, #047857 100%)',
                    '--active-pill-bg': 'linear-gradient(90deg, rgba(236, 253, 245, 0.9) 0%, rgba(240, 253, 248, 0.4) 100%)',
                    '--active-pill-border': 'rgba(167, 243, 208, 0.7)',
                    '--active-pill-color': '#047857',
                    '--focus-ring': 'rgba(16, 185, 129, 0.22)',
                    '--title-gradient': 'linear-gradient(135deg, #09090b 25%, #27272a 65%, #059669 100%)',
                    '--badge-primary-bg': '#ecfdf5',
                    '--badge-primary-color': '#047857',
                    '--badge-primary-border': 'rgba(167, 243, 208, 0.8)',
                    '--shadow-btn-primary': '0 1px 2px 0 rgba(5, 150, 105, 0.25), inset 0 1px 0 0 rgba(255, 255, 255, 0.25)',
                    '--shadow-btn-primary-hover': '0 4px 16px 0 rgba(16, 185, 129, 0.38), inset 0 1px 0 0 rgba(255, 255, 255, 0.3)'
                }
            },
            rose: {
                name: 'Electric Rose & Sunset',
                color: '#e11d48',
                vars: {
                    '--primary-color': '#e11d48',
                    '--primary-hover': '#be123c',
                    '--primary-gradient': 'linear-gradient(180deg, #f43f5e 0%, #e11d48 100%)',
                    '--primary-gradient-hover': 'linear-gradient(180deg, #fb7185 0%, #be123c 100%)',
                    '--accent-color': '#f43f5e',
                    '--accent-glow': 'rgba(244, 63, 94, 0.15)',
                    '--ambient-glow': 'radial-gradient(ellipse 80% 55% at 50% -15%, rgba(244, 63, 94, 0.10), rgba(255, 255, 255, 0)), radial-gradient(circle 500px at 90% 10%, rgba(245, 158, 11, 0.03), transparent)',
                    '--selection-bg': '#f43f5e',
                    '--brand-icon-bg': 'linear-gradient(135deg, #f43f5e 0%, #be123c 100%)',
                    '--active-pill-bg': 'linear-gradient(90deg, rgba(255, 241, 242, 0.9) 0%, rgba(255, 245, 246, 0.4) 100%)',
                    '--active-pill-border': 'rgba(254, 205, 211, 0.7)',
                    '--active-pill-color': '#be123c',
                    '--focus-ring': 'rgba(244, 63, 94, 0.22)',
                    '--title-gradient': 'linear-gradient(135deg, #09090b 25%, #27272a 65%, #e11d48 100%)',
                    '--badge-primary-bg': '#fff1f2',
                    '--badge-primary-color': '#be123c',
                    '--badge-primary-border': 'rgba(254, 205, 211, 0.8)',
                    '--shadow-btn-primary': '0 1px 2px 0 rgba(225, 29, 72, 0.25), inset 0 1px 0 0 rgba(255, 255, 255, 0.25)',
                    '--shadow-btn-primary-hover': '0 4px 16px 0 rgba(244, 63, 94, 0.38), inset 0 1px 0 0 rgba(255, 255, 255, 0.3)'
                }
            },
            violet: {
                name: 'Cyber Violet & Purple',
                color: '#7c3aed',
                vars: {
                    '--primary-color': '#7c3aed',
                    '--primary-hover': '#6d28d9',
                    '--primary-gradient': 'linear-gradient(180deg, #8b5cf6 0%, #7c3aed 100%)',
                    '--primary-gradient-hover': 'linear-gradient(180deg, #a78bfa 0%, #6d28d9 100%)',
                    '--accent-color': '#8b5cf6',
                    '--accent-glow': 'rgba(139, 92, 246, 0.15)',
                    '--ambient-glow': 'radial-gradient(ellipse 80% 55% at 50% -15%, rgba(139, 92, 246, 0.10), rgba(255, 255, 255, 0)), radial-gradient(circle 500px at 90% 10%, rgba(236, 72, 153, 0.03), transparent)',
                    '--selection-bg': '#8b5cf6',
                    '--brand-icon-bg': 'linear-gradient(135deg, #8b5cf6 0%, #6d28d9 100%)',
                    '--active-pill-bg': 'linear-gradient(90deg, rgba(245, 243, 255, 0.9) 0%, rgba(250, 248, 255, 0.4) 100%)',
                    '--active-pill-border': 'rgba(221, 214, 254, 0.7)',
                    '--active-pill-color': '#6d28d9',
                    '--focus-ring': 'rgba(139, 92, 246, 0.22)',
                    '--title-gradient': 'linear-gradient(135deg, #09090b 25%, #27272a 65%, #7c3aed 100%)',
                    '--badge-primary-bg': '#f5f3ff',
                    '--badge-primary-color': '#6d28d9',
                    '--badge-primary-border': 'rgba(221, 214, 254, 0.8)',
                    '--shadow-btn-primary': '0 1px 2px 0 rgba(124, 58, 237, 0.25), inset 0 1px 0 0 rgba(255, 255, 255, 0.25)',
                    '--shadow-btn-primary-hover': '0 4px 16px 0 rgba(139, 92, 246, 0.38), inset 0 1px 0 0 rgba(255, 255, 255, 0.3)'
                }
            },
            blue: {
                name: 'Ocean Blue & Royal',
                color: '#2563eb',
                vars: {
                    '--primary-color': '#2563eb',
                    '--primary-hover': '#1d4ed8',
                    '--primary-gradient': 'linear-gradient(180deg, #3b82f6 0%, #2563eb 100%)',
                    '--primary-gradient-hover': 'linear-gradient(180deg, #60a5fa 0%, #1d4ed8 100%)',
                    '--accent-color': '#3b82f6',
                    '--accent-glow': 'rgba(59, 130, 246, 0.15)',
                    '--ambient-glow': 'radial-gradient(ellipse 80% 55% at 50% -15%, rgba(59, 130, 246, 0.10), rgba(255, 255, 255, 0)), radial-gradient(circle 500px at 90% 10%, rgba(6, 182, 212, 0.03), transparent)',
                    '--selection-bg': '#3b82f6',
                    '--brand-icon-bg': 'linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%)',
                    '--active-pill-bg': 'linear-gradient(90deg, rgba(239, 246, 255, 0.9) 0%, rgba(245, 250, 255, 0.4) 100%)',
                    '--active-pill-border': 'rgba(191, 219, 254, 0.7)',
                    '--active-pill-color': '#1d4ed8',
                    '--focus-ring': 'rgba(59, 130, 246, 0.22)',
                    '--title-gradient': 'linear-gradient(135deg, #09090b 25%, #27272a 65%, #2563eb 100%)',
                    '--badge-primary-bg': '#eff6ff',
                    '--badge-primary-color': '#1d4ed8',
                    '--badge-primary-border': 'rgba(191, 219, 254, 0.8)',
                    '--shadow-btn-primary': '0 1px 2px 0 rgba(37, 99, 235, 0.25), inset 0 1px 0 0 rgba(255, 255, 255, 0.25)',
                    '--shadow-btn-primary-hover': '0 4px 16px 0 rgba(59, 130, 246, 0.38), inset 0 1px 0 0 rgba(255, 255, 255, 0.3)'
                }
            },
            amber: {
                name: 'Obsidian & Amber',
                color: '#d97706',
                vars: {
                    '--primary-color': '#d97706',
                    '--primary-hover': '#b45309',
                    '--primary-gradient': 'linear-gradient(180deg, #f59e0b 0%, #d97706 100%)',
                    '--primary-gradient-hover': 'linear-gradient(180deg, #fbbf24 0%, #b45309 100%)',
                    '--accent-color': '#f59e0b',
                    '--accent-glow': 'rgba(245, 158, 11, 0.15)',
                    '--ambient-glow': 'radial-gradient(ellipse 80% 55% at 50% -15%, rgba(245, 158, 11, 0.10), rgba(255, 255, 255, 0)), radial-gradient(circle 500px at 90% 10%, rgba(239, 68, 68, 0.03), transparent)',
                    '--selection-bg': '#f59e0b',
                    '--brand-icon-bg': 'linear-gradient(135deg, #f59e0b 0%, #b45309 100%)',
                    '--active-pill-bg': 'linear-gradient(90deg, rgba(254, 243, 199, 0.9) 0%, rgba(255, 248, 220, 0.4) 100%)',
                    '--active-pill-border': 'rgba(253, 230, 138, 0.7)',
                    '--active-pill-color': '#b45309',
                    '--focus-ring': 'rgba(245, 158, 11, 0.22)',
                    '--title-gradient': 'linear-gradient(135deg, #09090b 25%, #27272a 65%, #d97706 100%)',
                    '--badge-primary-bg': '#fffbeb',
                    '--badge-primary-color': '#b45309',
                    '--badge-primary-border': 'rgba(253, 230, 138, 0.8)',
                    '--shadow-btn-primary': '0 1px 2px 0 rgba(217, 119, 6, 0.25), inset 0 1px 0 0 rgba(255, 255, 255, 0.25)',
                    '--shadow-btn-primary-hover': '0 4px 16px 0 rgba(245, 158, 11, 0.38), inset 0 1px 0 0 rgba(255, 255, 255, 0.3)'
                }
            },
            teal: {
                name: 'Teal & Cyan Wave',
                color: '#0d9488',
                vars: {
                    '--primary-color': '#0d9488',
                    '--primary-hover': '#0f766e',
                    '--primary-gradient': 'linear-gradient(180deg, #14b8a6 0%, #0d9488 100%)',
                    '--primary-gradient-hover': 'linear-gradient(180deg, #2dd4bf 0%, #0f766e 100%)',
                    '--accent-color': '#14b8a6',
                    '--accent-glow': 'rgba(20, 184, 166, 0.15)',
                    '--ambient-glow': 'radial-gradient(ellipse 80% 55% at 50% -15%, rgba(20, 184, 166, 0.10), rgba(255, 255, 255, 0)), radial-gradient(circle 500px at 90% 10%, rgba(59, 130, 246, 0.03), transparent)',
                    '--selection-bg': '#14b8a6',
                    '--brand-icon-bg': 'linear-gradient(135deg, #14b8a6 0%, #0f766e 100%)',
                    '--active-pill-bg': 'linear-gradient(90deg, rgba(204, 251, 241, 0.9) 0%, rgba(220, 253, 246, 0.4) 100%)',
                    '--active-pill-border': 'rgba(153, 246, 228, 0.7)',
                    '--active-pill-color': '#0f766e',
                    '--focus-ring': 'rgba(20, 184, 166, 0.22)',
                    '--title-gradient': 'linear-gradient(135deg, #09090b 25%, #27272a 65%, #0d9488 100%)',
                    '--badge-primary-bg': '#f0fdfa',
                    '--badge-primary-color': '#0f766e',
                    '--badge-primary-border': 'rgba(153, 246, 228, 0.8)',
                    '--shadow-btn-primary': '0 1px 2px 0 rgba(13, 148, 136, 0.25), inset 0 1px 0 0 rgba(255, 255, 255, 0.25)',
                    '--shadow-btn-primary-hover': '0 4px 16px 0 rgba(20, 184, 166, 0.38), inset 0 1px 0 0 rgba(255, 255, 255, 0.3)'
                }
            },
            monochrome: {
                name: 'Minimal Charcoal',
                color: '#18181b',
                vars: {
                    '--primary-color': '#18181b',
                    '--primary-hover': '#09090b',
                    '--primary-gradient': 'linear-gradient(180deg, #27272a 0%, #18181b 100%)',
                    '--primary-gradient-hover': 'linear-gradient(180deg, #3f3f46 0%, #09090b 100%)',
                    '--accent-color': '#71717a',
                    '--accent-glow': 'rgba(24, 24, 27, 0.15)',
                    '--ambient-glow': 'radial-gradient(ellipse 80% 55% at 50% -15%, rgba(0, 0, 0, 0.04), rgba(255, 255, 255, 0))',
                    '--selection-bg': '#18181b',
                    '--brand-icon-bg': 'linear-gradient(135deg, #27272a 0%, #09090b 100%)',
                    '--active-pill-bg': '#f4f4f5',
                    '--active-pill-border': '#e4e4e7',
                    '--active-pill-color': '#09090b',
                    '--focus-ring': 'rgba(24, 24, 27, 0.18)',
                    '--title-gradient': 'linear-gradient(135deg, #09090b 40%, #3f3f46 100%)',
                    '--badge-primary-bg': '#f4f4f5',
                    '--badge-primary-color': '#18181b',
                    '--badge-primary-border': '#e4e4e7',
                    '--shadow-btn-primary': '0 1px 2px 0 rgba(0, 0, 0, 0.15), inset 0 1px 0 0 rgba(255, 255, 255, 0.15)',
                    '--shadow-btn-primary-hover': '0 4px 14px 0 rgba(0, 0, 0, 0.25)'
                }
            }
        };

        function changeTheme(themeName) {
            const selected = themes[themeName] || themes.indigo;
            const root = document.documentElement;
            for (const [key, value] of Object.entries(selected.vars)) {
                root.style.setProperty(key, value);
            }
            localStorage.setItem('selected-theme', themeName);

            document.querySelectorAll('.palette-check').forEach(el => el.classList.add('d-none'));
            const activeIndicator = document.getElementById(`theme-check-${themeName}`);
            if (activeIndicator) activeIndicator.classList.remove('d-none');
        }

        const savedTheme = localStorage.getItem('selected-theme') || 'indigo';
        changeTheme(savedTheme);
    </script>
    <style>
        :root {
            --primary-color: #4f46e5;
            --primary-hover: #4338ca;
            --primary-gradient: linear-gradient(180deg, #6366f1 0%, #4f46e5 100%);
            --primary-gradient-hover: linear-gradient(180deg, #6d70f7 0%, #4338ca 100%);
            --accent-color: #6366f1;
            --accent-glow: rgba(99, 102, 241, 0.15);
            --ambient-glow: radial-gradient(ellipse 80% 55% at 50% -15%, rgba(99, 102, 241, 0.09), rgba(255, 255, 255, 0)), radial-gradient(circle 500px at 90% 10%, rgba(16, 185, 129, 0.03), transparent);
            --selection-bg: #6366f1;
            --brand-icon-bg: linear-gradient(135deg, #6366f1 0%, #4338ca 100%);
            --active-pill-bg: linear-gradient(90deg, rgba(238, 242, 255, 0.9) 0%, rgba(245, 247, 255, 0.4) 100%);
            --active-pill-border: rgba(199, 210, 254, 0.7);
            --active-pill-color: #4338ca;
            --focus-ring: rgba(99, 102, 241, 0.2);
            --title-gradient: linear-gradient(135deg, #09090b 25%, #27272a 65%, #4f46e5 100%);
            --badge-primary-bg: #eef2ff;
            --badge-primary-color: #4338ca;
            --badge-primary-border: rgba(199, 210, 254, 0.8);
            --body-bg: #fafafa;
            --card-bg: #ffffff;
            --sidebar-bg: #ffffff;
            --border-color: #e4e4e7;
            --border-subtle: #f4f4f5;
            --text-color: #09090b;
            --text-secondary: #71717a;
            --text-tertiary: #a1a1aa;
            --radius-card: 14px;
            --radius-btn: 8px;
            --radius-input: 8px;
            --shadow-subtle: 0 1px 3px 0 rgba(0, 0, 0, 0.03), 0 1px 2px -1px rgba(0, 0, 0, 0.02);
            --shadow-hover: 0 8px 20px -4px rgba(99, 102, 241, 0.12), 0 3px 8px -2px rgba(0, 0, 0, 0.04);
            --shadow-btn-primary: 0 1px 2px 0 rgba(79, 70, 229, 0.25), inset 0 1px 0 0 rgba(255, 255, 255, 0.25);
            --shadow-btn-primary-hover: 0 4px 16px 0 rgba(99, 102, 241, 0.38), inset 0 1px 0 0 rgba(255, 255, 255, 0.3);
        }

        * {
            box-sizing: border-box;
        }

        ::selection {
            background-color: var(--selection-bg, #6366f1) !important;
            color: #ffffff !important;
        }

        ::-moz-selection {
            background-color: var(--selection-bg, #6366f1) !important;
            color: #ffffff !important;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Plus Jakarta Sans', sans-serif;
            background-color: var(--body-bg);
            background-image: var(--ambient-glow);
            background-repeat: no-repeat;
            background-attachment: fixed;
            color: var(--text-color);
            min-height: 100vh;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            letter-spacing: -0.011em;
            font-size: 0.875rem;
            line-height: 1.5;
        }

        /* Modern Sidebar Navigation */
        .sidebar {
            width: 240px;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            padding: 20px 14px;
            background-color: var(--sidebar-bg);
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 0 10px 18px 10px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 14px;
        }

        .brand-icon {
            width: 28px;
            height: 28px;
            border-radius: 8px;
            background: var(--brand-icon-bg, linear-gradient(135deg, #6366f1 0%, #4338ca 100%));
            display: flex;
            align-items: center;
            justify-content: center;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
            flex-shrink: 0;
        }

        .brand-text {
            font-family: 'Plus Jakarta Sans', sans-serif;
            font-size: 0.95rem;
            font-weight: 700;
            letter-spacing: -0.02em;
            color: #09090b;
        }

        .sidebar .nav-link {
            font-size: 0.8125rem;
            font-weight: 500;
            color: #52525b;
            padding: 7px 10px;
            border-radius: 6px;
            margin-bottom: 2px;
            display: flex;
            align-items: center;
            gap: 9px;
            transition: all 0.15s ease;
            border: 1px solid transparent;
        }

        .sidebar .nav-link i {
            font-size: 0.95rem;
            color: #71717a;
            transition: color 0.15s ease;
        }

        .sidebar .nav-link:hover {
            color: #09090b;
            background-color: #f4f4f5;
        }

        .sidebar .nav-link:hover i {
            color: #18181b;
        }

        .sidebar .nav-link.active {
            color: var(--active-pill-color, #4338ca);
            background: var(--active-pill-bg, #eef2ff);
            border-color: var(--active-pill-border, #c7d2fe);
            font-weight: 600;
        }

        .sidebar .nav-link.active i {
            color: var(--active-pill-color, #4338ca);
        }

        .sidebar .nav-item-header {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            color: var(--text-tertiary);
            padding: 14px 10px 4px 10px;
        }

        /* Top Sticky Header */
        .navbar-custom {
            height: 52px;
            background-color: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
            margin-left: 240px;
            padding: 0 28px;
            z-index: 90;
        }

        /* Main Content Container */
        .main-content {
            margin-left: 240px;
            padding: 24px 28px 48px 28px;
            min-height: calc(100vh - 52px);
        }

        /* Sleek Cards */
        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: var(--radius-card);
            box-shadow: var(--shadow-subtle);
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .card:hover {
            border-color: #d4d4d8;
        }

        .card-header {
            background-color: transparent;
            border-bottom: 1px solid var(--border-color);
            padding: 12px 18px;
            font-weight: 600;
            font-size: 0.8125rem;
            color: #09090b;
        }

        .card-body {
            padding: 18px;
        }

        /* Inputs & Form Controls */
        .form-control,
        .form-select {
            font-size: 0.8125rem;
            border-radius: var(--radius-input);
            border: 1px solid var(--border-color);
            background-color: #ffffff;
            color: #09090b;
            padding: 0.45rem 0.75rem;
            transition: border-color 0.15s ease, box-shadow 0.15s ease;
        }

        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary-color, #4f46e5);
            box-shadow: 0 0 0 3px var(--focus-ring, rgba(99, 102, 241, 0.15));
            outline: none;
        }

        .form-label {
            font-size: 0.75rem;
            font-weight: 600;
            color: #3f3f46;
            letter-spacing: -0.005em;
        }

        /* Buttons */
        .btn {
            font-size: 0.8125rem;
            font-weight: 500;
            border-radius: var(--radius-btn);
            padding: 0.45rem 0.85rem;
            letter-spacing: -0.01em;
            transition: all 0.15s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
        }

        .btn-sm {
            padding: 0.35rem 0.7rem;
            font-size: 0.75rem;
            border-radius: 6px;
        }

        .btn-xs {
            padding: 0.22rem 0.55rem;
            font-size: 0.7rem;
            border-radius: 5px;
        }

        .btn-primary {
            background: var(--primary-gradient, linear-gradient(180deg, #6366f1 0%, #4f46e5 100%));
            border: 1px solid var(--primary-color, #4f46e5);
            color: #ffffff;
            box-shadow: var(--shadow-btn-primary);
        }

        .btn-primary:hover {
            background: var(--primary-gradient-hover, linear-gradient(180deg, #6d70f7 0%, #4338ca 100%));
            border-color: var(--primary-hover, #4338ca);
            color: #ffffff;
            box-shadow: var(--shadow-btn-primary-hover);
        }

        .btn-outline-secondary {
            border-color: var(--border-color);
            color: #3f3f46;
            background: #ffffff;
        }

        .btn-outline-secondary:hover {
            background-color: #f4f4f5;
            border-color: #d4d4d8;
            color: #09090b;
        }

        /* Tables */
        .table {
            --bs-table-bg: transparent;
            font-size: 0.8125rem;
            color: #27272a;
        }

        .table thead th {
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--text-secondary);
            border-bottom: 1px solid var(--border-color);
            background-color: #fafafa;
            padding: 10px 14px;
        }

        .table tbody td {
            padding: 11px 14px;
            border-bottom: 1px solid var(--border-subtle);
            vertical-align: middle;
        }

        .table-hover tbody tr:hover {
            background-color: #f9fafb;
        }

        /* Badges */
        .badge {
            font-weight: 500;
            padding: 0.25rem 0.55rem;
            font-size: 0.7rem;
            border-radius: 999px;
            letter-spacing: 0.01em;
        }

        .bg-primary-soft {
            background-color: var(--badge-primary-bg, #eef2ff);
            color: var(--badge-primary-color, #4338ca);
            border: 1px solid var(--badge-primary-border, rgba(199, 210, 254, 0.7));
        }

        .bg-success-soft {
            background-color: #ecfdf5;
            color: #047857;
            border: 1px solid rgba(167, 243, 208, 0.7);
        }

        .bg-danger-soft {
            background-color: #fff1f2;
            color: #be123c;
            border: 1px solid rgba(254, 205, 211, 0.7);
        }

        .bg-warning-soft {
            background-color: #fffbeb;
            color: #b45309;
            border: 1px solid rgba(253, 230, 138, 0.7);
        }

        .bg-secondary-soft {
            background-color: #f4f4f5;
            color: #52525b;
            border: 1px solid #e4e4e7;
        }

        /* Subtle scrollbars */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }

        ::-webkit-scrollbar-track {
            background: transparent;
        }

        ::-webkit-scrollbar-thumb {
            background: #d4d4d8;
            border-radius: 999px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #a1a1aa;
        }

        .gradient-heading {
            background: var(--title-gradient, linear-gradient(135deg, #09090b 25%, #27272a 65%, #4f46e5 100%));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
    </style>
    @yield('extra_head')
</head>
<body>

    @if(session('user_id') || Auth::check())
    <!-- Sidebar Navigation -->
    <aside class="sidebar">
        <div class="sidebar-brand">
            <div class="brand-icon">
                <i class="bi bi-send-fill" style="font-size: 0.75rem;"></i>
            </div>
            <span class="brand-text">B2B Mails</span>
        </div>

        <ul class="nav flex-column mt-2">
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('dashboard_view') ? 'active' : '' }}"
                    href="{{ route('dashboard_view') }}">
                    <i class="bi bi-grid"></i> Dashboard
                </a>
            </li>
            @canany(['bulk-mail.create', 'bulk-mail', 'campaign.new', 'campaign.create', '/campaign/new'])
            <li class="nav-item">
                <a class="nav-link {{ (request()->routeIs('campaign_create_view') && request()->query('mode') !== 'paste' && !request()->routeIs('campaign_bulk_view')) ? 'active' : '' }}"
                    href="{{ route('campaign_create_view') }}">
                    <i class="bi bi-pencil-square"></i> Create Campaign
                </a>
            </li>
            @endcanany
            @can('bulk-mail')
            <li class="nav-item">
                <a class="nav-link {{ (request()->routeIs('campaign_bulk_view') || request()->query('mode') === 'paste') ? 'active' : '' }}"
                    href="{{ route('campaign_bulk_view') }}">
                    <i class="bi bi-envelope-at"></i> Bulk Email
                </a>
            </li>
            @endcan
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('campaign_list_view') ? 'active' : '' }}"
                    href="{{ route('campaign_list_view') }}">
                    <i class="bi bi-envelope-paper"></i> My Campaigns
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('signatures.*') ? 'active' : '' }}"
                    href="{{ route('signatures.index') }}">
                    <i class="bi bi-pen"></i> Signatures
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('replies_view') ? 'active' : '' }}"
                    href="{{ route('replies_view') }}">
                    <i class="bi bi-inbox"></i> Inbound Replies
                </a>
            </li>
            {{-- <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('salesforce.leads*') ? 'active' : '' }}"
                    href="{{ route('salesforce.leads') }}">
                    <i class="bi bi-person-lines-fill"></i> Salesforce Leads
                </a>
            </li> --}}

            @php
            $userRole = session('role') ?? (Auth::check() ? Auth::user()->role : 'user');
            $username = session('username') ?? (Auth::check() ? Auth::user()->username : 'User');
            $currentUser = Auth::user() ?: \App\Models\User::find(session('user_id'));
            $isTeamManager = $currentUser ? $currentUser->isTeamManager() : false;
            $isAdmin = $userRole === 'admin' || ($currentUser && method_exists($currentUser, 'hasRole') && $currentUser->hasRole('Admin'));
            @endphp

            @if(in_array($userRole, ['admin', 'manager']) || $isTeamManager || ($currentUser && method_exists($currentUser, 'hasRole') && $currentUser->hasRole('Manager')))
            <li class="nav-item-header">Management</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('team_campaigns_view') ? 'active' : '' }}"
                    href="{{ route('team_campaigns_view') }}">
                    <i class="bi bi-shield-check"></i> Team Campaigns
                </a>
            </li>
            @endif

            @if($isAdmin)
            <li class="nav-item-header">Administration</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}"
                    href="{{ route('admin.roles.index') }}">
                    <i class="bi bi-shield-lock"></i> Roles & Permissions
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin_users_view') ? 'active' : '' }}"
                    href="{{ route('admin_users_view') }}">
                    <i class="bi bi-people"></i> Users & Limits
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin_infrastructure_view') ? 'active' : '' }}"
                    href="{{ route('admin_infrastructure_view') }}">
                    <i class="bi bi-hdd-network"></i> IPs & Domains
                </a>
            </li>
            <li class="nav-item-header">Salesforce Sync</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.salesforce_leads*') ? 'active' : '' }}"
                    href="{{ route('admin.salesforce_leads.index') }}">
                    <i class="bi bi-funnel"></i> Synced Leads
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.salesforce_accounts*') ? 'active' : '' }}"
                    href="{{ route('admin.salesforce_accounts.index') }}">
                    <i class="bi bi-buildings"></i> Synced Accounts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.salesforce_contacts*') ? 'active' : '' }}"
                    href="{{ route('admin.salesforce_contacts.index') }}">
                    <i class="bi bi-person-rolodex"></i> Synced Contacts
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.salesforce_users*') ? 'active' : '' }}"
                    href="{{ route('admin.salesforce_users.index') }}">
                    <i class="bi bi-person-gear"></i> Synced Standard Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('admin.salesforce_sf_users*') ? 'active' : '' }}"
                    href="{{ route('admin.salesforce_sf_users.index') }}">
                    <i class="bi bi-person-badge"></i> Synced SF Users (SF_User__c)
                </a>
            </li>
            @endif

            <li class="nav-item-header">Developer Tools</li>
            <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('simulator_view') ? 'active' : '' }}"
                    href="{{ route('simulator_view') }}">
                    <i class="bi bi-terminal"></i> Client Simulator
                </a>
            </li>
            {{-- <li class="nav-item">
                <a class="nav-link {{ request()->routeIs('salesforce.connection_test') ? 'active' : '' }}"
                    href="{{ route('salesforce.connection_test') }}">
                    <i class="bi bi-shield-check"></i> SF Connection Test
                </a>
            </li> --}}
        </ul>
    </aside>

    <!-- Top Sticky Header -->
    <header class="navbar navbar-expand-lg navbar-custom sticky-top">
        <div class="container-fluid px-0">
            <div class="d-flex align-items-center gap-2">
                <h5 class="mb-0 fs-6 fw-semibold gradient-heading">
                    @yield('page_title', 'Dashboard')
                </h5>
            </div>

            <div class="d-flex align-items-center gap-2">
                <!-- Theme Palette Selector -->
                <div class="dropdown">
                    <button class="btn btn-outline-secondary btn-sm" type="button" id="themeDropdown"
                        data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-palette"></i> Palette
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="themeDropdown"
                        style="min-width: 220px;">
                        <li>
                            <h6 class="dropdown-header text-uppercase text-secondary fw-semibold"
                                style="font-size: 0.65rem; letter-spacing: 0.05em;">Color Theme</h6>
                        </li>
                        <li>
                            <a class="dropdown-item fw-medium d-flex align-items-center justify-content-between py-1.5"
                                href="#" onclick="changeTheme('indigo')">
                                <span><i class="bi bi-circle-fill me-2" style="color: #4f46e5;"></i>Linear Indigo</span>
                                <i class="bi bi-check-lg text-indigo-600 palette-check d-none"
                                    id="theme-check-indigo"></i>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item fw-medium d-flex align-items-center justify-content-between py-1.5"
                                href="#" onclick="changeTheme('emerald')">
                                <span><i class="bi bi-circle-fill me-2" style="color: #059669;"></i>Emerald &
                                    Mint</span>
                                <i class="bi bi-check-lg text-emerald-600 palette-check d-none"
                                    id="theme-check-emerald"></i>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item fw-medium d-flex align-items-center justify-content-between py-1.5"
                                href="#" onclick="changeTheme('rose')">
                                <span><i class="bi bi-circle-fill me-2" style="color: #e11d48;"></i>Electric Rose</span>
                                <i class="bi bi-check-lg text-rose-600 palette-check d-none" id="theme-check-rose"></i>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item fw-medium d-flex align-items-center justify-content-between py-1.5"
                                href="#" onclick="changeTheme('violet')">
                                <span><i class="bi bi-circle-fill me-2" style="color: #7c3aed;"></i>Cyber Violet</span>
                                <i class="bi bi-check-lg text-violet-600 palette-check d-none"
                                    id="theme-check-violet"></i>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item fw-medium d-flex align-items-center justify-content-between py-1.5"
                                href="#" onclick="changeTheme('blue')">
                                <span><i class="bi bi-circle-fill me-2" style="color: #2563eb;"></i>Ocean Blue</span>
                                <i class="bi bi-check-lg text-blue-600 palette-check d-none" id="theme-check-blue"></i>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item fw-medium d-flex align-items-center justify-content-between py-1.5"
                                href="#" onclick="changeTheme('amber')">
                                <span><i class="bi bi-circle-fill me-2" style="color: #d97706;"></i>Obsidian &
                                    Amber</span>
                                <i class="bi bi-check-lg text-amber-600 palette-check d-none"
                                    id="theme-check-amber"></i>
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item fw-medium d-flex align-items-center justify-content-between py-1.5"
                                href="#" onclick="changeTheme('teal')">
                                <span><i class="bi bi-circle-fill me-2" style="color: #0d9488;"></i>Teal & Cyan
                                    Wave</span>
                                <i class="bi bi-check-lg text-teal-600 palette-check d-none" id="theme-check-teal"></i>
                            </a>
                        </li>
                        <li>
                            <hr class="dropdown-divider my-1">
                        </li>
                        <li>
                            <a class="dropdown-item fw-medium d-flex align-items-center justify-content-between py-1.5"
                                href="#" onclick="changeTheme('monochrome')">
                                <span><i class="bi bi-circle-fill me-2" style="color: #18181b;"></i>Minimal
                                    Charcoal</span>
                                <i class="bi bi-check-lg text-dark palette-check d-none"
                                    id="theme-check-monochrome"></i>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- User Account Pill -->
                <div class="d-flex align-items-center gap-2 px-2.5 py-1 bg-white border rounded-pill text-zinc-800"
                    style="font-size: 0.78rem; box-shadow: 0 1px 3px rgba(0,0,0,0.02);">
                    <div class="rounded-circle text-white d-flex align-items-center justify-content-center fw-bold"
                        style="width: 22px; height: 22px; font-size: 0.65rem; background: linear-gradient(135deg, #6366f1 0%, #4338ca 100%); box-shadow: 0 1px 4px rgba(99,102,241,0.3);">
                        {{ strtoupper(substr($username, 0, 1)) }}
                    </div>
                    <span class="fw-semibold text-zinc-900">{{ $username }}</span>
                    <span class="text-zinc-300">&bull;</span>
                    <span class="text-zinc-500 font-medium">
                        @if($userRole === 'admin') Admin @elseif($userRole === 'manager') Manager @else Sender @endif
                    </span>
                </div>

                <!-- Logout Action -->
                <a href="{{ route('logout') }}" class="btn btn-outline-secondary btn-sm" title="Log out">
                    <i class="bi bi-box-arrow-right"></i>
                </a>
            </div>
        </div>
    </header>
    @endif

    <!-- Main App Container -->
    <main class="{{ (session('user_id') || Auth::check()) ? 'main-content' : 'container mt-5 pt-4' }}">

        <!-- Flash Alert Messages -->
        <div class="container-fluid px-0 mb-3">
            @foreach (['success', 'danger', 'warning', 'info'] as $msg)
            @if(session($msg))
            <div class="alert alert-{{ $msg }} alert-dismissible fade show d-flex align-items-center gap-2"
                role="alert">
                <i class="bi bi-info-circle-fill"></i>
                <div>{{ session($msg) }}</div>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert" aria-label="Close"
                    style="font-size: 0.7rem;"></button>
            </div>
            @endif
            @endforeach
        </div>

        @yield('content')
    </main>

    <!-- Bootstrap Bundle JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    @yield('extra_scripts')
</body>

</html>
