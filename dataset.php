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

// ====== CARI RESOURCE YANG BISA DIBACA ======
$dataUrl = null;
$fileType = null;

if (!empty($dataset['resources'])) {
  foreach ($dataset['resources'] as $r) {
    $url = $r['download_url'] ?? $r['url'] ?? $r['accessURL'] ?? null;
    if (!empty($url) && preg_match('/\.(csv|json)$/i', $url)) {
      $fileType = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
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
      $error = "Tidak dapat mengakses URL data.";
    } else {
      if ($fileType === 'csv') {
        $lines = explode("\n", trim($content));
        $totalRows = count($lines);
        $rows = array_slice($lines, 0, 21); // ambil max 20 baris + header

        $csv = array_map('str_getcsv', $rows);
        $columns = $csv[0] ?? [];
        $preview = array_slice($csv, 1); // tanpa header
      } elseif ($fileType === 'json') {
        $json = json_decode($content, true);
        if (is_array($json)) {
          $arr = [];
          // Ambil data array utama
          if (isset($json[0])) {
            $arr = $json;
          } elseif (isset($json['data']) && is_array($json['data'])) {
            $arr = $json['data'];
          }
          $totalRows = count($arr);
          $preview = array_slice($arr, 0, 20);
          $columns = array_keys($preview[0] ?? []);
        } else {
          $error = "Format JSON tidak dikenali.";
        }
      }
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
  <title><?= htmlspecialchars($dataset['title']) ?></title>
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
<div class="max-w-5xl mx-auto p-6">
  <a href="index.php" class="text-blue-600 hover:underline">← Kembali</a>
  <h1 class="text-3xl font-bold mt-2 mb-4 text-indigo-700"><?= htmlspecialchars($dataset['title']) ?></h1>
  <p class="text-gray-700 mb-6"><?= nl2br(htmlspecialchars($dataset['notes'] ?? 'Tidak ada deskripsi.')) ?></p>

  <div class="bg-white p-4 rounded-lg shadow mb-6">
    <p><strong>👀 Views:</strong> <?= $stats[$id]['views'] ?></p>
    <p><strong>⬇️ Downloads:</strong> <?= $stats[$id]['downloads'] ?></p>
  </div>

  <h2 class="text-xl font-semibold mb-3">📂 File Dataset</h2>
  <div class="space-y-3 mb-8">
    <?php foreach ($dataset['resources'] as $r): 
      $url = $r['download_url'] ?? $r['url'] ?? $r['accessURL'] ?? null; ?>
      <div class="bg-white p-4 rounded-lg shadow">
        <p class="font-semibold text-indigo-800"><?= htmlspecialchars($r['name'] ?? 'Tanpa nama') ?></p>
        <p>Format: <?= htmlspecialchars($r['format'] ?? '-') ?></p>
        <?php if ($url): ?>
          <a href="counter.php?id=<?= urlencode($id) ?>&url=<?= urlencode($url) ?>" target="_blank"
             class="inline-block mt-2 bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
            Download
          </a>
        <?php else: ?>
          <p class="text-sm text-gray-500 mt-2">URL tidak tersedia.</p>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- ===== PREVIEW DATASET ===== -->
  <h2 class="text-xl font-semibold mb-3">🔍 Preview Dataset</h2>
  <div class="bg-white p-4 rounded-lg shadow overflow-x-auto">
    <?php if ($error): ?>
      <p class="text-red-500"><?= htmlspecialchars($error) ?></p>
    <?php elseif ($dataUrl && !empty($preview)): ?>
      <p class="text-sm text-gray-500 mb-3">
        Menampilkan maksimal <strong>20</strong> baris pertama dari total <strong><?= $totalRows ?></strong> data.<br>
        Jumlah kolom: <strong><?= count($columns) ?></strong>
      </p>
      <table class="min-w-full text-sm text-left border border-gray-200">
        <thead class="bg-gray-50 text-gray-700">
          <tr>
            <?php foreach ($columns as $col): ?>
              <th class="px-3 py-2 border-b"><?= htmlspecialchars($col) ?></th>
            <?php endforeach; ?>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($preview as $row): ?>
            <tr class="hover:bg-gray-50">
              <?php foreach ($columns as $col): ?>
                <td class="px-3 py-2 border-b"><?= htmlspecialchars($row[$col] ?? '') ?></td>
              <?php endforeach; ?>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php else: ?>
      <p class="text-gray-500">⚠️ Tidak dapat menampilkan preview (mungkin file bukan CSV/JSON atau terlalu besar).</p>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
