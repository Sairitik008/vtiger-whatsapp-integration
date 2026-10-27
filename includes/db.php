<?php
/**
 * Database Connection Wrapper (PDO Singleton)
 * Secure, reusable, connection-pooled database handler.
 */

date_default_timezone_set('Asia/Kolkata');

class Database {
    private static ?PDO $instance = null;

    private const DB_HOST = '127.0.0.1';
    private const DB_PORT = '3306';
    private const DB_NAME = 'vtiger_whatsapp_crm';
    private const DB_USER = 'root';
    private const DB_PASS = '';

    private function __construct() {}
    private function __clone() {}

    public static function getConnection(): PDO {
        if (self::$instance === null) {
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            $ports = [3307, 3306];
            $lastException = null;

            foreach ($ports as $port) {
                try {
                    $dsn = sprintf(
                        'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
                        self::DB_HOST,
                        $port,
                        self::DB_NAME
                    );
                    self::$instance = new PDO($dsn, self::DB_USER, self::DB_PASS, $options);
                    break;
                } catch (PDOException $e) {
                    $lastException = $e;
                }
            }

            if (self::$instance === null) {
                // Return clean JSON error in API mode or throw in app mode
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error'   => 'Database connection failed: ' . ($lastException ? $lastException->getMessage() : 'Unknown error')
                ]);
                exit;
            }
        }
        return self::$instance;
    }

    /**
     * Standardizes phone numbers to E.164 digits-only format for accurate mapping.
     * Example: '+91 98230-12345' -> '919823012345'
     */
    public static function normalizePhone(string $phone): string {
        // Strip non-digits
        $digits = preg_replace('/[^0-9]/', '', $phone);
        
        // If 10 digits (India domestic number without country code), prepend '91'
        if (strlen($digits) === 10) {
            $digits = '91' . $digits;
        }
        // If starts with '0' followed by 10 digits (e.g. 09823012345)
        if (strlen($digits) === 11 && str_starts_with($digits, '0')) {
            $digits = '91' . substr($digits, 1);
        }

        return $digits;
    }
}
