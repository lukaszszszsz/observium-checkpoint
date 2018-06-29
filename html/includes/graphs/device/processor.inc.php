<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage graphs
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2016 Observium Limited
 *
 */

$sql = "SELECT * FROM `processors` WHERE `processor_type` != 'hr-average' AND `device_id` = ?";
if (isset($vars['id']))
{
  $sql .=  generate_query_values($vars['id'], 'processor_id');
}
$procs = dbFetchRows($sql, array($device['device_id']));

if ($config['os'][$device['os']]['processor_stacked'] == 1)
{
  include($config['html_dir']."/includes/graphs/device/processor_stack.inc.php");
} else {
  include($config['html_dir']."/includes/graphs/device/processor_separate.inc.php");
}

// EOF
