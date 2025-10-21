<?php
$id = $_GET['id'] ?? null;
if (!$id) die("Dataset tidak ditemukan.");

$cacheFile = __DIR__ . '/cache/datasets.json';
if (!file_exists($cacheFile)) die("Cache belum dibuat, jalankan update_cache.php dulu.");
$datasets = json_decode(file_get_contents($cacheFile), true);

$dataset = null;
foreach ($datasets as $d) {
    if ($d['id'] === $id) { 
        $dataset = $d; 
        break; 
    }
}
if (!$dataset) die("Data tidak ditemukan.");

// ====== STATS ======
$statsFile = __DIR__ . '/data/stats.json';
$stats = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : [];
if (!isset($stats[$id])) $stats[$id] = ["views" => 0, "downloads" => 0];
$stats[$id]['views']++;
file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));

// ====== CARI RESOURCE YANG BISA DIBACA (CSV/JSON) ======
$dataUrl = null;
$fileType = null;
if (!empty($dataset['resources'])) {
    foreach ($dataset['resources'] as $r) {
        $url = $r['download_url'] ?? $r['url'] ?? $r['accessURL'] ?? null;
        if (!empty($url) && preg_match('/\.(csv|json)$/i', $url)) {
            $fileType = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
            $dataUrl = $url;
            break;
        }
    }
}

