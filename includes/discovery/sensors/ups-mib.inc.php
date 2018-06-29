<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage discovery
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2016 Observium Limited
 *
 */

echo("Caching OIDs: ");
$ups_array = array();
echo("upsInput ");
$ups_array = snmpwalk_cache_multi_oid($device, "upsInput", $ups_array, "UPS-MIB");
echo("upsOutput ");
$ups_array = snmpwalk_cache_multi_oid($device, "upsOutput", $ups_array, "UPS-MIB");
echo("upsBypass ");
$ups_array = snmpwalk_cache_multi_oid($device, "upsBypass", $ups_array, "UPS-MIB");

$scale         = 0.1;
$scale_current = $scale;
if ($device['os'] == 'poweralert')
{
  // For poweralert use "incorrect" scale, see: http://jira.observium.org/browse/OBSERVIUM-1432
  // Fixed in firmware version 12.06.0068
  if (!empty($device['version']))
  {
    $tl_version = $device['version'];
  } else {
    $tl_version = snmp_get($device, '.1.3.6.1.4.1.850.10.1.2.3.0', '-Ovq', 'TRIPPLITE-12X');
  }
  if (!version_compare($tl_version, '12.06.0068', '>='))
  {
    // incorrect
    $scale_current = 1;
  }
}

print_debug_vars($ups_array);
$indexes = array_slice(array_keys($ups_array), 1);

// Check if total Input Current and Power more than 0
$ups_total = array('upsInputCurrent'   => 0,
                   'upsInputTruePower' => 0,
                   'upsBypassCurrent'  => 0,
                   'upsBypassPower'    => 0,
                  );
foreach ($indexes as $index)
{
  foreach ($ups_total as $oid => $v)
  {
    if (isset($ups_array[$index][$oid]))
    {
      $ups_total[$oid] += $ups_array[$index][$oid];
    }
  }
}

