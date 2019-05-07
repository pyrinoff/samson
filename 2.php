<?php
$SHOW_SQL_QUERRIES=false;

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

function mySortForKey($a, $b)
{
    if (!is_array($a)) throw new Exception('$a is not array');
    foreach ($a as $k => $v) if (!isset($v[$b])) throw new Exception($k);

    usort($a, function ($first, $second) use ($b) {
        $result = $first[$b] - $second[$b];
        if ($result < 0) return -1; //проверяем, чтобы избежать округления (для float)
        else if ($result > 0) return 1;
        else return 0;
    });
    return $a;
}


function createTables($server=NULL, $user=NULL, $pass=NULL, $dbname=NULL)
{
    if($server===NULL) $server = "localhost";
    if($user===NULL) $user = "root";
    if($pass===NULL) $pass = "";
    if($dbname===NULL) $dbname = 'test_samson';

    //connect
    $conn = new mysqli($server, $user, $pass);
    if ($conn->connect_error) {
        try {
            throw new Exception("Cant connect to $user:$pass@$server - " . $conn->connect_error);
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
    }

    //sqls
    $sqls[] = "DROP DATABASE $dbname;";
    $sqls[] = "CREATE DATABASE $dbname;";
    $sqls[] = "ALTER DATABASE $dbname CHARACTER SET utf8;";
    $sqls[] = "USE $dbname;";
    $sqls[] = "CREATE TABLE a_product (
        prod_id INT NOT NULL AUTO_INCREMENT,
        prod_code INT NOT NULL,
        prod_name VARCHAR (256),
        PRIMARY KEY(prod_id)  
);";
    $sqls[] = "CREATE TABLE a_property (        
        prod_id INT NOT NULL,
        prop_name VARCHAR (256),
        prop_val VARCHAR (256)  
);";
    $sqls[] = "CREATE TABLE a_price (        
        prod_id INT NOT NULL,
        price_name VARCHAR (256),
        price_val FLOAT
);";
    $sqls[] = "CREATE TABLE a_category (        
        cat_id INT NOT NULL AUTO_INCREMENT,
        cat_code SMALLINT,
        cat_name  VARCHAR (256),
        cat_parent INT DEFAULT 0,
        PRIMARY KEY(cat_id)  
);";
    $sqls[] = "CREATE TABLE a_prodcat (        
        prod_id INT NOT NULL,
        cat_code INT NOT NULL,
        PRIMARY KEY(prod_id, cat_code)  
);";
    global $SHOW_SQL_QUERRIES;
    foreach ($sqls as $k => $sql) {
        if($SHOW_SQL_QUERRIES) echo "Trying '$sql'";
        $result=$conn->query($sql);

        if ($result === false) {
            try {
                throw new Exception("Error with query #$k: " . $conn->error);
            }
            catch (Exception $e) {
                die($e->getMessage());
            }
        }
        if($SHOW_SQL_QUERRIES) echo "...successful\r\n";
    }
    $conn->close();

}

/*

 Были непонятные моменты, решал их по ы:
- Не понял, как уложить свойства товара в 1 строку, поэтому добавил еще один столбец для значения свойства, т.е. отдельно имя, отдельно значение
- Отбросил параметры для свойств продукта, ЕдИзм="%" и т.п.

Для создания рубрикатора добавил cat_parent в a_category
Для связи товара (по ИД) с категорией (по коду категории, не по ИД) создал таблицу a_prodcat, в которй хранится prod_id и cat_code
Проверку входных параметров не осуществлял, чтобы съэкономить время

С функцией importXml связаны функции xmlToArray (экспорт xml в массив) и myParce (чтобы доставать нужные данные из XML);
Функция createTables() создана для (пере)создания таблиц из задания.

Функцию findSimple из первого задания доработал
*/

function xmlToArray(SimpleXMLElement $xml): array
{
    $parser = function (SimpleXMLElement $xml, array $collection = []) use (&$parser) {
        $nodes = $xml->children();
        $attributes = $xml->attributes();

        if (0 !== count($attributes)) {
            foreach ($attributes as $attrName => $attrValue) {
                $collection['attributes'][$attrName] = strval($attrValue);
            }
        }

        if (0 === $nodes->count()) {
            $collection['value'] = strval($xml);
            return $collection;
        }

        foreach ($nodes as $nodeName => $nodeValue) {
            /*if (count($nodeValue->xpath('../' . $nodeName)) < 2) {
                $collection[$nodeName] = $parser($nodeValue);
                continue;
            }
            */

            $collection[$nodeName][] = $parser($nodeValue);
        }

        return $collection;
    };

    return [
        $xml->getName() => $parser($xml)
    ];
}

