<?php

namespace DigitalStars\SimpleAPI;

require_once __DIR__ . '/Exception.php';

class API {
    /** Объект API. Единый, доступный всем модулям
     * @var API
     */
    private static API $api;

    /** Входные параметры
     * @var array|mixed
     */
    public $data = [];
    /** Ответ от API
     * @var array|string
     */
    public $answer = [];
    /** Массив содержит поля API в которых обнаружена ошибка
     * @var array
     */
    private array $error_fields = [];
    /**  Функция, которая будет вызвана при генерации ошибки
     * @var callable|null
     */
    public $error_func = null;
    /** Сохранять ли ошибки в файл
     * @var bool
     */
    public bool $is_save_error = false;
    /** Игнорировать деструктор
     * @var bool
     */
    public bool $is_ignore_destruct = false;
    /** Игнорировать JSON-заголовок
     * @var bool
     */
    public bool $is_ignore_json_header = false;
    /** Время старта API
     * @var float
     */
    private float $time_start;
    /** Массив времени выполнения (логирование)
     * @var array
     */
    private array $timing_log = [];
    /** Вернуть ли в заголовок логирование времени
     * @var bool
     */
    public bool $is_log_time = true;
    /** Имя выполняемого в данный момент метода
     * @var string
     */
    private string $run_method_name = '';
    /** Имя модуля, в котором был вызван метод
     * @var string|null
     */
    private string $run_module_name = '';
    /** Массив функций, который будет вызван после завершения работы API
     * @var array
     */
    private array $before_destruct_func_list = [];
    /** Массив функций, который будет вызван после завершения работы API
     * @var array
     */
    private array $after_destruct_func_list = [];
    /** Ожидает ли клиент ответа в данный момент
     * @var bool
     */
    private bool $is_open_stream = true;
    /** Внутреннее хранилище, которое доступно всем модулям
     * @var array
     */
    public array $store = [];

    private function __construct() {
        $this->time_start = $this->timerStart();

        $this->data = $_POST + $_GET;
        if (!isset($this->data['method'])) {
            $raw_data = file_get_contents('php://input');
            if (empty($raw_data))
                $this->echo_error('data is empty');
            $this->data = json_decode($raw_data, 1);
            if ($this->data != null) {
                if (!isset($this->data['method'])) {
                    $this->echo_error('missed method');
                }
            } else {
                $this->echo_error('json invalid');
            }
        }
    }

    /** Вернуть объект API
     * @return API
     */
    public static function getApi(): API {
        if (empty(self::$api))
            self::$api = new self();
        return self::$api;
    }

