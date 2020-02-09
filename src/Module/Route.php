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
use R3m\Io\Config;
use R3m\Io\Module\Handler;
use R3m\Io\Module\File;
use R3m\Io\Module\Core;

class Route extends Data{
    const NAMESPACE = __NAMESPACE__;
    const NAME = 'Route';
    const SELECT = 'Route_select';

    const SELECT_DEFAULT = 'info';

    private $current;
    private $url;
    private $cache_url;

    public function url($url=null){
        if($url !== null){
            $this->url = $url;
        }
        return $this->url;
    }

    public function cache_url($url=null){
        if($url !== null){
            $this->cache_url = $url;
        }
        return $this->cache_url;
    }

    public function current($current=null){
        if($current !== null){
            $this->setCurrent($current);
        }
        return $this->getCurrent();
    }

    private function setCurrent($current=null){
        $this->current = $current;
    }

    private function getCurrent(){
        return $this->current;
    }

    public static function get($object, $name='', $option=[]){
        $route = $object->data(App::ROUTE);
        $get = $route->data($name);
//         d($get);
        if(empty($get)){
            return;
        }
        $path = $get->path;
        foreach($option as $key => $value){
            if(is_numeric($key)){
                $explode = explode('}', $get->path, 2);
                $temp = explode('{$', $explode[0], 2);
                if(array_key_exists(1, $temp)){
                    $variable = $temp[1];
                    $path = str_replace('{$' . $variable . '}', $value, $path);
                }
            } else {
                $path = str_replace('{$' . $key . '}', $value, $path);
            }
        }
        if($path == '/'){
            $url = $object->data('host.url');
        } else {
            $url = $object->data('host.url') . $path;
        }
        return $url;
    }

    private static function input_request($object, $input, $glue='/'){
        $request = [];
        foreach($input as $key => $value){
            $request[] = $value;
        }
        $input->request = implode($glue, $request);
        if(substr($input->request, -1, 1) != $glue){
            $input->request .= $glue;
        }
        return $input;
    }

    private static function add_request($object, $request){
        if(empty($request)){
            return $object;
        }
        $object->data(App::REQUEST)->data(Core::object_merge(
                $object->data(App::REQUEST)->data(),
                $request->request->data()
        ));
        return $object;
    }

    private static function select_info($object, $record){
        $select = new stdClass();
        $select->parameter = new stdClass();
        $select->attribute = [];
        $select->method = Handler::method();
        $select->host = [];
        $select->attribute[] = Route::SELECT_DEFAULT;
        $key = 0;
        $select->parameter->{$key} =  Route::SELECT_DEFAULT;
        foreach($record->parameter as $key => $value){
            $select->parameter->{$key + 1} = $value;
        }
        return $select;
    }

    public static function request($object){
        if(defined('IS_CLI')){
            $input = Route::input($object);
            $select = new stdClass();
            $select->parameter = $input->data();
            $key = 0;
            $select->attribute = [];
            if(property_exists($select->parameter, $key)){
                $select->attribute[] = $select->parameter->{$key};
            } else {
                $select->attribute[] = '';
            }
            $select->method = Handler::method();
            $select->host = [];
            $request = Route::select_cli($object, $select);

            if($request === false){
                $select = Route::select_info($object, $select);
                $request = Route::select_cli($object, $select);
            }
            if($request === false){
                throw new Exception('Exception in request');
            }
            $request->request->data(Core::object_merge(clone $select->parameter, $request->request->data()));

            /*
            if(property_exists($request, 'request') && is_object($request->request)){
                $request->request = Core::object_merge(clone $select->parameter, $request->request);
            } else {
                $request->request = $select->parameter;
            }
            */
            $route =  $object->data(App::ROUTE);
            $object = Route::add_request($object, $request);
            return $route->current($request);
        } else {
            $input = Route::input($object);
            $select = new stdClass();
            $select->input = $input;
            $select->deep = substr_count($input->data('request'), '/');
            $select->attribute = explode('/', $input->data('request'));
            array_pop($select->attribute);
            $select->method = Handler::method();
            $select->host = [];

            $subdomain = Host::subdomain();
            if($subdomain){
                $select->host[] = $subdomain . '.' . Host::domain() . '.' . Host::extension();
            } else {
                $select->host[] = Host::domain() . '.' . Host::extension();
            }
            $select->host = array_unique($select->host);
            $request = Route::select($object, $select);

            $route =  $object->data(App::ROUTE);
            $object = Route::add_request($object, $request);
            return $route->current($request);
        }
    }

