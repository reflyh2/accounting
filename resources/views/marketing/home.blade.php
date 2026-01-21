<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scroll-smooth">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FinfasPro - Software Akuntansi & ERP Indonesia | Aplikasi Pembukuan Bisnis</title>
    <meta name="description"
        content="FinfasPro adalah software akuntansi dan ERP terbaik untuk bisnis Indonesia. Kelola pembukuan, faktur, inventaris, dan laporan keuangan dalam satu platform terintegrasi.">
    <meta name="keywords"
        content="software akuntansi, aplikasi pembukuan, ERP Indonesia, software keuangan, aplikasi faktur, sistem akuntansi online, software bisnis">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">

    <!-- Fonts: Inter & Space Grotesk for that premium feel -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&family=Space+Grotesk:wght@400;500;600;700&display=swap"
        rel="stylesheet">

    @vite(['resources/css/app.css'])

    <style>
        :root {
            --bg-color: #0f172a;
            /* Slate 900 - Deep Blue */
            --text-color: #f8fafc;
            /* Slate 50 */
            --accent-color: #38bdf8;
            /* Sky 400 */
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--bg-color);
            /* Add a subtle gradient mesh for depth */
            background-image:
                radial-gradient(at 0% 0%, rgba(56, 189, 248, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%);
            background-attachment: fixed;
            color: var(--text-color);
            overflow-x: hidden;
            cursor: none;
        }

        h1,
        h2,
        h3,
        h4,
        .font-display {
            font-family: 'Space Grotesk', sans-serif;
        }

        /* Smooth Scroll (Lenis) recommended CSS */
        html.lenis {
            height: auto;
        }

        .lenis.lenis-smooth {
            scroll-behavior: auto;
        }

        .lenis.lenis-smooth [data-lenis-prevent] {
            overscroll-behavior: contain;
        }

        .lenis.lenis-stopped {
            overflow: hidden;
        }

        .lenis.lenis-scrolling iframe {
            pointer-events: none;
        }

        /* Custom Cursor */
        .cursor-dot,
        .cursor-outline {
            position: fixed;
            top: 0;
            left: 0;
            transform: translate(-50%, -50%);
            border-radius: 50%;
            z-index: 9999;
            pointer-events: none;
        }

        .cursor-dot {
            width: 8px;
            height: 8px;
            background-color: var(--accent-color);
            box-shadow: 0 0 10px var(--accent-color);
        }

        .cursor-outline {
            width: 40px;
            height: 40px;
            border: 1px solid rgba(56, 189, 248, 0.5);
            transition: width 0.2s, height 0.2s, background-color 0.2s;
        }

        /* Utilities */
        .text-huge {
            font-size: clamp(3rem, 8vw, 10rem);
            line-height: 0.9;
            letter-spacing: -0.04em;
        }

        .text-outline {
            -webkit-text-stroke: 1px rgba(255, 255, 255, 0.3);
            color: transparent;
        }

        .glass-panel {
            background: rgba(30, 41, 59, 0.4);
            /* Slate 800 with opacity */
            backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: 0 4px 30px rgba(0, 0, 0, 0.1);
        }

        /* Loader */
        .loader {
            position: fixed;
            inset: 0;
            background: #0f172a;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            justify-content: center;
            text-align: center;
            items-center;
            font-family: 'Space Grotesk';
            color: var(--accent-color);
            pointer-events: none;
        }

        .loader-text {
            font-size: clamp(3rem, 10vw, 15rem);
            font-weight: 800;
            letter-spacing: -0.05em;
            line-height: 1;
        }

        .loader-text .accent {
            color: var(--text-color);
        }
    </style>

    <!-- Scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.4/gsap.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/gsap/3.12.4/ScrollTrigger.min.js"></script>
    <script src="https://unpkg.com/@studio-freight/lenis@1.0.34/dist/lenis.min.js"></script>

    <!-- Three.js Import Map -->
    <script type="importmap">
        { "imports": { "three": "https://unpkg.com/three@0.160.0/build/three.module.js" } }
    </script>
</head>

