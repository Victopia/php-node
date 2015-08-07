<?php
/* ImageConverter.php | Converts and resize image using the GD2 PHP extension. */

namespace core;

use core\Utility;

class ImageConverter {

  //--------------------------------------------------
  //
  //  Properties
  //
  //--------------------------------------------------

  private $image = null;

  private $mime = null;

  //--------------------------------------------------
  //
  //  Constructor
  //
  //--------------------------------------------------

  function __construct($file = null) {
    if ($file) {
      $this->open($file);
    }
  }

  function __destruct() {
    $this->close();
  }

  //--------------------------------------------------
  //
  //  Methods
  //
  //--------------------------------------------------

  /**
   * Open an image file.
   */
  function open($file) {
    $stat = Utility::getInfo($file, FILEINFO_MIME_TYPE);

    switch ($stat) {
      case 'image/jpeg':
      case 'image/jpg':
        $image = imagecreatefromjpeg($file);
        break;

      case 'image/gif':
        $image = imagecreatefromgif($file);
        break;

      case 'image/png':
        $image = imagecreatefrompng($file);

        imagealphablending($image, true);
        imagesavealpha($image, true);
        break;

      case 'image/bmp':
        // $image = imagecreatefromwbmp($file);
        $image = self::importBMP($file);
        break;

      case 'image/vnd.wap.wbmp':
        $image = imagecreatefromwbmp($file);
        break;

      case 'image/tif':
      case 'image/tiff':
      default:
        $image = null;
        break;
    }

    if ( !$image ) {
      throw new exceptions\CoreException("Invalid image format \"$stat\", this class supports a limited set of image formats. Read the code for more details.");
    }

    imageinterlace($image, 1);

    $this->image = $image;

    $this->mime = $stat;
  }

  /**
   * Closes and destroys an image file.
   */
  function close() {
    if ( is_resource($this->image) ) {
      imagedestroy($this->image);

      $this->image = null;
    }
  }

  /**
   * The image width in pixels.
   */
  function getWidth() {
    $this->checkImage();

    return imagesx($this->image);
  }

  /**
   * The image height in pixels.
   */
  function getHeight() {
    $this->checkImage();

    return imagesy($this->image);
  }

  /**
   * Returns the binary image data, optionally with specified format.
   */
  function getImage($mime = null, $quality = 85) {
    if ( !is_resource($this->image) ) {
      return null;
    }

    if ( $mime === null ) {
      $mime = $this->mime;
    }

    ob_start();

    switch ( $mime ) {
      case 'image/jpeg':
      case 'image/jpg':
        imagejpeg($this->image, null, $quality);
        break;

      case 'image/gif':
        imagegif($this->image);
        break;

      case 'image/png':
        imagepng($this->image, null, $quality / 10, PNG_FILTER_PAETH);
        break;

      case 'image/vnd.wap.wbmp':
        imagewbmp($this->image);
        break;

      case 'image/bmp':
        self::exportBMP($this->image);
        break;

      case 'image/tif':
      case 'image/tiff':
      default:
        $image = null;
        break;
    }

    return ob_get_clean();
  }

