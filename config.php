<?php
require_once __DIR__ . '/vendor/autoload.php';

try {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->load();
    $dotenv->required(['OPENAI_API_KEY'])->notEmpty();
} catch (Exception $e) {
    die('Error loading environment variables: ' . $e->getMessage());
}

define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'ar_presentations');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');


define('OPENAI_API_KEY', $_ENV['OPENAI_API_KEY']);
define('PUBLIC_BASE_URL', 'https://latarsha-casebound-bernard.ngrok-free.dev/Final_project_WEB/');
define('UPLOAD_DIR', __DIR__ . '/uploads/presentations/');
define('PROCESSED_DIR', __DIR__ . '/uploads/processed/');
define('QR_DIR', __DIR__ . '/qrcodes/');
define('AI_IMAGES_DIR', __DIR__ . '/uploads/ai_generated/images/');
define('AI_TEXTS_DIR', __DIR__ . '/uploads/ai_generated/texts/');
define('TEMP_IMAGES_DIR', __DIR__ . '/uploads/temp_images/');
define('MAX_FILE_SIZE', 20 * 1024 * 1024);

// Seconds to wait between API calls to avoid rate limits (only used when MOCK_MODE is false)
define('API_RATE_LIMIT_SLEEP', 5);

$dirs = [UPLOAD_DIR, PROCESSED_DIR, QR_DIR, AI_IMAGES_DIR, AI_TEXTS_DIR, TEMP_IMAGES_DIR];
foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

class Database
{
    private static $instance = null;
    private $connection;

    private function __construct()
    {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please check configuration.");
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
        throw new Exception("Cannot unserialize singleton");
    }
}

function getDB()
{
    return Database::getInstance()->getConnection();
}