<body>

    <!-- Loading Screen -->
    <div class="loader">
        <span class="loader-text block">FINFAS<span class="accent">PRO</span></span>
    </div>

    <!-- Custom Cursor -->
    <div class="cursor-dot"></div>
    <div class="cursor-outline"></div>

    <!-- Navigation -->
    <nav class="fixed w-full z-50 py-4 text-white">
        <div
            class="max-w-[92%] mx-auto bg-slate-900/70 backdrop-blur-xl rounded-2xl px-6 py-3 border border-white/10 shadow-lg shadow-black/20">
            <div class="flex justify-between items-center">
                <div class="flex">
                    <div class="shrink-0 flex items-center">
                        <a href="#">
                            <svg class="block h-9 w-auto fill-current text-gray-800" viewBox="0 0 130 40"
                                xmlns="http://www.w3.org/2000/svg">
                                <defs>
                                    <linearGradient id="navGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                        <stop offset="0%" style="stop-color:#60a5fa" />
                                        <stop offset="100%" style="stop-color:#818cf8" />
                                    </linearGradient>
                                </defs>
                                <!-- F Icon Mark -->
                                <g transform="translate(0, 4)">
                                    <path d="M4 0h18a4 4 0 0 1 4 4v24a4 4 0 0 1-4 4H4a4 4 0 0 1-4-4V4a4 4 0 0 1 4-4z"
                                        fill="url(#navGradient)" />
                                    <path d="M8 8h12v3H11v4h7v3h-7v8H8V8z" fill="white" />
                                </g>
                                <!-- FinfasPro Text -->
                                <text x="34" y="28" font-family="Inter, system-ui, sans-serif" font-size="20"
                                    font-weight="700" fill="url(#navGradient)">
                                    <tspan>Finfas</tspan><tspan font-weight="500" fill="#f8fafc">Pro</tspan>
                                </text>
                            </svg>
                        </a>
                    </div>
                </div>

                <!-- Mobile Menu Button -->
                <button id="mobile-menu-btn" class="md:hidden p-2 rounded-lg hover:bg-white/10 transition-colors">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path id="menu-icon" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M4 6h16M4 12h16M4 18h16"></path>
                    </svg>
                </button>

                <div class="hidden md:flex items-center space-x-12">
                    <a href="#features"
                        class="hover:text-blue-300 transition-colors uppercase text-sm tracking-widest font-medium">{{ __('marketing.nav.features') }}</a>
                    <a href="#how-it-works"
                        class="hover:text-blue-300 transition-colors uppercase text-sm tracking-widest font-medium">{{ __('marketing.nav.how_it_works') }}</a>
                    <a href="#faq"
                        class="hover:text-blue-300 transition-colors uppercase text-sm tracking-widest font-medium">FAQ</a>

                    <div class="flex items-center space-x-6">
                        <!-- Lang Switcher (Dropdown) -->
                        <div class="relative group">
                            <button
                                class="font-display font-bold uppercase hover:text-blue-300 transition-colors flex items-center space-x-1">
                                <span>{{ app()->getLocale() }}</span>
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div
                                class="absolute right-0 top-full mt-2 w-32 bg-slate-800 border border-blue-500/30 rounded-lg shadow-xl opacity-0 invisible group-hover:opacity-100 group-hover:visible transition-all duration-300 overflow-hidden transform group-hover:translate-y-0 translate-y-2">
                                <a href="{{ route('lang.switch', 'en') }}"
                                    class="block px-4 py-2 hover:bg-blue-600/30 text-sm tracking-wider uppercase {{ app()->getLocale() === 'en' ? 'text-blue-400 font-bold' : 'text-blue-100/70' }}">English</a>
                                <a href="{{ route('lang.switch', 'id') }}"
                                    class="block px-4 py-2 hover:bg-blue-600/30 text-sm tracking-wider uppercase {{ app()->getLocale() === 'id' ? 'text-blue-400 font-bold' : 'text-blue-100/70' }}">Indonesia</a>
                            </div>
                        </div>

                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/dashboard') }}"
                                    class="uppercase text-sm tracking-widest border border-blue-400/30 px-6 py-2 rounded-full hover:bg-blue-600 hover:border-blue-600 transition-all shadow-lg shadow-blue-900/20">{{ __('marketing.nav.dashboard') }}</a>
                            @else
                                <a href="{{ route('central.login') }}"
                                    class="uppercase text-sm tracking-widest hover:text-blue-300">{{ __('marketing.nav.login') }}</a>
                                @if (Route::has('register'))
                                    <a href="#" onclick="event.preventDefault(); alert('Demo booking form coming soon!');"
                                        class="uppercase text-sm tracking-widest bg-blue-600 text-white px-6 py-2 rounded-full hover:bg-blue-500 hover:scale-105 transition-all shadow-lg shadow-blue-500/30">{{ __('marketing.nav.book_demo') }}</a>
                                @endif
                            @endauth
                        @endif
                    </div>
                </div>
            </div>

            <!-- Mobile Menu Panel -->
            <div id="mobile-menu" class="hidden md:hidden mt-4 pt-4 border-t border-white/10">
                <div class="flex flex-col space-y-4">
                    <a href="#features"
                        class="mobile-link py-2 text-blue-100 hover:text-blue-300 transition-colors uppercase text-sm tracking-widest font-medium">{{ __('marketing.nav.features') }}</a>
                    <a href="#how-it-works"
                        class="mobile-link py-2 text-blue-100 hover:text-blue-300 transition-colors uppercase text-sm tracking-widest font-medium">{{ __('marketing.nav.how_it_works') }}</a>
                    <a href="#faq"
                        class="mobile-link py-2 text-blue-100 hover:text-blue-300 transition-colors uppercase text-sm tracking-widest font-medium">FAQ</a>

                    <div class="flex items-center space-x-4 py-2">
                        <span
                            class="text-blue-200/60 text-sm uppercase tracking-wider">{{ __('marketing.nav.language') ?? 'Language' }}:</span>
                        <a href="{{ route('lang.switch', 'en') }}"
                            class="text-sm uppercase tracking-wider {{ app()->getLocale() === 'en' ? 'text-blue-400 font-bold' : 'text-blue-100/70' }}">EN</a>
                        <span class="text-blue-200/40">|</span>
                        <a href="{{ route('lang.switch', 'id') }}"
                            class="text-sm uppercase tracking-wider {{ app()->getLocale() === 'id' ? 'text-blue-400 font-bold' : 'text-blue-100/70' }}">ID</a>
                    </div>

                    <div class="flex flex-col space-y-3 pt-2">
                        @if (Route::has('login'))
                            @auth
                                <a href="{{ url('/dashboard') }}"
                                    class="text-center uppercase text-sm tracking-widest border border-blue-400/30 px-6 py-3 rounded-full hover:bg-blue-600 hover:border-blue-600 transition-all">{{ __('marketing.nav.dashboard') }}</a>
                            @else
                                <a href="{{ route('central.login') }}"
                                    class="text-center uppercase text-sm tracking-widest border border-blue-400/30 px-6 py-3 rounded-full hover:bg-blue-600 hover:border-blue-600 transition-all">{{ __('marketing.nav.login') }}</a>
                                @if (Route::has('register'))
                                    <a href="#" onclick="event.preventDefault(); alert('Demo booking form coming soon!');"
                                        class="text-center uppercase text-sm tracking-widest bg-blue-600 text-white px-6 py-3 rounded-full hover:bg-blue-500 transition-all shadow-lg shadow-blue-500/30">{{ __('marketing.nav.book_demo') }}</a>
                                @endif
                            @endauth
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <main>
        <section class="relative min-h-screen flex items-center justify-center overflow-hidden">
            <div class="absolute inset-0 z-0 opacity-80" id="canvas-container"></div>

            <div class="relative z-10 w-full max-w-[90%] mx-auto">
                <div class="hero-content overflow-hidden">
                    <h1 class="text-huge font-display font-medium leading-none mb-4">
                        <div class="reveal-text block text-white drop-shadow-2xl">FINANCIAL</div>
                        <div class="reveal-text block pl-[5vw] text-outline">INTELLIGENCE</div>
                        <div
                            class="reveal-text block text-right bg-gradient-to-r from-blue-400 to-indigo-400 bg-clip-text text-transparent">
                            MASTERED</div>
                    </h1>
                </div>

                <div
                    class="flex flex-col md:flex-row justify-between items-end mt-12 border-t border-blue-500/20 pt-8 opacity-0 hero-footer">
                    <p class="max-w-md text-lg text-blue-100/70 font-light leading-relaxed">
                        {{ __('marketing.hero.subtitle') }}
                    </p>
                    <div class="flex gap-4 mt-8 md:mt-0">
                        <a href="#features"
                            class="group flex items-center justify-between w-48 px-6 py-4 border border-blue-400/30 rounded-full hover:bg-blue-600 hover:border-blue-600 transition-all">
                            <span class="uppercase text-sm tracking-widest">{{ __('marketing.hero.explore') }}</span>
                            <svg class="w-4 h-4 transform group-hover:rotate-45 transition-transform" fill="none"
                                stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                    d="M14 5l7 7m0 0l-7 7m7-7H3"></path>
                            </svg>
                        </a>
                    </div>
                </div>
            </div>
        </section>

        <!-- Marquee Section -->
        <section
            class="py-12 border-y border-blue-500/10 bg-blue-950/30 backdrop-blur-sm overflow-hidden whitespace-nowrap">
            <div
                class="marquee-track inline-block text-6xl font-display font-bold text-transparent text-outline opacity-40">
                {{ __('marketing.marquee') }} {{ __('marketing.marquee') }}
            </div>
        </section>

        <!-- Product Showcase (New) -->
        <section id="showcase" class="py-32 px-4 md:px-0 relative overflow-hidden">
            <div class="max-w-[90%] mx-auto">
                <div class="text-center mb-16">
                    <h2 class="text-4xl md:text-6xl font-display font-medium mb-4">{{ __('marketing.showcase.title') }}
                    </h2>
                    <p class="text-xl text-blue-200/60">{{ __('marketing.showcase.subtitle') }}</p>
                </div>

                <!-- Mockup -->
                <div class="relative max-w-6xl mx-auto group perspective-1000">
                    <div
                        class="glass-panel rounded-2xl p-2 border border-blue-500/30 transform transition-transform duration-700 hover:rotate-x-2 hover:scale-[1.01] shadow-2xl shadow-blue-500/20">
                        <!-- Top Bar -->
                        <div class="bg-slate-900 h-8 rounded-t-xl w-full flex items-center px-4 space-x-2">
                            <div class="w-3 h-3 rounded-full bg-red-500"></div>
                            <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                            <div class="w-3 h-3 rounded-full bg-green-500"></div>
                        </div>
                        <!-- Carousel Window -->
                        <div
                            class="bg-slate-900 aspect-video w-full rounded-b-xl relative overflow-hidden group/carousel">
                            <!-- Slide 1: Dashboard -->
                            <div class="showcase-slide absolute inset-0 opacity-100">
                                <img src="{{ asset('images/marketing/dashboard.png') }}" alt="Dashboard"
                                    class="w-full h-full object-cover object-top">
                                <div
                                    class="absolute bottom-0 left-0 right-0 p-8 bg-gradient-to-t from-slate-900 via-slate-900/90 to-transparent translate-y-4 group-hover/carousel:translate-y-0 transition-transform duration-500">
                                    <h3 class="text-2xl font-display text-white mb-1">
                                        {{ __('marketing.showcase.slides.dashboard.title') }}
                                    </h3>
                                    <p class="text-blue-200/70">{{ __('marketing.showcase.slides.dashboard.desc') }}</p>
                                </div>
                            </div>
                            <!-- Slide 2: Invoices -->
                            <div class="showcase-slide absolute inset-0 opacity-0">
                                <img src="{{ asset('images/marketing/invoices.png') }}" alt="Invoices"
                                    class="w-full h-full object-cover object-top">
                                <div
                                    class="absolute bottom-0 left-0 right-0 p-8 bg-gradient-to-t from-slate-900 via-slate-900/90 to-transparent translate-y-4 group-hover/carousel:translate-y-0 transition-transform duration-500">
                                    <h3 class="text-2xl font-display text-white mb-1">
                                        {{ __('marketing.showcase.slides.invoices.title') }}
                                    </h3>
                                    <p class="text-blue-200/70">{{ __('marketing.showcase.slides.invoices.desc') }}</p>
                                </div>
                            </div>
                            <!-- Slide 3: Reports -->
                            <div class="showcase-slide absolute inset-0 opacity-0">
                                <img src="{{ asset('images/marketing/reports.png') }}" alt="Reports"
                                    class="w-full h-full object-cover object-top">
                                <div
                                    class="absolute bottom-0 left-0 right-0 p-8 bg-gradient-to-t from-slate-900 via-slate-900/90 to-transparent translate-y-4 group-hover/carousel:translate-y-0 transition-transform duration-500">
                                    <h3 class="text-2xl font-display text-white mb-1">
                                        {{ __('marketing.showcase.slides.reports.title') }}
                                    </h3>
                                    <p class="text-blue-200/70">{{ __('marketing.showcase.slides.reports.desc') }}</p>
                                </div>
                            </div>

                            <!-- Prev/Next Buttons -->
                            <button id="carousel-prev"
                                class="absolute left-4 top-1/2 -translate-y-1/2 z-20 w-12 h-12 rounded-full bg-slate-800/70 hover:bg-blue-600 border border-blue-400/30 flex items-center justify-center transition-all opacity-0 group-hover/carousel:opacity-100">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M15 19l-7-7 7-7"></path>
                                </svg>
                            </button>
                            <button id="carousel-next"
                                class="absolute right-4 top-1/2 -translate-y-1/2 z-20 w-12 h-12 rounded-full bg-slate-800/70 hover:bg-blue-600 border border-blue-400/30 flex items-center justify-center transition-all opacity-0 group-hover/carousel:opacity-100">
                                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <!-- Carousel Indicators -->
                    <div class="flex justify-center gap-3 mt-6" id="carousel-indicators">
                        <button class="carousel-indicator w-3 h-3 rounded-full bg-blue-400 transition-all"
                            data-index="0"></button>
                        <button
                            class="carousel-indicator w-3 h-3 rounded-full bg-blue-400/30 hover:bg-blue-400/60 transition-all"
                            data-index="1"></button>
                        <button
                            class="carousel-indicator w-3 h-3 rounded-full bg-blue-400/30 hover:bg-blue-400/60 transition-all"
                            data-index="2"></button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Bento Grid Features -->
        <section id="features" class="py-32 px-4 md:px-0">
            <div class="max-w-[90%] mx-auto">
                <div class="flex flex-col md:flex-row justify-between items-end mb-20">
                    <h2 class="text-6xl md:text-8xl font-display font-medium max-w-4xl">
                        {{ __('marketing.features.title') }} <br>
                        <span class="text-blue-400">{{ __('marketing.features.subtitle') }}</span>
                    </h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 auto-rows-[400px]">
                    <!-- Large Card (Analytics) -->
                    <div
                        class="md:col-span-2 glass-panel rounded-3xl p-10 relative overflow-hidden group hover-trigger">
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-blue-600/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                        </div>
                        <div class="relative z-10 h-full flex flex-col justify-between">
                            <div
                                class="w-16 h-16 rounded-full bg-blue-500/10 flex items-center justify-center border border-blue-400/20 group-hover:scale-110 transition-transform duration-500 text-blue-300">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z">
                                    </path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-3xl font-display mb-4 text-white">
                                    {{ __('marketing.features.analytics.title') }}
                                </h3>
                                <p class="text-blue-100/70 text-lg max-w-md">
                                    {{ __('marketing.features.analytics.desc') }}
                                </p>
                            </div>
                        </div>
                        <!-- Decorative Abstract Line/Chart -->
                        <div
                            class="absolute bottom-0 right-0 w-1/2 h-1/2 opacity-20 group-hover:opacity-40 transition-opacity">
                            <svg viewBox="0 0 200 100" class="w-full h-full">
                                <path d="M0 80 Q 50 20 100 50 T 200 20" fill="none" stroke="#60a5fa" stroke-width="2" />
                            </svg>
                        </div>
                    </div>

                    <!-- Tall Card (Security) -->
                    <div class="glass-panel rounded-3xl p-10 relative overflow-hidden group hover-trigger">
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-indigo-600/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                        </div>
                        <div class="relative z-10 h-full flex flex-col justify-between">
                            <div
                                class="w-16 h-16 rounded-full bg-indigo-500/10 flex items-center justify-center border border-indigo-400/20 group-hover:scale-110 transition-transform duration-500 text-indigo-300">
                                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                        d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z">
                                    </path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="text-3xl font-display mb-4 text-white">
                                    {{ __('marketing.features.security.title') }}
                                </h3>
                                <p class="text-blue-100/70">{{ __('marketing.features.security.desc') }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Wide Card (Automation) -->
                    <div
                        class="md:col-span-3 glass-panel rounded-3xl p-10 relative overflow-hidden group hover-trigger">
                        <div
                            class="absolute inset-0 bg-gradient-to-br from-cyan-600/30 to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-500">
                        </div>
                        <div class="relative z-10 flex flex-col md:flex-row items-center justify-between gap-12">
                            <div class="flex-1">
                                <div
                                    class="w-16 h-16 rounded-full bg-cyan-500/10 flex items-center justify-center border border-cyan-400/20 mb-8 group-hover:scale-110 transition-transform duration-500 text-cyan-300">
                                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                                            d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                                    </svg>
                                </div>
                                <h3 class="text-3xl font-display mb-4 text-white">
                                    {{ __('marketing.features.automation.title') }}
                                </h3>
                                <p class="text-blue-100/70 text-lg max-w-xl">
                                    {{ __('marketing.features.automation.desc') }}
                                </p>
                            </div>
                            <div class="flex-1 w-full max-w-sm">
                                <!-- Abstract UI representation -->
                                <div
                                    class="bg-blue-900/40 border border-blue-400/20 rounded-xl p-6 transform rotate-3 group-hover:rotate-0 transition-transform duration-500">
                                    <div class="h-2 w-1/3 bg-blue-400/40 rounded mb-4"></div>
                                    <div class="h-2 w-2/3 bg-blue-400/20 rounded mb-2"></div>
                                    <div class="h-2 w-1/2 bg-blue-400/20 rounded mb-2"></div>
                                    <div class="mt-4 flex gap-2">
                                        <div class="h-8 w-20 bg-blue-500 rounded shadow-lg shadow-blue-500/40"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Impact Section (New) -->
        <section id="impact" class="py-20 bg-blue-900/20 border-y border-blue-500/10">
            <div class="max-w-[90%] mx-auto">
                <div class="grid grid-cols-1 md:grid-cols-4 gap-12 text-center md:text-left">
                    <div class="col-span-1">
                        <h3 class="text-3xl font-display font-medium mb-4">{{ __('marketing.impact.title') }}</h3>
                    </div>
                    <div class="col-span-1">
                        <div class="text-5xl font-display font-bold text-blue-400 mb-2">
                            {{ __('marketing.impact.stat1_value') }}
                        </div>
                        <div class="text-sm font-light text-blue-200 uppercase tracking-widest">
                            {{ __('marketing.impact.stat1_label') }}
                        </div>
                    </div>
                    <div class="col-span-1">
                        <div class="text-5xl font-display font-bold text-indigo-400 mb-2">
                            {{ __('marketing.impact.stat2_value') }}
                        </div>
                        <div class="text-sm font-light text-blue-200 uppercase tracking-widest">
                            {{ __('marketing.impact.stat2_label') }}
                        </div>
                    </div>
                    <div class="col-span-1">
                        <div class="text-5xl font-display font-bold text-cyan-400 mb-2">
                            {{ __('marketing.impact.stat3_value') }}
                        </div>
                        <div class="text-sm font-light text-blue-200 uppercase tracking-widest">
                            {{ __('marketing.impact.stat3_label') }}
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- How It Works Section -->
        <section id="how-it-works" class="py-32 px-4 md:px-0">
            <div class="max-w-[90%] mx-auto">
                <div class="text-center mb-20">
                    <h2 class="text-4xl md:text-6xl font-display font-medium mb-4">
                        {{ __('marketing.how_it_works.title') }}
                    </h2>
                    <p class="text-xl text-blue-200/60">{{ __('marketing.how_it_works.subtitle') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    <!-- Step 1 -->
                    <div
                        class="glass-panel p-10 rounded-3xl border border-white/5 hover:border-blue-400/30 transition-all group">
                        <div
                            class="text-6xl font-display font-bold text-blue-400/30 mb-6 group-hover:text-blue-400/60 transition-colors">
                            {{ __('marketing.how_it_works.step1.number') }}
                        </div>
                        <h4 class="text-2xl font-display mb-4 text-white">{{ __('marketing.how_it_works.step1.title') }}
                        </h4>
                        <p class="text-blue-200/70">{{ __('marketing.how_it_works.step1.desc') }}</p>
                    </div>
                    <!-- Step 2 -->
                    <div
                        class="glass-panel p-10 rounded-3xl border border-white/5 hover:border-blue-400/30 transition-all group">
                        <div
                            class="text-6xl font-display font-bold text-indigo-400/30 mb-6 group-hover:text-indigo-400/60 transition-colors">
                            {{ __('marketing.how_it_works.step2.number') }}
                        </div>
                        <h4 class="text-2xl font-display mb-4 text-white">{{ __('marketing.how_it_works.step2.title') }}
                        </h4>
                        <p class="text-blue-200/70">{{ __('marketing.how_it_works.step2.desc') }}</p>
                    </div>
                    <!-- Step 3 -->
                    <div
                        class="glass-panel p-10 rounded-3xl border border-white/5 hover:border-blue-400/30 transition-all group">
                        <div
                            class="text-6xl font-display font-bold text-cyan-400/30 mb-6 group-hover:text-cyan-400/60 transition-colors">
                            {{ __('marketing.how_it_works.step3.number') }}
                        </div>
                        <h4 class="text-2xl font-display mb-4 text-white">{{ __('marketing.how_it_works.step3.title') }}
                        </h4>
                        <p class="text-blue-200/70">{{ __('marketing.how_it_works.step3.desc') }}</p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Testimonials Section -->
        <section id="testimonials" class="py-32 px-4 md:px-0 bg-blue-900/10 border-y border-blue-500/10">
            <div class="max-w-[90%] mx-auto">
                <div class="text-center mb-20">
                    <h2 class="text-4xl md:text-6xl font-display font-medium mb-4">
                        {{ __('marketing.testimonials.title') }}
                    </h2>
                    <p class="text-xl text-blue-200/60">{{ __('marketing.testimonials.subtitle') }}</p>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                    @foreach(__('marketing.testimonials.items') as $testimonial)
                        <div class="glass-panel p-8 rounded-3xl border border-white/5 flex flex-col">
                            <div class="flex-1">
                                <svg class="w-10 h-10 text-blue-400/50 mb-6" fill="currentColor" viewBox="0 0 24 24">
                                    <path
                                        d="M14.017 18L14.017 10.609C14.017 4.905 17.748 1.039 23 0L23.995 2.151C21.563 3.068 20 5.789 20 8H24V18H14.017ZM0 18V10.609C0 4.905 3.748 1.039 9 0L9.996 2.151C7.563 3.068 6 5.789 6 8H10V18H0Z" />
                                </svg>
                                <p class="text-lg text-blue-100/80 mb-8 leading-relaxed italic">
                                    "{{ $testimonial['quote'] }}"</p>
                            </div>
                            <div class="border-t border-blue-500/10 pt-6">
                                <p class="font-display font-medium text-white">{{ $testimonial['author'] }}</p>
                                <p class="text-sm text-blue-200/50">{{ $testimonial['role'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- FAQ Section -->
        <section id="faq" class="py-32 px-4 md:px-0">
            <div class="max-w-4xl mx-auto">
                <div class="text-center mb-20">
                    <h2 class="text-4xl md:text-6xl font-display font-medium mb-4">{{ __('marketing.faq.title') }}</h2>
                    <p class="text-xl text-blue-200/60">{{ __('marketing.faq.subtitle') }}</p>
                </div>

                <div class="space-y-4">
                    @foreach(__('marketing.faq.items') as $index => $faq)
                        <div class="glass-panel rounded-2xl border border-white/5 overflow-hidden">
                            <button class="faq-toggle w-full px-8 py-6 flex justify-between items-center text-left group"
                                data-target="faq-{{ $index }}">
                                <span
                                    class="text-lg font-medium text-white group-hover:text-blue-300 transition-colors">{{ $faq['q'] }}</span>
                                <svg class="w-6 h-6 text-blue-400 transition-transform faq-icon" fill="none"
                                    stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                        d="M19 9l-7 7-7-7"></path>
                                </svg>
                            </button>
                            <div id="faq-{{ $index }}" class="faq-content hidden px-8 pb-6">
                                <p class="text-blue-200/70 leading-relaxed">{{ $faq['a'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Demo CTA Section -->
        <section
            class="py-32 flex justify-center items-center bg-gradient-to-br from-blue-600 to-indigo-700 text-white overflow-hidden relative">
            <div class="max-w-4xl text-center z-10 px-4">
                <h3 class="text-4xl md:text-6xl font-display font-medium mb-6 drop-shadow-lg">
                    {{ __('marketing.demo_cta.title') }}
                </h3>
                <p class="text-xl text-blue-100/80 mb-12 max-w-2xl mx-auto">
                    {{ __('marketing.demo_cta.subtitle') }}
                </p>
                <a href="#" onclick="event.preventDefault(); alert('Demo booking form coming soon!');"
                    class="inline-block px-10 py-5 bg-white text-blue-900 rounded-full uppercase tracking-wider text-sm hover:scale-105 transition-transform font-bold shadow-xl">
                    {{ __('marketing.demo_cta.button') }}
                </a>
            </div>
            <!-- Dynamic Background Lines with Gradient Blur -->
            <div class="absolute inset-0 opacity-20">
                <svg width="100%" height="100%">
                    <defs>
                        <!-- Blur filter -->
                        <filter id="gridBlur" x="-80%" y="-60%" width="200%" height="200%">
                            <feGaussianBlur in="SourceGraphic" stdDeviation="1" />
                        </filter>
                        <!-- Radial gradient mask for fading edges -->
                        <radialGradient id="gridMask" cx="50%" cy="50%" r="70%" fx="50%" fy="50%">
                            <stop offset="0%" style="stop-color:white;stop-opacity:1" />
                            <stop offset="60%" style="stop-color:white;stop-opacity:0.6" />
                            <stop offset="100%" style="stop-color:white;stop-opacity:0" />
                        </radialGradient>
                        <mask id="fadeMask">
                            <rect width="100%" height="100%" fill="url(#gridMask)" />
                        </mask>
                        <!-- Grid pattern -->
                        <pattern id="grid" width="40" height="40" patternUnits="userSpaceOnUse">
                            <path d="M 40 0 L 0 0 0 40" fill="none" stroke="currentColor" stroke-width="1" />
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#grid)" filter="url(#gridBlur)" mask="url(#fadeMask)" />
                </svg>
            </div>
        </section>

        <!-- Footer -->
        <footer class="py-20 border-t border-blue-500/20 bg-blue-950/50">
            <div class="max-w-[90%] mx-auto grid grid-cols-1 md:grid-cols-4 gap-12">
                <div class="col-span-1 md:col-span-2">
                    <div class="flex">
                        <div class="shrink-0 flex items-center">
                            <a href="#">
                                <svg class="block h-9 w-auto fill-current text-gray-800" viewBox="0 0 130 40"
                                    xmlns="http://www.w3.org/2000/svg">
                                    <defs>
                                        <linearGradient id="navGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                                            <stop offset="0%" style="stop-color:#60a5fa" />
                                            <stop offset="100%" style="stop-color:#818cf8" />
                                        </linearGradient>
                                    </defs>
                                    <!-- F Icon Mark -->
                                    <g transform="translate(0, 4)">
                                        <path d="M4 0h18a4 4 0 0 1 4 4v24a4 4 0 0 1-4 4H4a4 4 0 0 1-4-4V4a4 4 0 0 1 4-4z"
                                            fill="url(#navGradient)" />
                                        <path d="M8 8h12v3H11v4h7v3h-7v8H8V8z" fill="white" />
                                    </g>
                                    <!-- FinfasPro Text -->
                                    <text x="34" y="28" font-family="Inter, system-ui, sans-serif" font-size="20"
                                        font-weight="700" fill="url(#navGradient)">
                                        <tspan>Finfas</tspan><tspan font-weight="500" fill="#f8fafc">Pro</tspan>
                                    </text>
                                </svg>
                            </a>
                        </div>
                    </div>
                    <p class="text-blue-200/60 max-w-sm">{{ __('marketing.footer.tagline') }}</p>
                </div>
                <div>
                    <h4 class="uppercase text-xs tracking-widest text-blue-400 mb-6 font-bold">
                        {{ __('marketing.footer.menu') }}
                    </h4>
                    <ul class="space-y-4 text-blue-200/80">
                        <li><a href="#features"
                                class="hover:text-white transition-colors">{{ __('marketing.nav.features') }}</a></li>
                        <li><a href="#how-it-works"
                                class="hover:text-white transition-colors">{{ __('marketing.nav.how_it_works') }}</a>
                        </li>
                        <li><a href="#faq" class="hover:text-white transition-colors">FAQ</a></li>
                        <li><a href="{{ route('central.login') }}"
                                class="hover:text-white transition-colors">{{ __('marketing.nav.login') }}</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="uppercase text-xs tracking-widest text-blue-400 mb-6 font-bold">
                        {{ __('marketing.footer.socials') }}
                    </h4>
                    <ul class="space-y-4 text-blue-200/80">
                        <li><a href="#" class="hover:text-white transition-colors">Twitter</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">LinkedIn</a></li>
                        <li><a href="#" class="hover:text-white transition-colors">Instagram</a></li>
                    </ul>
                </div>
            </div>
            <div
                class="max-w-[90%] mx-auto mt-20 pt-8 border-t border-blue-500/10 flex flex-col gap-4 md:flex-row justify-between text-sm text-blue-200/50">
                <p>&copy; {{ date('Y') }} FinfasPro Inc. {{ __('marketing.footer.rights') }}</p>
                <div class="flex gap-6">
                    <a href="#">{{ __('marketing.footer.privacy') }}</a>
                    <a href="#">{{ __('marketing.footer.terms') }}</a>
                </div>
            </div>
        </footer>
    </main>

    <script type="module">
        import * as THREE from 'three';

        // --- LENIS SMOOTH SCROLL ---
        const lenis = new Lenis({
            duration: 1.2,
            easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
            direction: 'vertical',
            gestureDirection: 'vertical',
            smooth: true,
            mouseMultiplier: 1,
            smoothTouch: false,
            touchMultiplier: 2,
        });

        function raf(time) {
            lenis.raf(time);
            requestAnimationFrame(raf);
        }
        requestAnimationFrame(raf);

        // --- SMOOTH SCROLL NAVIGATION ---
        // Handle all anchor links with smooth scroll animation
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                const href = this.getAttribute('href');
                if (href === '#') return; // Skip empty anchors
                
                e.preventDefault();
                const targetId = href.substring(1);
                const targetElement = document.getElementById(targetId);
                
                if (targetElement) {
                    // Use Lenis for buttery smooth scroll
                    lenis.scrollTo(targetElement, {
                        offset: -100, // Offset for fixed nav
                        duration: 1.8, // Slower for visible, smooth effect
                        easing: (t) => {
                            // Custom cubic-bezier easing for premium feel
                            return t < 0.5 
                                ? 4 * t * t * t 
                                : 1 - Math.pow(-2 * t + 2, 3) / 2;
                        },
                        onComplete: () => {
                            // Update URL hash without jump
                            history.pushState(null, null, href);
                        }
                    });
                    
                    // Add visual feedback to clicked link
                    this.classList.add('text-blue-400');
                    setTimeout(() => {
                        this.classList.remove('text-blue-400');
                    }, 500);
                }
            });
        });

        // Highlight active nav link on scroll
        const sections = document.querySelectorAll('section[id]');
        const navLinks = document.querySelectorAll('nav a[href^="#"]');
        
        function updateActiveLink() {
            const scrollPos = window.scrollY + 150;
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.offsetHeight;
                const sectionId = section.getAttribute('id');
                
                if (scrollPos >= sectionTop && scrollPos < sectionTop + sectionHeight) {
                    navLinks.forEach(link => {
                        link.classList.remove('text-blue-400');
                        if (link.getAttribute('href') === `#${sectionId}`) {
                            link.classList.add('text-blue-400');
                        }
                    });
                }
            });
        }
        
        lenis.on('scroll', updateActiveLink);

        // --- MOBILE MENU TOGGLE ---
        const mobileMenuBtn = document.getElementById('mobile-menu-btn');
        const mobileMenu = document.getElementById('mobile-menu');
        const menuIcon = document.getElementById('menu-icon');
        let isMenuOpen = false;

        if (mobileMenuBtn && mobileMenu) {
            mobileMenuBtn.addEventListener('click', () => {
                isMenuOpen = !isMenuOpen;
                mobileMenu.classList.toggle('hidden');
                // Animate icon
                if (isMenuOpen) {
                    menuIcon.setAttribute('d', 'M6 18L18 6M6 6l12 12');
                } else {
                    menuIcon.setAttribute('d', 'M4 6h16M4 12h16M4 18h16');
                }
            });

            // Close menu when clicking a link
            document.querySelectorAll('.mobile-link').forEach(link => {
                link.addEventListener('click', () => {
                    mobileMenu.classList.add('hidden');
                    isMenuOpen = false;
                    menuIcon.setAttribute('d', 'M4 6h16M4 12h16M4 18h16');
                });
            });
        }

        // --- GSAP ANIMATIONS ---
        gsap.registerPlugin(ScrollTrigger);

        // Loader Exit
        window.addEventListener('load', () => {
            const tl = gsap.timeline();
            tl.to('.loader-text', { opacity: 0, duration: 0.5, delay: 0.5 })
                .to('.loader', { yPercent: -100, duration: 1, ease: 'power4.inOut' })
                .from('.hero-content h1 .reveal-text', {
                    y: 100, opacity: 0, stagger: 0.1, duration: 1, ease: 'power3.out'
                }, '-=0.5')
                .to('.hero-footer', { opacity: 1, duration: 1 }, '-=0.5');
        });

        // Marquee Animation
        gsap.to('.marquee-track', {
            xPercent: -50,
            ease: "none",
            duration: 20,
            repeat: -1
        });

        // Reveal elements on scroll
        gsap.utils.toArray('.glass-panel').forEach(panel => {
            gsap.from(panel, {
                scrollTrigger: {
                    trigger: panel,
                    start: "top 85%",
                },
                y: 50,
                opacity: 0,
                duration: 1,
                ease: "power3.out"
            });
        });

        // --- SHOWCASE CAROUSEL ---
        const showcaseSlides = document.querySelectorAll('.showcase-slide');
        const indicators = document.querySelectorAll('.carousel-indicator');
        const prevBtn = document.getElementById('carousel-prev');
        const nextBtn = document.getElementById('carousel-next');
        let currentShowcaseSlide = 0;
        let autoSlideInterval;

        function updateIndicators() {
            indicators.forEach((indicator, index) => {
                if (index === currentShowcaseSlide) {
                    indicator.classList.remove('bg-blue-400/30');
                    indicator.classList.add('bg-blue-400');
                } else {
                    indicator.classList.remove('bg-blue-400');
                    indicator.classList.add('bg-blue-400/30');
                }
            });
        }

        function goToSlide(targetIndex) {
            if (showcaseSlides.length === 0 || targetIndex === currentShowcaseSlide) return;
            const current = showcaseSlides[currentShowcaseSlide];
            const next = showcaseSlides[targetIndex];

            gsap.to(current, { opacity: 0, duration: 0.6, ease: 'power2.inOut' });
            gsap.to(next, { opacity: 1, duration: 0.6, ease: 'power2.inOut' });

            currentShowcaseSlide = targetIndex;
            updateIndicators();
        }

        function nextShowcaseSlide() {
            const nextIndex = (currentShowcaseSlide + 1) % showcaseSlides.length;
            goToSlide(nextIndex);
        }

        function prevShowcaseSlide() {
            const prevIndex = (currentShowcaseSlide - 1 + showcaseSlides.length) % showcaseSlides.length;
            goToSlide(prevIndex);
        }

        // Event Listeners
        if (nextBtn) nextBtn.addEventListener('click', () => { nextShowcaseSlide(); resetAutoSlide(); });
        if (prevBtn) prevBtn.addEventListener('click', () => { prevShowcaseSlide(); resetAutoSlide(); });
        indicators.forEach(indicator => {
            indicator.addEventListener('click', () => {
                const index = parseInt(indicator.dataset.index, 10);
                goToSlide(index);
                resetAutoSlide();
            });
        });

        function resetAutoSlide() {
            clearInterval(autoSlideInterval);
            autoSlideInterval = setInterval(nextShowcaseSlide, 5000);
        }

        if (showcaseSlides.length > 0) {
            autoSlideInterval = setInterval(nextShowcaseSlide, 5000);
        }

        // --- FAQ ACCORDION ---
        document.querySelectorAll('.faq-toggle').forEach(button => {
            button.addEventListener('click', () => {
                const targetId = button.dataset.target;
                const content = document.getElementById(targetId);
                const icon = button.querySelector('.faq-icon');

                if (content.classList.contains('hidden')) {
                    content.classList.remove('hidden');
                    icon.style.transform = 'rotate(180deg)';
                } else {
                    content.classList.add('hidden');
                    icon.style.transform = 'rotate(0deg)';
                }
            });
        });

        // --- CUSTOM CURSOR ---
        const cursorDot = document.querySelector('.cursor-dot');
        const cursorOutline = document.querySelector('.cursor-outline');

        window.addEventListener('mousemove', (e) => {
            const posX = e.clientX;
            const posY = e.clientY;

            cursorDot.style.left = `${posX}px`;
            cursorDot.style.top = `${posY}px`;

            // Smoother delay for outline
            cursorOutline.animate({
                left: `${posX}px`,
                top: `${posY}px`
            }, { duration: 500, fill: "forwards" });
        });

        // Hover effects on interactivity
        document.querySelectorAll('a, button, .hover-trigger').forEach(el => {
            el.addEventListener('mouseenter', () => {
                cursorOutline.style.width = '60px';
                cursorOutline.style.height = '60px';
                cursorOutline.style.backgroundColor = 'rgba(56, 189, 248, 0.1)';
            });
            el.addEventListener('mouseleave', () => {
                cursorOutline.style.width = '40px';
                cursorOutline.style.height = '40px';
                cursorOutline.style.backgroundColor = 'transparent';
            });
        });

        // --- THREE.JS BACKGROUND ---
        const canvasContainer = document.getElementById('canvas-container');
        const scene = new THREE.Scene();
        // Fog colour needs to match BG. #0f172a
        scene.fog = new THREE.FogExp2(0x0f172a, 0.05);

        const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
        camera.position.z = 5;

        const renderer = new THREE.WebGLRenderer({ alpha: true, antialias: true });
        renderer.setSize(window.innerWidth, window.innerHeight);
        renderer.setPixelRatio(window.devicePixelRatio);
        canvasContainer.appendChild(renderer.domElement);

        // Particle System
        const particlesGeometry = new THREE.BufferGeometry();
        const particlesCount = 2000;
        const posArray = new Float32Array(particlesCount * 3);

        for (let i = 0; i < particlesCount * 3; i++) {
            posArray[i] = (Math.random() - 0.5) * 15;
        }

        particlesGeometry.setAttribute('position', new THREE.BufferAttribute(posArray, 3));
        const particlesMaterial = new THREE.PointsMaterial({
            size: 0.02,
            color: 0x38bdf8, // Sky 400
            transparent: true,
            opacity: 0.6,
        });
        const particlesMesh = new THREE.Points(particlesGeometry, particlesMaterial);
        scene.add(particlesMesh);

        // Abstract Geometric Shapes (floating)
        const geoGeometry = new THREE.IcosahedronGeometry(1, 0);
        const geoMaterial = new THREE.MeshBasicMaterial({ color: 0x6366f1, wireframe: true, transparent: true, opacity: 0.3 });
        const geoMesh = new THREE.Mesh(geoGeometry, geoMaterial);
        geoMesh.position.set(2, 0, 0);
        scene.add(geoMesh);

        function animate() {
            requestAnimationFrame(animate);
            particlesMesh.rotation.y += 0.0005;
            particlesMesh.rotation.x += 0.0002;

            geoMesh.rotation.x += 0.01;
            geoMesh.rotation.y += 0.01;

            renderer.render(scene, camera);
        }
        animate();

        window.addEventListener('resize', () => {
            camera.aspect = window.innerWidth / window.innerHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(window.innerWidth, window.innerHeight);
        });
    </script>
</body>

</html>