<?php
namespace App\Helpers;

use Imagick;
use ImagickPixel;

class Image
{
    public function __construct()
    {
        //
    }

    /**
     * Resize, strip, optimize, and save multiple image sizes.
     *
     * @param string $file        The path to the image to be processed.
     * @param integer $width      The width of the full-size image, in pixels.
     * @param integer $height     The width of the full-size image, in pixels.
     * @param mixed $minimum      The smallest allowable short edge, in pixels.
     * @param string $save        The path representing a directory to save the processed images.
     * @param boolean $thumbnail  If true, 375px and 750px thumbnails will be created.
     * @param boolean $mobile     If true, a 1440px variant for mobile devices will be created.
     * @param boolean $watermark  If true, the mobile and full variants will be watermarked.
     *
     * @return array              A key-value pair of the full-sized image's width, height, and file extension.
     */
    public static function resize($file, $width, $height, $minimum, $save, $thumbnail = false, $mobile = false, $watermark = false)
    {
        // Check for Valid Image
        try
        {
            list($w, $h) = getimagesize($file);
        }

        catch (\Exception $e)
        {
            return [
                'error'     =>  'read_error',
                'detail'    =>  $e->getMessage()
            ];
        }

        // Invalid File Formats
        if (empty($w) || empty($h))
        {
            return [
                'error'     =>  'file_format_invalid'
            ];
        }

        // Small Images
        if ($minimum &&
            ((gettype($minimum) == 'integer' && ($w < $minimum || $h < $minimum)) ||
             (gettype($minimum) == 'array') && ($w < $minimum[0] || $h < $minimum[1])))
        {
            return [
                'error'     =>  'resolution_too_low'
            ];
        }

        $magickSource = new Imagick();
        $magickSource->readImageBlob(file_get_contents($file));
        $magickSource = self::orientate($magickSource);

        $w = $magickSource->getImageWidth();
        $h = $magickSource->getImageHeight();

        $extension = ((exif_imagetype($file) == IMAGETYPE_PNG && $magickSource->getImageAlphaChannel()) ? '.png' : '.jpg');

        $magickSource->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
        $magickSource->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        $magickSource->writeImage($save . '_original' . $extension);

        // Determine Average Color of Watermark Area for Watermark Type
        if ($watermark)
        {
            $magickSourceDims = $magickSource->getImageGeometry();
            $magickWaterSize = max($magickSourceDims['height'], $magickSourceDims['width']) * 0.0875;
            $magickWaterArea = clone $magickSource;
            $magickWaterArea->cropImage(
                $magickWaterSize,                                                       // Width
                $magickWaterSize,                                                       // Height
                $magickSourceDims['width'] - $magickWaterSize - ($magickWaterSize / 5), // Top-Left X-Coordinate
                $magickSourceDims['height'] - $magickWaterSize - ($magickWaterSize / 5) // Top-Left Y-Coordinate
            );

            $magickWaterArea->resizeImage(1, 1, Imagick::FILTER_LANCZOS, 1, true); // FILTER_BOX is faster than FILTER_LANCZOS
            $magickWaterPixel = $magickWaterArea->getImagePixelColor(0, 0)->getColor();
            $magickWaterPixel = min($magickWaterPixel['r'], $magickWaterPixel['g'], $magickWaterPixel['b']);
            $magickWaterArea->destroy();

            $magickWater = new Imagick();
            $magickWater->setBackgroundColor(new ImagickPixel('transparent'));
            $magickWater->readImageBlob(file_get_contents('../watermark-' . ($magickWaterPixel > 225 ? 'solid' : 'shadow') . '.png'));
        }

        // Create Full-Size Image
        $magickFull = clone $magickSource;
        $magickFull->resizeImage(min($w, $width), min($h, $height), Imagick::FILTER_LANCZOS, 1, true);

        if ($watermark)
        {
            $magickWaterFull = clone $magickWater;
            $magickFullDims = $magickFull->getImageGeometry();
            $magickWaterFullSize = max($magickFullDims['height'], $magickFullDims['width']) * 0.0875;
            $magickWaterFull->scaleImage($magickWaterFullSize, $magickWaterFullSize);
            $magickFull->compositeImage($magickWaterFull, Imagick::COMPOSITE_OVER, $magickFullDims['width'] - $magickWaterFullSize - ($magickWaterFullSize / 5), $magickFullDims['height'] - $magickWaterFullSize - ($magickWaterFullSize / 5));
            $magickWaterFull->destroy();
        }

        $magickFull->setImageCompression(Imagick::COMPRESSION_JPEG);
        $magickFull->setImageCompressionQuality(80);
        $magickFull->stripImage();
        $magickFull->writeImage($save . $extension);
        $magickFull->destroy();

        // Create Mobile Variant
        if ($mobile && ($w > 1440 || $h > 1440))
        {
            $magickMobile = clone $magickSource;
            $magickMobile->resizeImage(1440, 1440, Imagick::FILTER_LANCZOS, 1, true);

            if ($watermark)
            {
                $magickWaterMobile = clone $magickWater;
                $magickMobileDims = $magickMobile->getImageGeometry();
                $magickWaterMobileSize = max($magickMobileDims['height'], $magickMobileDims['width']) * 0.0875;
                $magickWaterMobile->scaleImage($magickWaterMobileSize, $magickWaterMobileSize);
                $magickMobile->compositeImage($magickWaterMobile, Imagick::COMPOSITE_OVER, $magickMobileDims['width'] - $magickWaterMobileSize - ($magickWaterMobileSize / 5), $magickMobileDims['height'] - $magickWaterMobileSize - ($magickWaterMobileSize / 5));
                $magickWaterMobile->destroy();
            }

            $magickMobile->setImageCompression(Imagick::COMPRESSION_JPEG);
            $magickMobile->setImageCompressionQuality(80);
            $magickMobile->stripImage();
            $magickMobile->writeImage($save . '_mobile' . $extension);
            $magickMobile->destroy();
        }

        // Create Thumbnail Variants
        if ($thumbnail)
        {
            $magickThumb = clone $magickSource;
            $magickThumb->resizeImage(min(375, $w), min(375, $h), Imagick::FILTER_LANCZOS, 1, true);
            $magickThumb->setImageCompression(Imagick::COMPRESSION_JPEG);
            $magickThumb->setImageCompressionQuality(90);
            $magickThumb->stripImage();
            $magickThumb->writeImage($save . '_thumb' . $extension);
            $magickThumb->destroy();

            $magickThumb2x = clone $magickSource;
            $magickThumb2x->resizeImage(min(750, $w), min(750, $h), Imagick::FILTER_LANCZOS, 1, true);
            $magickThumb2x->setImageCompression(Imagick::COMPRESSION_JPEG);
            $magickThumb2x->setImageCompressionQuality(85);
            $magickThumb2x->stripImage();
            $magickThumb2x->writeImage($save . '_thumb@2x' . $extension);
            $magickThumb2x->destroy();
        }

        $magickSource->destroy();

        if ($watermark)
        {
            $magickWater->destroy();
        }

        return ([
            'width'     =>  $w,
            'height'    =>  $h,
            'extension' =>  $extension
        ]);
    }

