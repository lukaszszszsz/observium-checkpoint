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

/// Collect data about inputs :

$inputs      = snmpwalk_cache_twopart_oid($device, 'inputTable', array(), 'EATON-EPDU-MIB');
$inputs      = snmpwalk_cache_twopart_oid($device, 'inputTotal', $inputs, 'EATON-EPDU-MIB');
$inputs_o     = snmpwalk_cache_threepart_oid($device, 'inputVoltageTable', array(), 'EATON-EPDU-MIB');
$inputs_o     = snmpwalk_cache_threepart_oid($device, 'inputCurrentTable', $inputs_o, 'EATON-EPDU-MIB');
$inputs_o     = snmpwalk_cache_threepart_oid($device, 'inputPowerTable', $inputs_o, 'EATON-EPDU-MIB');

foreach ($inputs AS $unit_id => $unit_data)
{
  //echo "Unit $unit_id".PHP_EOL;

  foreach ($unit_data AS $input_id => $input_data)
  {
    //echo "  Input $input_id".PHP_EOL;

    $input_oid = $unit_id.".".$input_id;

   if (isset($input_data['inputFrequency']))
   {
      discover_sensor($valid['sensor'], 'frequency', $device, ".1.3.6.1.4.1.534.6.6.7.3.1.1.3.".$input_oid, "inputFrequency.$input_oid", 'eaton-epdu-mib', "Unit $unit_id Input $input_id Frequency", "0.1", $input_data['inputFrequency']);
   }

   if (isset($input_data['inputFrequencyStatus']))
   {
     discover_status($device, ".1.3.6.1.4.1.534.6.6.7.3.1.1.4.".$input_oid, "inputFrequencyStatus.".$input_oid, 'inputFrequencyStatus', "Unit $unit_id Input $input_id Frequency Status", $input_data['inputFrequencyStatus'], array('entPhysicalClass' => 'input'));
   }

   if (isset($input_data['inputTotalVA']) && is_numeric($input_data['inputTotalVA']))
   {
     $descr  = "Unit $unit_id Input $input_id";
     $oid    = ".1.3.6.1.4.1.534.6.6.7.3.5.1.3.".$input_oid;
     $value  = $input_data['inputTotalVA'];
     discover_sensor($valid['sensor'], 'apower', $device, $oid, "inputTotalVA.$input_oid", 'eaton-epdu-mib', $descr, 1, $value);
   }

   if (isset($input_data['inputTotalWatts']) && is_numeric($input_data['inputTotalWatts']))
   {
     $descr  = "Unit $unit_id Input $input_id";
     $oid    = ".1.3.6.1.4.1.534.6.6.7.3.5.1.4.".$input_oid;
     $value  = $input_data['inputTotalWatts'];
     discover_sensor($valid['sensor'], 'power', $device, $oid, "inputTotalWatts.$input_oid", 'eaton-epdu-mib', $descr, 1, $value);
   }

   if (isset($input_data['inputTotalPowerFactor']) && is_numeric($input_data['inputTotalPowerFactor']))
   {
     $descr  = "Unit $unit_id Input $input_id";
     $oid    = ".1.3.6.1.4.1.534.6.6.7.3.5.1.7.".$input_oid;
     $value  = $input_data['inputTotalPowerFactor'];
     discover_sensor($valid['sensor'], 'powerfactor', $device, $oid, "inputTotalPowerFactor.$input_oid", 'eaton-epdu-mib', $descr, 0.001, $value);
   }

   if (isset($input_data['inputTotalVAR']) && is_numeric($input_data['inputTotalVAR']))
   {
     $descr  = "Unit $unit_id Input $input_id";
     $oid    = ".1.3.6.1.4.1.534.6.6.7.3.5.1.8.".$input_oid;
     $value  = $input_data['inputTotalVAR'];
     discover_sensor($valid['sensor'], 'rpower', $device, $oid, "inputTotalVAR.$input_oid", 'eaton-epdu-mib', $descr, 1, $value);
   }

   if (is_array($inputs_o[$unit_id][$input_id])) {

      foreach ($inputs_o[$unit_id][$input_id] AS $id => $entry)
      {
        //print_r($entry);

        $entry_oid = $input_oid . "." . $id;

        if (isset($entry['inputVoltage']) && is_numeric($entry['inputVoltage']))
        {
          $descr  = "Unit $unit_id Input $input_id ".$entry['inputVoltageMeasType'];
          $oid    = ".1.3.6.1.4.1.534.6.6.7.3.2.1.3.".$entry_oid;
          $status_oid = ".1.3.6.1.4.1.534.6.6.7.3.2.1.4.".$entry_oid;
          $status_descr = $descr . " Voltage";
          $value  = $entry['inputVoltage'];
          $status_value  = $entry['inputVoltageThStatus'];
          $limits = array('limit_low' => $entry['inputVoltageThLowerCritical']*0.001, 'limit_low_warn' => $entry['inputVoltageThLowerWarning']*0.001,
                          'limit_high' => $entry['inputVoltageThUpperCritical']*0.001, 'limit_high_warn' => $entry['inputVoltageThUpperWarning']*0.001);

          discover_sensor($valid['sensor'], 'voltage', $device, $oid, "inputVoltage.$entry_oid", 'eaton-epdu-mib', $descr, 0.001, $value, $limits);
          discover_status($device, $status_oid, "inputVoltageThStatus.".$entry_oid, 'inputVoltageThStatus', $status_descr, $status_value, array('entPhysicalClass' => 'input'));
        }

        if (isset($entry['inputCurrent']) && is_numeric($entry['inputCurrent']))
        {
          $descr  = "Unit $unit_id Input $input_id ".$entry['inputCurrentMeasType'] . " (".$entry['inputCurrentCapacity']*0.001."A)";
          $oid    = ".1.3.6.1.4.1.534.6.6.7.3.3.1.4.".$entry_oid;
          $value  = $entry['inputCurrent'];
          $status_oid = ".1.3.6.1.4.1.534.6.6.7.3.3.1.5.".$entry_oid;
          $status_value  = $entry['inputCurrentThStatus'];
          $status_descr = $descr . " Current";

          $limits = array('limit_low' => $entry['inputCurrentThLowerCritical']*0.001, 'limit_low_warn' => $entry['inputCurrentThLowerWarning']*0.001,
                          'limit_high' => $entry['inputCurrentThUpperCritical']*0.001, 'limit_high_warn' => $entry['inputCurrentThUpperWarning']*0.001);

          discover_sensor($valid['sensor'], 'current', $device, $oid, "inputCurrent.$entry_oid", 'eaton-epdu-mib', $descr, 0.001, $value, $limits);
          discover_status($device, $status_oid, "inputCurrentThStatus.".$entry_oid, 'inputCurrentThStatus', $status_descr, $status_value, array('entPhysicalClass' => 'input'));

        }

        if (isset($entry['inputVA']) && is_numeric($entry['inputVA']))
        {
          $descr  = "Unit $unit_id Input $input_id ".$entry['inputPowerMeasType'];
          $oid    = ".1.3.6.1.4.1.534.6.6.7.3.4.1.3.".$entry_oid;
          $value  = $entry['inputVA'];
          discover_sensor($valid['sensor'], 'apower', $device, $oid, "inputVA.$entry_oid", 'eaton-epdu-mib', $descr, 1, $value);
        }

        if (isset($entry['inputWatts']) && is_numeric($entry['inputWatts']))
        {
          $descr  = "Unit $unit_id Input $input_id ".$entry['inputPowerMeasType'];
          $oid    = ".1.3.6.1.4.1.534.6.6.7.3.4.1.4.".$entry_oid;
          $value  = $entry['inputWatts'];
          discover_sensor($valid['sensor'], 'power', $device, $oid, "inputWatts.$entry_oid", 'eaton-epdu-mib', $descr, 1, $value);
        }

        if (isset($entry['inputPowerFactor']) && is_numeric($entry['inputPowerFactor']))
        {
          $descr  = "Unit $unit_id Input $input_id ".$entry['inputPowerMeasType'];
          $oid    = ".1.3.6.1.4.1.534.6.6.7.3.4.1.7.".$entry_oid;
          $value  = $entry['inputPowerFactor'];
          discover_sensor($valid['sensor'], 'powerfactor', $device, $oid, "inputPowerFactor.$entry_oid", 'eaton-epdu-mib', $descr, 0.001, $value);
        }

        if (isset($entry['inputVAR']) && is_numeric($entry['inputVAR']))
        {
          $descr  = "Unit $unit_id Input $input_id ".$entry['inputPowerMeasType'];
          $oid    = ".1.3.6.1.4.1.534.6.6.7.3.4.1.8.".$entry_oid;
          $value  = $entry['inputVAR'];
          discover_sensor($valid['sensor'], 'rpower', $device, $oid, "inputVAR.$entry_oid", 'eaton-epdu-mib', $descr, 1, $value);
        }

      }
    }
  }
}

