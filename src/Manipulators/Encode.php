<?php


namespace Colombo\Cdn\Manipulators;

use Intervention\Image\Image;
use League\Glide\Manipulators\BaseManipulator;

/**
 * @property string $fm
 * @property string $q
 */
class Encode extends BaseManipulator
{
    /**
     * Perform output image manipulation.
     * @param Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        $format = $this->getFormat($image);
        $quality = $this->getQuality();

        if (in_array($format, ['jpg', 'pjpg'], true)) {
            $image = $image->getDriver()
                ->newImage($image->width(), $image->height(), '#fff')
                ->insert($image, 'top-left', 0, 0);
        }

        if ($format === 'pjpg') {
            $image->interlace();
            $format = 'jpg';
        }

        return $image->encode($format, $quality);
    }

    /**
     * Resolve format.
     * @param Image $image The source image.
     * @return string The resolved format.
     */
    public function getFormat(Image $image)
    {
        $allowed = [
            'gif' => 'image/gif',
            'jpg' => 'image/jpeg',
            'pjpg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
        ];

        $format = $this->format;
        if(strpos($format, ",")){
            $format = explode(",", $format);
            $format = $format[0];
        }

//        echo "Format : " . $this->format;

        if (array_key_exists($format, $allowed)) {
            return $format;
        }

        if ($format = array_search($image->mime(), $allowed, true)) {
            return $format;
        }

        return 'jpg';
    }

    /**
     * Resolve quality.
     * @return string The resolved quality.
     */
    public function getQuality()
    {
        $default = 90;

        if (!is_numeric($this->q)) {
            return $default;
        }

        if ($this->q < 0 or $this->q > 100) {
            return $default;
        }

        return (int)$this->q;
    }
}
