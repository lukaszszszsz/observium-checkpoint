<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 * @package    observium
 * @subpackage snmp
 * @author     Adam Armstrong <adama@observium.org>
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2016 Observium Limited
 *
 */

## If anybody has again the idea to implement the PHP internal library calls,
## be aware that it was tried and banned by lead dev Adam
##
## TRUE STORY. THAT SHIT IS WHACK. -- adama.

//CLEANME:
// snmpwalk_cache_oid_num()   - (deprecated) not used anymore
// snmpget_entity_oids()      - (deprecated) not used anymore
// snmp_cache_slotport_oid()  - (deprecated) not used anymore
// snmp_walk_parser()         - (deprecated) used in poller module netscaler-vsvr, need rewrite
// snmp_walk_parser2()        - (deprecated) not used anymore
// parse_oid()                - (deprecated) not used anymore, use snmp_parse_line()
// parse_oid2()               - (deprecated) used in poller/discovery module mac-accounting, need rewrite

// `egrep -r 'snmpwalk_cache_oid *\( *\$' .       | grep -v snmp.inc.php | wc -l` => 519
// `egrep -r 'snmpwalk_cache_multi_oid *\( *\$' . | grep -v snmp.inc.php | wc -l` => 387
// snmpwalk_cache_multi_oid() - (duplicate) call to snmpwalk_cache_oid()

/**
 * MIB dirs generate functions
 */

/**
 * Generates a list of mibdirs in the correct format for net-snmp
 *
 * @return string
 * @global config
 * @param $mib1, $mib2, ...
 */
function mib_dirs()
{
  global $config;

  $dirs = array($config['mib_dir'].'/rfc', $config['mib_dir'].'/net-snmp');

  foreach (func_get_args() as $mibs)
  {
    if (!is_array($mibs)) { $mibs = array($mibs); }

    foreach ($mibs as $mib)
    {
      if (ctype_alnum(str_replace(array('-', '_'), '', $mib)))
      {
        // If mib name equals 'mibs' just add root mib_dir to list
        $dirs[] = ($mib == 'mibs' ? $config['mib_dir'] : $config['mib_dir'].'/'.$mib);
      }
    }
  }

  return implode(':', array_unique($dirs));
}


/**
 * Finds directories for requested MIBs as defined by the MIB definitions.
 *
 * @param string $mib One or more MIBs (separated by ':') to return the MIB dir for
 *
 * @return string Directories for requested MIBs, separated by ':' (for net-snmp)
 */
// TESTME needs unit testing
function snmp_mib2mibdirs($mib)
{
  global $config;

  $def_mibdirs = array();

  // As we accept multiple MIBs separated by :, process them all for definition entries
  foreach (explode(':', $mib) as $xmib)
  {
    if (!empty($config['mibs'][$xmib]['mib_dir'])) // Array or non-empty string
    {
      // Add definition based MIB dir. Don't worry about deduplication, mib_dirs() sorts that out for us
      $def_mibdirs = array_merge($def_mibdirs, (array)$config['mibs'][$xmib]['mib_dir']);
    }
  }

  if (count($def_mibdirs))
  {
    // Use MIB dirs found via foreach above
    return mib_dirs($def_mibdirs);
  } else {
    // No specific MIB dirs found, set default Observium MIB dir
    return $config['mib_dir'];
  }
}

/**
 * Convert/parse/validate oids & values
 */

/**
 * De-wrap 32bit counters
 * Crappy function to get workaround 32bit counter wrapping in HOST-RESOURCES-MIB
 * See: http://blog.logicmonitor.com/2011/06/11/linux-monitoring-net-snmp-and-terabyte-file-systems/
 *
 * @param integer $value
 * @return integer
 */
function snmp_dewrap32bit($value)
{
  if (is_numeric($value) && $value < 0)
  {
    return ($value + 4294967296);
  } else {
    return $value;
  }
}

/**
 * Combinate High and Low sizes into full 64bit size
 * Used in UCD-SNMP-MIB and NIMBLE-MIB
 * Note, this function required 64bit system!
 *
 * @param integer $high High bits value
 * @param integer $low  Low bits value
 * @return integer Result summ 64bit
 */
function snmp_size64_high_low($high, $low)
{
  return $high * 4294967296 + $low;
  //return $high << 32 + $low;
}

/**
 * Clean returned numeric data from snmp output
 * Supports only non-scientific numbers
 * Examples: "  20,4" -> 20.4
 *
 * @param string $value
 * @return mixed $numeric
 */
function snmp_fix_numeric($value)
{
  if (is_numeric($value)) { return $value + 0; } // If already numeric just return value

  $numeric = trim($value, " \t\n\r\0\x0B\"");
  list($numeric) = explode(' ', $numeric);
  $numeric = preg_replace('/[^0-9a-z\-,\.]/i', '', $numeric);
  // Some retarded devices report data with spaces and commas: STRING: "  20,4"
  $numeric = str_replace(',', '.', $numeric);
  if (is_numeric($numeric))
  {
    // If cleaned data is numeric return number
    return $numeric + 0;
  }
  else if (preg_match('/^(\d+(?:\.\d+)?)[a-z]+$/i', $numeric, $matches))
  {
    // Number with unit, ie "8232W"
    return $matches[1] + 0;
  } else {
    // Else return original value
    return $value;
  }
}

/**
 * Fixed ascii coded chars in snmp string as correct UTF-8 chars.
 * Convert all Mac/Windows newline chars (\r\n, \r) to Unix char (\n)
 *
 * NOTE, currently support only one-byte unicode
 *
 * Examples: "This is a &#269;&#x5d0; test&#39; &#250;" -> "This is a čא test' ú"
 *           "P<FA>lt stj<F3>rnst<F6><F0>"              -> "Púlt stjórnstöð"
 *
 * @param string $string
 * @return string $string
 */
function snmp_fix_string($string)
{
  if (!preg_match('/^[[:print:]\p{L}]*$/mu', $string))
  {
    // find unprintable and all unicode chars, because old pcre library not always detect orb
    $debug_msg = '>>> Founded unprintable chars in string:' . PHP_EOL . $string;
    $string = preg_replace_callback('/[^[:print:]\x00-\x1F\x80-\x9F]/m', 'convert_ord_char', $string);
    print_debug($debug_msg . PHP_EOL . '>>> Converted to:' . PHP_EOL . $string . PHP_EOL);
  }

  // Convert all Mac/Windows newline chars (\r\n, \r) to Unix char (\n)
  $string = nl2nl($string);

  return $string;
}

/**
 * Convert an SNMP hex string to regular string
 *
 * @param string $string
 * @return string
 */
function snmp_hexstring($string)
{
  if (isHexString($string))
  {
    return hex2str(str_replace(' 00', '', $string)); // 00 is EOL
  } else {
    return $string;
  }
}

/**
 * Clean SNMP value, ie: trim quotes, spaces, remove "wrong type", fix incorrect UTF8 strings, etc
 * @param	string	$value	Value
 * @param	integer	$flags	OBS_SNMP_* flags
 * @return	string			Cleaned value
 */
function snmp_value_clean($value, $flags = OBS_SNMP_ALL)
{
  // For null just return NULL
  if (NULL === $value)
  {
    return $value;
  }

  // Clean quotes and trim
  $value = trim_quotes($value, $flags);

  // Remove Wrong Type string
  if (strpos($value, 'Wrong Type') === 0)
  {
    $value = preg_replace('/Wrong Type .*?: (.*)/s', '\1', $value);
  }

  // Fix incorrect UTF8 strings
  if (is_flag_set(OBS_DECODE_UTF8, $flags))
  {
    $value = snmp_fix_string($value);
  }

  /* Need use case
  // Convert incorrect HEX strings back to string
  if (!is_flag_set(OBS_SNMP_HEX, $flags))
  {
    $value = snmp_hexstring($value);
  }
  */

  return $value;
}

/**
 * Convert an SNMP index string (with len!) to regular string
 * Opposite function for snmp_string_to_oid()
 * Example:
 *  9.79.98.115.101.114.118.105.117.109 -> Observium
 *
 * @param string $index
 * @return string
 */
function snmp_oid_to_string($index)
{
  $index = (string)$index;
  if ($index === '0') { return ''; } // This is just empty string!

  if (preg_match('/^\.?(\d+(?:\.\d+)+)$/', $index, $matches))
  {
    $str_parts = explode('.', $matches[1]);
    $str_len   = array_shift($str_parts);
    if ($str_len != count($str_parts))
    {
      // break, incorrect index string (str len not match)
      return $index;
    }
    $string = '';
    foreach ($str_parts as $char)
    {
      if ($char > 255)
      {
        // break, incorrect index string
        return $index;
      }
      $string .= zeropad(dechex($char));
    }
    return hex2str($string);
  }

  return $index;
}

/**
 * Convert ASCII string to an SNMP index (with len!)
 * Opposite function for snmp_oid_to_string()
 * Example:
 *  Observium -> 9.79.98.115.101.114.118.105.117.109
 *
 * @param string $string
 * @return string
 */
function snmp_string_to_oid($string)
{
  // uses the first octet of index as length
  $index = strlen((string)$string);
  if ($index === 0)
  {
    // Empty string
    return (string)$index;
  }

  // converts the index as string to decimal ascii codes
  foreach (str_split($string) as $char)
  {
    $index .= '.' . ord($char);
  }

  return $index;
}

/**
 * Check if returned snmp value is valid
 *
 * @param string $value
 * @return bool
 */
function is_valid_snmp_value($value)
{
  $valid = strpos($value, 'at this OID') === FALSE &&
           strpos($value, 'No more variables left') === FALSE &&
           $value != 'NULL' && $value != 'null' && $value !== NULL;

  return $valid;
}

/**
 * Parse each line in output from snmpwalk into:
 *   oid (raw), oid_name, index, index_parts, index_count, value
 *
 * This parser always used snmpwalk with base options: -OQUs
 * and allowed to use additional options: bnexX
 *
 * Value always cleaned from unnecessary data by snmp_value_clean()
 *
 * @param string $line   snmpwalk output line
 * @param integer $flags Common snmp flags
 * @return array Array with parsed values
 */
