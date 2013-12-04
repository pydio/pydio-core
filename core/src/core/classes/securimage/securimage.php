<?php

/**
 * Project:     Securimage: A PHP class for creating and managing form CAPTCHA images<br />
 * File:        securimage.php<br />
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or any later version.<br /><br />
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.<br /><br />
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA<br /><br />
 *
 * Any modifications to the library should be indicated clearly in the source code
 * to inform users that the changes are not a part of the original software.<br /><br />
 *
 * If you found this script useful, please take a quick moment to rate it.<br />
 * http://www.hotscripts.com/rate/49400.html  Thanks.
 *
 * @link http://www.phpcaptcha.org Securimage PHP CAPTCHA
 * @link http://www.phpcaptcha.org/latest.zip Download Latest Version
 * @link http://www.phpcaptcha.org/Securimage_Docs/ Online Documentation
 * @copyright 2009 Drew Phillips
 * @author Drew Phillips <drew@drew-phillips.com>
 * @version 2.0.1 BETA (December 6th, 2009)
 * @package Securimage
 *
 */

/**
 ChangeLog

 2.0.1
 - Add support for browsers with cookies disabled (requires php5, sqlite) maps users to md5 hashed ip addresses and md5 hashed codes for security
 - Add fallback to gd fonts if ttf support is not enabled or font file not found (Mike Challis http://www.642weather.com/weather/scripts.php)
 - Check for previous definition of image type constants (Mike Challis)
 - Fix mime type settings for audio output
 - Fixed color allocation issues with multiple colors and background images, consolidate allocation to one function
 - Ability to let codes expire after a given length of time
 - Allow HTML color codes to be passed to Securimage_Color (suggested by Mike Challis)

 2.0.0
 - Add mathematical distortion to characters (using code from HKCaptcha)
 - Improved session support
 - Added Securimage_Color class for easier color definitions
 - Add distortion to audio output to prevent binary comparison attack (proposed by Sven "SavageTiger" Hagemann [insecurity.nl])
 - Flash button to stream mp3 audio (Douglas Walsh www.douglaswalsh.net)
 - Audio output is mp3 format by default
 - Change font to AlteHaasGrotesk by yann le coroller
 - Some code cleanup

 1.0.4 (unreleased)
 - Ability to output audible codes in mp3 format to stream from flash

 1.0.3.1
 - Error reading from wordlist in some cases caused words to be cut off 1 letter short

 1.0.3
 - Removed shadow_text from code which could cause an undefined property error due to removal from previous version

 1.0.2
 - Audible CAPTCHA Code wav files
 - Create codes from a word list instead of random strings

 1.0
 - Added the ability to use a selected character set, rather than a-z0-9 only.
 - Added the multi-color text option to use different colors for each letter.
 - Switched to automatic session handling instead of using files for code storage
 - Added GD Font support if ttf support is not available.  Can use internal GD fonts or load new ones.
 - Added the ability to set line thickness
 - Added option for drawing arced lines over letters
 - Added ability to choose image type for output

 */

/**
 * Output images in JPEG format
 */
if (!defined('SI_IMAGE_JPEG'))
  define('SI_IMAGE_JPEG', 1);
/**
 * Output images in PNG format
 */
if (!defined('SI_IMAGE_PNG'))
  define('SI_IMAGE_PNG',  2);
/**
 * Output images in GIF format (not recommended)
 * Must have GD >= 2.0.28!
 */
if (!defined('SI_IMAGE_GIF'))
  define('SI_IMAGE_GIF',  3);

/**
 * Securimage CAPTCHA Class.
 *
 * @package    Securimage
 * @subpackage classes
 *
 */
class Securimage
{
    /**
     * The desired width of the CAPTCHA image.
     *
     * @var int
     */
    public $image_width;

    /**
     * The desired width of the CAPTCHA image.
     *
     * @var int
     */
    public $image_height;

    /**
     * The image format for output.<br />
     * Valid options: SI_IMAGE_PNG, SI_IMAGE_JPG, SI_IMAGE_GIF
     *
     * @var int
     */
    public $image_type;

    /**
     * The length of the code to generate.
     *
     * @var int
     */
    public $code_length;

    /**
     * The character set for individual characters in the image.<br />
     * Letters are converted to uppercase.<br />
     * The font must support the letters or there may be problematic substitutions.
     *
     * @var string
     */
    public $charset;

    /**
     * Create codes using this word list
     *
     * @var string  The path to the word list to use for creating CAPTCHA codes
     */
    public $wordlist_file;

    /**
     * Use wordlist of not
     *
     * @var bool true to use wordlist file, false to use random code
     */
    public $use_wordlist = false;

    /**
     * Note: Use of GD fonts is not recommended as many distortion features are not available<br />
     * The GD font to use.<br />
     * Internal gd fonts can be loaded by their number.<br />
     * Alternatively, a file path can be given and the font will be loaded from file.
     *
     * @var mixed
     */
    public $gd_font_file;

    /**
     * The approximate size of the font in pixels.<br />
     * This does not control the size of the font because that is determined by the GD font itself.<br />
     * This is used to aid the calculations of positioning used by this class.<br />
     *
     * @var int
     */
    public $gd_font_size;

    /**
     * Use a gd font instead of TTF
     *
     * @var bool true for gd font, false for TTF
     */
    public $use_gd_font;

    // Note: These font options below do not apply if you set $use_gd_font to true with the exception of $text_color

    /**
     * The path to the TTF font file to load.
     *
     * @var string
     */
    public $ttf_file;

    /**
     * How much to distort image, higher = more distortion.<br />
     * Distortion is only available when using TTF fonts.<br />
     *
     * @var float
     */
    public $perturbation;

