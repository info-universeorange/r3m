<?php
/**
 *  (c) 2019 Priya.software
 *
 *  License: MIT
 *
 *  Author: Remco van der Velde
 *  Version: 1.0
 */

namespace R3m\Io\Module;


use stdClass;
use Exception;
use R3m\Io\App;
use R3m\Io\Module\Core;

class Handler {
    const NAMESPACE = __NAMESPACE__;
    const NAME = 'Handler';
    const NAME_SESSION = 'Session';
    const NAME_REQUEST = 'Request';
    const NAME_COOKIE = 'Cookie';

    const NAME_HEADER = 'Header';
    const NAME_INPUT = 'Input';
    const NAME_FILE = 'File';

    const SESSION = 'session';
    const SESSION_HAS = 'has';
    const SESSION_START = 'start';
    const SESSION_CLOSE = 'close';
    const SESSION_DELETE = 'delete';

    const REQUEST = 'request';
    const REQUEST_HEADER = 'request.header';
    const REQUEST_INPUT = 'request.input';
    const REQUEST_FILE = 'request.file';

    const COOKIE_DELETE = 'delete';

    const METHOD_CLI = 'CLI';

    public static function request_configure($object){
        $object->data(
            App::NAMESPACE . '.' .
            Handler::NAME_REQUEST . '.' .
            Handler::NAME_HEADER,
            Handler::request_header()
        );
        $object->data(
            App::NAMESPACE . '.' .
            Handler::NAME_REQUEST . '.' .
            Handler::NAME_INPUT,
            Handler::request_input()
        );
        $object->data(
            App::NAMESPACE . '.' .
            Handler::NAME_REQUEST . '.' .
            Handler::NAME_FILE,
            Handler::request_file()
        );
    }

    private static function request_header(){
        //check if cli

        if(defined('IS_CLI')){
            return Core::array_object($_SERVER);
        } else {
            //In Cli mode apache functions aren't defined
            return Core::array_object(apache_request_headers());
        }
    }

    public static function response_header($string='', $http_response_code=null, $replace=true){
        if(empty($string)){
            return headers_list();
        }
        if(
            $string == 'delete' &&
            $http_response_code !== null &&
            is_string($http_response_code)
        ){
            header_remove($http_response_code);
        }
        elseif($http_response_code !== null){
            header($string, $replace, $http_response_code);
        } else {
            header($string, $replace);
        }
    }

    private static function request_file(){
        $nodeList = array();
        if(isset($_FILES)){
            foreach ($_FILES as $category => $list){
                if(is_array($list)){
                    foreach($list as $attribute => $subList){
                        if(is_array($subList)){
                            foreach ($subList as $nr => $value){
                                $nodeList[$nr][$attribute] = $value;
                            }
                            $nodeList[$nr]['input_name'] = $category;
                        } else {
                            $list['input_name'] = $category;
                            $nodeList[] = $list;
                            break;
                        }
                    }
                }
            }
        }
        return Core::array_object($nodeList);
    }

    private static function request_input(){
        $data = new Data();
        if(defined('IS_CLI')){
            global $argc, $argv;
            $temp = $argv;
            array_shift($temp);
            $request = $temp;
            $request = Core::array_object($request);
            foreach($request as $key => $value){
//                 $request->{$key} = trim($value);
                $data->data($key, trim($value));
            }
        } else {
            $request = $_REQUEST;
            $request = Handler::request_key_group($request);
            if(property_exists($request, 'request')){
                if(substr($request->request, -1) != '/'){
                    $request->request .= '/';
                }
            } else {
                $request->request = '/';
            }
            $data->data('request', $request->request);
            $input =
                htmlspecialchars(
                    htmlspecialchars_decode(
                        implode(
                            '',
                            file('php://input')
                        ),
                        ENT_NOQUOTES
                    ),
                    ENT_NOQUOTES,
                    'UTF-8'
                );
            if(!empty($input)){
                $input = json_decode($input);
            }
            if(!empty($input)){
//                 dd($input);
                foreach($input as $key => $record){
                    if(
                        is_object($record) &&
                        property_exists($record, 'name') &&
                        property_exists($record, 'value') &&
                        $record->name != 'request'
                    ){
//                         $request->{$record->name} = $record->value;
                        if($record->value !== null){
                            $data->data($record->name, $record->value);
                        }
                    } else {
//                         d($key);
//                         $request->{$key} = $record;
                        if($record !== null){
                            $data->data($key, $record);
                        }
                    }
                }
            }
        }

//         $data->data($request);
        return $data;
//         return $request;
    }

