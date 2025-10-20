<?php
function updateCache($silent = true)
{
    ini_set('max_execution_time', 900); 
    set_time_limit(900);

    $cacheDir  = __DIR__ . '/cache';
    $cacheFile = $cacheDir . '/datasets.json';
    $dataDir   = __DIR__ . '/data';
    $logFile   = $dataDir . '/log.txt';
    $refreshInterval = 60 * 60 * 24;
    $rowsPerRequest  = 1000;

    $apiSources = [
        'US' => ['name' => 'U.S. Government', 'url' => 'https://catalog.data.gov/api/3/action/package_search'],
        'AU' => ['name' => 'Data Australia', 'url' => 'https://data.gov.au/api/3/action/package_search'],
        'UK' => ['name' => 'Data UK', 'url' => 'https://data.gov.uk/api/3/action/package_search'],
        'CA' => ['name' => 'Open Data Canada', 'url' => 'https://open.canada.ca/data/api/3/action/package_search']
    ];

    if (!file_exists($cacheDir)) mkdir($cacheDir, 0777, true);
    if (!file_exists($dataDir)) mkdir($dataDir, 0777, true);

    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < $refreshInterval) {
        if (!$silent) echo "✅ Cache masih baru, tidak perlu update.\n";
        return;
    }

    if (!$silent) echo "🚀 Memulai update cache dari " . count($apiSources) . " sumber...\n\n";

    $allDatasets = [];

    foreach ($apiSources as $sourceKey => $source) {
        if (!$silent) echo "------------------------------------\n";
        if (!$silent) echo "🔄 Memproses: {$source['name']}\n";

        $context = stream_context_create([
            'http' => ['timeout' => 20, 'ignore_errors' => true, 'header' => "User-Agent: Multi-API-Updater/1.0\r\n"]
        ]);

        $totalFetched = 0;
        $start = 0;

        do {
            $apiUrl = "{$source['url']}?rows=$rowsPerRequest&start=$start";
            $startTime = microtime(true);

            $response = @file_get_contents($apiUrl, false, $context);
            if ($response === false) break;

            $data = json_decode($response, true);
            $results = $data['result']['results'] ?? [];

            if (empty($results)) break;

            foreach ($results as $dataset) {
                $dataset['id'] = $sourceKey . '-' . $dataset['id'];
                $dataset['source_name'] = $source['name'];
                $allDatasets[] = $dataset;
            }

            $count = count($results);
            $totalFetched += $count;
            $start += $rowsPerRequest;

            if (!$silent) echo "   ↳ Batch: $count dataset, total $totalFetched sejauh ini\n";

            if ((microtime(true) - $startTime) > 25) {
                if (!$silent) echo "   ⚠️ Timeout pada {$source['name']}, lanjut ke berikutnya.\n";
                break;
            }

        } while ($count === $rowsPerRequest);

        if (!$silent) echo "   ✅ Selesai: $totalFetched dataset dari {$source['name']}.\n";
    }

    if (empty($allDatasets)) {
        if (!$silent) echo "❌ Gagal ambil data dari semua sumber.\n";
        return;
    }

    file_put_contents($cacheFile, json_encode($allDatasets, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    if (!$silent) echo "\n🎉 Cache disimpan: " . count($allDatasets) . " dataset total.\n";
}

if (basename(__FILE__) === basename($_SERVER["SCRIPT_FILENAME"])) {
    echo "<!DOCTYPE html><html lang='id'><head><meta charset='UTF-8'>
    <title>Update Cache</title>
    <style>body{font-family:Arial;padding:20px;background:#f5f6fa;color:#333}</style></head><body><pre>";
    updateCache(false);
    echo "</pre></body></html>";
}
