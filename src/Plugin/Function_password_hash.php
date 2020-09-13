<?php
/**
 * @author          Remco van der Velde
 * @since           2020-09-13
 * @copyright       Remco van der Velde
 * @license         MIT
 * @version         1.0
 * @changeLog
 *     -            all
 */
use R3m\Io\Module\Parse;
use R3m\Io\Module\Data;

function function_password_hash(Parse $parse, Data $data, $password=''){
    return password_hash($password, PASSWORD_BCRYPT, [
        'cost' => 12
    ]);
}
