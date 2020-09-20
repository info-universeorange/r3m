<?php
/**
 * @author         Remco van der Velde
 * @since         19-07-2015
 * @version        1.0
 * @changeLog
 *  -    all
 */

namespace R3m\Io\Module\Parse;

use Exception;
use R3m\Io\Module\Data;
use R3m\Io\Module\Core;

class Variable {

    public static function assign($build, $token=[], Data $storage){
        $variable = array_shift($token);
        if(!array_key_exists('variable', $variable)){
            return '';
        }
        switch($variable['variable']['operator']){
            case '=' :
                $assign = '$this->storage()->data(\'';
                $assign .= $variable['variable']['attribute'] . '\', ';
                $value = Variable::getValue($build, $token, $storage, true);
                if(stristr($value, '"') && stristr($value, '\'') !== false){
//                     d($value);
                }
//                 d($value);
                if(empty($value)){
                    dd($assign);
                } else {
                    $assign .= $value . ')';
                }

                return $assign;
            break;
            case '+=' :
                // use $this->assign_plus_equal()

                $assign = '$this->storage()->data(\'';
                $assign .= $variable['variable']['attribute'] . '\', ';
                $assign .= '$this->assign_plus_equal(' ;
                $assign .= '$this->storage()->data(\'';
                $assign .= $variable['variable']['attribute'] . '\'), ';
                $value = Variable::getValue($build, $token, $storage, true);
                $assign .= $value . '))';
                return $assign;
            break;
            case '-=' :
                // use $this->assign_plus_equal()

                $assign = '$this->storage()->data(\'';
                $assign .= $variable['variable']['attribute'] . '\', ';
                $assign .= '$this->assign_min_equal(' ;
                $assign .= '$this->storage()->data(\'';
                $assign .= $variable['variable']['attribute'] . '\'), ';
                $value = Variable::getValue($build, $token, $storage, true);
                $assign .= $value . '))';
                return $assign;
            break;
            case '.=' :
                // use $this->assign_plus_equal()

                $assign = '$this->storage()->data(\'';
                $assign .= $variable['variable']['attribute'] . '\', ';
                $assign .= '$this->assign_dot_equal(' ;
                $assign .= '$this->storage()->data(\'';
                $assign .= $variable['variable']['attribute'] . '\'), ';
                $value = Variable::getValue($build, $token, $storage, true);
                $assign .= $value . '))';
                return $assign;
            break;
            case '++' :
                // use $this->assign_plus_equal()

                $assign = '$this->storage()->data(\'';
                $assign .= $variable['variable']['attribute'] . '\', ';
                $assign .= '$this->assign_plus_plus(' ;
                $assign .= '$this->storage()->data(\'';
                $assign .= $variable['variable']['attribute'] . '\')';
                $assign .= '))';
                return $assign;
            break;
            case '--' :
                // use $this->assign_plus_equal()

                $assign = '$this->storage()->data(\'';
                $assign .= $variable['variable']['attribute'] . '\', ';
                $assign .= '$this->assign_min_min(' ;
                $assign .= '$this->storage()->data(\'';
                $assign .= $variable['variable']['attribute'] . '\')';
                $assign .= '))';
                return $assign;
            break;
            default: throw new Exception('Variable operator not defined');

        }
    }

    public static function define($build, $token=[], Data $storage){
        $variable = array_shift($token);
        if(!array_key_exists('variable', $variable)){
            return '';
        }


        $define = '$this->storage()->data(\'' . $variable['variable']['attribute'] . '\')';
        $define_modifier = '';

//         $define_placeholder = 'R3M-IO-' . Core::uuid();

        if(
            array_key_exists('has_modifier', $variable['variable']) &&
            $variable['variable']['has_modifier'] === true
        ){
            foreach($variable['variable']['modifier'] as $nr => $modifier_list){
                foreach($modifier_list as $modifier_nr => $modifier){
                    $define_modifier .= '$this->' . $modifier['php_name'] . '($this->parse(), $this->storage(), ' . $define . ', ';
                    if($modifier['has_attribute']){
                        foreach($modifier['attribute'] as $attribute){
                            switch($attribute['type']){
                                case Token::TYPE_METHOD :
                                    dd($attribute);
                                break;
                                case TOKEN::TYPE_VARIABLE:
                                    $temp = [];
                                    $temp[] = $attribute;
                                    $define_modifier .= Variable::define($build, $temp, $storage) . ', ';
                                break;
                                default :
                                    $define_modifier .= Value::get($attribute) . ', ';
                            }
                        }
                    }
                    $define_modifier = substr($define_modifier, 0, -2) . ')';
                    $define = $define_modifier;
                    $define_modifier = '';
                }
            }
        }
        return $define;
    }

    public static function getValue($build, $token=[], Data $storage, $is_assign=false){


        $set_max = 1024;
        $set_counter = 0;
        $operator_max = 1024;
        $operator_counter = 0;
        $set = null;
//         d($token);
        //create new token for token + sets;
        while(Set::has($token)){
            $set = Set::get($token);
            while(Operator::has($set)){
                $statement = Operator::get($set);
                $set = Operator::remove($set, $statement);
                $statement = Operator::create($statement);
                $key = key($statement);
                $set[$key]['value'] = $statement[$key];
                $set[$key]['type'] = Token::TYPE_CODE;
                unset($set[$key]['execute']);
                unset($set[$key]['is_executed']);
                $token[$key] = $set[$key];
                $operator_counter++;
                if($operator_counter > $operator_max){
                    break;
                }
            }
            $target = Set::target($token);
            $token = Set::remove($token);
            $token = Set::replace($token, $set, $target);
            $set_counter++;
            if($set_counter > $set_max){
                break;
            }
        }
        $operator = $token;
        while(Operator::has($operator)){
            $statement = Operator::get($operator);
//             d($operator);
//             $debug = debug_backtrace(true);
//             d($debug[0]);
            $operator = Operator::remove($operator, $statement);
            $statement = Operator::create($statement);

            if(empty($statement)){
                throw new Exception('Operator error');
            }

            $key = key($statement);
            $operator[$key]['value'] = $statement[$key];
            $operator[$key]['type'] = Token::TYPE_CODE;
            unset($operator[$key]['execute']);
            unset($operator[$key]['is_executed']);
            $operator_counter++;
            if($operator_counter > $operator_max){
                break;
            }
        }
        $operator_counter = 0;
        $result = '';
        while(count($operator) >= 1){
//             d($operator);
            $record = array_shift($operator);
            $record = Method::get($build, $record, $storage, $is_assign);
//             d($record);
//             d($record['method']['attribute']);
//             $record['is_attribute'] = false;
            $result .= Value::get($record, $is_assign);
//             d($record);
//             d($result);
            /**
             * below breaks a lot, foreach, but also route.get so disabled
             */
//             d($record);
            if(
                in_array(
                    $record['type'],
                    [
                        Token::TYPE_STRING ,
                        Token::TYPE_QUOTE_DOUBLE_STRING,
//                         Token::TYPE_VARIABLE

                    ]
                ) &&
                empty($record['is_foreach'])
            ){

                $result .= ' . ';
            }

            $operator_counter++;
            if($operator_counter > $operator_max){
                break;
            }
        }
        //this too see below breaks a lot, foreach, but also route.get so disabled

        if(
            in_array(
                $record['type'],
                [
                    Token::TYPE_STRING ,
                    Token::TYPE_QUOTE_DOUBLE_STRING,
//                     Token::TYPE_VARIABLE

                ]
                )
            ){
                $result = substr($result,0, -3);
        }
// d($result);
        return $result;
    }

}