    public static function input($object){
        $input = $object->data(App::REQUEST);
        return $input;
    }

    private static function select_cli($object, $select){
        $route =  $object->data(App::ROUTE);
        if(empty($route)){
            return false;
        }
        $match = false;
        $data = $route->data();
        if(Core::object_is_empty($data)){
            return false;
        }
        if(!is_object($data)){
            return false;
        }
        $current = false;
        foreach($data as $record){
            if(property_exists($record, 'resource')){
                continue;
            }
            $match = Route::is_match_cli($object, $record, $select);
            if($match === true){
                $current = $record;
                break;
            }
        }
        if($current !== false){
            $current = Route::prepare($object, $current, $select);
            $current->parameter = $select->parameter;
            return $current;
        }
        return false;
    }


    private static function select($object, $select){
        $route =  $object->data(App::ROUTE);
        $match = false;
        $data = $route->data();
        if(empty($data)){
            return $select;
        }
        if(!is_object($data)){
            return $select;
        }
        $current = false;
        foreach($data as $record){
            if(property_exists($record, 'resource')){
                continue;
            }
            if(!property_exists($record, 'deep')){
                continue;
            }
            $match = Route::is_match($object, $record, $select);
            if($match === true){
                $current = $record;
                break;
            }
        }
        if($current !== false){
            $current = Route::prepare($object, $current, $select);
            return $current;
        }
        return false;
    }

    private static function add_localhost($object, $route){
        if(!property_exists($route, 'host')){
            return $route;
        }
        $allowed_host = [];
        $disallowed_host = [];
        foreach($route->host as $host){
            if(substr($host, 0, 1) == '!'){
                $disallowed_host[] = $host;
                continue;
            }
            $allowed_host[] = $host;
        }

        $config =  $object->data(App::CONFIG);
        $localdomain = $config->data(Config::LOCALHOST_EXTENSION);

        $allowed_host_new = [];
        $disallowed_host_new = [];

        if(is_array($localdomain)){
            foreach($allowed_host as $host){
                $allowed_host_new[] = $host;
                $explode = explode('.', $host);
                array_pop($explode);
                $prefix = implode('.', $explode);
                foreach($localdomain as $extension){
                    $allowed_host_new[] = $prefix . '.' . $extension;
                }
            }
            foreach($disallowed_host as $host){
                $disallowed_host_new[] = $host;
                $explode = explode('.', $host);
                array_pop($explode);
                $prefix = implode('.', $explode);
                foreach($localdomain as $extension){
                    $disallowed_host_new[] = $prefix . '.' . $extension;
                }
            }
            $route->host = array_merge($allowed_host_new, $disallowed_host_new);
        }
        return $route;
    }

    private static function is_variable($string){
        $string = trim($string);
        if(
            substr($string, 0, 2) == '{$' &&
            substr($string, -1) == '}'
        ){
            return true;
        }
        return false;
    }

    private static function get_variable($string){
        $string = trim($string);
        if(
            substr($string, 0, 2) == '{$' &&
            substr($string, -1) == '}'
        ){
            return substr($string, 2, -1);
        }
    }

    private static function prepare($object, $route, $select){
        $explode = explode('/', $route->path);
        array_pop($explode);
        $attribute = $select->attribute;

        if(property_exists($route, 'request')){
            $route->request = new Data($route->request);
        } else {
            $route->request = new Data();
        }
        foreach($explode as $nr => $part){
            if(Route::is_variable($part)){
                $variable = Route::get_variable($part);
                if(property_exists($route->request, $variable)){
                    continue;
                }
                if(array_key_exists($nr, $attribute)){
                    $route->request->data($variable, $attribute[$nr]);
                }
            }
        }
        foreach($object->data(App::REQUEST) as $key => $record){
            if($key == 'request'){
                continue;
            }
            $route->request->data($key, $record);
        }
        $controller = explode('.', $route->controller);
        $function = array_pop($controller);
        $route->controller = implode('\\', $controller);
        $route->function = $function;
        return $route;
    }

    private static function is_match_by_attribute($object, $route, $select){
        $explode = explode('/', $route->path);
        array_pop($explode);
        $attribute = $select->attribute;
        if(empty($attribute)){
            return true;
        }
        foreach($explode as $nr => $part){
            if(Route::is_variable($part)){
                continue;
            }
            if(array_key_exists($nr, $attribute) === false){
                return false;
            }
            if(strtolower($part) != strtolower($attribute[$nr])){
                return false;
            }
        }
        return true;
    }

