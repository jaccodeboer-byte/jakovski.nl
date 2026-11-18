<?php
/* Letterboxd for the television, channel 2.
   Fetches Jacco's public diary feed, keeps a copy for 3 hours, and returns the three newest films as JSON.
   The page asks this file for them; if it fails, the page simply shows the films written in index.html. */

$feed  = 'https://letterboxd.com/Jakkovski/rss/';
$cache = __DIR__ . '/letterboxd-cache.json';
$ttl   = 3 * 3600;

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=900');

if (is_file($cache) && time() - filemtime($cache) < $ttl) { readfile($cache); exit; }

$src = getenv('LB_TEST_FEED') ?: $feed;           // test hook; empty on a normal server
$xml = false;
if (function_exists('curl_init') && !getenv('LB_TEST_FEED')) {
  $c = curl_init($src);
  curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_TIMEOUT => 8,
                         CURLOPT_USERAGENT => 'jacco-site/1.0 (+letterboxd diary on a personal site)']);
  $xml = curl_exec($c);
  if (curl_getinfo($c, CURLINFO_HTTP_CODE) !== 200) $xml = false;
  curl_close($c);
} else {
  $xml = @file_get_contents($src);
}

$films = [];
if ($xml) {
  libxml_use_internal_errors(true);
  $rss = simplexml_load_string($xml);
  if ($rss && isset($rss->channel->item)) {
    foreach ($rss->channel->item as $item) {
      $lb = $item->children('https://letterboxd.com');
      $title = trim((string)$lb->filmTitle);
      if ($title === '') continue;                   // lists and reviews without a film
      $films[] = [
        'title'   => $title,
        'stars'   => isset($lb->memberRating) ? (float)$lb->memberRating : 0,
        'watched' => (string)$lb->watchedDate,
      ];
    }
    usort($films, fn($a, $b) => strcmp($b['watched'], $a['watched']));
    $films = array_slice($films, 0, 3);
  }
}

if ($films) {
  $out = json_encode($films, JSON_UNESCAPED_UNICODE);
  @file_put_contents($cache, $out);
  echo $out;
} elseif (is_file($cache)) {
  readfile($cache);                                   // Letterboxd unreachable: serve the last good copy
} else {
  http_response_code(503);
  echo '[]';
}