    /**
     * The minimum angle in degrees, with 0 degrees being left-to-right reading text.<br />
     * Higher values represent a counter-clockwise rotation.<br />
     * For example, a value of 90 would result in bottom-to-top reading text.<br />
     * This value along with maximum angle distance do not need to be very high with perturbation
     *
     * @var int
     */
    public $text_angle_minimum;

    /**
     * The minimum angle in degrees, with 0 degrees being left-to-right reading text.<br />
     * Higher values represent a counter-clockwise rotation.<br />
     * For example, a value of 90 would result in bottom-to-top reading text.
     *
     * @var int
     */
    public $text_angle_maximum;

    /**
     * The X-Position on the image where letter drawing will begin.<br />
     * This value is in pixels from the left side of the image.
     *
     * @var int
     * @deprecated 2.0
     */
    public $text_x_start;

    /**
     * The background color for the image as a Securimage_Color.<br />
     *
     * @var Securimage_Color
     */
    public $image_bg_color;

    /**
     * Scan this directory for gif, jpg, and png files to use as background images.<br />
     * A random image file will be picked each time.<br />
     * Change from null to the full path to your directory.<br />
     * i.e. var $background_directory = $_SERVER['DOCUMENT_ROOT'] . '/securimage/backgrounds';
     * Make sure not to pass a background image to the show function, otherwise this directive is ignored.
     *
     * @var string
     */
    public $background_directory = null; //'./backgrounds';

    /**
     * The text color to use for drawing characters as a Securimage_Color.<br />
     * This value is ignored if $use_multi_text is set to true.<br />
     * Make sure this contrasts well with the background color or image.<br />
     *
     * @see Securimage::$use_multi_text
     * @var Securimage_Color
     */
    public $text_color;

    /**
     * Set to true to use multiple colors for each character.
     *
     * @see Securimage::$multi_text_color
     * @var boolean
     */
    public $use_multi_text;

    /**
     * Array of Securimage_Colors which will be randomly selected for each letter.<br />
     *
     * @var array
     */
    public $multi_text_color;

    /**
     * Set to true to make the characters appear transparent.
     *
     * @see Securimage::$text_transparency_percentage
     * @var boolean
     */
    public $use_transparent_text;

    /**
     * The percentage of transparency, 0 to 100.<br />
     * A value of 0 is completely opaque, 100 is completely transparent (invisble)
     *
     * @see Securimage::$use_transparent_text
     * @var int
     */
    public $text_transparency_percentage;


    // Line options
    /**
    * Draw vertical and horizontal lines on the image.
    *
    * @see Securimage::$line_color
    * @see Securimage::$draw_lines_over_text
    * @var boolean
    */
    public $num_lines;

    /**
     * Color of lines drawn over text
     *
     * @var string
     */
    public $line_color;

    /**
     * Draw the lines over the text.<br />
     * If fales lines will be drawn before putting the text on the image.
     *
     * @var boolean
     */
    public $draw_lines_over_text;

    /**
     * Text to write at the bottom corner of captcha image
     *
     * @since 2.0
     * @var string Signature text
     */
    public $image_signature;

    /**
     * Color to use for writing signature text
     *
     * @since 2.0
     * @var Securimage_Color
     */
    public $signature_color;

    /**
     * Full path to the WAV files to use to make the audio files, include trailing /.<br />
     * Name Files  [A-Z0-9].wav
     *
     * @since 1.0.1
     * @var string
     */
    public $audio_path;

    /**
     * Type of audio file to generate (mp3 or wav)
     *
     * @var string
     */
    public $audio_format;

    /**
     * The session name to use if not the default.  Blank for none
     *
     * @see http://php.net/session_name
     * @since 2.0
     * @var string
     */
    public $session_name = '';

    /**
     * The amount of time in seconds that a code remains valid.<br />
     * Any code older than this number will be considered invalid even if entered correctly.<br />
     * Any non-numeric or value less than 1 disables this functionality.
     *
     * @var int
     */
    public $expiry_time;

    /**
     * Path to the file to use for storing codes for users.<br />
     * THIS FILE MUST ABSOLUTELY NOT BE ACCESSIBLE FROM A WEB BROWSER!!<br />
     * Put this file in a directory below the web root or one that is restricted (i.e. an apache .htaccess file with deny from all)<br />
     * If you cannot meet those requirements your forms may not be completely protected.<br />
     * You could obscure the database file name but this is also not recommended.
     *
     * @var string
     */
    public $sqlite_database;

    /**
     * Use an SQLite database for storing codes as a backup to sessions.<br />
     * Note: Sessions will still be used
     */
    public $use_sqlite_db;


    //END USER CONFIGURATION
    //There should be no need to edit below unless you really know what you are doing.

    /**
     * The gd image resource.
     *
     * @access private
     * @var resource
     */
    public $im;

    /**
     * Temporary image for rendering
     *
     * @access private
     * @var resource
     */
    public $tmpimg;

    /**
     * Internal scale factor for anti-alias @hkcaptcha
     *
     * @access private
     * @since 2.0
     * @var int
     */
    public $iscale; // internal scale factor for anti-alias @hkcaptcha

    /**
     * The background image resource
     *
     * @access private
     * @var resource
     */
    public $bgimg;

    /**
     * The code generated by the script
     *
     * @access private
     * @var string
     */
    public $code;

    /**
     * The code that was entered by the user
     *
     * @access private
     * @var string
     */
    public $code_entered;

    /**
     * Whether or not the correct code was entered
     *
     * @access private
     * @var boolean
     */
    public $correct_code;

    /**
     * Handle to SQLite database
     *
     * @access private
     * @var resource
     */
    public $sqlite_handle;

    /**
     * Color resource for image line color
     *
     * @access private
     * @var int
     */
    public $gdlinecolor;

