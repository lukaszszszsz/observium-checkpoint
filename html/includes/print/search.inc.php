<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage web
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2017 Observium Limited
 *
 */

/**
 * Generate search form
 *
 * generates a search form.
 * types allowed: select, multiselect, text (or input), datetime, newline
 *
 * Example of use:
 *  - array for 'select' item type
 *  $search[] = array('type'    => 'select',          // Type
 *                    'name'    => 'Search By',       // Displayed title for item
 *                    'id'      => 'searchby',        // Item id and name
 *                    'width'   => '120px',           // (Optional) Item width
 *                    'size'    => '15',              // (Optional) Maximum number of items to show in the menu (default 15)
 *                    'value'   => $vars['searchby'], // (Optional) Current value(-s) for item
 *                    'values'  => array('mac' => 'MAC Address',
 *                                       'ip'  => 'IP Address'));  // Array with option items
 *  - array for 'multiselect' item type (array keys same as above)
 *  $search[] = array('type'    => 'multiselect',
 *                    'name'    => 'Priorities',
 *                    'id'      => 'priority',
 *                    'width'   => '150px',
 *                    'subtext' => TRUE,              // (Optional) Display items value right of the item name
 *                    'encode'  => FALSE,             // (Optional) Use var_encode for values, use when values contains commas or empty string
 *                    'value'   => $vars['priority'],
 *                    'values'  => $priorities);
 *  - array for 'text' or 'input' item type
 *  $search[] = array('type'  => 'text',
 *                    'name'  => 'Address',
 *                    'id'    => 'address',
 *                    'width' => '120px',
 *                    'placeholder' => FALSE,         // (Optional) Display item name as pleseholder or left relatively input
 *                    'value' => $vars['address']);
 *  - array for 'datetime' item type
 *  $search[] = array('type'  => 'datetime',
 *                    'id'    => 'timestamp',
 *                    'presets' => TRUE,                  // (optional) Show select field with timerange presets
 *                    'min'   => dbFetchCell('SELECT MIN(`timestamp`) FROM `syslog`'), // (optional) Minimum allowed date/time
 *                    'max'   => dbFetchCell('SELECT MAX(`timestamp`) FROM `syslog`'), // (optional) Maximum allowed date/time
 *                    'from'  => $vars['timestamp_from'], // (optional) Current 'from' value
 *                    'to'    => $vars['timestamp_to']);  // (optional) Current 'to' value
 *  - array for 'sort' item pseudo type
 *  $search[] = array('type'   => 'sort',
 *                    'value'  => $vars['sort'],
 *                    'values' => $sorts);
 *  - array for 'newline' item pseudo type
 *  $search[] = array('type' => 'newline',
 *                    'hr'   => FALSE);                   // (optional) show or not horizontal line
 *  print_search($search, 'Title here', 'search', url);
 *
 * @param array $data, string $title
 * @return none
 *
 */
function print_search($data, $title = NULL, $button = 'search', $url = NULL)
{
  // Cache permissions to session var
  permissions_cache_session();
  //r($_SESSION['cache']);

  $submit_by_key = FALSE;
  $string_items = '';
  foreach ($data as $item)
  {
    if ($url && isset($item['id']))
    {
      // Remove old vars from url
      $url = preg_replace('/'.$item['id'].'=[^\/]+\/?/', '', $url);
    }
    if ($item['type'] == 'sort')
    {
      $sort = $item;
      continue;
    }
    else if (isset($item['submit_by_key']) && $item['submit_by_key'])
    {
      $submit_by_key = TRUE;
    }
    $string_items .= generate_form_element($item);
  }

  $form_id = 'search-'.strgen('4');

  if ($submit_by_key)
  {
    $action = '';
    if ($url)
    {
      $action .= 'this.form.prop(\'action\', form_to_path(\'' . $form_id . '\'));';
    }
    register_html_resource('script', '$(function(){$(\'form#' . $form_id . '\').each(function(){$(this).find(\'input\').keypress(function(e){if(e.which==10||e.which==13){'.$action.'this.form.submit();}});});});');
  }

  // Form header
  $string = PHP_EOL . '<!-- START search form -->' . PHP_EOL;
  $string .= '<form method="POST" action="'.$url.'" class="form-inline" id="'.$form_id.'">' . PHP_EOL;
  $string .= '<div class="navbar">' . PHP_EOL;
  $string .= '<div class="navbar-inner">';
  $string .= '<div class="container">';
  if (isset($title)) { $string .= '  <a class="brand">' . $title . '</a>' . PHP_EOL; }

  $string .= '<div class="nav" style="margin: 5px 0 5px 0;">';

  // Main
  $string .= $string_items;

  $string .= '</div>';

  // Form footer
  /// FIXME. I don't know how to put this buttons to middle or bottom..
  $string .= '    <div class="nav pull-right"';

  //$button_style = 'line-height: 20px;';
  $button_style = '';
  // Add sort switcher if present
  if (isset($sort))
  {
    $string .= ' style="margin: 5px 0 5px 0;">' . PHP_EOL;
    $string .= '      <select name="sort" id="sort" class="selectpicker pull-right" title="Sort Order" style="width: 150px;" data-width="150px">' . PHP_EOL;
    foreach ($sort['values'] as $item => $name)
    {
      if (!$sort['value']) { $sort['value'] = $item; }
      $string .= '        <option value="'.$item.'"';
      if ($sort['value'] == $item)
      {
        $string .= ' data-icon="'.$config['icon']['sort'].'" selected';
      }
      $string .= '>'.$name.'</option>';
    }
    $string .= '      </select><br />' . PHP_EOL;
    $button_style .= ' margin-top: 7px;';
  } else {
    $string .= '>' . PHP_EOL;
  }

  // Note, script submitURL() stored in js/observium.js
  $button_type    = 'submit';
  $button_onclick = '';
  if ($url)
  {
    $button_type    = 'button';
    $button_onclick = " onclick=\"form_to_path('".$form_id."');\"";
  }

  $string .= '      <button type="'.$button_type.'" class="btn btn-default pull-right" style="'.$button_style.'"'.$button_onclick.'>';
  switch($button)
  {
    // Note. 'update' - use POST request, all other - use GET with generate url from js.
    case 'update':
      $string .= '<i class="icon-refresh"></i> Update</button>' . PHP_EOL;
      break;
    default:
      $string .= '<i class="icon-search"></i> Search</button>' . PHP_EOL;
  }
  $string .= '    </div>' . PHP_EOL;
  $string .= '</div></div></div></form>' . PHP_EOL;
  $string .= '<!-- END search form -->' . PHP_EOL . PHP_EOL;

  // Print search form
  echo($string);
}

// Just callback functions to print_form with $return flag
function generate_form($data)
{
  return print_form($data, TRUE);
}

function generate_form_box($data)
{
  return print_form_box($data, TRUE);
}


/**
 * Pretty form generator
 *
 * Form options:
 *   id     - form id, default is auto generated
 *   type   - rows (multiple elements with small amount of rows), horizontal (mostly single element per row), simple (raw form without any grid/divs)
 *   brand  - only for rows, adds "other" form title (I think not work and obsolete)
 *   title  - displayed form title (only for rows and horizontal)
 *   icon   - adds icon to title
 *   class  - adds div with class (default box box-solid) in horizontal
 *   space  - adds style for base div in rows type and horizontal with box box-solid class (padding: xx) and horizontal type with box class (padding-top: xx)
 *   style  - adds style for base form element, default (margin-bottom:0;)
 *   url    - form action url, if url set and submit element with id "search" used (or submit_by_key), than form send params as GET query
 *   submit_by_key - send form query by press enter key in text/input forms
 *   fieldset - horizontal specific, array with fieldset names and descriptions, in form element should be add fieldset option with same key name
 *
 * Elements options see in generate_form_element() description
 *
 * @param array $data   Form options and form elements
 * @param bool  $return If used and set to TRUE, print_form() will return form html instead of outputting it.
 *
 * @return NULL
 */
