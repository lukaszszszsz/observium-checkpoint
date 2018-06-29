<?php

/**
 * Observium
 *
 *   This file is part of Observium.
 *
 *   These functions perform rewrites on strings and numbers.
 *
 * @package    observium
 * @subpackage cache
 * @author     Adam Armstrong <adama@observium.org>
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2017 Observium Limited
 *
 */

// Please see phpFastCache documentation and examples here:
// https://github.com/PHPSocialNetwork/phpfastcache/wiki
// https://github.com/PHPSocialNetwork/phpfastcache/tree/final/examples
// NOTE, static functions used only for compatibility with older php versions (5.4 and less).
//       For more cache functionality needed OOP style for use..

if (version_compare(PHP_VERSION, '5.5.0', '<'))
{
  // Disable phpFastCache 5.x for PHP less than 5.5, since it unsupported
  $config['cache']['enable'] = FALSE;
}

/**
 * Cache functions.
 * Leave it before initialize phpFastCache, since here used check for php version.
 */

/**
 * Prepend cache key with username/level
 *
 * @param string $key Identificator name
 * @param boolean $global Set to TRUE for do not prepend keys
 * @return string Prepended key identificator (ie 'data' -> 'mysql|mike|10|data')
 */
function get_cache_key($key, $global = FALSE)
{
  if ($global || is_cli()) { return $key; } // CLI used global key

  if ($_SESSION['authenticated'])
  {
    if ($_SESSION['userlevel'] >= 10)
    {
      // Use common cache for Global Administrators
      $key = '__admin|' . $key;
    } else {
      // All other users use unique keys!
      $key = $_SESSION['auth_mechanism'] . '|' . $_SESSION['username'] . '|' . $_SESSION['userlevel'] . '|' . $key;
    }
  } else {
    // Just "protect" anonymous requests from read/write to global cache
    $key = '__anonymous|' . $key;
  }

  return $key;
}

/**
 * Call to getItem() method for retrieve an Item for cached object
 *
 * @param string $key Item identifier (key), for WUI key auto prepended with current user identificator (by get_cache_key())
 * @return object Object with cache Item
 */
function get_cache_item($key)
{
  if ($GLOBALS[OBS_CACHE_LINK] === (object)$GLOBALS[OBS_CACHE_LINK])
  {
    $observium_cache = $GLOBALS[OBS_CACHE_LINK];
  } else {
    // Cache not enabled
    return;
  }

  $observium_cache_key  = get_cache_key($key); // User specific key

  return $observium_cache->getItem($observium_cache_key);
}

/**
 * Call to isHit() method for check if your cache Item exists and is still valid
 *
 * @param object $item Cache Item object
 * @return bool TRUE if cache exist and not expired
 */
function ishit_cache_item($item)
{
  if ($GLOBALS[OBS_CACHE_LINK] !== (object)$GLOBALS[OBS_CACHE_LINK])
  {
    // Cache not enabled
    return;
  }

  return $item->isHit();
}

/**
 * Call to get() method for retrieve cache data for used Item
 *
 * @param object $item Cache Item object
 * @return mixed Cached data
 */
function get_cache_data($item)
{
  if ($GLOBALS[OBS_CACHE_LINK] !== (object)$GLOBALS[OBS_CACHE_LINK])
  {
    // Cache not enabled
    return;
  }

  $start = microtime(TRUE);
  $data = $item->get();
  $cache_time = microtime(TRUE) - $start;

  if (OBS_DEBUG || OBS_CACHE_DEBUG)
  {
    print_warning('<span class="text-success">READ FROM CACHE</span> // TTL: '.$item->getTtl().'s // Expiration: <strong>' . $item->getExpirationDate()->format(Datetime::RFC2822) . '</strong><br />' .
                  'Key: <strong>' . $item->getKey() . '</strong> // Tags: <strong>' . $item->getTagsAsString() . '</strong><br />' .
                  'Driver: <strong>'.str_replace(array('phpFastCache\\Drivers\\', '\\Item'), '', get_class($item)).'</strong> // Read time: ' . sprintf("%.7f", $cache_time) . ' ms');
    //print_vars($item->getTags());
  }
  return $data;
}

