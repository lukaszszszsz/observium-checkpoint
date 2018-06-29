<?php

/**
 * Observium Network Management and Monitoring System
 * Copyright (C) 2006-2015, Adam Armstrong - http://www.observium.org
 *
 * @package    observium
 * @subpackage webui
 * @author     Adam Armstrong <adama@observium.org>
 * @copyright  (C) 2006-2013 Adam Armstrong, (C) 2013-2017 Observium Limited
 *
 */

// Contact display and editing page.

if ($_SESSION['userlevel'] < 7)
{
  print_error_permission();
  return;
}

include($config['html_dir'].'/includes/alerting-navbar.inc.php');

include($config['html_dir'].'/includes/contacts-navbar.inc.php');

// Begin Actions
$readonly = $_SESSION['userlevel'] < 10; // Currently edit allowed only for Admins





if (isset($vars['action']) && $_SESSION['userlevel'] = 10)
{
  switch ($vars['action'])
  {
    case 'add_contact':
      // Only proceed if the contact_method is valid in our transports array
      if (is_array($config['alerts']['transports'][$vars['contact_method']]))
      {
        foreach ($config['alerts']['transports'][$vars['contact_method']]['parameters'] as $section => $parameters)
        {
          foreach ($parameters as $parameter => $description)
          {
            if (isset($vars['contact_' . $vars['contact_method'] . '_' . $parameter]))
            {
              $endpoint_data[$parameter] = $vars['contact_' . $vars['contact_method'] . '_' . $parameter];
            }
          }
        }

        dbInsert('alert_contacts', array('contact_descr' => $vars['contact_descr'], 'contact_endpoint' => json_encode($endpoint_data), 'contact_method' => $vars['contact_method']));
      }
      break;

    case 'edit_contact':
      break;

    case 'delete_contact':

      if (in_array($vars['confirm_'.$vars['contact_id']], array('1', 'on', 'yes', 'confirm')))
      {
        $rows_deleted  = dbDelete('alert_contacts',       '`contact_id` = ?', array($vars['contact_id']));
        $rows_deleted += dbDelete('alert_contacts_assoc', '`contact_id` = ?', array($vars['contact_id']));

        if ($rows_deleted)
        {
          print_success('Deleted contact and all associations ('.$vars['contact_id'].')');
        }
      }
      unset($vars['contact_id']);
      break;

  }
}

// End Actions
?>

<div class="row">
<div class="col-sm-12">

<?php

