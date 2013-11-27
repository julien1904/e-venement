<?php

/**
 * ticket actions.
 *
 * @package    e-venement
 * @subpackage ticket
 * @author     Baptiste SIMON <baptiste.simon AT e-glop.net>
 * @version    SVN: $Id: actions.class.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */
class ticketActions extends sfActions
{
 /**
  * Executes index action
  *
  * @param sfRequest $request A request object
  */
  public function executeIndex(sfWebRequest $request)
  {
    $this->redirect('ticket/sell');
  }
  
  public function executeShow(sfWebRequest $request)
  {
    if ( !$request->hasParameter('id') )
      return 'Select';

    $id = $request->getParameter('id');
    
    $this->ticket = Doctrine::getTable('Ticket')->createQuery('tck')
      ->leftJoin('tck.Transaction t')
      ->leftJoin('tck.User u')
      ->leftJoin('tck.Cancelling c1')
      ->leftJoin('tck.Cancelled c2')
      ->leftJoin('tck.Duplicatas d1')
      ->leftJoin('tck.Duplicated d2')
      ->leftJoin('tck.Price p')
      ->leftJoin('tck.Gauge g')
      ->leftJoin('g.Workspace w')
      ->leftJoin('tck.MemberCard mc')
      ->leftJoin('mc.Contact c')
      ->leftJoin('t.Contact tc')
      ->leftJoin('t.Professional tp')
      ->leftJoin('tp.Organism o')
      ->leftJoin('tck.Manifestation m')
      ->leftJoin('m.Event e')
      ->andWhere('tck.id = ?',$id)
      ->fetchOne();
    
    $this->versions = Doctrine::getTable('TicketVersion')->createQuery('v')
      ->select('v.*, (SELECT s.username FROM SfGuardUser s WHERE s.id = v.sf_guard_user_id) as user')
      ->andWhere('v.id = ?',$id)
      ->orderBy('v.version DESC')
      ->execute();
  }
  
  public function executeSell(sfWebRequest $request)
  {
    require('sell.php');
  }
  public function executeTouchscreen(sfWebRequest $request)
  {
    require('touchscreen.php');
  }
  
  public function executeBarcode(sfWebRequest $request)
  {
    require('barcode.php');
  }
  
  public function executeCancelBoot(sfWebRequest $request)
  {
    if ( intval($request->getParameter('id')) > 0 )
      $this->redirect('ticket/cancel?id='.intval($request->getParameter('id')));
    
    $this->pay = intval($request->getParameter('pay'));
    if ( $this->pay == 0 ) $this->pay = '';
    $this->setTemplate('cancelBoot');
  }
  public function executeCancel(sfWebRequest $request)
  {
    require('cancel.php');
  }
  public function executeCancelPartial(sfWebRequest $request)
  {
    require('cancel-partial.php');
  }
  public function executeBatchCancel(sfWebRequest $request)
  {
    require('cancel-batch.php');
  }
  public function executeDuplicate(sfWebRequest $request)
  {
    require('duplicate.php');
  }
  public function executeContact(sfWebRequest $request)
  {
    require('contact.php');
  }
  
  // add manifestation
  public function executeManifs(sfWebRequest $request)
  {
    require('manifs.php');
  }
  
  // tickets public
  function executeTicket(sfWebRequest $request)
  {
    require('ticket.php');
  }
  
  // validate the entire transaction
  public function executeValidate(sfWebRequest $request)
  {
    require('validate.php');
  }
  // reset the entire transaction
  public function executeReset(sfWebRequest $request)
  {
    require('reset.php');
  }
  public function executeClosed(sfWebRequest $request)
  {
    $this->transaction = $this->getRoute()->getObject();
    if ( !$this->transaction->closed )
    {
      $this->getUser()->setFlash('error', 'The transaction is not closed, verify and validate first');
      return $this->redirect('ticket/sell?id='.$this->transaction->id);
    }
  }
  
  public function executePrint(sfWebRequest $request)
  {
    require('print.php');
  }
  public function executeIntegrate(sfWebRequest $request)
  {
    require('integrate.php');
  }
  public function executeBatchIntegrate(sfWebRequest $request)
  {
    require('batch-integrate.php');
  }
  public function executeRfid(sfWebRequest $request)
  {
    require('rfid.php');
  }
  
  // remember / forget selected manifestations
  public function executeFlash(sfWebRequest $request)
  {
  }
  
  protected function redirectToSeatsAllocationIfNeeded($type)
  {
    // checks if any ticket needs a seat
    foreach ( $this->transaction->Tickets as $ticket )
    if ( !$ticket->numerotation
      && $ticket->Gauge->Workspace->seated
      && $seated_plan = $ticket->Manifestation->Location->getWorkspaceSeatedPlan($ticket->Gauge->workspace_id)
    )
    {
      // if so ask the user which one to use for this ticket
      sfContext::getInstance()->getConfiguration()->loadHelpers(array('I18N','Url'));
      $this->getUser()->setFlash('notice', __('You still have to give some tickets a seat...'));
      $this->getUser()->setFlash('referer', $_SERVER['REQUEST_URI'].(!$_SERVER['QUERY_STRING'] ? '?'.file_get_contents("php://input") : ''));
      
      $url = url_for('ticket/seatsAllocation?type='.$type.'&id='.$this->transaction->id.'&gauge_id='.$ticket->gauge_id);
      if ( isset($this->toprint) && $this->toprint )
        $url .= '&toprint[]='.implode('&toprint[]=',$this->toprint);
      $this->redirect($url);
      return false;
    }
    
    return true;
  }
  public function executeSeatsAllocation(sfWebRequest $request)
  {
    require('seats-allocation.php');
  }
  public function executeGiveASeat(sfWebRequest $request)
  {
    return require('give-a-seat.php');
  }
  public function executeResetASeat(sfWebRequest $request)
  {
    return require('reset-a-seat.php');
  }
  