function snmp_parse_line($line, $flags = OBS_SNMP_ALL)
{
  /**
   * Note, this is parse line only for -OQUs (and additionally: bnexX)
   *  Q - Removes the type information when displaying varbind values: SNMPv2-MIB::sysUpTime.0 = 1:15:09:27.63
   *  U - Do not print the UNITS suffix at the end of the value
   *  s - Do not display the name of the MIB
   *  b - Display table indexes numerically: SNMP-VIEW-BASED-ACM-MIB::vacmSecurityModel.0.3.119.101.115 = xxx
   *  n - Displays the OID numerically: .1.3.6.1.2.1.1.3.0 = Timeticks: (14096763) 1 day, 15:09:27.63
   *  e - Removes the symbolic labels from enumeration values: forwarding(1) -> 1
   *  x - Force display string values as Hex strings
   *  X - Display table indexes in a more "program like" output: IPv6-MIB::ipv6RouteIfIndex[3ffe:100:ff00:0:0:0:0:0][64][1] = 2
   */

  list($r_oid, $value) = explode('=', $line, 2);
  $r_oid = trim($r_oid);
  $value = snmp_value_clean($value, $flags);

  $array = array('oid'   => $r_oid,
                 'value' => $value);

  if (is_flag_set(OBS_SNMP_NUMERIC, $flags))
  {
    // For numeric, just return raw oid and value
    // Example: .1.3.6.1.2.1.1.3.0 = 15:09:27.63
    if (isset($r_oid[0]))
    {
      // I think not possible, but I leave this here, just in case --mike
      //if ($r_oid[0] !== '.')
      //{
      //  $array['index'] = '.' . $array['index'];
      //}
      $array['index_count'] = 1;
    } else {
      $array['index_count'] = 0;
    }
    $array['index']       = $r_oid;
    return $array;
  }

  if ($is_table = is_flag_set(OBS_SNMP_TABLE, $flags))
  {
    // For table use another parse rules
    // Example: ipv6RouteIfIndex[3ffe:100:ff00:0:0:0:0:0][64][1]
    $oid_parts = array();
    foreach (explode('[', $r_oid) as $oid_part)
    {
      $oid_parts[] = rtrim($oid_part, ']');
    }
  }
  else if (strpos($r_oid, '"') !== FALSE)
  {
    // Example: jnxVpnPwLocalSiteId.l2Circuit."ge-0/1/1.0".621
    //$oid_part = stripslashes($r_oid);
    $oid_part = $r_oid;
    $oid_parts = array();
    do
    {
      if (preg_match('/^"([^"]*)"(?:\.(.+))?/', $oid_part, $matches))
      {
        // Part with stripes
        $oid_parts[] = $matches[1];
        $oid_part    = $matches[2]; // Next part
      } else {
        $matches = explode('.', $oid_part, 2);
        $oid_parts[] = $matches[0];
        $oid_part    = $matches[1]; // Next part
      }
      // print_vars($matches);
    } while (strlen($oid_part) > 0);
    // print_vars($oid_parts);
  } else {
    // Simple, not always correct
    // Example: vacmSecurityModel.0.3.119.101.115
    $oid_parts = explode('.', $r_oid);
  }
  $array['oid_name']    = array_shift($oid_parts);
  //$array['oid_name']    = end(explode('::', $array['oid_name'], 2)); // We use -Os
  $array['index_parts'] = $oid_parts;
  $array['index_count'] = count($oid_parts);
  $array['index']       = implode('.', $oid_parts);
  //var_dump($array);
  return $array;
}

// Translate OID string to numeric:
//'BGP4-V2-MIB-JUNIPER::jnxBgpM2PeerRemoteAs' -> '.1.3.6.1.4.1.2636.5.1.1.2.1.1.1.13'
// or numeric OID to string:
// '.1.3.6.1.4.1.9.1.685' -> 'ciscoAIRAP1240'
// DOCME needs phpdoc block
// TESTME needs unit testing
function snmp_translate($oid, $mib = NULL, $mibdir = NULL)
{
  // $rewrite_oids set in rewrites.inc.php
  global $rewrite_oids, $config;

  if (is_numeric(str_replace('.', '', $oid)))
  {
    $options = '-Os';
  }
  else if ($mib)
  {
    // If $mib::$oid known in $rewrite_oids use this value instead shell command snmptranslate.
    if (isset($rewrite_oids[$mib][$oid]))
    {
      print_debug("SNMP TRANSLATE (REWRITE): '$mib::$oid' -> '".$rewrite_oids[$mib][$oid]."'");
      return $rewrite_oids[$mib][$oid];
    }
    $oid = $mib . '::' . $oid;
  }

  $cmd  = $config['snmptranslate'];
  if ($options) { $cmd .= ' ' . $options; } else { $cmd .= ' -On'; }
  if ($mib) { $cmd .= ' -m ' . $mib; }

  // Set correct MIB directories based on passed dirs and OS definition
  // If $mibdir variable is passed to the function, we use it directly
  if ($mibdir)
  {
    $cmd .= " -M $mibdir";
  } else {
    $cmd .= ' -M ' . snmp_mib2mibdirs($mib);
  }

  $cmd .= ' \'' . $oid . '\'';
  if (!OBS_DEBUG) { $cmd .= ' 2>/dev/null'; }

  $data = trim(external_exec($cmd));

  $GLOBALS['snmp_stats']['snmptranslate']['count']++;
  $GLOBALS['snmp_stats']['snmptranslate']['time'] += $GLOBALS['exec_status']['runtime'];


  if ($data && !strstr($data, 'Unknown'))
  {
    print_debug("SNMP TRANSLATE (CMD): '$oid' -> '".$data."'");
    return $data;
  } else {
    return '';
  }
}


/**
 * Common SNMP functions for generate cmd and log errors
 */

/**
 * Build a commandline string for net-snmp commands.
 *
 * @param  string $command
 * @param  array  $device
 * @param  string $oids
 * @param  string $options
 * @param  string $mib
 * @param  string $mibdir Optional, correct path should be set in the MIB definition
 * @global config
 * @global debug
 * @return string
 */
// TESTME needs unit testing
function snmp_command($command, $device, $oids, $options, $mib = NULL, &$mibdir = NULL, $flags = OBS_SNMP_ALL)
{
  global $config;

  // This is compatibility code after refactor in r6306, for keep devices up before DB updated
  if (isset($device['snmpver']) && !isset($device['snmp_version'])) // get_db_version() < 189)
  {
    // FIXME. Remove this in r7000
    $device['snmp_version'] = $device['snmpver'];
    foreach (array('transport', 'port', 'timeout', 'retries', 'community',
                   'authlevel', 'authname', 'authpass', 'authalgo', 'cryptopass', 'cryptoalgo') as $old_key)
    {
      // Convert to new device snmp keys
      $device['snmp_'.$old_key] = $device[$old_key];
    }
  }

  $nobulk = $device['snmp_version'] == 'v1' || (isset($device['snmp_nobulk']) && $device['snmp_nobulk']) ||
            (isset($config['os'][$device['os']]['snmp']['nobulk']) && $config['os'][$device['os']]['snmp']['nobulk']);

  // Get the full command path from the config. Chose between bulkwalk and walk. Add max-reps if needed.
  switch($command)
  {
    case 'snmpwalk':
      if ($nobulk)
      {
        $cmd = $config['snmpwalk'];
      } else {
        $cmd = $config['snmpbulkwalk'];
        if (is_numeric($device['snmp_maxrep']))
        {
          $cmd .= ' -Cr'.$device['snmp_maxrep'];
        }
        elseif ($config['snmp']['max-rep'] && is_numeric($config['os'][$device['os']]['snmp']['max-rep']))
        {
          $cmd .= ' -Cr'.$config['os'][$device['os']]['snmp']['max-rep'];
        }
      }
      if (isset($config['os'][$device['os']]['snmp']['noincrease']) && $config['os'][$device['os']]['snmp']['noincrease'])
      {
        // do not check returned OIDs are increasing
        $cmd .= ' -Cc';
      }
      break;
    case 'snmpget':
      $cmd = $config[$command];
      break;
    case 'snmpbulkget':
      if ($nobulk)
      {
        $cmd = $config['snmpget'];
      } else {
        $cmd = $config['snmpbulkget'];
        if (is_numeric($device['snmp_maxrep']))
        {
          $cmd .= ' -Cr'.$device['snmp_maxrep'];
        }
        elseif ($config['snmp']['max-rep'] && is_numeric($config['os'][$device['os']]['snmp']['max-rep']))
        {
          $cmd .= ' -Cr'.$config['os'][$device['os']]['snmp']['max-rep'];
        }
      }
      break;
    default:
      print_error("Unknown command $command passed to snmp_command(). THIS SHOULD NOT HAPPEN. PLEASE REPORT TO DEVELOPERS.");
      return FALSE;
  }

  // Set timeout values if set in the database, otherwise set to configured defaults
  if (is_numeric($device['snmp_timeout']) && $device['snmp_timeout'] > 0)
  {
    $snmp_timeout = $device['snmp_timeout'];
  } else if (isset($config['snmp']['timeout'])) {
    $snmp_timeout = $config['snmp']['timeout'];
  }

  if (isset($snmp_timeout)) { $cmd .= ' -t ' . escapeshellarg($snmp_timeout); }

  // Set retries if set in the database, otherwise set to configured defaults
  if (is_numeric($device['snmp_retries']) && $device['snmp_retries'] >= 0)
  {
    $snmp_retries = $device['snmp_retries'];
  }
  else if (isset($config['snmp']['retries']))
  {
    $snmp_retries = $config['snmp']['retries'];
  }
  if (isset($snmp_retries)) { $cmd .= ' -r ' . escapeshellarg($snmp_retries); }

  // If no specific transport is set for the device, default to UDP.
  if (empty($device['snmp_transport'])) { $device['snmp_transport'] = 'udp'; }

  // If no specific port is set for the device, default to 161.
  if (!$device['snmp_port']) { $device['snmp_port'] = 161; }

  // Add the SNMP authentication settings for the device
  $cmd .= snmp_gen_auth($device);

  // Hardcode ignoring underscore parsing errors because net-snmp is dumb as a bag of rocks
  $cmd .= ' -Pu';

  // Disables the use of DISPLAY-HINT information when assigning values.
  if (is_flag_set(OBS_SNMP_HEX, $flags)) { $cmd .= ' -Ih'; }

  if ($options) { $cmd .= ' ' . $options; }
  if ($mib) { $cmd .= ' -m ' . $mib; }

  // Set correct MIB directories based on passed dirs and OS definition
  // If $mibdir variable is passed, we use it directly
  if (empty($mibdir))
  {
    // Change to correct mibdir, required for store in snmp_errors
    $mibdir = snmp_mib2mibdirs($mib);
  }
  $cmd .= " -M $mibdir";

  // Add the device URI to the string
  $cmd .= ' ' . escapeshellarg($device['snmp_transport']).':'.escapeshellarg($device['hostname']).':'.escapeshellarg($device['snmp_port']);

  // Add the OID(s) to the string
  $oids = trim($oids);
  if ($oids === '')
  {
    print_error("Empty oids passed to snmp_command(). THIS SHOULD NOT HAPPEN. PLEASE REPORT TO DEVELOPERS.");
    $GLOBALS['snmp_command'] = $cmd;
    return FALSE;
  } else {
    $cmd .= ' ' . addslashes($oids); // Quote slashes for string indexes
    $GLOBALS['snmp_command'] = $cmd;
  }

  // If we're not debugging, direct errors to /dev/null.
  if (!OBS_DEBUG) { $cmd .= ' 2>/dev/null'; }

  return $cmd;
}