// FIXME. Show for anyone > 5 (also for non-ADMIN) and any contacts?
$contacts = dbFetchRows('SELECT * FROM `alert_contacts` WHERE 1');
if (count($contacts))
{
  // We have contacts, print the table.
  echo generate_box_open();
?>

<table class="table table-condensed table-striped table-rounded table-hover">
  <thead>
    <tr>
    <th style="width: 1px"></th>
    <th style="width: 50px">Id</th>
    <th style="width: 100px">Method</th>
    <th style="width: 100px">Description</th>
    <th>Destination</th>
    <th style="width: 60px">Used</th>
    <th style="width: 70px">Status</th>
    <th style="width: 30px"></th>
    </tr>
  </thead>
  <tbody>

<?php

  $modals = '';

  foreach ($contacts as $contact)
  {
    $num_assocs = dbFetchCell("SELECT COUNT(*) FROM `alert_contacts_assoc` WHERE `contact_id` = ?", array($contact['contact_id'])) + 0;

    if ($contact['contact_disabled'] == 1) { $disabled = ""; }

    // If we have "identifiers" set for this type of transport, use those to print a user friendly destination.
    // If we don't, just dump the JSON array as we don't have a better idea what to do right now.
    if (isset($config['alerts']['transports'][$contact['contact_method']]['identifiers']))
    {
      // Decode JSON for use below
      $contact['endpoint_variables'] = json_decode($contact['contact_endpoint'], TRUE);

      // Add all identifier strings to an array and implode them into the description variable
      // We can't just foreach the identifiers array as we don't know what section the variable is in
      foreach ($config['alerts']['transports'][$contact['contact_method']]['identifiers'] as $key)
      {
        foreach ($config['alerts']['transports'][$contact['contact_method']]['parameters'] as $section => $parameters)
        {
          if (isset($parameters[$key]) && isset($contact['endpoint_variables'][$key]))
          {
            $contact['endpoint_identifiers'][] = escape_html($parameters[$key]['description'] . ': ' . $contact['endpoint_variables'][$key]);
          }
        }
      }

      $contact['endpoint_descr'] = implode('<br />', $contact['endpoint_identifiers']);
    }
    else
    {
      $contact['endpoint_descr'] = escape_html($contact['contact_endpoint']);
    }

    echo '    <tr>';
    echo '      <td></td>';
    echo '      <td>'.$contact['contact_id'].'</td>';
    echo '      <td><span class="label">'.$config['alerts']['transports'][$contact['contact_method']]['name'].'</span></td>';
    echo '      <td class="text-nowrap">'.escape_html($contact['contact_descr']).'</td>';
    echo '      <td><a href="' . generate_url(array('page' => 'contact', 'contact_id' => $contact['contact_id'])) . '">' . $contact['endpoint_descr'] . '</a></td>';
    echo '      <td><span class="label label-primary">'.$num_assocs.'</span></td>';
    echo '      <td>' . ($contact['contact_disabled'] ?  '<span class="label label-error">disabled</span>' : '<span class="label label-success">enabled</span>') . '</td>';
    echo '      <td style="text-align: right;">';
    if ($_SESSION['userlevel'] >= 10)
    {
      echo '
      <div class="btn-group btn-group-xs" role="group" aria-label="Contact actions">
        <a class="btn btn-danger" role="group" title="Delete" href="#modal-contact_delete_' . $contact['contact_id'] . '" data-toggle="modal"><i class="icon-trash"></i></a>
      </div>';
    }
    echo '</td>';
    echo '    </tr>';

    /* now in defaults for generate_form_modal()
    $modal_args = array(
      //'hide'  => TRUE,
      //'fade'  => TRUE,
      //'role'  => 'dialog',
      //'class' => 'modal-md',
    );
    */

    $form = array('type'       => 'horizontal',
                  'userlevel'  => 10,          // Minimum user level for display form
                  'id'         => 'modal-contact_delete_'.$contact['contact_id'],
                  'title'      => 'Delete Contact "'   . $contact['contact_descr'] .
                                  '" (Id: '. $contact['contact_id'] . ', ' . $config['alerts']['transports'][$contact['contact_method']]['name'] . ')',
                  //'modal_args' => $modal_args, // modal specific options
                  //'help'      => 'This will delete the selected contact and any alert assocations.',
                  //'class'     => '', // Clean default box class (default for modals)
                  //'url'       => 'delhost/'
                  );
    //$form['fieldset']['body']   = array('class' => 'modal-body');   // Required this class for modal body!
    //$form['fieldset']['footer'] = array('class' => 'modal-footer'); // Required this class for modal footer!

    $form['row'][0]['contact_id'] = array(
                                    'type'        => 'hidden',
                                    'fieldset'    => 'body',
                                    'value'       => $contact['contact_id']);
    $form['row'][0]['action']     = array(
                                      'type'        => 'hidden',
                                      'fieldset'    => 'body',
                                      'value'       => 'delete_contact');

    $form['row'][6]['confirm_'.$contact['contact_id']] = array(
                                    'type'        => 'checkbox',
                                    'fieldset'    => 'body',
                                    //'offset'      => FALSE,
                                    'name'        => 'Confirm',
                                    'placeholder' => 'Yes, please delete this contact!',
                                    'onchange'    => "javascript: toggleAttrib('disabled', 'delete_button_".$contact['contact_id']."'); showDiv(!this.checked, 'warning_".$contact['contact_id']."_div');",
                                    'value'       => 'confirm');
    $form['row'][7]['warning_'.$contact['contact_id']] = array(
                                    'type'        => 'html',
                                    'fieldset'    => 'body',
                                    'html'        => '<h4 class="alert-heading"><i class="icon-warning-sign"></i> Warning!</h4>' .
                                                     ' Are you sure you want to delete this alert association?',
                                    'div_style'   => 'display: none', // hide initially
                                    'div_class'   => 'alert alert-warning');

    $form['row'][8]['close'] = array(
                                    'type'        => 'submit',
                                    'fieldset'    => 'footer',
                                    'div_class'   => '', // Clean default form-action class!
                                    'name'        => 'Close',
                                    'icon'        => '',
                                    'attribs'     => array('data-dismiss' => 'modal',  // dismiss modal
                                                           'aria-hidden'  => 'true')); // do not sent any value
    $form['row'][9]['delete_button_'.$contact['contact_id']] = array(
                                    'type'        => 'submit',
                                    'fieldset'    => 'footer',
                                    'div_class'   => '', // Clean default form-action class!
                                    'name'        => 'Delete Contact',
                                    'icon'        => 'icon-trash icon-white',
                                    //'right'       => TRUE,
                                    'class'       => 'btn-danger',
                                    'disabled'    => TRUE,
                                    'value'       => 'contact_delete');

    $modals .= generate_form_modal($form);
    unset($form);

  }

?>

  </tbody>
</table>

<?php

  echo generate_box_close();

  echo $modals;
  unset($modals);

} else {
  // We don't have contacts. Say so.
  print_warning("There are currently no contacts configured.");
}


?>
  </div> <!-- col-sm-12 -->
</div> <!-- row -->

<?php

// EOF