  /**
   * Add text into the image.
   *
   * @param {string} Desired text to apply.
   * @param {float} $fontSize Defaults to 12 pt, or 2 in imagestring.
   * @param {mixed} #position Array in pixels of [x, y], or a space separated string.
   *                          String values can be numeric, "top", "center" or "bottom".
   * @param {float} $angle Angle in counter-clockwise degrees, default 0.
   * @param {mixed} $color Either color in HEX code or the color index returned from
   *                       imagecolor* functions, default 0.
   * @param {string} $font Target font file, defaults to "Arial".
   * @param {array} $options Font type specific options.
   *                         1. For FreeType2 fonts, extrainfo in imagefttext.
   *                         2. For PostScript fonts, it's [ background, space, tightness, antialias_steps ]
   */
  function addText($text = '', $fontSize = null, $position = array(0, 0), $angle = 0, $color = 0, $font = '') {
    $this->checkImage();

    if ( !$font ) {
      return false;
    }

    if ( is_null($fontSize) ) {
      $fontSize = 12;
    }

    // Position
    if ( is_string($position) ) {
      if ( preg_match('/^\s*(\w+|\d+%?)\s+(\w+|\d+%?)\s*$/', strtolower($position), $matches) ) {
        $position = array(0, 0);

        $iD = array(
            'width' => imagesx($this->image)
          , 'height'=> imagesy($this->image)
          );

        $fD = imagettfbbox($fontSize, $angle, $font, $text);

        $fD = array(
            'width' => abs($fD[4] - $fD[0])
          , 'height' => abs($fD[5] - $fD[1])
          );

        switch ( $matches[1] ) {
          case 'top':
            $position[1] = 0;
            break;

          case 'bottom':
            $position[1] = $iD['height'] - $fD['height'];
            break;

          case 'center':
          case 'middle':
            $position[1] = ($iD['height'] - $fD['height']) / 2;
            break;

          default:
            if ( preg_match('/(\d+)(%)?/', $matches[1], $_matches) ) {
              if ( @$_matches[2] ) {
                $position[1] = $iD['height'] * ($_matches[1] / 100);
              }
              else {
                $position[1] = intval($_matches[1]);
              }
            }

            unset($_matches);
        }

        $position[1]+= $fD['height'];

        switch ( $matches[2] ) {
          case 'left':
            $position[0] = 0;
            break;

          case 'right':
            $position[0] = $iD['width'] - $fD['width'];
            break;

          case 'center':
          case 'middle':
            $position[0] = ($iD['width'] - $fD['width']) / 2;
            break;

          default:
            if ( preg_match('/(\d+)(%)?/', $matches[2], $_matches) ) {
              if ( @$_matches[2] ) {
                $position[0] = $iD['width'] * ($_matches[1] / 100) + $fD['width'];
              }
              else {
                $position[0] = intval($_matches[1]);
              }
            }

            unset($_matches);
        }

        unset($iD, $fD);
      }
    }

    if ( preg_match('/#?([0-9A-F][0-9A-F])([0-9A-F][0-9A-F])([0-9A-F][0-9A-F])([0-9A-F][0-9A-F])?/i', $color, $matches) ) {
      $color = array_map('hexdec', array_slice($matches, 1));
    } unset($matches);

    // Color
    if ( is_array($color) ) {
      if ( count($color) == 3 ) {
        $color = imagecolorallocate($this->image, $color[0], $color[1], $color[2]);
      }
      else
      if ( count($color) == 4 ) {
        if ( $color[3] > 127 ) {
          $color[3] = 127;
        }

        $color = imagecolorallocatealpha($this->image, $color[0], $color[1], $color[2], $color[3]);
      }
    }

    // No text is gonna be written.
    if ( !$text ) {
      return;
    }

    return imagettftext($this->image, $fontSize, $angle, $position[0], $position[1], $color, $font, $text);
  }

  /**
   * Resize an image with or within the dimensions provided.
   *
   * @param $width (int) Width of the image in pixels.
   *
   * @param $height (int) Height of the image in pixels.
   *
   * @param $keepRatio (Optional) (bool) true to resize
   *        the image within the dimension provided.
   *
   * @param $ratioPicker (Optional) (callable) A function
   *        that returns either width ratio or height ratio
   *        when resizing an image. Defaults to php native
   *        min(), that picks the smaller one to resize within
   *        target bounds.
   *        Beware that if you put 'max' here, image will not
   *        crop but instead resize to *at least* the size
   *        you specified, for cropping, see the next parameter.
   *
   * @param $cropToBounds (Optional) (bool) Whether the image
   *        should be cropped when result is bigger than specified
   *        dimensions, this has no meaning when using the default
   *        min() ratio picker.
   *
   * @returns (bool) true on success, false otherwise.
   */
  function resizeTo($width, $height, $keepRatio = false, $ratioPicker = 'min', $cropToBounds = false) {
    $this->checkImage();

    $srcWidth  = imagesx($this->image);
    $srcHeight = imagesy($this->image);

    $ratioX = $width / $srcWidth;
    $ratioY = $height / $srcHeight;

    // Set scale ratio identical to maintain aspect ratio.
    if ($keepRatio) {
      $ratioX = $ratioY = call_user_func_array($ratioPicker, array($ratioX, $ratioY));
    }

    // image fill rect.
    $dstWidth = round($srcWidth * $ratioX);
    $dstHeight = round($srcHeight * $ratioY);

    // image bounds
    if ($cropToBounds) {
      $image = imagecreatetruecolor($width, $height);
    }
    else {
      $image = imagecreatetruecolor($dstWidth, $dstHeight);
    }

    if ( !$image ) {
      return false;
    }

    /* Note by Eric @ 24 Jan, 2013
       Filling the area with transparent is not the way to preserve transparency.

    // Creates an image with transparent background.
    imagefilledrectangle($image
      , 0, 0
      , $width, $height
      , imagecolorallocatealpha($image, 0, 0, 0, 127)
      );
    */

    // Disable alpha blending will have the alpha channel directly be replaced.
    imagealphablending($image, false);

    // And we will surely be saving the alpha data.
    imagesavealpha($image, true);

    // Perform resample and copy
    imagecopyresampled($image, $this->image
      , 0, 0, 0, 0
      , $dstWidth, $dstHeight, $srcWidth, $srcHeight
      );

    // Destroy the old image
    imagedestroy($this->image);

    // Assign the new image
    $this->image = $image;

    return true;
  }