/**
 * Call to set() and save() methods for store data in cache
 *
 * @param object $item Cache Item object
 * @param mixed $data Data for store in cache
 * @param array $params Additional options for cache entry
 * @return int Unix timestamp when cache item will expired
 */
function set_cache_item($item, $data, $params = array())
{
  if ($GLOBALS[OBS_CACHE_LINK] === (object)$GLOBALS[OBS_CACHE_LINK])
  {
    $observium_cache = $GLOBALS[OBS_CACHE_LINK];
  } else {
    // Cache not enabled
    return;
  }

  // Item tags (for search/cleanup cache later)
  if (is_cli())
  {
    $tags = array('__cli');
  } else {
    $tags = array('__wui');
    if ($_SESSION['authenticated'])
    {
      if ($_SESSION['userlevel'] >= 10)
      {
        $tags[] = '__admin';
      } else {
        $tags[] = '__username=' . $_SESSION['username'];
      }
    } else {
      $tags[] = '__anonymous';
    }
  }
  if (isset($params['tags']))
  {
    $tags = array_merge($tags, (array)$params['tags']);
  }

  // TTL for cache entry in seconds
  if (isset($params['ttl']) && is_numeric($params['ttl']))
  {
    $ttl = intval($params['ttl']);
  }
  else if (is_numeric($_GLOBALS['config']['cache']['ttl']))
  {
    $ttl = $_GLOBALS['config']['cache']['ttl'];
  } else {
    // Default TTL (5 min)
    $ttl = 300;
  }

  $start = microtime(TRUE);
  // Add data to cache
  $item->set($data);
  // Set expiration TTL
  $item->expiresAfter($ttl);
  // Add tags
  $item->addTags($tags);
  // Commit
  $observium_cache->save($item);
  $cache_time = microtime(TRUE) - $start;

  if (OBS_DEBUG || OBS_CACHE_DEBUG)
  {
    print_warning('<span class="text-info">WROTE TO CACHE</span> // TTL: '.$item->getTtl().'s // Expiration: <strong>' . $item->getExpirationDate()->format(Datetime::RFC2822) . '</strong><br />' .
                  'Key: <strong>' . $item->getKey() . '</strong> // Tags: <strong>' . $item->getTagsAsString() . '</strong><br />' .
                  'Driver: <strong>'.str_replace(array('phpFastCache\\Drivers\\', '\\Item'), '', get_class($item)).'</strong> // Write time: ' . sprintf("%.7f", $cache_time) . ' ms');
    //print_vars($item->getTags());
  }
  return $item->getExpirationDate()->getTimestamp();
}

/**
 * Get cache Items by single tag
 *
 * @param string $tag Tag name for Get Items
 */
function get_cache_items($tag)
{
  if ($GLOBALS[OBS_CACHE_LINK] === (object)$GLOBALS[OBS_CACHE_LINK])
  {
    $observium_cache = $GLOBALS[OBS_CACHE_LINK];
  } else {
    // Cache not enabled
    return;
  }

  return $observium_cache->getItemsByTag($tag);
}

/**
 * Delete cache Items by tags.
 * See session_logout() for example.
 *
 * @param array $tags Array of tags for deletion
 */
function del_cache_items($tags)
{
  if ($GLOBALS[OBS_CACHE_LINK] === (object)$GLOBALS[OBS_CACHE_LINK])
  {
    $observium_cache = $GLOBALS[OBS_CACHE_LINK];
  } else {
    // Cache not enabled
    return;
  }

  return $observium_cache->deleteItemsByTags($tags);
}

/**
 * Delete expired Items.
 * Used "workaround" as described here:
 * https://github.com/PHPSocialNetwork/phpfastcache/issues/413#issuecomment-270692658
 *
 * @param array $tags Array of tags for clear
 * @return int Unixtime when last expired cache cleared
 */
