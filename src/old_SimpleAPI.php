<?php

namespace DigitalStars\SimpleAPI;

class old_SimpleAPI {
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
    /** Текст ошибки
     * @var string|null
     */
    private ?string $error_text = null;
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
    /** Время работы модуля
     * @var float
     */
    private float $time_module = 0;
    /** Имя выполняемого в данный момент модуля
     * @var string
     */
    private string $run_module_name = '';
    /** Имя файла, в котором был вызван модуль
     * @var string|null
     */
    private ?string $log_filename = '';
    /** Массив всех зарегистрированных модулей и файлов
     * @var array
     */
    private array $list_modules = [];
    /** Имя файла точки вхождения
     * @var string|null
     */
    private ?string $root_filename = null;
    /** Имя файла дополнительного файла с модулями, если он есть
     * @var string|null
     */
    private ?string $sub_filename = null;
    /** Дочерний ли модуль в данный момент выполняется
     * @var bool
     */
    private bool $is_child_process = false;
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

    /** Создать объект SimpleAPI
     * @param float|int|null $time_start - время старта API (microtime)
     * @throws \Exception
     */
    public function __construct($time_start = null) {
        if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
            exit();
        }
        $this->time_start = $time_start ? (float)$time_start : $this->timerStart();

        $this->data = $_POST + $_GET;
        if (!isset($this->data['module'])) {
            $this->data = json_decode(file_get_contents('php://input'), 1);
            if ($this->data != null) {
                if (!isset($this->data['module'])) {
                    $this->error('missed module');
                    $this->printError("missed module");
                }
            } else {
                $this->error('json invalid');
                $this->printError("json invalid");
            }
        }
        $this->rootFilename($_SERVER['SCRIPT_FILENAME']);
    }

    /** Создать объект SimpleAPI
     * @param $time_start
     * @return self
     * @throws \Exception
     */
    public static function create($time_start = null): old_SimpleAPI {
        return new self($time_start);
    }

    /** Добавить функцию, которая будет вызвана до завершения работы API
     * @param $func
     * @return $this
     */
    public function addBeforeDestructFunc($func): old_SimpleAPI {
        if (is_callable($func))
            $this->before_destruct_func_list[] = $func;
        return $this;
    }

    /** Добавить функцию, которая будет вызвана после завершения работы API
     * @param $func
     * @return $this
     */
    public function addAfterDestructFunc($func): old_SimpleAPI {
        if (is_callable($func))
            $this->after_destruct_func_list[] = $func;
        return $this;
    }

    /** Проверяет, является ли текущий модуль дочерним
     * @return bool
     */
    public function isChildProcess(): bool {
        return $this->is_child_process;
    }

    /** Зарегистрировать корневой файл (принимает __FILE__)
     * @param string $file
     * @return void
     */
    public function rootFilename(string $file): void {
        $this->rawRootFilename(pathinfo($file, PATHINFO_FILENAME));
    }

    /** Зарегистрировать корневой файл (принимает имя файла)
     * @param $file
     * @return void
     */
    public function rawRootFilename($file): void {
        if (empty($this->root_filename))
            $this->root_filename = $file;
        else
            $this->sub_filename = $file;
        if ($this->root_filename === $this->sub_filename)
            $this->sub_filename = null;
    }

    /** Возвращает имя файла, в котором был вызван метод
     * @return string
     */
    public function getFilename(): string {
        return $this->log_filename;
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

    /** Вывод ошибки
     * @param string $text
     * @return void
     * @throws \Exception
     */
    public function error(string $text): void {
        $this->error_text = $text;
        $this->saveError(new \Exception($text));
        if (!$this->is_child_process) {
            exit();
        } else {
            $this->is_child_process = false;
            throw new \Exception("Error_children_process", 221);
        }
    }

    /** Завершение работы API
     * @return array|false|string - вывод, который возвращает модуль API
     */
    private function closeAPI() {
        foreach ($this->before_destruct_func_list as $i => $func) {
            $time_log = $this->timerStart();
            $func($this);
            $this->logTimerStop('before_destruct_func_' . $i, $time_log);
        }

        if (!$this->is_ignore_json_header)
            header('Content-Type: application/json');
        if (!empty($this->error_text)) {
            if ($this->is_ignore_json_header)
                $this->answer = $this->error_text;
            else
                $this->answer['error'] = $this->error_text;
            http_response_code(501);
        }

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

    /** Закрыть поток вывода (для Nginx и Apache). Модуль продолжит выполнение
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

    /** Регистрирует новый модуль
     * @param string $name - имя модуля
     * @param array $params - параметры модуля
     * @param callable $anon - анонимная функция модуля
     * @return void
     */
    public function module(string $name, array $params, callable $anon): void {
        if ($this->sub_filename && $this->sub_filename !== $this->root_filename) {
            $this->list_modules[$this->sub_filename][$name] = [
                'params' => $params,
                'anon' => $anon
            ];
            return;
        } else {
            $this->list_modules[$this->root_filename][$name] = [
                'params' => $params,
                'anon' => $anon
            ];
        }
        if ($this->data['module'] === $name) {
            $this->run_module_name = $name;
            $this->log_filename = $this->root_filename;
            if ($this->arrayKeysExist($params)) {
                $this->time_module = microtime(true);
                try {
                    $this->validate();
                    $answer_module = $anon($this->data);
                    if ($answer_module && empty($this->answer))
                        $this->answer = $answer_module;
                    $this->logTimerStop('Module', $this->time_module);
                } catch (\Throwable $e) {
                    http_response_code(501);
                    $this->saveError($e);
                    $this->logTimerStop('Module', $this->time_module);
                }
            } else
                $this->error('missed params');
            exit();
        }
    }

    /** Вызвать дочерний модуль
     * @param string $filename - имя файла, в котором находится модуль
     * @param string $module - имя модуля
     * @param array $data - данные, которые будут переданы модулю
     * @return array|mixed - результат работы модуля
     * @throws \Exception
     */
    public function runModule(string $filename, string $module, array $data = []) {
        $this->is_child_process = true;
        if (!isset($this->list_modules[$filename][$module]))
            throw new \Exception('Дочерний модуль не найден');
        $tmp_answer = $this->answer;
        $this->answer = [];
        $tmp_data = $this->data;
        $tmp_module = $this->run_module_name;
        $this->data = &$data;
        $this->run_module_name = $module;
        $this->log_filename = $filename;
        if ($this->arrayKeysExist($this->list_modules[$filename][$module]['params'])) {
            $time_module_2 = microtime(true);
            $this->validate();
            try {
                $answer_module = call_user_func_array($this->list_modules[$filename][$module]['anon'], [&$data]);
                if ($answer_module && empty($this->answer))
                    $this->answer = $answer_module;
            } catch (\Exception $e) {
                if ($e->getCode() !== 221)
                    throw $e;
            }
            $this->logTimerStop("subModule:$filename:$module", $time_module_2);
        } else {
            if ($this->is_ignore_json_header)
                $this->answer = 'missed params';
            else
                $this->answer['error'] = 'missed params';
        }
        $result = $this->answer;
        $this->answer = $tmp_answer;
        unset($this->data);
        $this->data = $tmp_data;
        $this->is_child_process = false;
        $this->run_module_name = $tmp_module;
        $this->log_filename = $this->root_filename;
        return $result;
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
     * @param $keys
     * @return bool
     */
    private function arrayKeysExist($keys): bool {
        foreach ($keys as $key) {
            $arr = explode(':', $key);
            $field = $arr[0];
            $type = $arr[1] ?? null;
            if ($field[0] == '?') {
                if ($type)
                    $this->valid(substr($field, 1), $type, false);
                else {
                    $real_field = substr($field, 1);
                    if (empty($this->data[$real_field]))
                        $this->data[$real_field] = null;
                    else if (isset($this->data[$real_field]) && is_string($this->data[$real_field]))
                        $this->data[$real_field] = trim($this->data[$real_field]);
                }
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

    /** Сохранить ошибку в файл
     * @param \Throwable $e
     * @return void
     */
    private function saveError(\Throwable $e): void {
        if (is_callable($this->error_func)) {
            call_user_func($this->error_func, $e);
        }
        if (!$this->is_save_error)
            return;
        $error = "Message: {$e->getMessage()}";
        $error .= "\r\nin: {$e->getFile()}:{$e->getLine()}";
        $error .= "\r\nStack trace:\r\n{$e->getTraceAsString()}\r\n\r\n";
        $this->printError($error);
    }

    /** Метод работы с файлом при сохранении ошибки
     * @param $error - текст ошибки
     * @return void
     */
    private function printError($error): void {
        if (!$this->is_save_error)
            return;

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
        switch ($type) {
            case 'int':
                if (!$is_strict && !isset($this->data[$field])) {
                    $this->data[$field] = null;
                    break;
                }
                if (!is_numeric($this->data[$field]))
                    $this->error_fields[] = $field;
                else
                    $this->data[$field] = (int)$this->data[$field];
                break;
            case 'double':
                if (!$is_strict && !isset($this->data[$field])) {
                    $this->data[$field] = null;
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
                    $this->data[$field] = null;
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
                    $this->data[$field] = [];
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
            case 'txt':
                if (!$is_strict && !isset($this->data[$field])) {
                    $this->data[$field] = null;
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
    public function valid_int(string $field): old_SimpleAPI {
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
    public function valid_double(string $field): old_SimpleAPI {
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
    public function valid_bool(string $field): old_SimpleAPI {
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
    public function valid_array(string $field): old_SimpleAPI {
        if (empty($this->data[$field]) || !is_array($this->data[$field]))
            $this->error_fields[] = $field;
        return $this;
    }

    /** Валидация значения по регулярному выражению
     * @param string $preg - регулярное выражение
     * @param string $field - поле
     * @return $this
     */
    public function valid_preg(string $preg, string $field): old_SimpleAPI {
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
        if (!empty($this->error_fields) && $is_send_exception)
            $this->error('error_fields');
        return !isset($this->error_fields);
    }

    /** Пометить поле как невалидное
     * @param string $field - поле
     * @return $this
     */
    public function field_invalid(string $field): old_SimpleAPI {
        $this->error_fields[] = $field;
        return $this;
    }
}