function print_form($data, $return = FALSE)
{
  // Just return if safety requirements are not fulfilled
  if (isset($data['userlevel']) && $data['userlevel'] > $_SESSION['userlevel']) { return; }

  /*
  // Use modal with form
  if (isset($data['modal_args']) && !empty($data['modal_args']))
  {
    // Print modal form
    echo(generate_form_modal($data));
  }
  */

  // Time our form filling.
  $form_start = microtime(TRUE);

  $form_id    = (isset($data['id']) ? $data['id'] : 'form-'.strgen());
  $form_class = 'form form-inline'; // default for rows and simple
  if (isset($data['style']))
  {
    $form_style = ' style="'.$data['style'].'"';
  } else {
    $form_style = ' style="margin-bottom: 0px;"';
  }
  $base_class = (array_key_exists('class', $data) ? $data['class'] : OBS_CLASS_BOX);
  $base_space = ($data['space'] ? $data['space'] : '5px');
  $used_vars  = array();

  // Cache permissions to session var
  permissions_cache_session();
  //r($_SESSION['cache']);

  if ($data['submit_by_key'])
  {
    $action = '';
    if ($data['url'])
    {
      $action .= 'this.form.prop(\'action\', form_to_path(\'' . $form_id . '\'));';
    }
    register_html_resource('script', '$(function(){$(\'form#' . $form_id . '\').each(function(){$(this).find(\'input\').keypress(function(e){if(e.which==10||e.which==13){'.$action.'this.form.submit();}});});});');
  }

  // Form elements
  if ($data['type'] == 'rows')
  {
    // Rows form, see example in html/pages/devices.inc.php
    //$div_padding = 'padding: 0px '.$base_space.' '.$base_space.' '.$base_space.' !important;'; // Top padding set as 0px, all other to base
    $div_padding = 'padding: '.$base_space.' !important;';
    if (strpos($base_class, 'box') !== FALSE)
    {
      $base_space = ($data['space'] ? $data['space'] : '10px');

      // Box horizontal style
      $box_args  = array('header-border' => TRUE,
                         'body-style' => $div_padding); // Override top padding
      if (isset($data['title'])) { $box_args['title'] = $data['title']; }
      $div_begin = generate_box_open($box_args);
      $div_end   = generate_box_close();
      unset($box_args);
    } else {
      $div_begin = '<div class="'.$base_class.'" style="'.$div_padding.'">' . PHP_EOL;
      $div_end   = '</div>' . PHP_EOL;
    }
    $row_style = '';
    $string_elements = '';

    // Calculate grid sizes for rows
    foreach ($data['row'] as $k => $row)
    {
      $row_count = count($row);
      // Default (auto) grid size for elements
      $row_grid = intval(12 / $row_count);
      $grid_count = 0; // Count for custom passed grid sizes
      foreach ($row as $id => $element)
      {
        if (isset($element['div_class']) && preg_match('/col-(?:lg|md|sm)-(\d+)/', $element['div_class'], $matches))
        {
          // Class with col size passed
          $grid_count += intval($matches[1]);
        }
        else if (isset($element['grid']))
        {
          // Grid size passed
          if ($element['grid'] > 0 && $element['grid'] <= 12)
          {
            $grid_count += intval($element['grid']);
          } else {
            // Incorrect size
            unset($row[$k]['grid']);
          }
        }
      }
      $row_grid = 12 - $grid_count;                            // Free grid size after custom grind
      $row_grid = intval($row_grid / $row_count);              // Default (auto) grid size for elements
      if ($grid_count > 2 && $row_grid < 1) { $row_grid = 1; } // minimum 1 for auto if custom grid passed
      else if ($row_grid < 2)               { $row_grid = 2; } // minimum 2 for auto

      $data['grid'][$k] = $row_grid;                           // Store default grid size for row
    }
    //r($data);

    foreach ($data['row'] as $k => $row)
    {
      $row_class = 'row';
      if (isset($data['row_options'][$k])) // If row options exist for current row
      {
        if ($data['row_options'][$k]['class'])
        {
          $row_class .= ' ' . $data['row_options'][$k]['class'];
        }
      }
      $string_elements .= '  <div class="'.$row_class.'" '.$row_style.'> <!-- START row-'.$k.' -->' . PHP_EOL;
      foreach ($row as $id => $element)
      {
        $used_vars[]      = $id;
        $element['id']    = $id;

        // Default class with default row grid size or passed from options
        $grid      = (isset($element['grid']) ? $element['grid'] : $data['grid'][$k]);
        $div_class = 'col-lg-' . $grid . ' col-md-' . $grid . ' col-sm-' . $grid;
        // By default xs grid always 12
        if (isset($element['grid_xs']) && $element['grid_xs'] > 0 && $element['grid_xs'] <= 12)
        {
          $div_class .= ' col-xs-' . $element['grid_xs'];
        }

        if (empty($element['div_class']))
        {
          $element['div_class'] = $div_class;
        }
        else if (isset($element['grid']) && !preg_match('/col-(?:lg|md|sm|xs)-(\d+)/', $element['div_class']))
        {
          // Combine if passed both: grid size and div_class (and class not has col-* grid elements)
          $element['div_class'] = $div_class . ' ' . $element['div_class'];
        }
        if ($element['right'])
        {
          $element['div_class'] .= ' col-lg-push-0 col-md-push-0 col-sm-push-0';
        }
        if ($id == 'search' && $data['url'])
        {
          // Add form_id here, for generate onclick action in submit button
          $element['form_id'] = $form_id;
        }
        // Here added padding-block-start for space between rows (also if row elements moved to newline)
        //$string_elements .= '    <div class="'.$element['div_class'].'" style="padding-block-start: '.$base_space.';">' . PHP_EOL;
        $string_elements .= '    <div id="' .$element['id'] . '_div" class="'.$element['div_class'].'"';
        if (!empty($element['div_style']))
        {
          $string_elements .= ' style="'.$element['div_style'].'"';
        }
        $string_elements .= '>' . PHP_EOL;
        $string_elements .= generate_form_element($element);
        $string_elements .= '    </div>' . PHP_EOL;
      }
      $string_elements .= '  </div> <!-- END row-'.$k.' -->' . PHP_EOL;
      // Add space between rows
      $row_style = 'style="margin-top: '.$base_space.';"';
    }
  } // end rows type
  else if ($data['type'] == 'horizontal')
  {
    // Horizontal form, see example in html/pages/edituser.inc.php
    if (strpos($base_class, 'widget') !== FALSE || strpos($base_class, 'box') !== FALSE)
    {
      $base_space = ($data['space'] ? $data['space'] : '10px');

      // Box horizontal style
      $box_args  = array('header-border' => TRUE,
                         'body-style' => 'padding-top: '.$base_space.' !important;'); // Override top padding
      if (isset($data['title'])) { $box_args['title'] = $data['title']; }
      $div_begin = generate_box_open($box_args);
      $div_end   = generate_box_close();
      unset($box_args);
    }
    else if (empty($base_class))
    {
      // Clean class
      // Example in html/pages.logon.inc.php
      $div_begin = PHP_EOL;
      $div_end   = PHP_EOL;
    } else {
      // Old box box-solid style (or any custom style)
      $div_begin = '<div class="'.$base_class.'" style="padding: '.$base_space.';">' . PHP_EOL;
      if (isset($data['title']))
      {
        $div_begin .= '  <div class="title">';
        if ($data['icon'])
        {
           $div_begin .= '<i class="'.$data['icon'].'"></i>';
        }
        $div_begin .= '&nbsp;'.$data['title'].'</div>' . PHP_EOL;
      }
      $div_end   = '</div>' . PHP_EOL;
    }
    $form_class = 'form form-horizontal';
    $row_style = '';
    $fieldset  = array();

    foreach ($data['row'] as $k => $row)
    {
      $first_key    = key($row);
      $row_group    = $k;
      $row_elements = '';
      $row_label    = '';
      $row_control_group = FALSE;
      $i = 0;
      foreach ($row as $id => $element)
      {
        $used_vars[]      = $id;
        $element['id']    = $id;
        if ($element['fieldset'])
        {
          $row_group = $element['fieldset']; // Add this element to group
        }

        // Additional element options for horizontal specific form
        $div_style = '';
        switch ($element['type'])
        {
          case 'hidden':
            break;
          case 'submit':
            $div_class = 'form-actions';
            break;
          case 'text':
          case 'input':
          case 'password':
          case 'textarea':
          default:
            $row_control_group = TRUE;
            // In horizontal, first element name always placed at left
            if (!isset($element['placeholder'])) { $element['placeholder'] = TRUE; }
            // offset == FALSE disable label width and align class control-label
            if (!isset($element['offset']))
            {
              if (isset($data['fieldset'][$element['fieldset']]['offset']))
              {
                // Copy from fieldset
                $element['offset'] = $data['fieldset'][$element['fieldset']]['offset'];
              }
              else if (($element['type'] == 'raw' || $element['type'] == 'html') &&
                       !isset($element['name']) && $first_key === $id)
              {
                // When raw/html element first, disable offset
                $element['offset'] = FALSE;
              } else {
                // Default
                $element['offset'] = TRUE;
              }
            }
            if ($i < 1)
            {
              // Add laber for first element in row
              if ($element['name'])
              {
                $row_label = '    <label';
                if ($element['offset'])
                {
                  $row_label .= ' class="control-label"';
                }
                $row_label .= ' for="'.$element['id'].'">'.$element['name'].'</label>' . PHP_EOL;
              }
              $row_control_id = $element['id'] . '_div';
              if ($element['type'] == 'datetime')
              {
                $element['name'] = '';
              }
            }
            // nextrow class element to new line (after label)
            $div_class = ($element['offset']) ? 'controls' : 'nextrow';
            break;
        }

        if (!isset($element['div_class']))
        {
          $element['div_class'] = $div_class;
        }
        if ($element['div_class'] == 'form-actions')
        {
          // Remove margins only for form-actions elements
          $div_style = 'margin: 0px;';
        }
        //if ($element['right'])
        //{
        //  $element['div_class'] .= ' pull-right';
        //}
        if (isset($element['div_style']))
        {
          $div_style .= ' ' . $element['div_style'];
        }
        if ($id == 'search' && $data['url'])
        {
          // Add form_id here, for generate onclick action in submit button
          $element['form_id'] = $form_id;
        }

        $row_elements .= generate_form_element($element);
        $i++;
      }
      if ($element['div_class'])
      {
        // no additional divs if empty div class (hidden element for example)
        $row_begin = $row_label . PHP_EOL . '    <div id="' .$element['id'] . '_div" class="'.$element['div_class'].'"';
        if (strlen($div_style))
        {
          $row_begin .= ' style="' . $div_style . '"';
        }
        $row_elements = $row_begin . '>' . PHP_EOL . $row_elements . '    </div>' . PHP_EOL;
      } else {
        $row_elements = $row_label . PHP_EOL . $row_elements;
      }

      if ($row_control_group)
      {
        $fieldset[$row_group] .= '  <div id="'.$row_control_id.'" class="control-group" style="margin-bottom: '.$base_space.';"> <!-- START row-'.$k.' -->' . PHP_EOL;
        $fieldset[$row_group] .= $row_elements;
        $fieldset[$row_group] .= '  </div> <!-- END row-'.$k.' -->' . PHP_EOL;
      } else {
        // Do not add control group for submit/hidden
        $fieldset[$row_group] .= $row_elements;
      }
      //$row_style = 'style="margin-top: '.$base_space.';"'; // Add space between rows
    }

    foreach ($data['fieldset'] as $row_group => $entry)
    {
      if (isset($fieldset[$row_group]))
      {
        if (!is_array($entry))
        {
          $entry = array('title' => $entry);
        }

        $fieldset_begin = '';
        $fieldset_end   = '';
        // Additional div class if set
        if (isset($entry['class']))
        {
          $fieldset_begin = '<div class="'.$entry['class'].'">' . PHP_EOL . $fieldset_begin;
          $fieldset_end  .= '</div>' . PHP_EOL;
        }

        $row_elements = $fieldset_begin . '
          <fieldset> <!-- START fieldset-'.$row_group.' -->';
        if (!empty($entry['title']))
        {
          // fieldset title
          $row_elements .= '
          <div class="control-group">
              <div class="controls">
                  <h3>'.$entry['title'].'</h3>
              </div>
          </div>';
        }
        $row_elements .= PHP_EOL . $fieldset[$row_group] . '
          </fieldset>  <!-- END fieldset-'.$row_group.' -->
        ' . PHP_EOL;
        $fieldset[$row_group] = $row_elements . $fieldset_end;
      }
    }
    // Final combining elements
    $string_elements = implode('', $fieldset);
  } else {
    // Simple form, without any divs, see example in html/pages/edituser.inc.php
    $div_begin  = '';
    $div_end    = '';
    $string_elements = '';
    foreach ($data['row'] as $k => $row)
    {
      foreach ($row as $id => $element)
      {
        $used_vars[]      = $id;
        $element['id']    = $id;

        if ($id == 'search' && $data['url'])
        {
          // Add form_id here, for generate onclick action in submit button
          $element['form_id'] = $form_id;
        }
        $string_elements .= generate_form_element($element);
      }
      $string_elements .= PHP_EOL;
    }
  }

  // Add CSRF Token
  if (!in_array('requesttoken', $used_vars) && isset($_SESSION['requesttoken']))
  {
    $string_elements .= generate_form_element(array('type'  => 'hidden',
                                                    'id'    => 'requesttoken',
                                                    'value' => $_SESSION['requesttoken'])) . PHP_EOL;
    $used_vars[] = 'requesttoken';
  }

  // Remove old vars from url
  if ($data['url'])
  {
    foreach ($used_vars as $var)
    {
      $data['url'] = preg_replace('/'.$var.'=[^\/]+\/?/', '', $data['url']);
    }
  }

  // Form header
  if (isset($data['right']) && $data['right'])
  {
    $form_class .= ' pull-right';
  }
  $string = PHP_EOL . "<!-- START $form_id -->" . PHP_EOL;
  $string .= $div_begin;
  $string .= '<form method="POST" id="'.$form_id.'" name="'.$form_id.'" action="'.$data['url'].'" class="'.$form_class.'"'.$form_style.'>' . PHP_EOL;
  if ($data['brand']) { $string .= '  <a class="brand">' . $data['brand'] . '</a>' . PHP_EOL; }
  if ($data['help'])  { $string .= '  <span class="help-block">' . $data['help'] . '</span>' . PHP_EOL; }

  // Form elements
  $string .= $string_elements;

  // Form footer
  $string .= '</form>' . PHP_EOL;
  $string .= $div_end;
  $string .= "<!-- END $form_id -->" . PHP_EOL;

  if ($return)
  {
    // Save generation time for profiling
    $GLOBALS['form_time'] += utime() - $form_start;

    // Return form as string
    return $string;
  } else {
    // Print form
    echo($string);

    // Save generation time for profiling (after echo)
    $GLOBALS['form_time'] += utime() - $form_start;
  }
}