/**
 * Build authentication for net-snmp commands using device array
 *
 * @return array
 * @param array $device
 */
// TESTME needs unit testing
function snmp_gen_auth(&$device)
{
  $cmd = '';
  $vlan = FALSE;

  if (isset($device['snmp_context']))
  {
    if (is_numeric($device['snmp_context']) && $device['snmp_context'] > 0 && $device['snmp_context'] < 4096 )
    {
      $vlan = $device['snmp_context'];
    }
  }

  switch ($device['snmp_version'])
  {
    case 'v3':
      $cmd = ' -v3 -l ' . escapeshellarg($device['snmp_authlevel']);
      /* NOTE.
       * For proper work of 'vlan-' context on cisco, it is necessary to add 'match prefix' in snmp-server config --mike
       * example: snmp-server group MONITOR v3 auth match prefix access SNMP-MONITOR
       */
      $cmd .= ($vlan) ? ' -n "vlan-' . $vlan . '"' : ' -n ""'; // Some devices, like HP, always require option '-n'

      switch ($device['snmp_authlevel'])
      {
        case 'authPriv':
          $cmd .= ' -x ' . escapeshellarg($device['snmp_cryptoalgo']);
          $cmd .= ' -X ' . escapeshellarg($device['snmp_cryptopass']);
          // no break here
        case 'authNoPriv':
          $cmd .= ' -a ' . escapeshellarg($device['snmp_authalgo']);
          $cmd .= ' -A ' . escapeshellarg($device['snmp_authpass']);
          $cmd .= ' -u ' . escapeshellarg($device['snmp_authname']);
          break;
        case 'noAuthNoPriv':
          // We have to provide a username anyway (see Net-SNMP doc)
          $cmd .= ' -u observium';
          break;
        default:
          print_error('ERROR: Unsupported SNMPv3 snmp_authlevel (' . $device['snmp_authlevel'] . ')');
      }
      break;

    case 'v2c':
    case 'v1':
      $cmd  = ' -' . $device['snmp_version'];
      $cmd .= ' -c ' . escapeshellarg($device['snmp_community']);
      if ($vlan) { $cmd .= '@' . $vlan; }
      break;
    default:
      print_error('ERROR: ' . $device['snmp_version'] . ' : Unsupported SNMP Version.');
  }

  if (OBS_DEBUG === 1 && !$GLOBALS['config']['snmp']['hide_auth'])
  {
    $debug_auth = "DEBUG: SNMP Auth options = $cmd";
    print_debug($debug_auth);
  }

  return $cmd;
}

/**
 * Detect SNMP errors and log it in DB.
 * Error logged in poller modules only, all other just return error code
 *
 * @param string  $command  Used snmp command (ie: snmpget, snmpwalk)
 * @param array   $device   Device array (device_id not allowed)
 * @param string  $oid      SNMP oid string
 * @param string  $options  SNMP options
 * @param string  $mib      SNMP MIBs list
 * @param string  $mibdir   SNMP MIB dirs list
 * @return int              Numeric error code. Full list error codes see in definitions: $config['snmp']['errorcodes']
 */
function snmp_log_errors($command, $device, $oid, $options, $mib, $mibdir)
{
  $error_timestamp = time(); // current timestamp
  $error_codes = $GLOBALS['config']['snmp']['errorcodes'];
  $error_code = 0; // By default - OK

  if ($GLOBALS['snmp_status'] === FALSE)
  {
    $error_code = 999; // Default Unknown error
    if (is_array($oid))
    {
      $oid = implode(' ', $oid);
    }
    if ($mib == 'SNMPv2-MIB')
    {
      // Pre-check for net-snmp errors
      if ($GLOBALS['exec_status']['exitcode'] === 1)
      {
        if      (strpos($GLOBALS['exec_status']['stderr'], '.index are too large') !== FALSE)
        {
          $error_code = 997;
        }
        else if (preg_match('/(?:Cannot find module|Unknown Object Identifier)/', $GLOBALS['exec_status']['stderr'])) { $error_code = 996; }
      }
      if ($error_code === 999)
      {
        if ($oid == 'sysObjectID.0 sysUpTime.0') { $error_code = 900; } // this is isSNMPable test, ignore
        else if (isset($GLOBALS['config']['os'][$device['os']]['snmpable']) &&
                 in_array($oid, isset($GLOBALS['config']['os'][$device['os']]['snmpable'])))
        {
          $error_code = 900; // This is also isSNMPable, ignore
        }
      }
    }

    if ($error_code === 999 && strlen(trim($GLOBALS['exec_status']['stdout'])) === 0)
    {
      $error_code = 1;  // Empty output non critical
      if ($GLOBALS['exec_status']['exitcode'] === 1 || $GLOBALS['exec_status']['exitcode'] === -1)
      {
        $error_code = 1002;
        if (strpos($GLOBALS['exec_status']['stderr'], '.index are too large') !== FALSE)      { $error_code = 997; }
        else if (strpos($GLOBALS['exec_status']['stderr'], 'Cannot find module') !== FALSE ||
            strpos($GLOBALS['exec_status']['stderr'], 'Unknown Object Identifier') !== FALSE) { $error_code = 996; }
        else if (strpos($GLOBALS['exec_status']['stderr'], 'Empty command passed') !== FALSE) { $error_code = 995; }
      }
      else if ($GLOBALS['exec_status']['exitcode'] === 2)
      {
        // Reason: (noSuchName) There is no such variable name in this MIB.
        // This is incorrect snmp version used for MIB/oid (mostly snmp v1)
        $error_code = 1000;
      }
    }
    else if ($error_code === 999)
    {
      if ($GLOBALS['exec_status']['exitcode'] === 2 && strpos($GLOBALS['exec_status']['stderr'], 'Response message would have been too large') !== FALSE)
      {
        // "Reason: (tooBig) Response message would have been too large."
        // Too big max-rep definition used,
        // this is not exactly device or net-snmp error, just need to set less max-rep in os definition
        $error_code = 4;
      }
      // Non empty output, some errors can ignored
      else if (preg_match('/(?:No Such Instance|No Such Object|There is no such variable|No more variables left|Wrong Type)/i', $GLOBALS['exec_status']['stdout']) ||
          $GLOBALS['exec_status']['stdout'] == 'NULL')
      {
        $error_code = 1000;
      }
      else if (stripos($GLOBALS['exec_status']['stdout'], 'Authentication failure') !== FALSE)
      {
        $error_code = 1001;
      }
      else if ($GLOBALS['exec_status']['exitcode'] === 2 || stripos($GLOBALS['exec_status']['stderr'], 'Timeout') !== FALSE)
      {
        // non critical
        $error_code = 2;
      }
      else if ($GLOBALS['exec_status']['exitcode'] === 1)
      {
        // Calculate current snmp timeout
        if (is_numeric($device['snmp_timeout']) && $device['snmp_timeout'] > 0)
        {
          $snmp_timeout = $device['snmp_timeout'];
        }
        else if (isset($GLOBALS['config']['snmp']['timeout']))
        {
          $snmp_timeout = $GLOBALS['config']['snmp']['timeout'];
        } else {
          $snmp_timeout = 1;
        }
        if (is_numeric($device['snmp_retries']) && $device['snmp_retries'] >= 0)
        {
          $snmp_retries = $device['snmp_retries'];
        }
        else if (isset($GLOBALS['config']['snmp']['retries']))
        {
          $snmp_retries = $GLOBALS['config']['snmp']['retries'];
        } else {
          $snmp_retries = 5;
        }
        $runtime_timeout = $snmp_timeout * (1 + $snmp_retries);

        //$error_code = 2; // All other is incomplete request or timeout?
        if (strpos($GLOBALS['exec_status']['stderr'], '.index are too large') !== FALSE) { $error_code = 997; }
        else if (preg_match('/(?:Cannot find module|Unknown Object Identifier)/', $GLOBALS['exec_status']['stderr'])) { $error_code = 996; }
        else if (preg_match('/ NULL\Z/', $GLOBALS['exec_status']['stdout']))             { $error_code = 1000; } // NULL as value at end of walk output
        else if ($GLOBALS['exec_status']['runtime'] >= $runtime_timeout)                 { $error_code = 3; }
      }
    }

    $msg = 'device: ' . $device['device_id'] . ', cmd: ' . $command . ', options: ' . $options;
    $msg .= ', mib: \'' . $mib . '\', oid: \'' . $oid . '\'';
    $msg .= ', cmd exitcode: ' . $GLOBALS['exec_status']['exitcode'] . ',' . PHP_EOL;
    $msg .= '             snmp error code: #' . $error_code . ', reason: \'' . $error_codes[$error_code]['reason'] . '\', runtime: ' . $GLOBALS['exec_status']['runtime'];

    if (OBS_DEBUG > 0)
    {
      if (OBS_DEBUG > 1)
      {
        // Show full error
        print_debug('SNMP ERROR - '. $msg);
      }
      else if ($error_code != 0 && $error_code != 900)
      {
        // Show only common error info
        print_message('SNMP ERROR[%r#' . $error_code . ' - ' . $error_codes[$error_code]['reason'] . '%n]', 'color');
      }
    }

    // Log error into DB, but only in poller modules, all other just return error code
    if (isset($GLOBALS['argv'][0]) && in_array(basename($GLOBALS['argv'][0]), array('poller.php')))
    {
      if ($error_code > 999 || $error_code < 900)
      {
        //$poll_period = 300;
        $poll_period = $GLOBALS['config']['rrd']['step'];
        // Count critical errors into DB (only for poller)
        $sql  = 'SELECT * FROM `snmp_errors` ';
        // Note, snmp_options not in unique db index
        //$sql .= 'WHERE `device_id` = ? AND `error_code` = ? AND `snmp_cmd` = ? AND `snmp_options` = ? AND `mib` = ? AND `oid` = ?;';
        //$error_db = dbFetchRow($sql, array($device['device_id'], $error_code, $command, $options, $mib, $oid));
        $sql .= 'WHERE `device_id` = ? AND `error_code` = ? AND `snmp_cmd` = ? AND `mib` = ? AND `oid` = ?;';
        $error_db = dbFetchRow($sql, array($device['device_id'], $error_code, $command, $mib, $oid));
        if (isset($error_db['error_id']))
        {
          $error_db['error_count']++;

          // DEBUG, error rate, if error rate >= 0.95, than error appears in each poll run
          //$poll_count = round(($error_timestamp - $error_db['added']) / $poll_period) + 1;
          //$error_db['error_rate'] = $error_db['error_count'] / $poll_count;
          //$msg .= ', rate: ' . $error_db['error_rate'] . ' err/poll';
          //logfile('snmp.log', $msg);

          // Update count
          $update_array = array('error_count' => $error_db['error_count'],
                                'updated'     => $error_timestamp);
          if ($error_db['mib_dir']      != $mibdir)  { $update_array['mib_dir']      = $mibdir; }
          if ($error_db['snmp_options'] != $options) { $update_array['snmp_options'] = $options; }
          dbUpdate($update_array, 'snmp_errors', '`error_id` = ?', array($error_db['error_id']));
        } else {
          dbInsert(array('device_id'          => $device['device_id'],
                         'error_count'        => 1,
                         'error_code'         => $error_code,
                         'error_reason'       => $error_codes[$error_code]['reason'],
                         'snmp_cmd_exitcode'  => $GLOBALS['exec_status']['exitcode'],
                         'snmp_cmd'           => $command,
                         'snmp_options'       => $options,
                         'mib'                => $mib,
                         'mib_dir'            => $mibdir,
                         'oid'                => $oid,
                         'added'              => $error_timestamp,
                         'updated'            => $error_timestamp), 'snmp_errors');
        }
      } else {
        // DEBUG
        //logfile('snmp.log', $msg);
      }
    }
  }

  $GLOBALS['snmp_error_code'] = $error_code; // Set global variable $snmp_error_code

  return $error_code;
}

