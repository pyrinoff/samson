<?php
/* Андрей Пыринов, pyrinoff@gmail.com, php 7.3.4 */

function findSimple(int $a, int $b)
{
    //проверяем, целое/положительное
    if (!is_int($a) || $a < 1) die('Error in ' . __FUNCTION__ . ': $a is not positive integer: ' . $a);
    if (!is_int($b) || $b < 1) die('Error in ' . __FUNCTION__ . ': $b is not positive integer: ' . $b);

    //ручной способ
    /*    $result = array();
        if ($a >= $b) {
            for ($i = $a; $i >= $b; $i--) $result[] = $i;
        } else {
            for ($i = $a; $i <= $b; $i++) $result[] = $i;
        }*/

    //нативными средствами
    $result = range($a, $b);

    return $result;
}

function createTrapeze(array $a)
{
    //проверяем, целое/положительное
    if (!is_array($a)) die('Error in ' . __FUNCTION__ . ': $a is not array');
    //if (array_keys($a) !== range(0, count($a) - 1)) die('Error in ' . __FUNCTION__ . ': assoc arrays is not allowed');
    foreach ($a as $k => $storona) {
        if (!is_numeric($storona) || $storona <= 0) die('Error in ' . __FUNCTION__ . ": \$a[$k] is not positive numeric: " . $storona);
    }

    $keys = ['a', 'b', 'c'];
    $kcount = count($keys);
    if (count($a) % $kcount !== 0) die('Error in ' . __FUNCTION__ . ': $a array count is not divisible by ' . $kcount);

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
    //проверяем входные данные, целое/положительное
    if (!is_array($a)) die('Error in ' . __FUNCTION__ . ': $a is not array');
    $checkkeys = ['a', 'b', 'c'];
    foreach ($a as $k => $trapeze) {
        foreach ($checkkeys as $key) {
            if (!is_numeric($trapeze[$key]) || $trapeze[$key] <= 0) die('Error in ' . __FUNCTION__ . ": \$a[$k]['$key'] is not positive numeric: " . $trapeze[$key]);
        }
        //добавляем площадь
        $a[$k]['s'] = 0.5 * $trapeze['a'] * $trapeze['b'] * $trapeze['c'];
    }
    return $a;
}

function getSizeForLimit(array $a, $b)
{
    //проверки 1
    if (!is_array($a)) die('Error in ' . __FUNCTION__ . ': $a is not array');
    if (!is_numeric($b) || $b <= 0) die('Error in ' . __FUNCTION__ . ':  $b is not positive number: ' . $b);
    //if (array_keys($a) !== range(0, count($a) - 1)) die('Error in ' . __FUNCTION__ . ': assoc arrays in $a is not allowed');
    $checkkeys = ['a', 'b', 'c', 's'];

    $maxvalue = NULL;
    $result = [];
    foreach ($a as $k => $trapeze) {
        //echo "{$trapeze['a']} {$trapeze['b']} {$trapeze['c']} {$trapeze['s']}, maxnow: $maxvalue\r\n";

        //проверки 2
        foreach ($checkkeys as $key) {
            if (!is_numeric($trapeze[$key]) || $trapeze[$key] <= 0) die('Error in ' . __FUNCTION__ . ": \$a[$k]['$key'] is not positive int: " . $trapeze[$key]);
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
}

function getMin(array $a)
{
    if (!is_array($a)) die('Error in ' . __FUNCTION__ . ': $a is not array');
    if (sort($a, SORT_NUMERIC)) return $a[0];
    else die('Error in ' . __FUNCTION__ . ': sort($a) returned false');
}

function printTrapeze($a)
{
    //проверки
    if (!is_array($a)) die('Error in ' . __FUNCTION__ . ': $a is not array');
    $checkkeys = ['a', 'b', 'c', 's'];

    //оформление
    echo '<style>.style1 {font-family: "Lucida Sans Unicode", "Lucida Grande", sans-serif;font-size: 14px;border-collapse: collapse;text-align: center;} .style1 th, .style1 td:first-child {background: #AFCDE7; color: white; padding: 10px 20px; } .style1 th, .style1 td { border-style: solid; border-width: 0 1px 1px 0; border-color: white; } .style1 td { background: #D8E6F3; } .style1 th:first-child, .style1 td:first-child { text-align: left; } .marked td {background: #98FB98}</style>';
    echo '<table class="style1"> <tr><th>#</th>  <th>a</th> <th>b</th> <th>c</th> <th>S</th> </tr>';

    foreach ($a as $k => $trapeze) {
        //проверки
        foreach ($checkkeys as $key) {
            if (!is_numeric($trapeze[$key]) || $trapeze[$key] <= 0) die('Error in ' . __FUNCTION__ . ": \$a[$k]['$key'] is not positive numeric");
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
        if (!is_numeric($a)
            || !is_numeric($b)
            || !is_numeric($c)) {
            die('Input data is not numeric');
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

//print_r(findSimple(5, 1));

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