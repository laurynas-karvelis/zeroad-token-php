<?php

declare(strict_types=1);

namespace ZeroAd\Token;

class Logger
{
  private static $levels = [
    "error" => 0,
    "warn" => 1,
    "info" => 2,
    "debug" => 3
  ];

  private static $currentLevel = "error";
  private static $logHandler = null;

  /**
   * Set the minimum log level
   *
   * @param string $level One of: error, warn, info, debug
   */
  public static function setLogLevel(string $level)
  {
    if (isset(self::$levels[$level])) {
      self::$currentLevel = $level;
    }
  }

  /**
   * Set custom log handler
   *
   * Example:
   * Logger::setLogHandler(function($level, $message) {
   *     error_log("[$level] $message");
   * });
   *
   * @param callable|null $handler Function that receives (string $level, string $message)
   */
  public static function setLogHandler($handler)
  {
    if ($handler === null || is_callable($handler)) {
      self::$logHandler = $handler;
    }
  }

  /**
   * Log a message at the specified level
   *
   * @param string $level Log level
   * @param mixed ...$args Arguments to log
   */
  public static function log(string $level, ...$args)
  {
    if (!isset(self::$levels[$level])) {
      return;
    }

    if (self::$levels[$level] > self::$levels[self::$currentLevel]) {
      return;
    }

    $msg =
      "[" .
      strtoupper($level) .
      "] " .
      implode(
        " ",
        array_map(function ($v) {
          return is_array($v) || is_object($v) ? json_encode($v) : (string) $v;
        }, $args)
      );

    if (self::$logHandler !== null) {
      call_user_func(self::$logHandler, $level, $msg);
    } else {
      // Use error_log instead of echo (doesn't break HTTP response)
      error_log($msg);
    }
  }
}
