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

  public static function setLogLevel(string $level)
  {
    if (isset(self::$levels[$level])) {
      self::$currentLevel = $level;
    }
  }

  public static function log(string $level, ...$args)
  {
    if (self::$levels[$level] <= self::$levels[self::$currentLevel]) {
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
      echo $msg . PHP_EOL;
    }
  }
}
