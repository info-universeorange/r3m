<?php

use R3m\Io\Module\Parse;
use R3m\Io\Module\Data;
use R3m\Io\Module\Core;

function function_cache_clear(Parse $parse, Data $data){   
   $object = $parse->object();
   $parse = new Parse($object);
   $command = \R3m\Io\Cli\Cache\Controller\Cache::CLEAR_COMMAND;
   foreach($command as $record){
      $execute = $parse->compile($record);
      echo 'Executing: ' . $execute . "...\n";
      $output = [];
      Core::execute($execute, $output);
      $output[] = '';
      echo implode("\n", $output);
      ob_flush();
      if(class_exists('\Doctrine\Common\Cache\ArrayCache')){
          $cacheDriver = new \Doctrine\Common\Cache\ArrayCache();
          $cacheDriver->deleteAll();
      }
   }
}
