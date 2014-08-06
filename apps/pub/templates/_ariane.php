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
*    Copyright (c) 2006-2012 Baptiste SIMON <baptiste.simon AT e-glop.net>
*    Copyright (c) 2006-2012 Libre Informatique [http://www.libre-informatique.fr/]
*
***********************************************************************************/
?>
<?php
  // $active sets the current item to highlight and which ones are past or future
  // be careful for the login step if it comes before command
  $nb = 0;
?>
<div id="ariane">
  <div class="login choices <?php if ( $active == $nb ) echo 'active'; else echo 'past' ?> access">
    <?php echo $sf_user->hasContact() ? link_to(__('My account'),'contact/index').' '.link_to(__('Logout'),'login/out') : link_to(__('Login'),'login/index').' '.link_to(__('Create an account'),'contact/new') ?>
  </div>
  <?php $nb++ ?>
  <div class="event choices <?php if ( $active == $nb ) echo 'active'; else echo $active < $nb ? 'future' : 'past' ?> access">
    <?php echo link_to(sfConfig::get('app_informations_index',__('Dates')),'event/index') ?>
    <?php echo link_to(__('Buy member cards'),'card/index') ?>
  </div>
  <?php $nb++ ?>
  <div class="cart <?php if ( $active == $nb ) echo 'active'; else echo $active < $nb ? 'future' : 'past' ?> access">
    <?php echo link_to(__('Cart'),'cart/show') ?>
  </div>
  <?php $nb++ ?>
  <div class="id <?php if ( $active == $nb ) echo 'active'; else echo $active < $nb ? 'future' : 'past' ?> access">
    <p class="coordinates">
    <?php if ( $sf_user->getTransaction()->contact_id ): ?>
      <?php echo link_to(__('Coordinates'), 'contact/index') ?>
    <?php else: ?>
      <?php echo __('Coordinates') ?>
    <?php endif ?>
    </p>
    <?php if ( count($cultures = sfConfig::get('project_internals_cultures',array('fr' => 'Français'))) > 1 ): ?>
    <p class="i18n">
      <?php foreach ( $cultures as $culture => $lang ): ?>
        <a href="<?php echo url_for('event/index?culture='.$culture) ?>" class="culture-<?php echo $culture ?>" title="<?php echo $lang ?>"><?php echo $lang ?></a>
      <?php endforeach ?>
    </p>
    <?php endif ?>
  </div>
  <?php $nb++ ?>
  <div class="command <?php if ( $active == $nb ) echo 'active'; else echo $active < $nb ? 'future' : 'past' ?> access">
    <?php echo __('Command') ?>
  </div>
</div>
