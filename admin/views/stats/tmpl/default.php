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
if (!file_exists(JPATH_COMPONENT_SITE.DS.'librairies'.DS.'echarts')) :
	echo "echarts library not installed in ".JPATH_COMPONENT_SITE.DS.'librairies'.DS.'echarts';
elseif (!file_exists(JPATH_COMPONENT_SITE.DS.'librairies'.DS.'zrender')) :
	echo "zrender library not installed in ".JPATH_COMPONENT_SITE.DS.'librairies'.DS.'zrender';
else :
?>
<script type="text/javascript">
    require.config({
        packages: [
            {
                name: 'echarts',
                location: '../components/com_flexicontent/librairies/echarts',
                main: 'echarts'
            },
            {
                name: 'zrender',
                location: '../components/com_flexicontent/librairies/zrender',
                main: 'zrender'
            }
        ]
    });
</script>


<div id="flexicontent" class="flexicontent">
	<table class="fc-table-list fc-tbl-short" style="margin:20px 0 20px 0; width:98%;">
	<tr>
		<th style="font-size:18px;">
			<?php echo JText::_( 'FLEXI_TOTAL_NUM_OF' ); ?>
		</th>
	</tr>
	</table>
	
	<!-- SITE TOTALS -->
	<div class="row-fluid">
		<div class="span12">
			<a href="index.php?option=com_flexicontent&amp;view=items"       class="btn btn-small"><?php echo $this->genstats[0]; ?> <?php echo JText::_( 'FLEXI_ITEMS' ) ?></a> 
			<a href="index.php?option=com_flexicontent&amp;view=categories"  class="btn btn-small"><?php echo $this->genstats[1]; ?> <?php echo JText::_( 'FLEXI_CATEGORIES' ) ?></a> 
			<a href="index.php?option=com_flexicontent&amp;view=tags"        class="btn btn-small"><?php echo $this->genstats[2]; ?> <?php echo JText::_( 'FLEXI_TAGS' ) ?></a> 
			<a href="index.php?option=com_flexicontent&amp;view=filemanager" class="btn btn-small"><?php echo $this->genstats[3]; ?> <?php echo JText::_( 'FLEXI_FILES' ) ?></a> 
			<a href="index.php?option=com_flexicontent&amp;view=types"       class="btn btn-small"><?php echo $this->genstats[4]; ?> <?php echo JText::_( 'FLEXI_TYPES' ) ?></a> 
			<a href="index.php?option=com_flexicontent&amp;view=users"       class="btn btn-small"><?php echo $this->genstats[5]; ?> <?php echo JText::_( 'FLEXI_USERS' ) ?></a> 
			<a href="index.php?option=com_flexicontent&amp;view=templates"   class="btn btn-small"><?php echo $this->genstats[6]; ?> <?php echo JText::_( 'FLEXI_TEMPLATES' ) ?></a> 
			<a href="index.php?option=com_flexicontent&amp;view=fields"      class="btn btn-small"><?php echo $this->genstats[7]; ?> <?php echo JText::_( 'FLEXI_FIELDS' ) ?></a> 
		</div>
	</div>
	<!-- End of SITE TOTALS -->
	
	
	<!-- ITEM TOTALS -->
	<hr />
	<span class="label"><?php echo JText::_( 'FLEXI_ITEMS' ) ?></span>
	<div class="span12">
		
		<div class="span2">
			<a href="index.php?option=com_flexicontent&amp;view=items&amp;filter_state=P" class="btn btn-block btn-large btn-success">
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
			<a href="index.php?option=com_flexicontent&amp;view=items&amp;filter_state=U" class="btn btn-block btn-large btn-warning">
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
			<a href="index.php?option=com_flexicontent&amp;view=items&amp;filter_state=PE" class="btn btn-block btn-large btn-danger">
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
			<a href="index.php?option=com_flexicontent&amp;view=items&amp;filter_state=IP" class="btn btn-block btn-large btn-primary">
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
			<a href="index.php?option=com_flexicontent&amp;view=items&amp;filter_state=PE" class="btn btn-block btn-large btn-info">
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
			<a href="index.php?option=com_flexicontent&amp;view=items&amp;filter_state=IP" class="btn btn-block btn-large btn-inverse">
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
	<!-- End of ITEM TOTALS -->
	
	
	<hr>
	
	<table class="fc-table-list fc-tbl-short" style="margin:120px 0 20px 0; width:98%;">
	<tr>
		<th style="font-size:18px;">
			<?php echo JText::_( 'FLEXI_ITEMS' ); ?> - <?php echo JText::_( 'FLEXI_CREATION_DATE' ); ?>
		</th>
	</tr>
	</table>


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
		<div class="span11">
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
	<table class="fc-table-list fc-tbl-short" style="margin:120px 0 20px 0; width:98%;">
	<tr>
		<th style="font-size:18px;">
			<?php echo JText::_( 'FLEXI_ITEM_STATES_CHART' ); ?>
		</th>
	</tr>
	</table>

	
	<div class="row-fluid">
		<div class="span11">
			
			<div id="pie" style="height:525px; 1px solid #ccc; padding: 10px;"></div>
	
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

	    <script type="text/javascript">
	       
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
			          radius : '75%',
			          center: ['60%', '60%'],
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

	
	
	<hr>
	<table class="fc-table-list fc-tbl-short" style="margin:120px 0 20px 0; width:98%;">
	<tr>
		<th style="font-size:18px;">
			<?php echo JText::_( 'FLEXI_GENERAL_STATS' ); ?>
		</th>
	</tr>
	</table>
	
	
	<!-- Most and less Popular-->
	<div class="row-fluid">
		
		<div class="span6">
			<div class="well">
				
				<h3><?php echo JText::_( 'FLEXI_MOST_POPULAR' ); ?> <a href="index.php?option=com_flexicontent&amp;view=items&amp;filter_order=i.hits&amp;filter_order_Dir=desc" class="btn btn-small"><?php echo JText::_( 'FLEXI_VIEW' ); ?></a></h3>
				<hr>
				<table class="adminlist  table table-hover table-striped">
					<thead>
						<tr>
							<th class="left"><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
							<th class="center"><?php echo JText::_( 'FLEXI_HITS' ); ?></th>
							<th class="left"><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$k = 0;
						for ($i=0, $n=count($this->popular); $i < $n; $i++) {
						$row = $this->popular[$i];
						$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid='. $row->id;
						?>
						<tr>
							<td style="width:65%">
								<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' ); ?>::<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>">
									<a href="<?php echo $link; ?>">
										<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
									</a>
								</span>
							</td>
							<td style="width:1%" class="center">
								<span class="badge badge-info"><?php echo $row->hits; ?></span>
							</td>
							<td style="width:34%">
								<strong><?php echo flexicontent_html::ratingbar( $row ); ?></strong>
							</td>
						</tr>
						<?php $k = 1 - $k; } ?>
					</tbody>
				</table>
				
			</div>
		</div>
		
		<div class="span6">
			<div class="well">
				
				<h3><?php echo JText::_( 'FLEXI_LESS_POPULAR' ) ?> <a href="index.php?option=com_flexicontent&amp;view=items&amp;filter_order=i.hits&amp;filter_order_Dir=asc" class="btn btn-small"><?php echo JText::_( 'FLEXI_VIEW' ); ?></a></h3>
				<hr>
				<table class="adminlist  table table-hover table-striped">
					<thead>
						<tr>
							<th class="left"><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
							<th class="center"><?php echo JText::_( 'FLEXI_HITS' ); ?></th>
							<th class="left"><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$k = 0;
						for ($i=0, $n=count($this->unpopular); $i < $n; $i++) {
						$row = $this->unpopular[$i];
						$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid='. $row->id;
						?>
						<tr>
							<td style="width:65%">
								<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' ); ?>::<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>">
									<a href="<?php echo $link; ?>">
										<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
									</a>
								</span>
							</td>
							<td style="width:1%" class="center">
								<span class="badge badge-info"><?php echo $row->hits; ?></span>
							</td>
							<td style="width:34%">
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
		
		<div class="span6">
			<div class="well">
				
				<h3><?php echo JText::_( 'FLEXI_MOST_FAVOURED' ) ?> </h3>
				<hr>
						<table class="adminlist  table table-hover table-striped">
					<thead>
						<tr>
							<th class="left"><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
							<th class="center"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php
						$k = 0;
						for ($i=0, $n=count($this->favoured); $i < $n; $i++) {
						$row = $this->favoured[$i];
						$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid='. $row->id;
						?>
						<tr>
							<td>
								<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_ITEM' );?>::<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>">
									<a href="<?php echo $link; ?>">
										<?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?>
									</a>
								</span>
							</td>
							<td class="center">
								<span class="badge badge-success"><?php echo $row->favnr; ?></span>
							</td>
						</tr>
						<?php $k = 1 - $k; } ?>
					</tbody>
				</table>
				
			</div>
		</div>
	
	</div>
	<!-- End of Most and less Popular-->
	
	
	
	<div class="clear clearfix"></div>
	
	<table class="fc-table-list fc-tbl-short" style="margin:120px 0 20px 0; width:98%;">
	<tr>
		<th style="font-size:18px;">
			<?php echo JText::_( 'FLEXI_RATING_STATS' ); ?>
		</th>
	</tr>
	</table>


	<!-- Most and less Popular-->
	<div class="row-fluid">

		<div class="span5">
			
			<div class="row-fluid">
				<div class="span12">
					<div class="well">
						<h3><?php echo JText::_( 'FLEXI_BEST_RATED' ) ?></h3>
						<hr>
						<table class="adminlist table table-hover table-striped">
							<thead>
								<tr>
									<th class="left"><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
									<th class="left"><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
								</tr>
							</thead>
							<tbody>
							<?php
								$k = 0;
								for ($i=0, $n=count($this->rating); $i < $n; $i++) {
								$row = $this->rating[$i];
								$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid='. $row->id;
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
								<table class="adminlist table table-hover table-striped">
									<thead>
										<tr>
											<th class="left"><?php echo JText::_( 'FLEXI_TITLE' ); ?></th>
											<th class="left"><?php echo JText::_( 'FLEXI_RATING' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php
										$k = 0;
										for ($i=0, $n=count($this->worstrating); $i < $n; $i++) {
										$row = $this->worstrating[$i];
										$link = 'index.php?option=com_flexicontent&amp;'.$ctrl_items.'edit&amp;cid='. $row->id;
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

		    <script type="text/javascript">
		       
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


	<div class="clear clearfix"></div>
	<hr>
	<table class="fc-table-list fc-tbl-short" style="margin:120px 0 20px 0; width:98%;">
	<tr>
		<th style="font-size:18px;">
			<?php echo JText::_( 'FLEXI_USER_STATS' ); ?>
		</th>
	</tr>
	</table>
	
	<!-- Most and less Popular-->
			<div class="row-fluid">
				<div class="span5">
					<div class="well">
						<h3><?php echo JText::_( 'FLEXI_TOP_EDITORS' ) ?></h3>
						<hr>
							<table class="adminlist  table table-hover table-striped">
								<thead>
									<tr>
										<th class="left"><?php echo JText::_( 'FLEXI_USER' ); ?></th>
										<th class="center"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									$k = 0;
									for ($i=0, $n=count($this->creators); $i < $n; $i++) {
									$row = $this->creators[$i];
									$link = 'index.php?option=com_flexicontent&amp;view=user&amp;'.$ctrl_users.'edit&amp;cid='. $row->id;
									?>
									<tr>
										<td>
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_USER' );?>::<?php echo htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8'); ?>">
												<a href="<?php echo $link; ?>">
													<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8').')'; ?>
												</a>
											</span>
										</td>
										<td class="center">
											<span class="badge badge-success"><?php echo $row->counter; ?></span>
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
							<table class="adminlist table table-hover table-striped">
								<thead>
									<tr>
										<th class="left"><?php echo JText::_( 'FLEXI_USER' ); ?></th>
										<th class="center"><?php echo JText::_( 'FLEXI_NUM' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<?php
									$k = 0;
									for ($i=0, $n=count($this->editors); $i < $n; $i++) {
									$row = $this->editors[$i];
									$link = 'index.php?option=com_flexicontent&amp;view=user&amp;'.$ctrl_users.'edit&amp;cid='. $row->id;
									?>
									<tr>
										<td>
											<span class="editlinktip hasTip" title="<?php echo JText::_( 'FLEXI_EDIT_USER' );?>::<?php echo htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8'); ?>">
												<a href="<?php echo $link; ?>">
													<?php echo htmlspecialchars($row->name, ENT_QUOTES, 'UTF-8').' ('.htmlspecialchars($row->username, ENT_QUOTES, 'UTF-8').')'; ?>
												</a>
											</span>
										</td>
										<td class="center">
											<span class="badge badge-success"><?php echo $row->counter; ?></span>
										</td>
									</tr>
									<?php $k = 1 - $k; } ?>
								</tbody>
							</table>
					</div>
				</div>
				
			</div>
		<!-- End of Most and less Popular-->

</div>

<?php endif; /* EOF: Load echart  libraries */ ?>


<div class="clear clearfix"></div>
</div><!-- BOF j-main-container -->