<?php
declare(strict_types=1);

$heroImage = trim((string) ($heroImage ?? ''));
$heroTitle = trim((string) ($heroTitle ?? ''));

if ($heroImage === '') {
    return;
}
?>
<div style="margin: 18px auto 18px; width: min(100%, 860px); padding: 0; overflow: hidden; border-radius: 12px; aspect-ratio: 16 / 9; max-height: 500px; position: relative; background: #0b1f3a; display: flex; align-items: center; justify-content: center; box-shadow: var(--card-shadow); border: 1px solid #2d3e50;">
    <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0; background-image: url('<?= e(url($heroImage)) ?>'); background-size: cover; background-position: center; filter: blur(30px) brightness(0.5); transform: scale(1.1);"></div>
    <img src="<?= e(url($heroImage)) ?>" alt="<?= e($heroTitle) ?>" style="position: relative; z-index: 1; max-width: 100%; height: 100%; object-fit: contain; display: block;">
</div>