// Box specific form (mostly same as in print_form, but support only box style and fieldset options)
// FIXME should likely not be in this file? As it's used throughout the software now...
function print_form_box($data, $return = FALSE)
{
  // Just return if safety requirements are not fulfilled
  if (isset($data['userlevel']) && $data['userlevel'] > $_SESSION['userlevel']) { return; }

  $form_id    = (isset($data['id']) ? $data['id'] : 'form-'.strgen());
  $form_class = 'form form-horizontal';
  if (isset($data['style']))
  {
    $form_style = ' style="'.$data['style'].'"';
  } else {
    $form_style = ' style="margin-bottom:0px;"';
  }
  $base_class = (array_key_exists('class', $data) ? $data['class'] : OBS_CLASS_BOX);
  $base_space = ($data['space'] ? $data['space'] : '15px');
  $used_vars  = array();

  // Cache permissions to session var
  permissions_cache_session();
  //r($_SESSION['cache']);

  if ($data['submit_by_key'])
  {
    $action = '';
    if ($data['url'])
    {
      $action .= 'this.form.prop(\'action\', form_to_path(\'' . $form_id . '\'));';
    }
    register_html_resource('script', '$(function(){$(\'form#' . $form_id . '\').each(function(){$(this).find(\'input\').keypress(function(e){if(e.which==10||e.which==13){'.$action.'this.form.submit();}});});});');
  }

  $header = '';
  if (isset($data['title']))
  {
    $header .= '  <h2>' . $data['title'] . '</h2>' . PHP_EOL;
  }

  // Form elements
  $div_begin = '<div class="row">' . PHP_EOL;
  $div_end   = '</div>' . PHP_EOL;
  if ($data['type'] == 'horizontal')
  {
    $row_style = '';
    $fieldset  = array();

    foreach ($data['row'] as $k => $row)
    {
      $first_key    = key($row);
      $row_group    = $k;
      $row_elements = '';
      $row_label    = '';
      $row_control_group = FALSE;
      $i = 0;
      foreach ($row as $id => $element)
      {
        $used_vars[]      = $id;
        $element['id']    = $id;
        if ($element['fieldset'])
        {
          $row_group = $element['fieldset']; // Add this element to group
        }

        // Additional element options for horizontal specific form
        $div_style = '';
        switch ($element['type'])
        {
          case 'hidden':
            break;
          case 'submit':
            $div_class = 'form-actions';
            break;
          case 'text':
          case 'input':
          case 'password':
          case 'textarea':
          default:
            $row_control_group = TRUE;
            // In horizontal, first element name always placed at left
            if (!isset($element['placeholder'])) { $element['placeholder'] = TRUE; }
            // offset == FALSE disable label width and align class control-label
            if (!isset($element['offset']))
            {
              if (isset($data['fieldset'][$element['fieldset']]['offset']))
              {
                // Copy from fieldset
                $element['offset'] = $data['fieldset'][$element['fieldset']]['offset'];
              }
              else if (($element['type'] == 'raw' || $element['type'] == 'html') && $first_key === $id)
              {
                // When raw/html element first, disable offset
                $element['offset'] = FALSE;
              } else {
                // Default
                $element['offset'] = TRUE;
              }
            }
            if ($i < 1)
            {
              // Add laber for first element in row
              if ($element['name'])
              {
                $row_label = '    <label';
                if ($element['offset'])
                {
                  $row_label .= ' class="control-label"';
                }
                $row_label .= ' for="'.$element['id'].'">'.$element['name'].'</label>' . PHP_EOL;
              }
              $row_control_id = $element['id'] . '_div';
              if ($element['type'] == 'datetime')
              {
                $element['name'] = '';
              }
            }
            // nextrow class element to new line (after label)
            $div_class = ($element['offset']) ? 'controls' : 'nextrow';
            break;
        }

        if (!isset($element['div_class']))
        {
          $element['div_class'] = $div_class;
        }
        if ($element['div_class'] == 'form-actions')
        {
          // Remove margins only for form-actions elements
          $div_style = 'margin: 0px;';
        }
        //if ($element['right'])
        //{
        //  $element['div_class'] .= ' pull-right';
        //}
        if (isset($element['div_style']))
        {
          $div_style .= ' ' . $element['div_style'];
        }
        if ($id == 'search' && $data['url'])
        {
          // Add form_id here, for generate onclick action in submit button
          $element['form_id'] = $form_id;
        }

        $row_elements .= generate_form_element($element);
        $i++;
      }

      if ($element['div_class'])
      {
        // no additional divs if empty div class (hidden element for example)
        $row_begin = $row_label . PHP_EOL . '    <div class="'.$element['div_class'].'"';
        if (strlen($div_style))
        {
          $row_begin .= ' style="' . $div_style . '"';
        }
        $row_elements = $row_begin . '>' . PHP_EOL . $row_elements . '    </div>' . PHP_EOL;
      } else {
        $row_label = str_replace(' class="control-label"', '', $row_label);
        $row_elements = $row_label . PHP_EOL . $row_elements;
      }

      if ($row_control_group)
      {
        $fieldset[$row_group] .= '  <div id="'.$row_control_id.'" class="control-group"> <!-- START row-'.$k.' -->' . PHP_EOL;
        $fieldset[$row_group] .= $row_elements;
        $fieldset[$row_group] .= '  </div> <!-- END row-'.$k.' -->' . PHP_EOL;
      } else {
        // Do not add control group for submit/hidden
        $fieldset[$row_group] .= $row_elements;
      }
      //$row_style = 'style="margin-top: '.$base_space.';"'; // Add space between rows
    }

    $divs = array();
    $fieldset_tooltip = '';
    foreach ($data['fieldset'] as $group => $entry)
    {
      if (isset($fieldset[$group]))
      {
        if (!is_array($entry))
        {
          $entry = array('title' => $entry);
        }
        // Custom style
        if (!isset($entry['style']))
        {
          $entry['style'] = 'padding-bottom: 0px !important;'; // Remove last additional padding space
        }
        // Combinate fieldsets into common rows
        if ($entry['div'])
        {
          $divs[$entry['div']][] = $group;
        } else {
          $divs['row'][] = $group;
        }

        $box_args = array('header-border' => TRUE,
                          'padding' => TRUE,
                          'id' => $group,
                         );
        if (isset($entry['style']))
        {
          $box_args['body-style'] = $entry['style'];
        }
        if (isset($entry['title']))
        {
          $box_args['title'] = $entry['title'];
          if ($entry['icon'])
          {
            // $box_args['icon'] => $entry['icon'];
          }
        }

        if (isset($entry['tooltip']))
        {
          $box_args['header-controls'] = array('controls' => array('tooltip'   => array('icon'   => 'icon-info text-primary',
                                                                                        'anchor' => TRUE,
                                                                                        //'url'    => '#',
                                                                                        'class'  => 'tooltip-from-element',
                                                                                        'data'   => 'data-tooltip-id="tooltip-'.$group.'"')));

          $fieldset_tooltip .= '<div id="tooltip-'.$group.'" style="display: none;">' . PHP_EOL;
          $fieldset_tooltip .= $entry['tooltip'] . '</div>' . PHP_EOL;
        }

        if (isset($entry['tooltip'])) { $box_args['style'] = $entry['style']; }

        $fieldset_begin = generate_box_open($box_args);

        $fieldset_end   = generate_box_close();

        // Additional div class if set
        if (isset($entry['class']))
        {
          $fieldset_begin = '<div class="'.$entry['class'].'">' . PHP_EOL . $fieldset_begin;
          $fieldset_end  .= '</div>' . PHP_EOL;
        }

        $row_elements = $fieldset_begin . '
          <fieldset> <!-- START fieldset-'.$group.' -->';
        $row_elements .= PHP_EOL . $fieldset[$group] . '
          </fieldset> <!-- END fieldset-'.$group.' -->' . PHP_EOL;
        $fieldset[$group] = $row_elements . $fieldset_end;
      }
    }
    // Combinate fieldsets into common rows
    foreach ($divs as $entry)
    {
      $row_elements = $div_begin;
      foreach ($entry as $i => $group)
      {
        $row_elements .= $fieldset[$group];
        if ($i > 0)
        {
          // unset all fieldsets except first one for replace later
          unset($fieldset[$group]);
        }
      }
      $row_elements .= $div_end;
      // now replace first fieldset in group
      $fieldset[array_shift($entry)] = $row_elements;
    }
    // Final combining elements
    $string_elements = implode('', $fieldset);
  }

  // Add CSRF Token
  if (!in_array('requesttoken', $used_vars) && isset($_SESSION['requesttoken']))
  {
    $string_elements .= generate_form_element(array('type'  => 'hidden',
                                                    'id'    => 'requesttoken',
                                                    'value' => $_SESSION['requesttoken'])) . PHP_EOL;
    $used_vars[] = 'requesttoken';
  }

  // Remove old vars from url
  if ($data['url'])
  {
    foreach ($used_vars as $var)
    {
      $data['url'] = preg_replace('/'.$var.'=[^\/]+\/?/', '', $data['url']);
    }
  }

  // Form header
  $string = PHP_EOL . "<!-- START $form_id -->" . PHP_EOL;
  $string .= $header;
  $string .= '<form method="POST" id="'.$form_id.'" name="'.$form_id.'" action="'.$data['url'].'" class="'.$form_class.'"'.$form_style.'>' . PHP_EOL;

  // Form elements
  $string .= $string_elements;

  // Form footer
  $string .= '</form>' . PHP_EOL;
  $string .= $fieldset_tooltip;
  $string .= "<!-- END $form_id -->" . PHP_EOL;

  if ($return)
  {
    // Save generation time for profiling
    $GLOBALS['form_time'] += utime() - $form_start;

    // Return form as string
    return $string;
  } else {
    // Print form
    echo($string);

    // Save generation time for profiling (after echo)
    $GLOBALS['form_time'] += utime() - $form_start;
  }
}