/**
 * Uses snmpget to fetch multiple OIDs and returns a parsed array.
 *
 * @param  array  $device
 * @param  array  $oids
 * @param  string $options
 * @param  string $mib
 * @param  string $mibdir Optional, correct path should be set in the MIB definition
 * @global debug
 * @return array
 */
// TESTME needs unit testing
function snmp_get_multi($device, $oids, $options = '-OQUs', $mib = NULL, $mibdir = NULL, $flags = OBS_QUOTES_TRIM)
{
  global $snmp_stats;

  if (is_array($oids))
  {
    $data = '';
    $oid_chunks = array_chunk($oids, 16);
    $GLOBALS['snmp_status'] = FALSE;
    foreach ($oid_chunks as $oid_chunk)
    {
      $oid_text = implode($oid_chunk, ' ');
      $cmd   = snmp_command('snmpget', $device, $oid_text, $options, $mib, $mibdir, $flags);
      $start = microtime(TRUE);
      $this_data = trim(external_exec($cmd));
      $runtime  = microtime(TRUE) - $start;
      $GLOBALS['snmp_status'] = ($GLOBALS['exec_status']['exitcode'] === 0 ? TRUE : $GLOBALS['snmp_status']);
      snmp_log_errors('snmpget', $device, $oid_text, $options, $mib, $mibdir);
      $data .= $this_data."\n";
      $GLOBALS['snmp_stats']['snmpget']['count']++;
      $GLOBALS['snmp_stats']['snmpget']['time'] += $runtime;
    }
  } else {
    $cmd  = snmp_command('snmpget', $device, $oids, $options, $mib, $mibdir, $flags);
    $start = microtime(TRUE);
    $data = trim(external_exec($cmd));
    $runtime  = microtime(TRUE) - $start;
    $GLOBALS['snmp_status'] = ($GLOBALS['exec_status']['exitcode'] === 0 ? TRUE : FALSE);
    snmp_log_errors('snmpget', $device, $oids, $options, $mib, $mibdir);
    $GLOBALS['snmp_stats']['snmpget']['count']++;
    $GLOBALS['snmp_stats']['snmpget']['time'] += $runtime;
  }

  foreach (explode("\n", $data) as $entry)
  {
    list($oid,$value) = explode('=', $entry, 2);
    $oid   = trim($oid);
    $value = snmp_value_clean($value, $flags);
    list($oid, $index) = explode('.', $oid, 2);

    if (isset($oid[0]) && is_valid_snmp_value($value))
    {
      $array[$index][$oid] = $value;
    }
  }
  if (empty($array))
  {
    $GLOBALS['snmp_status'] = FALSE;
    snmp_log_errors('snmpget', $device, $oids, $options, $mib, $mibdir);
  }

  if (OBS_DEBUG)
  {
    print_message('SNMP STATUS['.($GLOBALS['snmp_status'] ? '%gTRUE': '%rFALSE').'%n]', 'color');
  }

  return $array;
}

/**
 * Common SNMP get/walk functions
 */

/**
 * Uses snmpget to fetch a single OID and returns a string.
 *
 * @param  array  $device
 * @param  array  $oid
 * @param  string $options
 * @param  string $mib
 * @param  string $mibdir Optional, correct path should be set in the MIB definition
 * @global debug
 * @return string
 */
function snmp_get($device, $oid, $options = NULL, $mib = NULL, $mibdir = NULL, $flags = OBS_QUOTES_TRIM)
{
  global $snmp_stats;

  if (strpos($oid, ' '))
  {
    print_debug("WARNING: snmp_get called for multiple OIDs: $oid");
  }

  $cmd = snmp_command('snmpget', $device, $oid, $options, $mib, $mibdir, $flags);

  $start    = microtime(TRUE);
  $data     = external_exec($cmd);
  $runtime  = microtime(TRUE) - $start;

  $data = snmp_value_clean($data, $flags);
  $GLOBALS['snmp_status'] = ($GLOBALS['exec_status']['exitcode'] === 0 ? TRUE : FALSE);

  $GLOBALS['snmp_stats']['snmpget']['count']++;
  $GLOBALS['snmp_stats']['snmpget']['time'] += $runtime;

  if (isset($data[0])) // same as strlen($data) > 0
  {
    if (preg_match('/(?:No Such Instance|No Such Object|There is no such variable|No more variables left|Authentication failure)/i', $data) ||
        $data == 'NULL')
    {
      $data = '';
      $GLOBALS['snmp_status'] = FALSE;
    }
  } else {
    $GLOBALS['snmp_status'] = FALSE;
  }
  if (OBS_DEBUG)
  {
    print_message('SNMP STATUS['.($GLOBALS['snmp_status'] ? '%gTRUE': '%rFALSE').'%n]', 'color');
  }
  snmp_log_errors('snmpget', $device, $oid, $options, $mib, $mibdir);

  return $data;
}

// DOCME needs phpdoc block
// TESTME needs unit testing
// FIXME, why strip quotes is default? this removes all quotes also in index
function snmp_walk($device, $oid, $options = NULL, $mib = NULL, $mibdir = NULL, $flags = OBS_QUOTES_STRIP)
{
  global $snmp_stats;

  $cmd = snmp_command('snmpwalk', $device, $oid, $options, $mib, $mibdir, $flags);

  $start = microtime(TRUE);
  $data = trim(external_exec($cmd));
  $runtime  = microtime(TRUE) - $start;

  $GLOBALS['snmp_status'] = ($GLOBALS['exec_status']['exitcode'] === 0 ? TRUE : FALSE);

  // FIXME, move this to snmp_parse_line() or remove, since snmp_parse_line() and snmp_value_clean() do this at correct way!
  if (is_flag_set(OBS_QUOTES_STRIP, $flags))
  {
    if (OBS_DEBUG > 1)
    {
      print_warning('All quotes stripped from snmp output, also for indexes and values!');
    }
    $data = str_replace('"', '', $data);
  }

  if (is_string($data) && (preg_match("/No Such (Object|Instance)/i", $data)))
  {
    $data = '';
    $GLOBALS['snmp_status'] = FALSE;
  } else {
    if (preg_match('/No more variables left in this MIB View \(It is past the end of the MIB tree\)$/', $data)
     || preg_match('/End of MIB$/', $data))
    {
      # Bit ugly :-(
      $d_ex = explode("\n",$data);
      $d_ex_count = count($d_ex);
      if ($d_ex_count > 1)
      {
        // Remove last line
        unset($d_ex[$d_ex_count-1]);
        $data = implode("\n",$d_ex);
      } else {
        $data = '';
        $GLOBALS['snmp_status'] = FALSE;
      }
    }

    // Concatenate multiline values if not set option -Oq
    if (is_flag_set(OBS_SNMP_CONCAT, $flags) && $data && strpos($options, 'q') === FALSE)
    {
      $old_data = $data;
      $data = array();
      foreach (explode("\n", $old_data) as $line)
      {
        if (strpos($line, ' = ') !== FALSE)
        {
          $data[] = $line;
        } else {
          $key = count($data) - 1;
          list(,$end) = explode(' = ', $data[$key], 2);
          if ($line !== '' && $end !== '')
          {
            $data[$key] .= ' ';
            //var_dump($line);
          }
          //$data[count($data)-1] .= '\n' . $line; // here NOT newline char, but two chars!
          $data[$key] .= $line;
        }
      }
      unset($old_data);
      $data = implode("\n", $data);
    }
  }
  $GLOBALS['snmp_stats']['snmpwalk']['count']++;
  $GLOBALS['snmp_stats']['snmpwalk']['time'] += $runtime;

  if (OBS_DEBUG)
  {
    print_message('SNMP STATUS['.($GLOBALS['snmp_status'] ? '%gTRUE': '%rFALSE').'%n]', 'color');
  }
  snmp_log_errors('snmpwalk', $device, $oid, $options, $mib, $mibdir);

  return $data;
}

