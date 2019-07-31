# SimpleAPI
Библиотека для простого создания POST GET api  

## Подключение
composer require digitalstars/simple-api

## Пример
Скрипту обязательно должен приходить параметр module с названием модуля.  
* Первый параметр - Проверка на модуль  
* Второй параметр - необходимые параметры. Если поставить ? перед ним, то считается не обязательным. Если параметров нет, то ставьте пустой массив  
* Функция exit автоматически делает json_encode и возвращает браузеру json

site.ru/api.php?module=auth&login=123&password=123
```php
<?php
require_once 'vendor/autoload.php';
use DigitalStars\SimpleAPI;

$api = new SimpleAPI();
$api->module('auth', ['login', 'password'], function($data)use($api) {
    print $data['login'];
    $api->exit(['v' => '123']);
});
$api->module('registration', ['login', 'password', '?email'], function($data)use($api) {
    print $data['login'];
    $api->exit(['v' => '123']);
});
```

