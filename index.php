<?php
include_once 'update_cache.php';

$cacheFile = __DIR__ . '/cache/datasets.json';
$statsFile = __DIR__ . '/data/stats.json';

// ===== LOAD CACHE & STATS =====
$datasets = [];
if (file_exists($cacheFile)) {
    $content = file_get_contents($cacheFile);
    $datasets = json_decode($content, true);
    if (!is_array($datasets))
        $datasets = [];
}

if (!file_exists(__DIR__ . '/data'))
    mkdir(__DIR__ . '/data', 0777, true);
if (!file_exists($statsFile))
    file_put_contents($statsFile, "{}");
$stats = json_decode(file_get_contents($statsFile), true);
if (!is_array($stats))
    $stats = [];

// ===== DATA PREPARATION FOR DISPLAY =====
$sourceDatasets = $datasets;

// --- STATISTIK UMUM PORTAL (Dari contoh gambar) ---
$totalDatasetsCount = count($sourceDatasets); // Gunakan jumlah dataset yang dimuat
$totalKementerian = 70; // Dari image_db87bc.png
$totalProvinsi = 31;    // Dari image_db87bc.png
$totalKabKota = 273;    // Dari image_db87bc.png


$populer = [];
foreach ($sourceDatasets as $d) {
    $id = $d['id'];
    $views = $stats[$id]['views'] ?? 0;
    $populer[] = array_merge($d, ['views' => $views]);
}
usort($populer, fn($a, $b) => $b['views'] <=> $a['views']);
$top5Populer = array_slice($populer, 0, 5);
$top5Terbaru = array_slice($sourceDatasets, 0, 5);

// ===== SEARCH, FILTER & PAGINATION LOGIC =====
$search = $_GET['q'] ?? '';
$topic_filter = $_GET['topic'] ?? '';
$filteredDatasets = $sourceDatasets;