function myParce($arr, $mode, $key = false)
{
    if ($mode == 'KEY_PLUS_VAL') {
        foreach ($arr as $k => $v) $result[$k] = $v[0]['value'];
    }
    if ($mode == 'ATTRIBUTES' && isset($arr['attributes'])) {
        foreach ($arr['attributes'] as $k => $v) $result[$k] = $v;
    }
    if ($mode == 'CUSTOM_ATTR_PLUS_VAL' && $key !== false) {
        foreach ($arr as $subarr) {
            if (!isset($subarr['attributes'][$key])) {
                try {
                    throw new Exception('Invalid key: '.$key);
                }
                catch (Exception $e) {
                    die($e->getMessage());
                }
            }
            $result[$subarr['attributes'][$key]] = $subarr['value'];
        }
    }

    if ($mode == 'CUSTOM_KEY_PLUS_VAL' && $key !== false) {
        if (!isset($arr[$key])) {
            try {
                throw new Exception('Invalid key: '.$key);
            }
            catch (Exception $e) {
                die($e->getMessage());
            }
        }
        foreach ($arr[$key] as $subarr) {
            if (!isset($subarr['value'])) {
                var_dump($subarr);
                try {
                    throw new Exception('Invalid value: '.$subarr['value']);
                }
                catch (Exception $e) {
                    die($e->getMessage());
                }
            }
            $result[] = $subarr['value'];
        }
    }
    return $result;
}

function myQuery(mysqli $conn, $sql, $assoc=false) {
    global $SHOW_SQL_QUERRIES;
    if($SHOW_SQL_QUERRIES) echo "Trying '$sql'";
    $result=$conn->query($sql);
    if ($result === false) {
        try {
            throw new Exception("Error with query: " . $conn->error);
        }
        catch (Exception $e) {            die($e->getMessage());        }
    }
    if($SHOW_SQL_QUERRIES) echo "...successful\r\n";
    if($result instanceof mysqli_result)  {
        if($assoc) {
            while($row=mysqli_fetch_assoc($result)) $ret[][]=$row;
            return $ret;
        }
        else
            return mysqli_fetch_all($result);
    }
}