    private static function request_key_group($data){
        $result = new stdClass();
        foreach($data as $key => $value){
            $explode = explode('_', $key, 4);
            if(!isset($explode[1])){
                $result->{$key} = $value;
                continue;
            }
            $temp = Core::object_horizontal($explode, $value);
            $result = Core::object_merge($result, $temp);
        }
        return $result;
    }

    public static function method(){
        if(array_key_exists('REQUEST_METHOD', $_SERVER)){
            $method = $_SERVER['REQUEST_METHOD'];
            return $method;
        }
        elseif(defined('IS_CLI')){
            return Handler::METHOD_CLI;
        }
        throw new Exception('Method undefined');
    }

    public static function session($attribute=null, $value=null){
        if($attribute == Handler::SESSION_HAS){
            return isset($_SESSION);
        }
        elseif($attribute == Handler::SESSION_CLOSE){
            session_write_close();
            return;
        }
        if(!isset($_SESSION)){
            session_start();
            $_SESSION['id'] = session_id();
            if(empty($_SESSION['csrf'])){
                $_SESSION['csrf'] =
                rand(1000,9999) . '-' .
                rand(1000,9999) . '-' .
                rand(1000,9999) . '-' .
                rand(1000,9999)
                ;
            }
        }
        if($attribute !== null){
            $tmp = explode('.', $attribute);
            if($value !== null){
                if($attribute == Handler::SESSION_DELETE && $value == Handler::SESSION){
                    $unset = session_unset();
                    if($unset === false){
                        throw new Exception('Could not unset session');
                    }
                    $destroy = session_destroy();
                    if($destroy === false){
                        throw new Exception('Could not destroy session');
                    }
                }
                elseif($attribute == Handler::SESSION_DELETE){
                    $tmp = explode('.', $value);
                    switch(count($tmp)){
                        case 1 :
                            unset(
                            $_SESSION
                            [$value]
                            );
                            break;
                        case 2 :
                            unset(
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            );
                            break;
                        case 3 :
                            unset(
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            );
                            break;
                        case 4 :
                            unset(
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            );
                            break;
                        case 5 :
                            unset(
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            [$tmp[4]]
                            );
                            break;
                        case 6 :
                            unset(
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            [$tmp[4]]
                            [$tmp[5]]
                            );
                            break;
                        case 7 :
                            unset(
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            [$tmp[4]]
                            [$tmp[5]]
                            [$tmp[6]]
                            );
                            break;
                        case 8 :
                            unset(
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            [$tmp[4]]
                            [$tmp[5]]
                            [$tmp[6]]
                            [$tmp[7]]
                            );
                            break;
                        case 9 :
                            unset(
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            [$tmp[4]]
                            [$tmp[5]]
                            [$tmp[6]]
                            [$tmp[7]]
                            [$tmp[8]]
                            );
                            break;
                        case 10 :
                            unset(
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            [$tmp[4]]
                            [$tmp[5]]
                            [$tmp[6]]
                            [$tmp[7]]
                            [$tmp[8]]
                            [$tmp[9]]
                            );
                            break;
                    }
                    return true;
                } else {
                    switch(count($tmp)){
                        case 1 :
                            $_SESSION
                            [$attribute] = $value;
                            break;
                        case 2 :
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]] = $value;
                            break;
                        case 3 :
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]] = $value;
                            break;
                        case 4 :
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]] = $value;
                            break;
                        case 5 :
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            [$tmp[4]] = $value;
                            break;
                        case 6 :
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            [$tmp[4]]
                            [$tmp[5]] = $value;
                            break;
                        case 7 :
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            [$tmp[4]]
                            [$tmp[5]]
                            [$tmp[6]] = $value;
                            break;
                        case 8 :
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            [$tmp[4]]
                            [$tmp[5]]
                            [$tmp[6]]
                            [$tmp[7]] = $value;
                            break;
                        case 9 :
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            [$tmp[4]]
                            [$tmp[5]]
                            [$tmp[6]]
                            [$tmp[7]]
                            [$tmp[8]] = $value;
                            break;
                        case 10 :
                            $_SESSION
                            [$tmp[0]]
                            [$tmp[1]]
                            [$tmp[2]]
                            [$tmp[3]]
                            [$tmp[4]]
                            [$tmp[5]]
                            [$tmp[6]]
                            [$tmp[7]]
                            [$tmp[8]]
                            [$tmp[9]] = $value;
                            break;
                    }
                }
            }
            switch(count($tmp)){
                case 1 :
                    if(isset($_SESSION[$attribute])){
                        return $_SESSION[$attribute];
                    } else {
                        return null;
                    }
                    break;
                case 2 :
                    if(
                    isset($_SESSION[$tmp[0]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]])
                    ){
                        return $_SESSION[$tmp[0]][$tmp[1]];
                    } else {
                        return null;
                    }
                    break;
                case 3 :
                    if(
                    isset($_SESSION[$tmp[0]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]])
                    ){
                        return $_SESSION[$tmp[0]][$tmp[1]][$tmp[2]];
                    } else {
                        return null;
                    }
                    break;
                case 4 :
                    if(
                    isset($_SESSION[$tmp[0]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]])
                    ){
                        return $_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]];
                    } else {
                        return null;
                    }
                    break;
                case 5 :
                    if(
                    isset($_SESSION[$tmp[0]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]])
                    ){
                        return $_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]];
                    } else {
                        return null;
                    }
                    break;
                case 6 :
                    if(
                    isset($_SESSION[$tmp[0]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]])
                    ){
                        return $_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]];
                    } else {
                        return null;
                    }
                    break;
                case 7 :
                    if(
                    isset($_SESSION[$tmp[0]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]])
                    ){
                        return $_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]];
                    } else {
                        return null;
                    }
                    break;
                case 8 :
                    if(
                    isset($_SESSION[$tmp[0]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]][$tmp[7]])
                    ){
                        return $_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]][$tmp[7]];
                    } else {
                        return null;
                    }
                    break;
                case 9 :
                    if(
                    isset($_SESSION[$tmp[0]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]][$tmp[7]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]][$tmp[7]][$tmp[8]])
                    ){
                        return $_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]][$tmp[7]][$tmp[8]];
                    } else {
                        return null;
                    }
                    break;
                case 10 :
                    if(
                    isset($_SESSION[$tmp[0]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]][$tmp[7]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]][$tmp[7]][$tmp[8]]) &&
                    isset($_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]][$tmp[7]][$tmp[8]][$tmp[9]])
                    ){
                        return $_SESSION[$tmp[0]][$tmp[1]][$tmp[2]][$tmp[3]][$tmp[4]][$tmp[5]][$tmp[6]][$tmp[7]][$tmp[8]][$tmp[9]];
                    } else {
                        return null;
                    }
                    break;
            }
        } else {
            return $_SESSION;
        }
    }

    public static function cookie($attribute=null, $value=null, $duration=null){
        if($attribute !== null){
            if($value !== null){
                if($attribute == Handler::COOKIE_DELETE){
                    $result = @setcookie($value, null, 0, "/"); //ends at session
                    if(!empty($result) && defined('IS_CLI')){
                        unset($_COOKIE[$value]);
                    }
                    return $result;
                } else {
                    if($duration === null){
                        $duration = 60*60*24*365*2; // 2 years
                    }
                    $result = @setcookie($attribute, $value, time() + $duration, "/");
                    if(!empty($result) && defined('IS_CLI')){
                        $_COOKIE[$attribute] = $value;
                    }
                }
                if(isset($_COOKIE[$attribute])){
                    return $_COOKIE[$attribute];
                } else {
                    return null;
                }
            } else {
                if(isset($_COOKIE[$attribute])){
                    return $_COOKIE[$attribute];
                } else {
                    return null;
                }
            }
        }
        return $_COOKIE;
    }
}