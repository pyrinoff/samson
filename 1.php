<?php

function findSimple(int $a, int $b)
{
    try {
        //проверяем, целое/положительное
        if (!is_int($a) || $a < 1) throw new Exception('Error in ' . __FUNCTION__ . ': $a is not positive integer: ' . $a);
        if (!is_int($b) || $b < 1) throw new Exception('Error in ' . __FUNCTION__ . ': $b is not positive integer: ' . $b);
    } catch (Exception $exception) {
        echo 'Выброшена ошибка ',$exception->getMessage(),"\n";
        return 0;
    }
    $max = $a >= $b ? $a : $b;    //формируем массив с диапазоном от а до б
    $range_ab = range($a, $b);
    $count_ab = count($range_ab);

    $range_check = range(2, $max);  //и массив с диапазоном от 2 до большего из а и б (делители)


    if ($max > 40000) { //чуток облегчаем обработку для больших чисел, убрав некоторые делители. Полностью "решето" не реализовал, муторно
        $resheto_small = [2, 3, 5, 7, 9];
        //echo '$range_check before: '.count($range_check)."\r\n";
        foreach ($range_check as $range_check_key => $range_check_value) {
            foreach ($resheto_small as $numb) {
                if ($range_check_key % $numb == 0) {
                    unset($range_check[$range_check_key]);
                    break;
                }
            }
        }
        //echo '$range_check after: '.count($range_check)."\r\n";
    }


    $result = array();

    //перебираем весь введеный диапазон
    for ($i = 0; $i < $count_ab; $i++) {
        $is_simple = true;
        if ($range_ab[$i] < 2) continue;   //если меньше 2 (по сути, равно 1) - не считается
        foreach ($range_check as $someint) {    //перебираем делители
            if ($range_ab[$i] <= $someint) {     //если дошли до конца - замечательно, выходим из цикла и записываем число в массив
                //echo $range_ab[$i]." is simple!\r\n";
                break;
            }
            if ($range_ab[$i] % $someint == 0) {  //если поделилось на указанное число (которое заведомо не равно 1 и этому числу - значит число не простое)
                $is_simple = false;
                break;
            }
        }
        if ($is_simple) $result[] = $range_ab[$i];
    }
    return $result;
}

function createTrapeze(array $a)
{
    try {
        //проверяем, целое/положительное
        if (!is_array($a)) throw new Exception('Error in ' . __FUNCTION__ . ': $a is not array');
        //if (array_keys($a) !== range(0, count($a) - 1)) throw new Exception('Error in ' . __FUNCTION__ . ': assoc arrays is not allowed');
        foreach ($a as $k => $storona) {
            if (!is_numeric($storona) || $storona <= 0) throw new Exception('Error in ' . __FUNCTION__ . ": \$a[$k] is not positive numeric: " . $storona);
        }

        $keys = ['a', 'b', 'c'];
        $kcount = count($keys);
        if (count($a) % $kcount !== 0) throw new Exception('Error in ' . __FUNCTION__ . ': $a array count is not divisible by ' . $kcount);
    } catch (Exception $exception) {
        echo 'Выброшена ошибка ',$exception->getMessage(),"\n";
        return 0;
    }


    $result = array();
    $values = array_values($a);  //на случай входного ассоциативного массива

    for ($i = 0, $size = count($values), $subarray = 0; $i < $size; $i += $kcount, $subarray++) {
        foreach ($keys as $numb => $key) $result[$subarray][$key] = $values[$i + $numb];
    }

    //или так
    /*
    foreach($values as $k=>$val) {
        $mass=floor($k/$kcount); //0->  abc, 1->  abc
        $currkey=$k%$kcount; //a,b,c
        $r[$mass][$keys[$currkey]]=$val;
    }
    */
    return $result;
}

function squareTrapeze(array $a)
{
    try {
        //проверяем входные данные, целое/положительное
        if (!is_array($a)) throw new Exception('Error in ' . __FUNCTION__ . ': $a is not array');
        $checkkeys = ['a', 'b', 'c'];
        foreach ($a as $k => $trapeze) {
            foreach ($checkkeys as $key) {
                if (!is_numeric($trapeze[$key]) || $trapeze[$key] <= 0) throw new Exception('Error in ' . __FUNCTION__ . ": \$a[$k]['$key'] is not positive numeric: " . $trapeze[$key]);
            }
            //добавляем площадь
            $a[$k]['s'] = 0.5 * $trapeze['a'] * $trapeze['b'] * $trapeze['c'];
        }
        return $a;
    } catch (Exception $exception) {
        echo 'Выброшена ошибка ',$exception->getMessage(),"\n";
        return 0;
    }
}

