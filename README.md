# Koyabu Framework

## Installation
```composer require koyabu/webapi```

## composer.json
If your get error about minimum-stability, edit your composer.json add add/edit this param
```
    "minimum-stability": "dev",
    "prefer-stable": false
```

## config.php
For configuration example you can see file: vendor/koyabu/webapi/config.sample.php
```
namespace Koyabu\Webapi;
require_once 'vendor/autoload.php';

$config = [];
$config = [];
$config['APPS_NAME'] = 'KOYABU PHP FRAMEWORK';
$config['HOME_DIR'] = __DIR__ . DIRECTORY_SEPARATOR;

$config['mysql'] = array(
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'data' => 'test'
);
$API = new Koyabu($config);
?>
```

Dropbox Save, don't forget to setup your Dropbox secret key & token at $config['dropbox'], you see can see example
config.sample.php
```
$API = new Koyabu($config);
$filename = 'test.txt';
var_dump($API->save_dbx($filename));
```

## MySQL/MariaDB Query
```
// INSERT
$params = array(
    'id' => $id,
    'name' => $name
);
$primary_index = 'id';
$API->save($params,$table_name,'INSERT',$primary_index);
$API->save($params,$table_name,'REPLACE',$primary_index);
$API->save($params,$table_name,'UPDATE',$primary_index);
```
Params = coloumn name => value
name your array index with database table coloumn name.
$primary_index default = id,

```
// DELETE
$params = array(
    'db_colname' => $value
)
$API->delete($params,$table_name);
```
Use $params index name same as table coloumn name
and query will delete data where db_colname = $value

```
// Select
$qry = $API->select("select * from test");
while($result = $API->fetch()) {
    print_r($result);
}

$qry = $API->select("select * from test");
while($result = $API->fetch('row')) {
    print_r($result);
}
```