    public function run(Module $module, \ReflectionClass $reflection = null) {
        if (!$reflection)
            $reflection = new \ReflectionClass($module);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            if ($method->isConstructor() || $method->isDestructor() || $method->isStatic())
                continue;

            $name = $method->getName();

            if ($name === 'run' || $name !== $this->data['method'])
                continue;

            $params = $method->getParameters();
            $anon = $method->getClosure($module);
            $this->run_method_name = $name;
            $this->run_module_name = $reflection->getName();


            if ($this->arrayKeysExist($params)) {
                $data = $this->filterData($params);
                $time_module = microtime(true);
                try {
                    $this->validate();
                    $answer_module = call_user_func_array($anon, $data);
                    if ($answer_module && empty($this->answer))
                        $this->answer = $answer_module;
                    $this->logTimerStop('Method', $time_module);
                } catch (\Throwable $e) {
                    $this->logTimerStop('Method', $time_module);
                    $this->echo_error($e instanceof Exception ? $e->getMessage() : $e->__toString());
                }
            } else {
                try {
                    $this->validate();
                    $this->echo_error('missed params');
                } catch (\Throwable $e) {
                    $this->echo_error($e instanceof Exception ? $e->getMessage() : $e->__toString());
                }
            }
            return;
        }
    }

    /** Добавить функцию, которая будет вызвана до завершения работы API
     * @param $func
     * @return $this
     */
    public function addBeforeDestructFunc($func): API {
        if (is_callable($func))
            $this->before_destruct_func_list[] = $func;
        return $this;
    }

    /** Добавить функцию, которая будет вызвана после завершения работы API
     * @param $func
     * @return $this
     */
    public function addAfterDestructFunc($func): API {
        if (is_callable($func))
            $this->after_destruct_func_list[] = $func;
        return $this;
    }

    /** Возвращает имя метода, который был вызван
     * @return string
     */
    public function getMethod(): string {
        return $this->run_method_name;
    }

    /** Возвращает имя модуля, который был вызван
     * @return string
     */
    public function getModule(): string {
        return $this->run_module_name;
    }

    public function __destruct() {
        if (!$this->is_open_stream || $this->is_ignore_destruct) {
            foreach ($this->after_destruct_func_list as $func) {
                $func($this);
            }
            return;
        }

        if (empty($this->after_destruct_func_list))
            exit($this->closeAPI());

        $this->close();

        foreach ($this->after_destruct_func_list as $func) {
            $func($this);
        }
    }

    /** Завершение работы API
     * @return array|false|string - вывод, который возвращает API
     */
    private function closeAPI() {
        foreach ($this->before_destruct_func_list as $i => $func) {
            $time_log = $this->timerStart();
            $func($this);
            $this->logTimerStop('before_destruct_func_' . $i, $time_log);
        }

        if (!$this->is_ignore_json_header)
            header('Content-Type: application/json');

        $this->logTimerStop('Script', $this->time_start);
        if ($this->is_log_time) {
            $log_for_header = [];
            foreach ($this->timing_log as $log) {
                $log_for_header[] = "api;dur=$log[1];desc=\"$log[0]\"";
            }
            header('Server-Timing: ' . implode(', ', $log_for_header));
        }

        if (!empty($this->error_fields) && !$this->is_ignore_json_header)
            $this->answer['error_fields'] = $this->error_fields;

        if ($this->is_ignore_json_header)
            return $this->answer;
        else
            return json_encode($this->answer, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    /** Закрыть поток вывода (для Nginx и Apache). Скрипт продолжит выполнение
     * @return bool
     */
    public function close(): bool {
        if (!$this->is_open_stream || $this->isChildProcess())
            return true;

        $this->is_open_stream = false;

        $content = $this->closeAPI();
        echo $content;

        // для Nginx
        if (is_callable('fastcgi_finish_request')) {
            session_write_close();
            fastcgi_finish_request();
            return True;
        }
        // для Apache
        ignore_user_abort(true);

        ob_start();
        header('Content-Encoding: none');
        header('Content-Length: ' . mb_strlen($content, '8bit'));
        header('Connection: close');
        ob_end_flush();
        flush();
        return True;
    }

    /** Вывод ошибки
     * @param string $text
     * @return void
     * @throws \Exception
     */
    public function error(string $text): void {
        $e = new Exception($text);
        $this->saveError($e);
        throw $e;
    }

    /** Выводить ошибку в ответ и завершает выполнение
     * @param string $text
     * @return void
     */
    private function echo_error(string $text): void {
        http_response_code(501);
        if ($this->is_ignore_json_header)
            $this->answer = 'error:' . $text;
        else
            $this->answer['error'] = $text;
        exit();
    }

    /** Возвращает время старта API
     * @return float
     */
    public function getTimeStart(): float {
        return $this->time_start;
    }

    /** Засечь таймер
     * @return mixed - время начала
     */
    public function timerStart() {
        return microtime(true);
    }

    /** Зарегистрировать время таймера (по времени начала)
     * @param string $name - имя таймера
     * @param float $time_start - время начала
     * @return float|int - время выполнения
     */
    public function logTimerStop(string $name, float $time_start): float {
        if (!$this->is_log_time)
            return 0;
        $time_exec = (microtime(true) - $time_start) * 1000;
        $this->logTimer($name, $time_exec);
        return $time_exec;
    }

    /** Зарегистрировать время таймера
     * @param $name - имя таймера
     * @param $time_duration - время
     * @return void
     */
    public function logTimer($name, $time_duration): void {
        $this->timing_log[] = [$name, $time_duration];
    }

    /** Возвращает массив времени выполнения
     * @return array
     */
    public function getTimerLog(): array {
        return $this->timing_log;
    }

    /** Проверка входных данных модуля
     * @param $params
     * @return bool
     */
    private function arrayKeysExist($params): bool {
        foreach ($params as $param) {
            $field = $param->getName();
            $type = $param->getType() ? $param->getType()->getName() : null;
            $is_strict = !$param->isOptional();

            if (!$is_strict) {
                if ($type)
                    $this->valid($field, $type, false);
                else if (empty($this->data[$field]))
                    $this->data[$field] = null;
                else if (isset($this->data[$field]) && is_string($this->data[$field]))
                    $this->data[$field] = trim($this->data[$field]);
                continue;
            }

            if (!array_key_exists($field, $this->data))
                return false;
            if ($type)
                $this->valid($field, $type, true);
            else if (is_string($this->data[$field])) {
                $this->data[$field] = trim($this->data[$field]);
            }
        }
        return true;
    }

    /** Фильтрация данных (удаляет те, которые не указаны в параметрах)
     * @param $params
     * @return array
     * @throws \ReflectionException
     */
    private function filterData($params): array {
        $new_data = [];
        /** @var \ReflectionParameter $param */
        foreach ($params as $param) {
            $param_name = $param->getName();
            if (isset($this->data[$param_name]))
                $new_data[] = $this->data[$param_name];
            else
                $new_data[] = $param->getDefaultValue();
        }
        return $new_data;
    }

    /** Сохранить ошибку в файл
     * @param \Throwable $e
     * @return void
     */
    private function saveError(\Throwable $e): void {
        if (is_callable($this->error_func)) {
            call_user_func($this->error_func, $e);
        }
        $this->printError($e);
    }

    /** Метод работы с файлом при сохранении ошибки
     * @param \Throwable $e - исключение
     * @return void
     */
    private function printError(\Throwable $e): void {
        if (!$this->is_save_error)
            return;

        $error = "Message: {$e->getMessage()}";
        $error .= "\r\nin: {$e->getFile()}:{$e->getLine()}";
        $error .= "\r\nStack trace:\r\n{$e->getTraceAsString()}\r\n\r\n";

        $error_path = __DIR__ . "/../error/";
        if (!is_dir($error_path)) {
            if (!mkdir($error_path) && !is_dir($error_path))
                throw new \RuntimeException(sprintf('Directory "%s" was not created', $error_path));
        }
        $path_file = $error_path . date('d.m.y H:i:s') . ".log";
        $file = fopen($path_file, 'a');

        $error = "[Exception] " . date("d.m.y H:i:s") . "\r\n" . $error;

        if (isset($this->data))
            $error .= "\r\nData: " . json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (isset($_SESSION))
            $error .= "\r\nSession: " . json_encode($_SESSION, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        fwrite($file, $error);
        fclose($file);
    }

    /** Валидация входных данных
     * @param $field - поле
     * @param $type - тип
     * @param bool $is_strict - строгая валидация
     * @return void
     */
    private function valid($field, $type, bool $is_strict = false): void {
        if (!isset($this->data[$field]) && $is_strict) {
            $this->error_fields[] = $field;
            return;
        }
        if ($type === 'float')
            $type = 'double';
        switch ($type) {
            case 'int':
                if (!$is_strict && !isset($this->data[$field])) {
                    break;
                }
                if (!is_numeric($this->data[$field]))
                    $this->error_fields[] = $field;
                else
                    $this->data[$field] = (int)$this->data[$field];
                break;
            case 'double':
                if (!$is_strict && !isset($this->data[$field])) {
                    break;
                }
                $this->data[$field] = str_replace(',', '.', $this->data[$field]);
                if (!is_numeric($this->data[$field]))
                    $this->error_fields[] = $field;
                else
                    $this->data[$field] = (double)$this->data[$field];
                break;
            case 'bool':
                if (!$is_strict && !isset($this->data[$field])) {
                    break;
                }
                if (is_string($this->data[$field]) && $this->data[$field] === 'false')
                    $this->data[$field] = false;
                else if ($this->data[$field] == 1 || $this->data[$field] == 0)
                    $this->data[$field] = (bool)$this->data[$field];
                else
                    $this->error_fields[] = $field;
                break;
            case 'array':
                if (!$is_strict && !isset($this->data[$field])) {
                    break;
                }
                if ($is_strict) {
                    if (empty($this->data[$field]) || !is_array($this->data[$field]))
                        $this->error_fields[] = $field;
                } else {
                    if (isset($this->data[$field]) && !is_array($this->data[$field]))
                        $this->error_fields[] = $field;
                }
                break;
            case 'string':
                if (!$is_strict && !isset($this->data[$field])) {
                    break;
                }
                if ($is_strict) {
                    if (!isset($this->data[$field]) || !is_string($this->data[$field]))
                        $this->error_fields[] = $field;
                } else if (!is_string($this->data[$field])) {
                    $this->error_fields[] = $field;
                }

                $this->data[$field] = htmlentities($this->data[$field], ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, null, false);
                break;
        }
    }

    /** Валидация типа: число
     * @param string $field - поле
     * @return $this
     */
    public function valid_int(string $field): API {
        if (empty($this->data[$field]))
            return $this;
        if (!is_numeric($this->data[$field]))
            $this->error_fields[] = $field;
        else
            $this->data[$field] = (int)$this->data[$field];
        return $this;
    }

    /** Валидация типа: число с плавающей точкой
     * @param string $field - поле
     * @return $this
     */
    public function valid_double(string $field): API {
        if (empty($this->data[$field]))
            return $this;
        if (!is_numeric($this->data[$field]))
            $this->error_fields[] = $field;
        else
            $this->data[$field] = (double)$this->data[$field];
        return $this;
    }

    /** Валидация типа: логическое значение
     * @param string $field - поле
     * @return $this
     */
    public function valid_bool(string $field): API {
        if (empty($this->data[$field]))
            return $this;
        if ($this->data[$field] == 1 || $this->data[$field] == 0)
            $this->data[$field] = (bool)$this->data[$field];
        else
            $this->error_fields[] = $field;
        return $this;
    }

    /** Валидация типа: массив
     * @param string $field - поле
     * @return $this
     */
    public function valid_array(string $field): API {
        if (empty($this->data[$field]) || !is_array($this->data[$field]))
            $this->error_fields[] = $field;
        return $this;
    }

    /** Валидация значения по регулярному выражению
     * @param string $preg - регулярное выражение
     * @param string $field - поле
     * @return $this
     */
    public function valid_preg(string $preg, string $field): API {
        if (empty($this->data[$field]))
            return $this;
        if (!preg_match($preg, $this->data[$field]))
            $this->error_fields[] = $field;
        return $this;
    }

    /** Обработка валидаций
     * @param bool $is_send_exception - отправлять ли исключение
     * @return bool - результат валидации
     * @throws \Exception
     */
    public function validate(bool $is_send_exception = true): bool {
        $tmp_error_fields = $this->error_fields;
        $is_invalid = !empty($this->error_fields);
        $this->error_fields = [];
        if ($is_invalid && $is_send_exception)
            $this->error('error_fields: ' . implode(',', $tmp_error_fields));
        return !$is_invalid;
    }

    /** Пометить поле как невалидное
     * @param string $field - поле
     * @return $this
     */
    public function field_invalid(string $field): API {
        $this->error_fields[] = $field;
        return $this;
    }
}