foreach ($indexes as $index)
{
  # Input
  $phase = $ups_array[$index]['upsInputLineIndex'];

  // Workaround if no upsInputLineIndex
  if ($phase == '')
  {
    // some devices have incorrect indexes (with additional .0), see:
    // http://jira.observium.org/browse/OBSERVIUM-2157
    list($phase) = explode('.', $index);
  }

  $descr = "Input"; if ($ups_array[0]['upsInputNumLines'] > 1) { $descr .= " Phase $phase"; }

  ## Input voltage
  # FIXME maybe use upsConfigLowVoltageTransferPoint and upsConfigHighVoltageTransferPoint as limits? (upsConfig table)
  if (isset($ups_array[$index]['upsInputVoltage']))
  {
    $oid   = ".1.3.6.1.2.1.33.1.3.3.1.3.$index"; # UPS-MIB:upsInputVoltage.$index
    discover_sensor($valid['sensor'], 'voltage', $device, $oid, "upsInputEntry.".$phase, 'ups-mib', $descr, 1, $ups_array[$index]['upsInputVoltage']);
  }

  ## Input frequency
  if (isset($ups_array[$index]['upsInputFrequency']))
  {
    $oid   = ".1.3.6.1.2.1.33.1.3.3.1.2.$index"; # UPS-MIB:upsInputFrequency.$index
    discover_sensor($valid['sensor'], 'frequency', $device, $oid, "upsInputEntry.".$phase, 'ups-mib', $descr, $scale, $ups_array[$index]['upsInputFrequency']);
  }

  ## Input current
  if (isset($ups_array[$index]['upsInputCurrent']) && $ups_total['upsInputCurrent'] > 0)
  {
    $oid   = ".1.3.6.1.2.1.33.1.3.3.1.4.$index"; # UPS-MIB:upsInputCurrent.$index
    discover_sensor($valid['sensor'], 'current', $device, $oid, "upsInputEntry.".$phase, 'ups-mib', $descr, $scale_current, $ups_array[$index]['upsInputCurrent']);
  }

  ## Input power
  if (isset($ups_array[$index]['upsInputTruePower']) && $ups_total['upsInputTruePower'] > 0)
  {
    $oid   = ".1.3.6.1.2.1.33.1.3.3.1.5.$index"; # UPS-MIB:upsInputTruePower.$index
    discover_sensor($valid['sensor'], 'power', $device, $oid, "upsInputEntry.".$phase, 'ups-mib', $descr, $scale, $ups_array[$index]['upsInputTruePower']);
  }

  # Output
  $phase = $ups_array[$index]['upsOutputLineIndex'];

  // Workaround if no upsOutputLineIndex
  if ($phase == '')
  {
    // some devices have incorrect indexes (with additional .0), see:
    // http://jira.observium.org/browse/OBSERVIUM-2157
    list($phase) = explode('.', $index);
  }

  $descr = "Output"; if ($ups_array[0]['upsOutputNumLines'] > 1) { $descr .= " Phase $phase"; }

  ## Output voltage
  if (isset($ups_array[$index]['upsOutputVoltage']))
  {
    $oid   = ".1.3.6.1.2.1.33.1.4.4.1.2.$index"; # UPS-MIB:upsOutputVoltage.$index
    discover_sensor($valid['sensor'], 'voltage', $device, $oid, "upsOutputEntry.".$phase, 'ups-mib', $descr, 1, $ups_array[$index]['upsOutputVoltage']);
  }

  ## Output current
  if (isset($ups_array[$index]['upsOutputCurrent']))
  {
    $oid   = ".1.3.6.1.2.1.33.1.4.4.1.3.$index"; # UPS-MIB:upsOutputCurrent.$index
    discover_sensor($valid['sensor'], 'current', $device, $oid, "upsOutputEntry.".$phase, 'ups-mib', $descr, $scale_current, $ups_array[$index]['upsOutputCurrent']);
  }

  ## Output power
  if (isset($ups_array[$index]['upsOutputPower']))
  {
    $oid   = ".1.3.6.1.2.1.33.1.4.4.1.4.$index"; # UPS-MIB:upsOutputPower.$index
    //discover_sensor($valid['sensor'], 'apower', $device, $oid, "upsOutputEntry.".$phase, 'ups-mib', $descr, 1, $ups_array[$index]['upsOutputPower']);
    discover_sensor($valid['sensor'], 'power', $device, $oid, "upsOutputEntry.".$phase, 'ups-mib', $descr, 1, $ups_array[$index]['upsOutputPower']);
  }

  if (isset($ups_array[$index]['upsOutputPercentLoad']))
  {
    $oid   = ".1.3.6.1.2.1.33.1.4.4.1.5.$index"; # UPS-MIB:upsOutputPercentLoad.$index
    rename_rrd($device, "sensor-capacity-ups-mib-upsOutputPercentLoad.${phase}", "sensor-load-ups-mib-upsOutputPercentLoad.${phase}");
    discover_sensor($valid['sensor'], 'load', $device, $oid, "upsOutputPercentLoad.$phase", 'ups-mib', $descr, 1, $ups_array[$index]['upsOutputPercentLoad']);
  }

  # Bypass

  if ($ups_array[0]['upsBypassNumLines'] > 0)
  {
    // some devices have incorrect indexes (with additional .0), see:
    // http://jira.observium.org/browse/OBSERVIUM-2157
    list($phase) = explode('.', $index);
    $descr = "Bypass"; if ($ups_array[0]['upsBypassNumLines'] > 1) { $descr .= " Phase $phase"; }

    ## Bypass voltage
    if (isset($ups_array[$index]['upsBypassVoltage']))
    {
      $oid   = ".1.3.6.1.2.1.33.1.5.3.1.2.$index"; # UPS-MIB:upsBypassVoltage.$index
      discover_sensor($valid['sensor'], 'voltage', $device, $oid, "upsBypassEntry.".$phase, 'ups-mib', $descr, 1, $ups_array[$index]['upsBypassVoltage']);
    }

    ## Bypass current
    if (isset($ups_array[$index]['upsBypassCurrent']) && $ups_total['upsBypassCurrent'] > 0)
    {
      $oid   = ".1.3.6.1.2.1.33.1.5.3.1.3.$index"; # UPS-MIB:upsBypassCurrent.$index
      discover_sensor($valid['sensor'], 'current', $device, $oid, "upsBypassEntry.".$phase, 'ups-mib', $descr, $scale_current, $ups_array[$index]['upsBypassCurrent']);
    }

    ## Bypass power
    if (isset($ups_array[$index]['upsBypassPower']) && $ups_total['upsBypassPower'] > 0)
    {
      $oid   = ".1.3.6.1.2.1.33.1.5.3.1.4.$index"; # UPS-MIB:upsBypassPower.$index
      discover_sensor($valid['sensor'], 'power', $device, $oid, "upsBypassEntry.".$phase, 'ups-mib', $descr, 1, $ups_array[$index]['upsBypassPower']);
    }
  }
}

if (isset($ups_array[0]['upsOutputSource']))
{
  $descr = "Source of Output Power";
  $oid   = ".1.3.6.1.2.1.33.1.4.1.0";
  $value  = $ups_array[0]['upsOutputSource'];

  discover_status($device, $oid, "upsOutputSource.0", 'ups-mib-output-state', $descr, $value, array('entPhysicalClass' => 'other'));
}

$ups_array = snmpwalk_cache_multi_oid($device, "upsBattery", array(), "UPS-MIB");

if (isset($ups_array[0]['upsBatteryTemperature']) && $ups_array[0]['upsBatteryTemperature'] != 0) // Battery won't be freezing, 0 means no sensor.
{
  $oid = ".1.3.6.1.2.1.33.1.2.7.0"; # UPS-MIB:upsBatteryTemperature.0

  discover_sensor($valid['sensor'], 'temperature', $device, $oid, "upsBatteryTemperature", 'ups-mib', "Battery", 1, $ups_array[0]['upsBatteryTemperature']);
}

