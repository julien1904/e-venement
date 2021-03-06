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
*    Copyright (c) 2006-2011 Baptiste SIMON <baptiste.simon AT e-glop.net>
*    Copyright (c) 2006-2011 Libre Informatique [http://www.libre-informatique.fr/]
*
***********************************************************************************/
?>
<?php
  foreach ( array(
    'users' => __("Concerned users"),
    'workspaces' => __('Workspaces'),
    'manifestations' => __('Manifestations'),
  ) as $criteria => $text ):
?>
<?php if ( isset($$criteria) && $$criteria ): ?>
<div class="ui-widget-content ui-corner-all criterias" id="<?php echo $criteria ?>">
  <div class="fg-toolbar ui-widget-header ui-corner-all" onclick="javascript: $(this).closest('.criterias').toggleClass('hide');">
    <h2><?php echo $text ?></h2>
  </div>
  <ul><?php foreach ($$criteria as $detail): ?>
    <li><?php echo $detail ?></li>
  <?php endforeach ?></ul>
</div>
<?php endif ?>
<?php endforeach ?>
