<?php
declare(strict_types=1);

class MarkdownDocument
{
  public private(set) array $properties;
  public private(set) string $body = '';
  public private(set) string $title = '';

  public private(set) object $props {
    get => $this->props ??= $this->readOnlyProperties();
  }

  public static function load(string $filename) : ?self
  {
    if (!is_readable($filename)) {
      return null;
    }
    return new self($filename)->parse(file_get_contents($filename));
  }

  public static function from(string $text) : self
  {
    return new self('')->parse($text);
  }

  private function __construct(
    public private(set) string $filename,
  ) {}

  private function parse(string $text) : self
  {
    [ $frontmatter, $body ] = $this->splitFrontmatter($text);

    $this->properties = $this->parseAttributes($frontmatter);
    $this->body = trim($body);
    $this->title = $this->extractTitle($this->body) ?: basename($this->filename);
    return $this;
  }

  private function splitFrontmatter(string $text) : array
  {
    return preg_match("#^---\s*$(?P<frontmatter>.*?)^---\s*$(?P<body>.*)#ms" , $text, $blocks)
      ? [ $blocks['frontmatter'], $blocks['body'] ]
      : [ '', $text ];
  }

  private function extractTitle(string $body) : string
  {
    if (preg_match("/^#\s*(?P<title>[^#].*?)$/ms", $body, $matches)) {
      return trim($matches['title']);
    }
    return '';
  }

  private function parseAttributes(string $frontmatter) : array
  {
    $block = preg_replace('#\n#', '\n', $frontmatter);
    $lines = explode('\n', $block);
    $lastKey = null;
    $multiline = false;
    $properties = [];
    foreach ($lines as $line) {
      if ($multiline && preg_match('/^\s+(?P<line>.*?)$/', $line, $groups)) {
        $line = $groups['line'];
        $properties[$lastKey] .= $line."\n";

        continue;
      }
      $multiline = false;
      if (preg_match('/^\s*(?P<key>[^\d\W]\w*)\s*:\s*(?P<value>.*?)\s*$/', $line, $groups)) {
        $key = $groups['key'];
        $value = $groups['value'];
        if (is_numeric($value)) {
          $value = strpos($value, '.') === false ? (int) $value : (float) $value;
        } elseif ($value === 'true') {
          $value = true;
        } elseif ($value === 'false') {
          $value = false;
        } elseif ($value === '|') {
          $value = '';
          $multiline = true;
        } elseif (strlen($value) > 1 && $value[0] == '"' && $value[strlen($value) - 1] == '"') {
          $value = substr($value, 1, strlen($value) - 2);
        } elseif (preg_match('#^\d\d\d\d-[012]\d-[0123]\d([T ][012]\d:[0-5]\d(:[0-5]\d)?)?$#', $value)) {
          $value = new DateTime($value);
        }
        $properties[$key] = $value;
        $lastKey = $key;
      } elseif (preg_match('/^\s+-\s*(?P<item>.*?)\s*$/', $line, $groups)) {
        if ($properties[$lastKey] == null) {
          $properties[$lastKey] = [ $groups['item'] ];
        } elseif (is_array($properties[$lastKey])) {
          $properties[$lastKey][] = $groups['item'];
        } else {
          throw new Exception("Cannot add item '{$groups['item']}' to non-list key '{$lastKey}'");
        }
      }
    }

    return $properties;
  }

  private function readOnlyProperties() : object
  {
    return new class($this->properties) {
      public function __construct(private array $data)
      {}

      public function __get(string $key) : mixed
      {
        return $this->data[$key] ?? null;
      }

      public function __set(string $key, mixed $value) : void
      {
        throw new Exception("Cannot modify properties");
      }
    };
  }
}
