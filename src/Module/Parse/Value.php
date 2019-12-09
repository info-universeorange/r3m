<?php
/**
 * @author         Remco van der Velde
 * @since         19-07-2015
 * @version        1.0
 * @changeLog
 *  -    all
 */

namespace R3m\Io\Module\Parse;

use R3m\Io\Module\Data;
use Exception;

class Value {
    public const TYPE_CAST_BOOLEAN = 'bool';
    public const TYPE_CAST_INT = 'int';
    public const TYPE_CAST_FLOAT = 'float';
    public const TYPE_CAST_STRING = 'string';


    public static function get($record=[]){
        switch($record['type']){
            case Token::TYPE_INT :
            case Token::TYPE_FLOAT :
                return $record['execute'];
            break;
            case Token::TYPE_BOOLEAN :
            case Token::TYPE_NULL :
            case Token::TYPE_COMMA  :
            case Token::TYPE_EXCLAMATION :
            case Token::TYPE_BRACKET_SQUARE_OPEN :
            case Token::TYPE_BRACKET_SQUARE_CLOSE :
            case Token::TYPE_CODE :
            case Token::TYPE_QUOTE_SINGLE_STRING :
                return $record['value'];
            break;
            case Token::TYPE_STRING :
                return '\'' . $record['value'] . '\''; //might need str_replace on quote_single (') to (\')
            break;
            case Token::TYPE_QUOTE_DOUBLE_STRING :
                if(stristr($record['value'], '{') === false){
                    return $record['value'];
                }
                return 'str_replace([\'\n\', \'\t\'], ["\n", "\t"], $this->parse()->compile(\'' . substr($record['value'], 1, -1) . '\', [], $this->storage()))';
            break;
            case Token::TYPE_CAST :
                return Value::getCast($record);
            break;
            case Token::TYPE_VARIABLE :
                //missing storage from document
                return '$this->storage()->data(\'' . $record['variable']['attribute'] .'\')';
            break;
            default:
                $debug = debug_backtrace(true);
                d($record);
                dd($debug);
                throw new Exception('Variable value type ' .  $record['type'] . ' not defined');
        }
    }

    private function getCast($record=[]){
        switch(strtolower($record['value'])){
            case 'bool':
            case 'boolean':
                $result = Value::TYPE_CAST_BOOLEAN;
            break;
            case 'int':
            case 'integer':
                $result = Value::TYPE_CAST_INT;
            break;
            case 'float':
            case 'double':
                $result = Value::TYPE_CAST_FLOAT;
            break;
            case 'string':
                $result = Value::TYPE_CAST_STRING;
            break;
            default:
                throw new Exception('could not create cast: ' . $record['value']);
        }
        return '(' . $result . ')';
    }


}