function del_cache_expired($tag = '')
{
  if ($GLOBALS[OBS_CACHE_LINK] === (object)$GLOBALS[OBS_CACHE_LINK])
  {
    $observium_cache = $GLOBALS[OBS_CACHE_LINK];
  } else {
    // Cache not enabled
    return;
  }

  $item = $observium_cache->getItem('__cache_last_clear_expired');
  if (!$item->isHit())
  {
    // Here our default tags, see set_cache_item()
    if (empty($tag))
    {
      if (is_cli())
      {
        $tag = '__cli';
      } else {
        $tag = '__wui';
      }
    }

    // Touch items for clear expired
    get_cache_items($tag);

    $clear_expired = time();

    // Store last clean time for 24 hours
    $item->set($clear_expired)->expiresAfter(86400);
    // Commit
    $observium_cache->save($item);

    if (OBS_DEBUG || OBS_CACHE_DEBUG)
    {
      print_warning('<span class="text-success">CLEAR EXPIRED CACHE</span> // Time: <strong>' . format_unixtime($clear_expired) . '</strong>');
    }
  } else {
    $clear_expired = $item->get();
    if (OBS_DEBUG || OBS_CACHE_DEBUG)
    {
      print_warning('<span class="text-info">LAST CLEAR CACHE TIME</span> // Time: <strong>' . format_unixtime($clear_expired) . '</strong>');
    }
  }

  return $clear_expired;
}

/**
 * Add clear cache attrib, this will request for clering cache in next request.
 *
 * @param string $target Clear cache target: wui or cli (default if wui)
 */
/*
function set_cache_clear($target = 'wui')
{
  if (OBS_DEBUG || OBS_CACHE_DEBUG)
  {
    print_error('<span class="text-warning">CACHE CLEAR SET.</span> Cache clear set.');
  }
  if (!$GLOBALS['config']['cache']['enable'])
  {
    // Cache not enabled
    return;
  }

  switch (strtolower($target))
  {
    case 'cli':
      // Add clear CLI cache attrib. Currently not used
      set_obs_attrib('cache_cli_clear', get_request_id());
      break;
    default:
      // Add clear WUI cache attrib
      set_obs_attrib('cache_wui_clear', get_request_id());
  }
}
*/

/**
 * Return total cache size in bytes.
 * Note, this is not user/session specific size, but total for cache system
 *
 * @return int Total cache size in bytes
 */
function get_cache_stats()
{
  if ($GLOBALS[OBS_CACHE_LINK] === (object)$GLOBALS[OBS_CACHE_LINK])
  {
    $observium_cache = $GLOBALS[OBS_CACHE_LINK];
  } else {
    // Cache not enabled
    return array('enabled' => FALSE, 'size' => 0);
  }

  try
  {
    //$_s = $observium_cache->getStats();
    $size = $observium_cache->getStats()->getSize();
  }
  catch (Exception $e)
  {
    $size = 0;
  }
  //r($_s->getInfo());
  $stats = array('enabled' => TRUE,
                 'driver'  => str_replace(array('phpFastCache\\Drivers\\', '\\Driver'), '', get_class($observium_cache)),
                 'size'    => $size,
                );

  return $stats;
}

/////////////////////////////////////////////////////////
//  NO FUNCTION DEFINITIONS FOR CACHE AFTER THIS LINE! //
/////////////////////////////////////////////////////////
//               YES, THAT MEANS YOU                   //
/////////////////////////////////////////////////////////

define('OBS_CACHE_DEBUG', isset($_SERVER['PATH_INFO']) && strpos($_SERVER['PATH_INFO'], 'cache_info') !== FALSE);

// Do not load phpFastCache classes if caching mechanism not enabled or not supported
if (!$config['cache']['enable'])
{
  if (OBS_DEBUG || OBS_CACHE_DEBUG)
  {
    if (version_compare(PHP_VERSION, '5.5.0', '<'))
    {
      print_error('<span class="text-danger">CACHE DISABLED.</span> You use too old php version, see <a href="' . OBSERVIUM_URL . '/docs/software_requirements/">minimum software requirements</a>.');
    } else {
      print_error('<span class="text-danger">CACHE DISABLED.</span> Disabled in config.');
    }
  }
  return;
}

/**
 * Temporary hardcoded caching in files, will improved later with other providers
 */

define('OBS_CACHE_LINK', 'observium_cache'); // Variable name for call to cache class

// Call the phpFastCache
use phpFastCache\CacheManager;
use phpFastCache\Core\phpFastCache;