    /**
     * Array of colors for multi colored codes
     *
     * @access private
     * @var array
     */
    public $gdmulticolor;

    /**
     * Color resource for image font color
     *
     * @access private
     * @var int
     */
    public $gdtextcolor;

    /**
     * Color resource for image signature color
     *
     * @access private
     * @var int
     */
    public $gdsignaturecolor;

    /**
     * Color resource for image background color
     *
     * @access private
     * @var int
     */
    public $gdbgcolor;


    /**
     * Class constructor.<br />
     * Because the class uses sessions, this will attempt to start a session if there is no previous one.<br />
     * If you do not start a session before calling the class, the constructor must be called before any
     * output is sent to the browser.
     *
     * <code>
     *   $securimage = new Securimage();
     * </code>
     *
     */
    public function Securimage()
    {
        // Initialize session or attach to existing
        if ( session_id() == '' ) { // no session has been started yet, which is needed for validation
            if (trim($this->session_name) != '') {
                session_name($this->session_name); // set session name if provided
            }
            session_start();
        }

        // Set Default Values
        $this->image_width   = 230;
        $this->image_height  = 80;
        $this->image_type    = SI_IMAGE_PNG;

        $this->code_length   = 6;
        $this->charset       = 'ABCDEFGHKLMNPRSTUVWYZabcdefghklmnprstuvwyz23456789';
        $this->wordlist_file = './words/words.txt';
        $this->use_wordlist  = false;

        $this->gd_font_file  = 'gdfonts/automatic.gdf';
        $this->use_gd_font   = false;
        $this->gd_font_size  = 24;
        $this->text_x_start  = 15;

        $this->ttf_file      = './AHGBold.ttf';

        $this->perturbation       = 0.75;
        $this->iscale             = 5;
        $this->text_angle_minimum = 0;
        $this->text_angle_maximum = 0;

        $this->image_bg_color   = new Securimage_Color(0xff, 0xff, 0xff);
    $this->text_color       = new Securimage_Color(0x3d, 0x3d, 0x3d);
        $this->multi_text_color = array(new Securimage_Color(0x0, 0x20, 0xCC),
                                                                        new Securimage_Color(0x0, 0x30, 0xEE),
                                                                        new Securimage_color(0x0, 0x40, 0xCC),
                                                                        new Securimage_Color(0x0, 0x50, 0xEE),
                                                                        new Securimage_Color(0x0, 0x60, 0xCC));
        $this->use_multi_text   = false;

        $this->use_transparent_text         = false;
        $this->text_transparency_percentage = 30;

        $this->num_lines            = 10;
        $this->line_color           = new Securimage_Color(0x3d, 0x3d, 0x3d);
        $this->draw_lines_over_text = true;

        $this->image_signature = '';
        $this->signature_color = new Securimage_Color(0x20, 0x50, 0xCC);
        $this->signature_font  = './AHGBold.ttf';

        $this->audio_path   = './audio/';
        $this->audio_format = 'mp3';
        $this->session_name = '';
        $this->expiry_time  = 900;

        $this->sqlite_database = 'database/securimage.sqlite';
        $this->use_sqlite_db   = false;

        $this->sqlite_handle = false;
    }

    /**
     * Generate a code and output the image to the browser.
     *
     * <code>
     *   <?php
     *   include 'securimage.php';
     *   $securimage = new Securimage();
     *   $securimage->show('bg.jpg');
     *   ?>
     * </code>
     *
     * @param string $background_image  The path to an image to use as the background for the CAPTCHA
     */
    public function show($background_image = "")
    {
        if ($background_image != "" && is_readable($background_image)) {
            $this->bgimg = $background_image;
        }

        $this->doImage();
    }

    /**
     * Validate the code entered by the user.
     *
     * <code>
     *   $code = $_POST['code'];
     *   if ($securimage->check($code) == false) {
     *     die("Sorry, the code entered did not match.");
     *   } else {
     *     $valid = true;
     *   }
     * </code>
     * @param string $code  The code the user entered
     * @return boolean  true if the code was correct, false if not
     */
    public function check($code)
    {
        $this->code_entered = $code;
        $this->validate();
        return $this->correct_code;
    }

    /**
     * Output audio file with HTTP headers to browser
     *
     * <code>
     *   $sound = new Securimage();
     *   $sound->audio_format = 'mp3';
     *   $sound->outputAudioFile();
     * </code>
     *
     * @since 2.0
     */
    public function outputAudioFile()
    {
        if (strtolower($this->audio_format) == 'wav') {
            header('Content-type: audio/x-wav');
            $ext = 'wav';
        } else {
            header('Content-type: audio/mpeg'); // default to mp3
            $ext = 'mp3';
        }

        header("Content-Disposition: attachment; filename=\"securimage_audio.{$ext}\"");
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Expires: Sun, 1 Jan 2000 12:00:00 GMT');
        header('Last-Modified: ' . gmdate('D, d M Y H:i:s') . 'GMT');

        $audio = $this->getAudibleCode($ext);

        header('Content-Length: ' . strlen($audio));

        echo $audio;
        exit;
    }

    /**
     * Generate and output the image
     *
     * @access private
     *
     */
    public function doImage()
    {
        if ($this->use_gd_font == true) {
            $this->iscale = 1;
        }
        if ($this->use_transparent_text == true || $this->bgimg != "") {
            $this->im     = imagecreatetruecolor($this->image_width, $this->image_height);
            $this->tmpimg = imagecreatetruecolor($this->image_width * $this->iscale, $this->image_height * $this->iscale);

        } else { //no transparency
            $this->im     = imagecreate($this->image_width, $this->image_height);
            $this->tmpimg = imagecreate($this->image_width * $this->iscale, $this->image_height * $this->iscale);
        }

        $this->allocateColors();
        imagepalettecopy($this->tmpimg, $this->im);

        $this->setBackground();

        $this->createCode();

        if (!$this->draw_lines_over_text && $this->num_lines > 0) $this->drawLines();

        $this->drawWord();
        if ($this->use_gd_font == false && is_readable($this->ttf_file)) $this->distortedCopy();

        if ($this->draw_lines_over_text && $this->num_lines > 0) $this->drawLines();

        if (trim($this->image_signature) != '')	$this->addSignature();

        $this->output();

    }

