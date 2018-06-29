<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage poller
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2017 Observium Limited
 *
 */

if (!$hardware)
{
  $hardware = 'AW+';
  $version  = snmp_get($device, 'currSoftVersion.0', '-OsvQU', 'AT-SETUP-MIB');
}

// EOF
