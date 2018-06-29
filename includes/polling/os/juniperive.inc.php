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

// FIXME move below to poller graphs definitions

// Users

$clusterusers = snmp_get($device, 'clusterConcurrentUsers.0', '-OQv', 'PULSESECURE-PSG-MIB');
$iveusers     = snmp_get($device, 'iveConcurrentUsers.0', '-OQv', 'PULSESECURE-PSG-MIB');

if (!is_null($clusterusers) and !is_null($iveusers))
{
  rrdtool_update_ng($device, 'juniperive-users', array(
    'clusterusers' => $clusterusers,
    'iveusers'     => $iveusers,
  ));

  $graphs['juniperive_users'] = TRUE;
}

// Meetings

$meetingusers = snmp_get($device, 'meetingUserCount.0', '-OQv', 'PULSESECURE-PSG-MIB');
$meetings     = snmp_get($device, 'meetingCount.0', '-OQv', 'PULSESECURE-PSG-MIB');

if (is_numeric($meetingusers) and is_numeric($meetings))
{
  rrdtool_update_ng($device, 'juniperive-meetings', array(
    'meetingusers' => $meetingusers,
    'meetings'     => $meetings,
  ));

  $graphs['juniperive_meetings'] = TRUE;
}

// Connections

$webusers  = snmp_get($device, 'signedInWebUsers.0', '-OQv', 'PULSESECURE-PSG-MIB');
$mailusers = snmp_get($device, 'signedInMailUsers.0', '-OQv', 'PULSESECURE-PSG-MIB');

if (!is_null($webusers) and !is_null($mailusers))
{
  rrdtool_update_ng($device, 'juniperive-connections', array(
    'webusers'  => $webusers,
    'mailusers' => $mailusers,
  ));

  $graphs['juniperive_connections'] = TRUE;
}

// Storage

$diskpercent = snmp_get($device, 'diskFullPercent.0', '-OQv', 'PULSESECURE-PSG-MIB');
$logpercent = snmp_get($device, 'logFullPercent.0', '-OQv', 'PULSESECURE-PSG-MIB');

if (!is_null($diskpercent) and !is_null($logpercent))
{
  rrdtool_update_ng($device, 'juniperive-storage', array(
    'diskpercent' => $diskpercent,
    'logpercent'  => $logpercent,
  ));

  $graphs['juniperive_storage'] = TRUE;
}

// EOF
