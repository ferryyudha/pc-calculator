<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PC Build Recommendation | PC Calculator</title>

    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#3b82f6',
                        accent: '#a855f7',
                        success: '#10b981',
                        danger: '#ef4444',
                        surface: {
                            700: '#334155',
                            800: '#1e293b',
                            900: '#0f172a',
                            950: '#020617'
                        }
                    }
                }
            }
        }
    </script>
    
    <!-- Alpine.js CDN -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <!-- Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800;900&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Outfit', sans-serif;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
        .glass {
            background: rgba(30, 41, 59, 0.45);
            backdrop-filter: blur(12px);
            -webkit-backdrop-filter: blur(12px);
            border: 1px solid rgba(255, 255, 255, 0.05);
        }
        .glow-cyan {
            box-shadow: 0 0 20px rgba(6, 182, 212, 0.15);
        }
    </style>
</head>
<body class="bg-surface-950 text-slate-100 min-h-screen pb-16">

    <!-- Header Navigation -->
    <header class="border-b border-slate-800 bg-surface-900/80 sticky top-0 z-50 backdrop-blur-md">
        <div class="max-w-6xl mx-auto px-4 h-16 flex items-center justify-between">
            <a href="/" class="flex items-center gap-2 font-black text-xl tracking-tight text-white">
                <span class="bg-gradient-to-r from-blue-500 to-purple-600 bg-clip-text text-transparent">PC Calculator</span>
            </a>
            <nav class="flex items-center gap-6 text-sm font-semibold text-slate-300">
                <a href="/components" class="hover:text-white transition">Komponen</a>
                <a href="/build-recommendation" class="text-blue-400 hover:text-white transition">Rekomendasi Rakitan</a>
                <a href="/compatibility" class="hover:text-white transition">Kompatibilitas</a>
            </nav>
        </div>
    </header>

    <div class="max-w-4xl mx-auto px-4 sm:px-6 py-12">
        <div class="mb-10">
            <p class="text-blue-400 text-sm font-medium mb-2 uppercase tracking-wider">🏗️ Build Recommendation</p>
            <h1 class="text-4xl font-extrabold text-white mb-3 tracking-tight">Rekomendasi Rakitan PC</h1>
            <p class="text-slate-400 leading-relaxed">Pilih spesifikasi PC rakitan terbaik dengan cepat melalui AI, tier preset pintar, atau atur secara manual sesuai kebutuhan gaming dan budget Anda.</p>
        </div>

        <!-- Section 1 — AI Prompt Input -->
        <div class="bg-slate-800/80 border border-slate-700/50 rounded-2xl p-6 mb-6 glass glow-cyan">
            <label class="block text-sm text-slate-300 font-semibold mb-2 flex items-center gap-2">
                <span>🤖 Gunakan AI untuk bantu rakitan kamu</span>
                <span class="bg-gradient-to-r from-blue-500 to-purple-500 text-[10px] uppercase font-black px-2 py-0.5 rounded text-white tracking-widest">Groq Powered</span>
            </label>
            <form method="POST" action="{{ route('build.ai-prompt') }}"
                  x-data="{ loading: false }" @submit="loading = true"
                  class="flex flex-col sm:flex-row gap-3">
                @csrf
                <input type="text" name="prompt"
                    placeholder="Contoh: main GTA V 1080p, budget 10 juta, butuh RTX, 16GB RAM"
                    value="{{ old('prompt', isset($parsedPrompt) ? request('prompt') : '') }}"
                    required
                    class="flex-1 bg-slate-900 border border-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-xl px-4 py-3 text-slate-100 placeholder-slate-500 outline-none transition duration-200">
                <button type="submit"
                    class="bg-gradient-to-r from-blue-500 to-purple-600 hover:from-blue-600 hover:to-purple-700 text-white font-bold px-6 py-3 rounded-xl whitespace-nowrap transition-all duration-200 shadow-lg shadow-purple-900/20 active:scale-[0.98] flex items-center justify-center gap-2">
                    <span x-show="!loading" class="flex items-center gap-2">
                        <span>Build dengan AI</span>
                    </span>
                    <span x-show="loading" class="flex items-center gap-2">
                        <svg class="animate-spin h-5 w-5 text-white" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        <span>Menganalisa...</span>
                    </span>
                </button>
            </form>

            @if(isset($parsedPrompt))
            <div class="mt-4 pt-3 border-t border-slate-700/50 flex flex-wrap gap-2 text-xs text-slate-400 items-center">
                <span class="font-semibold text-slate-300">Hasil Deteksi AI:</span>
                <span class="bg-slate-900 px-2.5 py-1 rounded-md border border-slate-800">
                    Budget Maks: <strong class="text-blue-400">Rp {{ number_format($parsedPrompt['budget_max'] ?? 0, 0, ',', '.') }}</strong>
                </span>
                <span class="bg-slate-900 px-2.5 py-1 rounded-md border border-slate-800">
                    Resolusi: <strong class="text-purple-400">{{ $parsedPrompt['resolution'] ?? '-' }}</strong>
                </span>
                @if(!empty($parsedPrompt['gpu_preference']))
                <span class="bg-slate-900 px-2.5 py-1 rounded-md border border-slate-800">
                    Preferensi GPU: <strong class="text-green-400">{{ $parsedPrompt['gpu_preference'] }}</strong>
                </span>
                @endif
                @if(!empty($parsedPrompt['cpu_preference']))
                <span class="bg-slate-900 px-2.5 py-1 rounded-md border border-slate-800">
                    Preferensi CPU: <strong class="text-orange-400">{{ $parsedPrompt['cpu_preference'] }}</strong>
                </span>
                @endif
            </div>
            @endif
        </div>

        <!-- Section 2 — Tier Cards -->
        <h2 class="text-lg font-bold text-slate-300 mb-3 uppercase tracking-wider">Pilih Berdasarkan Tier Budget</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8">
            @foreach([
                ['key' => 'entry',       'label' => 'Entry Level',  'desc' => 'Hingga Rp 5 Juta',  'color' => 'emerald', 'badge' => 'sub-12k'],
                ['key' => 'mainstream',  'label' => 'Mainstream',   'desc' => 'Hingga Rp 12 Juta', 'color' => 'blue',    'badge' => 'mid-range'],
                ['key' => 'enthusiast',  'label' => 'Enthusiast',   'desc' => 'Di atas Rp 20 Juta', 'color' => 'purple',  'badge' => 'ultra-high'],
            ] as $tier)
            <form method="POST" action="{{ route('build.tier', $tier['key']) }}" class="w-full">
                @csrf
                <button type="submit"
                    class="w-full h-full bg-slate-800/50 hover:bg-slate-800 border border-slate-700/80 hover:border-slate-600 rounded-2xl p-6 text-left transition duration-200 group relative overflow-hidden flex flex-col justify-between">
                    <div class="absolute -right-4 -top-4 w-16 h-16 bg-{{ $tier['color'] }}-500/10 rounded-full blur-xl group-hover:scale-150 transition duration-300"></div>
                    <div>
                        <div class="text-xs uppercase tracking-wider text-{{ $tier['color'] }}-400 font-bold mb-2">
                            {{ $tier['label'] }}
                        </div>
                        <div class="text-white text-xl font-extrabold mb-1">{{ $tier['desc'] }}</div>
                        <div class="text-slate-400 text-xs mb-4">Cocok untuk target resolusi {{ $tier['key'] === 'entry' ? '1080p' : ($tier['key'] === 'mainstream' ? '1440p' : '4K') }}</div>
                    </div>
                    <div class="inline-flex items-center gap-1.5 bg-{{ $tier['color'] }}-500/15 text-{{ $tier['color'] }}-400 text-xs font-semibold px-3 py-1.5 rounded-xl group-hover:bg-{{ $tier['color'] }}-500 group-hover:text-white transition duration-200 w-fit">
                        <span>Rakit Sekarang</span>
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"></path></svg>
                    </div>
                </button>
            </form>
            @endforeach
        </div>

        <!-- Section 3 — Manual Form -->
        <div class="bg-slate-900 border border-slate-800 rounded-2xl p-6 mb-8">
            <h2 class="text-xl font-bold text-white mb-5 flex items-center gap-2">
                <span class="w-2 h-5 bg-blue-500 rounded-full"></span>
                <span>Atur Manual Rakitan</span>
            </h2>
            <form method="POST" action="{{ route('build.manual') }}" class="space-y-5">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Budget -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2">Budget (IDR)</label>
                        <input type="number" name="budget" min="1000000" step="500000"
                            placeholder="Contoh: 10000000"
                            value="{{ old('budget', 10000000) }}"
                            required
                            class="w-full bg-slate-950 border border-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-xl px-4 py-2.5 text-slate-100 placeholder-slate-600 outline-none transition duration-200">
                    </div>

                    <!-- Game Target -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2">Game Target</label>
                        <select name="game_id" required
                            class="w-full bg-slate-950 border border-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-xl px-4 py-2.5 text-slate-100 outline-none transition duration-200">
                            @foreach($games as $g)
                                <option value="{{ $g->id }}" {{ old('game_id') == $g->id ? 'selected' : '' }}>
                                    {{ $g->name }} (Min. VRAM: {{ $g->min_vram }}GB)
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <!-- Resolution Target -->
                    <div>
                        <label class="block text-sm font-semibold text-slate-300 mb-2">Resolusi Target</label>
                        <select name="resolution" required
                            class="w-full bg-slate-950 border border-slate-700 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 rounded-xl px-4 py-2.5 text-slate-100 outline-none transition duration-200">
                            @foreach(['720p', '1080p', '1440p', '4K'] as $res)
                                <option value="{{ $res }}" {{ old('resolution', '1080p') == $res ? 'selected' : '' }}>
                                    {{ $res }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>

                <button type="submit"
                    class="w-full bg-slate-800 hover:bg-slate-700 text-white font-bold py-3.5 rounded-xl border border-slate-700 hover:border-slate-600 transition duration-200 flex items-center justify-center gap-2">
                    <span>🏗️ Rekomendasikan Build</span>
                </button>
            </form>
        </div>

        <!-- Section 4 — Hasil Build dengan Gambar Komponen -->
        @if($result !== null && !isset($result['error']))
        <div class="mt-8 bg-slate-900 border border-slate-850 rounded-2xl p-6 shadow-2xl relative overflow-hidden">
            <div class="absolute right-0 top-0 w-64 h-64 bg-blue-500/5 rounded-full blur-3xl pointer-events-none"></div>
            
            <h2 class="text-2xl font-black text-white mb-6 flex items-center gap-2">
                <span class="text-blue-500 text-3xl">📦</span>
                <span>Rekomendasi Rakitan PC Builder Style</span>
            </h2>

            <div class="space-y-3">
                <!-- CPU -->
                <div class="flex flex-col sm:flex-items-center sm:flex-row gap-4 bg-slate-950 hover:bg-slate-900 border border-slate-800/80 hover:border-slate-700 rounded-xl p-4 transition duration-200">
                    <div class="bg-slate-900/60 p-2.5 rounded-xl border border-slate-800/50 w-20 h-20 flex items-center justify-center shrink-0">
                        <x-component-icon :category="$result['build']['cpu']['image_category'] ?? 'cpu-amd'" :size="64" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] tracking-wider text-slate-500 uppercase font-black">Processor</div>
                        <div class="font-extrabold text-white text-base truncate mt-0.5">{{ $result['build']['cpu']['name'] }}</div>
                        <div class="text-xs text-slate-400 mt-1 flex flex-wrap gap-2">
                            <span>Socket: {{ $result['build']['cpu']['socket'] }}</span>
                            <span>•</span>
                            <span>Cores: {{ $result['build']['cpu']['cores'] }} Cores</span>
                            <span>•</span>
                            <span>TDP: {{ $result['build']['cpu']['tdp'] }}W</span>
                        </div>
                    </div>
                    <div class="text-left sm:text-right font-bold text-blue-400 text-lg shrink-0">
                        Rp {{ number_format($result['build']['cpu']['price'], 0, ',', '.') }}
                    </div>
                </div>

                <!-- GPU -->
                <div class="flex flex-col sm:flex-items-center sm:flex-row gap-4 bg-slate-950 hover:bg-slate-900 border border-slate-800/80 hover:border-slate-700 rounded-xl p-4 transition duration-200">
                    <div class="bg-slate-900/60 p-2.5 rounded-xl border border-slate-800/50 w-20 h-20 flex items-center justify-center shrink-0">
                        <x-component-icon :category="$result['build']['gpu']['image_category'] ?? 'gpu-nvidia'" :size="64" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] tracking-wider text-slate-500 uppercase font-black">Graphics Card</div>
                        <div class="font-extrabold text-white text-base truncate mt-0.5">{{ $result['build']['gpu']['name'] }}</div>
                        <div class="text-xs text-slate-400 mt-1 flex flex-wrap gap-2">
                            <span>VRAM: {{ $result['build']['gpu']['vram'] }} GB</span>
                            <span>•</span>
                            <span>Power: {{ $result['build']['gpu']['power_draw'] }}W</span>
                        </div>
                    </div>
                    <div class="text-left sm:text-right font-bold text-blue-400 text-lg shrink-0">
                        Rp {{ number_format($result['build']['gpu']['price'], 0, ',', '.') }}
                    </div>
                </div>

                <!-- Motherboard -->
                <div class="flex flex-col sm:flex-items-center sm:flex-row gap-4 bg-slate-950 hover:bg-slate-900 border border-slate-800/80 hover:border-slate-700 rounded-xl p-4 transition duration-200">
                    <div class="bg-slate-900/60 p-2.5 rounded-xl border border-slate-800/50 w-20 h-20 flex items-center justify-center shrink-0">
                        <x-component-icon :category="$result['build']['motherboard']['image_category'] ?? 'motherboard'" :size="64" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] tracking-wider text-slate-500 uppercase font-black">Motherboard</div>
                        <div class="font-extrabold text-white text-base truncate mt-0.5">{{ $result['build']['motherboard']['name'] }}</div>
                        <div class="text-xs text-slate-400 mt-1 flex flex-wrap gap-2">
                            <span>Socket: {{ $result['build']['motherboard']['socket'] }}</span>
                            <span>•</span>
                            <span>Chipset: {{ $result['build']['motherboard']['chipset'] }}</span>
                        </div>
                    </div>
                    <div class="text-left sm:text-right font-bold text-blue-400 text-lg shrink-0">
                        Rp {{ number_format($result['build']['motherboard']['price'], 0, ',', '.') }}
                    </div>
                </div>

                <!-- RAM -->
                <div class="flex flex-col sm:flex-items-center sm:flex-row gap-4 bg-slate-950 hover:bg-slate-900 border border-slate-800/80 hover:border-slate-700 rounded-xl p-4 transition duration-200">
                    <div class="bg-slate-900/60 p-2.5 rounded-xl border border-slate-800/50 w-20 h-20 flex items-center justify-center shrink-0">
                        <x-component-icon :category="$result['build']['ram']['image_category'] ?? 'ram-ddr4'" :size="64" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] tracking-wider text-slate-500 uppercase font-black">Memory (RAM)</div>
                        <div class="font-extrabold text-white text-base truncate mt-0.5">{{ $result['build']['ram']['name'] }}</div>
                        <div class="text-xs text-slate-400 mt-1 flex flex-wrap gap-2">
                            <span>Type: {{ $result['build']['ram']['ddr_version'] }}</span>
                            <span>•</span>
                            <span>Kapasitas: {{ $result['build']['ram']['capacity'] }}GB</span>
                            <span>•</span>
                            <span>Speed: {{ $result['build']['ram']['speed'] }}MHz</span>
                        </div>
                    </div>
                    <div class="text-left sm:text-right font-bold text-blue-400 text-lg shrink-0">
                        Rp {{ number_format($result['build']['ram']['price'], 0, ',', '.') }}
                    </div>
                </div>

                <!-- SSD -->
                <div class="flex flex-col sm:flex-items-center sm:flex-row gap-4 bg-slate-950 hover:bg-slate-900 border border-slate-800/80 hover:border-slate-700 rounded-xl p-4 transition duration-200">
                    <div class="bg-slate-900/60 p-2.5 rounded-xl border border-slate-800/50 w-20 h-20 flex items-center justify-center shrink-0">
                        <x-component-icon :category="$result['build']['ssd']['image_category'] ?? 'ssd'" :size="64" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] tracking-wider text-slate-500 uppercase font-black">Storage (SSD)</div>
                        <div class="font-extrabold text-white text-base truncate mt-0.5">{{ $result['build']['ssd']['name'] }}</div>
                        <div class="text-xs text-slate-400 mt-1 flex flex-wrap gap-2">
                            <span>Kapasitas: {{ $result['build']['ssd']['capacity'] }}GB</span>
                            <span>•</span>
                            <span>Type: {{ $result['build']['ssd']['type'] }}</span>
                        </div>
                    </div>
                    <div class="text-left sm:text-right font-bold text-blue-400 text-lg shrink-0">
                        Rp {{ number_format($result['build']['ssd']['price'], 0, ',', '.') }}
                    </div>
                </div>

                <!-- PSU -->
                <div class="flex flex-col sm:flex-items-center sm:flex-row gap-4 bg-slate-950 hover:bg-slate-900 border border-slate-800/80 hover:border-slate-700 rounded-xl p-4 transition duration-200">
                    <div class="bg-slate-900/60 p-2.5 rounded-xl border border-slate-800/50 w-20 h-20 flex items-center justify-center shrink-0">
                        <x-component-icon :category="$result['build']['psu']['image_category'] ?? 'psu'" :size="64" />
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] tracking-wider text-slate-500 uppercase font-black">Power Supply (PSU)</div>
                        <div class="font-extrabold text-white text-base truncate mt-0.5">{{ $result['build']['psu']['name'] }}</div>
                        <div class="text-xs text-slate-400 mt-1 flex flex-wrap gap-2">
                            <span>Daya: {{ $result['build']['psu']['watt'] }}W</span>
                            <span>•</span>
                            <span>Sertifikasi: {{ $result['build']['psu']['certification'] }}</span>
                        </div>
                    </div>
                    <div class="text-left sm:text-right font-bold text-blue-400 text-lg shrink-0">
                        Rp {{ number_format($result['build']['psu']['price'], 0, ',', '.') }}
                    </div>
                </div>
            </div>

            <!-- Footer Summary info -->
            <div class="border-t border-slate-800/80 mt-6 pt-6 flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                <div>
                    <div class="text-xs text-slate-400 font-bold uppercase tracking-wider">Total Harga Rakitan</div>
                    <div class="text-3xl font-black text-blue-500 mt-1">
                        Rp {{ number_format($result['total_price'], 0, ',', '.') }}
                    </div>
                </div>
                @if(isset($result['estimated_fps']))
                <div class="text-left md:text-right bg-success/10 border border-success/20 rounded-2xl px-5 py-3 flex items-center gap-3">
                    <span class="text-success text-2xl font-semibold">🎮</span>
                    <div>
                        <div class="text-[10px] text-success/80 font-black uppercase tracking-widest">Estimasi Performance</div>
                        <div class="text-xl font-extrabold text-success mt-0.5">
                            {{ $result['estimated_fps']['high'] ?? $result['estimated_fps'] }} FPS <span class="text-xs text-success/70 font-medium">(High Setting)</span>
                        </div>
                    </div>
                </div>
                @endif
            </div>
        </div>
        @elseif($result !== null && isset($result['error']))
        <div class="mt-8 bg-red-950/40 border border-red-800/60 rounded-2xl p-6 text-red-400 flex items-start gap-4">
            <span class="text-2xl mt-0.5">⚠️</span>
            <div>
                <h3 class="font-extrabold text-white text-lg">Gagal Membuat Rekomendasi</h3>
                <p class="mt-1.5 text-red-300 text-sm leading-relaxed">{{ $result['error'] }}</p>
                <p class="text-xs text-slate-400 mt-3">Saran: Silakan naikkan budget target Anda atau pilih game target/resolusi yang memiliki spesifikasi minimum lebih rendah.</p>
            </div>
        </div>
        @endif
    </div>

</body>
</html>
