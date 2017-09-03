<?php
defined('JPATH_BASE') or die;

extract($displayData);

// Get Favourites field configuration (if FIELD is empty then retrieve it)
$favs_field = $field ?: reset(FlexicontentFields::getFieldsByIds(array(12)));
$favs_field->parameters = new JRegistry($favs_field->attribs);

$users_counter = (int) $favs_field->parameters->get('display_favoured_usercount', 0);
$users_list_type = (int) $favs_field->parameters->get('display_favoured_userlist', 0);
$users_list_limit = (int) $favs_field->parameters->get('display_favoured_max', 12);

// No user favouring the item yet
if (!$favourites) return;

// Nothing to do if all options disabled
if (!$users_counter && !$users_list_type)  return;

$userlist = '';
if ( $users_list_type )
{
  $uname = $users_list_type==1 ? "u.username" : "u.name";

  $db	= JFactory::getDbo();
  $query = 'SELECT '.($users_list_type==1 ? "u.username" : "u.name")
    .' FROM #__flexicontent_favourites AS ff'
    .' LEFT JOIN #__users AS u ON u.id=ff.userid '
    .' WHERE ff.itemid=' . $item->id . ' AND type = 0';
  $db->setQuery($query);
  $favusers = $db->loadColumn();

  if (is_array($favusers) && count($favusers))
  {
    $count = 0;
    foreach($favusers as $favuser)
    {
      $_list[] = $favuser;
      if ($count++ >= $users_list_limit) break;
    }
    $userlist = ($users_counter ? ': ' : '') . implode(', ', $_list) . (count($favusers) > $users_list_limit ? ' ...' : '');
  }
}

echo '
  <div class="fc-mssg fc-info fc-iblock fc-nobgimage fcfavs-subscribers-count">
    ' . ($users_counter ? JText::_('FLEXI_TOTAL').': '.$favourites.' '.JText::_('FLEXI_USERS') : '') . '
    ' . $userlist . '
  </div>';
