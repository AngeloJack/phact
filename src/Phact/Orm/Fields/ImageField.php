<?php
/**
 * Created by PhpStorm.
 * User: aleksandrgordeev
 * Date: 09.08.16
 * Time: 12:55
 */

namespace Phact\Orm\Fields;


use Exception;
use Imagine\Image\AbstractImage;
use Imagine\Image\AbstractImagine;
use Imagine\Image\Box;
use Imagine\Image\BoxInterface;
use Imagine\Image\ImageInterface;
use Imagine\Image\ManipulatorInterface;
use Imagine\Image\Metadata\DefaultMetadataReader;
use Phact\Exceptions\InvalidConfigException;
use Phact\Storage\Files\FileInterface;
use Phact\Storage\Files\StorageFile;


class ImageField extends FileField
{
    /**
     * Array with image sizes
     * key 'original' is reserved!
     * example:
     * [
     *      'thumb' => [
     *          300,200,
     *          'method' => 'adaptiveResize'
     *      ]
     * ]
     *
     * There are 3 methods resize(THUMBNAIL_INSET), adaptiveResize(THUMBNAIL_OUTBOUND),
     * adaptiveResizeFromTop(THUMBNAIL_OUTBOUND from top)
     *
     * @var array
     */
    public $sizes = [];

    /**
     * Force resize images
     * @var bool
     */
    public $force = false;

    /**
     * Imagine default options
     * @var array
     */
    public $options = [
        'resolution-units' => ImageInterface::RESOLUTION_PIXELSPERINCH,
        'resolution-x' => 72,
        'resolution-y' => 72,
        'jpeg_quality' => 100,
        'quality' => 100,
        'png_compression_level' => 0
    ];

    /**
     * @var bool
     */
    public $storeOriginal = true;

    /**
     * Cached original
     * @var null | \Imagine\Image\ImagineInterface
     */
    public $_original = null;

    /**
     * Cached original name
     * @var null | string
     */
    public $_originalName = null;

    /**
     * Recreate file if missing
     * @var bool
     */
    public $checkMissing = false;

    /**
     * @var bool.
     * Set true if AbstractImagine use defaultMetadataReader
     */
    public $useDefaultMetadataReader = true;


    /**
     * @var AbstractImagine instance
     */
    protected $_imagine;

    /**
     * @var AbstractImage instance
     */
    protected $_imageInstance;


    const RESIZE_METHOD_PREFIX = 'size';


    public function __get($name)
    {
        if (strpos($name, 'url_') === 0) {
            return $this->sizeUrl(str_replace('url_', '', $name));
        } else {
            return parent::__smartGet($name);
        }
    }

    public function sizeUrl($prefix)
    {

    }

    public function afterSave()
    {
        parent::afterSave();

        if (!empty($this->sizes)) {
            $this->createSizes();
        }
    }

    public function deleteOld()
    {
        parent::deleteOld();
        if (is_a($this->getOldAttribute(), FileInterface::class)) {
            foreach (array_keys($this->sizes) as $prefix) {
                $this->getStorage()->delete($this->sizeStoragePath($prefix, $this->getOldAttribute()));
            }
        }

    }


    public function createSizes()
    {
        foreach ($this->sizes as $sizeName => $params) {

            if (!$params['method']) {
                continue;
            }

            $imageInstance = $this->getImageInstance();

            $methodName = $params['method'];
            $methodName = self::RESIZE_METHOD_PREFIX . ucfirst($methodName);

            if ($imageInstance && method_exists($this, $methodName)) {

                $box = $this->getSizeBox($imageInstance, $params);

                if (!$imageInstance->getSize()->contains($box)) {
                    $source = $imageInstance;
                } else {
                    /** @var ImageInterface $source */
                    $source = $this->{$methodName}($box);
                }

                $this->saveSize($sizeName, $source);
            }
        }
    }

    /**
     * @param BoxInterface $box
     * @return ImageInterface|static
     */
    public function sizeCover(BoxInterface $box)
    {
        $imageInstance = $this->getImageInstance();
        return $imageInstance->thumbnail($box, ManipulatorInterface::THUMBNAIL_OUTBOUND);
    }

    /**
     * @param BoxInterface $box
     * @return ImageInterface|static
     */
    public function sizeContain(BoxInterface $box)
    {
        $imageInstance = $this->getImageInstance();
        return $imageInstance->thumbnail($box, ManipulatorInterface::THUMBNAIL_INSET);
    }


    /**
     * @param $sizeName
     * @param $source ImageInterface
     */
    public function saveSize($sizeName, $source)
    {

        /** @var StorageFile $storageFile */
        $storageFile = $this->attribute;
        $extension = $this->getStorage()->getExtension($storageFile->path);
        $options = isset($params['options']) ? $params['options'] : $this->options;
        $this->getStorage()->save($this->sizeStoragePath($sizeName, $storageFile), $source->get($extension, $options));
    }

    /**
     * @param $sizeName
     * @return string path storage
     */
    public function sizeStoragePath($sizeName, StorageFile $storageFile)
    {
        $directory = pathinfo($storageFile->path, PATHINFO_DIRNAME);
        $sizeFileName = $this->preparePrefixSize($sizeName) . $storageFile->getBaseName();
        return $directory . DIRECTORY_SEPARATOR . $sizeFileName;
    }

    /**
     * @param ImageInterface $image
     * @param $sizeParams
     */
    protected function getSizeBox(ImageInterface $image, $sizeParams)
    {
        $width = isset($sizeParams[0]) ? $sizeParams[0] : null;
        $height = isset($sizeParams[1]) ? $sizeParams[1] : null;

        /** if one of size params not passed, scale image proportion */
        if (!$width || !$height) {
            $box = new Box($image->getSize()->getWidth(), $image->getSize()->getHeight());
            if (!$width) {
                $box = $box->heighten($height);
            }
            if (!$height) {
                $box = $box->widen($width);
            }
        } else {
            $box = new Box($width, $height);
        }

        return $box;

    }

    protected function preparePrefixSize($prefix)
    {
        return rtrim($prefix, '_') . '_';
    }


    public function getImagine()
    {
        if ($this->_imagine === null) {
            $this->_imagine = $this->initImagine();
        }
        return $this->_imagine;
    }

    public function getImageInstance()
    {
        $filePath = $this->getPath();
        if ($this->_imageInstance == null && is_readable($filePath)) {
            try {
                $this->_imageInstance = $this->getImagine()->open($filePath);
            } catch (Exception $e) {
                $this->_imageInstance = null;
            }
        }
        return $this->_imageInstance;
    }


    public function initImagine()
    {
        $imagine = null;

        if (class_exists('Gmagick', false)) {
            $imagine = new \Imagine\Gmagick\Imagine();
        }
        if (class_exists('Imagick', false)) {
            $imagine = new \Imagine\Imagick\Imagine();
        }
        if (function_exists('gd_info')) {
            $imagine = new \Imagine\Gd\Imagine();
        }

        if ($imagine && $this->useDefaultMetadataReader) {
            $imagine->setMetadataReader(new DefaultMetadataReader());
        }

        if ($imagine) {
            return $imagine;
        }

        throw new InvalidConfigException('Libs: Gmagick, Imagick or Gd not found');
    }

    public function getFormField()
    {
        return $this->setUpFormField([
            'class' => \Phact\Form\Fields\ImageField::class
        ]);
    }


}