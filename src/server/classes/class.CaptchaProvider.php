<?php

include_once(INSTALL_PATH."/server/classes/securimage/securimage.php");

class CaptchaProvider{	
	
	public static function sendCaptcha(){
		
		$libPath = INSTALL_PATH."/server/classes/securimage";
		
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
	
	public static function checkCaptchaResult($code){
		
		$img = new Securimage();
		return $img->check($code);
		
	}
	
}

?>