// Cache snmpEngineID
// DOCME needs phpdoc block
// TESTME needs unit testing
function snmp_cache_snmpEngineID($device)
{
  global $cache;

  if ($device['snmp_version'] == 'v1') { return; } // snmpEngineID allowed only in v2c/v3

  if (!isset($cache['snmp'][$device['device_id']]['snmpEngineID']))
  {
    //$snmpEngineID = snmp_get($device, 'snmpEngineID.0', '-OQv', 'SNMP-FRAMEWORK-MIB');
    $snmpEngineID = snmp_get_oid($device, 'snmpEngineID.0', 'SNMP-FRAMEWORK-MIB');
    $snmpEngineID = str_replace(array(' ', '"', "'", "\n", "\r"), '', $snmpEngineID);

    if (isset($device['device_id']) && $device['device_id'] > 0)
    {
      $cache['snmp'][$device['device_id']]['snmpEngineID'] = $snmpEngineID;
    } else {
      // Correctly use this function, when device_id not set
      return $snmpEngineID;
    }
  }

  return $cache['snmp'][$device['device_id']]['snmpEngineID'];
}

// Return just an array of values without oids.
// DOCME needs phpdoc block
// TESTME needs unit testing
function snmpwalk_values($device, $oid, $array, $mib = NULL, $mibdir = NULL)
{
  $data = snmp_walk($device, $oid, '-OQUs', $mib, $mibdir);
  foreach (explode("\n", $data) as $line)
  {
    $entry = snmp_parse_line($line);

    if (isset($entry['oid_name'][0]) && $entry['index_count'] > 0 && is_valid_snmp_value($entry['value']))
    {
      $array[] = $entry['value'];
    }
  }

  return $array;
}

// Return an array of values with numeric oids as keys
// DOCME needs phpdoc block
// TESTME needs unit testing
function snmpwalk_numericoids($device, $oid, $array, $mib = NULL, $mibdir = NULL)
{
  return snmpwalk_cache_oid($device, $oid, $array, $mib, $mibdir, OBS_SNMP_ALL_NUMERIC);

  /*
  $data = snmp_walk($device, $oid, '-OQUn', $mib, $mibdir);
  foreach (explode("\n", $data) as $entry)
  {
    list($oid,$value) = explode('=', $entry, 2);
    $oid = trim($oid); $value = trim($value);
    if (isset($oid) && is_valid_snmp_value($value))
    {
      $array[$oid] = $value;
    }
  }

  return $array;
  */
}

/**
 * Uses snmpget to fetch single OID and return string value.
 * Differences from snmp_get:
 *  - not required raw $options, default is -OQv
 *
 * snmp_get() in-code (r8636) options:
 *    -Oqv    : 252
 *    -OQv    : 149
 *    -OQUsv  : 37
 *    -OUnqv  : 21
 *    -Onqv   : 19
 *    -Oqsv   : 17
 *    -OQUnv  : 14
 *    -OUqv   : 7
 *    -Onqsv  : 3
 *    -OQUs   : 2
 *    -OUqsv  : 1
 *    -OQnv   : 1
 *    -OQUnsv : 1
 * 
 * snmp_get() cleaned options:
 *   'U', 's' has no effect with 'v', 'Q' better than 'q' (no more Wrong Type):
 *    -OQv    : 463
 *    -OQnv   : 59
 *    -OQUs   : 2
 * 
 *    snmp_get() each option:
 *    v       : 522
 *    q       : 320
 *    Q       : 204
 *    U       : 83
 *    s       : 61
 *    n       : 59
 *
 * @param  array  $device
 * @param  string $oid
 * @param  string $mib
 * @param  string $mibdir Optional, correct path should be set in the MIB definition
 * @global debug
 * @return string
 */
function snmp_get_oid($device, $oid, $mib = NULL, $mibdir = NULL, $flags = OBS_QUOTES_TRIM)
{
  global $snmp_stats;

  // Basic options,
  // NOTE: 'U', 's' has no effect with 'v',
  //       'Q' better than 'q' (no more Wrong Type):
  $output = 'Qv';
  
  //if (is_flag_set(OBS_SNMP_NUMERIC_INDEX, $flags)) { $output .= 'b'; } // has no effect
  if (is_flag_set(OBS_SNMP_NUMERIC,       $flags)) { $output .= 'n'; }   // not sure, probably need 't' (timeticks) also
  if (is_flag_set(OBS_SNMP_ENUM,          $flags)) { $output .= 'e'; }
  if (is_flag_set(OBS_SNMP_HEX,           $flags)) { $output .= 'x'; }
  //if (is_flag_set(OBS_SNMP_TABLE,         $flags)) { $output .= 'X'; } // has no effect
  $options = "-O$output";

  return snmp_get($device, $oid, $options, $mib, $mibdir, $flags);
}

/**
 * Uses snmpget to fetch multiple OIDs and returns a parsed array.
 * Differences from snmp_get_multi:
 *  - return same array as in snmpwalk_cache_oid()
 *  - array merges with passed array as in snmpwalk_cache_oid()
 *
 * @param  array  $device
 * @param  array  $oids
 * @param  string $options
 * @param  string $mib
 * @param  string $mibdir Optional, correct path should be set in the MIB definition
 * @global debug
 * @return array
 */
// TESTME needs unit testing
function snmp_get_multi_oid($device, $oids, $array, $mib = NULL, $mibdir = NULL, $flags = OBS_QUOTES_TRIM)
{
  global $snmp_stats;

  $output       = 'QUs';
  $numeric_oids = is_flag_set(OBS_SNMP_NUMERIC, $flags); // Numeric oids, do not parse oid part
  if (is_flag_set(OBS_SNMP_NUMERIC_INDEX, $flags)) { $output .= 'b'; }
  if ($numeric_oids)                               { $output .= 'n'; }
  if (is_flag_set(OBS_SNMP_ENUM,          $flags)) { $output .= 'e'; }
  if (is_flag_set(OBS_SNMP_HEX,           $flags)) { $output .= 'x'; }
  if (is_flag_set(OBS_SNMP_TABLE,         $flags)) { $output .= 'X'; }
  $options = "-O$output";

  if (is_array($oids))
  {
    $data = '';
    $oid_chunks = array_chunk($oids, 16);
    $GLOBALS['snmp_status'] = FALSE;
    foreach ($oid_chunks as $oid_chunk)
    {
      $oid_text = implode($oid_chunk, ' ');
      $cmd   = snmp_command('snmpget', $device, $oid_text, $options, $mib, $mibdir, $flags);
      $start = microtime(TRUE);
      $this_data = trim(external_exec($cmd));
      $runtime  = microtime(TRUE) - $start;

      $GLOBALS['snmp_status'] = ($GLOBALS['exec_status']['exitcode'] === 0 ? TRUE : $GLOBALS['snmp_status']);
      snmp_log_errors('snmpget', $device, $oid_text, $options, $mib, $mibdir);
      $data .= $this_data."\n";
      $GLOBALS['snmp_stats']['snmpget']['count']++;
      $GLOBALS['snmp_stats']['snmpget']['time'] += $runtime;
    }
  } else {
    $cmd  = snmp_command('snmpget', $device, $oids, $options, $mib, $mibdir, $flags);
    $start = microtime(TRUE);
    $data = trim(external_exec($cmd));
    $runtime  = microtime(TRUE) - $start;
    $GLOBALS['snmp_status'] = ($GLOBALS['exec_status']['exitcode'] === 0 ? TRUE : FALSE);
    snmp_log_errors('snmpget', $device, $oids, $options, $mib, $mibdir);
    $GLOBALS['snmp_stats']['snmpget']['count']++;
    $GLOBALS['snmp_stats']['snmpget']['time'] += $runtime;
  }

  foreach (explode("\n", $data) as $line)
  {
    $entry = snmp_parse_line($line, $flags);

    // For numeric oids do not split oid and index part
    if ($numeric_oids && $entry['index_count'] > 0 && is_valid_snmp_value($entry['value']))
    {
      $array[$entry['index']] = $entry['value'];
      continue;
    }

    //list($oid, $index) = explode('.', $oid, 2);
    if (isset($entry['oid_name'][0]) && $entry['index_count'] > 0 && is_valid_snmp_value($entry['value']))
    {
      $array[$entry['index']][$entry['oid_name']] = $entry['value'];
    }
  }

  if (empty($array))
  {
    $GLOBALS['snmp_status'] = FALSE;
    snmp_log_errors('snmpget', $device, $oids, $options, $mib, $mibdir);
  }

  if (OBS_DEBUG)
  {
    print_message('SNMP STATUS['.($GLOBALS['snmp_status'] ? '%gTRUE': '%rFALSE').'%n]', 'color');
  }

  return $array;
}

/**
 * Uses snmpwalk to fetch a single OID and returns a array.
 *
 * @param  array  $device
 * @param  string $oid
 * @param  string $mib
 * @param  string $mibdir Optional, correct path should be set in the MIB definition
 * @return array
 */
