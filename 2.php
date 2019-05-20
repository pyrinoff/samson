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

trait zLogTrait
{
    public function log($msg, $linebreak = true)
    {
        echo
            //self::class . ' ' .
        $msg;
        if ($linebreak) echo "\r\n";
    }
}

interface zLogInterface
{
    public function log($msg, $linebreak = true);
}

class zSQL implements zLogInterface
{
    private $server, $user, $pass, $dbname;
    private $conn;
    use zLogTrait;
    private $charset;
    private $last_id;

    public function __construct($server = 'localhost', $user = 'root', $pass = '', $dbname = NULL, $charset = 'utf8')
    {
        $this->server = self::escape($server, 1);
        $this->user = self::escape($user, 1);
        $this->pass = self::escape($pass, 1);
        $this->dbname = self::escape($dbname, 1);
        $this->charset = self::escape($charset);
        $this->connect();
    }

    public static function escape($str, $mode = false)
    {
        if (is_array($str)) return array_map(__METHOD__, $str);

        if (!empty($str) && is_string($str)) {
            $str = str_replace(array('\\', "\0", "\n", "\r", "'", '"', "\x1a"), array('\\\\', '\\0', '\\n', '\\r', "\\'", '\\"', '\\Z'), $str);
        }
        if ($mode === 1) {
            $str = str_replace(';', '', $str);
        }

        return $str;
    }

    public function connect()
    {
        if ($this->conn !== NULL) self::log('Renew connection to mySQL...', false);
        else self::log('Creating new connection to mySQL...', false);
        $newconn = NULL;
        $dsn = "mysql:host=$this->server;";
        if ($this->dbname !== NULL) $dsn .= "dbname=$this->dbname;";
        $options = [
            PDO::ATTR_EMULATE_PREPARES => false, // turn off emulation mode for "real" prepared statements
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, //turn on errors in the form of exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, //make the default fetch be an associative array
            PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true,
        ];
        try {
            $newconn = new \PDO($dsn, $this->user, $this->pass, $options);
        } catch (Exception  $e) {
            self::log("Cant connect to $this->user:$this->pass@$this->server");
            throw new Exception($e->getMessage() . ' ' . $e->getCode());
        }
        self::log('established');
        $this->conn = $newconn;
        $this->set_charset($this->charset);
    }

    public function set_charset($charset)
    {
        try {
            self::log('Trying set charset ' . $charset . '...', false);
            $result = $this->conn->exec('SET NAMES ' . $charset);
            if ($result === false) {
                self::log('FALSE!!!');
                throw new Exception('ERROR');
            }
            self::log('successfully');
            return $result;
        } catch (Exception $e) {
            self::log('ERROR!!!');
            throw new Exception($e->getMessage() . ' ' . $e->getCode());
        }
    }

    public function __destruct()
    {
        self::log('Closing mySQL connection...');
        $this->conn = NULL;
    }

    //prepared
    //args to exec (with escaping)

    public function prepare($sql)
    {
        self::log('Trying (prepare) ' . $sql, false);
        $h = NULL;
        try {
            $this->conn->beginTransaction();
            $h = $this->conn->prepare($sql);
            if ($h === FALSE) {
                self::log('prerare FALSE!!');
                throw new Exception();
            }

            if (func_num_args() > 1) {
                $escaped_args = self::escape((func_get_args()));
                array_shift($escaped_args);
                $ret = $h->execute($escaped_args);

            } else $ret = $h->execute(array());

            if ($ret === FALSE) {
                $h->closeCursor();
                self::log('execute FALSE!!');
                throw new Exception($h->errorCode() . ' ' . $h->errorInfo()[2]);
            }
            do {
                $result[] = $h->fetchAll(PDO::FETCH_NUM);
            } while ($h->nextRowSet());

            //$result = $h->fetchAll(PDO::FETCH_NUM);
            //list($result) = $h->fetchAll(PDO::FETCH_ASSOC);
            //$result=$h->fetchAll();

            //var_dump($result);
            if (!$h->closeCursor()) self::log('ERROR: Can\'t close current cursor.');
            $this->last_id = $this->conn->lastInsertId(); //To do: брать last только у insert
            $this->conn->commit();
            self::log('...successful.');
            return $result;
        } catch (Exception $e) {
            self::log('ERROR!!');
            if ($h !== NULL) $h->closeCursor();
            $this->conn->rollback();
            throw $e;
        }
        return $result;
    }

