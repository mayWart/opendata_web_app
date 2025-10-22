<?php
$id = $_GET['id'] ?? null;
if (!$id)
    die("Dataset tidak ditemukan.");

$cacheFile = __DIR__ . '/cache/datasets.json';
if (!file_exists($cacheFile))
    die("Cache belum dibuat, jalankan update_cache.php dulu.");
$datasets = json_decode(file_get_contents($cacheFile), true);

$dataset = null;
foreach ($datasets as $d) {
    if ($d['id'] === $id) {
        $dataset = $d;
        break;
    }
}
if (!$dataset)
    die("Data tidak ditemukan.");

$statsFile = __DIR__ . '/data/stats.json';
$stats = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : [];
if (!isset($stats[$id]))
    $stats[$id] = ["views" => 0, "downloads" => 0];
$stats[$id]['views']++;
file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));

$dataUrl = null;
$fileType = null;
if (!empty($dataset['resources'])) {
    foreach ($dataset['resources'] as $r) {
        $url = $r['download_url'] ?? $r['url'] ?? $r['accessURL'] ?? null;

        // Abaikan resource kosong
        if (empty($url))
            continue;

        // Perbaikan: cocokkan .csv atau .json bahkan jika ada query di akhir
        if (preg_match('/\.(csv|json)(\?.*)?$/i', $url, $match)) {
            $fileType = strtolower($match[1]); // Ambil ekstensi dari hasil regex
            $dataUrl = $url;
            break;
        }
    }
}

$preview = [];
$columns = [];
$totalRows = 0;
$error = null;