    private static function is_match_by_method($object, $route, $select){
        if(!property_exists($route, 'method')){
            return false;
        }
        if(!is_array($route->method)){
            return false;
        }
        foreach($route->method as $method){
            if(strtoupper($method) == strtoupper($select->method)){
                return true;
            }
        }
        return false;
    }

    private static function is_match_by_host($object, $route, $select){
        if(!property_exists($route, 'host')){
            return true;
        }
        if(!is_array($route->host)){
            return false;
        }
        $allowed_host = [];
        $disallowed_host = [];
        foreach($select->host as $host){
            $host = strtolower($host);
            if(substr($host, 0, 1) == '!'){
                $disallowed_host[] = substr($host, 1);
                continue;
            }
            $allowed_host[] = $host;
        }
        foreach($route->host as $host){
            if(in_array($host, $disallowed_host)){
                return false;
            }
            if(in_array($host, $allowed_host)){
                return true;
            }
        }
        return false;
    }

    private function is_match_by_deep($object, $route, $select){
        if(!property_exists($route, 'deep')){
            return false;
        }
        if(!property_exists($select, 'deep')){
            return false;
        }
        if($route->deep != $select->deep){
            return false;
        }
        return true;
    }

    private static function is_match_cli($object, $route, $select){
        $is_match = Route::is_match_by_attribute($object, $route, $select);
        if($is_match === false){
            return $is_match;
        }
        $is_match = Route::is_match_by_method($object, $route, $select);
        if($is_match === false){
            return $is_match;
        }
        return $is_match;
    }

    private static function is_match($object, $route, $select){
        $is_match = Route::is_match_by_deep($object, $route, $select);
        if($is_match === false){
            return $is_match;
        }
        $route = Route::add_localhost($object, $route);
        $is_match = Route::is_match_by_host($object, $route, $select);
        if($is_match === false){
            return $is_match;
        }
        $is_match = Route::is_match_by_attribute($object, $route, $select);
        if($is_match === false){
            return $is_match;
        }
        $is_match = Route::is_match_by_method($object, $route, $select);
        if($is_match === false){
            return $is_match;
        }
        return $is_match;
    }

    public static function configure($object){
        $config = $object->data(App::CONFIG);

        $url = $config->data(Config::DATA_PROJECT_DIR_DATA) . $config->data(Config::DATA_PROJECT_ROUTE_FILENAME);
        if(empty($config->data(Config::DATA_PROJECT_ROUTE_URL))){
            $config->data(Config::DATA_PROJECT_ROUTE_URL, $url);
        }
        $url = $config->data(Config::DATA_PROJECT_ROUTE_URL);
        $cache_url = $config->data(Config::DATA_PROJECT_DIR_DATA) . 'Cache' . $config->data('ds') . $config->data(Config::DATA_PROJECT_ROUTE_FILENAME);
        $cache = Route::cache_read($object, $url, $cache_url);
        $cache = Route::cache_invalidate($object, $cache);

        if(empty($cache)){
            if(File::exist($url)){
                $read = File::read($url);
                $data = new Route(Core::object($read));
                $data->url($url);
                $data->cache_url($cache_url);
                $object->data(App::ROUTE, $data);
                Route::load($object);
                Route::framework($object);
                Route::cache_write($object);
            }
        } else {
            $object->data(App::ROUTE, $cache);
        }
    }

    private static function cache_mtime($object, $cache){
        $time = strtotime(date('Y-m-d H:i:00'));
        if(File::mtime($cache->cache_url()) != $time){
            return File::touch($cache->cache_url(), $time, $time);
        }
    }

    private static function cache_invalidate($object, $cache){
        $has_resource = false;
        $invalidate = true;

        if(empty($cache)){
            return;
        }
        $time = strtotime(date('Y-m-d H:i:00'));
        if(
            File::exist($cache->cache_url()) &&
            $time == File::mtime($cache->cache_url())
        ){
            return $cache;
        }
        $data = $cache->data();
        foreach($data as $record){
            if(property_exists($record, 'resource')){
                $has_resource = true;
                if(!File::exist($record->resource)){
                    break;
                }
                if(!property_exists($record, 'mtime')){
                    break;
                }
                if(File::mtime($record->resource) != $record->mtime){
                    break;
                }
                continue;
            }
            $invalidate = false;
            break;
        }
        if(
            $invalidate &&
            $has_resource
        ){
            $cache_url = $cache->cache_url();
            if(File::exist($cache_url)){
                File::delete($cache_url);
            }
            return false;
        }
        elseif($has_resource === false) {
            $cache_url = $cache->cache_url();
            File::delete($cache_url);
            return false;
        } else {
            Route::cache_mtime($object, $cache);
            return $cache;
        }
    }