    public function query($sql)
    {
        self::log('Trying (query) ', false);
        $h = NULL;
        try {
            $this->conn->beginTransaction();
            if (func_num_args() > 1) {
                if (substr_count($sql, '?') !== (func_num_args() - 1)) {
                    self::log('QUERY: count of args and bind values not equal');
                    throw new Exception('Count of args and bind values not equal');
                }
                $escaped_args = self::escape((func_get_args()));
                array_shift($escaped_args);

                $imploded_sql = explode('?', $sql);
                $mixed_sql = '';
                foreach ($imploded_sql as $k => $part) {
                    $mixed_sql .= $part . $escaped_args[$k];
                }
                self::log('"' . $mixed_sql . '"', false);
                $h = $this->conn->query($mixed_sql);

            } else {
                self::log('"' . $sql . '"', false);
                $h = $this->conn->query($sql);
            }

            do {
                $result[] = $h->fetchAll(PDO::FETCH_NUM);
            } while ($h->nextRowSet());
            //list($result) = $h->fetchAll(PDO::FETCH_NUM);
            //list($result) = $h->fetchAll(PDO::FETCH_ASSOC);
            //$result=$h->fetchAll();
            //} while($h->nextRowSet());
            //var_dump($result);
            if (!$h->closeCursor()) self::log('ERROR: Can\'t close current cursor.');
            $this->last_id = $this->conn->lastInsertId(); //To do: брать last только у insert
            $this->conn->commit();
            self::log(' ...successful.');
            return $result;
        } catch (Exception $e) {
            self::log('ERROR!!');
            if ($h !== NULL) $h->closeCursor();
            $this->conn->rollback();
            throw $e;
        }
        return $result;
    }

    public function exec($sql)
    {
        self::log('Trying (exec) ', false);
        try {
            if (func_num_args() > 1) {
                if (substr_count($sql, '?') !== (func_num_args() - 1)) {
                    self::log('EXEC: count of args and bind values not equal');
                    throw new Exception('Count of args and bind values not equal');
                }
                $escaped_args = self::escape((func_get_args()));
                array_shift($escaped_args);

                $imploded_sql = explode('?', $sql);
                $mixed_sql = '';
                foreach ($imploded_sql as $k => $part) {
                    $mixed_sql .= $part . $escaped_args[$k];
                }
                self::log($mixed_sql, false);

                $ret = $this->conn->exec($mixed_sql);

            } else $ret = $this->conn->exec($sql);

            if ($ret === FALSE) {
                self::log('FALSE!!');
                throw new Exception();
            }
            self::log('...successful.');
            return;
        } catch (Exception $e) {
            self::log('ERROR!!');
            throw $e;
        }
        return;
    }

    public function last_id()
    {
        if ($this->last_id !== false) return (int)$this->last_id;
        else throw new Exception();
    }

    public function insert_id()
    {
        self::log('Getting last insert id...', false);
        try {
            $result = $this->conn->lastInsertId();
            if ($result === false) {
                self::log('false');
                throw new Exception('Cant get last insert id: ' . $this->conn->connect_error);
            }
            self::log('"' . $result . '" successfully');
            return $result;
        } catch (Exception $e) {
            self::log('ERROR!!');
            throw new Exception();
        }
    }
}


function recreateTablesAndCategories()
{
    $db = new zSQL();
    $dbname = 'test_samson';

    try {
        $db->exec("DROP DATABASE ?;", $dbname);
    } catch (Exception $e) {
    }
    $db->exec("CREATE DATABASE ?;", $dbname);
    $db->exec("ALTER DATABASE ? CHARACTER SET utf8;", $dbname);
    $db->exec("USE ?;", $dbname);
    $db->prepare("CREATE TABLE a_product (
        prod_id INT NOT NULL AUTO_INCREMENT,
        prod_code INT NOT NULL,
        prod_name VARCHAR (256),
        PRIMARY KEY(prod_id)  
);");
    $db->prepare("CREATE TABLE a_property (        
        prod_id INT NOT NULL,
        prop_name VARCHAR (256),
        prop_val VARCHAR (256)  
);");
    $db->prepare("CREATE TABLE a_price (        
        prod_id INT NOT NULL,
        price_name VARCHAR (256),
        price_val FLOAT
);");
    $db->prepare("CREATE TABLE a_category (        
        cat_id INT NOT NULL AUTO_INCREMENT,
        cat_code SMALLINT,
        cat_name  VARCHAR (256),
        cat_parent INT DEFAULT 0,
        PRIMARY KEY(cat_id)  
);");
    $db->prepare("CREATE TABLE a_prodcat (        
        prod_id INT NOT NULL,
        cat_code INT NOT NULL,
        PRIMARY KEY(prod_id, cat_code)  
);");

    //добавляем нужные категории в таблицу (считаем, что они уже внесены)
    //Если в cat_parent 0 - не вложена, иначе - вложена в категорию с указанным кодом
    $db->prepare("INSERT INTO a_category (cat_code, cat_name, cat_parent) VALUES (100, 'Бумага', 0);");
    $db->prepare("INSERT INTO a_category (cat_code, cat_name, cat_parent) VALUES (101, 'Принтеры', 0);");
    $db->prepare("INSERT INTO a_category (cat_code, cat_name, cat_parent) VALUES (102, 'МФУ', 0);");
    $db->prepare("INSERT INTO a_category (cat_code, cat_name, cat_parent) VALUES (103, 'Суб-принтеры', 101);");
    $db->prepare("INSERT INTO a_category (cat_code, cat_name, cat_parent) VALUES (104, 'Суб-принтеры2', 101);");
}


