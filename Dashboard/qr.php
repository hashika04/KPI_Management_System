<?php
/*
 * qr.php — QR Code Image Generator
 * Compatible with endroid/qr-code ^5.0 (PHP 8.1–8.3)
 *
 * Place this file at:
 *   KPI_Management_System/overview/qr.php
 *
 * Usage:  <img src="qr.php?id=4">
 * Output: PNG image of a QR code that opens
 *         staff_profile.php?id=4 when scanned
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;

/* ── Validate incoming ID ── */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    exit('Invalid staff ID');
}

/* ══════════════════════════════════════════
   BASE URL DETECTION
   Auto-detects LAN IP so QR works on phones
   on the same WiFi network as your XAMPP PC.
   If auto-detection fails, hardcode your IP:
     $host = '192.168.1.10';
══════════════════════════════════════════ */
$profileUrl = 'http://192.168.0.233/KPI_Management_System/staff_masterlist/staffprofile.php?id=' . $id;
/* ── Build & output QR code using v5 Builder API ── */
$result = Builder::create()
    ->writer(new PngWriter())
    ->data($profileUrl)
    ->encoding(new Encoding('UTF-8'))
    ->errorCorrectionLevel(ErrorCorrectionLevel::High)
    ->size(200)
    ->margin(10)
    ->roundBlockSizeMode(RoundBlockSizeMode::Margin)
    ->foregroundColor(new Color(102, 2, 31))   /* #66021F — brand burgundy */
    ->backgroundColor(new Color(255, 255, 255))
    ->build();

header('Content-Type: ' . $result->getMimeType());
header('Cache-Control: public, max-age=86400');
echo $result->getString();