if (isset($ups_array[0]['upsBatteryCurrent']) && $ups_array[0]['upsBatteryCurrent'] > 0)
{
  $oid = ".1.3.6.1.2.1.33.1.2.6.0"; # UPS-MIB:upsBatteryCurrent.0

  discover_sensor($valid['sensor'], 'current', $device, $oid, "upsBatteryCurrent", 'ups-mib', "Battery", $scale_current, $ups_array[0]['upsBatteryCurrent']);
}

if (isset($ups_array[0]['upsBatteryVoltage']))
{
  $oid = ".1.3.6.1.2.1.33.1.2.5.0"; # UPS-MIB:upsBatteryVoltage.0

  discover_sensor($valid['sensor'], 'voltage', $device, $oid, "upsBatteryVoltage", 'ups-mib', "Battery", $scale, $ups_array[0]['upsBatteryVoltage']);
}

if (isset($ups_array[0]['upsBatteryStatus']))
{
  $descr = "Battery Status";
  $oid   = ".1.3.6.1.2.1.33.1.2.1.0";
  $value  = $ups_array[0]['upsBatteryStatus'];

  discover_status($device, $oid, "upsBatteryStatus.0", 'ups-mib-battery-state', $descr, $value, array('entPhysicalClass' => 'other'));
}

if (isset($ups_array[0]['upsEstimatedMinutesRemaining']))
{
  $descr  = "Battery Runtime Remaining";
  $oid    = ".1.3.6.1.2.1.33.1.2.3.0";
  $limits = array('limit_low' => snmp_get($device, "upsConfigLowBattTime.0", "-OUqv", "UPS-MIB"));
  $value  = $ups_array[0]['upsEstimatedMinutesRemaining'];

  // FIXME, why mge? seems as copy-paste
  discover_sensor($valid['sensor'], 'runtime', $device, $oid, "upsEstimatedMinutesRemaining.0", 'mge', $descr, 1, $value, $limits);
}

if (isset($ups_array[0]['upsEstimatedChargeRemaining']))
{
  $descr = "Battery Charge Remaining";
  $oid   = ".1.3.6.1.2.1.33.1.2.4.0";
  $value  = $ups_array[0]['upsEstimatedChargeRemaining'];

  discover_sensor($valid['sensor'], 'capacity', $device, $oid, "upsEstimatedChargeRemaining.0", 'ups-mib', $descr, 1, $value);
}

## Output Frequency
$oid   = ".1.3.6.1.2.1.33.1.4.2.0"; # UPS-MIB:upsOutputFrequency.0
$value = snmp_get($device, $oid, "-OUqv");
if (is_numeric($value))
{
  discover_sensor($valid['sensor'], 'frequency', $device, $oid, "upsOutputFrequency", 'ups-mib', "Output", $scale, $value);
}

## Bypass Frequency
$oid   = ".1.3.6.1.2.1.33.1.5.1.0"; # UPS-MIB:upsBypassFrequency.0
$value = snmp_get($device, $oid, "-OUqv");
if (is_numeric($value))
{
  discover_sensor($valid['sensor'], 'frequency', $device, $oid, "upsBypassFrequency", 'ups-mib', "Bypass", $scale, $value);
}

//UPS-MIB::upsTestId.0 = OID: UPS-MIB::upsTestNoTestsInitiated
//UPS-MIB::upsTestSpinLock.0 = INTEGER: 1
//UPS-MIB::upsTestResultsSummary.0 = INTEGER: noTestsInitiated(6)
//UPS-MIB::upsTestResultsDetail.0 = STRING: No test initiated.
//UPS-MIB::upsTestStartTime.0 = Timeticks: (0) 0:00:00.00
//UPS-MIB::upsTestElapsedTime.0 = INTEGER: 0
$ups_array = snmpwalk_cache_multi_oid($device, "upsTest", array(), "UPS-MIB");
if (isset($ups_array[0]['upsTestResultsSummary']) && $ups_array[0]['upsTestResultsSummary'] != 'noTestsInitiated')
{
  $descr = "Diagnostics Results";
  $oid   = ".1.3.6.1.2.1.33.1.7.3.0";
  $value  = $ups_array[0]['upsTestResultsSummary'];
  $test_starttime = timeticks_to_sec($ups_array[0]['upsTestStartTime']);
  if ($test_starttime)
  {
    $test_sysUpime = timeticks_to_sec(snmp_get($device, "sysUpTime.0", "-OQUs", "SNMPv2-MIB"));
    if ($test_sysUpime)
    {
      $test_starttime = time() + $test_starttime - $test_sysUpime; // Unixtime of start test
      $descr .= ' (last ' . format_unixtime($test_starttime) . ')';
    }
  }

  discover_status($device, $oid, "upsTestResultsSummary.0", 'ups-mib-test-state', $descr, $value, array('entPhysicalClass' => 'other'));
}

unset($ups_array, $ups_total, $indexes, $index, $oid);

// EOF
