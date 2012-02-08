<?php

/**
 * @file template.php
 */

/**
 * Implements hook_theme().
 */
function twig_theme($existing, $type, $theme, $path) {
  $templates = drupal_find_theme_templates($existing, '.twig', $path);
  foreach (array_keys($templates) as $i) {
    $templates[$i] = $existing[$i];
    unset($templates[$i]['file']);
    $templates[$i]['function'] = 'twig_theme_callback';
    if (is_string($existing[$i]['template'])) {
      $templates[$i]['preprocess functions'][] = "twig_preprocess_{$existing[$i]['template']}";
    }
  }
  return $templates;
}

/**
 * Load Twig.
 */
function twig_load() {
  static $loaded = FALSE;
  if (!$loaded) {
    // Register the Twig auto-loader.
    require_once (DRUPAL_ROOT . '/sites/all/libraries/twig/lib/Twig/Autoloader.php');
    Twig_Autoloader::register();
    $loaded = TRUE;
  }
  return $loaded;
}

function twig_instance($path = '') {
  static $twig = NULL;
  static $loader = NULL;
  if (!isset($twig)) {
    $loader = new Twig_Loader_Filesystem($path);

    $options['debug'] = variable_get('twig_debug', FALSE);
    $options['autoescape'] = variable_get('twig_autoescape', FALSE);
    if (variable_get('twig_cache', TRUE)) {
      $options['cache'] = file_directory_temp();
    }

    $twig = new Twig_Environment($loader, $options);

    $filters['render'] = 'render';
    $filters['t']     = 't';
    $filters['url']   = 'url';
    $filters['hide']  = 'hide';
    $filters['debug'] = function_exists('kpr') ? 'kpr' : 'vardump';

    foreach ($filters as $filter => $function) {
      $twig->addFilter($filter, new Twig_Filter_Function($function));
    }
  }
  elseif (isset($loader) && !empty($path)) {
    $loader->addPath($path);
  }
  return $twig;
}

/**
 * Clone of theme().
 *
 * We are sure this is template-theme.
 * Template file placed under this theme /templates/
 */
