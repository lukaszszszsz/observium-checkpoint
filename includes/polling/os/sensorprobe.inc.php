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

$hardware = snmp_get($device, 'spManufName.0', '-OQv', 'SPAGENT-MIB');
$hardware .= ' ' . snmp_get($device, 'spProductName.0', '-OQv', 'SPAGENT-MIB');

// EOF
