<?php


namespace Colombo\Cdn\Manipulators;


use Intervention\Image\Image;
use League\Glide\Manipulators\BaseManipulator;

/**
 * @property string $flip
 */
class Flip extends BaseManipulator
{
    /**
     * Perform flip image manipulation.
     * @param Image $image The source image.
     * @return Image The manipulated image.
     */
    public function run(Image $image)
    {
        if ($flip = $this->getFlip()) {
            if($flip == "x"){
                $flip = "h";
            }elseif($flip == "y"){
                $flip = "v";
            }
            if ($flip === 'both') {
                return $image->flip('h')->flip('v');
            }

            return $image->flip($flip);
        }

        return $image;
    }

    /**
     * Resolve flip.
     * @return string The resolved flip.
     */
    public function getFlip()
    {
        if (in_array($this->flip, ['x','h','y','v', 'both'], true)) {
            return $this->flip;
        }
    }
}
