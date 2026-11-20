<?php

declare(strict_types=1);

$src = 'C:/Users/Dell/.cursor/projects/d-EDUSN-GLOBAL-TEAM-digizenship-frontend/assets/image-f31b8835-b34a-418f-bfdd-585a21ec3f7b.png';
$dst = dirname(__DIR__) . '/public/assets/vendhub-brand.png';

$srcIm = imagecreatefrompng($src);
if ($srcIm === false) {
    fwrite(STDERR, "Failed to load source image.\n");
    exit(1);
}

$w = imagesx($srcIm);
$h = imagesy($srcIm);
$out = imagecreatetruecolor($w, $h);
imagealphablending($out, false);
imagesavealpha($out, true);

$transparent = imagecolorallocatealpha($out, 0, 0, 0, 127);
imagefilledrectangle($out, 0, 0, $w, $h, $transparent);

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $rgb = imagecolorat($srcIm, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        $max = max($r, $g, $b);

        if ($max < 28) {
            imagesetpixel($out, $x, $y, $transparent);
            continue;
        }

        if ($max < 48) {
            $alpha = (int) min(127, (int) round((48 - $max) / 48 * 127));
            imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, $alpha));
            continue;
        }

        imagesetpixel($out, $x, $y, imagecolorallocatealpha($out, $r, $g, $b, 0));
    }
}

if (!imagepng($out, $dst, 6)) {
    fwrite(STDERR, "Failed to write output image.\n");
    exit(1);
}

imagedestroy($srcIm);
imagedestroy($out);
echo 'Wrote ' . $dst . ' (' . filesize($dst) . " bytes)\n";