// Setup File Path and other basic options
CacheManager::setDefaultConfig(array(
  'path'                => $config['cache_dir'],
  'securityKey'         => is_cli() ? 'cli' : 'wui', // do not use $_SERVER['hostname'] as key
  'ignoreSymfonyNotice' => TRUE,
));

$cache_driver = 'files'; // If other drivers not detected, use files as fallback
if (str_istarts($config['cache']['driver'], 'auto'))
{
  // Detect avialable drivers,
  // NOTE order from fastest to slowest!
  //$detect_driver = array('zendshm', 'apcu', 'xcache', 'sqlite', 'files');
  $detect_driver = array('zendshm', 'apcu', 'sqlite', 'files');
} else {
  $detect_driver = array(strtolower($config['cache']['driver']));
}
// Basic detect if extension/driver available
$cache_driver = 'files';
foreach ($detect_driver as $entry)
{
  switch($entry)
  {
    case 'zendshm':
      if (extension_loaded('Zend Data Cache') && function_exists('zend_shm_cache_store'))
      {
        $cache_driver = 'zendshm';
        break 2;
      }
      break;
    case '!memcached':
      // Also need connection test
      try
      {
        $mc = new Memcached();
      } catch (Exception $e) {}
      if (class_exists('Memcached'))
      {
        $cache_driver = 'memcached';
        break 2;
      }
      break;
    case 'apcu':
      if (extension_loaded('apcu') && ini_get('apc.enabled'))
      {
        $cache_driver = 'apcu';
        break 2;
      }
      break;
    /* XCache RIP
    case 'xcache':
      if (extension_loaded('xcache') && function_exists('xcache_get'))
      {
        $cache_driver = 'xcache';
        break 2;
      }
      break;
    */
    case 'sqlite':
      if (extension_loaded('pdo_sqlite'))
      {
        $cache_driver = 'sqlite';
        break 2;
      }
      break;
    case 'files':
    default:
      //$cache_driver = 'files';
      break;
  }
}

switch($cache_driver)
{
  case 'sqlite':
    // Create base cache dir if not exist
    if (!is_dir($config['cache_dir']))
    {
      mkdir($config['cache_dir'], 0777, TRUE);
      chmod($config['cache_dir'], 0777);
    }
    // Do not break here!

  case 'zendshm':
  case 'memcached':
  case 'apcu':
  //case 'xcache':
    try
    {
      $GLOBALS[OBS_CACHE_LINK] = CacheManager::getInstance($cache_driver);
    }
    catch (Exception $e)
    {
      print_debug('Cache driver '.ucfirst($cache_driver).' not functional. Caching disabled!');
      $GLOBALS[OBS_CACHE_LINK] = CacheManager::getInstance('Devfalse'); // disable caching
    }
    break;

  case 'files':
  default:
    // Create base cache dir if not exist
    if (!is_dir($config['cache_dir']))
    {
      mkdir($config['cache_dir'], 0777, TRUE);
      chmod($config['cache_dir'], 0777);
    }

    try
    {
      $GLOBALS[OBS_CACHE_LINK] = CacheManager::getInstance('files');
    }
    catch (Exeption $e)
    {
      print_debug('Cache driver Files not functional. Caching disabled!');
      $GLOBALS[OBS_CACHE_LINK] = CacheManager::getInstance('Devfalse'); // disable caching
    }
}

// Clear cache totally by requested attrib
if (is_cli())
{
  if (get_obs_attrib('cache_cli_clear'))
  {
    $GLOBALS[OBS_CACHE_LINK]->clear();
    del_obs_attrib('cache_cli_clear');
    if (OBS_DEBUG || OBS_CACHE_DEBUG)
    {
      print_error('<span class="text-warning">CACHE CLEARED.</span> Cache clear requested.');
    }
  }
}
else if ($request_id = get_obs_attrib('cache_wui_clear'))
{
  if ($request_id !== get_request_id()) // Skip cache clear inside same page request
  {
    $GLOBALS[OBS_CACHE_LINK]->clear();
    del_obs_attrib('cache_wui_clear');
    if (OBS_DEBUG || OBS_CACHE_DEBUG)
    {
      print_error('<span class="text-warning">CACHE CLEARED.</span> Cache clear requested.');
    }
  }
}

// Clean
unset($detect_driver, $cache_driver, $entry);

// EOF