    /**
     * Allocate all colors that will be used in the CAPTCHA image
     *
     * @since 2.0.1
     * @access private
     */
    public function allocateColors()
    {
        // allocate bg color first for imagecreate
        $this->gdbgcolor = imagecolorallocate($this->im, $this->image_bg_color->r, $this->image_bg_color->g, $this->image_bg_color->b);

        $alpha = intval($this->text_transparency_percentage / 100 * 127);

        if ($this->use_transparent_text == true) {
      $this->gdtextcolor = imagecolorallocatealpha($this->im, $this->text_color->r, $this->text_color->g, $this->text_color->b, $alpha);
      $this->gdlinecolor = imagecolorallocatealpha($this->im, $this->line_color->r, $this->line_color->g, $this->line_color->b, $alpha);
        } else {
            $this->gdtextcolor = imagecolorallocate($this->im, $this->text_color->r, $this->text_color->g, $this->text_color->b);
      $this->gdlinecolor = imagecolorallocate($this->im, $this->line_color->r, $this->line_color->g, $this->line_color->b);
        }

    $this->gdsignaturecolor = imagecolorallocate($this->im, $this->signature_color->r, $this->signature_color->g, $this->signature_color->b);

    if ($this->use_multi_text == true) {
        $this->gdmulticolor = array();

        foreach ($this->multi_text_color as $color) {
            if ($this->use_transparent_text == true) {
              $this->gdmulticolor[] = imagecolorallocatealpha($this->im, $color->r, $color->g, $color->b, $alpha);
            } else {
                $this->gdmulticolor[] = imagecolorallocate($this->im, $color->r, $color->g, $color->b);
            }
        }
    }
    }

    /**
     * Set the background of the CAPTCHA image
     *
     * @access private
     *
     */
    public function setBackground()
    {
        imagefilledrectangle($this->im, 0, 0, $this->image_width * $this->iscale, $this->image_height * $this->iscale, $this->gdbgcolor);
    imagefilledrectangle($this->tmpimg, 0, 0, $this->image_width * $this->iscale, $this->image_height * $this->iscale, $this->gdbgcolor);

        if ($this->bgimg == '') {
            if ($this->background_directory != null && is_dir($this->background_directory) && is_readable($this->background_directory)) {
                $img = $this->getBackgroundFromDirectory();
                if ($img != false) {
                    $this->bgimg = $img;
                }
            }
        }

        $dat = @getimagesize($this->bgimg);
        if ($dat == false) {
            return;
        }

        switch ($dat[2]) {
            case 1:  $newim = @imagecreatefromgif($this->bgimg); break;
            case 2:  $newim = @imagecreatefromjpeg($this->bgimg); break;
            case 3:  $newim = @imagecreatefrompng($this->bgimg); break;
            case 15: $newim = @imagecreatefromwbmp($this->bgimg); break;
            case 16: $newim = @imagecreatefromxbm($this->bgimg); break;
            default: return;
        }

        if(!$newim) return;

        imagecopyresized($this->im, $newim, 0, 0, 0, 0, $this->image_width, $this->image_height, imagesx($newim), imagesy($newim));
    }

    /**
     * Return the full path to a random gif, jpg, or png from the background directory.
     *
     * @access private
     * @see Securimage::$background_directory
     * @return mixed  false if none found, string $path if found
     */
    public function getBackgroundFromDirectory()
    {
        $images = array();

        if ($dh = opendir($this->background_directory)) {
            while (($file = readdir($dh)) !== false) {
                if (preg_match('/(jpg|gif|png)$/i', $file)) $images[] = $file;
            }

            closedir($dh);

            if (sizeof($images) > 0) {
                return rtrim($this->background_directory, '/') . '/' . $images[rand(0, sizeof($images)-1)];
            }
        }

        return false;
    }

    /**
     * Draw random curvy lines over the image<br />
     * Modified code from HKCaptcha
     *
     * @since 2.0
     * @access private
     *
     */
    public function drawLines()
    {
        for ($line = 0; $line < $this->num_lines; ++$line) {
            $x = $this->image_width * (1 + $line) / ($this->num_lines + 1);
            $x += (0.5 - $this->frand()) * $this->image_width / $this->num_lines;
            $y = rand($this->image_height * 0.1, $this->image_height * 0.9);

            $theta = ($this->frand()-0.5) * M_PI * 0.7;
            $w = $this->image_width;
            $len = rand($w * 0.4, $w * 0.7);
            $lwid = rand(0, 2);

            $k = $this->frand() * 0.6 + 0.2;
            $k = $k * $k * 0.5;
            $phi = $this->frand() * 6.28;
            $step = 0.5;
            $dx = $step * cos($theta);
            $dy = $step * sin($theta);
            $n = $len / $step;
            $amp = 1.5 * $this->frand() / ($k + 5.0 / $len);
            $x0 = $x - 0.5 * $len * cos($theta);
            $y0 = $y - 0.5 * $len * sin($theta);

            $ldx = round(-$dy * $lwid);
            $ldy = round($dx * $lwid);

            for ($i = 0; $i < $n; ++$i) {
                $x = $x0 + $i * $dx + $amp * $dy * sin($k * $i * $step + $phi);
                $y = $y0 + $i * $dy - $amp * $dx * sin($k * $i * $step + $phi);
                imagefilledrectangle($this->im, $x, $y, $x + $lwid, $y + $lwid, $this->gdlinecolor);
            }
        }
    }