  //--------------------------------------------------
  //
  //  Private methods
  //
  //--------------------------------------------------

  /**
   * @private
   *
   * Check if an appropiate image has already been loaded.
   */
  private function checkImage() {
    if ( !$this->image || !is_resource($this->image) ) {
      throw new exceptions\CoreException('Image has not been properly loaded, please call ImageConverter::open() first.');
    }
  }

  /**
   * @private
   *
   * Raw import BMP file.
   */
  private static function importBMP($file) {
    if ( !($file = @fopen($file, 'rb')) ) {
      return false;
    }

    $buffer = fread($file, 10);

    while ( !foef($file) && $buffer ) {
      $buffer.= fread($file, 1024);
    }

    $buffer = unpack('H*', $buffer);
    $buffer = $buffer[1];

    $header = substr($buffer, 0, 108);

    // Dimensions
    $dimensions = array(
        'top' => 0
      , 'left' => 0
      , 'width' => 0
      , 'height' => 0
      );

    if (substr($header, 0, 4) == '424d') {
      $header = str_split($header, 2);

      $dimensions['width'] = hexdec("$header[19]$header[18]");
      $dimensions['height'] = hexdec("$header[23]$header[22]");
    }
    else {
      // TODO: No dimensions defined???
      return false;
    }

    unset($header);

    $image = imagecreatetruecolor($dimensions['width'], $dimensions['height']);

    $body = substr($buffer, 108);
    $bodySize = strlen($body) / 2;

    $hasPadding = $bodySize > $dimensions['width'] * $dimensions['height'] * 3 + 4;

    for ($i=0; $i<$bodySize; $i+=3) {
      if ($dimensions['left'] >= $dimensions['width']) {
        if ($hasPadding) {
          $i+= $dimensions['width'] % 4;
        }

        $dimensions['left'] = 0;
        $dimensions['top']++;

        if ($dimensions['top'] > $dimensions['height']) {
          break;
        }
      }

      $point = $i * 2;

      $color = imagecolorallocate($image
        , hexdec($body[$point + 4], $body[$point + 5]) // Red
        , hexdec($body[$point + 2], $body[$point + 3]) // Green
        , hexdec($body[$point + 0], $body[$point + 1]) // Blue
        );

      imagesetpixel($image
        , $dimensions['left']
        , $dimensions['height'] - $dimensions['top']
        , $color
        );

      $dimensions['left']++;
    } unset($i, $point, $color, $body, $bodySize, $hasPadding);

    return $image;
  }

  /**
   * @private
   *
   * Raw output of BMP file
   */
  private static function exportBMP($image, $writeSteam = null) {
    if ( !is_resource($image) ) {
      return false;
    }

    if ( $writeSteam === null ) {
      $writeSteam = 'php://output';
    }

    $writeSteam = fopen($writeSteam, 'w');

    if ( !$writeSteam ) {
      return false;
    }

    $dimensions = array(
      'width' => imagesx($image)
    , 'height'=> imagesy($image)
    );

    $bitsOffset = 54;

    $imageBPLine = $dimensions['width'] * 3;
    $imageStride = ($imageBPLine + 3) & ~3;
    $imageSize = $imageStride * $dimensions['height'];

    // BMP File Header
    fwrite($writeSteam, 'BM');
    fwrite($writeSteam, pack('VvvV', $bitsOffset + $imageSize, 0, 0, $bitsOffset));

    // BMP Info Header
    fwrite($writeSteam, pack( 'VVVvvVVVVVV', 40
                            , $dimensions['width'], $dimensions['height']
                            , 1, 24, 0
                            , $imageStride * $dimensions['height']
                            , 0, 0, 0, 0));

    // Bits padding
    $bitsOffset = $imageStride - $imageBPLine;

    for ($y=$dimensions['height']-1; $y>=0; --$y) {
      for ($x=0; $x<$dimensions['width']; ++$x) {
        fwrite($writeSteam, pack('V', imagecolorat($image, $x, $y)), 3);
      }

      for ($i=0; $i<$bitsOffset; ++$i) {
        fwrite($writeSteam, pack('C', 0));
      }
    }

    fclose($writeSteam);

    return true;
  }

}
