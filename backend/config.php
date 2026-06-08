<?php

require_once dirname(__DIR__) . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(dirname(__DIR__));
$dotenv->safeLoad();

function envValue(string $name, ?string $default = null): ?string
{
    $value = $_ENV[$name] ?? $_SERVER[$name] ?? getenv($name);
    return ($value === false || $value === null || $value === '') ? $default : (string) $value;
}

define('DB_HOST', envValue('DB_HOST', 'localhost'));
define('DB_PORT', envValue('DB_PORT', '3306'));
define('DB_NAME', envValue('DB_NAME', 'ar_presentations'));
define('DB_USER', envValue('DB_USER', 'root'));
define('DB_PASS', envValue('DB_PASS', ''));
define('DB_CHARSET', envValue('DB_CHARSET', 'utf8mb4'));

define('OPENAI_API_KEY', envValue('OPENAI_API_KEY', ''));
define('OPENAI_IMAGE_MODEL', envValue('OPENAI_IMAGE_MODEL', 'gpt-image-2'));
define('PUBLIC_BASE_URL', rtrim(envValue('PUBLIC_BASE_URL', 'http://localhost:8000'), '/'));
define('MAX_FILE_SIZE', (int) envValue('MAX_FILE_SIZE', (string) (20 * 1024 * 1024)));
define('API_RATE_LIMIT_SLEEP', (int) envValue('API_RATE_LIMIT_SLEEP', '5'));

define('STORAGE_DRIVER', strtolower(envValue('STORAGE_DRIVER', 'local')));
define('AWS_REGION', envValue('AWS_REGION', 'eu-central-1'));
define('AWS_S3_BUCKET', envValue('AWS_S3_BUCKET', ''));
define('AWS_S3_PREFIX', trim(envValue('AWS_S3_PREFIX', 'ar-presentations'), '/'));
define('AWS_S3_ENDPOINT', envValue('AWS_S3_ENDPOINT'));
define('AWS_S3_PATH_STYLE', filter_var(envValue('AWS_S3_PATH_STYLE', 'false'), FILTER_VALIDATE_BOOL));
define('AWS_ACCESS_KEY_ID', envValue('AWS_ACCESS_KEY_ID'));
define('AWS_SECRET_ACCESS_KEY', envValue('AWS_SECRET_ACCESS_KEY'));
define('AWS_SESSION_TOKEN', envValue('AWS_SESSION_TOKEN'));

define('STORAGE_DIR', __DIR__ . '/storage/');
define('UPLOAD_DIR', STORAGE_DIR . 'presentations/');
define('PROCESSED_DIR', STORAGE_DIR . 'processed/');
define('QR_DIR', STORAGE_DIR . 'qrcodes/');
define('AI_IMAGES_DIR', STORAGE_DIR . 'ai_generated/images/');
define('AI_TEXTS_DIR', STORAGE_DIR . 'ai_generated/texts/');
define('TEMP_IMAGES_DIR', STORAGE_DIR . 'temp_images/');

$dirs = [UPLOAD_DIR, PROCESSED_DIR, QR_DIR, AI_IMAGES_DIR, AI_TEXTS_DIR, TEMP_IMAGES_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException("Unable to create storage directory: {$dir}");
    }
}

require_once __DIR__ . '/src/Storage/FileStorage.php';
require_once __DIR__ . '/src/Storage/LocalStorage.php';
require_once __DIR__ . '/src/Storage/S3Storage.php';
require_once __DIR__ . '/src/Storage/storage_helpers.php';

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection failed: ' . $e->getMessage());
            throw new RuntimeException('Database connection failed. Please check configuration.', 0, $e);
        }
    }

    public static function getInstance()
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection()
    {
        return $this->connection;
    }

    private function __clone() {}

    public function __wakeup()
    {
        throw new Exception('Cannot unserialize singleton');
    }
}

function getDB()
{
    return Database::getInstance()->getConnection();
}
