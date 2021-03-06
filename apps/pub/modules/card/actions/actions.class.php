<?php

/**
 * card actions.
 *
 * @package    symfony
 * @subpackage card
 * @author     Your name here
 * @version    SVN: $Id: actions.class.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */
class cardActions extends sfActions
{
  public function preExecute()
  {
    $this->dispatcher->notify(new sfEvent($this, 'pub.pre_execute', array('configuration' => $this->configuration)));
    parent::preExecute();
  }
  public function executeIndex(sfWebRequest $request)
  {
    //$this->redirectIfNotAuthenticated();
    
    $this->member_card_types = Doctrine::getTable('MemberCardType')->createQuery('mct')
      ->leftJoin('mct.Users u')
      ->leftJoin('mct.Translation translation WITH translation.lang = ?', $this->getUser()->getCulture())
      ->andWhere('u.id = ?',$this->getUser()->getId())
      ->orderBy('translation.description')
      ->execute();
    
    $this->mct = array();
    if ( $this->getUser()->getTransaction()->MemberCards->count() > 0 )
    foreach ( $this->getUser()->getTransaction()->MemberCards as $mc )
    {
      if ( !isset($this->mct[$mc->member_card_type_id]) )
        $this->mct[$mc->member_card_type_id] = 0;
      $this->mct[$mc->member_card_type_id]++;
    }
  }
  
  public function executeDel(sfWebRequest $request)
  {
    $this->getContext()->getConfiguration()->loadHelpers('I18N');
    $q = Doctrine::getTable('MemberCard')->createQuery('mc')
      ->andWhere('mc.transaction_id = ?', $this->getUser()->getTransactionId())
      ->andWhere('mc.active = ?', false)
      ->andWhere('mc.id = ?', $request->getParameter('id',0))
    ;
    $this->forward404Unless($mcs = $q->execute());
    $mcs->delete();
    $this->getUser()->setFlash('success', __('Your items were successfully removed from your cart.'));
    $this->redirect('store/index');
  }
  
  public function executeOrder(sfWebRequest $request)
  {
    $transaction = $this->getUser()->getTransaction();
    //$this->redirectIfNotAuthenticated();
    
    // empty'ing member cards from transaction
    if ( !$request->hasAttribute('append') )
    {
      foreach ( $transaction->MemberCards as $mc )
      foreach ( $mc->MemberCardPrice as $mcp )
      foreach ( $transaction->Tickets as $i => $ticket )
      if ( $ticket->price_id = $mcp->price_id
        && $ticket->Manifestation->event_id == $mcp->event_id )
      {
        unset($transaction->Tickets[$i]);
        $ticket->delete();
        break;
      }
      $transaction->MemberCards->delete();
    }
    
    $order = $request->getParameter('member_card_type');
    $cpt = 0;
    foreach ( $order as $id => $qty )
    if ( intval($qty) > 0 )
    {
      $cpt += intval($qty);
      if ( $cpt <= sfConfig::get('app_member_cards_max_per_transaction', 3) )
      for ( $i = 0 ; $i < intval($qty) ; $i++ )
        $this->getContext()->getConfiguration()->addMemberCard($transaction, $id);
    }
    
    $this->redirect('cart/show');
  }
  
  protected function isAuthenticated()
  {
    try { return $this->getUser()->getContact(); }
    catch ( liEvenementException $e )
    { return false; }
  }
  protected function redirectIfNotAuthenticated()
  {
    if ( self::isAuthenticated() )
      return true;

    $this->getContext()->getConfiguration()->loadHelpers('I18N');
    $this->getUser()->setFlash('error',__('To order a pass, you need to be authenticated or Create an account'));
    $this->forward('login','index');
    return false;
  }
}
