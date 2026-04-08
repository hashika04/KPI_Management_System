<?php
$url = isset($_GET['url']) ? $_GET['url'] : 'http://localhost';
$encodedUrl = urlencode($url);
$googleQR = "https://chart.googleapis.com/chart?chs=200x200&cht=qr&chl={$encodedUrl}&choe=UTF-8";

header('Location: ' . $googleQR);
exit;
?>