function twig_theme_twin($hook, $variables) {
  static $hooks = NULL;

  if (!isset($hooks)) {
    $hooks = theme_get_registry(FALSE);
  }

  // If an array of hook candidates were passed, use the first one that has an
  // implementation.
  if (is_array($hook)) {
    foreach ($hook as $candidate) {
      if (isset($hooks[$candidate])) {
        break;
      }
    }
    $hook = $candidate;
  }

  if (!isset($hooks[$hook])) {
    while ($pos = strrpos($hook, '__')) {
      $hook = substr($hook, 0, $pos);
      if (isset($hooks[$hook])) {
        break;
      }
    }

    if (!isset($hooks[$hook])) {
      return '<!-- Theme key "'. $hook .'" not found.  -->';
    }
  }

  $info = $hooks[$hook];
  global $theme_path;
  $temp = $theme_path;
  // point path_to_theme() to the currently used theme path:
  $theme_path = $info['theme path'];

  // If a renderable array is passed as $variables, then set $variables to
  // the arguments expected by the theme function.
  if (isset($variables['#theme']) || isset($variables['#theme_wrappers'])) {
    $element = $variables;
    $variables = array();
    if (isset($info['variables'])) {
      foreach (array_keys($info['variables']) as $name) {
        if (isset($element["#$name"])) {
          $variables[$name] = $element["#$name"];
        }
      }
    }
    else {
      $variables[$info['render element']] = $element;
    }
  }

  // Merge in argument defaults.
  if (!empty($info['variables'])) {
    $variables += $info['variables'];
  }
  elseif (!empty($info['render element'])) {
    $variables += array($info['render element'] => array());
  }

  // Invoke the variable processors, if any. The processors may specify
  // alternate suggestions for which hook's template/function to use. If the
  // hook is a suggestion of a base hook, invoke the variable processors of
  // the base hook, but retain the suggestion as a high priority suggestion to
  // be used unless overridden by a variable processor function.
  if (isset($info['base hook'])) {
    $base_hook = $info['base hook'];
    $base_hook_info = $hooks[$base_hook];
    if (isset($base_hook_info['preprocess functions']) || isset($base_hook_info['process functions'])) {
      $variables['theme_hook_suggestion'] = $hook;
      $hook = $base_hook;
      $info = $base_hook_info;
    }
  }
  if (isset($info['preprocess functions']) || isset($info['process functions'])) {
    $variables['theme_hook_suggestions'] = array();
    foreach (array('preprocess functions', 'process functions') as $phase) {
      if (!empty($info[$phase])) {
        #dsm($phase);
        foreach ($info[$phase] as $processor_function) {
          if (function_exists($processor_function)) {
            #dsm($processor_function);
            // We don't want a poorly behaved process function changing $hook.
            $hook_clone = $hook;
            $processor_function($variables, $hook_clone);
          }
        }
      }
    }
    // If the preprocess/process functions specified hook suggestions, and the
    // suggestion exists in the theme registry, use it instead of the hook that
    // theme() was called with. This allows the preprocess/process step to
    // route to a more specific theme hook. For example, a function may call
    // theme('node', ...), but a preprocess function can add 'node__article' as
    // a suggestion, enabling a theme to have an alternate template file for
    // article nodes. Suggestions are checked in the following order:
    // - The 'theme_hook_suggestion' variable is checked first. It overrides
    //   all others.
    // - The 'theme_hook_suggestions' variable is checked in FILO order, so the
    //   last suggestion added to the array takes precedence over suggestions
    //   added earlier.
    $suggestions = array();
    if (!empty($variables['theme_hook_suggestions'])) {
      $suggestions = $variables['theme_hook_suggestions'];
    }
    if (!empty($variables['theme_hook_suggestion'])) {
      $suggestions[] = $variables['theme_hook_suggestion'];
    }
    foreach (array_reverse($suggestions) as $suggestion) {
      if (isset($hooks[$suggestion])) {
        $info = $hooks[$suggestion];
        break;
      }
    }
  }

  if (!isset($variables['directory'])) {
    $default_template_variables = array();
    template_preprocess($default_template_variables, $hook);
    $variables += $default_template_variables;
  }

  // Render the output using the template file.
  $template_file = $info['template'] . '.twig';
  $template_file = basename($template_file);
  $template_file = $info['theme path'] . '/templates/' . $template_file;
  $output = twig_render_template($template_file, $variables);

  // restore path_to_theme()
  $theme_path = $temp;
  return $output;
}

function twig_theme_callback(&$variables) {
  $trace = debug_backtrace(FALSE);
  $hook = $trace[1]['args'][0];
  return twig_theme_twin($hook, $variables);
}

/**
 * Override or insert variables into the html templates.
 * Replace 'twig' with your themes name, i.e. mytheme_preprocess_html()
 */
function twig_preprocess_html(&$vars) {
  $media_queries_css = array('twig.responsive.style.css', 'twig.responsive.gpanels.css');
  load_subtheme_media_queries($media_queries_css, 'twig');
}

/**
 *
 * @param type $vars
 */
function twig_preprocess_node(&$vars) {
  foreach (array('comments', 'links') as $i) {
    if (isset($vars['content'][$i])) {
      $vars[$i] = $vars['content'][$i];
      unset($vars['content'][$i]);
    }
  }
}

/**
 * Render a Twig template.
 */
function twig_render_template($template_file, $variables) {
  if (twig_load()) {
    $path = DRUPAL_ROOT . '/' . dirname($template_file);
    $template = basename($template_file);
    $twig = twig_instance($path);
    ob_start();
    echo $twig->render($template, $variables);
    return ob_get_clean();
  }
}

