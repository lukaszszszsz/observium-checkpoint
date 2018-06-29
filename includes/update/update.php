<?php

/* Observium Network Management and Monitoring System
 *
 * @package    observium
 * @subpackage updater
 * @author     Adam Armstrong <adama@observium.org>
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2016 Observium Limited
 *
 */

if (!defined('OBS_DEBUG'))
{
  // Direct call not allowed, use
  echo("WARNING. Direct call to this script is no longer supported, please use './discovery.php -u' from main observium directory.\n");
  exit(2);

  /*
  include_once("includes/defaults.inc.php");
  include_once("config.php");

  $options = getopt("d");

  include_once("includes/definitions.inc.php");
  include("includes/functions.inc.php");
  */
}

/**
 * Tests with initial db schema install (252):
 *
 * 5.5.46, no strict:
 *  sql_mode
 *  series  2min 14.535s
 *  install 1min  4.387s
 *
 * 5.6.27, no strict:
 *  sql_mode NO_ENGINE_SUBSTITUTION
 *  series  8min 14.076s
 *  install 4min  2.166s
 *
 * 5.7.9,    strict:
 *  sql_mode ONLY_FULL_GROUP_BY,STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_AUTO_CREATE_USER,NO_ENGINE_SUBSTITUTION
 *  series  5min 47.310s
 *  install 3min 14.853s
 */

$db_rev = get_db_version();
// DB schema not installed
$schema_insert = ($db_rev == 0 && !dbQuery('SELECT 1 FROM `devices` LIMIT 1;'));

// Try to use mysql cmd for insert initial db schema
if ($schema_insert && is_file($config['install_dir'] . '/update/db_schema_mysql.sql'))
{
  if (is_executable('/usr/bin/mysql'))
  {
    // Default path
    $mysql_cmd = '/usr/bin/mysql';
  } else {
    // Try to find mysql executable in search paths
    $mysql_cmd = external_exec('which mysql');
  }

  if (is_executable($mysql_cmd))
  {
    // If mysql executable exist (or find) use insert initial schema
    $cmd = $mysql_cmd .
            ' -u' . escapeshellarg($config['db_user']) .
            ' -p' . escapeshellarg($config['db_pass']) .
            ' -h' . escapeshellarg($config['db_host']) .
            ' -D ' .escapeshellarg($config['db_name']) .
            ' < ' . escapeshellarg($config['install_dir'] . '/update/db_schema_mysql.sql');

    echo('Install initial database schema ...');
    external_exec($cmd);
    $mysql_status = $GLOBALS['exec_status']['exitcode'] === 0;

    // Recheck if initial schema installed
    $db_rev = get_db_version();
    $schema_insert = ($db_rev == 0 && !dbQuery('SELECT 1 FROM `devices` LIMIT 1;'));

    if ($mysql_status)
    {
      echo(' done.' . PHP_EOL);
    } else {
      echo(' FALSE.' . PHP_EOL);
      if (!$schema_insert)
      {
        print_error("Error during installation initial schema, but tables exist. Run update again."); // Not should happen NEVER
        exit(2);
      }
    }
  }
}

if ($db_rev > 272) // observium_processes added in db version 272
{
  // Check if discovery -u already running
  $pid_info = check_process_run(-1);
  if ($pid_info)
  {
    // Process ID exist in DB
    print_message("%rAnother ".$pid_info['process_name']." process (PID: ".$pid_info['PID'].", UID: ".$pid_info['UID'].", STARTED: ".$pid_info['STARTED'].") already running for update.%n", 'color');

    // Not sure what better, return and process all other discovery operations
    // or complete stop discovery
    // (stop can produce 6 hour latency for devices discovery, but return can broke all discovery devices after update)
    return FALSE;
    //exit(2);
  }
  add_process_info(-1); // Store process info
}

$updating = 0;

// Only numeric filenames (001.sql, 013.php)
$sql_regexp = "/^\d+\.sql$/";
$php_regexp = "/^\d+\.php$/";

if ($handle = opendir($config['install_dir'] . '/update'))
{
  while (false !== ($file = readdir($handle)))
  {
    if (filetype($config['install_dir'] . '/update/' . $file) == 'file' && (preg_match($sql_regexp, $file) || preg_match($php_regexp, $file)))
    {
      $filelist[] = $file;
    }
  }
  closedir($handle);
}

sort($filelist);
//print_vars($filelist);

