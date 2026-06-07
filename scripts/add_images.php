#!/usr/bin/env php
<?php
$_SERVER['MAGE_PROFILER'] = 'skip';

if (PHP_SAPI !== 'cli') {
    echo "This script must be run from CLI.\n";
    exit(1);
}

$magentoDir = '/var/www/html';
if (!file_exists("$magentoDir/app/bootstrap.php")) {
    $magentoDir = '/data';
}
require "$magentoDir/app/bootstrap.php";

$bootstrap = \Magento\Framework\App\Bootstrap::create(BP, $_SERVER);
$objectManager = $bootstrap->getObjectManager();
$state = $objectManager->get(\Magento\Framework\App\State::class);
$state->setAreaCode('adminhtml');

$productRepository = $objectManager->get(\Magento\Catalog\Api\ProductRepositoryInterface::class);

function createPlaceholderImage(string $filename, string $label, string $hexColor): void {
    $size = 400;
    $img = imagecreatetruecolor($size, $size);

    $r = hexdec(substr($hexColor, 0, 2));
    $g = hexdec(substr($hexColor, 2, 2));
    $b = hexdec(substr($hexColor, 4, 2));

    $bg = imagecolorallocate($img, $r, $g, $b);
    imagefill($img, 0, 0, $bg);

    $lr = min(255, $r + 40);
    $lg = min(255, $g + 40);
    $lb = min(255, $b + 40);
    $circleColor = imagecolorallocate($img, $lr, $lg, $lb);
    imagefilledellipse($img, $size/2, $size/2 - 20, 180, 180, $circleColor);

    $white = imagecolorallocate($img, 255, 255, 255);
    $fontSize = 5;

    $lines = explode("\n", wordwrap($label, 12, "\n"));
    $y = $size/2 + 60;
    foreach ($lines as $line) {
        $textWidth = imagefontwidth($fontSize) * strlen($line);
        $x = ($size - $textWidth) / 2;
        imagestring($img, $fontSize, (int)$x, (int)$y, $line, $white);
        $y += imagefontheight($fontSize) + 4;
    }

    imagepng($img, $filename);
    imagedestroy($img);
}

// Create images inside Magento's media tmp dir
$mediaDir = $magentoDir . '/pub/media';
$tmpDir = $mediaDir . '/import';
@mkdir($tmpDir, 0777, true);

$products = [
    'smartphone-x-pro'       => ['label' => "Smartphone\nX Pro",       'color' => '2563eb'],
    'laptop-ultra-15'        => ['label' => "Laptop\nUltra 15",        'color' => '7c3aed'],
    'wireless-headphones-pro'=> ['label' => "Wireless\nHeadphones",    'color' => '0891b2'],
    'running-shoes-apex'     => ['label' => "Running\nShoes Apex",     'color' => 'dc2626'],
    'yoga-mat-premium'       => ['label' => "Yoga Mat\nPremium",      'color' => '16a34a'],
    'denim-jacket-classic'   => ['label' => "Denim\nJacket Classic",  'color' => '1e40af'],
    'cotton-tshirt-basic'    => ['label' => "Cotton\nT-Shirt Basic",  'color' => 'ca8a04'],
    'coffee-maker-deluxe'    => ['label' => "Coffee\nMaker Deluxe",   'color' => '92400e'],
    'desk-lamp-led'          => ['label' => "Desk\nLamp LED",         'color' => 'eab308'],
    'urban-backpack-30l'     => ['label' => "Urban\nBackpack 30L",   'color' => '4f46e5'],
];

echo "--- Generating placeholder images ---\n";
$imageFiles = [];
foreach ($products as $sku => $info) {
    $filename = "$tmpDir/$sku.png";
    createPlaceholderImage($filename, $info['label'], $info['color']);
    $imageFiles[$sku] = $filename;
    echo "  Created: $sku.png\n";
}

echo "\n--- Assigning images to products ---\n";
$added = 0;
$skipped = 0;
foreach ($imageFiles as $sku => $filePath) {
    try {
        $product = $productRepository->get($sku);
    } catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
        echo "  $sku: product not found, skipping.\n";
        $skipped++;
        continue;
    }

    $existingImages = $product->getMediaGalleryImages();
    if ($existingImages && $existingImages->count() > 0) {
        echo "  $sku: already has {$existingImages->count()} image(s), skipping.\n";
        $skipped++;
        continue;
    }

    try {
        $product->addImageToMediaGallery($filePath, ['image', 'small_image', 'thumbnail'], true, false);
        $productRepository->save($product);
        echo "  $sku: image added.\n";
        $added++;
    } catch (\Exception $e) {
        echo "  $sku: error - " . $e->getMessage() . "\n";
        $skipped++;
    }
}

// Cleanup generated images (Magenta has its own copy now)
echo "\nCleaning up temporary files...\n";
array_map('unlink', $imageFiles);
rmdir($tmpDir);

echo "\n--- Summary ---\n";
echo "  Images added: $added\n";
echo "  Skipped: $skipped\n";
