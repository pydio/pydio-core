<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

include_once(AJXP_BIN_FOLDER."/securimage/securimage.php");

/**
 * @package info.ajaxplorer.core
 */
/**
 * Encapsulation of the securimage external library, to generate a Captcha Image on brute force login attempt.
 */
class CaptchaProvider{
	/**
     * Print out a Captcha image
     * @static
     * @return void
     */
	public static function sendCaptcha(){
		
		$libPath = AJXP_BIN_FOLDER."/securimage";
		
		$img = new Securimage();
		$img->wordlist_file = $libPath."/words/words.txt";
		$img->gd_font_file = $libPath."/gdfonts/automatic.gdf";
		$img->signature_font = $img->ttf_file = $libPath."/AHGBold.ttf";
				
		$img->image_height = 80;
		$img->image_width = 170;
		$img->perturbation = 0.85;
		$img->image_bg_color = new Securimage_Color("#f6f6f6");
		$img->multi_text_color = array(new Securimage_Color("#3399ff"),
		                               new Securimage_Color("#3300cc"),
		                               new Securimage_Color("#3333cc"),
		                               new Securimage_Color("#6666ff"),
		                               new Securimage_Color("#99cccc")
		                               );
		$img->use_multi_text = true;
		$img->text_angle_minimum = -5;
		$img->text_angle_maximum = 5;
		$img->use_transparent_text = true;
		$img->text_transparency_percentage = 30; // 100 = completely transparent
		$img->num_lines = 5;
		$img->line_color = new Securimage_Color("#eaeaea");
		$img->signature_color = new Securimage_Color(rand(0, 64), rand(64, 128), rand(128, 255));
		$img->use_wordlist = true; 
		if(!function_exists('imagettftext')){
			$img->use_gd_font = true;	
			$img->use_transparent_text = false;	
			$img->use_multi_text = false;
		}
		//$img->show($libPath."/backgrounds/bg3.jpg");		
		$img->show();
	}

    /**
     * Verify the code against the current image.
     * @static
     * @param $code
     * @return bool
     */
	public static function checkCaptchaResult($code){
		
		$img = new Securimage();
		return $img->check($code);
		
	}
	
}

?>