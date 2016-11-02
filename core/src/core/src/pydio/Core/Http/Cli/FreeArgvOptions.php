<?php
/**
 * This file is part of the ApiGen (http://apigen.org)
 *
 * For the full copyright and license information, please view
 * the file license.md that was distributed with this source code.
 *
 * The MIT License

- Copyright (c) 2014 [Tomáš Votruba](http://tomasvotruba.cz)
- Copyright (c) 2012 [Olivier Laviale](https://github.com/olvlvl)
- Copyright (c) 2011 [Ondřej Nešpor](https://github.com/Andrewsville)
- Copyright (c) 2011 [Jaroslav Hanslík](https://github.com/kukulich)
- Copyright (c) 2010 [David Grudl](http://davidgrudl.com)

Permission is hereby granted, free of charge, to any person
obtaining a copy of this software and associated documentation
files (the "Software"), to deal in the Software without
restriction, including without limitation the rights to use,
copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the
Software is furnished to do so, subject to the following
conditions:

The above copyright notice and this permission notice shall be
included in all copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND,
EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES
OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND
NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY,
WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING
FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR
OTHER DEALINGS IN THE SOFTWARE.
 *
 */
namespace Pydio\Core\Http\Cli;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Class FreeArgvOptions
 * Options parser for Pydio
 * @package Pydio\Core\Http\Cli
 */
class FreeArgvOptions extends ArgvInput
{
    private $freeParsed;

    /**
     * FreeArgvOptions constructor.
     * @param array $argv
     */
    public function __construct($argv = array()){
        $arr = $_SERVER["argv"];
        $script = array_shift($arr);
        $_SERVER["argv"] = array_merge([$script, "pydio"], $arr);
        parent::__construct($_SERVER["argv"]);
    }

    /**
     * Decides wether we should split an option value by commas
     * @param $key
     * @return bool
     */
    protected function fieldSupportCommaSplit($key){
        return in_array($key, ["cli_repository_id", "cli_impersonate"]);
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        if(!isSet($this->freeParsed)){
            $options = parent::getOptions();
            foreach ($options as $key => $value) {
                $value = self::removeEqualsSign($value);
                if($this->fieldSupportCommaSplit($key)){
                    $options[$key] = $this->splitByComma($value);
                }else{
                    $options[$key] = $value;
                }
            }
            $this->freeParsed = $options;
        }
        return $this->freeParsed;
    }
    /**
     * @param string $name
     * @return mixed
     */
    public function getOption($name)
    {
        $this->options = $this->getOptions();
        return parent::getOption($name);
    }
    /**
     * @param array|string $value
     * @return array|string
     */
    public static function removeEqualsSign($value)
    {
        if (is_array($value)) {
            array_walk($value, function (&$singleValue) {
                $singleValue = ltrim($singleValue, '=');
            });
        } else {
            $value = ltrim($value, '=');
        }
        return $value;
    }
    /**
     * @param mixed $value
     * @return mixed
     */
    private function splitByComma($value)
    {
        if (is_array($value) && count($value) === 1) {
            array_walk($value, function (&$singleValue) {
                if ($this->containsComma($singleValue)) {
                    $singleValue = explode(',', $singleValue);
                }
            });
            if (is_array($value[0])) {
                return $value[0];
            }
        }
        if ($this->containsComma($value)) {
            $value = explode(',', $value);
        }
        return $value;
    }
    /**
     * @param string $value
     * @return bool
     */
    private function containsComma($value)
    {
        return strpos($value, ',') !== FALSE;
    }
}
