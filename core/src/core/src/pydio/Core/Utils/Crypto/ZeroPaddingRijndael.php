<?php
/*
 * Copyright 2007-2017 Abstrium <contact (at) pydio.com>
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
 * The latest code can be found at <https://pydio.com/>.
 */

namespace Pydio\Core\Utils\Crypto;

defined('AJXP_EXEC') or die('Access not allowed');

use \phpseclib\Crypt\Rijndael;

/**
 * Class ZeroPaddingRijndael
 * @package Pydio\Core\Utils\Crypto
 */
class ZeroPaddingRijndael extends Rijndael {
    /**
     * Pads a string
     *
     * Pads a string using the RSA PKCS padding standards so that its length is a multiple of the blocksize.
     * $this->block_size - (strlen($text) % $this->block_size) bytes are added, each of which is equal to
     * chr($this->block_size - (strlen($text) % $this->block_size)
     *
     * If padding is disabled and $text is not a multiple of the blocksize, the string will be padded regardless
     * and padding will, hence forth, be enabled.
     *
     * @see self::_unpad()
     * @param string $text
     * @throws \LengthException if padding is disabled and the plaintext's length is not a multiple of the block size
     * @access private
     * @return string
     */
    function _pad($text)
    {
        $length = strlen($text);

        if (!$this->padding) {
            if ($length % $this->block_size == 0) {
                return $text;
            } else {
                throw new \LengthException("The plaintext's length ($length) is not a multiple of the block size ({$this->block_size}). Try enabling padding.");
            }
        }

        $pad = $this->block_size - ($length % $this->block_size);
        return str_pad($text, $length + $pad, "\0");
    }
    /**
     * Unpads a string.
     *
     * If padding is enabled and the reported padding length is invalid the encryption key will be assumed to be wrong
     * and false will be returned.
     *
     * @see self::_pad()
     * @param string $text
     * @throws \LengthException if the ciphertext's length is not a multiple of the block size
     * @access private
     * @return string
     */
    function _unpad($text)   {
        return trim($text, "\0");
    }
}
