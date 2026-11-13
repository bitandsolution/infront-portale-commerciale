<?php

namespace App\Support\Demo;

use GdImage;

/**
 * Material of the demo on the VM (D-438, D-439; DM-08), read from DEMO_ASSETS_PATH, never from the
 * repository:
 * - campi/<NAME>.jpg: photos of the fields of the old system (INFRONT_FILA1, UDINESE_JUMBO_DOMINATION…);
 * - loghi/<CLUB CODE>.png: real logos of the clubs of the demo;
 * - slide/<PREFIX>_<KEY>.png (or .jpg): slides of 1920×1080 that replace the drawn ones, PREFIX being
 *   INFRONT or the code of a club (e.g. INFRONT_COPERTINA, LAZIO_RECAP).
 * Every lookup ignores the case of the name and of the extension. Without the folder nothing is
 * found and the command draws everything (DemoArtwork).
 */
final class DemoAssets
{
    /** @var list<string> */
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg'];

    /** @var list<string> files of the slide folder skipped because not 1920×1080 */
    private array $rejected = [];

    public function __construct(private readonly ?string $root) {}

    public static function fromConfig(): self
    {
        $root = config('documents.demo_assets_path');

        return new self(is_string($root) && $root !== '' ? rtrim($root, '/') : null);
    }

    public function root(): ?string
    {
        return $this->root;
    }

    public function exists(): bool
    {
        return $this->root !== null && is_dir($this->root);
    }

    /** Path of a photo of the field (campi/<NAME>.*), if present. */
    public function fieldPhoto(string $name): ?string
    {
        return $this->find('campi', $name);
    }

    /** Contents of the PNG logo of a club (loghi/<CODE>.png), if present. */
    public function clubLogo(string $code): ?string
    {
        $path = $this->find('loghi', $code, ['png']);

        return $path !== null ? (string) file_get_contents($path) : null;
    }

    /**
     * A slide that replaces the drawn one (slide/<PREFIX>_<KEY>.*), as PNG contents of 1920×1080 (a
     * JPEG extracted from the example PDFs is converted: the library takes only PNG, M08 §7). A file
     * of another size is skipped and reported.
     */
    public function slide(string $prefix, string $key): ?string
    {
        $path = $this->find('slide', $prefix.'_'.$key);

        if ($path === null) {
            return null;
        }

        $contents = (string) file_get_contents($path);
        $size = @getimagesizefromstring($contents);

        if (! is_array($size) || $size[0] !== 1920 || $size[1] !== 1080) {
            $this->rejected[] = basename($path);

            return null;
        }

        if ($size[2] === IMAGETYPE_PNG) {
            return $contents;
        }

        $image = @imagecreatefromstring($contents);

        if (! $image instanceof GdImage) {
            $this->rejected[] = basename($path);

            return null;
        }

        ob_start();
        imagepng($image, null, 6);

        return (string) ob_get_clean();
    }

    /**
     * Files of the slide folder skipped because they are not images of 1920×1080.
     *
     * @return list<string>
     */
    public function rejected(): array
    {
        return array_values(array_unique($this->rejected));
    }

    /**
     * @param  list<string>  $extensions
     */
    private function find(string $folder, string $name, array $extensions = self::IMAGE_EXTENSIONS): ?string
    {
        if ($this->root === null) {
            return null;
        }

        $directory = $this->root.'/'.$folder;

        if (! is_dir($directory)) {
            return null;
        }

        foreach (scandir($directory) ?: [] as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (in_array($extension, $extensions, true)
                && strcasecmp(pathinfo($file, PATHINFO_FILENAME), $name) === 0
                && is_file($directory.'/'.$file)) {
                return $directory.'/'.$file;
            }
        }

        return null;
    }
}