// =================================================================
// 1. FIX: DEFINISI TOPIK GLOBAL (DIPINDAH KE ATAS)
// =================================================================
$topics = [
    'ekonomi' => ["name" => "Ekonomi dan Industri", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18M9.75 3v18M14.25 3v18M18.75 3v18M3.75 6.75h16.5" /></svg>'],
    'lingkungan' => ["name" => "Lingkungan dan SDA", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 3v2.25m6.364.386l-1.591 1.591M21 12h-2.25m-.386 6.364l-1.591-1.591M12 18.75V21m-4.773-4.227l-1.591 1.591M5.25 12H3m4.227-4.773L5.636 5.636M15.75 12a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0z" /></svg>'],
    'budaya' => ["name" => "Budaya dan Agama", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21v-8.25M15.75 21v-8.25M8.25 21v-8.25M3 9l9-6 9 6m-1.5 12V10.332A48.36 48.36 0 0012 9.75c-2.551 0-5.056.2-7.5.582V21M3 21h18M12 6.75h.008v.008H12V6.75z" /></svg>'],
    'sosial' => ["name" => "Sosial & Kesejahteraan", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 003.741-.479 3 3 0 00-4.682-2.72m-7.5-2.962a3.75 3.75 0 015.404 0L18 18.72m-7.5-2.962a3.75 3.75 0 00-5.404 0L6 18.72m-3.375 0a3 3 0 002.72 4.682A9.095 9.095 0 006 21a9.095 9.095 0 003.375-2.28m12.844-3.482a3.75 3.75 0 00-5.404-5.404M6.75 12a3.75 3.75 0 117.5 0 3.75 3.75 0 01-7.5 0z" /></svg>'],
    'pembangunan' => ["name" => "Pembangunan Daerah", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M12 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21" /></svg>'],
    'pendidikan' => ["name" => "Pendidikan", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 12.75c1.148 0 2.278.08 3.383.237 1.037.148 1.867 1.074 1.867 2.176v2.268a2.5 2.5 0 01-2.5 2.5H8.25a2.5 2.5 0 01-2.5-2.5v-2.268c0-1.102.83-2.028 1.867-2.176A48.347 48.347 0 0112 12.75z" /></svg>'],
    'pemerintahan' => ["name" => "Pemerintahan", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M12 18v-5.25m0 0a6.01 6.01 0 001.5-.189m-1.5.189a6.01 6.01 0 01-1.5-.189m3.75 7.478a12.06 12.06 0 01-4.5 0m3.75 2.311a7.5 7.5 0 01-7.5 0c-1.421-.218-2.68-.668-3.75-1.245M12 12.75V15m0 0V15m0 2.25v-2.25m0 2.25c-1.421.218-2.68-.668-3.75 1.245A7.5 7.5 0 0112 15m0 0c1.421-.218 2.68-.668 3.75-1.245A7.5 7.5 0 0012 15m-3.75-.75a3.75 3.75 0 017.5 0" /></svg>'],
    'ketertiban' => ["name" => "Ketertiban", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-3-7.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.602-3.751A11.959 11.959 0 0112 2.714z" /></svg>'],
    'pertahanan' => ["name" => "Pertahanan", "icon" => '<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 3v18h16.5V3H3.75zm8.25 9.75h4.5v-.75h-4.5v.75zm0 2.25h4.5v-.75h-4.5v.75zm0 2.25h4.5v-.75h-4.5v.75zM9 9.75h-1.5v-1.5H9v1.5z" /></svg>'],
];

// --- Kamus Pemetaan Topik (Indonesia -> Inggris) ---
$topicMap = [
    'ekonomi' => ['economy', 'business', 'industry', 'trade', 'finance'],
    'lingkungan' => ['environment', 'disaster', 'climate', 'resource', 'nature'],
    'budaya' => ['culture', 'tourism', 'heritage', 'art', 'religion'],
    'sosial' => ['social', 'welfare', 'population', 'poverty', 'society'],
    'pembangunan' => ['development', 'regional', 'infrastructure', 'urban', 'rural'],
    'pendidikan' => ['education', 'school', 'student', 'research'],
    'pemerintahan' => ['government', 'governance', 'public service', 'parliament'],
    'ketertiban' => ['law', 'justice', 'crime', 'security'],
    'pertahanan' => ['defense', 'military', 'security'],
];

// --- Terapkan Filter Topik ---
if ($topic_filter && isset($topicMap[$topic_filter])) {
    $keywords = $topicMap[$topic_filter];
    $filteredDatasets = array_filter($sourceDatasets, function ($d) use ($keywords) {
        $searchText = $d['title'] . ' ' . ($d['notes'] ?? '');
        if (!empty($d['tags'])) {
            foreach ($d['tags'] as $tag) {
                if (isset($tag['name']))
                    $searchText .= ' ' . $tag['name'];
            }
        }
        foreach ($keywords as $kw) {
            if (stripos($searchText, $kw) !== false)
                return true;
        }
        return false;
    });
}

// --- LOGIKA AUTO TRANSLATE UNTUK PENCARIAN ---
if ($search) {
    $translationMap = [
        'penduduk' => 'population',
        'ekonomi' => 'economy',
        'kesehatan' => 'health',
        'pendidikan' => 'education',
        'lingkungan' => 'environment',
        'cuaca' => 'weather',
        'iklim' => 'climate',
        'keuangan' => 'finance',
        'inflasi' => 'inflation',
        'pertanian' => 'agriculture',
        'peta' => 'map',
        'laporan' => 'report',
        'surat' => 'letter',
        'jalan' => 'road',
        'kota' => 'city',
        'kabupaten' => 'regency',
        'provinsi' => 'province',
    ];

    $search_terms = explode(' ', strtolower($search));
    $translated_terms = [];
    foreach ($search_terms as $term) {
        if (isset($translationMap[$term])) {
            $translated_terms[] = $translationMap[$term];
        }
    }
    $all_search_terms = array_unique(array_merge($search_terms, $translated_terms));

    $filteredDatasets = array_filter($filteredDatasets, function ($d) use ($all_search_terms) {
        $haystack = $d['title'] . ' ' . ($d['notes'] ?? '');
        if (!empty($d['tags'])) {
            foreach ($d['tags'] as $tag) {
                if (isset($tag['name']))
                    $haystack .= ' ' . $tag['name'];
            }
        }
        foreach ($all_search_terms as $term) {
            if (stripos($haystack, $term) !== false) {
                return true;
            }
        }
        return false;
    });
}

// ===== HITUNG STATISTIK TOPIK UNTUK GRAFIK (AMAN KARENA $topics SUDAH DIATAS) =====
$topicCounts = [];
$topicsToProcess = $sourceDatasets;

foreach ($topicsToProcess as $d) {
    // Ambil kata kunci dari judul dan tags
    $haystack = strtolower($d['title'] . ' ' . ($d['notes'] ?? ''));
    if (!empty($d['tags'])) {
        foreach ($d['tags'] as $tag) {
            if (isset($tag['name']))
                $haystack .= ' ' . strtolower($tag['name']);
        }
    }

    // Cek kecocokan untuk setiap topik di $topicMap
    foreach ($topicMap as $topicKey => $keywords) {
        foreach ($keywords as $kw) {
            if (stripos($haystack, $kw) !== false) {
                // Tambahkan hitungan, lalu pindah ke dataset berikutnya
                $topicCounts[$topicKey] = ($topicCounts[$topicKey] ?? 0) + 1;
                continue 3; // Lanjut ke dataset berikutnya
            }
        }
    }
}

// Urutkan dan ambil 5 teratas
arsort($topicCounts);
$top5TopicCounts = array_slice($topicCounts, 0, 5, true);

// Hitung max count untuk persentase bar relatif
$maxTopicCount = max($top5TopicCounts) ?: 1;

// Gabungkan dengan nama dan ikon topik
$top5TopicsData = [];
foreach ($top5TopicCounts as $key => $count) {
    if (isset($topics[$key])) {
        $top5TopicsData[] = [
            'name' => $topics[$key]['name'],
            'count' => $count,
            'icon' => $topics[$key]['icon'],
            'percentage' => round(($count / $maxTopicCount) * 100), // Persentase bar relatif
        ];
    }
}
// END OF STATISTIK TOPIK

// =================================================================
// 2. LOGIKA STATISTIK FORMAT FILE (BARU DITAMBAHKAN)
// =================================================================
$formatCounts = [];
$formatMap = [
    'CSV' => ['csv', 'text/csv'],
    'XLSX' => ['xlsx', 'xls', 'spreadsheet', 'excel'],
    'API/JSON' => ['api', 'json', 'xml', 'application/json', 'geo-json'],
    'SHP/Geo' => ['shp', 'geojson', 'kml', 'peta', 'geographic'],
    'PDF/Dokumen' => ['pdf', 'doc', 'docx', 'document'],
    'Gambar/Media' => ['jpg', 'png', 'jpeg', 'tif'],
    'Lainnya' => [],
];

foreach ($sourceDatasets as $d) {
    if (!isset($d['resources']) || !is_array($d['resources'])) {
        continue;
    }

    $formats = [];
    foreach ($d['resources'] as $resource) {
        $format = strtoupper($resource['format'] ?? '');

        if (empty($format) && isset($resource['url'])) {
            $path = parse_url($resource['url'], PHP_URL_PATH);
            $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $format = $ext;
        }

        if (!empty($format)) {
            $formats[] = $format;
        }
    }

    $countedCategories = [];
    foreach ($formats as $format) {
        $foundKey = 'Lainnya';
        $formatLower = strtolower($format);

        foreach ($formatMap as $key => $keywords) {
            if (in_array($formatLower, $keywords) || stripos($formatLower, strtolower($key)) !== false) {
                $foundKey = $key;
                break;
            }
        }

        // Pastikan hanya menghitung satu kali per dataset, meskipun memiliki banyak format di kategori yang sama
        if (!in_array($foundKey, $countedCategories)) {
            $formatCounts[$foundKey] = ($formatCounts[$foundKey] ?? 0) + 1;
            $countedCategories[] = $foundKey;
        }
    }
}

$formatDataForChart = [];
$maxFormatCount = max($formatCounts) ?: 1;

if (!empty($formatCounts)) {
    arsort($formatCounts);
    foreach ($formatCounts as $format => $count) {
        $percentage = round(($count / $maxFormatCount) * 100);

        $formatDataForChart[] = [
            'name' => $format,
            'count' => $count,
            'percentage' => $percentage,
        ];
    }
}
// END OF FORMAT FILE


// ===== PAGINATION LOGIC =====
$perPage = 9;
$total = count($filteredDatasets);
$page = max(1, intval($_GET['page'] ?? 1));
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;
$pagedData = array_slice($filteredDatasets, $offset, $perPage);

// --- LAST UPDATED ---
$lastUpdate = file_exists($cacheFile) ? date("d M Y H:i", filemtime($cacheFile)) : "Belum pernah diperbarui";
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Data Web App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');
        *{
            scroll-behavior : smooth
        }
        body {
            font-family: 'Poppins', sans-serif;
            opacity: 0;
            animation: fadeInPage 1s ease forwards;
        }

        @keyframes fadeInPage {
            from {
                opacity: 0;
                transform: translateY(15px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        [data-animate] {
            opacity: 0;
            transform: translateY(30px);
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }

        [data-animate].visible {
            opacity: 1;
            transform: translateY(0);
        }

        [data-animate-left] {
            opacity: 0;
            transform: translateX(-40px);
            transition: all 0.9s ease-out;
        }

        [data-animate-right] {
            opacity: 0;
            transform: translateX(40px);
            transition: all 0.9s ease-out;
        }

        .visible[data-animate-left],
        .visible[data-animate-right] {
            opacity: 1;
            transform: translateX(0);
        }

        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .hover-lift:hover {
            transform: translateY(-6px) scale(1.02);
            box-shadow: 0 8px 22px rgba(0, 0, 0, 0.1);
        }

        a,
        button {
            transition: all 0.3s ease;
        }

        a:hover,
        button:hover {
            transform: scale(1.03);
        }

        /* Parallax efek hero image */
        .parallax {
            transition: transform 0.6s ease-out;
        }
        
        /* Style untuk Bar Chart Animation */
        .transition-all { transition: all 1s ease-out; }
    </style>
</head>

<body class="bg-gray-50 text-gray-800">

    <header class="bg-white shadow-sm sticky top-0 z-50" data-animate>
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <a href="index.php" class="flex items-center space-x-2">
                    <svg class="h-8 w-8 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"
                        stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round"
                            d="M3.75 12.75h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                    </svg>
                    <span class="text-xl font-bold text-gray-800">Web Open-Data</span>
                </a>
                <nav class="hidden md:flex space-x-8">
                    <a href="index.php" class="text-sm font-medium text-gray-500 hover:text-red-600">Home</a>
                    <a href="#all-datasets" class="text-sm font-medium text-gray-500 hover:text-red-600">Dataset</a>
                    <a href="#all_data" class="text-sm font-medium text-gray-500 hover:text-red-600">Topik Data</a>
                </nav>
            </div>
        </div>
    </header>

    <main>
        <section class="bg-white" data-animate>
            <div class="max-w-7xl mx-auto py-16 px-4 sm:px-6 lg:px-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-12 items-center">
                    <div class="md:col-span-1">
                        <span class="text-sm font-bold uppercase text-red-600 tracking-wider">Selamat Datang di Web
                            Open-Data</span>
                        <h1 class="text-4xl md:text-5xl font-extrabold my-3 text-gray-900 leading-tight">Satu Portal
                            untuk Semua Kebutuhan Data Anda</h1>
                        <p class="text-lg text-gray-500 max-w-2xl">Jelajahi, analisis, dan unduh beragam kumpulan data
                            berkualitas tinggi untuk mendukung riset, inovasi, dan pengambilan keputusan Anda.</p>
                        <div class="mt-8 flex items-center space-x-6 text-gray-600">
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-red-500" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4" />
                                </svg>
                                <span class="font-semibold"><?= number_format($totalDatasetsCount) ?>+</span>&nbsp;Dataset
                            </div>
                            <div class="flex items-center">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 mr-2 text-red-500" fill="none"
                                    viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                    <path stroke-linecap="round" stroke-linejoin="round"
                                        d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                </svg>
                                <span class="font-semibold"><?= $totalKementerian ?>+</span>&nbsp;
                            </div>
                        </div>
                    </div>
                    <div class="md:col-span-1">
                        <img src="assets/image/hero_image_well.png"
                            alt="Ilustrasi orang menganalisis data pada layar besar" class="w-full h-auto rounded-lg">
                    </div>
                </div>
            </div>
        </section>

        <section id="search-section" class="py-16 bg-gray-100" data-animate>
            <div class="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
                <h2 class="text-3xl font-bold text-gray-900">Temukan Dataset yang Anda Butuhkan</h2>
                <p class="text-md text-gray-500 max-w-2xl mx-auto mt-3 mb-8">Gunakan kata kunci untuk menemukan dataset
                    berdasarkan judul atau deskripsinya.</p>
                <form method="get" action="#all-datasets" class="max-w-2xl mx-auto">
                    <div class="relative flex items-center">
                        <svg class="absolute left-4 h-6 w-6 text-gray-400" xmlns="http://www.w3.org/2000/svg"
                            fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input type="text" name="q" placeholder="Cari 'laporan', 'penduduk', 'kesehatan'..."
                            value="<?= htmlspecialchars($search) ?>"
                            class="border-gray-300 shadow-sm rounded-full py-4 pl-12 pr-32 w-full focus:ring-2 focus:ring-red-500 focus:border-red-500 transition text-lg">
                        <button type="submit"
                            class="absolute right-2 flex items-center bg-red-600 text-white px-8 py-3 rounded-full text-base font-semibold hover:bg-red-700 transition">Cari</button>
                    </div>
                </form>
            </div>
        </section>

        <section class="py-16 bg-white" data-animate>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <h2 class="text-3xl font-extrabold text-gray-900 mb-8 text-center">
                    Statistik Distribusi Dataset
                </h2>
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">

                    <div class="p-6 bg-gray-50 rounded-xl shadow-lg border border-gray-200 h-full">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l2-2 2 2 2-2 2 2m-6-4h4m10 6v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16-4H4a2 2 0 01-2-2V7a2 2 0 012-2h16a2 2 0 012 2v3a2 2 0 01-2 2z"></path></svg>
                            Top 5 Dataset Berdasarkan Topik
                        </h3>
                        <div class="space-y-4">
                            <?php if (!empty($top5TopicsData)): ?>
                                <?php foreach ($top5TopicsData as $data): ?>
                                    <div class="flex items-center">
                                        <span class="w-2/5 text-sm font-medium text-gray-700 flex items-center">
                                            <span class="text-red-600 mr-2 h-5 w-5"><?= $data['icon'] ?></span>
                                            <?= htmlspecialchars($data['name']) ?>
                                        </span>
                                        <div class="w-3/5 flex items-center">
                                            <div class="flex-grow bg-gray-200 rounded-full h-2.5 mr-3">
                                                <div class="bg-red-600 h-2.5 rounded-full transition-all duration-1000 ease-out"
                                                    style="width: 0%;" data-width="<?= $data['percentage'] ?>">
                                                </div>
                                            </div>
                                            <span class="text-xs font-semibold text-gray-600 w-10 text-right">
                                                <?= number_format($data['count']) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center p-8 text-gray-500">
                                    <p>Tidak ada data topik yang cukup untuk ditampilkan.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="p-6 bg-gray-50 rounded-xl shadow-lg border border-gray-200 h-full">
                        <h3 class="text-xl font-bold text-gray-900 mb-4 flex items-center">
                            <svg class="w-6 h-6 mr-2 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 7v10a2 2 0 002 2h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2zM9 9h6m-6 4h4" />
                            </svg>
                            Distribusi Dataset Berdasarkan Format
                        </h3>
                        <div class="space-y-4">
                            <?php if (!empty($formatDataForChart)): ?>
                                <?php foreach ($formatDataForChart as $data): ?>
                                    <div class="flex items-center">
                                        <span class="w-2/5 text-sm font-medium text-gray-700"><?= $data['name'] ?></span>
                                        <div class="w-3/5 flex items-center">
                                            <div class="flex-grow bg-gray-200 rounded-full h-2.5 mr-3">
                                                <div class="bg-red-600 h-2.5 rounded-full transition-all duration-1000 ease-out"
                                                    style="width: 0%;" data-width="<?= $data['percentage'] ?>">
                                                </div>
                                            </div>
                                            <span class="text-xs font-semibold text-gray-600 w-10 text-right">
                                                <?= number_format($data['count']) ?>
                                            </span>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="text-center p-8 text-gray-500">
                                    <p>Tidak ada data format file yang cukup untuk ditampilkan.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </section>
        <section class="bg-white py-20" data-animate>
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center mb-12">
                    <h2 id="all_data" class="text-3xl font-extrabold text-gray-900">Topik Data</h2>
                    <div class="mt-3 h-1 w-20 bg-red-600 mx-auto rounded-full"></div>
                </div>
                <div class="grid grid-cols-2 md:grid-cols-4 lg:grid-cols-5 gap-4">
                    <?php
                    // Variabel $topics sudah didefinisikan di awal PHP, jadi kode ini aman
                    if ($topic_filter) {
                        echo '<a href="index.php#all-datasets" class="group flex items-center justify-center p-4 border-2 border-red-600 rounded-lg bg-red-600 text-white transition-colors duration-200">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="w-6 h-6 mr-2"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" /></svg>
                                <span class="ml-1 text-sm font-semibold">Reset Filter</span>
                              </a>';
                    }

                    foreach ($topics as $key => $topic) {
                        $isActive = ($topic_filter === $key);
                        $activeClasses = $isActive ? 'border-red-500 bg-red-50' : 'border-gray-200 bg-white';
                        $textClasses = $isActive ? 'text-red-600' : 'text-gray-700 group-hover:text-red-600';
                        echo '<a href="?topic=' . $key . '#all-datasets" class="group flex items-center p-4 border rounded-lg hover:border-red-500 hover:bg-red-50 transition-colors duration-200 ' . $activeClasses . '">
                                <div class="text-red-600">' . $topic['icon'] . '</div>
                                <span class="ml-4 text-sm font-medium ' . $textClasses . '">' . $topic['name'] . '</span>
                              </a>';
                    }
                    ?>
                </div>
            </div>
        </section>

        <section class="max-w-7xl mx-auto py-16 px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-extrabold text-gray-900">Dataset Pilihan</h2>
                <div class="mt-3 h-1 w-20 bg-red-600 mx-auto rounded-full"></div>
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div class="bg-white rounded-lg shadow-md border border-gray-200">
                    <div class="bg-yellow-500 text-white font-bold p-4 rounded-t-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                            class="w-6 h-6 mr-3">
                            <path fill-rule="evenodd"
                                d="M10.788 3.21c.448-1.077 1.976-1.077 2.424 0l2.082 5.007 5.404.433c1.164.093 1.636 1.545.749 2.305l-4.117 3.527 1.257 5.273c.271 1.136-.964 2.033-1.96 1.425L12 18.354 7.373 21.18c-.996.608-2.231-.29-1.96-1.425l1.257-5.273-4.117-3.527c-.887-.76-.415-2.212.749-2.305l5.404-.433 2.082-5.006z"
                                clip-rule="evenodd" />
                        </svg>
                        Dataset Populer
                    </div>
                    <div class="p-4 space-y-4">
                        <?php foreach ($top5Populer as $d): ?>
                            <div class="border-b last:border-b-0 pb-4 last:pb-0">
                                <?php
                                $formats = ['XLSX', 'PDF', 'RAR', 'CSV'];
                                $format = $d['format'] ?? $formats[array_rand($formats)];
                                $format_color = ['XLSX' => 'bg-green-600', 'PDF' => 'bg-red-600', 'RAR' => 'bg-purple-600', 'CSV' => 'bg-blue-600'];
                                ?>
                                <span
                                    class="text-xs font-bold text-white px-2 py-1 rounded-md <?= $format_color[$format] ?>"><?= $format ?></span>
                                <a href="dataset.php?id=<?= urlencode($d['id']) ?>"
                                    class="block text-md font-bold text-gray-800 hover:text-red-600 mt-2"><?= htmlspecialchars($d['title']) ?></a>
                                <p class="text-sm text-gray-500 mt-1 line-clamp-2">
                                    <?= htmlspecialchars($d['notes'] ?? 'Tidak ada deskripsi.') ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow-md border border-gray-200">
                    <div class="bg-red-600 text-white font-bold p-4 rounded-t-lg flex items-center">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"
                            class="w-6 h-6 mr-3">
                            <path
                                d="M12.75 3.002c.38-.03.763-.03 1.146 0l4.333.325c.381.028.718.232.923.518.206.285.282.639.193.97l-1.523 5.71a.75.75 0 01-.718.552H17.25a.75.75 0 00-.75.75 2.25 2.25 0 002.25 2.25c.53 0 1.01-.18 1.386-.486l1.233 1.644A4.232 4.232 0 0119.5 18a4.5 4.5 0 01-4.5-4.5c0-1.21.474-2.325 1.242-3.151L12 3.002zM8.25 4.877c-.38-.03-.763-.03-1.146 0L2.77 5.202a1.5 1.5 0 00-1.316 1.488l1.523 5.71a.75.75 0 01-.718.552H1.5a.75.75 0 00-.75.75 2.25 2.25 0 002.25 2.25c.53 0 1.01-.18 1.386-.486l1.233 1.644A4.232 4.232 0 014.5 18a4.5 4.5 0 01-4.5-4.5c0-1.21.474-2.325 1.242-3.151L8.25 4.877z" />
                        </svg>
                        Dataset Terbaru
                    </div>
                    <div class="p-4 space-y-4">
                        <?php foreach ($top5Terbaru as $d): ?>
                            <div class="border-b last:border-b-0 pb-4 last:pb-0">
                                <?php $format = $d['format'] ?? $formats[array_rand($formats)]; ?>
                                <span
                                    class="text-xs font-bold text-white px-2 py-1 rounded-md <?= $format_color[$format] ?>"><?= $format ?></span>
                                <a href="dataset.php?id=<?= urlencode($d['id']) ?>"
                                    class="block text-md font-bold text-gray-800 hover:text-red-600 mt-2"><?= htmlspecialchars($d['title']) ?></a>
                                <p class="text-sm text-gray-500 mt-1 line-clamp-2">
                                    <?= htmlspecialchars($d['notes'] ?? 'Tidak ada deskripsi.') ?>
                                </p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <section id="all-datasets" class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16" data-animate>
            <div class="text-center mb-8">
                <h2 class="text-3xl font-extrabold text-gray-900">
                    <?php
                    if ($search)
                        echo 'Hasil Pencarian';
                    elseif ($topic_filter && isset($topics[$topic_filter]))
                        echo 'Topik: ' . htmlspecialchars($topics[$topic_filter]['name']);
                    else
                        echo 'Jelajahi Semua Dataset';
                    ?>
                </h2>
                <div class="mt-3 h-1 w-20 bg-red-600 mx-auto rounded-full"></div>
                <p class="text-sm text-gray-500 mt-4">Menampilkan <?= count($pagedData) ?> dari <?= $total ?> hasil</p>
            </div>
            <?php if (empty($pagedData)): ?>
                <div class="text-center bg-white rounded-lg border border-gray-200 p-12">
                    <h3 class="mt-2 text-lg font-medium text-gray-900">⚠️ Tidak ada dataset ditemukan</h3>
                    <p class="mt-1 text-sm text-gray-500">Filter atau kata kunci yang Anda gunakan tidak menemukan hasil.
                    </p>
                    <a href="index.php#all-datasets"
                        class="mt-6 inline-block bg-red-600 text-white font-semibold px-6 py-2 rounded-md hover:bg-red-700">
                        Hapus Filter & Muat Ulang
                    </a>
                </div>
            <?php else: ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($pagedData as $d): ?>
                        <?php
                            $current_stats = $stats[$d['id']] ?? [];
                            ?>
                        <div
                            class="bg-white border border-gray-200 shadow-sm rounded-xl p-6 hover:shadow-lg hover:-translate-y-1 transition-all duration-300 flex flex-col">
                            <h3 class="text-lg font-bold text-gray-900 mb-2 line-clamp-2"><?= htmlspecialchars($d['title']) ?>
                            </h3>
                            <p class="text-sm text-gray-600 mb-4 flex-grow line-clamp-3">
                                <?= htmlspecialchars(substr($d['notes'] ?? 'Tidak ada deskripsi.', 0, 120)) . '...' ?>
                            </p>
                            <div class="border-t border-gray-100 pt-4 mt-auto">
                                <div class="flex justify-between items-center">
                                    <div class="text-xs text-gray-400 flex items-center space-x-4">
                                        <span class="flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path>
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z">
                                                </path>
                                            </svg>
                                            <span><?= $current_stats['views'] ?? 0 ?></span>
                                        </span>
                                        <span class="flex items-center space-x-1">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path>
                                            </svg>
                                            <span><?= $current_stats['downloads'] ?? 0 ?></span>
                                        </span>
                                    </div>
                                    <a href="dataset.php?id=<?= urlencode($d['id']) ?>"
                                        class="inline-block text-red-600 text-sm font-semibold hover:text-red-800 transition-colors">
                                        Detail &rarr;
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <nav class="flex justify-center mt-12">
                        <div class="flex items-center bg-white rounded-lg shadow-sm border border-gray-200">
                            <?php
                            $queryParams = http_build_query(['q' => $search, 'topic' => $topic_filter]);
                            if ($page > 1) {
                                echo '<a href="?page=' . ($page - 1) . '&' . $queryParams . '#all-datasets" class="px-3 py-2 text-gray-500 hover:bg-gray-100 transition-colors rounded-l-md"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M12.707 5.293a1 1 0 010 1.414L9.414 10l3.293 3.293a1 1 0 01-1.414 1.414l-4-4a1 1 0 010-1.414l4-4a1 1 0 011.414 0z" clip-rule="evenodd"></path></svg></a>';
                            }
                            $window = 2;
                            for ($i = 1; $i <= $totalPages; $i++) {
                                if ($i == 1 || $i == $totalPages || ($i >= $page - $window && $i <= $page + $window)) {
                                    $active = $i == $page ? 'bg-red-600 text-white hover:bg-red-700 z-10' : 'bg-white text-gray-600 hover:bg-gray-50';
                                    echo '<a href="?page=' . $i . '&' . $queryParams . '#all-datasets" class="px-4 py-2 text-sm font-medium border-l border-gray-200 ' . $active . ' transition-colors">' . $i . '</a>';
                                } elseif ($i == $page - $window - 1 || $i == $page + $window + 1) {
                                    echo '<span class="px-4 py-2 text-sm font-medium text-gray-500 border-l border-gray-200">...</span>';
                                }
                            }
                            if ($page < $totalPages) {
                                echo '<a href="?page=' . ($page + 1) . '&' . $queryParams . '#all-datasets" class="px-3 py-2 text-gray-500 hover:bg-gray-100 transition-colors rounded-r-md border-l border-gray-200"><svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M7.293 14.707a1 1 0 010-1.414L10.586 10 7.293 6.707a1 1 0 011.414-1.414l4 4a1 1 0 010 1.414l-4 4a1 1 0 01-1.414 0z" clip-rule="evenodd"></path></svg></a>';
                            }
                            ?>
                        </div>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </main>

    <footer class="bg-white border-t mt-12" data-animate>
        <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div class="space-y-4">
                    <a href="index.php" class="flex items-center space-x-2">
                        <svg class="h-8 w-8 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none"
                            viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round"
                                d="M3.75 12.75h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" />
                        </svg>
                        <span class="text-xl font-bold text-gray-800">Web Open-Data</span>
                    </a>
                    <p class="text-sm text-gray-500">Menyediakan akses data yang mudah, cepat, dan akurat untuk
                        mendorong inovasi dan transparansi.</p>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 tracking-wider uppercase">Navigasi</h3>
                    <ul class="mt-4 space-y-2">
                        <li><a href="#" class="text-sm text-gray-500 hover:text-red-600">Datasets</a></li>
                        <li><a href="#" class="text-sm text-gray-500 hover:text-red-600">Instansi</a></li>
                        <li><a href="#" class="text-sm text-gray-500 hover:text-red-600">Topik Data</a></li>
                        <li><a href="#" class="text-sm text-gray-500 hover:text-red-600">Tentang Kami</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 tracking-wider uppercase">Bantuan</h3>
                    <ul class="mt-4 space-y-2">
                        <li><a href="#" class="text-sm text-gray-500 hover:text-red-600">FAQ</a></li>
                        <li><a href="#" class="text-sm text-gray-500 hover:text-red-600">Hubungi Kami</a></li>
                        <li><a href="#" class="text-sm text-gray-500 hover:text-red-600">Kebijakan Privasi</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 tracking-wider uppercase">Ikuti Kami</h3>
                    <div class="flex mt-4 space-x-4">
                        <a href="#" class="text-gray-400 hover:text-red-600"><span class="sr-only">Twitter</span><svg
                                class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path
                                    d="M8.29 20.251c7.547 0 11.675-6.253 11.675-11.675 0-.178 0-.355-.012-.53A8.348 8.348 0 0022 5.92a8.19 8.19 0 01-2.357.646 4.118 4.118 0 001.804-2.27 8.224 8.224 0 01-2.605.996 4.107 4.107 0 00-6.993 3.743 11.65 11.65 0 01-8.457-4.287 4.106 4.106 0 001.27 5.477A4.072 4.072 0 012.8 9.71v.052a4.105 4.105 0 003.292 4.022 4.095 4.095 0 01-1.853.07 4.108 4.108 0 003.834 2.85A8.233 8.233 0 012 18.407a11.616 11.616 0 006.29 1.84" />
                            </svg></a>
                        <a href="https://www.instagram.com/friesszx?igsh=M2VwbDZwYjhxbTUz"
                            class="text-gray-400 hover:text-red-600"><span class="sr-only">Instagram</span><svg
                                class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                <path fill-rule="evenodd"
                                    d="M12.315 2c2.43 0 2.784.013 3.808.06 1.064.049 1.791.218 2.427.465a4.902 4.902 0 011.772 1.153 4.902 4.902 0 011.153 1.772c.247.636.416 1.363.465 2.427.048 1.024.06 1.378.06 3.808s-.012 2.784-.06 3.808c-.049 1.064-.218 1.791-.465 2.427a4.902 4.902 0 01-1.153 1.772 4.902 4.902 0 01-1.772 1.153c-.636.247-1.363.416-2.427.465-1.024.048-1.378.06-3.808.06s-2.784-.013-3.808-.06c-1.064-.049-1.791-.218-2.427-.465a4.902 4.902 0 01-1.772-1.153 4.902 4.902 0 01-1.153-1.772c-.247-.636-.416-1.363-.465-2.427-.048-1.024-.06-1.378-.06-3.808s.012-2.784.06-3.808c.049-1.064.218-1.791.465-2.427a4.902 4.902 0 011.153-1.772A4.902 4.902 0 016.345 2.525c.636-.247 1.363-.416 2.427-.465C9.795 2.013 10.148 2 12.315 2zm-1.163 1.943c-1.042.045-1.71.21-2.225.41a3.001 3.001 0 00-1.13 1.13c-.2.515-.365 1.183-.41 2.225-.045 1.02-.058 1.349-.058 3.778s.013 2.758.058 3.778c.045 1.042.21 1.71.41 2.225a3.001 3.001 0 001.13 1.13c.515.2 1.183.365 2.225.41 1.02.045 1.349.058 3.778.058s2.758-.013 3.778-.058c1.042-.045 1.71-.21 2.225-.41a3.001 3.001 0 001.13-1.13c.2-.515.365-1.183.41-2.225.045-1.02.058-1.349-.058-3.778s-.013-2.758-.058-3.778c-.045-1.042-.21-1.71-.41-2.225a3.001 3.001 0 00-1.13-1.13c-.515-.2-1.183-.365-2.225-.41-1.02-.045-1.349-.058-3.778-.058zM12 6.865a5.135 5.135 0 100 10.27 5.135 5.135 0 000-10.27zm0 1.802a3.333 3.333 0 110 6.666 3.333 3.333 0 010-6.666zm5.338-3.205a1.2 1.2 0 100 2.4 1.2 1.2 0 000-2.4z"
                                    clip-rule="evenodd" />
                            </svg></a>
                    </div>
                </div>
            </div>
            <div class="mt-8 border-t border-gray-200 pt-8 text-center">
                <p class="text-sm text-gray-500">&copy; <?= date("Y") ?> Open Data Web App. All rights reserved.
                </p>
            </div>
        </div>
    </footer>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            const elements = document.querySelectorAll("[data-animate], [data-animate-left], [data-animate-right]");
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("visible");
                    } else {
                        // Anda dapat menghapus baris ini jika ingin animasi hanya dipicu sekali
                        entry.target.classList.remove("visible");
                    }
                });
            }, { threshold: 0.15 });

            elements.forEach(el => observer.observe(el));

            // Parallax efek pada hero image
            const heroImg = document.querySelector(".parallax");
            window.addEventListener("scroll", () => {
                if (heroImg) {
                    const offset = window.scrollY * 0.2;
                    heroImg.style.transform = `translateY(${offset}px)`;
                }
            });

            // Animasi Bar Chart
            const chartBars = document.querySelectorAll('[data-width]');
            chartBars.forEach(bar => {
                // Atur lebar awal ke 0% agar animasinya terlihat
                bar.style.width = '0%';
            });

            // Observer untuk memicu animasi saat chart terlihat
            const chartObserver = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        // Query ulang di dalam entry.target agar hanya memproses bar di chart yang terlihat
                        const bars = entry.target.querySelectorAll('[data-width]');
                        bars.forEach(bar => {
                            // Terapkan lebar yang sebenarnya untuk memicu CSS transition
                            const actualWidth = bar.getAttribute('data-width');
                            bar.style.width = actualWidth + '%';
                        });
                        // Hentikan observer setelah animasi dipicu
                        chartObserver.unobserve(entry.target);
                    }
                });
            }, { threshold: 0.2 });

            // Ambil elemen induk dari chart (section Statistik Distribusi Dataset yang baru)
            const chartSection = document.querySelector('section.py-16.bg-white:not([id])');
            if (chartSection) {
                chartObserver.observe(chartSection);
            }
        });
    </script>
</body>
</html>