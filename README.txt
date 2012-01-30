Install
---

You can install Twig in various ways: http://twig.sensiolabs.org/doc/intro.html

Download the tarball, extract it to sites/all/libraries/twig - the 
Twig/Autoloader.php can be found at sites/all/libraries/twig/lib/Twig/Autoloader.php

Available filters:
---

  - render: render structured array.
  - t: translate string.
  - url: generate the full URL.
  - debug: Debug a variable.
  - striptags: Strip tags from string.
  - upper: Title-cases.
  - title: Title-cases.
  - join(', '): join a list by commas.
  - @TODO: truncate_utf8
  - @TODO: drupal_convert_to_utf8
  - @TODO: decode_entities
  - @TODO: drupal_strlen
  - @TODO: drupal_ucfirst
  - @TODO: markdown https://github.com/geta6/Twig-Markdown/

Available variables:
---

  - _self: references the current template
  - context: references the current context
  - charset: references the current charset.

Syntax:
---

  - {{ }}
  - {% %}
  - {% start %} {% end %}