function importXml($a)
{
    $server = "localhost";
    $user = "mysql";
    $pass = "mysql";
    $dbname = 'test_samson';

    if (!file_exists($a)) {
        try {
            throw new Exception('Input file doesnt exists');
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
    }

    $file_content = file_get_contents($a);
    //$file_content=mb_convert_encoding($file_content, 'UTF-8');//, mb_detect_encoding ($file_content));
    //$data = json_decode(json_encode(simplexml_load_string($file_content)), true);
    $data = xmlToArray(simplexml_load_string($file_content)); //из XML в массив

    if (!is_array($data['Товары'])) {
        try {
            throw new Exception('Wrong XML structure (Tovari empty)');
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
    }

    $sqls = array();

    $conn = new mysqli($server, $user, $pass, $dbname);
    if ($conn->connect_error) {
        try {
            throw new Exception("Cant connect to $user:$pass@$server - " . $conn->connect_error);
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
    }
    //createTables($server, $user, $pass, $dbname);

    //Если в cat_parent 0 - не вложена, иначе - вложена в категорию с указанным кодом
    myQuery($conn,"INSERT INTO a_category (cat_code, cat_name, cat_parent) VALUES (100, 'Бумага', 0);");
    myQuery($conn,"INSERT INTO a_category (cat_code, cat_name, cat_parent) VALUES (101, 'Принтеры', 0);");
    myQuery($conn,"INSERT INTO a_category (cat_code, cat_name, cat_parent) VALUES (102, 'МФУ', 0);");
    myQuery($conn,"INSERT INTO a_category (cat_code, cat_name, cat_parent) VALUES (103, 'Суб-принтеры', 101);");
    myQuery($conn,"INSERT INTO a_category (cat_code, cat_name, cat_parent) VALUES (104, 'Суб-принтеры2', 101);");

    foreach ($data['Товары']['Товар'] as $k => $prod) {
        $product=myParce($prod, 'ATTRIBUTES');
        $prod_name = $product['Название'];
        $prod_code = $product['Код'];

        myQuery($conn, "INSERT INTO a_product (prod_code, prod_name) VALUES ($prod_code, '$prod_name');");
        $prod_id=mysqli_insert_id(($conn));
        if($prod_id==false) {
            try {
                throw new Exception('Cant connect last insert id: '.$conn->connect_error);
            }
            catch (Exception $e) {
                die($e->getMessage());
            }
        }

        $price = myParce($prod['Цена'], 'CUSTOM_ATTR_PLUS_VAL', 'Тип');
        foreach($price as $pricename=>$pricevalue) {
            myQuery($conn, "INSERT INTO a_price (prod_id, price_name, price_val) VALUES ($prod_id, '$pricename', $pricevalue);");
        }

        $properties=myParce($prod['Свойства'][0], 'KEY_PLUS_VAL');
        foreach($properties as $propname=>$propvalue) {
            myQuery($conn, "INSERT INTO a_property (prod_id, prop_name, prop_val) VALUES ($prod_id, '$propname', '$propvalue');");
        }

        $categories = myParce($prod['Разделы'][0], 'CUSTOM_KEY_PLUS_VAL', 'Раздел');
        foreach ($categories as $catname) {
            $cat_code_arr=myQuery($conn, "SELECT cat_code FROM a_category WHERE cat_name='$catname' LIMIT 1;");
            //var_dump($cat_code_arr);
            $cat_code=$cat_code_arr[0][0];
            myQuery($conn, "INSERT INTO a_prodcat (prod_id, cat_code) VALUES ($prod_id, $cat_code);");
        }

    }
    $conn->close();
}

function exportXml($a, $b) {
    $server = "localhost";
    $user = "mysql";
    $pass = "mysql";
    $dbname = 'test_samson';

    $conn = new mysqli($server, $user, $pass, $dbname);
    if ($conn->connect_error) {
        try {
            throw new Exception("Cant connect to $user:$pass@$server - " . $conn->connect_error);
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
    }

    if(!is_int($b) || $b<0) {
        try {
            throw new Exception("B is not numeric or not positive");
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
    }

    //получаем все субкатегории, входящие в категорию $b
    $result=myQuery($conn, "SELECT cat_code
FROM
    (SELECT * FROM a_category ORDER BY cat_parent, cat_code) category_sorted,
    (SELECT @pv :='$b') INITIALISATION
WHERE find_in_set(cat_parent, @pv)
    ;"); //AND length (@pv :=concat (@pv, ',', cat_parent))

    $cat_codes[0]=$b;
    foreach($result as $row) $cat_codes[]=$row[0];
    $cats=implode(',', $cat_codes);

    //получаем скисок товаров, входищих в эти категории
    $result2=myQuery($conn, "SELECT prod_id FROM a_prodcat WHERE cat_code IN ($cats);");
    foreach($result2 as $row) $prod_ids[]=$row[0];

    $tab='	';

    $xml="<?xml version=\"1.0\" encoding=\"utf-8\"?>\r\n<Товары>\r\n";

    //получаем данные для этих товаров
    foreach($prod_ids as $prod_id) {
        //Название/код
        $a_product=myQuery($conn, "SELECT prod_code, prod_name FROM a_product WHERE prod_id=$prod_id");
        $product['Код']=$a_product[0][0];
        $product['Название']=$a_product[0][1];
        $xml.=$tab.'<Товар Код="'.$product['Код'].'" Название="'.$product['Название'].'">'."\r\n";
        //Цены
        $a_price=myQuery($conn, "SELECT price_name, price_val FROM a_price WHERE prod_id=$prod_id");
        //var_dump($a_price);
        foreach($a_price as $price) {
            $xml.=$tab.$tab.'<Цена Тип="'.$price[0].'">'.$price[1].'</Цена>'."\r\n";
        }
        //Свойства
        $a_property=myQuery($conn, "SELECT prop_name, prop_val FROM a_property WHERE prod_id=$prod_id");
        //var_dump($a_property);
        $xml.=$tab.$tab."<Свойства>\r\n";
        foreach($a_property as $prop) {
            $xml.=$tab.$tab.$tab.'<'.$prop[0].'>'.$prop[1].'</'.$prop[0].'>'."\r\n";
        }
        $xml.=$tab.$tab."</Свойства>\r\n";
        //Разделы
        $a_category=myQuery($conn,
            "SELECT cat_name FROM a_category WHERE cat_code IN 
                    ( SELECT cat_code FROM a_prodcat WHERE prod_id=4 )
                ;");
        //var_dump($a_category);
        $xml.=$tab.$tab."<Разделы>\r\n";
        foreach($a_category as $cat) {
            $xml.=$tab.$tab.$tab.'<Раздел>'.$cat[0].'</Раздел>'."\r\n";
        }
        $xml.=$tab.$tab."</Разделы>\r\n";



        $xml.="    </Товар>\r\n";
    }

    $xml.='</Товары>';
    if(!file_put_contents($a, $xml)) {
        try {
            throw new Exception("Cant write XML to file '$a'");
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
    }

}



//var_dump(convertString('testANDtestANDtest', 'test'));
//$someA = [['a' => 3, 'b' => 3], ['a' => 1, 'b' => 1], ['a' => 2, 'b' => 2]];
//var_dump(mySortForKey($someA, 'b'));



//$SHOW_SQL_QUERRIES=false;
//createTables(); //re-create

//importXml('2.xml');
//exportXml('3.xml', 101);