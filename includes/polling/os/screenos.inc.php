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

$hardware = rewrite_junos_hardware($poll_device['sysObjectID']);

// FIXME move to graph definitions
$snmpdata = snmp_get_multi($device, 'nsResSessAllocate.0 nsResSessMaxium.0 nsResSessFailed.0', '-OQUs', 'NETSCREEN-RESOURCE-MIB');

rrdtool_update_ng($device, 'screenos-sessions', array(
  'allocate' => $snmpdata[0]['nsResSessAllocate'],
  'max'      => $snmpdata[0]['nsResSessMaxium'],
  'failed'   => $snmpdata[0]['nsResSessFailed'],
));

$graphs['screenos_sessions'] = TRUE;

// EOF