    /**
     * Draw the CAPTCHA code over the image
     *
     * @access private
     *
     */
    public function drawWord()
    {
        $width2 = $this->image_width * $this->iscale;
        $height2 = $this->image_height * $this->iscale;

        if ($this->use_gd_font == true || !is_readable($this->ttf_file)) {
            if (!is_int($this->gd_font_file)) { //is a file name
                $font = @imageloadfont($this->gd_font_file);
                if ($font == false) {
                    trigger_error("Failed to load GD Font file {$this->gd_font_file} ", E_USER_WARNING);
                    return;
                }
            } else { //gd font identifier
                $font = $this->gd_font_file;
            }

            imagestring($this->im, $font, $this->text_x_start, ($this->image_height / 2) - ($this->gd_font_size / 2), $this->code, $this->gdtextcolor);
        } else { //ttf font
            $font_size = $height2 * .35;
            $bb = imagettfbbox($font_size, 0, $this->ttf_file, $this->code);
            $tx = $bb[4] - $bb[0];
            $ty = $bb[5] - $bb[1];
            $x  = floor($width2 / 2 - $tx / 2 - $bb[0]);
            $y  = round($height2 / 2 - $ty / 2 - $bb[1]);

            $strlen = strlen($this->code);
            if (!is_array($this->multi_text_color)) $this->use_multi_text = false;


            if ($this->use_multi_text == false && $this->text_angle_minimum == 0 && $this->text_angle_maximum == 0) { // no angled or multi-color characters
                imagettftext($this->tmpimg, $font_size, 0, $x, $y, $this->gdtextcolor, $this->ttf_file, $this->code);
            } else {
                for ($i = 0; $i < $strlen; ++$i) {
                    $angle = rand($this->text_angle_minimum, $this->text_angle_maximum);
                    $y = rand($y - 5, $y + 5);
                    if ($this->use_multi_text == true) {
                        $font_color = $this->gdmulticolor[rand(0, sizeof($this->gdmulticolor) - 1)];
                    } else {
                        $font_color = $this->gdtextcolor;
                    }

                    $ch = $this->code{$i};

                    imagettftext($this->tmpimg, $font_size, $angle, $x, $y, $font_color, $this->ttf_file, $ch);

                    // estimate character widths to increment $x without creating spaces that are too large or too small
                    // these are best estimates to align text but may vary between fonts
                    // for optimal character widths, do not use multiple text colors or character angles and the complete string will be written by imagettftext
                    if (strpos('abcdeghknopqsuvxyz', $ch) !== false) {
                        $min_x = $font_size - ($this->iscale * 6);
                        $max_x = $font_size - ($this->iscale * 6);
                    } else if (strpos('ilI1', $ch) !== false) {
                        $min_x = $font_size / 5;
                        $max_x = $font_size / 3;
                    } else if (strpos('fjrt', $ch) !== false) {
                        $min_x = $font_size - ($this->iscale * 12);
                        $max_x = $font_size - ($this->iscale * 12);
                    } else if ($ch == 'wm') {
                        $min_x = $font_size;
                        $max_x = $font_size + ($this->iscale * 3);
                    } else { // numbers, capitals or unicode
                        $min_x = $font_size + ($this->iscale * 2);
                        $max_x = $font_size + ($this->iscale * 5);
                    }

                    $x += rand($min_x, $max_x);
                } //for loop
            } // angled or multi-color
        } //else ttf font
        //$this->im = $this->tmpimg;
        //$this->output();
    } //function

    /**
     * Warp text from temporary image onto final image.<br />
     * Modified for securimage
     *
     * @access private
     * @since 2.0
     * @author Han-Kwang Nienhuys modified
     * @copyright Han-Kwang Neinhuys
     *
     */
    public function distortedCopy()
    {
        $numpoles = 3; // distortion factor

        // make array of poles AKA attractor points
        for ($i = 0; $i < $numpoles; ++$i) {
            $px[$i]  = rand($this->image_width * 0.3, $this->image_width * 0.7);
            $py[$i]  = rand($this->image_height * 0.3, $this->image_height * 0.7);
            $rad[$i] = rand($this->image_width * 0.4, $this->image_width * 0.7);
            $tmp     = -$this->frand() * 0.15 - 0.15;
            $amp[$i] = $this->perturbation * $tmp;
        }

        $bgCol   = imagecolorat($this->tmpimg, 0, 0);
        $width2  = $this->iscale * $this->image_width;
        $height2 = $this->iscale * $this->image_height;

        imagepalettecopy($this->im, $this->tmpimg); // copy palette to final image so text colors come across

        // loop over $img pixels, take pixels from $tmpimg with distortion field
        for ($ix = 0; $ix < $this->image_width; ++$ix) {
            for ($iy = 0; $iy < $this->image_height; ++$iy) {
                $x = $ix;
                $y = $iy;

                for ($i = 0; $i < $numpoles; ++$i) {
                    $dx = $ix - $px[$i];
                    $dy = $iy - $py[$i];
                    if ($dx == 0 && $dy == 0) continue;

                    $r = sqrt($dx * $dx + $dy * $dy);
                    if ($r > $rad[$i]) continue;

                    $rscale = $amp[$i] * sin(3.14 * $r / $rad[$i]);
                    $x += $dx * $rscale;
                    $y += $dy * $rscale;
                }

                $c = $bgCol;
                $x *= $this->iscale;
                $y *= $this->iscale;

                if ($x >= 0 && $x < $width2 && $y >= 0 && $y < $height2) {
                    $c = imagecolorat($this->tmpimg, $x, $y);
                }

                if ($c != $bgCol) { // only copy pixels of letters to preserve any background image
                    imagesetpixel($this->im, $ix, $iy, $c);
                }
            }
        }
    }

