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
namespace R3m\Io\Module;

use R3m\Io\Exception\UrlEmptyException;
use stdClass;
use R3m\Io\Exception\ObjectException;
use R3m\Io\App;

class Core {

    const EXCEPTION_MERGE_ARRAY_OBJECT = 'Cannot merge an array with an object.';
    const EXCEPTION_OBJECT_OUTPUT = 'Unknown output in object.';

    const ATTRIBUTE_EXPLODE = [
        '.'
    ];

    const OBJECT_ARRAY = 'array';
    const OBJECT_OBJECT = 'object';
    const OBJECT_JSON = 'json';
    const OBJECT_JSON_DATA = 'json-data';
    const OBJECT_JSON_LINE = 'json-line';

    const OBJECT_TYPE_ROOT = 'root';
    const OBJECT_TYPE_CHILD = 'child';

    const SHELL_DETACHED = 'detached';
    const SHELL_NORMAL = 'normal';
    const SHELL_PROCESS = 'process';

    const OUTPUT_MODE_IMPLICIT = 'implicit';
    const OUTPUT_MODE_EXPLICIT = 'explicit';
    const OUTPUT_MODE_DEFAULT = Core::OUTPUT_MODE_EXPLICIT;

    const OUTPUT_MODE = [
            Core::OUTPUT_MODE_IMPLICIT,
            Core::OUTPUT_MODE_EXPLICIT,
    ];

    const MODE_INTERACTIVE = Core::OUTPUT_MODE_IMPLICIT;
    const MODE_PASSIVE = Core::OUTPUT_MODE_EXPLICIT;

    public static function binary(){
        if(array_key_exists('_', $_SERVER)){
            $dirname = Dir::name($_SERVER['_']);
            return str_replace($dirname, '', $_SERVER['_']);
        }
    }

    public static function detach($command){
        return Core::execute($command, $output, Core::SHELL_DETACHED);
    }

    public static function async($command){
        if(stristr($command, '&') === false){
            $command .= ' &';
        }
        $output = [];
        return Core::execute($command, $output, Core::SHELL_PROCESS);
    }

    public static function execute($command, &$output=[], $type=null){
        if($output === null){
            $output = [];
        }
        $result = [
            'pid' => getmypid()
        ];
        if(
            in_array(
                $type,
                [
                    Core::SHELL_DETACHED,
                    Core::SHELL_PROCESS
                ]
            )
        ){
            $pid = pcntl_fork();
            switch($pid) {
                // fork errror
                case -1 :
                    return false;
                case 0 :
                    //in child process
                    //create a seperate process to execute another process (async);
                    exec($command, $output);
                    if($type != Core::SHELL_PROCESS){
                        // echo implode(PHP_EOL, $output) . PHP_EOL;
                    }
                    $output = [];
                    exit();
                default :
                    if($type == Core::SHELL_PROCESS){
                        pcntl_waitpid(0, $status, WNOHANG);
                        $status = pcntl_wexitstatus($status);
                        $child = [
                            'status' => $status,
                            'pid' => $pid
                        ];
                        $result['child'] = $child;
                        return $result;
                    }
                    //main process (parent)
                    while (pcntl_waitpid(0, $status) != -1) {
                        //add max execution time here / time outs etc..
                        $status = pcntl_wexitstatus($status);
                        $child = [
                            'status' => $status,
                            'pid' => $pid
                        ];
                        $result['child'] = $child;
                    }
            }
            return $result;
        } else {
            return exec($command, $output);
        }
    }

    public static function output_mode($mode = null){
        if(!in_array($mode, Core::OUTPUT_MODE)){
            $mode = Core::OUTPUT_MODE_DEFAULT;
        }
        switch($mode){
            case  Core::MODE_INTERACTIVE :
                ob_implicit_flush(true);
                @ob_end_flush();
                break;
            default :
                ob_implicit_flush(false);
                @ob_end_flush();
        }
    }

    public static function interactive(){
        return Core::output_mode(Core::MODE_INTERACTIVE);
    }

    public static function passive(){
        return Core::output_mode(Core::MODE_PASSIVE);
    }

    public static function redirect($url=''){
        if(empty($url)){
            throw new UrlEmptyException('url is empty...');
        }
        header('Location: ' . $url);
        exit;
    }

    public static function is_array_nested($array=[]){
        $array = (array) $array;
        foreach($array as $value){
            if(is_array($value)){
                return true;
            }
        }
        return false;
    }

