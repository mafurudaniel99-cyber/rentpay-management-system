<?php
/**
 * config/database.php
 * Central PDO connection used by every module in the system.
 * Include this file, then call Database::getConnection().
 */

class Database
{
    private static ?PDO $connection = null;

    // ---- Update these to match your local / server environment ----
    private static string $host    = "localhost";
    private static string $dbName  = "rentpay_db";
    private static string $user    = "root";
    private static string $pass    = "";
    private static string $charset = "utf8mb4";

    public static function getConnection(): PDO
    {
        if (self::$connection === null) {
            $dsn = "mysql:host=" . self::$host . ";dbname=" . self::$dbName . ";charset=" . self::$charset;

            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ];

            try {
                self::$connection = new PDO($dsn, self::$user, self::$pass, $options);
            } catch (PDOException $e) {
                // Never leak DB credentials or raw exception details to the client.
                error_log("Database connection failed: " . $e->getMessage());
                http_response_code(500);
                die(json_encode(["error" => "Unable to connect to the database."]));
            }
        }
        return self::$connection;
    }
}