class zXmlArray implements zLogInterface
{
    use zLogTrait;

    public static function file_to_array($filepath)
    {
        if (!file_exists($filepath)) throw new Exception('Input file doesnt exists');

        $file_content = file_get_contents($filepath);
        //$file_content=mb_convert_encoding($file_content, 'UTF-8');//, mb_detect_encoding ($file_content));
        //$data = json_decode(json_encode(simplexml_load_string($file_content)), true);
        return self::xml_to_array(simplexml_load_string($file_content)); //из XML в массив
    }

    public static function xml_to_array(SimpleXMLElement $xml): array
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

    public static function getAssocKeyValue(array $arr)
    {
        if (!is_array($arr)) throw new Exception(__FUNCTION__ . ' !array');
        $result = NULL;
        foreach ($arr as $k => $v) {
            foreach($v as $subval)
            $result[$k][] = $subval['value'];
        }
        return $result;
    }

    public static function getAttributes(array $arr)
    {
        if (!is_array($arr['attributes'])) throw new Exception(__FUNCTION__ . ' ["attributes"] !array');
        $result = NULL;
        foreach ($arr['attributes'] as $k => $v) $result[$k] = $v;
        return $result;
    }

    public static function getSpecificAttributePlusValue(array $arr, $key)
    {
        if (!is_array($arr)) throw new Exception(__FUNCTION__ . ' !array');
        $result = NULL;
        foreach ($arr as $subarr) {
            if (!isset($subarr['attributes'][$key])) throw new Exception(__FUNCTION__ . ' invalid key: ' . $key);
            $result[$subarr['attributes'][$key]] = $subarr['value'];
        }
        return $result;
    }


    public static function getSpecificKeyPlusVal(array $arr, $key)
    {
        if (!is_array($arr)) throw new Exception(__FUNCTION__ . ' !array');
        if (!isset($arr[$key])) throw new Exception('Invalid key: ' . $key);
        $result = NULL;
        foreach ($arr[$key] as $subarr) {
            if (!isset($subarr['value'])) {
                var_dump($subarr);
                throw new Exception('Invalid key: ' . $key);
            }
            $result[] = $subarr['value'];
        }
        return $result;
    }
}


class Product implements zLogInterface
{
    private $code;
    private $name;
    private $prices;
    private $properties;
    private $categories;

    use zLogTrait;

    //TO DO: сделать getCode и проч (проверка на NULL)

    public function __construct()
    {

    }

    public function insertData(zSQL $link)
    {
        //при желании можно обработать эксепшн и не добавлять в базу только некорректно спрарсенный товар, отловив тут эксепшн
        //try {
        $this->checkDataExistsInDB($link);
        //] catch (Exception $e) { return; }

        $link->prepare("INSERT INTO a_product (prod_code, prod_name) VALUES (?, ?);", $this->getCode(), $this->getName());
        $prod_id = $link->last_id();

        foreach ($this->getPrices() as $price) {
            $link->prepare("INSERT INTO a_price (prod_id, price_name, price_val) VALUES (?, ?, ?);", $prod_id, $price['name'], $price['val']);
        }

        foreach ($this->getProperties() as $property) {
            $link->prepare("INSERT INTO a_property (prod_id, prop_name, prop_val) VALUES (?, ?, ?);", $prod_id, $property['name'], $property['val']);
        }

        foreach ($this->getCategories() as $category) {
            $cat_code_arr = $link->prepare("SELECT cat_code FROM a_category WHERE cat_name=? LIMIT 1;", $category['name']);
            //var_dump($cat_code_arr);
            $cat_code = $cat_code_arr[0][0][0];
            $link->prepare("INSERT INTO a_prodcat (prod_id, cat_code) VALUES (?, ?);", $prod_id, $cat_code);
        }

    }