  public function executeAccounting(sfWebRequest $request, $printed = true, $manifestation_id = false)
  {
    require('accounting.php');
  }
  // order
  public function executeOrder(sfWebRequest $request)
  {
    return require('order.php');
  }
  public function executeRecordAccounting(sfWebRequest $request)
  {
    $accounting = new RawAccounting();
    $invoice = Doctrine::getTable('Invoice')->fetchOneById(intval($request->getParameter('invoice_id')));
    if ( !$invoice ) throw new sfError404Exception();
    
    $accounting->content = $request->getParameter('content');
    $accounting->invoice_id = $invoice->id;
    
    $accounting->save();
    return sfView::NONE;
  }
  // invoice
  public function executeInvoice(sfWebRequest $request)
  {
    $this->executeAccounting($request,true,$request->hasParameter('partial') ? $request->getParameter('manifestation_id') : false);
    
    $this->partial = false;
    $this->invoice = false;
    if ( $request->hasParameter('partial') && intval($request->getParameter('manifestation_id')) > 0 )
    {
      $this->partial = true;
      foreach ( $this->transaction->Invoice as $key => $invoice )
      if ( $invoice->manifestation_id == intval($request->getParameter('manifestation_id')) )
        $this->invoice = $invoice;
      
      if ( !$this->invoice )
      {
        $this->invoice = new Invoice();
        $this->transaction->Invoice[] = $this->invoice;
      }
      $this->invoice->manifestation_id = intval($request->getParameter('manifestation_id'));
    }
    else
    {
      foreach ( $this->transaction->Invoice as $invoice )
      if ( is_null($invoice->manifestation_id) )
        $this->invoice = $invoice;
      
      if ( !$this->invoice )
        $this->invoice = new Invoice();
      $this->transaction->Invoice[] = $this->invoice;
    }
    
    $this->invoice->updated_at = date('Y-m-d H:i:s');
    $this->invoice->save();
  }
  
  public function executeRespawn(sfWebRequest $request)
  {
    $this->transaction_id = $request->hasParameter('id') ? intval($request->getParameter('id')) : '';
  }
  public function executeControl(sfWebRequest $request)
  {
    require('control.php');
  }
  public function executeBatchControl(sfWebRequest $request)
  {
    require('batch-control.php');
  }
  
  public function executeAccess(sfWebRequest $request)
  {
    $id = intval($request->getParameter('id'));
    
    if ( $request->getParameter('reopen') && $this->getUser()->hasCredential('tck-unblock') )
    {
      $q = new Doctrine_Query();
      $q->from('Transaction')
        ->where('id = ?',$id);
      if ( $this->transaction = $q->fetchOne() )
      {
        $this->transaction->closed = false;
        $this->transaction->save();
      }
    }
    
    $this->redirect('ticket/sell?id='.$id);
    //return sfView::NONE;
  }
  
  public function executeGauge(sfWebRequest $request)
  {
    require('gauge.php');
  }
  
  // single cash deal, eg: for cancellations
  public function executePay(sfWebRequest $request)
  {
    if ( !($this->getRoute() instanceof sfObjectRoute) )
    {
      if ( intval($request->getParameter('id')) > 0 )
        $this->redirect('ticket/pay?id='.intval($request->getParameter('id')));
      else
      {
        $this->getUser()->setFlash('error','You gave bad informations... try again.');
        $this->redirect('ticket/cancel');
      }
    }
    else
    {
      $q = Doctrine_Query::create()
        ->from('Transaction t')
        ->leftJoin('t.Tickets tck ON tck.transaction_id = t.id AND tck.duplicating IS NULL AND (tck.printed_at IS NOT NULL OR tck.integrated_at IS NOT NULL OR tck.cancelling IS NOT NULL)')
        ->leftJoin('tck.Duplicatas duplis')
        ->leftJoin('tck.Cancelled cancelled')
        ->leftJoin('tck.Manifestation m')
        ->leftJoin('tck.Price p')
        ->orderBy('p.name, tck.price_id, tck.id')
        ->andWhere('t.id = ?',$request->getParameter('id'));
      
      $this->transaction = $q->fetchOne();
    }
  }
  
  public function executeContactPrices(sfWebRequest $request)
  {
    return require('contact-prices.php');
  }
  
  // partial printing
  public function executePartial(sfWebRequest $request)
  {
    require('partial.php');
  }
  
  // returns how many tickets exist for a contact on a metaevent for given price names
  protected function createTransactionForm($excludes = array(), $parameters = null)
  {
    // contact_id
    // manifesatation_id
    // tarif_names []
    require('transaction-form.php');
  }
}
