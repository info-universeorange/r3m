<?php
/**
 * @author          Remco van der Velde
 * @since           04-01-2019
 * @copyright       (c) Remco van der Velde
 * @license         MIT
 * @version         1.0
 * @changeLog
 *  -    all
 */
namespace R3m\Io\Cli\Route\Controller;

use Exception;
use R3m\Io\App;
use R3m\Io\Config;
use R3m\Io\Module\File;
use R3m\Io\Module\View;

class Route extends View{
    const NAME = 'Route';
    const DIR = __DIR__;

    const COMMAND_INFO = 'info';
    const COMMAND_RESTART = 'restart';
    const COMMAND = [
        Route::COMMAND_INFO,
        Route::COMMAND_RESTART
    ];

    const DEFAULT_COMMAND = Route::COMMAND_INFO;

    const EXCEPTION_COMMAND_PARAMETER = '{$command}';
    const EXCEPTION_COMMAND = 'invalid command (' . Route::EXCEPTION_COMMAND_PARAMETER . ')';

    public static function run($object){
        $command = $object->parameter($object, Route::NAME, 1);

        if($command === null){
            $command = Route::DEFAULT_COMMAND;
        }
        if(!in_array($command, Route::COMMAND)){
            $exception = str_replace(
                Route::EXCEPTION_COMMAND_PARAMETER,
                $command,
                Route::EXCEPTION_COMMAND
            );
            throw new Exception($exception);
        }
        return Route::{$command}($object);
    }

    private static function info($object){
        $url = Route::locate($object, ucfirst(__FUNCTION__));
        return Route::view($object, $url);
    }


    private static function restart($object){        
        $url = Route::locate($object, ucfirst(__FUNCTION__));
        return Route::view($object, $url);
    }
}