/**
 * Generates form elements. The main use for print_search() and print_form(), see examples of this functions.
 *
 * Common options (can be in any(mostly) element type):
 *   (string) id      - element identificator
 *   (array)  attribs - any custom element attrib (where key is attrib name, value - attrib value)
 *   (bool)   offset  - for horizontal forms enable (default) or disable element offset (shift to the right on 180px)
 * Options tree:
 * textarea -\
 *     (string)id, (string)name, (bool)readonly, (bool)disabled, (string)width, (string)class,
 *     (int)rows, (int)cols,
 *     (string)value, (bool,string)placeholder, (bool)ajax, (array)ajax_vars
 * text, input, password -\
 *     (string)id, (string)name, (bool)readonly, (bool)disabled, (string)width, (string)class,
 *     (string)value, (bool,string)placeholder, (bool)ajax, (array)ajax_vars,
 *     (bool)show_password
 * hidden -\
 *     (string)id, (string)value
 * select, multiselect -\
 *     (string)id, (string)name, (bool)readonly, (bool)disabled, (string)onchange, (string)width,
 *     (string)title, (int)size, (bool)right, (bool)live-search, (bool)encode, (bool)subtext
 *     (string)value, (array)values, (string)icon,
 *     values items can be arrays, ie:
 *         value => array('name' => string, 'group' => string, 'icon' => string, 'class' => string, 'style' => string)
 * datetime -\
 *     (string)id, (string)name, (bool)readonly, (bool)disabled,
 *     (string|FALSE)from, (string|FALSE)to, (bool)presets, (string)min, (string)max
 *     (string)value (use it for single input)
 * checkbox, switch, toggle -\
 *     (string)id, (string)name, (bool)readonly, (bool)disabled, (string)onchange,
 *     [switch only]: (bool)revert, (int)width, (string)size, (string)off-color, (string)on-color, (string)off-text, (string)on-text
 *     [toggle only]: (string)view, (string)size, (string)palette, (string)group, (string)label,
 *                    (string)icon-check, (string)label-check, (string)icon-uncheck, (string)label-uncheck
 *     (string)value, (string)placeholder, (string)title
 * submit -\
 *     (string)id, (string)name, (bool)readonly, (bool)disabled,
 *     (string)class, (bool)right, (string)tooltip,
 *     (string)value, (string)form_id, (string)icon
 * html, raw -\
 *     (string)id, (bool)offset,
 *     (string)html, (string)value
 * newline -\
 *     (string)id,
 *     (bool)hr
 *
 * @param array $item Options for current form element
 * @param string $type Type of form element, also can passed as $item['type']
 * @return string Generated form element
 */