    public function checkDataExistsInDB(zSQL $link)
    {
        //проверка на существования продукта с таким же кодом (хотя не уверен, что она нужна, т.к. у нас ID - уникальный столбец)
        $result = $link->prepare("SELECT EXISTS (SELECT prod_id FROM a_product WHERE prod_code=?);", $this->code);
        if ($result[0][0][0] === 1) throw new Exception('Product with prod_code ' . $this->code . ' already exists in database');

        //проверка существования категории с таким именем
        foreach ($this->categories as $category) {
            $result = $link->prepare("SELECT EXISTS (SELECT cat_code FROM a_category WHERE cat_name=?);", $category['name']);
            if ($result[0][0][0] !== 1) throw new Exception('Category "' . $category['name'] . '" for product with prod_code ' . $this->code . ' doesn\'t exists in database');

        }
    }

    public function getCode()
    {
        if ($this->code == NULL) throw new Exception('Code is null');
        return $this->code;
    }

    public function setCode($code)
    {
        $code = (int)$code;
        //тут могут быть проверки на адекватность данных
        $this->code = $code;
    }

    public function getName()
    {
        if ($this->name == NULL) throw new Exception('Name is null');
        return $this->name;
    }

    public function setName($name)
    {
        //тут могут быть проверки на адекватность данных
        if (strlen($name) < 0 || strlen($name) > 100) throw new Exception('setName "$name" incorrect');
        $this->name = $name;
    }

    public function getPrices()
    {
        if ($this->prices == NULL) throw new Exception('Prices is null');
        return $this->prices;
    }

    public function getProperties()
    {
        if ($this->properties == NULL) throw new Exception('Properties is null');
        return $this->properties;
    }

    public function getCategories()
    {
        if ($this->categories == NULL) throw new Exception('Categories is null');
        return $this->categories;
    }

