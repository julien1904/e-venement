<?php

/**
 * PluginPayment
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @package    e-venement
 * @subpackage model
 * @author     Baptiste SIMON <baptiste.simon AT e-glop.net>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
abstract class PluginPayment extends BasePayment
{
  public function preSave($event)
  {
    // to redirect stuff if we're not here for an insert
    // this is a hack to use preSave() to overcome a problem on tranasction_id updates...
    if ( !$this->isNew() )
      return parent::preSave($event);
    
    $cards = array();
    if ( $this->Method->member_card_linked )
    {
      if ( is_null($this->member_card_id) )
      {
        $transaction = Doctrine::getTable('Transaction')->createQuery('t')
          ->leftJoin('t.Contact c')
          ->leftJoin('c.MemberCards mc')
          ->andWhere('t.id = ?',$this->transaction_id)
          ->andWhere('mc.active = true')
          ->fetchOne();
        
        foreach ( $transaction->Contact->MemberCards as $card )
        if ( strtotime($card->expire_at) > strtotime('now')
          && strtotime('now') > strtotime($card->created_at)
          && $card->value >= $this->value )
          $cards[$card->id] = $card;
        
        if ( count($cards) == 1 )
        {
          $card = array_pop($cards);
          $this->member_card_id = $card->id;
        }
        elseif ( count($cards) > 1 )
        {
          $this->member_card_id = $cards;
          throw new liMemberCardPaymentException("There is more than one available member cards for this Payment... Choose one.");
        }
      }
      
      if ( is_null($this->member_card_id) || is_array($this->member_card_id) )
        throw new liEvenementException('No MemberCard linked with this Payment whereas its Method requires it.');
    }
    
    return parent::preSave($event);
  }
}