function generate_form_element($item, $type = '')
{
  $value_isset = isset($item['value']);
  if (!$value_isset) { $item['value'] = ''; }
  if (!isset($item['type']))  { $item['type'] = $type; }
  $string          = '';
  $element_tooltip = '';
  $element_attribs = '';
  if (isset($item['attribs']) && is_array($item['attribs']))
  {
    // Custom html attributes
    foreach ($item['attribs'] as $attr => $value)
    {
      if (preg_match('/^(data\-.+|aria\-.+|role)$/', $attr)) // Filter attributes (data-*, aria-*, role)
      {
        $element_data .= ' ' . escape_html($attr) . '="' . escape_html($value) . '"';
      }
    }
    if (isset($item['attribs']['data-toggle']))
    {
      // Enable item specific JS/CSS/Script
      switch ($item['attribs']['data-toggle'])
      {
        case 'confirm':
        case 'confirmation':
          // popConfirm
          register_html_resource('js',     'jquery.popconfirm.js');
          register_html_resource('script', '$("[data-toggle=\'' . $item['attribs']['data-toggle'] . '\']").popConfirm();');
          break;
        //case 'switch':
        //  // bootstrapSwitch
        //  register_html_resource('js',     'bootstrap-switch.min.js');
        //  //register_html_resource('css',    'bootstrap-switch.css');
        //  register_html_resource('script', '$("[data-toggle=\'' . $item['attribs']['data-toggle'] . '\']").bootstrapSwitch();');
        //  break;
        case 'toggle':
          // TinyToggle
          $script = '';
          if ($item['onchange'])
          {
            // Here toggle specific onchange behavior
            $script .= 'onChange: function(obj, value) { ' . $item['onchange'] . ' },';
            unset($item['onchange']);
          }
          register_html_resource('js',     'jquery.tinytoggle.min.js');
          register_html_resource('css',    'tinytoggle.min.css');
          register_html_resource('script', '$("[data-toggle=\'' . $item['attribs']['data-toggle'] . '\']").tinyToggle({'.$script.'});');
          break;
      }
    }
  }
  switch ($item['type'])
  {
    case 'hidden':
      if (!$item['readonly'] && !$item['disabled']) // If item readonly or disabled, just skip item
      {
        $string .= '    <input type="'.$item['type'].'" name="'.$item['id'] . '" id="' .$item['id'] . '" value="'.$item['value'].'"'.$element_data.' />' . PHP_EOL;
      }
      break;

    case 'password':
    case 'textarea':
    case 'text':
    case 'input':
      if ($item['type'] != 'textarea')
      {
        $item_begin = '    <input type="'.$item['type'].'" ';
        // password specific options
        if ($item['type'] == 'password')
        {
          // disable autocomplete for passwords
          //$item_begin .= ' autocomplete="off" ';
          // http://stackoverflow.com/questions/15738259/disabling-chrome-autofill
          if (!(isset($item['autocomplete']) && $item['autocomplete']))
          {
            $item_begin .= ' autocomplete="new-password" ';
          }
          // mask password field for disabled/readonly by bullet
          if (strlen($item['value']) && ($item['disabled'] || $item['readonly']))
          {
            if (!($item['show_password'] && $_SESSION['userlevel'] > 7)) // For admin, do not replace, required for show password
            {
              $item['value'] = '&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;';
            }
          }
          // add icon for show/hide password
          if ($item['show_password'])
          {
            $item_begin .= ' data-toggle="password" ';
            register_html_resource('js', 'bootstrap-show-password.min.js');
            $GLOBALS['cache_html']['javascript'][] = "$('[data-toggle=\"password\"]').password();";
          }
        }
        $item_end   = ' value="'.$item['value'].'" />';
        $item_class = 'input';
      } else {
        $item_begin = '    <textarea ';
        // textarea specific options
        if (is_numeric($item['rows']))
        {
          $item_begin .= 'rows="' . $item['rows'] . '" ';
        }
        if (is_numeric($item['cols']))
        {
          $item_begin .= 'cols="' . $item['cols'] . '" ';
        }
        $item_end   = '>' . $item['value'] . '</textarea>';
        $item_class = 'form-control';
      }
      $item_begin .= $element_data; // Add custom data- attribs
      if ($item['disabled'])
      {
        $item_end = ' disabled="1"' . $item_end;
      }
      else if ($item['readonly'])
      {
        $item_end = ' readonly="1"' . $item_end;
      }

      if (isset($item['placeholder']) && $item['placeholder'] !== FALSE)
      {
        if ($item['placeholder'] === TRUE)
        {
          $item['placeholder'] = $item['name'];
        }
        $string .= PHP_EOL;
        $string .= $item_begin . 'placeholder="'.$item['placeholder'].'" ';
        $item['placeholder'] = TRUE; // Set to true for check at end
      } else {
        $string .= '  <div class="input-prepend">' . PHP_EOL;
        if (!$item['name']) { $item['name'] = '<i class="icon-list"></i>'; }
        $string .= '    <span class="add-on">'.$item['name'].'</span>' . PHP_EOL;
        $string .= $item_begin;
      }
      if ($item['class'])
      {
        $item_class .= ' ' . $item['class'];
      }

      $string .= (isset($item['width'])) ? 'style="width:' . $item['width'] . '" ' : '';
      $string .= 'name="'.$item['id'] . '" id="' .$item['id'] . '" class="' . $item_class;

      if ($item['ajax'] === TRUE && is_array($item['ajax_vars']))
      {
        $ajax_vars = array();
        if (!isset($item['ajax_vars']['field']))
        {
          // If query field not specified use item id as field
          $item['ajax_vars']['field'] = $item['id'];
        }
        foreach ($item['ajax_vars'] as $k => $v)
        {
          $ajax_vars[] = urlencode($k) . '=' . var_encode($v);
        }
        $string .= ' ajax-typeahead" autocomplete="off" data-link="/ajax/input.php?' . implode('&amp;', $ajax_vars);

        // Register scripts/css
        register_html_resource('js', 'typeahead.bundle.min.js');
        register_html_resource('css', 'typeaheadjs.css');

        // Ajax autocomplete for input
        // <input type='text' class='ajax-typeahead' data-link='your-json-link' />
        $item_id = $item['id'];
        $script = <<<SCRIPT
  var element_$item_id = $('#$item_id.ajax-typeahead');
  var entries_$item_id = new Bloodhound({
    datumTokenizer: Bloodhound.tokenizers.obj.whitespace('value'),
    queryTokenizer: Bloodhound.tokenizers.whitespace,
    remote: {
      url: element_$item_id.data('link') + '&query=%QUERY',
      wildcard: '%QUERY',
      filter: function(json) {
        return json.options;
      }
    }
  });
  element_$item_id.typeahead({
      hint: false,
      highlight: true,
      minLength: 1
    },
    {
      name: 'options',
      limit: 16,
      source: entries_$item_id
    }
  );
SCRIPT;
        register_html_resource('script', $script);
      } // end ajax

      $string .= '" ' . $item_end . PHP_EOL;
      $string .= ($item['placeholder'] ? PHP_EOL : '  </div>' . PHP_EOL);
      // End 'text' & 'input'
      break;

    case 'switch':
      // switch specific options
      if (isset($item['revert']) && $item['revert'])
      {
        // Fallback for old preconfigured style
        if (!isset($item['size']))      { $item['size']      = 'mini'; }
        if (!isset($item['on-color']))  { $item['on-color']  = 'danger'; }
        if (!isset($item['off-color'])) { $item['off-color'] = 'primary'; }
        if (!isset($item['on-text']))   { $item['on-text']   = 'No'; }
        if (!isset($item['off-text']))  { $item['off-text']  = 'Yes'; }
        unset($item['revert']);
      }
      // Convert to data attribs and recursive call to checkbox
      $item['attribs']['data-toggle'] = 'switch';
      $item_attribs = array('size', 'on-color', 'on-text', 'off-color', 'off-text');
      foreach($item_attribs as $attr)
      {
        if (isset($item[$attr])) { $item['attribs']['data-'.$attr] = $item[$attr]; }
      }
      if (is_numeric($item['width']) && $item['width'] > 10)
      {
        $item['attribs']['data-handle-width'] = intval($item['width'] / 2);
      }
      $item['type'] = 'checkbox'; // replace item type
      return generate_form_element($item);
      // end switch

    case 'toggle':
      // toggle specific options
      // Convert to data attribs and recursive call to checkbox
      $item['attribs']['data-toggle'] = 'toggle';
      $item_attribs = array('size', 'palette', 'group', 'label', 'icon-check', 'label-check', 'icon-uncheck', 'label-uncheck');
      foreach($item_attribs as $attr)
      {
        if (isset($item[$attr])) { $item['attribs']['data-tt-'.$attr] = $item[$attr]; }
      }
      // Types: http://tinytoggle.simonerighi.net/#types
      if (in_array($item['view'], array('toggle', 'check', 'circle', 'square', 'square_v', 'power', 'dot', 'like', 'watch', 'star', 'lock', 'heart', 'smile')))
      {
        $item['attribs']['data-tt-type'] = $item['view'];
      } else {
        $item['attribs']['data-tt-type'] = 'square'; // default type
      }
      // Onchange target id
      if ($item['onchange-id'])
      {
        $item['attribs']['data-onchange-id'] = $item['onchange-id'];
      }

      $item['class'] .= ' tiny-toggle'; // additional class for toggle
      $item['type'] = 'checkbox'; // replace item type
      return generate_form_element($item);
      // end toggle

    case 'checkbox':
      $string = '    <input type="checkbox" ';
      $string .= ' name="'.$item['id'] . '" id="' .$item['id'] . '" ' . $item_switch;
      if ($item['title'])
      {
        $string .= ' data-rel="tooltip" data-tooltip="'.escape_html($item['title']).'"';
      }
      if ($item['value'] == '1' || $item['value'] === 'on' || $item['value'] === 'yes' || $item['value'] === TRUE)
      {
        $string .= ' checked';
      }
      if ($item['disabled'])
      {
        $string .= ' disabled="1"';
      }
      else if ($item['readonly'])
      {
        $string .= ' readonly="1" onclick="return false"';
      }
      else if ($item['onchange'])
      {
        $string .= ' onchange="'.$item['onchange'].'"';
      }
      $string .= $element_data; // Add custom data- attribs
      $string .= ' value="1" />';
      if (is_string($item['placeholder']))
      {
        // add placeholder text at right of the element
        $string .= '      <label for="' . $item['id'] . '" class="help-inline" style="margin-top: 4px;">' .
                   $item['placeholder'] . '</label>' . PHP_EOL;
      }
      // End 'switch' & 'checkbox'
      break;

    case 'datetime':
      register_html_resource('js', 'bootstrap-datetimepicker.min.js'); // Enable DateTime JS
      $id_from = $item['id'].'_from';
      $id_to = $item['id'].'_to';
      if ($value_isset && !$item['from'] && !$item['to'])
      {
        // Single datetime input
        $item['from']    = $item['value'];
        $item['to']      = FALSE;
        $item['presets'] = FALSE;
        $id_from      = $item['id'];
        $name_from    = $item['name'];
      } else {
        $name_from = 'From';
      }
      // Presets
      if ($item['from'] === FALSE || $item['to'] === FALSE) { $item['presets'] = FALSE; }

      if (is_numeric($item['from'])) { $item['from'] = strftime("%F %T", $item['from']); }
      if (is_numeric($item['to']))   { $item['to']   = strftime("%F %T", $item['to']); }

      if ($item['presets'])
      {
        $presets = array('sixhours'   => 'Last 6 hours',
                         'today'      => 'Today',
                         'yesterday'  => 'Yesterday',
                         'tweek'      => 'This week',
                         'lweek'      => 'Last week',
                         'tmonth'     => 'This month',
                         'lmonth'     => 'Last month',
                         'tquarter'   => 'This quarter',
                         'lquarter'   => 'Last quarter',
                         'tyear'      => 'This year',
                         'lyear'      => 'Last year');
        // Recursive call
        $preset_item = array('id'     => $item['id'].'_preset',
                             'type'   => 'select',
                             'name'   => 'Date presets',
                             'width'  => '110px',
                             'values' => $presets);
        $string .= generate_form_element($preset_item)  . PHP_EOL;
      }
      // Date/Time input fields
      if ($item['from'] !== FALSE)
      {
        $string .= '  <div id="'.$id_from.'_div" class="input-prepend" style="margin-bottom: 0;">' . PHP_EOL;
        $string .= '    <span class="add-on btn"><i data-time-icon="icon-time" data-date-icon="icon-calendar"></i> '.$name_from.'</span>' . PHP_EOL;
        //$string .= '    <input type="text" class="input-medium" data-format="yyyy-MM-dd hh:mm:ss" ';
        $string .= '    <input type="text" data-format="yyyy-MM-dd hh:mm:ss" ';
        $string .= (isset($item['width'])) ? 'style="width:' . escape_html($item['width']) . '" ' : 'style="width: 130px;" ';
        if ($item['disabled'])
        {
          $string .= 'disabled="1" ';
        }
        else if ($item['readonly'])
        {
          $item['disabled'] = TRUE; // for js
          $string .= 'readonly="1" ';
        }
        $string .= 'name="'.$id_from.'" id="'.$id_from.'" value="'.escape_html($item['from']).'"/>' . PHP_EOL;
        $string .= '  </div>' . PHP_EOL;
      }
      if ($item['to'] !== FALSE)
      {
        $string .= '  <div id="'.$id_to.'_div" class="input-prepend" style="margin-bottom: 0;">' . PHP_EOL;
        $string .= '    <span class="add-on btn"><i data-time-icon="icon-time" data-date-icon="icon-calendar"></i> To</span>' . PHP_EOL;
        //$string .= '    <input type="text" class="input-medium" data-format="yyyy-MM-dd hh:mm:ss" ';
        $string .= '    <input type="text" data-format="yyyy-MM-dd hh:mm:ss"';
        $string .= (isset($item['width'])) ? ' style="width:' . escape_html($item['width']) . '"' : ' style="width: 140px;"';
        $string .= $element_data; // Add custom data- attribs
        $string .= ' name="'.$id_to.'" id="'.$id_to.'" value="'.escape_html($item['to']).'"/>' . PHP_EOL;
        $string .= '  </div>' . PHP_EOL;
      }
      // JS SCRIPT
      $min = '-Infinity';
      $max = 'Infinity';
      $pattern = '/^(\d{4})-(\d{2})-(\d{2}) ([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/';
      if (!empty($item['min']))
      {
        if (preg_match($pattern, $item['min'], $matches))
        {
          $matches[2] = $matches[2] - 1;
          array_shift($matches);
          $min = 'new Date(' . implode(',', $matches) . ')';
        }
        else if ($item['min'] == 'now' || $item['min'] == 'current')
        {
          $min = 'new Date()';
        }
      }
      if (!empty($item['max']))
      {
        if (preg_match($pattern, $item['max'], $matches))
        {
          $matches[2] = $matches[2] - 1;
          array_shift($matches);
          $max = 'new Date(' . implode(',', $matches) . ')';
        }
        else if ($item['max'] == 'now' || $item['max'] == 'current')
        {
          $max = 'new Date()';
        }
      }

      $script = '
      var startDate = '.$min.';
      var endDate   = '.$max.';
      $(document).ready(function() {
        $(\'[id='.$id_from.'_div]\').datetimepicker({
          //pickSeconds: false,
          weekStart: 1,
          startDate: startDate,
          endDate: endDate
        });';
      if ($item['disabled'])
      {
        $script .= '
        $(\'[id='.$id_from.'_div]\').datetimepicker(\'disable\');';
      }
      if ($item['to'] !== FALSE)
      {
        $script .= '
        $(\'[id='.$id_to.'_div]\').datetimepicker({
          //pickSeconds: false,
          weekStart: 1,
          startDate: startDate,
          endDate: endDate
        });';
      }
      $script .= '
      });' . PHP_EOL;

      if ($item['presets'])
      {
        $script .= '
      $(\'select[id='.$item['id'].'_preset]\').change(function() {
        var input_from = $(\'input#'.$id_from.'\');
        var input_to   = $(\'input#'.$id_to.'\');
        switch ($(this).val()) {' . PHP_EOL;
          foreach ($presets as $k => $v)
          {
            $preset = datetime_preset($k);
            $script .= "          case '$k':\n";
            $script .= "            input_from.val('".$preset['from']."');\n";
            $script .= "            input_to.val('".$preset['to']."');\n";
            $script .= "            break;\n";
          }
          $script .= '
          default:
            input_from.val("");
            input_to.val("");
            break;
        }
      });';
      }
      register_html_resource('script', $script);
      // End 'datetime'
      break;

    case 'tags': // Tags mostly same as multiselect, but used separate options and Bootstrap Tags Input JS
      register_html_resource('js',  'bootstrap-tagsinput.min.js');  // Enable Tags Input JS
      //register_html_resource('js',  'bootstrap-tagsinput.js');      // Enable Tags Input JS
      register_html_resource('css', 'bootstrap-tagsinput.css');     // Enable Tags Input CSS
      // defaults
      $delimiter = empty($item['delimiter']) ? ',' : $item['delimiter'];
      $script_begin   = '';
      $script_options = array('trimValue' => 'true',
                              'tagClass'  => 'function(item) {return "label label-default";}',
                              );
      //register_html_resource('script', '$("input[data-role=tagsinput], select[multiple][data-role=tagsinput]").tagsinput({trimValue: true, tagClass: function(item) {return "label label-default";} });');

      $string .= '    <select multiple data-toggle="tagsinput" name="'.$item['id'].'[]" ';
      $string .= 'id="'.$item['id'].'" ';

      if      ($item['title'])       { $string .= 'title="' . $item['title'] . '" '; }
      else if (isset($item['name'])) { $string .= 'title="' . $item['name']  . '" '; }
      if (isset($item['placeholder']) && $item['placeholder'] !== FALSE)
      {
        if ($item['placeholder'] === TRUE)
        {
          $item['placeholder'] = $item['name'];
        }
        //$string .= PHP_EOL;
        $string .= ' placeholder="'.$item['placeholder'].'"';
        //$item['placeholder'] = TRUE; // Set to true for check at end
      }

      if ($item['disabled'])
      {
        $string .= ' disabled="1"';
      }
      else if ($item['readonly'])
      {
        $string .= ' disabled="1" readonly="1"'; // Bootstrap Tags Input not support readonly attribute, currently use disable
      }
      if ($item['onchange'])
      {
        $string .= ' onchange="'.$item['onchange'].'"';
      }
      $string .= $element_data; // Add custom data- attribs
      $string .= '>' . PHP_EOL . '      '; // end <select>

      // Process values
      if (!is_array($item['value']))
      {
        //$item['value'] = explode($delimiter, $item['value']);
        $item['value'] = array($item['value']);
      }
      //$item['value'] = array('test', 'hello');

      $suggest = array();
      foreach ($item['value'] as $entry)
      {
        $value   = (string)$entry;
        if ($value == '[there is no data]' || $value === '') { continue; }
        $suggest[] = $value;

        $string .= '<option value="'.$value.'"';
        $string .= '>'.escape_html($value).'</option> ';
      }
      $string .= PHP_EOL . '    </select>' . PHP_EOL;

      // Generate typeahead from values
      $item['values'] = (array)$item['values'];
      if (is_array_assoc($item['values']))
      {
        // convert associative values to simple
        $item['values'] = array_keys($item['values']);
      }
      $suggest = array_merge($suggest, $item['values']);
      if (count($suggest))
      {
        $option = '[{ hint: false, highlight: true, minLength: 1 },
                    { name: "suggest", limit: 16, source: suggest_'.$item['id'].' }]';

        $script_begin .= 'var suggest_' . $item['id'] . ' = new Bloodhound({ matchAnyQueryToken: true, queryTokenizer: Bloodhound.tokenizers.nonword, datumTokenizer: Bloodhound.tokenizers.nonword,
        local: [';
        $values = array();
        foreach (array_unique($suggest) as $k => $entry)
        {
          if (is_array($entry))
          {
            $value = (string)$k;
          } else {
            $value = (string)$entry;
          }
          $values[] = "'" . str_replace("'", "\'", $value) . "'";
        }
        $script_begin .= implode(',', $values);
        $script_begin .= ']});' . PHP_EOL;

        $script_options['typeaheadjs'] = $option;

        // Register scripts/css
        //register_html_resource('js', 'typeahead.bundle.js');
        register_html_resource('js', 'typeahead.bundle.min.js');
        register_html_resource('css', 'typeaheadjs.css');
      }

      if (count($script_options))
      {
        $script  = $script_begin;
        $script .= "$('#".$item['id']."').tagsinput({" . PHP_EOL;
        foreach ($script_options as $key => &$option)
        {
          $option = '  ' . $key . ': ' . $option;
        }
        $script .= implode(','.PHP_EOL, $script_options) . PHP_EOL;
        $script .= "});";
        register_html_resource('script', $script);
      }
      // End 'tags'
      break;

    case 'multiselect':
      unset($item['icon']); // For now not used icons in multiselect
    case 'select':
      $count_values = count($item['values']);
      if (empty($item['values']))
      {
        $item['values'] = array(0 => '[there is no data]');
        $item['subtext'] = FALSE;
      }
      if ($item['type'] == 'multiselect')
      {
        $string .= '    <select multiple name="'.$item['id'].'[]" ';
        // Enable Select/Deselect all (if select values count more than 4)
        if ($count_values > 4)
        {
          $string .= ' data-actions-box="true" ';
        }
      } else {
        $string .= '    <select name="'.$item['id'].'" ';
      }
      $string .= 'id="'.$item['id'].'" ';
      if      ($item['title'])       { $string .= 'title="' . $item['title'] . '" '; }
      else if (isset($item['name'])) { $string .= 'title="' . $item['name']  . '" '; }

      $data_width = ($item['width']) ? ' data-width="'.$item['width'].'"' : ' data-width="auto"';
      $data_size = (is_numeric($item['size'])) ? ' data-size="'.$item['size'].'"' : ' data-size="15"';
      $string .= 'class="selectpicker show-tick';
      if ($item['right']) { $string .= ' pull-right'; }
      $string .= '" data-selected-text-format="count>2"';
      if ($item['data-style']) { $string .= ' data-style="'.$item['data-style'].'"'; }
      // Enable Live search in values list (if select values count more than 12)
      if (($count_values > 12 || $count_values == 0 )&& $item['live-search'] !== FALSE) { $string .= ' data-live-search="true"'; }

      if ($item['disabled'])
      {
        $string .= ' disabled="1"';
      }
      else if ($item['readonly'])
      {
        $string .= ' disabled="1" readonly="1"'; // Bootstrap select not support readonly attribute, currently use disable
      }
      if ($item['onchange'])
      {
        $string .= ' onchange="'.$item['onchange'].'"';
      }
      $string .= $element_data; // Add custom data- attribs

      $string .= $data_width . $data_size . '>' . PHP_EOL . '      '; // end <select>
      if (!is_array($item['value'])) { $item['value'] = array($item['value']); }

      // Prepare values for optgroups
      $values = array();
      $optgroup = array();
      foreach ($item['values'] as $k => $entry)
      {
        $k = (string)$k;
        $value   = ($item['encode'] ? var_encode($k) : $k); // Use base64+serialize encoding
        // Default group is '' (empty string), for allow to use 0 as group name!
        $group = '';
        if (!is_array($entry))
        {
          $entry = array('name' => $entry);
        }
        else if (isset($entry['group']))
        {
          $group = $entry['group'];
        }
        if ($item['subtext'] && !isset($entry['subtext']))
        {
          $entry['subtext'] = $k;
        }

        // Icons and empty name fix
        if ($item['icon'] && $item['value'] === array(''))
        {
          // Only one main icon
          $entry['icon'] = $item['icon']; // Set value icon as global icon
          unset($item['icon']);
        }
        if (in_array($k, $item['value']))
        {
          if (!($k === '' && $entry['name'] === '')) // additionaly skip if value and name empty
          {
            if ($item['icon'])
            {
              $entry['icon'] = $item['icon']; // Set value icon as global icon
            }
            // Element selected
            $entry['selected'] = TRUE;
          }
        }
        else if ($entry['name'] == '[there is no data]')
        {
          $entry['disabled'] = TRUE;
        }
        if (strlen($entry['name']) == 0 && $k !== '') { $entry['name'] = $k; } // if name still empty set it as value

        $values[$group][$value] = $entry;
      }

      // Generate optgroups for values
      foreach ($values as $group => $entries)
      {
        $optgroup[$group] = '';
        foreach ($entries as $value => $entry)
        {
          $optgroup[$group] .= '<option value="'.$value.'"';
          if (isset($entry['subtext']) && strlen($entry['subtext']))
          {
            $optgroup[$group] .= ' data-subtext="' . $entry['subtext'] . '"';
          }
          if ($entry['name'] == '[there is no data]')
          {
            $optgroup[$group] .= ' disabled="1"';
          }

          if (isset($entry['class']) && $entry['class'])
          {
            $optgroup[$group] .= ' class="' . $entry['class'] . '"';
          }
          else if (isset($entry['style']) && $entry['style'])
          {
            $optgroup[$group] .= ' style="' . $entry['style'] . '"';
          }
          else if (isset($entry['color']) && $entry['color'])
          {
            $optgroup[$group] .= ' style="color:' . $entry['color'] . ' !important;"';
            //$optgroup[$group] .= ' data-content="<span style=\'color: ' . $entry['color'] . '\'>' . $entry['name'] . '</span>"';
          }

          // Icons
          if (isset($entry['icon']) && $entry['icon'])
          {
            $optgroup[$group] .= ' data-icon="'.$entry['icon'].'"';
          }
          // Disabled, Selected
          if (isset($entry['disabled']) && $entry['disabled'])
          {
            $optgroup[$group] .= ' disabled="1"';
          }
          else if (isset($entry['selected']) && $entry['selected'])
          {
            $optgroup[$group] .= ' selected';
          }

          $optgroup[$group] .= '>'.escape_html($entry['name']).'</option> ';
        }
      }

      // If item groups passed, use order passed from it
      $optgroups = array_keys($optgroup);
      if (isset($item['groups']))
      {
        $groups = array_intersect((array)$item['groups'], $optgroups);
        $optgroups = array_diff($optgroups, $groups);
        $optgroups = array_merge($groups, $optgroups);
      }

      if (count($optgroups) === 1) // && isset($optgroup['']))
      {
        // Single optgroup, do not use optgroup tags
        $string .= array_shift($optgroup);
      } else {
        // Multiple optgroups implode
        foreach($optgroups as $group)
        {
          $entry = $optgroup[$group];
          $label = ($group !== '' ? ' label="'.$group.'"' : '');
          $string .= '<optgroup'.$label.'>' . PHP_EOL;
          $string .= $entry;
          $string .= '</optgroup>' . PHP_EOL;
        }
      }

      $string .= PHP_EOL . '    </select>' . PHP_EOL;
      // End 'select' & 'multiselect'
      break;
    case 'submit':
      $button_type    = 'submit';
      $button_onclick = '';
      if (isset($item['icon_only']) && $item['icon_only'] && $item['icon'])
      {
        // icon only submit button
        $button_class   = 'btn-icon';
        if (!empty($item['class']))
        {
          $button_class .= ' ' . $item['class'];
        }
      } else {
        // classic submit button
        $button_class   = 'btn';
        if (!empty($item['class']))
        {
          if (!preg_match('/btn-(default|primary|success|info|warning|danger)/', $item['class']))
          {
            // Add default class if custom class hot have it
            $button_class .= ' btn-default';
          }
          $button_class .= ' ' . $item['class'];
        } else {
          $button_class .= ' btn-default';
        }
      }
      if ($item['right'])
      {
        $button_class .= ' pull-right';
      }
      if ($item['form_id'] && $item['id'] == 'search')
      {
        // Note, used script form_to_path() stored in js/observium.js
        $button_type    = 'button';
        $button_onclick = " onclick=\"form_to_path('".$item['form_id']."');\"";
      }

      $button_disabled = $item['disabled'] || $item['readonly'];
      if ($button_disabled)
      {
        $button_class .= ' disabled';
      }

      $string .= '      <button id="' . $item['id'] . '" name="' . $item['id'] . '" type="'.$button_type.'"';

      // Add tooltip data
      if ($item['tooltip'])
      {
        $button_class .= ' tooltip-from-element';
        $string .= ' data-tooltip-id="tooltip-' . $item['id'] . '"';
        $element_tooltip .= '<div id="tooltip-' . $item['id'] . '" style="display: none;">' . $item['tooltip'] . '</div>' . PHP_EOL;
      }

      //$string .= ' class="'.$button_class.' text-nowrap" style="line-height: 20px;"'.$button_onclick;
      $string .= ' class="'.$button_class.' text-nowrap"'.$button_onclick;
      if ($button_disabled)
      {
        $string .= ' disabled="1"';
      }

      if ($item['value'])
      {
        $string .= ' value="' . $item['value'] . '"';
      }
      $string .= $element_data; // Add custom data- attribs
      $string .= '>';
      switch($item['id'])
      {
        // Note. 'update' - use POST request, all other - use GET with generate url from js.
        case 'update':
          $button_icon = 'icon-refresh';
          $button_name = 'Update';
          break;
        default:
          $button_icon = 'icon-search';
          $button_name = 'Search';
      }
      $nbsp = 0;
      if (array_key_exists('icon', $item)) { $button_icon = trim($item['icon']); }
      if (strlen($button_icon))
      {
        $string .= '<i class="'.$button_icon.'" style="margin-right: 0px;"></i>'; // Override margin style, here used "own" margin
        $nbsp++;
      }

      if (array_key_exists('name', $item)) { $button_name = trim($item['name']); }
      if (strlen($button_name))
      {
        $nbsp++;
      }

      if ($nbsp == 2)
      {
        $string .= '&nbsp;&nbsp;';
      }
      $string .= $button_name.'</button>' . PHP_EOL;
      // End 'submit'
      break;
    case 'raw':
    case 'html':
      // Just add custom (raw) html element
      if (isset($item['html']))
      {
        $string .= $item['html'];
      } else {
        $string .= '<span';
        if (isset($item['class']))
        {
          $string .= ' class="' . $item['class'] . '"';
        }
        $string .= '>' . $item['value'] . '</span>';
      }
      break;
    case 'newline': // Deprecated
      $string .= '<div class="clearfix" id="'.$item['id'].'">';
      $string .= ($item['hr'] ? '<hr />' : '<hr style="border-width: 0px;" />');
      $string .= '</div>' . PHP_EOL;
      // End 'newline'
      break;
  }

  return($string . $element_tooltip);
}

