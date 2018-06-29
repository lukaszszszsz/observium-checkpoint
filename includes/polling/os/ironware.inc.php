<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage poller
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2016 Observium Limited
 *
 */

$hardware = rewrite_definition_hardware($device, $poll_device['sysObjectID']);
if (!$hardware)
{
  $hardware = snmp_translate($poll_device['sysObjectID'], 'FOUNDRY-SN-AGENT-MIB:FOUNDRY-SN-ROOT-MIB');
}

// EOF
