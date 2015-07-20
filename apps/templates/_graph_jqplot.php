<?php
/**********************************************************************************
*
*	    This file is part of e-venement.
*
*    e-venement is free software; you can redistribute it and/or modify
*    it under the terms of the GNU General Public License as published by
*    the Free Software Foundation; either version 2 of the License.
*
*    e-venement is distributed in the hope that it will be useful,
*    but WITHOUT ANY WARRANTY; without even the implied warranty of
*    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*    GNU General Public License for more details.
*
*    You should have received a copy of the GNU General Public License
*    along with e-venement; if not, write to the Free Software
*    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*
*    Copyright (c) 2006-2015 Baptiste SIMON <baptiste.simon AT e-glop.net>
*    Copyright (c) 2006-2015 Libre Informatique [http://www.libre-informatique.fr/]
*
***********************************************************************************/

/*
PARAMETERS :

mandatory:
* id: the id of the chart, used for the parent div & the chart itself (appended by "_chart")
* data: URL or Array usable directly by jqplot
* label: the string to display as a label

optional:
* width: in px, by default 450px
* name: the name of the current object
*/
?>

<?php use_stylesheet('/js/jqplot/jquery.jqplot.css') ?>
<?php use_stylesheet('jqplot') ?>
<?php use_javascript('jqplot') ?>
<?php use_javascript('/js/jqplot/jquery.jqplot.js') ?>
<?php use_javascript('/js/jqplot/jqplot.axisLabelRenderer.js') ?>
<?php use_javascript('/js/jqplot/jqplot.axisTickRenderer.js') ?>

<?php
  if ( !isset($width) )
    $width = 450;
  if ( intval($width).'' == ''.$width )
    $width .= 'px';
  if ( $width == '100%' )
    $width = 'calc(100% - 50px)';
  if ( !isset($name) ) $name = '';
?>

<script type="text/javascript"><!--
  LI.series['<?php echo $id ?>'] = <?php echo is_array($data) ? json_encode($data) : '"'+$data+'"' ?>;
--></script>
<div class="<?php echo $id ?> jqplot ui-widget ui-corner-all ui-widget-content">
  <a name="<?php echo $id ?>_chart"></a>
  <div class="ui-widget-header ui-corner-all fg-toolbar">
    <h2 title="<?php echo $name ?>"><?php echo $label ?></h2>
  </div>
  <div
    data-series-name="<?php echo $id ?>"
    id="<?php echo $id ?>_chart"
    class="chart"
    style="width: <?php echo $width ?>"
    <?php if ( !is_array($data) ) echo 'data-json-url="'.$data.'"' ?>
  ></div>
  <div class="actions">
    <?php include_partial('global/chart_actions',array(
      'dl'  => true,
      'ofc' => false,
    )) ?>
  </div>
</div>