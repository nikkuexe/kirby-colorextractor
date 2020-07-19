<?php

namespace SylvainJule;

use League\ColorExtractor\Color;
use League\ColorExtractor\ColorExtractor as Extractor;
use League\ColorExtractor\Palette;

use Kirby\Cms\Files;
use Kirby\Cms\File;

class ColorExtractor {

	public static function extractColor($image, $size, $fallbackColor) {
		if($image->isResizable()) {
            $thumb     = $image->width() > $image->height() ? $image->resize(null, $size) : $image->resize($size);
            $thumb     = $thumb->save();
			$root      = $thumb->root();
			$palette   = Palette::fromFilename($root, Color::fromHexToInt($fallbackColor));
			$extractor = new Extractor($palette);
			$colors    = $extractor->extract(1);
			$hex       = Color::fromIntToHex($colors[0]);
			$hsl = self::hexToHsl($hex);
			$hex = '#' . strtoupper(self::hslToHex($hsl));

			// Update image metadata
			$image->update(array(
				'color' => $hex
			));
		}
	}

    private static $cache = null;

    private static function cache(): \Kirby\Cache\Cache {
        if(!static::$cache) {
            static::$cache = kirby()->cache('sylvainjule.colorextractor');
        }
        return static::$cache;
    }

    public static function getFilesIndex($force = false) {
        $index = $force ? null : static::cache()->get('files.index');
        if(!$index) {
        	$published = site()->index()->images();
        	$drafts    = site()->drafts()->images();
        	$index     = new Files(array($published, $drafts));
            $files     = array();

            foreach($index as $f) {
                $files[] = array(
                    'filename' => $f->filename(),
                    'parent'   => $f->parent()->uri()
                );
            }
            static::cache()->set('files.index', $files, 15);
        }
        else {
            $index = array_map(function($a) { return kirby()->page($a['parent'])->file($a['filename']); }, $index);
            $index = new Files($index, kirby()->site());
        }
        return $index;
    }

    private static function hexToHsl($hex)
    {
        // Normalize hex string
        $hex = str_replace('#', '', $hex);
        $hex = array($hex[0] . $hex[1], $hex[2] . $hex[3], $hex[4] . $hex[5]);
        $rgb = array_map(function ($part) {
            return hexdec($part) / 255;
        }, $hex);

        $max = max($rgb);
        $min = min($rgb);

        $l = ($max + $min) / 2;

        if ($max == $min) {
            $h = $s = 0;
        } else {
            $diff = $max - $min;
            $s = $l > 0.5 ? $diff / (2 - $max - $min) : $diff / ($max + $min);

            switch ($max) {
                case $rgb[0]:
                    $h = ($rgb[1] - $rgb[2]) / $diff + ($rgb[1] < $rgb[2] ? 6 : 0);
                    break;
                case $rgb[1]:
                    $h = ($rgb[2] - $rgb[0]) / $diff + 2;
                    break;
                case $rgb[2]:
                    $h = ($rgb[0] - $rgb[1]) / $diff + 4;
                    break;
            }

            $h /= 6;
        }

        // Normalize Lightness
        if ($l > 0.8) {
            $l = 0.8;
        } elseif ($l < 0.2) {
            $l = 0.2;
        }

        return array($h, $s, $l);
    }

    private static function hslToHex($hsl)
    {
        list($h, $s, $l) = $hsl;

        if ($s == 0) {
            $r = $g = $b = 1;
        } else {
            $q = $l < 0.5 ? $l * (1 + $s) : $l + $s - $l * $s;
            $p = 2 * $l - $q;

            $r = self::hue2rgb($p, $q, $h + 1 / 3);
            $g = self::hue2rgb($p, $q, $h);
            $b = self::hue2rgb($p, $q, $h - 1 / 3);
        }

        return self::rgb2hex($r) . self::rgb2hex($g) . self::rgb2hex($b);
    }

    private static function hue2rgb($p, $q, $t)
    {
        if ($t < 0) {
            $t += 1;
        }

        if ($t > 1) {
            $t -= 1;
        }

        if ($t < 1 / 6) {
            return $p + ($q - $p) * 6 * $t;
        }

        if ($t < 1 / 2) {
            return $q;
        }

        if ($t < 2 / 3) {
            return $p + ($q - $p) * (2 / 3 - $t) * 6;
        }

        return $p;
    }

    private static function rgb2hex($rgb)
    {
        return str_pad(dechex($rgb * 255), 2, '0', STR_PAD_LEFT);
    }

}
