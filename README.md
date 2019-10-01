# SimpleAPI

## Подключение
composer require digitalstars/simple-api

## Как с этим работать
Скрипту обязательно должен приходить параметр module с названием модуля.  
* Первый параметр - название модуля
* Остальные параметры - необходимые данные. Если в функции params поставить ? перед параметром, то он считается необязательным
* В переменную answer добавляются необходимые данные для фронта. В конце выполнения кейса, библиотека автоматически вызывает деструктор и данные в json формате отправляются фронту.
* если вам удобнее передавать много данный в json формате, то передавайте его в параметр json_data
* есть метод error, в который передается текст. Этот метод завершает скрипт и возвращает на фронт json вида:
```{"error":"ваш текст"}```

* Если не указать хотя бы один из необходимых параметров, то вернется json:
```{"error":"missed params"}```

## Пример №1 Обычное использование
Запрос:  
```site.ru/api.php?module=auth&login=admin&password=admin```  

```php
<?php
require_once 'lib/vendor/autoload.php';
use DigitalStars\SimpleAPI;

$api = new SimpleAPI();
switch ($api->module) {
    case 'auth':
        $data = $api->params(['login', 'password']); //если одного из параметров не будет, скрипт завершится с error
        $api->answer['auth'] = ($data['login'] == 'admin' && $data['password'] == 'admin');
}
```
Ответ в json:
```{"auth" : true}```


## Пример №2 Использование необязательных параметров
Запрос:  
```site.ru/api.php?module=reg&login=admin&password=admin&name=Fedor```  

```php
<?php
require_once 'lib/vendor/autoload.php';
use DigitalStars\SimpleAPI;

$api = new SimpleAPI();
switch ($api->module) {
    case 'auth':
        $data = $api->params(['login', 'password', '?name']);
        if(isset($data['name']]))
            $api->answer['status'] = true;
        else
            $api->answer['status'] = false;
}
```
Ответ в json:
```{"status" : true}```

## Пример №3 использование функции error
Запрос:  
```site.ru/api.php?module=reg&login=admin&password=admin&age=10```  

```php
<?php
require_once 'lib/vendor/autoload.php';
use DigitalStars\SimpleAPI;

$api = new SimpleAPI();
switch ($api->module) {
    case 'auth':
        $data = $api->params(['login', 'password', 'age']);
        if($data['age'] < 18)
            $api->error('Извини, но тебе меньше 18 лет');
}
```
Ответ в json:
```{"error" : "Извини, но тебе меньше 18 лет"}```


## Пример №4 передача json
Как видите, никакого различия в коде по сравнению с 1 примером  
Запрос:  
```site.ru/api.php?module=auth&json_data={"login":"123","password":"123"}```

```php
<?php
require_once 'lib/vendor/autoload.php';
use DigitalStars\SimpleAPI;

$api = new SimpleAPI();
switch ($api->module) {
    case 'auth':
        $data = $api->params(['login', 'password']);
        $api->answer['auth'] = ($data['login'] == 'admin' && $data['password'] == 'admin');
}
```
Ответ в json:
```{"auth" : false}```
