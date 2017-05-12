<?php
/*
 * Copyright 2007-2017 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <https://pydio.com>.
 */
namespace Pydio\Core\Utils\Http;

defined('AJXP_EXEC') or die( 'Access not allowed');

/**
 * Encapsulation of the securimage external library, to generate a Captcha Image on brute force login attempt.
 */
class CaptchaProvider
{
    /**
     * Print out a Captcha image
     * @static
     * @return void
     */
    public static function sendCaptcha()
    {
        $img = new \Securimage();

        $img->wordlist_file = dirname(__FILE__).DIRECTORY_SEPARATOR.'captcha_words.txt';
        $img->image_height = 80;
        $img->image_width = 170;
        $img->perturbation = 0.85;
        $img->image_bg_color = new \Securimage_Color("#f6f6f6");
        $img->multi_text_color = array(new \Securimage_Color("#3399ff"),
                                       new \Securimage_Color("#3300cc"),
                                       new \Securimage_Color("#3333cc"),
                                       new \Securimage_Color("#6666ff"),
                                       new \Securimage_Color("#99cccc")
                                       );
        $img->use_multi_text = true;
        $img->text_angle_minimum = -5;
        $img->text_angle_maximum = 5;
        $img->use_transparent_text = true;
        $img->text_transparency_percentage = 30; // 100 = completely transparent
        $img->num_lines = 5;
        $img->line_color = new \Securimage_Color("#eaeaea");
        $img->signature_color = new \Securimage_Color(rand(0, 64), rand(64, 128), rand(128, 255));
        $img->use_wordlist = true;
        if (!function_exists('imagettftext')) {
            $img->use_gd_font = true;
            $img->use_transparent_text = false;
            $img->use_multi_text = false;
        }
        $img->show();
    }

    /**
     * Verify the code against the current image.
     * @static
     * @param $code
     * @return bool
     */
    public static function checkCaptchaResult($code)
    {
        $img = new \Securimage();
        return $img->check($code);

    }

}
