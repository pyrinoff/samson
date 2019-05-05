<?php

function convertString(String $a, String $b)
{
    $replace_number = 2;  //номер заменяемого вхождения

/*
    //Вариант 1 - через explode и пересборку строки
    $exp = explode($b, $a, $replace_number + 1);
    $count = count($exp);
    if ($count <= $replace_number) return $a;    //если недостаточно вхождений - возвращаем исходный код

    $final = '';
    for ($i = 0; $i < $count; $i++) {
        $final .= $exp[$i]; //всегда добавляем элемент массива
        if ($i == $replace_number - 1) $final .= strrev($b); //если это тот самый номер - реверсивную строку
        else if ($i < $count - 1) $final .= $b; //либо обычную подстроку (если не последний элемент)
    }
 */

    //Вариант 2 - через индекс
    if (substr_count($a, $b) < $replace_number) return $a;
    $index = 0;
    for ($i = 0; $i < $replace_number; $i++) { //находим стартовый индекс для замены
        $index = strpos($a, $b, $index);
        if ($i == $replace_number - 1) break; //дошли до нужного индекса
        else $index += strlen($b); //не дошли - прибавляем для следующего поиска
    }
    $final = substr_replace($a, strrev($b), $index, strlen($b)); //заменяем

    return $final;
}

//var_dump(convertString('testANDtestANDtest', 'test'));
