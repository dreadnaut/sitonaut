<?php
require 'src/MarkdownDocument.php';

it('cannot be instantiated directly', function() {
  $md = new MarkdownDocument();
})->throws("Call to private");

describe("from", function() {
  it("creates an instance from the given string", function() {
    $md = MarkdownDocument::from(<<<MD
    # Title
    Body
    MD);
    expect($md)->toBeInstanceOf(MarkdownDocument::class);
    expect($md->title)->toBe('Title');
    expect($md->body)->toBe("# Title\nBody");
  });

  it("does not set a filename", function() {
    $md = MarkdownDocument::from(<<<MD
    # Title
    Body
    MD);
    expect($md->filename)->toBeEmpty();
  });
});

describe("load", function() {
  it("returns null if the file does not exist", function() {
    $md = MarkdownDocument::load('tests/fixtures/does-not-exists.md');
    expect($md)->toBeNull();
  });

  it("create an instance from the file contents", function() {
    $md = MarkdownDocument::load('tests/fixtures/full-document.md');
    expect($md)->toBeInstanceOf(MarkdownDocument::class);
    expect($md->title)->toBe('This is the title');
    expect($md->properties['tags'])->toBe([ 'web', 'things' ]);
  });

  it("keeps track of the filename", function() {
    $md = MarkdownDocument::load('tests/fixtures/no-title.md');
    expect($md->filename)->toBe('tests/fixtures/no-title.md');
  });
});

describe('title', function() {
  it('returns the first heading if present', function() {
    $md = MarkdownDocument::from(<<<MD
    # Title here

    Text here

    # Other heading
    MD);
    expect($md->title)->toBe('Title here');
  });

  it('finds the heading after empty lines', function() {
    $md = MarkdownDocument::from(<<<MD

    # Title here

    Text here
    MD);
    expect($md->title)->toBe('Title here');
  });

  it('finds the heading after the front matter', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    key: value
    ---
    # Title here

    Text here
    MD);
    expect($md->title)->toBe('Title here');
  });

  it('finds the heading after other text', function() {
    $md = MarkdownDocument::from(<<<MD
    Text before

    # Title here

    Text here
    MD);
    expect($md->title)->toBe('Title here');
  });

  it('only finds level one headings', function() {
    $md = MarkdownDocument::from(<<<MD
    ### Level 3

    Text here
    MD);
    expect($md->title)->toBe('');
  });

  it("falls back to the filename if missing", function() {
    $md = MarkdownDocument::load('tests/fixtures/no-title.md');
    expect($md->title)->toBe('no-title.md');
  });

  it('returns an empty string if missing and not from file', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    key: value
    ---

    Text here
    MD);
    expect($md->title)->toBeEmpty();
  });

  it("cannot be overwritten", function() {
    $md = MarkdownDocument::from(<<<MD
    # A title
    MD);
    $md->body = 'Another title';
  })->throws('Cannot modify');
});

describe('body', function() {
  it('collects all content lines', function() {
    $md = MarkdownDocument::from(<<<MD
    Line 1
    Line 2
    Line 3
    MD);
    expect($md->body)->toBe("Line 1\nLine 2\nLine 3");
  });

  it('finds them after the front matter', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    key: value
    ---

    Text here
    MD);
    expect($md->body)->toBe('Text here');
  });

  it('finds them after a heading', function() {
    $md = MarkdownDocument::from(<<<MD
    # Title here

    Line 1
    Line 2
    MD);
    expect($md->body)->toBe("# Title here\n\nLine 1\nLine 2");
  });

  it('skips empty lines before and after', function() {
    $md = MarkdownDocument::from(<<<MD

    Line 1
    Line 2

    MD);
    expect($md->body)->toBe("Line 1\nLine 2");
  });

  it("cannot be overwritten", function() {
    $md = MarkdownDocument::from(<<<MD
    Some text
    MD);
    $md->body = 'Other text';
  })->throws('Cannot modify');
});

describe('properties', function() {
  it('reads values from the front matter', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    one: value
    two: other
    three: yep
    ---

    And a body
    MD);
    expect($md->properties)->toEqual([
      'one' => 'value',
      'two' => 'other',
      'three' => 'yep',
    ]);
  });

  it('reads values even if there is no body', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    one: value
    two: other
    three: yep
    ---
    MD);
    expect($md->properties)->toEqual([
      'one' => 'value',
      'two' => 'other',
      'three' => 'yep',
    ]);
  });

  it('is not confused by horizontal dividers', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    key: value
    ---

    # Title

    other: value

    ---

    Second paragraph
    MD);
    expect($md->properties)->toEqual([
      'key' => 'value',
    ]);
    expect($md->title)->toBe('Title');
    expect($md->body)->toContain('other: value');
  });

  it('supports text values', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    key: value
    ---
    MD);
    expect($md->properties['key'])->toBe('value');
  });

  it('supports double quotes', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    key: "value"
    ---
    MD);
    expect($md->properties['key'])->toBe('value');
  });

  it('supports multi-line text', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    key: |
      first line
      second line
    ---
    MD);
    expect($md->properties['key'])->toBe("first line\nsecond line\n");
  });

  it('understands where multi-line text stops', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    key: |
      first line
      second: line
    other: value
    ---
    MD);
    expect($md->properties['key'])->toBe("first line\nsecond: line\n");
    expect($md->properties['other'])->toBe('value');
  });

  it('supports numeric values', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    first: 22
    second: 3.14
    ---
    MD);
    expect($md->properties['first'])->toBe(22);
    expect($md->properties['second'])->toBe(3.14);
  });

  it('supports boolean values', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    first: true
    second: false
    third: "true"
    fourth: "false"
    ---
    MD);
    expect($md->properties['first'])->toBeTrue();
    expect($md->properties['second'])->toBeFalse();
    expect($md->properties['third'])->toBe('true');
    expect($md->properties['fourth'])->toBe('false');
  });

  it('supports date values', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    key: 2024-04-01
    ---
    MD);
    expect($md->properties['key'])->toEqual(new DateTime('2024-04-01'));
  });

  it('supports datetime values', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    first: 2024-04-01 14:00:00
    second: 2024-04-01T18:00:00
    ---
    MD);
    expect($md->properties['first'])->toEqual(new DateTime('2024-04-01T14:00:00'));
    expect($md->properties['second'])->toEqual(new DateTime('2024-04-01T18:00:00'));
  });

  it('supports lists', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    key:
      - one
      - two
      - three
      - four
    another: here
    ---
    MD);
    expect($md->properties['key'])->toBe([ 'one', 'two', 'three', 'four' ]);
    expect($md->properties['another'])->toBe('here');
  });

  it('does not add list items to non-list keys', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    another: is a string
      - can't have items
    ---
    MD);
  })->throws("Cannot add item");

  it('cannot be modified', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    another: a string
    ---
    MD);
    $md->properties['another'] = 3;
  })->throws('Cannot indirectly modify');
});

describe("props", function() {
  it("provides object access to properties", function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    key:
      - one
      - two
      - three
    another: here
    ---
    MD);
    expect($md->props->key)->toBe([ 'one', 'two', 'three' ]);
    expect($md->props->another)->toBe('here');
    expect($md->props->not_there)->toBeNull();
  });

  it('cannot be modified', function() {
    $md = MarkdownDocument::from(<<<MD
    ---
    another: a string
    ---
    MD);
    $md->props->another = 3;
  })->throws('Cannot modify');
});