    /**
     * Create a code and save to the session
     *
     * @access private
     * @since 1.0.1
     *
     */
    public function createCode()
    {
        $this->code = false;

        if ($this->use_wordlist && is_readable($this->wordlist_file)) {
            $this->code = $this->readCodeFromFile();
        }

        if ($this->code == false) {
            $this->code = $this->generateCode($this->code_length);
        }

        $this->saveData();
    }

    /**
     * Generate a code
     *
     * @access private
     * @param int $len  The code length
     * @return string
     */
    public function generateCode($len)
    {
        $code = '';

        for ($i = 1, $cslen = strlen($this->charset); $i <= $len; ++$i) {
            $code .= $this->charset{rand(0, $cslen - 1)};
        }
        return $code;
    }

    /**
     * Reads a word list file to get a code
     *
     * @access private
     * @since 1.0.2
     * @return mixed  false on failure, a word on success
     */
    public function readCodeFromFile()
    {
        $fp = @fopen($this->wordlist_file, 'rb');
        if (!$fp) return false;

        $fsize = filesize($this->wordlist_file);
        if ($fsize < 32) return false; // too small of a list to be effective

        if ($fsize < 128) {
            $max = $fsize; // still pretty small but changes the range of seeking
        } else {
            $max = 128;
        }

        fseek($fp, rand(0, $fsize - $max), SEEK_SET);
        $data = fread($fp, 128); // read a random 128 bytes from file
        fclose($fp);
        $data = preg_replace("/\r?\n/", "\n", $data);

        $start = strpos($data, "\n", rand(0, 100)) + 1; // random start position
        $end   = strpos($data, "\n", $start);           // find end of word

        return strtolower(substr($data, $start, $end - $start)); // return substring in 128 bytes
    }

    /**
     * Output image to the browser
     *
     * @access private
     *
     */
    public function output()
    {
        header("Expires: Mon, 26 Jul 1997 05:00:00 GMT");
        header("Last-Modified: " . gmdate("D, d M Y H:i:s") . "GMT");
        header("Cache-Control: no-store, no-cache, must-revalidate");
        header("Cache-Control: post-check=0, pre-check=0", false);
        header("Pragma: no-cache");

        switch ($this->image_type) {
            case SI_IMAGE_JPEG:
                header("Content-Type: image/jpeg");
                imagejpeg($this->im, null, 90);
                break;

            case SI_IMAGE_GIF:
                header("Content-Type: image/gif");
                imagegif($this->im);
                break;

            default:
                header("Content-Type: image/png");
                imagepng($this->im);
                break;
        }

        imagedestroy($this->im);
        exit;
    }

    /**
     * Get WAV or MP3 file data of the spoken code.<br />
     * This is appropriate for output to the browser as audio/x-wav or audio/mpeg
     *
     * @since 1.0.1
     * @return string  WAV or MP3 data
     *
     */
    public function getAudibleCode($format = 'wav')
    {
        $letters = array();
        $code    = $this->getCode();

        if ($code == '') {
            $this->createCode();
            $code = $this->getCode();
        }

        for ($i = 0; $i < strlen($code); ++$i) {
            $letters[] = $code{$i};
        }

        if ($format == 'mp3') {
            return $this->generateMP3($letters);
        } else {
            return $this->generateWAV($letters);
        }
    }

    /**
     * Set the path to the audio directory.<br />
     *
     * @since 1.0.4
     * @return bool true if the directory exists and is readble, false if not
     */
    public function setAudioPath($audio_directory)
    {
        if (is_dir($audio_directory) && is_readable($audio_directory)) {
            $this->audio_path = $audio_directory;
            return true;
        } else {
            return false;
        }
    }

    /**
     * Save the code in the session
     *
     * @access private
     *
     */
    public function saveData()
    {
        $_SESSION['securimage_code_value'] = strtolower($this->code);
        $_SESSION['securimage_code_ctime'] = time();

        $this->saveCodeToDatabase();
    }

    /**
     * Validate the code to the user code
     *
     * @access private
     *
     */
    public function validate()
    {
        // retrieve code from session, if no code exists check sqlite database if supported.

        if (isset($_SESSION['securimage_code_value']) && trim($_SESSION['securimage_code_value']) != '') {
            if ($this->isCodeExpired($_SESSION['securimage_code_ctime']) == false) {
              $code = $_SESSION['securimage_code_value'];
            }
        } else if ($this->use_sqlite_db == true && function_exists('sqlite_open')) { // no code in session - may mean user has cookies turned off
            $this->openDatabase();
            $code = $this->getCodeFromDatabase();
        } else {
            // session code invalid or non-existant and code not found in sqlite db or sqlite is not available
            $code = '';
        }

        $code               = trim(strtolower($code));
        $code_entered       = trim(strtolower($this->code_entered));
        $this->correct_code = false;

        if ($code != '') {
            if ($code == $code_entered) {
              $this->correct_code = true;
              $_SESSION['securimage_code_value'] = '';
              $_SESSION['securimage_code_ctime'] = '';
              $this->clearCodeFromDatabase();
          }
        }
    }

    /**
     * Get the captcha code
     *
     * @since 1.0.1
     * @return string
     */
    public function getCode()
    {
        if (isset($_SESSION['securimage_code_value']) && !empty($_SESSION['securimage_code_value'])) {
            return strtolower($_SESSION['securimage_code_value']);
        } else {
            if ($this->sqlite_handle == false) $this->openDatabase();

            return $this->getCodeFromDatabase(); // attempt to get from database, returns empty string if sqlite is not available or disabled
        }
    }