function generate_form_values($type, $form_filter = FALSE, $column = NULL, $options = array())
{
  //global $cache;

  $form_items = array();
  switch ($type)
  {
    case 'example':
      break;
    default:
      // Entity based
      $form_function = 'generate_' . $type . '_form_values';
      if (function_exists($form_function))
      {
        return call_user_func_array($form_function, array($form_filter, $column, $options));
      }
  }

  return $form_items;
}

/**
 * Complex function for generate modal window.
 * Use it when an simple modal used.
 *
 * Used args:
 *  id, title, icon, class, body, footer,
 *  hide (default TRUE), fade (default TRUE), role (default dialog)
 *
 * Note, if used separate functions generate_modal_open(), generate_modal_close()
 * then required to add body content inside div <div class="modal-body"></div>
 * and if also used footer, it should be inside <div class="modal-footer"></div>
 *
 * @param array $args Array with arguments
 * @return string
 */
function generate_modal($args)
{
/*
<!-- Modal -->
<div class="modal fade" id="myModal" tabindex="-1" role="dialog" aria-labelledby="myModalLabel">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title" id="myModalLabel">Modal title</h4>
      </div>
      <div class="modal-body">
        ...
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
        <button type="button" class="btn btn-primary">Save changes</button>
      </div>
    </div>
  </div>
</div>
*/
  //print_vars($args);

  // Begin & Header
  $string = generate_modal_open($args);

  // Body
  $string .= '      <div class="modal-body">' . PHP_EOL .
             $args['body']                    . PHP_EOL .
             '      </div>'                   . PHP_EOL;

  // Footer
  if (strlen($args['footer']))
  {
    $string .= '      <div class="modal-footer">' . PHP_EOL .
               $args['footer']                    . PHP_EOL .
               '      </div>'                     . PHP_EOL;
  }

  // End
  $string .= generate_modal_close($args);

  return $string;
}

