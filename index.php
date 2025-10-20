<?php
include_once 'update_cache.php';

$cacheFile = __DIR__ . '/cache/datasets.json';
$statsFile = __DIR__ . '/data/stats.json';

// ===== LOAD CACHE =====
$datasets = [];
if (file_exists($cacheFile)) {
    $content = file_get_contents($cacheFile);
    $datasets = json_decode($content, true);
    if (!is_array($datasets)) $datasets = [];
}

// ===== LOAD STATS =====
if (!file_exists(__DIR__ . '/data')) mkdir(__DIR__ . '/data', 0777, true);
if (!file_exists($statsFile)) file_put_contents($statsFile, "{}");
$stats = json_decode(file_get_contents($statsFile), true);
if (!is_array($stats)) $stats = [];

// ===== SEARCH =====
$search = $_GET['q'] ?? '';
if ($search && !empty($datasets)) {
    $datasets = array_filter($datasets, function ($d) use ($search) {
        return stripos($d['title'], $search) !== false ||
               stripos($d['notes'] ?? '', $search) !== false;
    });
}

// ===== PAGINATION =====
$perPage = 9;
$total = count($datasets);
$page = max(1, intval($_GET['page'] ?? 1));
$totalPages = max(1, ceil($total / $perPage));
$offset = ($page - 1) * $perPage;
$pagedData = array_slice($datasets, $offset, $perPage);

// ===== LAST UPDATED =====
$lastUpdate = file_exists($cacheFile)
    ? date("d M Y H:i", filemtime($cacheFile))
    : "Belum pernah diperbarui";

// ====== DATA UNTUK CHART ======
// Urutkan berdasarkan views tertinggi
$populer = [];
foreach ($datasets as $d) {
    $id = $d['id'];
    $views = $stats[$id]['views'] ?? 0;
    $downloads = $stats[$id]['downloads'] ?? 0;
    $populer[] = [
        'title' => $d['title'],
        'views' => $views,
        'downloads' => $downloads,
    ];
}

// Ambil 5 dataset terpopuler
usort($populer, fn($a, $b) => $b['views'] <=> $a['views']);
$top5 = array_slice($populer, 0, 5);