    /**
     * Check if the user entered code was correct
     *
     * @access private
     * @return boolean
     */
    public function checkCode()
    {
        return $this->correct_code;
    }

    /**
     * Generate a wav file by concatenating individual files
     *
     * @since 1.0.1
     * @access private
     * @param array $letters  Array of letters to build a file from
     * @return string  WAV file data
     */
    public function generateWAV($letters)
    {
        $data_len    = 0;
        $files       = array();
        $out_data    = '';

        foreach ($letters as $letter) {
            $filename = $this->audio_path . strtoupper($letter) . '.wav';

            $fp = fopen($filename, 'rb');

            $file = array();

            $data = fread($fp, filesize($filename)); // read file in

            $header = substr($data, 0, 36);
            $body   = substr($data, 44);


            $data = unpack('NChunkID/VChunkSize/NFormat/NSubChunk1ID/VSubChunk1Size/vAudioFormat/vNumChannels/VSampleRate/VByteRate/vBlockAlign/vBitsPerSample', $header);

            $file['sub_chunk1_id']   = $data['SubChunk1ID'];
            $file['bits_per_sample'] = $data['BitsPerSample'];
            $file['channels']        = $data['NumChannels'];
            $file['format']          = $data['AudioFormat'];
            $file['sample_rate']     = $data['SampleRate'];
            $file['size']            = $data['ChunkSize'] + 8;
            $file['data']            = $body;

            if ( ($p = strpos($file['data'], 'LIST')) !== false) {
                // If the LIST data is not at the end of the file, this will probably break your sound file
                $info         = substr($file['data'], $p + 4, 8);
                $data         = unpack('Vlength/Vjunk', $info);
                $file['data'] = substr($file['data'], 0, $p);
                $file['size'] = $file['size'] - (strlen($file['data']) - $p);
            }

            $files[] = $file;
            $data    = null;
            $header  = null;
            $body    = null;

            $data_len += strlen($file['data']);

            fclose($fp);
        }

        $out_data = '';
        for ($i = 0; $i < sizeof($files); ++$i) {
            if ($i == 0) { // output header
                $out_data .= pack('C4VC8', ord('R'), ord('I'), ord('F'), ord('F'), $data_len + 36, ord('W'), ord('A'), ord('V'), ord('E'), ord('f'), ord('m'), ord('t'), ord(' '));

                $out_data .= pack('VvvVVvv',
                16,
                $files[$i]['format'],
                $files[$i]['channels'],
                $files[$i]['sample_rate'],
                $files[$i]['sample_rate'] * (($files[$i]['bits_per_sample'] * $files[$i]['channels']) / 8),
                ($files[$i]['bits_per_sample'] * $files[$i]['channels']) / 8,
                $files[$i]['bits_per_sample'] );

                $out_data .= pack('C4', ord('d'), ord('a'), ord('t'), ord('a'));

                $out_data .= pack('V', $data_len);
            }

            $out_data .= $files[$i]['data'];
        }

        $this->scrambleAudioData($out_data, 'wav');
        return $out_data;
    }

    /**
     * Randomly modify the audio data to scramble sound and prevent binary recognition.<br />
     * Take care not to "break" the audio file by leaving the header data intact.
     *
     * @since 2.0
     * @access private
     * @param $data Sound data in mp3 of wav format
     */
    public function scrambleAudioData(&$data, $format)
    {
        if ($format == 'wav') {
            $start = strpos($data, 'data') + 4; // look for "data" indicator
            if ($start === false) $start = 44;  // if not found assume 44 byte header
        } else { // mp3
            $start = 4; // 4 byte (32 bit) frame header
        }

        $start  += rand(1, 64); // randomize starting offset
        $datalen = strlen($data) - $start - 256; // leave last 256 bytes unchanged

        for ($i = $start; $i < $datalen; $i += 64) {
            $ch = ord($data{$i});
            if ($ch < 9 || $ch > 119) continue;

            $data{$i} = chr($ch + rand(-8, 8));
        }
    }

    /**
     * Generate an mp3 file by concatenating individual files
     * @since 1.0.4
     * @access private
     * @param array $letters  Array of letters to build a file from
     * @return string  MP3 file data
     */
    public function generateMP3($letters)
    {
        $data_len    = 0;
        $files       = array();
        $out_data    = '';

        foreach ($letters as $letter) {
            $filename = $this->audio_path . strtoupper($letter) . '.mp3';

            $fp   = fopen($filename, 'rb');
            $data = fread($fp, filesize($filename)); // read file in

            $this->scrambleAudioData($data, 'mp3');
            $out_data .= $data;

            fclose($fp);
        }


        return $out_data;
    }

    /**
     * Generate random number less than 1
     * @since 2.0
     * @access private
     * @return float
     */
    public function frand()
    {
        return 0.0001*rand(0,9999);
    }

    /**
     * Print signature text on image
     *
     * @since 2.0
     * @access private
     *
     */
    public function addSignature()
    {
        if ($this->use_gd_font) {
            imagestring($this->im, 5, $this->image_width - (strlen($this->image_signature) * 10), $this->image_height - 20, $this->image_signature, $this->gdsignaturecolor);
        } else {

            $bbox = imagettfbbox(10, 0, $this->signature_font, $this->image_signature);
            $textlen = $bbox[2] - $bbox[0];
            $x = $this->image_width - $textlen - 5;
            $y = $this->image_height - 3;

            imagettftext($this->im, 10, 0, $x, $y, $this->gdsignaturecolor, $this->signature_font, $this->image_signature);
        }
    }

