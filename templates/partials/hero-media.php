<?php
declare(strict_types=1);

$heroImage = content_asset_path(trim((string) ($heroImage ?? '')));
$heroTitle = trim((string) ($heroTitle ?? ''));

if ($heroImage === '') {
    return;
}
?>
<div class="hero-media-wrap">
    <div class="hero-media-blur" style="background-image: url('<?= e($heroImage) ?>');"></div>
    <img class="hero-media-img" src="<?= e($heroImage) ?>" alt="<?= e($heroTitle) ?>">
</div>
