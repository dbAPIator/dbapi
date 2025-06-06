<?php
/**
 * Created by PhpStorm.
 * User: vsergiu
 * Date: 10/10/18
 * Time: 1:53 PM
 */
namespace dbAPI;
/**
 * Autoloads Apiator classes
 *
 * @author    Brent Shaffer <bshafs at gmail dot com>
 * @license   MIT License
 */
class Autoloader
{
    /**
     * @var string
     */
    private $dir;

    /**
     * @param string $dir
     */
    public function __construct($dir = null)
    {
        if (is_null($dir)) {
            $dir = dirname(__FILE__).'/..';
        }
        $this->dir = $dir;
    }

    /**
     * Registers OAuth2\Autoloader as an SPL autoloader.
     */
    public static function register($dir = null)
    {
        ini_set('unserialize_callback_func', 'spl_autoload_call');
        spl_autoload_register(array(new self($dir), 'autoload'));
    }

    /**
     * Handles autoloading of classes.
     *
     * @param string $class - A class name.
     * @return boolean      - Returns true if the class has been loaded
     */
    public function autoload($class)
    {
        $file = $this->dir.'/'.str_replace('\\', '/', $class).'.php';
        // echo "file: $file\n";
        if (0 !== strpos($class, 'dbAPI')) {
            return;
        }
        if (file_exists($file)) {
            /** @noinspection PhpIncludeInspection */
            require $file;
        }
    }
}