foreach ($filelist as $file)
{
  $filepath = $config['install_dir'] . '/update/' . $file;
  list($filename, $extension) = explode('.', $file, 2);
  if ($filename > $db_rev)
  {
    if (!$updating)
    {
      echo('-- Updating database/file schema' . PHP_EOL);
    }

    $error_ignore = FALSE; // Stop update if errors not ignored

    if ($extension == "php")
    {
      echo(sprintf("%03d",$db_rev) . " -> " . sprintf("%03d", $filename) . " # (php) ");

      $start = time();
      if (include_wrapper($filepath))
      {
        // File included OK, update dbSchema
        $schema_status = set_db_version($filename, $schema_insert);
        if ($schema_insert && $schema_status !== FALSE)
        {
          // dbSchema inserted, now only update
          $schema_insert = FALSE;
        }
        $update_time = formatUptime(time() - $start);
        echo(" Done ($update_time)." . PHP_EOL);
      } else {
        // Critical errors, stop update
        logfile('update-errors.log', "====== Schema update " . sprintf("%03d", $db_rev) . " -> " . sprintf("%03d", $filename) . " ==============");
        logfile('update-errors.log', "Error: Could not load file $filepath!");
        exit(1);
      }
    }
    else if ($extension == "sql")
    {
      echo(sprintf("%03d",$db_rev) . " -> " . sprintf("%03d",$filename) . " # (db) ");

      $err   = 0;
      $start = time();

      if ($fd = @fopen($filepath, 'r'))
      {
        $data = fread($fd,4096);
        while (!feof($fd))
        {
          $data .= fread($fd, 4096);
        }
        fclose($fd);

        foreach (explode("\n", $data) as $line)
        {
          if (trim($line))
          {
            // Skip comments
            if ($line[0] == '#' || $line[0] == '-' || $line[0] == '/')
            {
              if ((strpos($line, 'ERROR_IGNORE') !== FALSE) ||
                  (strpos($line, 'IGNORE_ERROR') !== FALSE))
              {
                $error_ignore = TRUE;
              }
              else if (strpos($line, 'NOTE') !== FALSE)
              {
                list(, $note) = explode('NOTE', $line, 2);
                echo('('.trim($note).')');
              }
              continue;
            }

            print_debug($line);

            $update = dbQuery($line);
            if (!$update)
            {
              $error_no  = dbErrorNo();
              $error_msg = "($error_no) " . dbError();
              if ($error_no >= 2000) // || !$error_ignore)
              {
                // Critical errors, stop update
                echo(" stopped. Critical error: " . $error_msg . PHP_EOL);
                // http://dev.mysql.com/doc/refman/5.6/en/error-messages-client.html
                logfile('update-errors.log', "====== Schema update " . sprintf("%03d", $db_rev) . " -> " . sprintf("%03d", $filename) . " ==============");
                logfile('update-errors.log', "Query: " . $line);
                logfile('update-errors.log', "Error: " . $error_msg);
                del_process_info(-1); // Remove process info
                exit(1);
              } else {
                echo('F');
                $err++;
                $errors[] = array('query' => $line, 'error' => $error_msg);
                print_debug($error_msg);
              }
            } else {
              echo('.');
            }

          }
        }

        $update_time = formatUptime(time() - $start);
        if ($db_rev < 1)
        {
          echo(" Done ($update_time)." . PHP_EOL);
        }
        else if ($err)
        {
          echo(" Done ($update_time, $err errors)." . PHP_EOL);
          logfile('update-errors.log', "====== Schema update " . sprintf("%03d", $db_rev) . " -> " . sprintf("%03d", $filename) . " ==============");
          foreach ($errors as $error)
          {
            logfile('update-errors.log', "Query: " . $error['query']);
            logfile('update-errors.log', "Error: " . $error['error']);
          }
          unset($errors);
        } else {
          echo(" Done ($update_time)." . PHP_EOL);
        }

        // SQL update done, update dbSchema
        $schema_status = set_db_version($filename, $schema_insert);
        if ($schema_insert && $schema_status !== FALSE)
        {
          // dbSchema inserted, now only update
          $schema_insert = FALSE;
        }
      } else {
        echo(' Could not open file!' . PHP_EOL);
        // Critical errors, stop update
        logfile('update-errors.log', "====== Schema update " . sprintf("%03d", $db_rev) . " -> " . sprintf("%03d", $filename) . " ==============");
        logfile('update-errors.log', "Error: Could not open file $filepath!");
        del_process_info(-1); // Remove process info
        exit(1);
      }
    }

    $updating++;
    $db_rev = $filename;
  }
}

if ($updating)
{
//  $GLOBALS['cache']['db_version'] = $db_rev; // Cache new db version
//  if ($schema_insert)
//  {
//    dbInsert(array('attrib_type' => 'dbSchema', 'attrib_value' => $db_rev), 'observium_attribs');
//  } else {
//    dbUpdate(array('attrib_value' => $db_rev), 'observium_attribs', 'attrib_type = ?', array('dbSchema'));
//  }
  echo('-- Done.' . PHP_EOL);
} else {
  echo('-- Database is up to date.' . PHP_EOL);
}

// Clean
del_process_info(-1); // Remove process info

// EOF
