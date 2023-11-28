<?php
defined('_JEXEC') or die('Restricted access');

if (!empty($this->placementMsgs))
{
	foreach($this->placementMsgs as $msg_type => $msgs)
	{
		foreach ($msgs as $msg) echo sprintf( $alert_box, ' style="" ', $msg_type, '', $msg);
	}
}

$submit_msg = $approval_msg = '';


/**
 * A custom message about submitting new Content via configuration parameter per item type
 */
if ($isnew && $submit_message)
{
	$submit_msg = sprintf( $info_box, 'id="fc_submit_msg"', 'note', 'fc-nobgimage', JText::_($submit_message) );
}


/**
 * (Frontend only) Autopublishing a new item regardless of publish privilege via an override in submit menu item,
 * use a menu item specific message if this is set, or notify user of autopublishing with a default message
 */
if ($is_autopublished)
{
	$approval_msg = $this->params->get('autopublished_message')
		? $this->params->get('autopublished_message')
		:  JText::_( 'FLEXI_CONTENT_WILL_BE_AUTOPUBLISHED' . ($isSite ? '' : '_BE') ) ;
	$approval_msg = str_replace('_PUBLISH_UP_DAYS_INTERVAL_', $this->params->get('autopublished_up_interval') / (24*60), $approval_msg);
	$approval_msg = str_replace('_PUBLISH_DOWN_DAYS_INTERVAL_', $this->params->get('autopublished_up_interval') / (24*60), $approval_msg);
}

/**
 * Current user does not have general publish privilege, aka new/existing items will surely go through approval/reviewal process
 */
elseif ($approval_warning_inform)
{
	if (!$this->perms['canpublish'])
	{
		if ($isnew)
		{
			$approval_msg = JText::_( $this->params->get('document_approval_msg' . $CFGsfx, 'FLEXI_REQUIRES_DOCUMENT_APPROVAL') ) ;
		}
		elseif ($use_versioning)
		{
			$approval_msg = JText::_( $this->params->get('version_reviewal_msg' . $CFGsfx, 'FLEXI_REQUIRES_VERSION_REVIEWAL') ) ;
		}
		else
		{
			$approval_msg = JText::_( $this->params->get('changes_applied_immediately_msg' . $CFGsfx, 'FLEXI_CHANGES_APPLIED_IMMEDIATELY') ) ;
		}
	}

	/**
	 * Have general publish privilege but may not have privilege if item is assigned to specific category or is of a specific type
	 * !!! Add this only to FRONTEND (as it maybe nuisance in backend)
	 */
	elseif ($isSite)
	{
		if ($isnew)
		{
			$approval_msg = JText::_( $this->params->get('mr_document_approval_msg' . $CFGsfx, 'FLEXI_MIGHT_REQUIRE_DOCUMENT_APPROVAL') ) ;
		}
		elseif ($use_versioning)
		{
			$approval_msg = JText::_( $this->params->get('version_reviewal_msg' . $CFGsfx, 'FLEXI_MIGHT_REQUIRE_VERSION_REVIEWAL') ) ;
		}
		else
		{
			$approval_msg = JText::_( $this->params->get('changes_applied_immediately_msg' . $CFGsfx, 'FLEXI_CHANGES_APPLIED_IMMEDIATELY') ) ;
		}
	}
}


/**
 * Display messages to system messages notification area
 */
if ($submit_msg)
{
	$app->enqueueMessage($submit_msg, 'info');
}
if ($approval_msg)
{
	$app->enqueueMessage($approval_msg, 'info');
}