function getSizeForLimit(array $a, $b)
{
    try{
        //проверки 1
        if (!is_array($a)) throw new Exception('Error in ' . __FUNCTION__ . ': $a is not array');
        if (!is_numeric($b) || $b <= 0) throw new Exception('Error in ' . __FUNCTION__ . ':  $b is not positive number: ' . $b);
        //if (array_keys($a) !== range(0, count($a) - 1)) throw new Exception('Error in ' . __FUNCTION__ . ': assoc arrays in $a is not allowed');
        $checkkeys = ['a', 'b', 'c', 's'];

        $maxvalue = NULL;
        $result = [];
        foreach ($a as $k => $trapeze) {
            //echo "{$trapeze['a']} {$trapeze['b']} {$trapeze['c']} {$trapeze['s']}, maxnow: $maxvalue\r\n";

            //проверки 2
            foreach ($checkkeys as $key) {
                if (!is_numeric($trapeze[$key]) || $trapeze[$key] <= 0) throw new Exception('Error in ' . __FUNCTION__ . ": \$a[$k]['$key'] is not positive int: " . $trapeze[$key]);
            }

            //сама функция
            if ($trapeze['s'] > $b) continue;
            if ($trapeze['s'] > $maxvalue) {
                $maxvalue = $trapeze['s'];
                $result = NULL;
                $result[] = $trapeze;
                continue;
            } else if ($trapeze['s'] == $maxvalue) {
                $result[] = $trapeze;
                continue;
            }
        }
        return $result;
    } catch (Exception $exception) {
        echo 'Выброшена ошибка ',$exception->getMessage(),"\n";
        return 0;
    }
}

function getMin(array $a)
{
    try {
        if (!is_array($a)) throw new Exception('Error in ' . __FUNCTION__ . ': $a is not array');

        if (sort($a, SORT_NUMERIC)) return $a[0];
        else throw new Exception('Error in ' . __FUNCTION__ . ': sort($a) returned false');
    } catch (Exception $exception) {
        echo 'Выброшена ошибка ',$exception->getMessage(),"\n";
        return 0;
    }
}

function printTrapeze($a)
{
    try {
        //проверки
        if (!is_array($a)) throw new Exception('Error in ' . __FUNCTION__ . ': $a is not array');
        $checkkeys = ['a', 'b', 'c', 's'];

        //оформление
        echo '<style>.style1 {font-family: "Lucida Sans Unicode", "Lucida Grande", sans-serif;font-size: 14px;border-collapse: collapse;text-align: center;} .style1 th, .style1 td:first-child {background: #AFCDE7; color: white; padding: 10px 20px; } .style1 th, .style1 td { border-style: solid; border-width: 0 1px 1px 0; border-color: white; } .style1 td { background: #D8E6F3; } .style1 th:first-child, .style1 td:first-child { text-align: left; } .marked td {background: #98FB98}</style>';
        echo '<table class="style1"> <tr><th>#</th>  <th>a</th> <th>b</th> <th>c</th> <th>S</th> </tr>';

        foreach ($a as $k => $trapeze) {
            //проверки
            foreach ($checkkeys as $key) {
                if (!is_numeric($trapeze[$key]) || $trapeze[$key] <= 0) throw new Exception('Error in ' . __FUNCTION__ . ": \$a[$k]['$key'] is not positive numeric");
            }
            //вывод строк таблицы
            static $i = 0;
            //выбрал на свое усмотрение округление в меньшую сторону для определения четное/нечетное
            $colored = floor($trapeze['s']) % 2 == 0 ? '' : ' class="marked"';
            echo "<tr{$colored}>
                        <td>$i</td>
                        <td>{$trapeze['a']}</td>
                        <td>{$trapeze['b']}</td>
                        <td>{$trapeze['c']}</td>
                        <td>{$trapeze['s']}</td>
                  </tr>";
            $i++;
        }
        echo '</table>';
    } catch (Exception $exception) {
        echo 'Выброшена ошибка ',$exception->getMessage(),"\n";
        return 0;
    }

}

abstract class BaseMath
{
    protected $result;

    static protected function exp1($a, $b, $c)
    {
        return $a * ($b ^ $c);
    }

    static protected function exp2($a, $b, $c)
    {
        return ($a / $b) ^ $c;
    }

    public function getValue()
    {
        return $this->result;
    }

}

class F1 extends BaseMath
{
    protected $result;

    public function __construct($a, $b, $c)
    {
        try {
            if (!is_numeric($a)
                || !is_numeric($b)
                || !is_numeric($c)) {
                throw new Exception('Input data is not numeric');
            }
        } catch (Exception $exception) {
            echo 'Выброшена ошибка ',$exception->getMessage(),"\n";
            return 0;
        }
        $this->result =
            self::exp1($a, $b, $c)
            + (self::exp2($a, $b, $c) % 3)
            ^ min($a, $b, $c);
    }

    public function getValue()
    {
        return parent::getValue();
    }
}


//TESTS
//print_r(findSimple(55, 2));

//$arr = [2, 2, 2, 3, 3, 3, 9, 9, 9, 2, 2, 2];
//print_r(createTrapeze($arr));


/*$arr2 = [
    ['a' => 2, 'b' => 2, 'c' => 2],
    ['a' => 5, 'b' => 5, 'c' => 5],
    ['a' => 9, 'b' => 9.5, 'c' => 9],
    ['a' => 1, 'b' => 1, 'c' => 1]
];*/

//print_r(squareTrapeze(createTrapeze($arr)));
//print_r(squareTrapeze($arr2));

//print_r(getSizeForLimit(squareTrapeze(createTrapeze($arr)), 365));
//print_r(getSizeForLimit(squareTrapeze($arr2), 365));
//print_r(getMin(findSimple(100, 1)));

//printTrapeze(squareTrapeze(createTrapeze($arr)));


/*$a = new F1(2, 3, 4);
echo $a->getValue();*/