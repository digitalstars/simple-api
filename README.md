# SimpleAPI
Библиотека для простого создания POST GET api  

## Подключение
composer require digitalstars/simple-api

## Пример
Скрипту обязательно должен приходить параметр module с названием модуля.  
* Первый параметр - Проверка на модуль  
* Второй параметр - необходимые параметры. Если поставить ? перед ним, то считается не обязательным. Если параметров нет, то ставьте пустой массив  
* В переменную answer добавляются необходимые данные для фронта. В конце выполнения модуля автоматически вызывается деструктор и данные в json формате отправляются фронту

Пример запроса:
site.ru/api.php?module=auth&login=123&password=123  
Ответ в json:  
{"auth" = true}
```php
<?php
require_once 'vendor/autoload.php';
use DigitalStars\SimpleAPI;

$api = new SimpleAPI();
$api->module('auth', ['login', 'password'], function($data)use($api) {
    $login = $data['login'];
    $api->answer['auth'] = true;
});
$api->module('registration', ['login', 'password', '?email'], function($data)use($api) {
    //...
});
```