    /**
     * Get hashed IP address of remote user
     *
     * @access private
     * @since 2.0.1
     * @return string
     */
    public function getIPHash()
    {
        return strtolower(md5($_SERVER['REMOTE_ADDR']));
    }

    /**
     * Open SQLite database
     *
     * @access private
     * @since 2.0.1
     * @return bool true if database was opened successfully
     */
    public function openDatabase()
    {
        $this->sqlite_handle = false;

        if ($this->use_sqlite_db && function_exists('sqlite_open')) {
            $this->sqlite_handle = sqlite_open($this->sqlite_database, 0666);

            if ($this->sqlite_handle !== false) {
                $res = sqlite_query($this->sqlite_handle, "PRAGMA table_info(codes)");
                if (sqlite_num_rows($res) == 0) {
                  sqlite_query($this->sqlite_handle, "CREATE TABLE codes (iphash VARCHAR(32) PRIMARY KEY, code VARCHAR(32) NOT NULL, created INTEGER)");
                }
            }

            return $this->sqlite_handle != false;
        }

        return $this->sqlite_handle;
    }

    /**
     * Save captcha code to sqlite database
     *
     * @access private
     * @since 2.0.1
     * @return bool true if code was saved, false if not
     */
    public function saveCodeToDatabase()
    {
        $success = false;

        $this->openDatabase();

        if ($this->use_sqlite_db && $this->sqlite_handle !== false) {
            $ip = $this->getIPHash();
            $time = time();
            $code = $_SESSION['securimage_code_value']; // hash code for security - if cookies are disabled the session still exists at this point
            $success = sqlite_query($this->sqlite_handle, "INSERT OR REPLACE INTO codes(iphash, code, created) VALUES('$ip', '$code', $time)");
        }

        return $success !== false;
    }

    /**
     * Get stored captcha code from sqlite database based on ip address hash
     *
     * @access private
     * @since 2.0.1
     * @return string captcha code
     */
    public function getCodeFromDatabase()
    {
    $code = '';

    if ($this->use_sqlite_db && $this->sqlite_handle !== false) {
        $ip = $this->getIPHash();

        $res = sqlite_query($this->sqlite_handle, "SELECT * FROM codes WHERE iphash = '$ip'");
        if ($res && sqlite_num_rows($res) > 0) {
            $res = sqlite_fetch_array($res);

            if ($this->isCodeExpired($res['created']) == false) {
                $code = $res['code'];
            }
        }
    }

    return $code;
    }

    /**
     * Delete a code from the database by ip address hash
     *
     * @access private
     * @since 2.0.1
     */
    public function clearCodeFromDatabase()
    {
        if ($this->sqlite_handle !== false) {
            $ip = $this->getIPHash();

            sqlite_query($this->sqlite_handle, "DELETE FROM codes WHERE iphash = '$ip'");
        }
    }

    /**
     * Purge codes over a day old from database
     *
     * @access private
     * @since 2.0.1
     */
    public function purgeOldCodesFromDatabase()
    {
        if ($this->use_sqlite_db && $this->sqlite_handle !== false) {
            $now   = time();
            $limit = (!is_numeric($this->expiry_time) || $this->expiry_time < 1) ? 86400 : $this->expiry_time;

            sqlite_query($this->sqlite_handle, "DELETE FROM codes WHERE $now - created > $limit");
        }
    }

    /**
     * Check a code to see if it is expired based on creation time
     *
     * @access private
     * @since 2.0.1
     * @param $creation_time unix timestamp of code creation time
     * @return bool true if code has expired, false if not
     */
    public function isCodeExpired($creation_time)
    {
        $expired = true;

        if (!is_numeric($this->expiry_time) || $this->expiry_time < 1) {
            $expired = false;
        } else if (time() - $creation_time < $this->expiry_time) {
            $expired = false;
        }

        return $expired;
    }

} /* class Securimage */


/**
 * Color object for Securimage CAPTCHA
 *
 * @since 2.0
 * @package Securimage
 * @subpackage classes
 *
 */
class Securimage_Color
{
    /**
     * Red component: 0-255
     *
     * @var int
     */
    public $r;
    /**
     * Green component: 0-255
     *
     * @var int
     */
    public $g;
    /**
     * Blue component: 0-255
     *
     * @var int
     */
    public $b;

    /**
     * Create a new Securimage_Color object.<br />
     * Specify the red, green, and blue components using their HTML hex code equivalent.<br />
     * Example: The code for the HTML color #4A203C is:<br />
     * $color = new Securimage_Color(0x4A, 0x20, 0x3C);
     *
     * @param $red Red component 0-255
     * @param $green Green component 0-255
     * @param $blue Blue component 0-255
     */
    public function Securimage_Color($red, $green = null, $blue = null)
    {
        if ($green == null && $blue == null && preg_match('/^#[a-f0-9]{3,6}$/i', $red)) {
            $col = substr($red, 1);
            if (strlen($col) == 3) {
                $red   = str_repeat(substr($col, 0, 1), 2);
                $green = str_repeat(substr($col, 1, 1), 2);
                $blue  = str_repeat(substr($col, 2, 1), 2);
            } else {
                $red   = substr($col, 0, 2);
                $green = substr($col, 2, 2);
                $blue  = substr($col, 4, 2);
            }

            $red   = hexdec($red);
            $green = hexdec($green);
            $blue  = hexdec($blue);
        } else {
            if ($red < 0) $red       = 0;
            if ($red > 255) $red     = 255;
            if ($green < 0) $green   = 0;
            if ($green > 255) $green = 255;
            if ($blue < 0) $blue     = 0;
            if ($blue > 255) $blue   = 255;
        }

        $this->r = $red;
        $this->g = $green;
        $this->b = $blue;
    }
}
