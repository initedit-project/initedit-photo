<?php

/**
 * Instagram filters with PHP and ImageMagick
 *
 * @package    Instagraph
 * @author     Webarto &lt;dejan.marjanovic@gmail.com>
 * @copyright  NetTuts+
 * @license    http://creativecommons.org/licenses/by-nc/3.0/ CC BY-NC
 */
class Instagraph {

    public $_image = NULL;
    public $_output = NULL;
    public $_prefix = 'IMG';
    private $_width = NULL;
    private $_height = NULL;
    private $_tmp = NULL;

    public static function factory($image, $output) {
        return new Instagraph($image, $output);
    }

    public function __construct($image, $output) {
        if (file_exists($image)) {
            $this->_image = $image;
            list($this->_width, $this->_height) = getimagesize($image);
            $this->_output = $output;
        } else {
            throw new Exception('File not found. Aborting.');
        }
    }

    public function tempfile() {
        # copy original file and assign temporary name
        $this->_tmp = $this->_prefix . rand();
        copy($this->_image, $this->_tmp);
    }

    public function output() {
        # rename working temporary file to output filename
        rename($this->_tmp, $this->_output);
    }

    public function execute($command) {
        # remove newlines and convert single quotes to double to prevent errors
        $command = str_replace(array("\n", "'"), array('', '"'), $command);
        $command = escapeshellcmd($command);
        # execute convert program
        exec($command);
    }

