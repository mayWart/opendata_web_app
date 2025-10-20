<?php
$url = "https://demo.ckan.org/api/3/action/package_search?rows=5";
$context = stream_context_create(['http' => ['timeout' => 20, 'ignore_errors' => true]]);
$response = @file_get_contents($url, false, $context);

if ($response === false) {
    echo "❌ Tidak bisa mengakses API (network error)";
} else {
    $data = json_decode($response, true);
    if ($data && $data['success']) {
        echo "✅ Berhasil ambil " . count($data['result']['results']) . " dataset.<br>";
        echo "<pre>";
        print_r($data['result']['results'][0]);
        echo "</pre>";
    } else {
        echo "⚠️ API tidak mengembalikan data yang valid.";
    }
}
?>