    public static function array_object($array=[]){
        $object = new stdClass();
        foreach ($array as $key => $value){
            if(is_array($value)){
                $object->{$key} = Core::array_object($value);
            } else {
                $object->{$key} = $value;
            }
        }
        return $object;
    }

    public static function explode_multi($delimiter=[], $string='', $limit=[]){
        $result = array();
        if(!is_array($limit)){
            $limit = explode(',', $limit);
            $value = reset($limit);
            if(count($delimiter) > count($limit)){
                for($i = count($limit); $i < count($delimiter); $i++){
                    $limit[$i] = $value;
                }
            }
        }
        foreach($delimiter as $nr => $delim){
            if(isset($limit[$nr])){
                $tmp = explode($delim, $string, $limit[$nr]);
            } else {
                $tmp = explode($delim, $string);
            }
            if(count($tmp)==1){
                continue;
            }
            foreach ($tmp as $tmp_value){
                $result[] = $tmp_value;
            }
        }
        if(empty($result)){
            $result[] = $string;
        }
        return $result;
    }

    /**
     * @throws ObjectException
     */
    public static function object($input='', $output=null, $type=null){
        if($output === null){
            $output = Core::OBJECT_OBJECT;
        }
        if($type === null){
            $type = Core::OBJECT_TYPE_ROOT;
        }
        if(is_bool($input)){
            if($output == Core::OBJECT_OBJECT || $output == Core::OBJECT_JSON){
                $data = new stdClass();
                if(empty($input)){
                    $data->false = false;
                } else {
                    $data->true = true;
                }
                if($output == Core::OBJECT_JSON){
                    $data = json_encode($data);
                }
                return $data;
            }
            elseif($output == Core::OBJECT_ARRAY) {
                return array($input);
            } else {
                throw new ObjectException(Core::EXCEPTION_OBJECT_OUTPUT);
            }
        }
        if(is_null($input)){
            if($output == Core::OBJECT_OBJECT){
                return new stdClass();
            }
            elseif($output == Core::OBJECT_ARRAY){
                return array();
            }
            elseif($output == Core::OBJECT_JSON){
                return '{}';
            }
        }
        if(is_array($input) && $output == Core::OBJECT_OBJECT){
            return Core::array_object($input);
        }
        if(is_string($input)){
            $input = trim($input);
            if($output == Core::OBJECT_OBJECT){
                if(substr($input,0,1)=='{' && substr($input,-1,1)=='}'){
                    $json = json_decode($input);
                    if(json_last_error()){
                        throw new ObjectException(json_last_error_msg());
                    }
                    return $json;
                }
                elseif(substr($input,0,1)=='[' && substr($input,-1,1)==']'){
                    $json = json_decode($input);
                    if(json_last_error()){
                        throw new ObjectException(json_last_error_msg());
                    }
                    return $json;
                }
            }
            elseif(stristr($output, Core::OBJECT_JSON) !== false){
                if(substr($input,0,1)=='{' && substr($input,-1,1)=='}'){
                    $input = json_decode($input);
                }
            }
            elseif($output == Core::OBJECT_ARRAY){
                if(substr($input,0,1)=='{' && substr($input,-1,1)=='}'){
                    return json_decode($input, true);
                }
                elseif(substr($input,0,1)=='[' && substr($input,-1,1)==']'){
                    return json_decode($input, true);
                }
            }
        }
        if(stristr($output, Core::OBJECT_JSON) !== false && stristr($output, 'data') !== false){
            $data = str_replace('"', '&quot;',json_encode($input));
        }
        elseif(stristr($output, Core::OBJECT_JSON) !== false && stristr($output, 'line') !== false){
            $data = json_encode($input);
        } else {
            $data = json_encode($input, JSON_PRETTY_PRINT);
        }
        if($output == Core::OBJECT_OBJECT){
            return json_decode($data);
        }
        elseif(stristr($output, Core::OBJECT_JSON) !== false){
            if($type==Core::OBJECT_TYPE_CHILD){
                return substr($data,1,-1);
            } else {
                return $data;
            }
        }
        elseif($output == Core::OBJECT_ARRAY){
            return json_decode($data,true);
        } else {
            throw new ObjectException(Core::EXCEPTION_OBJECT_OUTPUT);
        }
    }