    public function importProductFromDB($link, $id)
    {
        $id = (int)$id;

        $a_product = $link->query("SELECT prod_code, prod_name FROM a_product WHERE prod_id=?;", $id);
        $this->setCode($a_product[0][0][0]);
        $this->setName($a_product[0][0][1]);


        $a_price = $link->query("SELECT price_name, price_val FROM a_price WHERE prod_id=?;", $id);
        foreach ($a_price[0] as $price) $this->setPrice($price[0], $price[1]);

        $a_property = $link->query("SELECT prop_name, prop_val FROM a_property WHERE prod_id=?;", $id);
        foreach ($a_property[0] as $prop) $this->setProperty($prop[0], $prop[1]);


        $a_category = $link->query(
            "SELECT cat_name FROM a_category WHERE cat_code IN
                    ( SELECT cat_code FROM a_prodcat WHERE prod_id=? )
                ;", $id);
        foreach ($a_category[0] as $cat) $this->setCategory($cat[0], $cat[1]);

        return;

        /*
        //Название/код

        $xml .= $tab . '<Товар Код="' . $product['Код'] . '" Название="' . $product['Название'] . '">' . "\r\n";
        //Цены

        //var_dump($a_price);
        foreach ($a_price as $price) {
            $xml .= $tab . $tab . '<Цена Тип="' . $price[0] . '">' . $price[1] . '</Цена>' . "\r\n";
        }
        //Свойства

        //var_dump($a_property);
        $xml .= $tab . $tab . "<Свойства>\r\n";
        foreach ($a_property as $prop) {
            $xml .= $tab . $tab . $tab . '<' . $prop[0] . '>' . $prop[1] . '</' . $prop[0] . '>' . "\r\n";
        }
        $xml .= $tab . $tab . "</Свойства>\r\n";
        //Разделы

        //var_dump($a_category);
        $xml .= $tab . $tab . "<Разделы>\r\n";
        foreach ($a_category as $cat) {
            $xml .= $tab . $tab . $tab . '<Раздел>' . $cat[0] . '</Раздел>' . "\r\n";
        }
        $xml .= $tab . $tab . "</Разделы>\r\n";


        $xml .= "    </Товар>\r\n";
        */


    }

    public function setPrice($pricename, $pricevalue)
    {
        $pricevalue = (float)$pricevalue;
        //тут могут быть проверки на адекватность данных
        $this->prices[] = ['name' => $pricename, 'val' => $pricevalue];
    }

    public function setProperty($propname, $propvalue, $prop_unit = false, $prop_unit_val = false)
    {
        //тут могут быть проверки на адекватность данных
        $arr['name'] = $propname;
        $arr['val'] = $propvalue;
        if ($prop_unit && $prop_unit_val) {
            $arr['unit'] = $prop_unit;
            $arr['unit_val'] = $prop_unit_val;
        }
        $this->properties[] = $arr;
    }

    public function setCategory($catname, $catcode = NULL)
    {
        //тут могут быть проверки на адекватность данных
        $this->categories[] = ['name' => $catname, 'code' => $catcode];
    }

    public function getProductXML(DOMDocument $dom) : DOMElement
    {
        $product=$dom->createElement("Товар");
        $product->setAttribute('Код', $this->getCode());
        $product->setAttribute('Название', $this->getName());

        foreach($this->getPrices() as $price) {
            $dom_price=$dom->createElement('Цена', $price['val']);
            $dom_price->setAttribute('Тип', $price['name']);
            $product->appendChild($dom_price);
        }

        $properties=$dom->createElement("Свойства");
        $product->appendChild($properties);
        foreach($this->getProperties() as $prop) {
            $dom_prop=$dom->createElement($prop['name'], $prop['val']);
            $properties->appendChild($dom_prop);
        }

        $categories=$dom->createElement("Разделы");
        $product->appendChild($categories);
        foreach($this->getCategories() as $cat) {
            $dom_cat=$dom->createElement('Раздел', $cat['name']);
            $categories->appendChild($dom_cat);
        }

        return $product;
    }
}


function importXml($a)
{
    $data = zXmlArray::file_to_array($a);

    if (!is_array($data['Товары']['Товар'])) throw new Exception('Wrong XML structure (Tovari empty)');

    $link = new zSQL('localhost', 'root', '', 'test_samson');

    foreach ($data['Товары']['Товар'] as $k => $prod) {
        $Product = new Product();

        $prop1 = zXmlArray::getAttributes($prod);
        $Product->setName($prop1['Название']);
        $Product->setCode($prop1['Код']);

        $price = zXmlArray::getSpecificAttributePlusValue($prod['Цена'], 'Тип');
        foreach ($price as $pricename => $pricevalue) $Product->setPrice($pricename, $pricevalue);

        $properties = zXmlArray::getAssocKeyValue($prod['Свойства'][0]);
        foreach ($properties as $propname => $subprop) {
            foreach ($subprop as $propvalue) {
                $Product->setProperty($propname, $propvalue);
                echo $propname.' '.$propvalue;
            }
        }

        $categories = zXmlArray::getSpecificKeyPlusVal($prod['Разделы'][0], 'Раздел');
        foreach ($categories as $catname) $Product->setCategory($catname, NULL);

        $Product->insertData($link);
    }
}


function getAllSubcategories(zSQL $link, $b)
{
    $result = $link->query("SELECT cat_code FROM
    (SELECT * FROM a_category ORDER BY cat_parent, cat_code) category_sorted,
    (SELECT @pv :='?') INITIALISATION
    WHERE find_in_set(cat_parent, @pv)
    ;", $b); //AND length (@pv :=concat (@pv, ',', cat_parent))

    $cat_codes[] = (int)$b;
    foreach ($result[0] as $row) $cat_codes[] = (int)$row[0];
    return $cat_codes;
}


function exportXml($a, $b)
{

    if (!is_int($b) || $b < 0) throw new Exception("B is not numeric or not positive");
    $link = new zSQL('localhost', 'root', '', 'test_samson');

    //получаем все субкатегории, входящие в категорию $b
    $cat_codes = getAllSubcategories($link, $b);
    $cats = implode(',', $cat_codes);

    //получаем скисок товаров, входищих в эти категории
    $result2 = $link->query("SELECT prod_id FROM a_prodcat WHERE cat_code IN (?);", $cats);
    $prod_ids = NULL;
    foreach ($result2[0] as $row) $prod_ids[] = $row[0];


    $dom = new DOMDocument('1.0', 'utf-8');
    $dom->formatOutput = true;
    $dom_products = $dom->createElement("Товары");
    $dom->appendChild($dom_products);

    foreach ($prod_ids as $k => $id) {
        $Products[$k] = new Product();
        $Products[$k]->importProductFromDB($link, $id);
        $dom_products->appendChild(
            $Products[$k]->getProductXML($dom)
        );
    }

    $xml = $dom->saveXML();
    echo $xml;
    if (!file_put_contents($a, $xml)) throw new Exception("Cant write XML to file '$a'");
}

//var_dump(convertString('testANDtestANDtest', 'test'));
//$someA = [['a' => 3, 'b' => 3], ['a' => 1, 'b' => 1], ['a' => 2, 'b' => 2]];
//var_dump(mySortForKey($someA, 'b'));


//recreateTablesAndCategories();

importXml('2.xml');
exportXml('3.xml', 101);