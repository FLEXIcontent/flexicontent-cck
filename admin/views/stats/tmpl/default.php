<?php
/**
 * @version 1.5 stable $Id: default.php 1657 2013-03-25 11:31:45Z ggppdk $
 * @package Joomla
 * @subpackage FLEXIcontent
 * @copyright (C) 2009 Emmanuel Danan - www.vistamedia.fr
 * @license GNU/GPL v2
 * 
 * FLEXIcontent is a derivative work of the excellent QuickFAQ component
 * @copyright (C) 2008 Christoph Lukes
 * see www.schlu.net for more information
 *
 * FLEXIcontent is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

defined( '_JEXEC' ) or die( 'Restricted access' );

$ctrl_items = FLEXI_J16GE ? "task=items." : "controller=items&amp;task=";
$ctrl_users = FLEXI_J16GE ? "task=users." : "controller=users&amp;task=";
?>

<?php if (!empty( $this->sidebar)) : ?>
	<div id="j-sidebar-container" class="span2">
		<?php echo $this->sidebar; ?>
	</div>
	<div id="j-main-container" class="span10">
<?php else : ?>
	<div id="j-main-container">
<?php endif;?>

<?php
// BOF: Load echart libraries
if (file_exists(JPATH_COMPONENT.DS.'assets'.DS.'echarts') && file_exists(JPATH_COMPONENT.DS.'assets'.DS.'zrender')) :
?>
<script type="text/javascript">
    require.config({
        packages: [
            {
                name: 'echarts',
                location: 'components/com_flexicontent/assets/echarts',
                main: 'echarts'
            },
            {
                name: 'zrender',
                location: 'http://ecomfe.github.io/zrender/src',
                //location: '../../../zrender/src', // I don't know why it does not work with this
                main: 'zrender'
            }
        ]
    });
</script>
<?php 
endif;
// EOF: Load echart libraries
?>

<?php
echo FLEXI_J16GE ? JHtml::_('tabs.start') : $this->pane->startPane( 'stat-pane' );



echo FLEXI_J16GE ? JHtml::_('tabs.panel', JText::_( 'FLEXI_GENERAL_STATS' ), 'general' ) : $this->pane->startPanel( JText::_( 'FLEXI_GENERAL_STATS' ), 'general' ) ;
?>

<table border="0">
<tr><td>

	<div class="cssbox">
		<div class="cssbox_head">
			<h2><?php echo JText::_( 'FLEXI_GENERAL_STATS' ); ?></h2>
		</div>
		<div class="cssbox_body">
			<table class="adminlist  table table-hover table-striped">
				<thead>
					<tr>
						<th><?php echo JText::_( 'FLEXI_TYPE' ); ?></th>
						<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td>
							<?php echo JText::_( 'FLEXI_TOTAL_NR_ITEMS' ); ?>
						</td>
						<td align="center">
							<strong><?php echo $this->genstats[0]; ?></strong>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'FLEXI_TOTAL_NR_CATEGORIES' ); ?>
						</td>
						<td align="center">
							<strong><?php echo $this->genstats[1]; ?></strong>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'FLEXI_TOTAL_NR_TAGS' ); ?>
						</td>
						<td align="center">
							<strong><?php echo $this->genstats[2]; ?></strong>
						</td>
					</tr>
					<tr>
						<td>
							<?php echo JText::_( 'FLEXI_TOTAL_NR_FILES' ); ?>
						</td>
						<td align="center">
							<strong><?php echo $this->genstats[3]; ?></strong>
						</td>
					</tr>
				</tbody>
			</table>
		</div>
	</div>

</td><td>

	<div class="cssbox">
		<div class="cssbox_head">
			<h2><?php echo JText::_( 'FLEXI_MOST_POPULAR' ); ?></h2>
		</div>
		<div class="cssbox_body">
			<table class="adminlist  table table-hover table-striped">
				<thead>
					<tr>
						<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
						<th><?php echo JText::_( 'FLEXI_HITS' ); ?></th>
						<th><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$k = 0;
					for ($i=0, $n=count($this->popular); $i < $n; $i++) {
					$row = $this->popular[$i];
					$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid[]='. $row->id;
					?>
					<tr>
						<td width="65%">
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' ); ?>::<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>">
								<a href="<?php echo $link; ?>">
									<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
								</a>
							</span>
						</td>
						<td width="1%" align="center">
							<strong><?php echo $row->hits; ?></strong>
						</td>
						<td width="34%">
							<strong><?php echo flexicontent_html::ratingbar( $row ); ?></strong>
						</td>
					</tr>
					<?php $k = 1 - $k; } ?>
				</tbody>
			</table>
		</div>
	</div>

</td></tr><tr><td>

	<div class="cssbox">
		<div class="cssbox_head">
			<h2><?php echo JText::_( 'FLEXI_ITEM_STATES' ); ?></h2>
		</div>
		<div class="cssbox_body">
			<img src="http://chart.apis.google.com/chart?chs=450x150&amp;chd=t:<?php echo $this->statestats['values']; ?>&amp;cht=p3&amp;chl=<?php echo $this->statestats['labels']; ?>" alt="<?php echo JText::_( 'FLEXI_ITEM_STATES_CHART' ); ?>" />
		</div>
	</div>

</td><td>
	
<div class="cssbox">
	<div class="cssbox_head">
		<h2><?php echo JText::_( 'FLEXI_MOST_FAVOURED' ); ?></h2>
	</div>
	<div class="cssbox_body">
		<table class="adminlist  table table-hover table-striped">
			<thead>
				<tr>
					<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
					<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
				$k = 0;
				for ($i=0, $n=count($this->favoured); $i < $n; $i++) {
				$row = $this->favoured[$i];
				$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid[]='. $row->id;
				?>
				<tr>
					<td>
						<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>">
							<a href="<?php echo $link; ?>">
								<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
							</a>
						</span>
					</td>
					<td align="center">
						<strong><?php echo $row->favnr; ?></strong>
					</td>
				</tr>
				<?php $k = 1 - $k; } ?>
			</tbody>
		</table>
	</div>
</div>

</td></tr>
</table>

<?php
echo FLEXI_J16GE ? '' : $this->pane->endPanel();






echo FLEXI_J16GE ? JHtml::_('tabs.panel', JText::_( 'FLEXI_RATING_STATS' ), 'ratings' ) : $this->pane->startPanel( JText::_( 'FLEXI_RATING_STATS' ), 'ratings' ) ;
?>


<table border="0">
<tr><td>

	<div class="cssbox">
		<div class="cssbox_head">
			<h2><?php echo JText::_( 'FLEXI_VOTE_STATS' ); ?></h2>
		</div>
		<div class="cssbox_body">
			<img src="http://chart.apis.google.com/chart?chs=450x150&amp;chd=t:<?php echo $this->votesstats['values']; ?>&amp;cht=p3&amp;chl=<?php echo $this->votesstats['labels']; ?>" alt="<?php echo JText::_( 'FLEXI_ITEM_VOTES_CHART' ); ?>" />
		</div>
	</div>

</td><td>
	
	<div class="cssbox">
		<div class="cssbox_head">
			<h2><?php echo JText::_( 'FLEXI_BEST_RATED' ); ?></h2>
		</div>
		<div class="cssbox_body">
			<table class="adminlist  table table-hover table-striped">
				<thead>
					<tr>
						<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
						<th><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
					</tr>
				</thead>
				<tbody>
				<?php
					$k = 0;
					for ($i=0, $n=count($this->rating); $i < $n; $i++) {
					$row = $this->rating[$i];
					$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid[]='. $row->id;
					?>
					<tr>
						<td>
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>">
								<a href="<?php echo $link; ?>">
									<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
								</a>
							</span>
						</td>
						<td>
							<strong><?php echo flexicontent_html::ratingbar( $row ); ?></strong>
						</td>
					</tr>
					<?php $k = 1 - $k; } ?>
				</tbody>
			</table>
		</div>
	</div>

</td></tr><tr><td>

	<div class="cssbox">
		<div class="cssbox_head">
			<h2><?php echo JText::_( 'FLEXI_WORST_RATED' ); ?></h2>
		</div>
		<div class="cssbox_body">
			<table class="adminlist  table table-hover table-striped">
				<thead>
					<tr>
						<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
						<th><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$k = 0;
					for ($i=0, $n=count($this->worstrating); $i < $n; $i++) {
					$row = $this->worstrating[$i];
					$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid[]='. $row->id;
					?>
					<tr>
						<td>
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>">
								<a href="<?php echo $link; ?>">
									<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
								</a>
							</span>
						</td>
						<td>
							<strong><?php echo flexicontent_html::ratingbar( $row ); ?></strong>
						</td>
					</tr>
					<?php $k = 1 - $k; } ?>
				</tbody>
			</table>
		</div>
	</div>

</td></tr>
</table>

<?php
echo FLEXI_J16GE ? '' : $this->pane->endPanel();




echo FLEXI_J16GE ? JHtml::_('tabs.panel', JText::_( 'FLEXI_USER_STATS' ), 'users' ) : $this->pane->startPanel( JText::_( 'FLEXI_USER_STATS' ), 'users' ) ;
?>

<table border="0">
<tr><td>

	<div class="cssbox">
		<div class="cssbox_head">
			<h2><?php echo JText::_( 'FLEXI_TOP_CONTRIBUTORS' ); ?></h2>
		</div>
		<div class="cssbox_body">
			<table class="adminlist  table table-hover table-striped">
				<thead>
					<tr>
						<th><?php echo JText::_( 'FLEXI_USER' ); ?></th>
						<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$k = 0;
					for ($i=0, $n=count($this->creators); $i < $n; $i++) {
					$row = $this->creators[$i];
					$link = 'index.php?option=com_flexicontent&amp;view=user&amp;'.$ctrl_users.'edit&amp;cid[]='. $row->id;
					?>
					<tr>
						<td>
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_USER' );?>::<?php echo htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8'); ?>">
								<a href="<?php echo $link; ?>">
									<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8').')'; ?>
								</a>
							</span>
						</td>
						<td align="center">
							<strong><?php echo $row->counter; ?></strong>
						</td>
					</tr>
					<?php $k = 1 - $k; } ?>
				</tbody>
			</table>
		</div>
	</div>

</td><td>

	<div class="cssbox">
		<div class="cssbox_head">
			<h2><?php echo JText::_( 'FLEXI_TOP_EDITORS' ); ?></h2>
		</div>
		<div class="cssbox_body">
			<table class="adminlist  table table-hover table-striped">
				<thead>
					<tr>
						<th><?php echo JText::_( 'FLEXI_USER' ); ?></th>
						<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php
					$k = 0;
					for ($i=0, $n=count($this->editors); $i < $n; $i++) {
					$row = $this->editors[$i];
					$link = 'index.php?option=com_flexicontent&amp;view=user&amp;'.$ctrl_users.'edit&amp;cid[]='. $row->id;
					?>
					<tr>
						<td>
							<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_USER' );?>::<?php echo htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8'); ?>">
								<a href="<?php echo $link; ?>">
									<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8').')'; ?>
								</a>
							</span>
						</td>
						<td align="center">
							<strong><?php echo $row->counter; ?></strong>
						</td>
					</tr>
					<?php $k = 1 - $k; } ?>
				</tbody>
			</table>
		</div>
	</div>

</td></tr>
</table>

<?php
echo FLEXI_J16GE ? '' : $this->pane->endPanel();

// BOF: statistics that use ECHART
if (file_exists(JPATH_COMPONENT.DS.'assets'.DS.'echarts') && file_exists(JPATH_COMPONENT.DS.'assets'.DS.'zrender')) :

echo FLEXI_J16GE ? JHtml::_('tabs.panel', JText::_( 'FLEXI_DASHBOARD' ), 'ratings' ) : $this->pane->startPanel( JText::_( 'FLEXI_DASHBOARD' ), 'ratings' ) ;
?>
<div class="clear clearfix"></div>
<div class="container-fluid">

	<!-- H1 Title -->
	<div class="row-fluid">
		<div class="span12"><h1><?php echo JText::_( 'FLEXI_DASHBOARD' ); ?></h1></div>
	</div>
	<!-- End of H1 Title -->
	<!-- Breadcrumbs -->
	<div class="row-fluid">
		<div class="span12">
			<?php echo JText::_( 'FLEXI_TOTAL_NUM_OF' ) ?>:
			<a href="index.php?option=com_flexicontent&view=items"       class="btn btn-small"><?php echo $this->genstats[0]; ?> <?php echo JText::_( 'FLEXI_ITEMS' ) ?></a> 
			<a href="index.php?option=com_flexicontent&view=categories"  class="btn btn-small"><?php echo $this->genstats[1]; ?> <?php echo JText::_( 'FLEXI_CATEGORIES' ) ?></a> 
			<a href="index.php?option=com_flexicontent&view=tags"        class="btn btn-small"><?php echo $this->genstats[2]; ?> <?php echo JText::_( 'FLEXI_TAGS' ) ?></a> 
			<a href="index.php?option=com_flexicontent&view=filemanager" class="btn btn-small"><?php echo $this->genstats[3]; ?> <?php echo JText::_( 'FLEXI_FILES' ) ?></a> 
			<a href="index.php?option=com_flexicontent&view=types"       class="btn btn-small"><?php echo $this->genstats[4]; ?> <?php echo JText::_( 'FLEXI_TYPES' ) ?></a> 
			<a href="index.php?option=com_flexicontent&view=users"       class="btn btn-small"><?php echo $this->genstats[5]; ?> <?php echo JText::_( 'FLEXI_USERS' ) ?></a> 
			<a href="index.php?option=com_flexicontent&view=templates"   class="btn btn-small"><?php echo $this->genstats[6]; ?> <?php echo JText::_( 'FLEXI_TEMPLATES' ) ?></a> 
			<a href="index.php?option=com_flexicontent&view=fields"      class="btn btn-small"><?php echo $this->genstats[7]; ?> <?php echo JText::_( 'FLEXI_FIELDS' ) ?></a> 

			

		</div>
	</div>
	<!-- End of Breadcrumbs -->

	<hr>
	<div class="row-fluid">
		<div class="span12"><h3><?php echo JText::_( 'FLEXI_ITEMS' ); ?> <a href="index.php?option=com_flexicontent&view=items"       class="btn btn-small"><?php echo JText::_( 'FLEXI_VIEW' ) ?></a> </h3></div>
	</div>
	<!-- Ballons -->
		<div class="row-fluid">
			<div class="span2">
	  			<a href="index.php?option=com_flexicontent&view=items&filter_state=P" class="btn btn-block btn-large btn-success">
	  			  	<div>
	  			  		<i class="icon-asterisk "></i>
		  			  	<span class="white">
		  			  		<?php print_r($this->totalitemspublish[0]->itemspub);  ?>
		  			  	</span>
	  			  	</div>
	  			  	<small><?php echo JText::_( 'FLEXI_TOTAL_NUM_OF_PUBLISHED_ITEMS' ) ?></small>
	  			</a>
			</div>
			<div class="span2">
				<a href="index.php?option=com_flexicontent&view=items&filter_state=U" class="btn btn-block btn-large btn-warning">
				<div class="white">
		  			  		<i class="icon-file "></i>
		  			  		<span class="">
								<?php print_r($this->totalitemsunpublish[0]->itemsunpub);  ?>
		  			  		</span>
		  			  	</div>
		  			  	<small><?php echo JText::_( 'FLEXI_TOTAL_NUM_UNPUBLISHED_ITEMS' ) ?></small>

				</a>
		  			  	
	  		</div>
	  		<div class="span2">

				<a href="index.php?option=com_flexicontent&view=items&filter_state=PE" class="btn btn-block btn-large btn-danger">
	  			  	<div class="white">
	  			  		<i class="icon-trash "></i>
	  			  			<span class="">
								<?php print_r($this->totalitemswaiting[0]->itemswaiting);  ?>
	  			  			</span>
	  			  	</div>
	  			  	<small><?php echo JText::_( 'FLEXI_TOTAL_NUM_OF_WAITING_ITEMS' ) ?></small>
	  			</a>
	  		</div>
	  		<div class="span2">
	  			<a href="index.php?option=com_flexicontent&view=items&filter_state=IP" class="btn btn-block btn-large btn-primary">
	  			  	<div class="white">
	  			  		<i class="icon-archive "></i>
	  			  			<span class="">
								<?php print_r($this->totalitemsprogress[0]->itemsprogress);  ?>
	  			  			</span>
	  			  	</div>
	  			  	<small><?php echo JText::_( 'FLEXI_TOTAL_NUM_OF_INPROGRESS_ITEMS' ) ?></small>
	  			</a>
	  		</div>
	  		<div class="span2">

				<a href="index.php?option=com_flexicontent&view=items&filter_state=PE" class="btn btn-block btn-large btn-info">
	  			  	<div class="white">
	  			  		<i class="icon-list"></i>
	  			  			<span class="">
								<?php print_r($this->metadescription[0]->itemsmetadesc);  ?>
	  			  			</span>
	  			  	</div>
	  			  	<small><?php echo JText::_( 'FLEXI_SEODESC_ITEMS' ) ?></small>
	  			</a>
	  		</div>
	  		<div class="span2">
	  			<a href="index.php?option=com_flexicontent&view=items&filter_state=IP" class="btn btn-block btn-large btn-inverse">
	  			  	<div class="white">
	  			  		<i class="icon-tag "></i>
	  			  			<span class="">
								<?php print_r($this->metakeywords[0]->itemsmetakey);  ?>
	  			  			</span>
	  			  	</div>
	  			  	<small><?php echo JText::_( 'FLEXI_SEOKEYWORDS_ITEMS' ) ?></small>
	  			</a>
	  		</div>
	</div>
	<br>
	<!-- End of Ballons -->

<?php 
	$stasts          = $this->itemsgraph[0];
	$months          = array();
	$monthslist      = '';
	$totalitems       = array();
	$totalitemslist   = '';


	foreach ($stasts as $s) {

		$months[] .= $s->year_month_text;
		$monthslist = json_encode($months);

		$totalitems[]    .= $s->item_count;
		$totalitemslist   = json_encode($totalitems);
	}

 ?>
    <div class="row-fluid">
        <div class="span12">
        	<div class="">
	            <div id="main" style="height:400px;width:100%"></div>
            </div>
        </div>
    </div>

	<script>
		var option = {
	            tooltip : {
	                trigger: 'axis'
	            },
	            legend: {
	                data:['Total Items']
	            },
	            toolbox: {
	                show : true,
	                feature : {
						          //mark : {show: true},
						          //dataView : {show: true, readOnly: false},
						          restore : {
						          	show: true,
						          	title: 'Refresh'
						          },
						          saveAsImage : {
						          	show: true,
						          	title: 'Export'
						          }
						        }
	            },
	            calculable : true,
	            xAxis : [
	                {
	                    type : 'category',
	                    data : <?php echo $monthslist; ?>
	                }
	            ],
	            yAxis : [
	                {
	                    type : 'value',
	                    splitArea : {show : true}
	                }
	            ],
	            series : [
	                {
	                    name:'Total Items by Month',
	                    type:'bar',
	                    data:<?php echo $totalitemslist; ?>
	                }
	            ]
	        };
	        
	        require(
	            [
	                'echarts',
	                'echarts/chart/line',
	                'echarts/chart/bar'
	            ],
	            function (ec) {
	                var myChart = ec.init(document.getElementById('main'));
	                myChart.setOption(option);
	            }
	        )

	</script>

	<hr>
	<div class="row-fluid">
		<div class="span12"><h3><?php echo JText::_( 'FLEXI_GENERAL_STATS' ); ?></h3></div>
	</div>
	<!-- Most and less Popular-->
		<div class="row-fluid">
			<div class="span5">
					<!-- Most and less Popular-->
		<div class="row-fluid">
			
			<div class="span12">
				<div class="well">
					<h3><?php echo JText::_( 'FLEXI_MOST_POPULAR' ); ?> <a href="index.php?option=com_flexicontent&view=items&filter_order=i.hits&filter_order_Dir=desc" class="btn btn-small"><?php echo JText::_( 'FLEXI_VIEW' ); ?></a></h3>
					<hr>
					<table class="adminlist  table table-hover table-striped">
						<thead>
							<tr>
								<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
								<th><?php echo JText::_( 'FLEXI_HITS' ); ?></th>
								<th><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$k = 0;
							for ($i=0, $n=count($this->popular); $i < $n; $i++) {
							$row = $this->popular[$i];
							$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid[]='. $row->id;
							?>
							<tr>
								<td width="65%">
									<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' ); ?>::<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>">
										<a href="<?php echo $link; ?>">
											<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
										</a>
									</span>
								</td>
								<td width="1%" align="center">
									<strong><?php echo $row->hits; ?></strong>
								</td>
								<td width="34%">
									<strong><?php echo flexicontent_html::ratingbar( $row ); ?></strong>
								</td>
							</tr>
							<?php $k = 1 - $k; } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>
		<div class="row-fluid">
			
			<div class="span12">
				<div class="well">
					<h3><?php echo JText::_( 'FLEXI_LESS_POPULAR' ) ?> <a href="index.php?option=com_flexicontent&view=items&filter_order=i.hits&filter_order_Dir=asc" class="btn btn-small"><?php echo JText::_( 'FLEXI_VIEW' ); ?></a></h3>
					<hr>
					<table class="adminlist  table table-hover table-striped">
						<thead>
							<tr>
								<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
								<th><?php echo JText::_( 'FLEXI_HITS' ); ?></th>
								<th><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$k = 0;
							for ($i=0, $n=count($this->unpopular); $i < $n; $i++) {
							$row = $this->unpopular[$i];
							$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid[]='. $row->id;
							?>
							<tr>
								<td width="65%">
									<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' ); ?>::<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>">
										<a href="<?php echo $link; ?>">
											<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
										</a>
									</span>
								</td>
								<td width="1%" align="center">
									<strong><?php echo $row->hits; ?></strong>
								</td>
								<td width="34%">
									<strong><?php echo flexicontent_html::ratingbar( $row ); ?></strong>
								</td>
							</tr>
							<?php $k = 1 - $k; } ?>
						</tbody>
					</table>
				</div>
			</div>
		</div>


		<div class="row-fluid">
			
			<div class="span12">
				<div class="well">
					<h3><?php echo JText::_( 'FLEXI_MOST_FAVOURED' ) ?> </h3>
					<hr>
							<table class="adminlist  table table-hover table-striped">
						<thead>
							<tr>
								<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
								<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$k = 0;
							for ($i=0, $n=count($this->favoured); $i < $n; $i++) {
							$row = $this->favoured[$i];
							$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid[]='. $row->id;
							?>
							<tr>
								<td>
									<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>">
										<a href="<?php echo $link; ?>">
											<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
										</a>
									</span>
								</td>
								<td align="center">
									<strong><?php echo $row->favnr; ?></strong>
								</td>
							</tr>
							<?php $k = 1 - $k; } ?>
						</tbody>
					</table>
				</div>
			</div>
			</div>
			</div>
			<div class="span7">
				<div class="well">
					<h3><?php echo JText::_( 'FLEXI_ITEM_STATES_CHART' ); ?></h3>

 
					    <div id="pie" style="height:742px; width:100%; 1px solid #ccc; padding: 10px;"></div>

						<?php 

							$workflow          = $this->statestats;
							$typeslabel        = explode('|',$workflow['labels']);
							$typeslabellist    = json_encode($typeslabel);
							$valuesitems       = explode(',',$workflow['values']);
							$datalist          = '';

							foreach ($typeslabel  as $key=>$label) {
									$datalist.= '{value:'.$valuesitems[$key].', name:"'.$label.'"},';
							}
						 ?>

					    <script type="text/javascript" language="javascript">
					       
							var optionpie = {
							      tooltip : {
							        trigger: 'item',
							        formatter: "{a} <br/>{b} : {c} ({d}%)"
							      },
							      legend: {
							        orient : 'vertical',
							        x : 'left',
							        data:<?php echo $typeslabellist; ?>
							      },
							      toolbox: {
							        show : true,
							        feature : {
						          //mark : {show: true},
						          //dataView : {show: true, readOnly: false},
						          restore : {
						          	show: true,
						          	title: 'Refresh'
						          },
						          saveAsImage : {
						          	show: true,
						          	title: 'Export'
						          }
						        }
							      },
							      calculable : false,
							      series : [
							        {
							          name:'Workflow',
							          type:'pie',
							          radius : '50%',
							          center: ['50%', '60%'],
							          data:[
							               <?php echo $datalist; ?>
							           ]
							        }
							      ]
							    };
						        require(
						            [
						                'echarts',
						                'echarts/chart/pie'
						            ],
						            function (ec) {
						                var myChart = ec.init(document.getElementById('pie'));
						                myChart.setOption(optionpie);
						            }
						        )

					        </script>

						</div>
					</div>
				</div>
			<!-- End of Most and less Popular-->
	<hr>
	<div class="row-fluid">
		<div class="span12"><h3><?php echo JText::_( 'FLEXI_RATING_STATS' ) ?></h3></div>
	</div>
	<!-- Most and less Popular-->
			<div class="row-fluid">

				<div class="span5">
					<div class="row-fluid">
						<div class="span12">
							<div class="well">
								<h3><?php echo JText::_( 'FLEXI_BEST_RATED' ) ?></h3>
								<hr>
								<table class="adminlist  table table-hover table-striped">
									<thead>
										<tr>
											<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
											<th><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
										</tr>
									</thead>
									<tbody>
									<?php
										$k = 0;
										for ($i=0, $n=count($this->rating); $i < $n; $i++) {
										$row = $this->rating[$i];
										$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid[]='. $row->id;
										?>
										<tr>
											<td>
												<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>">
													<a href="<?php echo $link; ?>">
														<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
													</a>
												</span>
											</td>
											<td>
												<strong><?php echo flexicontent_html::ratingbar( $row ); ?></strong>
											</td>
										</tr>
										<?php $k = 1 - $k; } ?>
									</tbody>
								</table>
							</div>
						</div>
					</div>
					<div class="row-fluid">
						<div class="span12">
							<div class="well">
								<h3><?php echo JText::_( 'FLEXI_WORST_RATED' ) ?></h3>
								<hr>
										<table class="adminlist  table table-hover table-striped">
											<thead>
												<tr>
													<th><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
													<th><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
												</tr>
											</thead>
											<tbody>
												<?php
												$k = 0;
												for ($i=0, $n=count($this->worstrating); $i < $n; $i++) {
												$row = $this->worstrating[$i];
												$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid[]='. $row->id;
												?>
												<tr>
													<td>
														<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>">
															<a href="<?php echo $link; ?>">
																<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
															</a>
														</span>
													</td>
													<td>
														<strong><?php echo flexicontent_html::ratingbar( $row ); ?></strong>
													</td>
												</tr>
												<?php $k = 1 - $k; } ?>
											</tbody>
										</table>
							</div>
						</div>
					</div>
				</div>


				<div class="span7">
					<div class="well">
						<h3><?php echo JText::_( 'FLEXI_ITEM_VOTES_CHART' ) ?></h3>
						<hr>

					 
				    <div id="pie2" style="height:525px; width:100%; 1px solid #ccc; padding: 10px;"></div>

					<?php 


						$votesitems        = $this->votesstats;
						$voteslabel        = explode('|',$votesitems['labels']);
						$voteslabellist    = json_encode($voteslabel);
						$valuesvotes       = explode(',',$votesitems['values']);
						$datalist          = '';

						foreach ($voteslabel  as $key=>$label) {
								$datalist.= '{value:'.$valuesvotes[$key].', name:"'.$label.'"},';
						}
					 ?>

				    <script type="text/javascript" language="javascript">
				       
						var optionpie2 = {
						      tooltip : {
						        trigger: 'item',
						        formatter: "{a} <br/>{b} : {c} ({d}%)"
						      },
						      legend: {
						        orient : 'vertical',
						        x : 'left',
						        data:<?php echo $voteslabellist; ?>
						      },
						      toolbox: {
						        show : true,
						        feature : {
						          //mark : {show: true},
						          //dataView : {show: true, readOnly: false},
						          restore : {
						          	show: true,
						          	title: 'Refresh'
						          },
						          saveAsImage : {
						          	show: true,
						          	title: 'Export'
						          }
						        }
						      },
						      calculable : false,
						      series : [
						        {
						          name:'Raitings',
						          type:'pie',
						          radius : '75%',
						          center: ['50%', '60%'],
						          data:[
						               <?php echo $datalist; ?>
						           ]
						        }
						      ]
						    };
					        require(
					            [
					                'echarts',
					                'echarts/chart/pie'
					            ],
					            function (ec) {
					                var myChart = ec.init(document.getElementById('pie2'));
					                myChart.setOption(optionpie2);
					            }
					        )

				        </script>
					</div>
				</div>
			</div>
		<!-- End of Most and less Popular-->

	<hr>
	<div class="row-fluid">
		<div class="span12"><h3><?php echo JText::_( 'FLEXI_USER_STATS' ) ?></h3></div>
	</div>
	<!-- Most and less Popular-->
			<div class="row-fluid">
				<div class="span5">
					<div class="well">
						<h3><?php echo JText::_( 'FLEXI_TOP_EDITORS' ) ?></h3>
						<hr>
							<table class="adminlist  table table-hover table-striped">
								<thead>
									<tr>
										<th><?php echo JText::_( 'FLEXI_USER' ); ?></th>
										<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									$k = 0;
									for ($i=0, $n=count($this->creators); $i < $n; $i++) {
									$row = $this->creators[$i];
									$link = 'index.php?option=com_flexicontent&amp;view=user&amp;'.$ctrl_users.'edit&amp;cid[]='. $row->id;
									?>
									<tr>
										<td>
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_USER' );?>::<?php echo htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8'); ?>">
												<a href="<?php echo $link; ?>">
													<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8').')'; ?>
												</a>
											</span>
										</td>
										<td align="center">
											<strong><?php echo $row->counter; ?></strong>
										</td>
									</tr>
									<?php $k = 1 - $k; } ?>
								</tbody>
							</table>
					</div>
				</div>
				<div class="span7">
					<div class="well">
						<h3><?php echo JText::_( 'FLEXI_TOP_CONTRIBUTORS' ) ?></h3>
						<hr>
							<table class="adminlist  table table-hover table-striped">
								<thead>
									<tr>
										<th><?php echo JText::_( 'FLEXI_USER' ); ?></th>
										<th><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									$k = 0;
									for ($i=0, $n=count($this->editors); $i < $n; $i++) {
									$row = $this->editors[$i];
									$link = 'index.php?option=com_flexicontent&amp;view=user&amp;'.$ctrl_users.'edit&amp;cid[]='. $row->id;
									?>
									<tr>
										<td>
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_USER' );?>::<?php echo htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8'); ?>">
												<a href="<?php echo $link; ?>">
													<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8').')'; ?>
												</a>
											</span>
										</td>
										<td align="center">
											<strong><?php echo $row->counter; ?></strong>
										</td>
									</tr>
									<?php $k = 1 - $k; } ?>
								</tbody>
					</div>
				</div>
				
			</div>
		<!-- End of Most and less Popular-->




</div>
<div class="clear clearfix"></div>

<?php
echo FLEXI_J16GE ? '' : $this->pane->endPanel();

endif;
// EOF: statistics that use ECHART

echo FLEXI_J16GE ? JHtml::_('tabs.end') : $this->pane->endPane();
?>


	</div>