    private static function cache_read($object, $url, $cache_url){
        if(File::Exist($cache_url)){
            $read = File::read($cache_url);
            $data = new Route(Core::object($read));
            $data->url($url);
            $data->cache_url($cache_url);
            return $data;
        }
    }

    private static function cache_write($object){
        if (posix_getuid() === 0){
            //don't write cache file as root, otherways it will be inaccessible
            return false;
        }
        $config = $object->data(App::CONFIG);
        $route = $object->data(App::ROUTE);
        $data = $route->data();
        $result = new Data();
        $url = $route->url();
        $cache_url = $route->cache_url();
        $cache_dir = Dir::name($cache_url);

        $main = new stdClass();
        $main->resource = $url;
        $main->read = true;
        $main->mtime = File::mtime($url);

        $result->data(Core::uuid(), $main);
        foreach($data as $key => $record){
            if(property_exists($record, 'resource') === false){
                continue;
            }
            $result->data($key, $record);
        }
        foreach($data as $key => $record){
            if(property_exists($record, 'resource')){
                continue;
            }
            $result->data($key, $record);
        }
        $write = Core::object($result->data(), Core::OBJECT_JSON);
        Dir::create($cache_dir, Dir::CHMOD);
        $byte =  File::write($cache_url, $write);
        $time = strtotime(date('Y-m-d H:i:00'));
        $touch = File::touch($cache_url, $time, $time);
        return $byte;
    }

    private function item_path($object, $item){
        if(!property_exists($item, 'path')){
            return $item;
        }
        if(substr($item->path, 0, 1) == '/'){
            $item->path = substr($item->path, 1);
        }
        if(substr($item->path, -1) !== '/'){
            $item->path .= '/';
        }
        return $item;

    }

    private function item_deep($object, $item){
        $item->deep = substr_count($item->path, '/');
        return $item;
    }

    public static function load($object){
        $reload = false;
        $route = $object->data(App::ROUTE);
        if(empty($route)){
            return;
        }
        $data = $route->data();
        if(empty($data)){
            return;
        }
        foreach($data as $item){
            if(!is_object($item)){
                continue;
            }
            if(!property_exists($item, 'resource')){
                $item = Route::item_path($object, $item);
                $item = Route::item_deep($object, $item);
                continue;
            }
            if(property_exists($item, 'read')){
                continue;
            }
            $item->resource = Route::parse($object, $item->resource);
            if(File::exist($item->resource)){
                $read = File::read($item->resource);
                $resource = Core::object($read);
                if(Core::object_is_empty($resource)){
                    throw new Exception('Could not read route file (' . $item->resource .')');
                }
                foreach($resource as $resource_key => $resource_item){
                    $check = $route->data($resource_key);
                    if(empty($check)){
                        $route->data($resource_key, $resource_item);
                    }
                }
                $reload = true;
                $item->read = true;
                $item->mtime = File::mtime($item->resource);
            } else {
                $item->read = false;
            }
        }
        if($reload === true){
            Route::load($object);
        }
    }

    private static function framework($object){
        $config = $object->data(App::CONFIG);
        $route = $object->data(App::ROUTE);
        $default_route = $config->data('framework.default.route');
        foreach($default_route as $record){
            $path = strtolower($record);
            $control = ucfirst($path);
            $attribute = 'r3m-io-cli-' . $path;
            $item = new stdClass();
            $item->path = $path . '/';
            $item->controller = 'R3m.Io.Cli.' . $control . '.Controller.' . $control . '.run';
            $item->language = 'en';
            $item->method = [
                "CLI"
            ];
            $item->deep = 1;
            $route->data($attribute, $item);
        }
    }

    public static function parse($object, $resource){
        $explode = explode('}', $resource, 2);
        if(!isset($explode[1])){
            return $resource;
        }
        $temp = explode('{', $explode[0], 2);
        if(isset($temp[1])){
            $attribute = substr($temp[1], 1);
            $config = $object->data(App::CONFIG);
            $value = $config->data($attribute);
            $resource = str_replace('{$' . $attribute . '}', $value, $resource);
            return Route::parse($object, $resource);
        } else {
            return $resource;
        }
    }

}