/**
 * Generates begin of modal window
 * See descriptions for generate_modal()
 *
 * @param array $args Array with arguments
 * @return string
 */
function generate_modal_open(&$args)
{
  if (!isset($args['id'])) { $args['id'] = 'modal-'.strgen('4'); }

  $string = PHP_EOL . '<!-- START modal ' . $args['id'] . ' -->' . PHP_EOL;

  // Create base class
  $base_class = 'modal';
  if (isset($args['hide']) && !$args['hide']) {} else // Hide by default
  {
    $base_class .= ' hide';
  }
  if (isset($args['fade']) && !$args['fade']) {} else // Fade by default
  {
    $base_class .= ' fade';
  }
  if (!isset($args['role'])) // Role dialog by default
  {
    $args['role'] = 'dialog';
  }
  $args['class'] = (isset($args['class'])) ? ' ' . $args['class'] : '';

  $string .= '<div class="' . $base_class . '" id="' . $args['id'] . '" tabindex="-1"';

  if ($args['role'] == 'dialog')
  {
    $string .= ' role="dialog" aria-labelledby="' . $args['id'] . '_label">' . PHP_EOL;
  } else {
    $string .= ' role="document">' . PHP_EOL;
  }
  $string .= '  <div class="modal-dialog'.$args['class'].'" role="document">' . PHP_EOL .
             '    <div class="modal-content">' . PHP_EOL;

  // Header
  $string .= '      <div class="modal-header">' . PHP_EOL .
             '        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>' . PHP_EOL;
  if (isset($args['title']))
  {
    $string .= '        <h3 class="modal-title" id="' . $args['id'] . '_label">';
    if ($args['icon'])
    {
      $string .= '<i class="' . $args['icon'] . '"></i>&nbsp;';
    }
    $string .= $args['title'] . '</h3>' . PHP_EOL;
  }
  $string .= '      </div>' . PHP_EOL;

  return $string;
}

