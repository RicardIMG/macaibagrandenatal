<?php
// Gera as imagens usadas por tests/e2e_test.py. Uso: php tests/make_test_images.php /pasta/destino
$dir = $argv[1] ?? __DIR__ . '/output/imgs';
@mkdir($dir, 0755, true);
foreach ([['hero.jpg', 3200, 2000], ['topo.png', 4000, 3000], ['g1.jpg', 1800, 1200], ['g2.jpg', 1600, 1600], ['g3.webp', 2000, 1300]] as [$n, $w, $h]) {
    $im = imagecreatetruecolor($w, $h);
    imagefill($im, 0, 0, imagecolorallocate($im, 80, 100, 80));
    for ($i = 0; $i < 30; $i++) {
        imageellipse($im, rand(0, $w), rand(0, $h), rand(100, 900), rand(100, 700), imagecolorallocate($im, rand(0, 255), rand(0, 255), rand(0, 255)));
    }
    $ext = pathinfo($n, PATHINFO_EXTENSION);
    $ext === 'jpg' ? imagejpeg($im, "$dir/$n", 85) : ($ext === 'png' ? imagepng($im, "$dir/$n") : imagewebp($im, "$dir/$n"));
}
// Arquivos maliciosos que DEVEM ser recusados
file_put_contents("$dir/evil.php", '<?php system($_GET["c"]); ?>');
file_put_contents("$dir/evil.jpg", '<?php system($_GET["c"]); ?>');
file_put_contents("$dir/fake.jpg", "\xff\xd8\xff\xe0<?php phpinfo(); ?>");
echo "Imagens geradas em $dir\n";
