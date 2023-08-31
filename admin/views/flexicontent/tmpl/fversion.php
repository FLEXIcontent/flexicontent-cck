<?php
/**
 * @package         FLEXIcontent
 * @version         3.3
 *
 * @author          Emmanuel Danan, Georgios Papadakis, Yannick Berges, others, see contributor page
 * @link            https://flexicontent.org
 * @copyright       Copyright © 2019, FLEXIcontent team, All Rights Reserved
 * @license         http://www.gnu.org/licenses/gpl-2.0.html GNU/GPL
 */

defined( '_JEXEC' ) or die( 'Restricted access' );
$app = JFactory::getApplication();
$template = $app->getTemplate();

if ( $this->check[ 'connect' ] == 0 ) {
?>
  <div class="container">
    <h3><?php echo JText::_('FLEXI_VERSION'); ?></h3>
    <div class="alert alert-warning">
      <?php echo JText::_('FLEXI_CONNECTION_FAILED'); ?>
    </div>
  </div>
<?php
} elseif ( $this->check[ 'enabled' ] == 1 ) {
?>
  <div class="container">
    <div class="row g-0">
      <div class="col">
        <?php
      if ( $this->check[ 'current' ] == 0 ) {
        echo '<div class="alert alert-success"><i class="fas fa-check-circle me-2"></i>' . JText::_( 'FLEXI_LATEST_VERSION_INSTALLED' ) . '</div>';
      } elseif ( $this->check[ 'current' ] == -1 ) {
        echo '<div class="alert alert-info"><i class="fas fa-exclamation-circle me-2"></i>' . JText::_( 'FLEXI_OLD_VERSION_INSTALLED' ) . '</div>';
      } else {
        echo '<div class="alert alert-info"><i class="fas fa-exclamation-circle me-2"></i>' . JText::_( 'You have installed a newer version than the latest officially stable version' ) . '</div>';
      }
        ?>
      </div>
    </div>
    <div class="row g-0">
      <div class="col-5 border-top pt-3 pb-3 pe-2">
        <strong><?php echo JText::_('FLEXI_LATEST_VERSION') . ':'; ?></strong>
      </div>
      <div class="col-7 border-top pt-3 pb-3">
        <span class="badge badge-success"><?php echo $this->check['version']; ?></span>
        <strong><?php echo JText::_('FLEXI_RELEASED_DATE'); ?></strong>: <?php echo $this->check['released']; ?>
      </div>
    </div>
    <div class="row g-0">
      <div class="col-5 border-top pt-3 pb-3 pe-2">
        <strong><?php echo JText::_('FLEXI_INSTALLED_VERSION'); ?></strong>
      </div>
      <div class="col-7 border-top pt-2 pb-2">
        <span class="badge <?php echo $this->check['current']==-1 ? 'badge-warning' : ($this->check['current']==0 ? 'badge-success' : ''); ?>"><?php echo $this->check['current_version']; ?></span>
        <strong><?php echo JText::_('FLEXI_RELEASED_DATE'); ?></strong>: <?php echo $this->check['current_creationDate']; ?>
      </div>
    </div>
  </div>
<?php
}
?>