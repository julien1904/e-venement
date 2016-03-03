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
*    Copyright (c) 2006-2014 Baptiste SIMON <baptiste.simon AT e-glop.net>
*    Copyright (c) 2006-2014 Libre Informatique [http://www.libre-informatique.fr/]
*
***********************************************************************************/
?>
<?php

require_once dirname(__FILE__).'../../../../config/autoload.inc.php';

class pubConfiguration extends sfApplicationConfiguration
{
  public function setup()
  {
    if (!( sfContext::hasInstance() && get_class(sfContext::getInstance()->getConfiguration()) != get_class($this) ))
      $this->enablePlugins(array('liClassLoaderPlugin', 'sfDomPDFPlugin', 'liBarcodePlugin'));
    parent::setup();
  }
  public function configure()
  {
    $this->dispatcher->connect('pub.transaction_before_creation', array($this, 'triggerTransactionBeforeCreation'));
    $this->dispatcher->connect('pub.transaction_before_creation', array($this, 'recordWebOrigin'));
    $this->dispatcher->connect('pub.transaction_respawning', array($this, 'recordWebOrigin'));
  }
  public function initialize()
  {
    if (!( sfContext::hasInstance() && get_class(sfContext::getInstance()->getConfiguration()) != get_class($this) ))
      $this->enableSecondWavePlugins(sfConfig::get('app_options_plugins', array()));
    ProjectConfiguration::initialize();
    sfConfig::set('app_options_session', sfConfig::get('app_options_session','pub'));
  }
  
  public function shut()
  {
    if ( !sfConfig::get('app_open',false) )
    {
      header('Content-Type: text/html; charset=utf-8');
      echo pubConfiguration::getText('app_texts_when_closed','This application is not opened');
      throw new liOnlineSaleException(pubConfiguration::getText('app_texts_when_closed','This application is not opened'));
    }
  }
  
  public static function getText($var, $default = '')
  {
    $txt = sfConfig::get($var, $default);
    $culture = sfContext::hasInstance() && sfContext::getInstance()->getUser() instanceof sfUser
      ? sfContext::getInstance()->getUser()->getCulture()
      : false;
    
    // no translation
    if ( !is_array($txt) )
      return $txt;
    
    // no translation available, keep the first term coming
    if ( !$culture )
      return array_shift($txt);
    
    // the current translation
    if ( isset($txt[$culture]) )
      return $txt[$culture];
    
    // no translation available
    foreach ( $txt as $culture => $value )
    if ( strlen($culture) > 2 )
      return $txt;
    
    // the first translation
    return array_shift($txt);
  }
  
  public static function addMemberCard(Transaction $transaction, $member_card_type_id, $detail = NULL)
  {
    $mcf = new MemberCardForm;
    $arr = array();
    
    $mct = Doctrine::getTable('MemberCardType')->createQuery('mct')
      ->select('mct.*')
      ->addSelect('(SELECT count(mcpm.id) FROM MemberCardPriceModel mcpm WHERE mcpm.member_card_type_id = mct.id) AS nb_available')
      ->andWhere('mct.id = ?', $member_card_type_id)
      ->fetchOne();
    if ( $mct->nb_tickets_mini > $mct->nb_available )
      return;
    
    $arr['member_card_type_id'] = $member_card_type_id;
    $arr['created_at'] = date('Y-m-d');
    $arr['transaction_id'] = $transaction->id;
    $arr['contact_id'] = $transaction->contact_id;
    $arr['active'] = false;
    if ( $detail )
      $arr['detail'] = $detail;
    $arr[$mcf->getCSRFFieldName()] = $mcf->getCSRFToken();
    
    $arr['expire_at'] = sfConfig::has('project_cards_expiration_delay')
      ? date('Y-m-d H:i:s',strtotime(sfConfig::get('project_cards_expiration_delay')))
      : (strtotime(date('Y').'-'.sfConfig::get('project_cards_expiration_date')) > strtotime('now')
        ? date('Y').'-'.sfConfig::get('project_cards_expiration_date')
        : (date('Y')+1).'-'.sfConfig::get('project_cards_expiration_date'));
    
    $mcf->bind($arr);
    
    if ( !$mcf->isValid() )
      throw new liEvenementException('Error when adding member cards.');
    
    $transaction->MemberCards[] = $mcf->getObject();
    $mcf->save();
    foreach ( $mcf->getObject()->BoughtProducts as $bp )
    {
      $bp->integrated_at = NULL;
      $bp->save();
    }
    
    $mcps = sfContext::getInstance()->getUser()->getAttribute('pub.mc.autoadd_tickets', false) instanceof Doctrine_Collection
      ? sfContext::getInstance()->getUser()->getAttribute('pub.mc.autoadd_tickets')
      : new Doctrine_Collection('MemberCardPriceModel');
    foreach ( $mcf->getObject()->MemberCardType->MemberCardPriceModels as $mcpm )
    if ( $mcpm->autoadd )
    {
      if ( isset($mcps[$i = $mcpm->event_id.' '.$mcpm->price_id]) )
        $mcps[$i]->quantity = $mcps[$i]->quantity + $mcpm->quantity;
      else
        $mcps[$i] = $mcpm->copy();
    }
    sfContext::getInstance()->getUser()->setAttribute('pub.mc.autoadd_tickets', $mcps);
  }
  
  public function recordWebOrigin(sfEvent $event)
  {
    $context = sfContext::getInstance();
    $request = $context->getRequest();
    $params = $event->getParameters();
    if ( !sfContext::hasInstance() || !( isset($params['transaction']) && $params['transaction'] instanceof Transaction ))
    {
      error_log('Impossible to record Web Origin: no transaction given, or similar...');
      return;
    }
    $transaction = $params['transaction'];
    
    $wo = NULL;
    $event->getSubject()->getOriginId() && $wo = Doctrine::getTable('WebOrigin')->createQuery('wo')
      ->andWhere('wo.transaction_id = ?', $event->getSubject()->getOriginId())
      ->fetchOne();
    
    $origin = new WebOrigin;
    $origin->Transaction  = $transaction;
    $origin->first_page   = $wo ? $wo->first_page : $request->getUri();
    $origin->campaign     = $wo ? $wo->campaign : $request->getParameter('com');
    $origin->referer      = $wo ? $wo->referer : $request->getReferer();
    $origin->ipaddress    = $request->getRemoteAddress();
    $origin->user_agent   = $_SERVER['HTTP_USER_AGENT'].'';
    
    $origin->save();
    
    if ( $wo )
    {
      $wo->next_id = $origin->id;
      $wo->save();
    }
  }
  
  public function triggerTransactionBeforeCreation(sfEvent $event)
  {
    $conf = sfConfig::get('app_tickets_vel', array());
    if (!( isset($conf['email_auto_on_update']) && $conf['email_auto_on_update'] ))
      return;
    
    $params = $event->getParameters();
    $transaction = $params['transaction'];
    $transaction->send_an_email = true;
  }
  
  public function hardenIntegrity($redirect = true)
  {
    if ( !sfContext::hasInstance() )
      throw new liOnlineSaleException('Checking the cart integrity is not possible.');
    $sf_action = sfContext::getInstance()->getActionStack()->getLastEntry()->getActionInstance();
    $sf_action->getContext()->getConfiguration()->loadHelpers('I18N');
    
    // the global timeout (item timeout is done by the garbage collector)
    $timeout = sfConfig::get('app_timeout_global', '30 minutes');
    $end = strtotime('+'.$timeout, strtotime($sf_action->getUser()->getTransaction()->created_at));
    if ( time() > $end )
    {
      $sf_action->getUser()->resetTransaction();
      $sf_action->getUser()->setFlash('notice', __('Delay expired. Your order has been abandonned.'));
      $sf_action->redirect('@homepage');
    }
    
    $tickets = Doctrine_Query::create()->from('Ticket tck')
      ->andWhere('tck.price_id IS NULL')
      ->andWhere('tck.transaction_id = ?', $sf_action->getUser()->getTransactionId())
      ->delete()
      ->execute();
    
    $gauges = Doctrine_Query::create()->from('Gauge g')
      ->select('g.*, tck.*')
      ->leftJoin('g.Manifestation m')
      ->leftJoin('m.Event e')
      
      ->leftJoin('g.Tickets tck WITH tck.printed_at IS NULL AND tck.integrated_at IS NULL AND tck.duplicating IS NULL AND tck.cancelling IS NULL')
      ->andWhere('tck.transaction_id = ?', $sf_action->getUser()->getTransactionId())
      
      ->leftJoin('tck.Price p')
      ->leftJoin('p.Manifestations pm WITH pm.id = m.id')
      ->leftJoin('p.Users pu')
      
      ->execute()
    ;
    
    // to seat
    foreach ( $gauges as $gauge )
    {
      // SECURITY: not to sell forbidden products
      // the workspace + meta_event
      $q = Doctrine::getTable('Ticket')->createQuery('tck')
        ->select('tck.id')
        
        ->leftJoin('tck.Manifestation m')
        ->leftJoin('m.Event e')
        ->leftJoin('tck.Gauge g')
        ->andWhereNotIn('e.meta_event_id', array_keys($sf_action->getUser()->getMetaEventsCredentials()))
        ->andWhereNotIn('g.workspace_id', array_keys($sf_action->getUser()->getMetaEventsCredentials()))
        
        ->leftJoin('tck.Transaction t')
        ->leftJoin('t.Order o')
        ->andWhere('tck.transaction_id = ?', $sf_action->getUser()->getTransactionId())
        ->andWhere('tck.printed_at IS NULL AND tck.integrated_at IS NULL AND tck.duplicating IS NULL AND tck.cancelling IS NULL')
        ->andWhere('o.id IS NULL')
        
        ->andWhere('tck.price_id NOT IN (SELECT up.price_id FROM UserPrice up WHERE up.sf_guard_user_id = ?)', $sf_action->getUser()->getId())
      ;
      if ( $q->count() > 0 )
        $q->execute()->delete();
      
      // what ticket needs to be seated
      $to_seat = array();
      foreach ( $gauge->Tickets as $ticket )
      if ( !$ticket->seat_id )
        $to_seat[] = $ticket;
      
      // what can be done for that ?
      $seater = new Seater($gauge->id);
      $seats = $seater->findSeats(count($to_seat));
      foreach ( $seats as $seat )
      {
        $ticket = array_pop($to_seat);
        $ticket->Seat = $seat;
        $ticket->save(); // do it
      }
    }
    
    // orphans
    try {
      $orphans = $this->getOrphans($sf_action->getUser()->getTransaction(), array());
      if ( count($orphans) > 0 )
      {
        if ( !$redirect )
          return false;
        
        $gauge = array_pop($orphans);
        $orphan = array_pop($gauge); // arbitrary choice, the last gauge, to start from somewhere solving the results
        $sf_action->getContext()->getConfiguration()->loadHelpers('I18N');
         
        $sf_action->getUser()->setFlash('error', __('There are still some orphan seats generated by your selection, please choose other seats and follow eventual warnings on the plan after confirmation.'));
        $sf_action->redirect('manifestation/show?id='.$orphan['manifestation_id'].'#'.$orphan['gauge_id'].'#orphans');
      }
      
      return true;
    }
    catch ( liOnlineSaleException $e )
    {
      error_log('error, unable to find any orphan on this plateform: '.$e->getMessage());
      return true;
    }
  }
  
  public function getOrphans(Transaction $transaction, array $options)
  {
    foreach ( array('gauge_id', 'manifestation_id', 'seat_id', 'ticket_id') as $field )
    if ( !isset($options[$field]) )
      $options[$field] = false;
    
    $manif_details = true;
    
    // the query
    $q = Doctrine::getTable('Gauge')->createQuery('g', false)
      ->leftJoin('g.Workspace ws')
      ->leftJoin('ws.SeatedPlans sp')
      
      ->leftJoin('g.Manifestation m')
      ->leftJoin('m.Event e')
      ->leftJoin('e.Translation et')
      ->leftJoin('m.Location l')
      ->andWhere('l.id = sp.location_id')
      
      ->leftJoin('g.Tickets tck WITH tck.transaction_id = ?', $transaction->id)
      ->leftJoin('tck.Seat s')
    ;
    
    if ( $options['gauge_id'] )
    {
      $q->andWhere('g.id = ?', $options['gauge_id']);
      $manif_details = false;
    }
    else
      $q->andWhere('s.id IS NOT NULL');
    
    if ( $options['ticket_id'] )
      $q->andWhere('tck.id = ?', $options['ticket_id']);
    
    if ( $options['manifestation_id'] )
    {
      $q->andWhere('m.id = ?', $options['manifestation_id']);
      $manif_details = false;
    }
    
    if ( $options['seat_id'] )
    {
      $q->andWhere('tck.seat_id = ?', $options['seat_id']);
      $manif_details = false;
    }
    $gauges = $q->execute();
    
    if ( !$options )
      $q->andWhere('tck.transaction_id = ?', $transaction->id);
    
    $orphans = array();
    
    //throw new sfException('glop');
    // no gauge ?!
    if ( $gauges->count() == 0 )
      return $orphans;
    
    // gauges, one by one
    foreach ( $gauges as $gauge )
    {
      // preparing the field
      $seater = new Seater($gauge->id);
      $seats = new Doctrine_Collection('Seat');
      foreach ( $gauge->Tickets as $ticket )
        $seats[] = $ticket->Seat;
      
      // forging the json data
      foreach ( $seater->findOrphansWith($seats) as $orphan )
      {
        $orphans[$gauge->id][] = array(
          'seat_id'   => $orphan->id,
          'seat_name' => (string)$orphan,
          'gauge_id' => $gauge->id,
          'manifestation_id' => $gauge->manifestation_id,
          'seated_plan_id' => $orphan->seated_plan_id,
          'transaction_id' => $transaction->id,
          'gauge' => (string)$gauge,
          'manifestation' => (string)$gauge->Manifestation,
        );
      }
    }
    
    // now cleaning useless data
    foreach ( $orphans as $gid => $data )
    if ( !$orphans[$gid] )
      unset($orphans[$gid]);
    
    return $orphans;
  }

  public function initGarbageCollectors(sfCommandApplicationTask $task = NULL)
  {
    $this->task = $task;
    
    // destocked products, too old to stay out of the stock
    $this->addGarbageCollector('stocks', function(){
      $cart_timeout = sfConfig::get('app_timeout_global', '1 hour');
      $section = 'Stocks';
      $this->stdout($section, 'Incrementing stocks for elder products...', 'COMMAND');
      $q = Doctrine_Query::create()->from('Transaction t')
        ->leftJoin('t.BoughtProducts bp')
        ->andWhere('bp.integrated_at IS NULL AND bp.destocked = ? AND bp.product_declination_id IS NOT NULL', true)
        ->leftJoin('t.Order o')
        ->andWhere('o.id IS NULL')
        ->leftJoin('t.Payments p')
        ->andWhere('p.id IS NULL')
        ->leftJoin('bp.User u')
        ->andWhereIn('u.username', array_merge(array(sfConfig::get('app_user_templating', -1)), sfConfig::get('app_user_other_templates',array())))
        ->andWhere('bp.created_at < ?', $date = date('Y-m-d H:i:s', strtotime($cart_timeout.' ago')))
      ;
      $transactions = $q->execute();
      $nb = 0;
      foreach ( $transactions as $t )
      foreach ( $t->BoughtProducts as $bp )
      {
        $bp->destocked = false;
        $bp->save();
        $nb++;
      }
      $this->stdout($section, "[OK] $nb products restocked", 'INFO');
    });
    
    // too-old online transactions collector
    $this->addGarbageCollector('transactions', function(){
      $cart_timeout = sfConfig::get('app_timeout_global', '1 hour');
      $section = 'Transactions';
      $this->stdout($section, 'Closing old transactions...', 'COMMAND');
      $q = Doctrine_Query::create()->from('Transaction t')
        ->andWhere('t.closed = ?', false)
        ->leftJoin('t.Order o')
        ->andWhere('o.id IS NULL')
        ->leftJoin('t.Payments p')
        ->andWhere('p.id IS NULL')
        ->leftJoin('t.Tickets tck WITH tck.printed_at IS NOT NULL OR tck.integrated_at IS NOT NULL OR tck.duplicating IS NOT NULL OR tck.cancelling IS NOT NULL')
        ->andWhere('tck.id IS NULL')
        ->leftJoin('t.BoughtProducts bp WITH bp.integrated_at IS NOT NULL')
        ->andWhere('bp.id IS NULL')
        ->leftJoin('t.User u')
        ->andWhere('u.username = ?', sfConfig::get('app_user_templating', -1))
        ->andWhere('t.created_at < ?', $date = date('Y-m-d H:i:s', strtotime($cart_timeout.' ago')))
      ;
      $transactions = $q->execute();
      $nb = $transactions->count();
      foreach ( $transactions as $t )
      {
        $t->closed = true;
        $t->save();
      }
      $this->stdout($section, "[OK] $nb transactions closed", 'INFO');
    });
    
    return $this;
  }
}
