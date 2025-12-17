<?php

// cspell:words EXTR
function render(string $template, array $data = []): string
{
  if (!preg_match('/^[a-zA-Z0-9_-]+$/', $template)) {
    throw new RuntimeException("Invalid template");
  }

  extract($data, EXTR_SKIP);
  ob_start();
  include __DIR__ . "/templates/$template.php";

  return ob_get_clean();
}
