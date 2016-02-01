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
?>
<?php

/**
 * Transaction
 *
 * This class has been auto-generated by the Doctrine ORM Framework
 *
 * @package    e-venement
 * @subpackage model
 * @author     Baptiste SIMON <baptiste.simon AT e-glop.net>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
class Transaction extends PluginTransaction
{
  public function getItemables()
  {
    $r = new Doctrine_Collection('Itemable');
    foreach ( $this->getTable()->getRelations() as $rel => $fk )
    if ( is_a($fk->getClass(), 'Itemable', true) )
    foreach ( $this->$rel as $obj )
      $r[] = $obj;
    return $r;
  }

  /**
    * Retrieve the applyable surveys
    *
    * @return Doctrine_Collection
    *
    **/
  public function getSurveys()
  {
    $surveys = array();

    // surveys linked to any transaction's related component
    foreach ( $this->Tickets as $ticket )
    foreach ( $ticket->Manifestation->SurveysToApply as $sat )
    if ( ($sat->date_from ? $sat->date_from <= date('Y-m-d H:i:s') : true) && ($sat->date_to ? $sat->date_to > date('Y-m-d H:i:s') : true) )
      $surveys[$sat->Survey->id] = $sat->Survey;
    foreach ( $this->Contact->SurveysToApply as $sat )
    if ( ($sat->date_from ? $sat->date_from <= date('Y-m-d H:i:s') : true) && ($sat->date_to ? $sat->date_to > date('Y-m-d H:i:s') : true) )
      $surveys[$sat->Survey->id] = $sat->Survey;
    foreach ( $this->Contact->Groups as $group )
    foreach ( $group->SurveysToApply as $sat )
    if ( ($sat->date_from ? $sat->date_from <= date('Y-m-d H:i:s') : true) && ($sat->date_to ? $sat->date_to > date('Y-m-d H:i:s') : true) )
      $surveys[$sat->Survey->id] = $sat->Survey;

    // surveys applyable everywhere
    foreach ( Doctrine::getTable('Survey')->createQuery('s')
      ->leftJoin('s.ApplyTo sat')
      ->andWhere('sat.everywhere = ?', true)
      ->andWhere('sat.date_from <= NOW() OR sat.date_from IS NULL')
      ->andWhere('sat.date_to > NOW() OR sat.date_to IS NULL')
      ->execute()
      as $survey )
      $surveys[$survey->id] = $survey;

    ksort($surveys);
    $collection = Doctrine_Collection::create('Survey');
    $collection->setData($surveys);
    return $collection;
  }

  public function isHoldTransaction()
  {
    $arr = $this->Transaction->toArray();
    return isset($arr['HoldTransaction']);
  }

  /**
    * Retrieve the applyable surveys that need to be answered
    *
    * @return Doctrine_Collection
    *
    **/
  public function getSurveysToFillIn()
  {
    $surveys = $this->getSurveys();

    foreach ( $surveys as $key => $survey )
    foreach ( $survey->AnswersGroups as $group )
    if ( $group->transaction_id == $this->id )
    if ( in_array($key, $surveys->getKeys()) )
      $surveys->remove($key);

    return $surveys;
  }

  public function getNotPrinted()
  {
    $toprint = 0;
    foreach ( $this->Tickets as $ticket )
    if ( $ticket->Duplicatas->count() == 0 && !$ticket->printed_at && !$ticket->integrated_at && is_null($ticket->cancelling) )
      $toprint++;
    return $toprint;
  }
  public function getTicketsPrice($including_not_printed = false)
  {
    $price = 0;
    foreach ( $this->Tickets as $ticket )
    if ( $ticket->Duplicatas->count() == 0
      && ($including_not_printed === true || $ticket->printed_at || $ticket->integrated_at || !is_null($ticket->cancelling)) )
      $price += $ticket->value + $ticket->taxes;
    return round($price,2);
  }
  public function getProductsPrice($including_not_integrated = false)
  {
    $price = 0;
    foreach ( $this->BoughtProducts as $product )
    if ( $including_not_integrated === true || $product->integrated_at )
      $price += $product->value + $product->shipping_fees;
    return round($price,2);
  }
  public function getPrice($including_not_printed = false, $all_inclusive = false)
  {
    if ( $all_inclusive === true )
    {
      $mc = 0;
      if ( $this->MemberCards->count() > 0 )
      {
        $mc = $this->getMemberCardPrice($including_not_printed);
        $tltmc = $this->getTicketsLinkedToMemberCardPrice($including_not_printed);
        if ( $tltmc > $mc )
          $mc = 0;
        else
          $mc -= $tltmc;
      }

      return $this->getTicketsPrice($including_not_printed)
        + $this->getProductsPrice($including_not_printed)
        + $mc
      ;
    }

    return $this->getTicketsPrice($including_not_printed)
      + $this->getProductsPrice($including_not_printed)
    ;
  }
  public function getMemberCardPrice($including_not_activated = false)
  {
    $price = 0;
    foreach ( $this->MemberCards as $mc )
    if ( $including_not_activated === true || $mc->active )
      $price += $mc->MemberCardType->value;
    return round($price,2);
  }

  public function getTicketsLinkedToMemberCardPrice($including_not_activated = false)
  {
    $price = 0;

    // all member cards that counts
    $mcs = new Doctrine_Collection('MemberCard');
    foreach ( array($this->MemberCards, $this->contact_id ? $this->Contact->MemberCards : array()) as $m_c )
    foreach ( $m_c as $mc )
    if ( $including_not_activated === true && $mc->transaction_id == $this->id
      || $mc->active && $mc->transaction_id != $this->id )
    if ( $mc->value > 0 || $mc->MemberCardPrices->count() > 0 )
    {
      $mcs[$mc->id] = $mc->copy();
      foreach ( $mc->MemberCardPrices as $mcp )
        $mcs[$mc->id]->MemberCardPrices[] = $mcp->copy();
    }

    // creates the collection of tickets linked to a member card
    $tickets = new Doctrine_Collection('Ticket');
    foreach ( $this->Tickets as $ticket )
    if ( $ticket->Price->member_card_linked || $ticket->member_card_id )
      $tickets[] = $ticket;

    // processing all tickets linked to a member card
    foreach ( $tickets as $ticket )
    if ( $ticket->member_card_id )
    {
      if ( isset($mcs[$ticket->member_card_id]) )
        $price += $ticket->value;
        //$mcs[$ticket->member_card_id]->value -= $ticket->value;
    }
    else
    {
      foreach ( $mcs as $mc )
      foreach ( $mc->MemberCardPrices as $i => $mcp )
      if ( $mcp->event_id )
      {
        if ( $mcp->event_id == $ticket->Manifestation->event_id
          && $mcp->price_id == $ticket->price_id
          && $mc->value >= $ticket->value )
        {
          //$mc->value -= $ticket->value;
          $price += $ticket->value;
          unset($mc->MemberCardPrices[$i]);
          break(2);
        }
      }
      else if ( $mcp->price_id == $ticket->price_id && $mc->value >= $ticket->value )
      {
        //$mc->value -= $ticket->value;
        $price += $ticket->value;
        unset($mc->MemberCardPrices[$i]);
        break(2);
      }
    }

    return $price;
  }
  public function getPaid()
  {
    $paid = 0;
    foreach ( $this->Payments as $payment )
      $paid += $payment->value;
    return $paid;
  }

  public function renderSimplifiedTickets(array $with = array())
  {
    foreach ( array('only' => array(), 'css' => true, 'tickets' => true, 'barcode' => 'html') as $field => $value )
    if ( !isset($with[$field]) )
      $with[$field] = $value;

    sfApplicationConfiguration::getActive()->loadHelpers(array('I18N'));
    $tickets_html = '';

    // tickets w/ barcode
    if (!( isset($with['css']) && !$with['css'] ))
    {
      $tickets_html .= '<div style="clear: both"></div>';
      $tickets_html .= '<style type="text/css" media="all">'.file_get_contents(sfConfig::get('sf_web_dir').'/css/print-simplified-tickets.css').'</style>';
      if ( file_exists(sfConfig::get('sf_web_dir').'/private/print-simplified-tickets.css') )
        $tickets_html .= '<style type="text/css" media="all">'.file_get_contents(sfConfig::get('sf_web_dir').'/private/print-simplified-tickets.css').'</style>';
    }

    if ( $with['only'] )
    foreach ( $with['only'] as $key => $pdt )
    if ( $pdt instanceof Doctrine_Record )
      $with['only'][$key] = $pdt->id;

    $batch = array();
    $content = array();
    if (!( isset($with['tickets']) && !$with['tickets'] ))
    foreach ( $this->Tickets as $ticket )
    {
      if ( $with['only'] )
      {
        if ( !in_array($ticket->id, $with['only']) )
          continue;
      }
      
      // merging tickets
      if ( in_array(sfConfig::get('app_tickets_merge', false), array('horizontal', 'vertical')) )
      {
        // init on the first loop
        if ( count($batch) == 0 )
          $batch[] = array('barcodes' => array(), 'tickets' => array(), 'descriptions' => array());
        
        $id = false;
        switch ( sfConfig::get('app_tickets_merge', false) ) {
          case 'horizontal': // every tickets for one manifestation
            $id = 'm'.$ticket->manifestation_id;
            break;
          case 'vertical': // every tickets of a single DirectContact
            if ( $ticket->contact_id )
              $id = 'c'.$ticket->contact_id;
            break;
        }
        
        // if something has to be merged
        if ( $id )
        {
          if ( !isset($batch[$id]) )
            $batch[$id] = array('barcodes' => array(), 'tickets' => array(), 'descriptions' => array());
          
          $batch[$id]['barcodes'][] = $ticket->barcode;
          $batch[$id]['tickets'][] = $ticket;
          $batch[$id]['descriptions'][] = $ticket->description;
          
          continue;
        }
      }
      
      // normal processing
      $content[] = $ticket->renderSimplified($with['barcode']);
    }
    
    // process tickets in batch mode
    if ( $batch )
    foreach ( $batch as $b )
    if ( count($b) > 0 )
    {
      // if nothing has to be processed as a merged ticket
      if ( count($b['tickets']) == 0 )
        continue;
      
      // if there is only one ticket in the batch
      if ( count($b['tickets']) == 1 )
      {
        $content[] = $b['tickets'][0]->renderSimplified($with['barcode']);
        continue;
      }
      
      $tck = new Ticket;
      $tck->id = ' ';
      if ( sfConfig::get('app_tickets_merge', false) == 'vertical' )
        $tck->DirectContact = $b['tickets'][0]->DirectContact;
      $tck->Manifestation = clone $b['tickets'][0]->Manifestation;
      $tck->Manifestation->Event->name = __('Meta-Ticket', null, 'li_tickets_email');
      $tck->Transaction = $b['tickets'][0]->Transaction;
      $tck->Manifestation->Location = new Location;
      $tck->Manifestation->Location->country = '';
      $tck->Gauge = new Gauge;
      $tck->Manifestation->happens_at = NULL;
      $tck->Gauge = $b['tickets'][0]->Gauge;
      $tck->Manifestation->Event->description = implode('<br/>',$b['descriptions']);
      $tck->barcode = json_encode($b['barcodes']);
      $content[] = '<div class="merged">'.$tck->renderSimplified($with['barcode']).'</div>';
    }
    
    return $tickets_html."\n".implode("\n", $content);
  }
  public function renderSimplifiedProducts(array $with = array())
  {
    $conf = sfConfig::get('app_transaction_email', array());
    $conf = isset($conf['products']) ? $conf['products'] : sfConfig::get('app_store_email_products', 'never');
    if ( in_array($conf, array('never', false)) )
      return false;

    foreach ( array('only' => array(), 'css' => true, 'products' => true, 'barcode' => 'png', 'qrcode_only_id' => false, 'debug' => false) as $field => $value )
    if ( !isset($with[$field]) )
      $with[$field] = $value;

    sfApplicationConfiguration::getActive()->loadHelpers(array('I18N'));
    $products_html = '';

    // tickets w/ barcode
    if (!( isset($with['css']) && !$with['css'] ))
    {
      $products_html .= '<div style="clear: both"></div>';
      $products_html .= '<style type="text/css" media="all">'.file_get_contents(sfConfig::get('sf_web_dir').'/css/print-simplified-tickets.css').'</style>';
      if ( file_exists(sfConfig::get('sf_web_dir').'/private/print-simplified-tickets.css') )
        $products_html .= '<style type="text/css" media="all">'.file_get_contents(sfConfig::get('sf_web_dir').'/private/print-simplified-tickets.css').'</style>';
    }

    $content = array();
    if (!( isset($with['products']) && !$with['products'] ))
    foreach ( $this->BoughtProducts as $product )
    {
      if ( $with['only'] )
      {
        foreach ( $with['only'] as $key => $pdt )
        if ( $pdt instanceof Doctrine_Record )
          $with['only'][$key] = $pdt->id;

        if ( !in_array($product->id, $with['only']) )
          continue;
      }
      if ( $conf === 'e-product' && !$product->description_for_buyers )
        continue;
      $content[] = $c = $product->renderSimplified($with['barcode'], $with['qrcode_only_id'], $with['debug'])."\n";
    }

    $final = $products_html."\n".implode("\n", $content);
    if ( $with['debug'] )
    {
      echo $final;
      die();
    }
    return $final;
  }
  /**
   * @return array
   */
  public function getDirectContacts()
  {
    $contacts = array();
    foreach ( $this->Tickets as $ticket )
    if ( $ticket->contact_id )
        $contacts[] = $ticket->DirectContact;
    return $contacts;
  }
}