/**
 * Generates end of modal window
 * See descriptions for generate_modal()
 *
 * @param array $args Array with arguments
 * @return string
 */
function generate_modal_close($args)
{
  $string  = '    </div>' . PHP_EOL .
             '  </div>'   . PHP_EOL .
             '</div>'     . PHP_EOL;
  $string .= '<!-- END modal ' . $args['id'] . ' -->' . PHP_EOL;

  return $string;
}

// Modal specific form
function generate_form_modal($form)
{
  // Just return if safety requirements are not fulfilled
  if (isset($form['userlevel']) && $form['userlevel'] > $_SESSION['userlevel']) { return; }

  // Time our form filling.
  $form_start = microtime(TRUE);

  // Use modal with form
  if (isset($form['modal_args']))
  {
    $modal_args = $form['modal_args'];
    unset($form['modal_args']);
  } else {
    $modal_args = array();
  }

  if (!isset($modal_args['id']) && isset($form['id']))
  {
    // Generate modal id from form id
    if (str_starts($form['id'], 'modal-'))
    {
      $modal_args['id'] = $form['id'];
      $form['id']       = substr($form['id'], 6);
    } else {
      $modal_args['id'] = 'modal-' . $form['id'];
    }
  }
  if (!isset($modal_args['title']) && isset($form['title']))
  {
    // Move form title to modal header
    $modal_args['title'] = $form['title'];
    unset($form['title']);
  }

  $form['class'] = ''; // Clean default box class!
  $form['fieldset']['body']['class']   = 'modal-body';   // Required this class for modal body!
  $form['fieldset']['footer']['class'] = 'modal-footer'; // Required this class for modal footer!

  $modal  = generate_modal_open($modal_args);

  // Save generation time for profiling
  $GLOBALS['form_time'] += utime() - $form_start;

  $modal .= generate_form($form);

  // Time our form filling.
  $form_start = microtime(TRUE);

  $modal .= generate_modal_close($modal_args);

  // Save generation time for profiling
  $GLOBALS['form_time'] += utime() - $form_start;

  return $modal;
}

// EOF
