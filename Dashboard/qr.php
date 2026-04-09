<?php
$url = isset($_GET['url']) ? $_GET['url'] : 'http://localhost';
$encodedUrl = urlencode($url);

header('Location: https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . $encodedUrl);
exit;
?>