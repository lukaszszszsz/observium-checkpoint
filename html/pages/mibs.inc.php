<?php

/**
 * Observium Network Management and Monitoring System
 * Copyright (C) 2006-2015, Adam Armstrong - http://www.observium.org
 *
 * @package    observium
 * @subpackage webui
 * @author     Adam Armstrong <adama@observium.org>
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2016 Observium Limited
 *
 */

if ($_SESSION['userlevel'] <= 7)
{
  print_error_permission();
  return;
}

// Fetch all MIBs we support for specific OSes
foreach ($config['os'] as $os => $data)
{
  foreach ($data['mibs'] as $mib)
  {
    $mibs[$mib]['oses'][$os] = TRUE;
  }
}

// Fetch all MIBs we support for specific OS groups
foreach ($config['os_group'] as $os => $data)
{
  foreach ($data['mibs'] as $mib)
  {
    $mibs[$mib]['oses'][$os] = TRUE;
  }
}

ksort($mibs);

$obs_attribs    = get_obs_attribs('mib_');
$defined_config = get_defined_settings(); // Used defined configs in config.php

// r($vars);

if ($vars['toggle_mib'] && isset($mibs[$vars['toggle_mib']]) &&
    !isset($defined_config['mibs'][$mib]['enable'])) // Ignore if defined in config.php
{
  $mib = $vars['toggle_mib'];

  $mib_disabled = isset($config['mibs'][$mib]['enable']) && !$config['mibs'][$mib]['enable'];
  $set_attrib   = $mib_disabled ? 1 : 0;

  if (isset($obs_attribs['mib_'.$mib]))
  {
    del_obs_attrib('mib_' . $mib);
  } else {
    set_obs_attrib('mib_' . $mib, $set_attrib);
  }

  $obs_attribs = get_obs_attribs('mib_');

}

print_message("This page allows you to globally disable individual MIBs. This configuration disables all discovery and polling using this MIB.");

// r($obs_attribs);

?>

<div class="row"> <!-- begin row -->

  <div class="col-md-12">

<?php
   $box_args = array('title' => 'Global MIB Configuration',
                                'header-border' => TRUE,
                    );

  echo generate_box_open($box_args);

?>


<table class="table  table-striped table-condensed ">
  <thead>
    <tr>
      <th>Module</th>
      <th>Description</th>
      <th style="width: 60px;">Status</th>
      <th></th>
    </tr>
  </thead>
  <tbody>

<?php

foreach ($mibs as $mib => $data)
{

  $attrib_set = isset($obs_attribs['mib_'.$mib]);
  $class = $attrib_set ? ' class="warning"' : '';

  echo('<tr' . $class . '><td><strong>'.$mib.'</strong></td>');

  if (isset($config['mibs'][$mib])) { $descr = $config['mibs'][$mib]['descr']; } else { $descr = ''; }

/*
echo('<pre>

$mib = "'.$mib.'";
$config[\'mibs\'][ $mib ][\'mib_dir\'] = "";
$config[\'mibs\'][ $mib ][\'descr\']   = "";

</pre>');
*/

  echo '<td>'.$descr.'</td>';

  echo '<td>';

  $readonly = FALSE;
  $btn_value = '';
  $btn_tooltip = '';
  if (isset($defined_config['mibs'][$mib]['enable']) && !$defined_config['mibs'][$mib]['enable'])
  {
    // Disabled in config.php
    $attrib_status = '<span class="label label-danger">disabled</span>';
    $toggle        = 'Config';
    $btn_class     = '';
    $btn_tooltip   = 'Disabled in config.php, see: <mark>$config[\'mibs\'][\'' . $mib . '\'][\'enable\']</mark>';
    $readonly      = TRUE;
  }
  else if (($attrib_set && $obs_attribs['mib_'.$mib] == 0) ||
           (!$attrib_set && isset($config['mibs'][$mib]['enable']) && !$config['mibs'][$mib]['enable']))
  {
    // Disabled in definitions or manually, can be re-enabled
    $attrib_status = '<span class="label label-danger">disabled</span>';
    $toggle        = 'Enable';
    $btn_class     = 'btn-success';
    $btn_value     = 'Toggle';
  } else {
    $attrib_status = '<span class="label label-success">enabled</span>';
    $toggle        = 'Disable';
    $btn_class     = 'btn-danger';
  }

  echo($attrib_status.'</td><td>');

  $form = array('id'    => 'toggle_mib',
                'type'  => 'simple');
  // Elements
  $form['row'][0]['toggle_mib']  = array('type'     => 'hidden',
                                         'value'    => $mib);
  $form['row'][0]['submit']      = array('type'     => 'submit',
                                         'name'     => $toggle,
                                         'class'    => 'btn-mini '.$btn_class,
                                         'icon'     => '',
                                         'tooltip'  => $btn_tooltip,
                                         'right'    => TRUE,
                                         'readonly' => $readonly,
                                         'value'    => $btn_value);
  print_form($form); unset($form);

  echo('</td></tr>');
}
?>
  </tbody>
</table>

<?php echo generate_box_close(); ?>

  </div> <!-- end row -->
</div> <!-- end container -->

<?php

register_html_title('MIBs');

// EOF