// ====== PREVIEW DATA ======
$preview = [];
$columns = [];
$totalRows = 0;
$error = null;
if ($dataUrl) {
    try {
        $content = @file_get_contents($dataUrl);
        if ($content === false) {
            $error = "Tidak dapat mengakses URL data untuk preview.";
        } else {
            if ($fileType === 'csv') {
                $lines = explode("\n", trim($content));
                $totalRows = count($lines) - 1;
                $rows = array_slice($lines, 0, 21); 

                $csv = array_map('str_getcsv', $rows);
                if (isset($csv[0])) {
                    $columns = $csv[0];
                    // Pastikan setiap baris punya jumlah kolom yg sama dgn header
                    $preview = array_map(function($row) use ($columns) {
                        return array_combine($columns, array_pad($row, count($columns), null));
                    }, array_slice($csv, 1));
                }
            } elseif ($fileType === 'json') {
                $json = json_decode($content, true);
                if (is_array($json)) {
                    $arr = current(array_filter($json, 'is_array')) ?: $json;
                    $totalRows = count($arr);
                    $preview = array_slice($arr, 0, 20);
                    if (!empty($preview) && is_array($preview[0])) {
                         $columns = array_keys($preview[0]);
                    }
                } else { $error = "Format JSON tidak valid."; }
            }
        }
    } catch (Exception $e) { $error = "Gagal memuat data: " . $e->getMessage(); }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($dataset['title']) ?> - Portal Data</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap');
        body { font-family: 'Poppins', sans-serif; }
    </style>
</head>
<body class="bg-gray-50 text-gray-800">

    <header class="bg-white shadow-sm sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center py-4">
                <a href="index.php" class="flex items-center space-x-2">
                    <svg class="h-8 w-8 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12.75h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 010 3.75H5.625a1.875 1.875 0 010-3.75z" /></svg>
                    <span class="text-xl font-bold text-gray-800">Portal Data</span>
                </a>
                <nav class="hidden md:flex space-x-8">
                    <a href="index.php" class="text-sm font-medium text-gray-500 hover:text-red-600">Home</a>
                    <a href="#" class="text-sm font-medium text-gray-500 hover:text-red-600">Instansi</a>
                    <a href="#" class="text-sm font-medium text-gray-500 hover:text-red-600">Publikasi</a>
                </nav>
            </div>
        </div>
    </header>

    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="mb-6">
            <a href="index.php" class="inline-flex items-center text-sm font-semibold text-red-600 hover:text-red-800">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 mr-2"><path fill-rule="evenodd" d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" clip-rule="evenodd" /></svg>
                Kembali ke Beranda
            </a>
        </div>
        
        <div class="grid grid-cols-1 lg:grid-cols-10 gap-8">
            <div class="lg:col-span-7">
                <div class="bg-white p-6 rounded-lg shadow-sm border mb-8">
                    <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900 leading-tight"><?= htmlspecialchars($dataset['title']) ?></h1>
                    <p class="mt-4 text-gray-600 leading-relaxed"><?= nl2br(htmlspecialchars($dataset['notes'] ?? 'Tidak ada deskripsi untuk dataset ini.')) ?></p>
                </div>
                
                <div class="bg-white p-6 rounded-lg shadow-sm border mb-8">
                    <h2 class="text-2xl font-bold mb-4">📂 File Dataset</h2>
                    <div class="space-y-4">
                        <?php foreach ($dataset['resources'] as $r): 
                            $url = $r['download_url'] ?? $r['url'] ?? $r['accessURL'] ?? null; ?>
                            <div class="border-b last:border-0 pb-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-bold text-lg text-gray-800"><?= htmlspecialchars($r['name'] ?? 'Tanpa Nama File') ?></p>
                                        <div class="flex items-center text-sm text-gray-500 mt-1 space-x-4">
                                            <span>Format: <span class="font-semibold"><?= htmlspecialchars(strtoupper($r['format'] ?? '-')) ?></span></span>
                                            </div>
                                    </div>
                                    <?php if ($url): ?>
                                    <a href="counter.php?id=<?= urlencode($id) ?>&url=<?= urlencode($url) ?>" target="_blank"
                                       class="inline-block bg-red-600 text-white text-sm font-semibold px-5 py-2 rounded-md hover:bg-red-700 transition whitespace-nowrap">
                                        Download
                                    </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border">
                    <h2 class="text-2xl font-bold mb-4">🔍 Preview Dataset</h2>
                    <div class="overflow-x-auto">
                        <?php if ($error): ?>
                            <p class="text-red-600 bg-red-50 p-4 rounded-md"><?= htmlspecialchars($error) ?></p>
                        <?php elseif ($dataUrl && !empty($preview)): ?>
                            <p class="text-sm text-gray-500 mb-4">Menampilkan <strong><?= count($preview) ?></strong> baris pertama dari total <strong><?= number_format($totalRows) ?></strong> baris data.</p>
                            <table class="min-w-full text-sm border border-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <?php foreach ($columns as $col): ?>
                                        <th class="px-4 py-2 text-left font-semibold text-gray-700 border-b"><?= htmlspecialchars($col) ?></th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preview as $row): ?>
                                    <tr class="hover:bg-gray-50 border-b last:border-0">
                                        <?php foreach ($row as $cell): ?>
                                        <td class="px-4 py-2 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($cell) ?></td>
                                        <?php endforeach; ?>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-gray-500 bg-gray-50 p-4 rounded-md">⚠️ Tidak ada preview yang bisa ditampilkan untuk file ini (format harus CSV atau JSON yang dapat diakses publik).</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3 space-y-6">
                <div class="bg-white p-6 rounded-lg shadow-sm border">
                    <h3 class="text-lg font-bold mb-3">Statistik</h3>
                    <div class="space-y-2 text-gray-600">
                        <div class="flex items-center"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 mr-2 text-gray-400"><path d="M10 12.5a2.5 2.5 0 100-5 2.5 2.5 0 000 5z" /><path fill-rule="evenodd" d="M.664 10.59a1.651 1.651 0 010-1.18l.88-1.467a1.651 1.651 0 011.086-.88l1.467-.88a1.651 1.651 0 011.18 0l1.467.88a1.651 1.651 0 01.88 1.086l.88 1.467a1.651 1.651 0 010 1.18l-.88 1.467a1.651 1.651 0 01-1.086.88l-1.467.88a1.651 1.651 0 01-1.18 0l-1.467-.88a1.651 1.651 0 01-.88-1.086l-.88-1.467zM10 15a5 5 0 100-10 5 5 0 000 10z" clip-rule="evenodd" /></svg>
                            Dilihat: <span class="font-bold ml-auto"><?= number_format($stats[$id]['views']) ?> kali</span>
                        </div>
                        <div class="flex items-center"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5 mr-2 text-gray-400"><path d="M10.75 2.75a.75.75 0 00-1.5 0v8.614L6.295 8.235a.75.75 0 10-1.09 1.03l4.25 4.5a.75.75 0 001.09 0l4.25-4.5a.75.75 0 00-1.09-1.03l-2.955 3.129V2.75z" /><path d="M3.5 12.75a.75.75 0 00-1.5 0v2.5A2.75 2.75 0 004.75 18h10.5A2.75 2.75 0 0018 15.25v-2.5a.75.75 0 00-1.5 0v2.5c0 .69-.56 1.25-1.25 1.25H4.75c-.69 0-1.25-.56-1.25-1.25v-2.5z" /></svg>
                            Diunduh: <span class="font-bold ml-auto"><?= number_format($stats[$id]['downloads']) ?> kali</span>
                        </div>
                    </div>
                </div>
                <div class="bg-white p-6 rounded-lg shadow-sm border">
                    <h3 class="text-lg font-bold mb-3">Informasi Tambahan</h3>
                    <div class="space-y-3 text-sm text-gray-600">
                        <p><strong>Organisasi:</strong> <span class="text-gray-800"><?= htmlspecialchars($dataset['organization']['title'] ?? 'Tidak diketahui') ?></span></p>
                        <p><strong>Lisensi:</strong> <span class="text-gray-800"><?= htmlspecialchars($dataset['license_title'] ?? 'Tidak ada') ?></span></p>
                        <p><strong>Terakhir Update:</strong> <span class="text-gray-800"><?= htmlspecialchars(date('d M Y', strtotime($dataset['metadata_modified'] ?? 'now'))) ?></span></p>
                    </div>
                </div>
            </div>

        </div>
    </main>
    
    <footer class="bg-white border-t mt-12">
        </footer>

</body>
</html>