<?php
/* ImageConverter.php | Converts and resize image using the GD2 PHP extension. */

namespace core;

class ImageConverter {

  //--------------------------------------------------
  //
  //  Properties
  //
  //--------------------------------------------------

  private $image = NULL;

  private $mime = NULL;

  //--------------------------------------------------
  //
  //  Constructor
  //
  //--------------------------------------------------

  function __construct($file = NULL) {
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
    $stat = \utils::getInfo($file, FILEINFO_MIME_TYPE);

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
        $image = NULL;
        break;
    }

    if (!$image) {
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
    if (is_resource($this->image)) {
      imagedestroy($this->image);

      $this->image = NULL;
    }
  }

  function getImage($mime = NULL, $quality = 85) {
    if (!is_resource($this->image)) {
      return NULL;
    }

    if ($mime === NULL) {
      $mime = $this->mime;
    }

    ob_start();

    switch ($mime) {
      case 'image/jpeg':
      case 'image/jpg':
        imagejpeg($this->image, NULL, $quality);
        break;

      case 'image/gif':
        imagegif($this->image);
        break;

      case 'image/png':
        imagepng($this->image, NULL, $quality / 10, PNG_FILTER_PAETH);
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
        $image = NULL;
        break;
    }

    return ob_get_clean();
  }

  /**
   * Resize an image with or within the dimensions provided.
   *
   * @param $width (int) Width of the image in pixels.
   *
   * @param $height (int) Height of the image in pixels.
   *
   * @param $keepRatio (Optional) (bool) TRUE to resize
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
   * @returns (bool) TRUE on success, FALSE otherwise.
   */
  function resizeTo($width, $height, $options = NULL) {
    $options = (array) $options;

    $this->checkImage();

    $src = array(
        'w' => imagesx($this->image)
      , 'h' => imagesy($this->image)
      );

    $ratio = array(
        'x' => $width / $src['w']
      , 'y' => $height / $src['h']
      );

    if (@$options['ratioPicker'] === TRUE) {
      $options['ratioPicker'] = 'min';
    }

    // Set scale ratio identical to maintain aspect ratio, with the specified function.
    if (@$options['ratioPicker']) {
      $ratio['x'] = $ratio['y'] =
        call_user_func_array(@$options['ratioPicker'], array($ratio['x'], $ratio['y']));
    }

    // Image fill rect
    $dst = array(
        'w' => round($src['w'] * $ratio['x'])
      , 'h' => round($src['h'] * $ratio['y'])
      );

    // Image bounds
    if (@$options['cropsTarget']) {
      $image = imagecreatetruecolor($width, $height);

      switch (@$options['cropsTarget']['x']) {
        case 'auto':
        case 'center':
          if ($dst['w'] > $width) { // width needs cropping
            $dst['x'] = round($width / 2 - $dst['w'] / 2);
          }
          else {
            // nothing needs to do here.
          }
          break;

        default:
          $dst['x'] = intval($options['cropsTarget']['x']);
          break;
      }

      switch (@$options['cropsTarget']['y']) {
        case 'auto':
        case 'center':
          if ($dst['h'] > $height) { // height needs cropping
            $dst['y'] = round($height / 2 - $dst['h'] / 2);
          }
          else {
            // nothing needs to do here.
          }
          break;

        default:
          $dst['y'] = intval($options['cropsTarget']['y']);
          break;
      }
    }

    // Take only smaller edges, automatically crop empty pixels
    $image = imagecreatetruecolor(
        min($dst['w'], $width)
      , min($dst['h'], $height)
      );

    // Creates an image with transparent background
    imagefilledrectangle($image
      , 0, 0
      , $width, $height
      , imagecolorallocatealpha($image, 0, 0, 0, 127)
      );

    // Perform resample and copy
    imagecopyresampled($image, $this->image
      , (int) @$dst['x'], (int) @$dst['y']
      , (int) @$src['x'], (int) @$src['y']
      , $dst['w'], $dst['h']
      , $src['w'], $src['h']
      );

    // Destroy the old image
    imagedestroy($this->image);

    // Assign the new image
    $this->image = $image;

    return TRUE;
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
    if (!$this->image || !is_resource($this->image)) {
      throw new exceptions\CoreException('Image has not been properly loaded, please call ImageConverter::open() first.');
    }
  }

  /**
   * @private
   *
   * Raw import BMP file.
   */
  private static function importBMP($file) {
    if (!($file = @fopen($file, 'rb'))) {
      return FALSE;
    }

    $buffer = fread($file, 10);

    while (!foef($file) && $buffer) {
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
      return FALSE;
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
  private static function exportBMP($image, $writeSteam = NULL) {
    if (!is_resource($image)) {
      return FALSE;
    }

    if ($writeSteam === NULL) {
      $writeSteam = 'php://output';
    }

    $writeSteam = fopen($writeSteam, 'w');

    if (!$writeSteam) {
      return FALSE;
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

    return TRUE;
  }

}