function snmpwalk_cache_oid($device, $oid, $array, $mib = NULL, $mibdir = NULL, $flags = OBS_SNMP_ALL)
{
  $numeric_oids = is_flag_set(OBS_SNMP_NUMERIC, $flags); // Numeric oids, do not parse oid part

  $output = 'QUs';
  if (is_flag_set(OBS_SNMP_NUMERIC_INDEX, $flags)) { $output .= 'b'; }
  if ($numeric_oids)                               { $output .= 'n'; }
  if (is_flag_set(OBS_SNMP_ENUM,          $flags)) { $output .= 'e'; }
  if (is_flag_set(OBS_SNMP_HEX,           $flags)) { $output .= 'x'; }
  if (is_flag_set(OBS_SNMP_TABLE,         $flags)) { $output .= 'X'; }

  $data = snmp_walk($device, $oid, "-O$output", $mib, $mibdir, $flags);
  foreach (explode("\n", $data) as $line)
  {
    $entry = snmp_parse_line($line, $flags);

    // For numeric oids do not split oid and index part
    if ($numeric_oids && $entry['index_count'] > 0 && is_valid_snmp_value($entry['value']))
    {
      $array[$entry['index']] = $entry['value'];
      continue;
    }

    if (isset($entry['oid_name'][0]) && $entry['index_count'] > 0 && is_valid_snmp_value($entry['value']))
    {
      $array[$entry['index']][$entry['oid_name']] = $entry['value'];
    }
  }

  return $array;

  // CLEANME
  /*
  $output = 'QUs';
  if (is_flag_set(OBS_SNMP_NUMERIC_INDEX, $flags)) { $output .= 'b'; }
  if (is_flag_set(OBS_SNMP_NUMERIC,       $flags)) { $output .= 'n'; }
  if (is_flag_set(OBS_SNMP_ENUM,          $flags)) { $output .= 'e'; }
  if (is_flag_set(OBS_SNMP_HEX,           $flags)) { $output .= 'x'; }

  $data = snmp_walk($device, $oid, "-O$output", $mib, $mibdir, $flags);

  foreach (explode("\n", $data) as $entry)
  {
    list($oid, $value) = explode('=', $entry, 2);
    $oid   = trim($oid);
    $value = trim_quotes($value, $flags);
    list($oid, $index) = explode('.', $oid, 2);
    if (isset($oid) && isset($index) && is_valid_snmp_value($value))
    {
      $array[$index][$oid] = $value;
    }
  }

  return $array;
  */
}

// just like snmpwalk_cache_oid except that it returns the numerical oid as the index
// this is useful when the oid is indexed by the mac address and snmpwalk would
// return periods (.) for non-printable numbers, thus making many different indexes appear
// to be the same.
// DOCME needs phpdoc block
// TESTME needs unit testing
// CLEANME (deprecated) not used anymore
function snmpwalk_cache_oid_num($device, $oid, $array, $mib = NULL, $mibdir = NULL)
{
  return snmpwalk_cache_oid($device, $oid, $array, $mib, $mibdir, OBS_SNMP_ALL_NUMERIC);

  /*
  $data = snmp_walk($device, $oid, '-OQUn', $mib, $mibdir);
  foreach (explode("\n", $data) as $entry)
  {
    list($oid,$value) = explode('=', $entry, 2);
    $oid = trim($oid); $value = trim($value);
    list($oid, $index) = explode('.', $oid, 2);
    if (isset($oid) && isset($index[0]) && is_valid_snmp_value($value))
    {
      $array[$index][$oid] = $value;
    }
  }

  return $array;
  */
}

// just like snmpwalk_cache_oid_num (it returns the numerical oid as the index),
// but use snmptranslate for cut mib part from index
// DOCME needs phpdoc block
// TESTME needs unit testing
// FIXME. maybe override function snmpwalk_cache_oid_num()?
function snmpwalk_cache_oid_num2($device, $oid, $array, $mib = NULL, $mibdir = NULL, $flags = OBS_SNMP_ALL_NUMERIC)
{
  $output = 'QUn'; // This function always use OBS_SNMP_NUMERIC
  //if (is_flag_set(OBS_SNMP_NUMERIC_INDEX, $flags)) { $output .= 'b'; }
  //if (is_flag_set(OBS_SNMP_NUMERIC,       $flags)) { $output .= 'n'; }
  if (is_flag_set(OBS_SNMP_ENUM,          $flags)) { $output .= 'e'; }
  if (is_flag_set(OBS_SNMP_HEX,           $flags)) { $output .= 'x'; }

  $data = snmp_walk($device, $oid, "-O$output", $mib, $mibdir, $flags);

  $oid_base = snmp_translate($oid, $mib, $mibdir);
  $pattern = '/^' . str_replace('.', '\.', $oid_base) . '\./';

  foreach (explode("\n", $data) as $entry)
  {
    list($oid_num, $value) = explode('=', $entry, 2);
    $oid_num = trim($oid_num);
    $value   = snmp_value_clean($value, $flags);
    $index   = preg_replace($pattern, '', $oid_num);

    if (isset($oid) && isset($index[0]) && is_valid_snmp_value($value))
    {
      $array[$index][$oid] = $value;
    }
  }

  return $array;
}

// DOCME needs phpdoc block
// TESTME needs unit testing
// used only in discovery/processors/juniper-system-mib.inc.php
function snmpwalk_cache_bare_oid($device, $oid, $array, $mib = NULL, $mibdir = NULL, $flags = OBS_SNMP_ALL)
{
  // Always use snmpwalk_cache_oid() for numeric
  if (is_flag_set(OBS_SNMP_NUMERIC,       $flags))
  {
    return snmpwalk_cache_oid($device, $oid, $array, $mib, $mibdir, $flags);
  }

  $output = 'QUs';
  if (is_flag_set(OBS_SNMP_NUMERIC_INDEX, $flags)) { $output .= 'b'; }
  //if (is_flag_set(OBS_SNMP_NUMERIC,       $flags)) { $output .= 'n'; }
  if (is_flag_set(OBS_SNMP_ENUM,          $flags)) { $output .= 'e'; }
  if (is_flag_set(OBS_SNMP_HEX,           $flags)) { $output .= 'x'; }
  if (is_flag_set(OBS_SNMP_TABLE,         $flags)) { $output .= 'X'; }

  $index_count = 2;
  $data = snmp_walk($device, $oid, "-O$output", $mib, $mibdir, $flags);
  foreach (explode("\n", $data) as $line)
  {
    $entry = snmp_parse_line($line, $flags);

    // Not know why, but here removed index part more than 2, here old code:
    // list($r_oid, $first, $second) = explode('.', $r_oid);
    if (isset($entry['oid']) && is_valid_snmp_value($entry['value']))
    {
      $array[$entry['oid']] = $entry['value'];
    }
  }

  return $array;
}


