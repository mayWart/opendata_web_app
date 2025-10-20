<?php
$id = $_GET['id'] ?? null;
$url = $_GET['url'] ?? null;
if (!$id || !$url) die("Invalid request.");

$statsFile = __DIR__ . '/data/stats.json';
$stats = file_exists($statsFile) ? json_decode(file_get_contents($statsFile), true) : [];
if (!isset($stats[$id])) $stats[$id] = ["views" => 0, "downloads" => 0];
$stats[$id]['downloads']++;
file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));

header("Location: $url");
exit;