$chartLabels = array_column($top5, 'title');
$chartViews = array_column($top5, 'views');
$chartDownloads = array_column($top5, 'downloads');
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Data Global</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-100 text-slate-800">
    <div class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        
        <div class="text-center mb-10">
            <h1 class="text-4xl md:text-5xl font-extrabold mb-2 text-indigo-700">🌐 Open Data Global</h1>
            <p class="text-slate-500">Jelajahi kumpulan data dari seluruh dunia.</p>
            <p class="text-xs text-slate-400 mt-2">Terakhir update: <?= htmlspecialchars($lastUpdate) ?></p>
        </div>

        <!-- ====== CHART DATASET POPULER ====== -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-10">
            <h2 class="text-xl font-bold mb-4 text-indigo-700">🔥 Dataset Terpopuler</h2>
            <canvas id="popularChart" height="100"></canvas>
        </div>

        <!-- ====== FORM SEARCH ====== -->
        <form method="get" class="mb-8 max-w-2xl mx-auto">
            <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                    <svg class="h-5 w-5 text-gray-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8 4a4 4 0 100 8 4 4 0 000-8zM2 8a6 6 0 1110.89 3.476l4.817 4.817a1 1 0 01-1.414 1.414l-4.816-4.816A6 6 0 012 8z" clip-rule="evenodd" />
                    </svg>
                </div>
                <input type="text" name="q" placeholder="Cari dataset berdasarkan judul atau deskripsi..." 
                       value="<?= htmlspecialchars($search) ?>"
                       class="border border-slate-300 rounded-full py-3 pl-10 pr-32 w-full focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500 transition">
                <button class="absolute inset-y-0 right-0 flex items-center bg-indigo-600 text-white px-6 rounded-full m-1.5 text-sm font-semibold hover:bg-indigo-700 transition">
                    Cari
                </button>
            </div>
        </form>

        <!-- ====== LIST DATASET ====== -->
        <?php if (empty($pagedData)): ?>
            <div class="text-center bg-white rounded-lg shadow-md p-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path vector-effect="non-scaling-stroke" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 13h6m-3-3v6m-9 1V7a2 2 0 012-2h6l2 2h6a2 2 0 012 2v8a2 2 0 01-2 2H5a2 2 0 01-2-2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">⚠️ Tidak ada dataset ditemukan</h3>
                <p class="mt-1 text-sm text-gray-500">Coba kata kunci lain atau muat ulang data dari sumber.</p>
                <div class="mt-6">
                    <a href="update_cache.php" class="inline-flex items-center px-4 py-2 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-indigo-600 hover:bg-indigo-700">
                        Update Cache
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($pagedData as $d): ?>
                    <div class="bg-white border border-slate-200 shadow-sm rounded-xl p-6 hover:shadow-xl hover:-translate-y-1 transition-all duration-300 flex flex-col">
                        <h2 class="text-lg font-bold text-indigo-800 mb-2"><?= htmlspecialchars($d['title']) ?></h2>
                        <p class="text-sm text-slate-600 mb-4 flex-grow">
                            <?= htmlspecialchars(substr($d['notes'] ?? 'Tidak ada deskripsi', 0, 120)) ?>...
                        </p>
                        <div class="text-xs text-slate-400 mb-4 flex items-center space-x-4">
                            <span class="flex items-center">
                                👁️ <?= $stats[$d['id']]['views'] ?? 0 ?>
                            </span>
                             <span class="flex items-center">
                                ⬇️ <?= $stats[$d['id']]['downloads'] ?? 0 ?>
                            </span>
                        </div>
                        <a href="dataset.php?id=<?= urlencode($d['id']) ?>" 
                           class="inline-block text-center bg-indigo-600 text-white text-sm font-semibold px-4 py-2 rounded-lg hover:bg-indigo-700 transition-colors w-full">
                            Lihat Detail
                        </a>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- ====== PAGINATION ====== -->
            <div class="flex justify-center items-center mt-10 space-x-1">
                <?php
                $maxPagesToShow = 5;
                $half = floor($maxPagesToShow / 2);
                $startPage = max(1, $page - $half);
                $endPage = min($totalPages, $page + $half);
                
                if ($page > 1)
                    echo '<a href="?page=' . ($page - 1) . '&q=' . urlencode($search) . '" class="px-3 py-2 bg-white rounded-md shadow-sm text-sm font-medium text-slate-700 hover:bg-slate-50">← Prev</a>';

                for ($i = $startPage; $i <= $endPage; $i++) {
                    $active = $i == $page ? 'bg-indigo-600 text-white' : 'bg-white text-slate-700 hover:bg-slate-50';
                    echo '<a href="?page=' . $i . '&q=' . urlencode($search) . '" class="px-4 py-2 rounded-md shadow-sm text-sm font-medium ' . $active . '">' . $i . '</a>';
                }

                if ($page < $totalPages)
                    echo '<a href="?page=' . ($page + 1) . '&q=' . urlencode($search) . '" class="px-3 py-2 bg-white rounded-md shadow-sm text-sm font-medium text-slate-700 hover:bg-slate-50">Next →</a>';
                ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- ====== CHART SCRIPT ====== -->
    <script>
        const ctx = document.getElementById('popularChart');
        const popularChart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: <?= json_encode($chartLabels, JSON_UNESCAPED_UNICODE) ?>,
                datasets: [
                    {
                        label: 'Views 👁️',
                        data: <?= json_encode($chartViews) ?>,
                        backgroundColor: 'rgba(79, 70, 229, 0.8)',
                        borderRadius: 6
                    },
                    {
                        label: 'Downloads ⬇️',
                        data: <?= json_encode($chartDownloads) ?>,
                        backgroundColor: 'rgba(16, 185, 129, 0.7)',
                        borderRadius: 6
                    }
                ]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: { position: 'bottom' },
                    title: {
                        display: false
                    }
                },
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Auto-refresh setiap 10 menit (silent)
        setInterval(() => {
            fetch('update_cache.php?silent=true').catch(console.error);
        }, 10 * 60 * 1000);
    </script>
</body>
</html>