if ($dataUrl) {
    try {
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: Mozilla/5.0\r\n",
                'timeout' => 10
            ]
        ]);

        $fileType = strtolower(pathinfo(parse_url($dataUrl, PHP_URL_PATH), PATHINFO_EXTENSION));

        // ==== CSV ====
        if ($fileType === 'csv') {
            $handle = @fopen($dataUrl, "r", false, $context);
            if (!$handle) {
                $error = "Tidak dapat membuka file CSV untuk preview.";
            } else {
                $rows = [];
                $maxRows = 21; // header + 20 data
                $count = 0;
                while (($line = fgetcsv($handle)) !== false && $count < $maxRows) {
                    $rows[] = $line;
                    $count++;
                }
                fclose($handle);

                if (!empty($rows)) {
                    $columns = $rows[0];
                    $totalRows = count($rows) - 1;
                    $preview = array_map(function ($row) use ($columns) {
                        return array_combine($columns, array_pad($row, count($columns), null));
                    }, array_slice($rows, 1));
                } else {
                    $error = "File CSV kosong atau tidak terbaca.";
                }
            }
        }

        // ==== JSON ====
        elseif ($fileType === 'json') {
            $content = @file_get_contents($dataUrl, false, $context);
            if ($content === false) {
                $error = "Tidak dapat mengakses file JSON untuk preview.";
            } else {
                $json = json_decode($content, true);
                if (is_array($json)) {
                    if (isset($json['data']) && is_array($json['data'])) {
                        $arr = $json['data'];
                    } else {
                        $arr = current(array_filter($json, 'is_array')) ?: $json;
                    }
                    $totalRows = count($arr);
                    $preview = array_slice($arr, 0, 20);
                    if (!empty($preview) && is_array($preview[0])) {
                        $columns = array_keys($preview[0]);
                    }
                } else {
                    $error = "Format JSON tidak valid.";
                }
            }
        } else {
            $error = "Format file tidak didukung (hanya CSV/JSON).";
        }
    } catch (Exception $e) {
        $error = "Gagal memuat data: " . $e->getMessage();
    }
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

        body {
            font-family: 'Poppins', sans-serif;
            opacity: 0;
            animation: fadeInPage 0.8s ease-in forwards;
        }

        @keyframes fadeInPage {
            from {
                opacity: 0;
                transform: translateY(10px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        [data-animate] {
            opacity: 0;
            transform: translateY(25px);
            transition: all 0.8s ease-out;
        }

        [data-animate].visible {
            opacity: 1;
            transform: translateY(0);
        }

        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .hover-lift:hover {
            transform: translateY(-6px);
            box-shadow: 0 8px 18px rgba(0, 0, 0, 0.1);
        }

        a,
        button {
            transition: all 0.3s ease;
        }

        a:hover,
        button:hover {
            transform: scale(1.03);
        }
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

    <main class="max-w-7xl mx-auto py-12 px-4 sm:px-6 lg:px-8">
        <div class="mb-6" data-animate>
            <a href="index.php" class="inline-flex items-center text-sm font-semibold text-red-600 hover:text-red-800">
                <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" class="w-5 h-5 mr-2">
                    <path
                        d="M17 10a.75.75 0 01-.75.75H5.612l4.158 3.96a.75.75 0 11-1.04 1.08l-5.5-5.25a.75.75 0 010-1.08l5.5-5.25a.75.75 0 111.04 1.08L5.612 9.25H16.25A.75.75 0 0117 10z" />
                </svg>
                Kembali ke Beranda
            </a>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-10 gap-8">
            <div class="lg:col-span-7 space-y-8">
                <div class="bg-white p-6 rounded-lg shadow-sm border hover-lift" data-animate>
                    <h1 class="text-3xl md:text-4xl font-extrabold text-gray-900">
                        <?= htmlspecialchars($dataset['title']) ?>
                    </h1>
                    <p class="mt-4 text-gray-600">
                        <?= nl2br(htmlspecialchars($dataset['notes'] ?? 'Tidak ada deskripsi.')) ?>
                    </p>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border hover-lift" data-animate>
                    <h2 class="text-2xl font-bold mb-4">📂 File Dataset</h2>
                    <div class="space-y-4">
                        <?php foreach ($dataset['resources'] as $r):
                            $url = $r['download_url'] ?? $r['url'] ?? $r['accessURL'] ?? null; ?>
                            <div class="border-b last:border-0 pb-4">
                                <div class="flex justify-between items-start">
                                    <div>
                                        <p class="font-bold text-lg text-gray-800">
                                            <?= htmlspecialchars($r['name'] ?? 'Tanpa Nama File') ?>
                                        </p>
                                        <p class="text-sm text-gray-500 mt-1">Format:
                                            <strong><?= htmlspecialchars(strtoupper($r['format'] ?? '-')) ?></strong>
                                        </p>
                                    </div>
                                    <?php if ($url): ?>
                                        <a href="counter.php?id=<?= urlencode($id) ?>&url=<?= urlencode($url) ?>"
                                            target="_blank"
                                            class="inline-block bg-red-600 text-white text-sm font-semibold px-5 py-2 rounded-md hover:bg-red-700 transition">
                                            Download
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border hover-lift" data-animate>
                    <h2 class="text-2xl font-bold mb-4">🔍 Preview Dataset</h2>
                    <div class="overflow-x-auto">
                        <?php if ($error): ?>
                            <p class="text-red-600 bg-red-50 p-4 rounded-md"><?= htmlspecialchars($error) ?></p>
                        <?php elseif ($dataUrl && !empty($preview)): ?>
                            <p class="text-sm text-gray-500 mb-4">Menampilkan <?= count($preview) ?> baris pertama dari
                                total <?= number_format($totalRows) ?> baris.</p>
                            <table class="min-w-full text-sm border border-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <?php foreach ($columns as $col): ?>
                                            <th class="px-4 py-2 text-left font-semibold text-gray-700 border-b">
                                                <?= htmlspecialchars($col) ?>
                                            </th>
                                        <?php endforeach; ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($preview as $row): ?>
                                        <tr class="hover:bg-gray-50 border-b last:border-0">
                                            <?php foreach ($row as $cell): ?>
                                                <td class="px-4 py-2 text-gray-600 whitespace-nowrap"><?= htmlspecialchars($cell) ?>
                                                </td>
                                            <?php endforeach; ?>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <p class="text-gray-500 bg-gray-50 p-4 rounded-md">⚠️ Tidak ada preview yang bisa ditampilkan.
                            </p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="lg:col-span-3 space-y-6">
                <div class="bg-white p-6 rounded-lg shadow-sm border hover-lift" data-animate>
                    <h3 class="text-lg font-bold mb-3">Statistik</h3>
                    <p class="text-gray-600">Dilihat: <strong><?= number_format($stats[$id]['views']) ?></strong> kali
                    </p>
                    <p class="text-gray-600">Diunduh: <strong><?= number_format($stats[$id]['downloads']) ?></strong>
                        kali</p>
                </div>

                <div class="bg-white p-6 rounded-lg shadow-sm border hover-lift" data-animate>
                    <h3 class="text-lg font-bold mb-3">Informasi Tambahan</h3>
                    <p><strong>Organisasi:</strong>
                        <?= htmlspecialchars($dataset['organization']['title'] ?? 'Tidak diketahui') ?></p>
                    <p><strong>Lisensi:</strong> <?= htmlspecialchars($dataset['license_title'] ?? 'Tidak ada') ?></p>
                    <p><strong>Terakhir Update:</strong>
                        <?= htmlspecialchars(date('d M Y', strtotime($dataset['metadata_modified'] ?? 'now'))) ?></p>
                </div>
            </div>
        </div>
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
            const elements = document.querySelectorAll("[data-animate]");
            const observer = new IntersectionObserver(entries => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("visible");
                    } else {
                        entry.target.classList.remove("visible");
                    }
                });
            }, { threshold: 0.15 });
            elements.forEach(el => observer.observe(el));
        });
    </script>

</body>

</html>