    /**
     * Generate a base64-encoded variant of an image to be used for pre-loading.
     *
     * @return string The base64 encoded representation of the downscaled image.
     */
    public static function base64($file)
    {
        $file = file_get_contents($file);

        $magickMicro = new Imagick();
        $magickMicro->readImageBlob($file);
        $magickMicro = Self::orientate($magickSource);
        $magickMicro->resizeImage(32, 32, Imagick::FILTER_LANCZOS, 1, true);
        $magickMicro->setImageCompression(Imagick::COMPRESSION_JPEG);
        $magickMicro->setImageCompressionQuality(25);
        $magickMicro->stripImage();
        $data = base64_encode($magickMicro->getImageBlob());
        $magickMicro->destroy();

        return $data;
    }

    /**
     * Rotate an image based on its EXIF data, if present.
     * [via https://stackoverflow.com/a/31943940/2141501]
     *
     * @param Imagick $image  The ImageMagick representation of the image.
     *
     * @return Imagick The orientated image.
     */
    public static function orientate(&$magickSource)
    {
        switch ($magickSource->getImageOrientation())
        {
            case Imagick::ORIENTATION_TOPLEFT:
                break;
            case Imagick::ORIENTATION_TOPRIGHT:
                $magickSource->flopImage();
                break;
            case Imagick::ORIENTATION_BOTTOMRIGHT:
                $magickSource->rotateImage('#000', 180);
                break;
            case Imagick::ORIENTATION_BOTTOMLEFT:
                $magickSource->flopImage();
                $magickSource->rotateImage('#000', 180);
                break;
            case Imagick::ORIENTATION_LEFTTOP:
                $magickSource->flopImage();
                $magickSource->rotateImage('#000', -90);
                break;
            case Imagick::ORIENTATION_RIGHTTOP:
                $magickSource->rotateImage('#000', 90);
                break;
            case Imagick::ORIENTATION_RIGHTBOTTOM:
                $magickSource->flopImage();
                $magickSource->rotateImage('#000', 90);
                break;
            case Imagick::ORIENTATION_LEFTBOTTOM:
                $magickSource->rotateImage('#000', -90);
                break;
            default: // Invalid or Unspecified Orientation
                break;
        }

        return $magickSource;
    }
}