// Collect data about outputs

$outlets      = snmpwalk_cache_twopart_oid($device, 'outletTable', array(), 'EATON-EPDU-MIB');
$outlets      = snmpwalk_cache_twopart_oid($device, 'outletCurrentTable', $outlets, 'EATON-EPDU-MIB');

// Power statistics currently not collected.
//$outlets      = snmpwalk_cache_twopart_oid($device, 'outletCurrentTable', $outlets, 'EATON-EPDU-MIB');

foreach ($outlets AS $unit_id => $unit_data)
{
  foreach ($unit_data AS $outlet_id => $outlet)
  {
    $outlet_index = $unit_id.".".$outlet_id;
    $outlet_descr = "Unit $unit_id ".$outlet['outletName'] . " (".$outlet['outletType'].")";
    $outlet_capacity = $outlet['outletCurrentCapacity'] * 0.001;

    $current_value = $outlet['outletCurrent'];
    $percent_value = $outlet['outletCurrentPercentLoad'];
    $status_value  = $outlet['outletCurrentThStatus'];
    $crest_value = $outlet['outletCurrentCrestFactor'];

    $current_oid = '.1.3.6.1.4.1.534.6.6.7.6.4.1.3.'.$outlet_index;
    $percent_oid = '.1.3.6.1.4.1.534.6.6.7.6.4.1.10.'.$outlet_index;
    $status_oid  = '.1.3.6.1.4.1.534.6.6.7.6.4.1.4.'.$outlet_index;
    $crest_oid = '.1.3.6.1.4.1.534.6.6.7.6.4.1.9.'.$outlet_index;

    discover_status($device, $status_oid, "outletCurrentThStatus.".$outlet_index, 'outletCurrentThStatus', $outlet_descr, $status_value, array('entPhysicalClass' => 'outlet'));

    $limits = array('limit_low' => $entry['outletCurrentThLowerCritical']*0.001, 'limit_low_warn' => $entry['outletCurrentThLowerWarning']*0.001,
                    'limit_high' => $entry['outletCurrentThUpperCritical']*0.001, 'limit_high_warn' => $entry['outletCurrentThUpperWarning']*0.001);

    discover_sensor($valid['sensor'], 'current', $device, $current_oid, "outletCurrent.$outlet_index", 'eaton-epdu-mib', $outlet_descr, 0.001, $current_value, $limits);
    discover_sensor($valid['sensor'], 'load', $device, $percent_oid, "outletCurrentPercentLoad.$outlet_index", 'eaton-epdu-mib', $outlet_descr, 1, $percent_value);
    discover_sensor($valid['sensor'], 'crestfactor', $device, $crest_oid, "outletCurrentCrestFactor.$outlet_index", 'eaton-epdu-mib', $outlet_descr, 1, $crest_value);
  }
}

//print_r($outlets);

/// EOF
