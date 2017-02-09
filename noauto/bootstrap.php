<?php
ini_set('error_reporting', E_ALL);
define('MOCK_ALL_REQUESTS', true);

/* mock COREPOS API classes for testing */

if (!class_exists('COREPOS\\pos\\plugins\\Plugin', false)) {
    include(__DIR__ . '/mocks/Plugin.php');
}

if (!class_exists('COREPOS\\pos\\lib\\AjaxCallback', false)) {
    include(__DIR__ . '/mocks/AjaxCallback.php');
}

if (!class_exists('COREPOS\\pos\\lib\\Notifier', false)) {
    include(__DIR__ . '/mocks/Notifier.php');
}

if (!class_exists('AutoLoader', false)) {
    class AutoLoader 
    {
        public static function dispatch(){}
    }
}

if (!class_exists('COREPOS\\pos\\lib\\gui\\BasicCorePage', false)) {
    include(__DIR__ . '/mocks/BasicCorePage.php');
}

if (!class_exists('COREPOS\\pos\\lib\\gui\\NoInputCorePage', false)) {
    include(__DIR__ . '/mocks/NoInputCorePage.php');
}

if (!class_exists('COREPOS\\pos\\parser\\Parser', false)) {
    include(__DIR__ . '/mocks/Parser.php');
}

if (!class_exists('COREPOS\\pos\\lib\\DisplayLib', false)) {
    include(__DIR__ . '/mocks/DisplayLib.php');
}

if (!class_exists('COREPOS\\pos\\lib\\MiscLib', false)) {
    include(__DIR__ . '/mocks/MiscLib.php');
}

if (!class_exists('COREPOS\\pos\\lib\\TransRecord', false)) {
    include(__DIR__ . '/mocks/TransRecord.php');
}

if (!class_exists('COREPOS\\pos\\lib\\ReceiptLib', false)) {
    include(__DIR__ . '/mocks/ReceiptLib.php');
}

if (!class_exists('SQLManager', false)) {
    class SQLManager
    {
        private static $mock = array();

        public function __construct($host, $dbms, $db, $user, $passwd)
        {
        }

        public function insertID()
        {
            return 1;
        }

        public function curdate()
        {
            return 'curdate()';
        }

        public function now()
        {
            return 'now()';
        }

        public static function addResult($row)
        {
            self::$mock[] = $row;
        }

        public static function clear()
        {
            self::$mock = array();
        }

        public function sep()
        {
            return '.';
        }

        public function query($str)
        {
            return true;
        }

        public function numRows($res)
        {
            return count(self::$mock);
        }

        public function fetchRow($res)
        {
            $row = array_shift(self::$mock);
            return $row === null ? false : $row;
        }

        public function getRow($prep, $args=array())
        {
            return $this->fetchRow(null);
        }

        public function prepare($query)
        {
            return $query;
        }

        public function execute($prep, $args)
        {
            return true;
        }
    }
}

if (!class_exists('COREPOS\\pos\\lib\\Database', false)) {
    include(__DIR__ . '/mocks/Database.php');
}

if (!class_exists('CoreLocal', false)) {
    class CoreLocal
    {
        private static $data = array();
        public static function get($k)
        {
            return isset(self::$data[$k]) ? self::$data[$k] : '';
        }

        public static function set($k, $v)
        {
            self::$data[$k] = $v;
        }
    }

    class CLWrapper
    {
        function get($k)
        {
            return CoreLocal::get($k);
        }

        function set($k, $v)
        {
            CoreLocal::set($k, $v);
        }
    }
}

if (!class_exists('COREPOS\\pos\\lib\\PrehLib', false)) {
    include(__DIR__ . '/mocks/PrehLib.php');
}
if (!class_exists('COREPOS\\pos\\lib\\PrintHandlers\\PrintHandler', false)) {
    include(__DIR__ . '/mocks/PrintHandler.php');
}

if (!class_exists('COREPOS\\pos\\lib\\MemberLib')) {
    include(__DIR__ . '/mocks/MemberLib.php');
}

if (!class_exists('COREPOS\\pos\\parser\\parse\\Void')) {
    include(__DIR__ . '/mocks/Void.php');
}

if (!class_exists('COREPOS\\pos\\lib\\DeptLib')) {
    include(__DIR__ . '/mocks/DeptLib.php');
}

if (!class_exists('COREPOS\\pos\\lib\\FormLib')) {
    include(__DIR__ . '/mocks/FormLib.php');
}

if (!class_exists('COREPOS\\pos\\lib\\Authenticate')) {
    include(__DIR__ . '/mocks/Authenticate.php');
}

if (!class_exists('COREPOS\\pos\\lib\\Database')) {
    include(__DIR__ . '/mocks/Database.php');
}

if (!class_exists('COREPOS\\pos\\lib\\UdpComm')) {
    include(__DIR__ . '/mocks/UdpComm.php');
}

include(__DIR__ . '/self.php');