    /** ACTIONS */
    public function colortone($input, $color, $level, $type = 0) {
        $args[0] = $level;
        $args[1] = 100 - $level;
        $negate = $type == 0 ? '-negate' : '';

        $this->execute("convert 
        {$input} 
        ( -clone 0 -fill '$color' -colorize 100% ) 
        ( -clone 0 -colorspace gray $negate ) 
        -compose blend -define compose:args=$args[0],$args[1] -composite 
        {$input}");
    }

    public function border($input, $color = 'black', $width = 20) {
        $this->execute("convert $input -bordercolor $color -border {$width}x{$width} $input");
    }

    public function frame($input, $frame) {
        $this->execute("convert $input ( '$frame' -resize {$this->_width}x{$this->_height}! -unsharp 1.5×1.0+1.5+0.02 ) -flatten $input");
    }

    public function vignette($input, $color_1 = 'none', $color_2 = 'black', $crop_factor = 1.5) {
        $crop_x = floor($this->_width * $crop_factor);
        $crop_y = floor($this->_height * $crop_factor);

        $this->execute("convert 
        ( {$input} ) 
        ( -size {$crop_x}x{$crop_y} 
        radial-gradient:$color_1-$color_2
        -gravity center -crop {$this->_width}x{$this->_height}+0+0 +repage )
        -compose multiply -flatten 
        {$input}");
    }

    public function getImageResource() {
        $im = $this->imageCreateFromAny($this->_output);
        return $im;
    }

    public function deleteImage() {
        unlink($this->_tmp);
        unlink($this->_output);
    }

    public function imageCreateFromAny($filepath) {
        $type = exif_imagetype($filepath); // [] if you don't have exif you could use getImageSize()
        $allowedTypes = array(
            1, // [] gif
            2, // [] jpg
            3 // [] png
        );
        if (!in_array($type, $allowedTypes)) {
            return false;
        }
        switch ($type) {
            case 1 :
                $im = imageCreateFromGif($filepath);
                break;
            case 2 :
                $im = imageCreateFromJpeg($filepath);
                break;
            case 3 :
                $im = imageCreateFromPng($filepath);
                break;
            case 6 :
                $im = imageCreateFromBmp($filepath);
                break;
        }
        return $im;
    }

    /** RESERVED FOR FILTER METHODS */
    public function gotham() {
        $this->tempfile();
        $this->execute("convert $this->_tmp -modulate 120,10,100 -fill '#222b6d' -colorize 20 -gamma 0.5 -contrast -contrast $this->_tmp");
        $this->border($this->_tmp);
        $this->output();
    }

    public function toaster() {
        $this->tempfile();
        $this->colortone($this->_tmp, '#330000', 100, 0);

        $this->execute("convert $this->_tmp -modulate 150,80,100 -gamma 1.2 -contrast -contrast $this->_tmp");

        $this->vignette($this->_tmp, 'none', 'LavenderBlush3');
        $this->vignette($this->_tmp, '#ff9966', 'none');

        $this->output();
    }

    public function nashville() {
        $this->tempfile();

        $this->colortone($this->_tmp, '#222b6d', 100, 0);
        $this->colortone($this->_tmp, '#f7daae', 100, 1);

        $this->execute("convert $this->_tmp -contrast -modulate 100,150,100 -auto-gamma $this->_tmp");
        $this->frame($this->_tmp, __FUNCTION__);

        $this->output();
    }

    public function lomo() {
        $this->tempfile();

        $command = "convert {$this->_tmp} -channel R -level 33% -channel G -level 33% $this->_tmp";

        $this->execute($command);
        $this->vignette($this->_tmp);

        $this->output();
    }

    public function kelvin() {
        $this->tempfile();

        $this->execute("convert 
    ( $this->_tmp -auto-gamma -modulate 120,50,100 ) 
    ( -size {$this->_width}x{$this->_height} -fill 'rgba(255,153,0,0.5)' -draw 'rectangle 0,0 {$this->_width},{$this->_height}' ) -compose multiply 
    $this->_tmp");
        $this->frame($this->_tmp, __FUNCTION__);

        $this->output();
    }

    public function tiltShift() {
        $this->tempfile();
        $this->execute("convert
        ( $this->_tmp -gamma 0.75 -modulate 100,130 -contrast )
        ( +clone -sparse-color Barycentric '0,0 black 0,%h white' -function polynomial 4,-4,1 -level 0,50% )
        -compose blur -set option:compose:args 5 -composite
        $this->_tmp");
        $this->output();
    }

    public function mylomo() {
        $this->tempfile();

        $command = "convert {$this->_tmp} -channel R -level 33% -channel B -level 33% $this->_tmp";

        $this->execute($command);
        $this->vignette($this->_tmp);

        $this->output();
    }

    public function mylomo2() {
        $this->tempfile();

        $command = "convert {$this->_tmp} -channel G -level 33% -channel B -level 33% $this->_tmp";

        $this->execute($command);
        $this->vignette($this->_tmp);

        $this->output();
    }

    public function coollomo() {
        $this->tempfile();

        $command = "convert {$this->_tmp} -channel R -level 10% -channel G -level 43% $this->_tmp";

        $this->execute($command);
        $this->vignette($this->_tmp);

        $this->output();
    }

    public function charcoal() {
        $this->tempfile();

        $this->execute("convert $this->_tmp -charcoal 1 $this->_tmp");

        $this->output();
    }

    public function coolcharcoal() {
        $this->tempfile();

        $this->execute("convert $this->_tmp -charcoal 2 $this->_tmp");

        $this->output();
    }

    public function blackandwhite() {
        $this->tempfile();

        $this->execute("convert $this->_tmp -colorspace Gray $this->_tmp");

        $this->output();
    }

    public function highcontrast() {
        $this->tempfile();

        $this->execute("convert $this->_tmp -color-matrix 
  ' 1.5 0.0 0.0 0.0, 0.0, -0.157 
    0.0 1.5 0.0 0.0, 0.0, -0.157 
    0.0 0.0 1.5 0.0, 0.0, -0.157 
    0.0 0.0 0.0 1.0, 0.0,  0.0 
    0.0 0.0 0.0 0.0, 1.0,  0.0 
    0.0 0.0 0.0 0.0, 0.0,  1.0' $this->_tmp");

        $this->output();
    }

    public function noise() {
        $this->tempfile();

        $this->execute("convert $this->_tmp +noise Impulse $this->_tmp");

        $this->output();
    }

    public function colorize() {
        $this->tempfile();

        $this->execute("convert $this->_tmp -colorize 0,0,50 $this->_tmp");

        $this->output();
    }

    public function colorize2() {
        $this->tempfile();

        $this->execute("convert $this->_tmp -colorize 0,50,0 $this->_tmp");

        $this->output();
    }

    public function colorize3() {
        $this->tempfile();

        $this->execute("convert $this->_tmp -colorize 30,80,70 $this->_tmp");

        $this->output();
    }

    public function coolcolorize() {
        $this->tempfile();

        $this->execute("convert $this->_tmp -colorize 50,0,0 $this->_tmp");

        $this->output();
    }

    public function fuzz() {
        $this->tempfile();

        $this->execute("convert $this->_tmp -fuzz 80% $this->_tmp");

        $this->output();
    }

}
