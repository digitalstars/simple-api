<?php

namespace DigitalStars\SimpleAPI;

class Module {
    /** Экземпляр SimpleAPI
     * @var API
     */
    protected API $api;
    public function __construct() {
        $this->api = API::getApi();
    }

    public static function create() {
        return new static();
    }

    public function run(): void {
        $ref = new \ReflectionClass($this);
        if ($ref->getFileName() === $_SERVER['SCRIPT_FILENAME'])
            $this->api->run($this, $ref);
    }
}