    public static function object_delete($attributeList=[], $object='', $parent='', $key=null){
        if(is_string($attributeList)){
            $attributeList = Core::explode_multi(Core::ATTRIBUTE_EXPLODE, $attributeList);
        }
        if(is_array($attributeList)){
            $attributeList = Core::object_horizontal($attributeList);
        }
        if(!empty($attributeList) && is_object($attributeList)){
            foreach($attributeList as $key => $attribute){
                if(isset($object->{$key})){
                    return Core::object_delete($attribute, $object->{$key}, $object, $key);
                } else {
                    unset($object->{$key}); //to delete nulls
                    return false;
                }
            }
        } else {
            unset($parent->{$key});    //unset $object won't delete it from the first object (parent) given
            return true;
        }
    }

    public static function object_has($attributeList=[], $object=''){
        if(Core::object_is_empty($object)){
            if(empty($attributeList)){
                return true;
            }
            return false;
        }
        if(is_string($attributeList)){
            $attributeList = Core::explode_multi(Core::ATTRIBUTE_EXPLODE, $attributeList);
            foreach($attributeList as $nr => $attribute){
                if(empty($attribute)){
                    unset($attributeList[$nr]);
                }
            }
        }
        if(is_array($attributeList)){
            $attributeList = Core::object_horizontal($attributeList);
        }
        if(empty($attributeList)){
            return true;
        }
        foreach($attributeList as $key => $attribute){
            if(empty($key)){
                continue;
            }
            if(property_exists($object,$key)){
                $get = Core::object_has($attributeList->{$key}, $object->{$key});
                if($get === false){
                    return false;
                }
                return true;
            }
        }
        return false;
    }

    public static function object_get($attributeList=[], $object=''){
        if(Core::object_is_empty($object)){        	
            if(empty($attributeList)){
                return $object;
            }
            if(is_array($object)){
            	foreach($attributeList as $key => $attribute){
            		if(empty($key) && $key != 0){
            			continue;
            		}
            		if(array_key_exists($key, $object)){
            			return Core::object_get($attributeList->{$key}, $object[$key]);
            		}
            	}            	
            }            
            return null;
        }
        if(is_string($attributeList)){
            $attributeList = Core::explode_multi(Core::ATTRIBUTE_EXPLODE, $attributeList);
            foreach($attributeList as $nr => $attribute){
                if(empty($attribute) && $attribute != '0'){
                    unset($attributeList[$nr]);
                }
            }
        }
        if(is_array($attributeList)){
            $attributeList = Core::object_horizontal($attributeList);
        }        
        if(empty($attributeList)){
            return $object;
        }
        foreach($attributeList as $key => $attribute){
            if(empty($key) && $key != 0){
                continue;
            }
            if(isset($object->{$key})){
                return Core::object_get($attributeList->{$key}, $object->{$key});
            }                       
        }
        return null;
    }

    public static function object_merge(){
        $objects = func_get_args();
        $main = array_shift($objects);
        if(empty($main) && !is_array($main)){
            $main = new stdClass();
        }
        foreach($objects as $nr => $object){
            if(is_array($object)){
                foreach($object as $key => $value){
                    if(is_object($main)){
                        throw new ObjectException(Core::EXCEPTION_MERGE_ARRAY_OBJECT);
                    }
                    if(!isset($main[$key])){
                        $main[$key] = $value;
                    } else {
                        if(is_array($value) && is_array($main[$key])){
                            $main[$key] = Core::object_merge($main[$key], $value);
                        } else {
                            $main[$key] = $value;
                        }
                    }
                }
            }
            elseif(is_object($object)){
                foreach($object as $key => $value){
                    if((!isset($main->{$key}))){
                        $main->{$key} = $value;
                    } else {
                        if(is_object($value) && is_object($main->{$key})){
                            $main->{$key} = Core::object_merge(clone $main->{$key}, clone $value);
                        } else {
                            $main->{$key} = $value;
                        }
                    }
                }
            }
        }
        return $main;
    }

    public static function object_set($attributeList=[], $value=null, $object='', $return='child'){
        if(empty($object)){
            return;
        }
        if(is_string($return) && $return != 'child'){
            if($return == 'root'){
                $return = $object;
            } else {
                $return = Core::object_get($return, $object);
            }
        }
        if(is_string($attributeList)){
            $attributeList = Core::explode_multi(Core::ATTRIBUTE_EXPLODE, $attributeList);
        }
        if(is_array($attributeList)){
            $attributeList = Core::object_horizontal($attributeList);
        }
        if(!empty($attributeList)){
            foreach($attributeList as $key => $attribute){
                if(isset($object->{$key}) && is_object($object->{$key})){
                    if(empty($attribute) && is_object($value)){
                        foreach($value as $value_key => $value_value){
                            /*
                            if(isset($object->$key->$value_key)){
                                // unset($object->$key->$value_key);   //so sort will happen, @bug request will take forever and apache2 crashes needs reboot apache2
                            }
                            */
                            $object->{$key}->{$value_key} = $value_value;
                        }
                        return $object->{$key};
                    }
                    return Core::object_set($attribute, $value, $object->{$key}, $return);
                }
                elseif(is_object($attribute)){
                    $object->{$key} = new stdClass();
                    return Core::object_set($attribute, $value, $object->{$key}, $return);
                } else {
                    $object->{$key} = $value;
                }
            }
        }
        if($return == 'child'){
            return $value;
        }
        return $return;
    }

