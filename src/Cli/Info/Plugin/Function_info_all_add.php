<?php

use R3m\Io\Module\Parse;
use R3m\Io\Module\Data;
use R3m\Io\Module\Config;
use R3m\Io\Module\Dir;
use R3m\Io\Module\File;

function function_info_all_add(Parse $parse, Data $data, $list){
    $result = [];
    foreach($list as $nr => $record){
        if(
            property_exists($record, 'controller') &&
            property_exists($record, 'function')
        ){
            $class = $record->controller;
            $constant =  $class . '::INFO_' . strtoupper($record->function);
            $info = false;
            if(defined($constant)) {
                $info = constant($constant);
            }
            elseif(defined($class . '::INFO')){
                $info = constant($class . '::INFO');
            }
            $record->info = $info;
            $result[] = $record;
        }
    }
    return $result;
}