// DOCME needs phpdoc block
// TESTME needs unit testing
// used only in discovery/processors/juniper-system-mib.inc.php
function snmpwalk_cache_double_oid($device, $oid, $array, $mib = NULL, $mibdir = NULL, $flags = OBS_SNMP_ALL)
{
  // Always use snmpwalk_cache_oid() for numeric 
  if (is_flag_set(OBS_SNMP_NUMERIC,       $flags))
  {
    return snmpwalk_cache_oid($device, $oid, $array, $mib, $mibdir, $flags);
  }

  $output = 'QUs';
  if (is_flag_set(OBS_SNMP_NUMERIC_INDEX, $flags)) { $output .= 'b'; }
  //if (is_flag_set(OBS_SNMP_NUMERIC,       $flags)) { $output .= 'n'; }
  if (is_flag_set(OBS_SNMP_ENUM,          $flags)) { $output .= 'e'; }
  if (is_flag_set(OBS_SNMP_HEX,           $flags)) { $output .= 'x'; }
  if (is_flag_set(OBS_SNMP_TABLE,         $flags)) { $output .= 'X'; }

  $index_count = 2;
  $data = snmp_walk($device, $oid, "-O$output", $mib, $mibdir, $flags);
  foreach (explode("\n", $data) as $line)
  {
    $entry = snmp_parse_line($line, $flags);

    // Not know why, but here removed index part more than 2, here old code:
    // list($r_oid, $first, $second) = explode('.', $r_oid);
    if (isset($entry['oid_name'][0]) && $entry['index_count'] >= $index_count && is_valid_snmp_value($entry['value']))
    {
      $index = implode('.', array_slice($entry['index_parts'], 0, $index_count));
      $array[$index][$entry['oid_name']] = $entry['value'];
    }
  }

  return $array;
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function snmpwalk_cache_triple_oid($device, $oid, $array, $mib = NULL, $mibdir = NULL, $flags = OBS_SNMP_ALL)
{
  // Always use snmpwalk_cache_oid() for numeric 
  if (is_flag_set(OBS_SNMP_NUMERIC,       $flags))
  {
    return snmpwalk_cache_oid($device, $oid, $array, $mib, $mibdir, $flags);
  }

  $output = 'QUs';
  if (is_flag_set(OBS_SNMP_NUMERIC_INDEX, $flags)) { $output .= 'b'; }
  //if (is_flag_set(OBS_SNMP_NUMERIC,       $flags)) { $output .= 'n'; }
  if (is_flag_set(OBS_SNMP_ENUM,          $flags)) { $output .= 'e'; }
  if (is_flag_set(OBS_SNMP_HEX,           $flags)) { $output .= 'x'; }
  if (is_flag_set(OBS_SNMP_TABLE,         $flags)) { $output .= 'X'; }

  $index_count = 3; // Not know why, but here removed index part more than 3
  $data = snmp_walk($device, $oid, "-O$output", $mib, $mibdir, $flags);
  foreach (explode("\n", $data) as $line)
  {
    $entry = snmp_parse_line($line, $flags);

    // Not know why, but here removed index part more than 3, here old code:
    // list($r_oid, $first, $second, $tried) = explode('.', $r_oid);
    if (isset($entry['oid_name'][0]) && $entry['index_count'] >= $index_count && is_valid_snmp_value($entry['value']))
    {
      $index = implode('.', array_slice($entry['index_parts'], 0, $index_count));
      $array[$index][$entry['oid_name']] = $entry['value'];
    }
  }

  return $array;
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function snmpwalk_cache_twopart_oid($device, $oid, $array, $mib = NULL, $mibdir = NULL, $flags = OBS_SNMP_ALL)
{
  // Always use snmpwalk_cache_oid() for numeric 
  if (is_flag_set(OBS_SNMP_NUMERIC,       $flags))
  {
    return snmpwalk_cache_oid($device, $oid, $array, $mib, $mibdir, $flags);
  }

  $output = 'QUs';
  if (is_flag_set(OBS_SNMP_NUMERIC_INDEX, $flags)) { $output .= 'b'; }
  //if (is_flag_set(OBS_SNMP_NUMERIC,       $flags)) { $output .= 'n'; }
  if (is_flag_set(OBS_SNMP_ENUM,          $flags)) { $output .= 'e'; }
  if (is_flag_set(OBS_SNMP_HEX,           $flags)) { $output .= 'x'; }
  if (is_flag_set(OBS_SNMP_TABLE,         $flags)) { $output .= 'X'; }

  $index_count = 2;
  $data = snmp_walk($device, $oid, "-O$output", $mib, $mibdir, $flags);
  foreach (explode("\n", $data) as $line)
  {
    $entry = snmp_parse_line($line, $flags);

    if (isset($entry['oid_name'][0]) && $entry['index_count'] >= $index_count && is_valid_snmp_value($entry['value']))
    {
      $first     = array_shift($entry['index_parts']);
      $second    = implode('.', $entry['index_parts']);

      $array[$first][$second][$entry['oid_name']] = $entry['value'];
    }
  }

  return $array;
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function snmpwalk_cache_threepart_oid($device, $oid, $array, $mib = NULL, $mibdir = NULL, $flags = OBS_SNMP_ALL)
{
  // Always use snmpwalk_cache_oid() for numeric
  if (is_flag_set(OBS_SNMP_NUMERIC,       $flags))
  {
    return snmpwalk_cache_oid($device, $oid, $array, $mib, $mibdir, $flags);
  }

  $output = 'QUs';
  if (is_flag_set(OBS_SNMP_NUMERIC_INDEX, $flags)) { $output .= 'b'; }
  //if (is_flag_set(OBS_SNMP_NUMERIC,       $flags)) { $output .= 'n'; }
  if (is_flag_set(OBS_SNMP_ENUM,          $flags)) { $output .= 'e'; }
  if (is_flag_set(OBS_SNMP_HEX,           $flags)) { $output .= 'x'; }
  if (is_flag_set(OBS_SNMP_TABLE,         $flags)) { $output .= 'X'; }

  $index_count = 3;
  $data = snmp_walk($device, $oid, "-O$output", $mib, $mibdir, $flags);
  foreach (explode("\n", $data) as $line)
  {
    $entry = snmp_parse_line($line, $flags);

    if (isset($entry['oid_name'][0]) && $entry['index_count'] >= $index_count && is_valid_snmp_value($entry['value']))
    {
      $first     = array_shift($entry['index_parts']);
      $second    = array_shift($entry['index_parts']);
      $third     = implode('.', $entry['index_parts']);
      $array[$first][$second][$third][$entry['oid_name']] = $entry['value'];
    }
  }

  return $array;
}

/**
 * SNMP walk and parse tables with any (not limited) count of index parts into multilevel array.
 * Array levels same as count index parts. Ie: someOid.1.2.3.4 -> 4 index parts, and result array also will have 4 levels
 *
 * @param array   $device Device array
 * @param string  $oid    Table OID name
 * @param array   $array  Array from previous snmpwalk for merge (or empty)
 * @param string  $mib    MIB name
 * @param mixed   $mibdir Array or string with MIB dirs list, by default used dir from MIB definitions
 * @param integer $flags  SNMP walk/parse flags
 *
 * @return array          Prsed array with content from requested Table
 */
function snmp_walk_multipart_oid($device, $oid, $array, $mib = NULL, $mibdir = NULL, $flags = OBS_QUOTES_TRIM)
{
  // Always use snmpwalk_cache_oid() for numeric
  if (is_flag_set(OBS_SNMP_NUMERIC,       $flags))
  {
    return snmpwalk_cache_oid($device, $oid, $array, $mib, $mibdir, $flags);
  }

  $output = 'QUs';
  if (is_flag_set(OBS_SNMP_NUMERIC_INDEX, $flags)) { $output .= 'b'; }
  //if (is_flag_set(OBS_SNMP_NUMERIC,       $flags)) { $output .= 'n'; }
  if (is_flag_set(OBS_SNMP_ENUM,          $flags)) { $output .= 'e'; }
  if (is_flag_set(OBS_SNMP_HEX,           $flags)) { $output .= 'x'; }
  if (is_flag_set(OBS_SNMP_TABLE,         $flags)) { $output .= 'X'; }

  $index_count = 1;
  $data = snmp_walk($device, $oid, "-O$output", $mib, $mibdir, $flags);
  foreach (explode("\n", $data) as $line)
  {
    $entry = snmp_parse_line($line, $flags);
    //print_vars($entry);

    if (isset($entry['oid_name'][0]) && $entry['index_count'] >= $index_count && is_valid_snmp_value($entry['value']))
    {
      $entry_array = array($entry['oid_name'] => $entry['value']);
      for ($i = $entry['index_count'] - 1; $i >= 0; $i--)
      {
        $entry_array = array($entry['index_parts'][$i] => $entry_array);
      }
      $array = array_replace_recursive((array)$array, $entry_array);

      /* this seems retarded. need a way to just build this automatically.
      switch ($entry['index_count'])
      {
        case 1:
          $array[$entry['index_parts'][0]][$entry['oid_name']] = $entry['value'];
          break;
        case 2:
          $array[$entry['index_parts'][0]][$entry['index_parts'][1]][$entry['oid_name']] = $entry['value'];
          break;
        case 3:
          $array[$entry['index_parts'][0]][$entry['index_parts'][1]][$entry['index_parts'][2]][$entry['oid_name']] = $entry['value'];
          break;
        case 4:
          $array[$entry['index_parts'][0]][$entry['index_parts'][1]][$entry['index_parts'][2]][$entry['index_parts'][3]][$entry['oid_name']] = $entry['value'];
          break;
        case 5:
          $array[$entry['index_parts'][0]][$entry['index_parts'][1]][$entry['index_parts'][2]][$entry['index_parts'][3]][$entry['index_parts'][4]][$entry['oid_name']] = $entry['value'];
          break;
      }
      */
    }
  }

  return $array;
}

/**
 * Initialize (start) snmpsimd daemon, for tests or other purposes.
 *   Stop daemon not required, because here registered shutdown_function for kill daemon at end of run script(s)
 *
 * @param string $snmpsimd_data Data DIR, where *.snmprec placed
 * @param string $snmpsimd_ip   Local IP which used for daemon (default 127.0.0.1)
 * @param string $snmpsimd_port Local Port which used for daemon (default 16111)
 */
function snmpsimd_init($snmpsimd_data, $snmpsimd_ip = '127.0.0.1', $snmpsimd_port = 16111)
{
  global $config;

  if (str_contains($snmpsimd_ip, ':'))
  {
    // IPv6
    $ifconfig_cmd = "ifconfig | grep 'inet6 addr:$snmpsimd_ip' | cut -d: -f2 | awk '{print $1}'";
    $snmpsimd_end = 'udpv6';
  } else {
    $ifconfig_cmd = "ifconfig | grep 'inet addr:$snmpsimd_ip' | cut -d: -f2 | awk '{print $1}'";
    $snmpsimd_end = 'udpv4';
  }
  $snmpsimd_ip  = external_exec($ifconfig_cmd);

  if ($snmpsimd_ip)
  {
    //$snmpsimd_port = 16111;

    // Detect snmpsimd command path
    $snmpsimd_path = external_exec('which snmpsimd.py');
    if (empty($snmpsimd_path))
    {
      foreach (array('/usr/local/bin/', '/usr/bin/', '/usr/sbin/') as $path)
      {
        if (is_executable($path . 'snmpsimd.py'))
        {
          $snmpsimd_path = $path . 'snmpsimd.py';
          break;
        }
        else if (is_executable($path . 'snmpsimd'))
        {
          $snmpsimd_path = $path . 'snmpsimd';
          break;
        }
      }
    }
    //var_dump($snmpsimd_path);

    if (empty($snmpsimd_path))
    {
      print_warning("snmpsimd not found, please install it first.");
    } else {
      //$snmpsimd_data = dirname(__FILE__) . '/data/os';

      $tmp_path = empty($config['temp_dir']) ? '/tmp' : $config['temp_dir']; // GLOBALS empty in php units

      $snmpsimd_pid  = $tmp_path.'/observium_snmpsimd.pid';
      $snmpsimd_log  = $tmp_path.'/observium_snmpsimd.log';

      if (is_file($snmpsimd_pid))
      {
        // Kill stale snmpsimd process
        $pid  = file_get_contents($snmpsimd_pid);
        $info = get_pid_info($pid);
        //var_dump($info);
        if (str_contains($info['COMMAND'], 'snmpsimd'))
        {
          external_exec("kill -9 $pid");
        }
        unlink($snmpsimd_pid);
      }

      $snmpsimd_cmd = "$snmpsimd_path --daemonize --data-dir=$snmpsimd_data --agent-$snmpsimd_end-endpoint=$snmpsimd_ip:$snmpsimd_port --pid-file=$snmpsimd_pid --logging-method=file:$snmpsimd_log";
      //var_dump($snmpsimd_cmd);

      external_exec($snmpsimd_cmd);
      $pid = file_get_contents($snmpsimd_pid);
      if ($pid)
      {
        define('OBS_SNMPSIMD', TRUE);
        register_shutdown_function(function($snmpsimd_pid){
          $pid = file_get_contents($snmpsimd_pid);
          //echo "KILL'em all! PID: $pid\n";
          external_exec("kill -9 $pid");
          unlink($snmpsimd_pid);
        }, $snmpsimd_pid);
      }
    }
    //exit;
  } else {
    print_warning("Local IP $snmpsimd_ip unavailable. SNMP simulator not started.");
  }
  if (!defined('OBS_SNMPSIMD'))
  {
    define('OBS_SNMPSIMD', FALSE);
  }
}


/**
 * Deprecated, not used functions (can removed later)
 */

// DOCME needs phpdoc block
// TESTME needs unit testing
// FIXME this function is not used, OK to remove?
// CLEANME (deprecated) not used anymore
function snmp_cache_slotport_oid($oid, $device, $array, $mib = NULL)
{
  $data = snmp_walk($device, $oid, '-OQUs', $mib);

  $device_id = $device['device_id'];

  foreach (explode("\n", $data) as $entry)
  {
    $entry = str_replace($oid.'.', '', $entry);
    list($slotport, $value) = explode('=', $entry, 2);
    $slotport = trim($slotport); $value = trim($value);
    if ($array[$slotport]['ifIndex'] && is_valid_snmp_value($value))
    {
      $ifIndex = $array[$slotport]['ifIndex'];
      $array[$ifIndex][$oid] = $value;
    }
  }

  return $array;
}

// DOCME needs phpdoc block
// TESTME needs unit testing
// FIXME this function is not used, OK to remove?
// CLEANME (deprecated) not used anymore
function snmpget_entity_oids($oids, $index, $device, $array, $mib = NULL)
{
  foreach ($oids as $oid)
  {
    $oid_string .= " $oid.$index";
  }

  return snmp_get_multi($device, $oids, '-Ovq', $mib);
}

// DOCME needs phpdoc block
// TESTME needs unit testing
// CLEANME (deprecated) not used anymore
function snmpwalk_cache_cip($device, $oid, $array, $mib = NULL, $mibdir = NULL)
{
  $data = snmp_walk($device, $oid, '-OsnQ', $mib, $mibdir);

  foreach (explode("\n", $data) as $entry)
  {
    list ($this_oid, $this_value) = preg_split("/=/", $entry);
    $this_oid = trim($this_oid);
    $this_value = trim($this_value);
    $this_oid = substr($this_oid, 30);
    list($ifIndex,$dir,$a,$b,$c,$d,$e,$f) = explode('.', $this_oid);
    $h_a = zeropad(dechex($a));
    $h_b = zeropad(dechex($b));
    $h_c = zeropad(dechex($c));
    $h_d = zeropad(dechex($d));
    $h_e = zeropad(dechex($e));
    $h_f = zeropad(dechex($f));
    $mac = "$h_a$h_b$h_c$h_d$h_e$h_f";
    if ($dir == 1) { $dir = 'input'; } elseif ($dir == 2) { $dir = 'output'; }
    if ($mac && $dir)
    {
      $array[$ifIndex][$mac][$oid][$dir] = $this_value;
    }
  }
  return $array;
}

/**
 * Take -OXqs output and parse it into an array containing OID array and the value
 * Hopefully this is the beginning of more intelligent OID parsing!
 * Thanks to David Farrell <DavidPFarrell@gmail.com> for the parser solution.
 * This function is free for use by all with attribution to David.
 *
 * @return array
 * @param $string
 */
// TESTME needs unit testing
function parse_oid2($string)
{
  $result = array();
  $matches = array();

  // Match OID - If wrapped in double-quotes ('"'), must escape '"', else must escape ' ' (space) or '[' - Other escaping is optional
  $match_count = preg_match('/^(?:((?!")(?:[^\\\\\\[ ]|(?:\\\\.))+)|(?:"((?:[^\\\\\"]|(?:\\\\.))+)"))/', $string, $matches);
  if (null !== $match_count && $match_count > 0)
  {
    // [1] = unquoted, [2] = quoted
    $value = strlen($matches[1]) > 0 ? $matches[1] : $matches[2];
    $result[] = stripslashes($value);

    // I do this (vs keeping track of offset) to use ^ in regex
    $string = substr($string, strlen($matches[0]));

    // Match indexes (optional) - If wrapped in double-quotes ('"'), must escape '"', else must escape ']' - Other escaping is optional
    while (true)
    {
      $match_count = preg_match('/^\\[(?:((?!")(?:[^\\\\\\]]|(?:\\\\.))+)|(?:"((?:[^\\\\\"]|(?:\\\\.))+)"))\\]/', $string, $matches);
      if (null !== $match_count && $match_count > 0)
      {
        // [1] = unquoted, [2] = quoted
        $value = strlen($matches[1]) > 0 ? $matches[1] : $matches[2];
        $result[] = stripslashes($value);

        // I do this (vs keeping track of offset) to use ^ in regex
        $string = substr($string, strlen($matches[0]));
      }
      else
      {
        break;
      }
    } // while

    // Match value - Skips leading ' ' characters - If remainder is wrapped in double-quotes ('"'), must escape '"', other escaping is optional
    $match_count = preg_match('/^\\s+(?:((?!")(?:[^\\\\]|(?:\\\\.))+)|(?:"((?:[^\\\\\"]|(?:\\\\.))+)"))$/', $string, $matches);
    if (null !== $match_count && $match_count > 0)
    {
      // [1] = unquoted, [2] = quoted
      $value = strlen($matches[1]) > 0 ? $matches[1] : $matches[2];

      $result[] = stripslashes($value);

      if (strlen($string) != strlen($matches[0])) { echo 'Length error!'; return null; }

      return $result;
    }
  }

  // All or nothing
  return null;
}

/**
 * Take -Oqs output and parse it into an array containing OID array and the value
 * Hopefully this is the beginning of more intelligent OID parsing!
 * Thanks to David Farrell <DavidPFarrell@gmail.com> for the parser solution.
 * This function is free for use by all with attribution to David.
 *
 * @return array
 * @param $string
 */
// TESTME needs unit testing
// CLEANME (deprecated) not used anymore
function parse_oid($string)
{
  $result = array();
  while (true)
  {
    $matches = array();
    $match_count = preg_match('/^(?:((?:[^\\\\\\. "]|(?:\\\\.))+)|(?:"((?:[^\\\\"]|(?:\\\\.))+)"))((?:[\\. ])|$)/', $string, $matches);
    if (null !== $match_count && $match_count > 0)
    {
      // [1] = unquoted, [2] = quoted
      $value = strlen($matches[1]) > 0 ? $matches[1] : $matches[2];
      $result[] = stripslashes($value);

      // Are we expecting any more parts?
      if (strlen($matches[3]) > 0)
      {
        // I do this (vs keeping track of offset) to use ^ in regex
        $string = substr($string, strlen($matches[0]));
      }
      else
      {
        $ret['value'] = array_pop($result);
        $ret['oid']   = $result;
        return $ret;
      }
    }
    else
    {
      // All or nothing
      return null;
    }
  } // while
}

// DOCME needs phpdoc block
// TESTME needs unit testing
// CLEANME (deprecated) not used anymore
function snmp_walk_parser2($device, $oid, $oid_elements, $mib = NULL, $mibdir = NULL)
{
  $data = snmp_walk($device, $oid, '-Oqs', $mib, $mibdir, FALSE);
  foreach (explode("\n", $data) as $text)
  {
    $ret = parse_oid2($text);
    if (!empty($ret['value']))
    {
      // this seems retarded. need a way to just build this automatically.
      switch ($oid_elements)
      {
        case 1:
          $array[$ret['oid'][0]] = $ret['value'];
          break;
        case 2:
          $array[$ret['oid'][1]][$ret['oid'][0]] = $ret['value'];
          break;
        case 3:
          $array[$ret['oid'][1]][$ret['oid'][2]][$ret['oid'][0]] = $ret['value'];
          break;
        case 4:
          $array[$ret['oid'][1]][$ret['oid'][2]][$ret['oid'][3]][$ret['oid'][0]] = $ret['value'];
          break;
      }
    }
  }
  return $array;
}

// DOCME needs phpdoc block
// TESTME needs unit testing
function snmp_walk_parser($device, $oid, $oid_elements, $mib = NULL, $mibdir = NULL)
{
  $data = snmp_walk($device, $oid, '-Oqs', $mib, $mibdir, FALSE);
  foreach (explode("\n", $data) as $text)
  {
    $ret = parse_oid($text);
    if (!empty($ret['value']))
    {
      // this seems retarded. need a way to just build this automatically.
      switch ($oid_elements)
      {
        case 1:
          $array[$ret['oid'][0]] = $ret['value'];
          break;
        case 2:
          $array[$ret['oid'][1]][$ret['oid'][0]] = $ret['value'];
          break;
        case 3:
          $array[$ret['oid'][1]][$ret['oid'][2]][$ret['oid'][0]] = $ret['value'];
          break;
        case 4:
          $array[$ret['oid'][1]][$ret['oid'][2]][$ret['oid'][3]][$ret['oid'][0]] = $ret['value'];
          break;
      }
    }
  }

  return $array;
}


// CLEANME (deprecated) duplicate for snmpwalk_cache_oid
function snmpwalk_cache_multi_oid($device, $oid, $array, $mib = NULL, $mibdir = NULL, $flags = OBS_SNMP_ALL)
{
  return snmpwalk_cache_oid($device, $oid, $array, $mib, $mibdir, $flags);
}

// CLEANME (deprecated) not used anymore
function snmp_parser_quote($m)
{
  return str_replace(array('.',' '),
    array('PLACEHOLDER-DOT', 'PLACEHOLDER-SPACE'), $m[1]);
}

// CLEANME (deprecated) not used anymore
function snmp_parser_unquote($str)
{
  return str_replace(array('PLACEHOLDER-DOT', 'PLACEHOLDER-SPACE', 'PLACEHOLDER-ESCAPED-QUOTE'),
    array('.',' ','"'), $str);
}

// CLEANME (deprecated) not used anymore
function ascii_to_oid($string)
{
  return snmp_string_to_oid($string);
}

// CLEANME (deprecated) not used anymore
function string_to_oid($string)
{
  return snmp_string_to_oid($string);
}

// EOF