    public static function object_is_empty($object=null){
        if(!is_object($object)){
            return true;
        }
        $is_empty = true;
        foreach ($object as $value){
            $is_empty = false;
            break;
        }
        return $is_empty;
    }

    public static function is_cli(){
        if(isset($_SERVER['HTTP_HOST'])){
            $domain = $_SERVER['HTTP_HOST'];
        }
        elseif(isset($_SERVER['SERVER_NAME'])){
            $domain = $_SERVER['SERVER_NAME'];
        } else {
            $domain = '';
        }
        if(empty($domain)){
            if(!defined('IS_CLI')){
                define('IS_CLI', true);
                return true;
            }
        } else {
            return false;
        }
    }

    public static function object_horizontal($verticalArray=[], $value=null, $return='object'){
        if(empty($verticalArray)){
            return false;
        }
        $object = new stdClass();
        if(is_object($verticalArray)){
            $attributeList = get_object_vars($verticalArray);
            $list = array_keys($attributeList);
            $last = array_pop($list);
            if($value===null){
                $value = $verticalArray->$last;
            }
            $verticalArray = $list;
        } else {
            $last = array_pop($verticalArray);
        }
        if(empty($last) && $last != '0'){
            return false;
        }
        foreach($verticalArray as $attribute){
            if(empty($attribute)){
                continue;
            }
            if(!isset($deep)){
                $object->{$attribute} = new stdClass();
                $deep = $object->{$attribute};
            } else {
                $deep->{$attribute} = new stdClass();
                $deep = $deep->{$attribute};
            }
        }
        if(!isset($deep)){
            $object->$last = $value;
        } else {
            $deep->$last = $value;
        }
        if($return=='array'){
            $json = json_encode($object);
            return json_decode($json,true);
        } else {
            return $object;
        }
    }

    public static function uuid(){
        $data = openssl_random_pseudo_bytes(16);
        assert(strlen($data) == 16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }

    public static function uuid_variable(){
        $uuid = Core::uuid();
        $search = [];
        $search[] = 0;
        $search[] = 1;
        $search[] = 2;
        $search[] = 3;
        $search[] = 4;
        $search[] = 5;
        $search[] = 6;
        $search[] = 7;
        $search[] = 8;
        $search[] = 9;
        $search[] = '-';
        $replace = [];
        $replace[] = 'g';
        $replace[] = 'h';
        $replace[] = 'i';
        $replace[] = 'j';
        $replace[] = 'k';
        $replace[] = 'l';
        $replace[] = 'm';
        $replace[] = 'n';
        $replace[] = 'o';
        $replace[] = 'p';
        $replace[] = '_';
        $variable = '$' . str_replace($search, $replace, $uuid);
        return $variable;
    }

    public static function ucfirst_sentence($string='', $delimiter='.'){
        $explode = explode($delimiter, $string);
        foreach($explode as $nr => $part){
            $explode[$nr] = ucfirst(trim($part));
        }
        return implode($delimiter, $explode);
    }

    public static function cors(){
        header("HTTP/1.1 200 OK");
        header("Access-Control-Allow-Origin: *");
        if (array_key_exists('HTTP_ORIGIN', $_SERVER)) {
            header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
        }
        if (
            array_key_exists('REQUEST_METHOD', $_SERVER) &&
            $_SERVER['REQUEST_METHOD'] == 'OPTIONS'
        ) {
            header('Access-Control-Allow-Credentials: true');
            header('Access-Control-Allow-Methods: GET, POST, PATCH, PUT, DELETE, OPTIONS');
            //header('Access-Control-Allow-Headers: Origin, Content-Type, Authorization');
            header('Access-Control-Allow-Headers: Origin, Cache-Control, Content-Type, Authorization, X-Requested-With');
            header('Access-Control-Max-Age: 86400');    // cache for 1 day
            exit(0);
        }
    }
}