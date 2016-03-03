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
*    Foundation, Inc., 5'.$rank.' Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*
*    Copyright (c) 2006-2011 Baptiste SIMON <baptiste.simon AT e-glop.net>
*    Copyright (c) 2006-2011 Libre Informatique [http://www.libre-informatique.fr/]
*
***********************************************************************************/
?>
<?php

require_once dirname(__FILE__).'/../lib/manifestationGeneratorConfiguration.class.php';
require_once dirname(__FILE__).'/../lib/manifestationGeneratorHelper.class.php';

/**
 * manifestation actions.
 *
 * @package    e-venement
 * @subpackage manifestation
 * @author     Baptiste SIMON <baptiste.simon AT e-glop.net>
 * @version    SVN: $Id: actions.class.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */
class manifestationActions extends autoManifestationActions
{
  public function executeGaugesAll(sfWebRequest $request)
  {
    $this->redirect('manifestation/show?id='.$request->getParameter('id').'#sf_fieldset_workspaces');
  }
  public function executePossibleIncomes(sfWebRequest $request)
  {
    $this->json = array('min' => array('value' => 0, 'currency' => NULL), 'max' => array('value' => 0, 'currency' => NULL),);
    
    $q = Doctrine::getTable('Gauge')->createQuery('g',true,true)
      ->andWhere('g.manifestation_id = ?', $request->getParameter('id'))
      
      ->leftJoin('g.PriceGauges pg')
      ->leftJoin('pg.Price pgp')
      ->leftJoin('pgp.Users pgpu WITH pgpu.id = ?', $this->getUser()->getId())
      
      ->leftJoin('g.Manifestation m')
      ->leftJoin('m.PriceManifestations pm WITH pm.price_id != pg.price_id')
      ->leftJoin('pm.Price pmp')
      ->leftJoin('pmp.Users pmpu WITH pmpu.id = ?', $this->getUser()->getId())
      
      ->addSelect('(CASE WHEN COUNT(pm.id) = 0 OR MAX(pg.value) > MAX(pm.value) THEN MAX(pg.value) ELSE MAX(pm.value) END) AS max')
      ->addSelect('(CASE WHEN COUNT(pm.id) = 0 OR MIN(pg.value) > MIN(pm.value) THEN MIN(pg.value) ELSE MIN(pm.value) END) AS min')
      //->addSelect('(CASE WHEN COUNT(pm.id)+COUNT(pg.id) > 0 THEN ((SUM(pm.value)+SUM(pg.value))/(COUNT(pm.id)+COUNT(pg.id)) ELSE 0 END) AS avg')
      ->addSelect('g.value')
      ->groupBy('g.id, g.value')
    ;
    
    foreach ( $q->fetchArray() as $gauge )
    foreach ( array('max', 'min') as $field )
      $this->json[$field]['value'] += $gauge[$field]*$gauge['value'];
    $this->getContext()->getConfiguration()->loadHelpers('Number');
    foreach ( array('max', 'min') as $field )
      $this->json[$field]['currency'] = format_currency($this->json[$field]['value'], '€');
    
    if (!( sfConfig::get('sf_web_debug', true) && $request->hasParameter('debug') ))
      return 'Json';
  }
  public function executeStatsMetaData(sfWebRequest $request)
  {
    require __DIR__.'/stats-meta-data.php';
    if (!( sfConfig::get('sf_web_debug', true) && $request->hasParameter('debug') ))
      return 'Json';
  }
  public function executeStatsFillingData(sfWebRequest $request)
  {
    require __DIR__.'/stats-filling-data.php';
    if (!( sfConfig::get('sf_web_debug', true) && $request->hasParameter('debug') ))
      return 'Json';
  }
  public function executeAddGaugePrice(sfWebRequest $request)
  {
    $this->json = array('success' => array(), 'error' => array());
    
    if ( sfConfig::get('sf_web_debug', false) && $request->hasParameter('debug') )
    {
      $this->getResponse()->setContentType('text/html');
      $this->setLayout('layout');
    }
    else
      sfConfig::set('sf_web_debug', false);
    $this->json = array();
    $error = 'A problem occurred during the price creation / update (you should better reload your screen)';
    
    if (!( $pg = $request->getParameter('price_gauge') ))
    {
      $this->json['error']['message'] = $error;
      return 'Success';
    }
    
    $form = new PriceGaugeForm(intval($pg['id']) > 0 ? Doctrine::getTable('PriceGauge')->find($pg['id']) : NULL);
    $form->bind($pg);
    if ( !$form->isValid() )
    {
      error_log($form->getErrorSchema());
      $this->json['error']['message'] = $error;
      return 'Success';
    }
    
    $form->save();
    $this->json['success']['id'] = $form->getObject()->id;
    $this->json['success']['message'] = 'Price created or updated for this gauge';
  }
  public function executeSlideHappensAt(sfWebRequest $request)
  {
    require(dirname(__FILE__).'/slide-happens-at.php');
    return sfView::NONE;
  }
  
  public function executeSlideDuration(sfWebRequest $request)
  {
    require(dirname(__FILE__).'/slide-duration.php');
    return sfView::NONE;
  }
  
  // needs previously cleaned $request->getParameter('ids'), usually it's done by executeBatch()
  public function executeBestFreeSeat(sfWebRequest $request)
  {
    $this->getContext()->getConfiguration()->loadHelpers('Url');
    
    $q = Doctrine::getTable('Manifestation')->createQuery('m');
    if ( $request->getParameter('ids') )
      $q->andWhereIn('e.id', $request->getParameter('ids'));
    else
      $q->andWhere('m.happens_at > NOW()')
        ->limit(20);
    $manifs = $q->execute();
    
    $this->seats = array();
    $seated_plans = array();
    foreach ( $manifs as $manif )
    foreach ( $manif->getBestFreeSeat(5) as $seat )
    {
      if ( !isset($seated_plans[$seat->seated_plan_id]) )
        $seated_plans[$seat->seated_plan_id] = $seat->SeatedPlan;
      $workspaces = array();
      foreach ( $seated_plans[$seat->seated_plan_id]->Workspaces as $ws )
        $workspaces[] = (string)$ws;
      
      
      $this->seats[$seat->rank.'--'.$manif->id.'-'.$seat->id] = array(
        'rank'              => $seat->rank,
        'name'              => (string)$seat,
        'id'                => $seat->id,
        'event'             => (string)$manif->Event,
        'manifestation'     => (string)$manif,
        'workspaces'        => implode("\n", $workspaces),
        'manifestation_url' => url_for('manifestation/show?id='.$manif->id, true),
        'sell_url'          => url_for('manifestation/sell?id='.$manif->id, true),
        'happens_at'        => $manif->happens_at,
        'happens_at_txt'    => $manif->mini_date,
      );
    }
  }
  public function executeBatchBestFreeSeat(sfWebRequest $request)
  { $this->forward('manifestation', 'bestFreeSeat'); }
  
  public function executeSell(sfWebRequest $request)
  {
    $this->getContext()->getConfiguration()->loadHelpers('CrossAppLink');
    
    $this->forward404Unless($request->hasParameter('id'));
    $this->redirect(cross_app_url_for('tck',
      'transaction/new#'.
      ($this->getContext()->getConfiguration()->getApplication() == 'museum' ? 'museum' : 'manifestations').
      '-'.$request->getParameter('id')
    ));
  }
  
  public function executeExport(sfWebRequest $request)
  {
    require(dirname(__FILE__).'/export.php');
    return sfView::NONE;
  }
  public function executeCsv(sfWebRequest $request)
  {
    require(dirname(__FILE__).'/csv.php');
  }
  public function executeDuplicate(sfWebRequest $request)
  {
    $this->getContext()->getConfiguration()->loadHelpers('I18N');
    
    $manif = Doctrine_Query::create()->from('Manifestation m')
      ->leftJoin('m.PriceManifestations p')
      ->leftJoin('m.Gauges g')
      ->leftJoin('m.Organizers o')
      ->andWhere('m.id = ?',$request->getParameter('id',0))
      ->fetchOne()
      ->duplicate();
    
    $this->getUser()->setFlash('notice',__('The manifestation has been duplicated successfully.'));
    $this->redirect('manifestation/edit?id='.$manif->id);
  }
  public function executePeriodicity(sfWebRequest $request)
  {
    require(dirname(__FILE__).'/periodicity.php');
  }
  public function executeNew(sfWebRequest $request)
  {
    if ( !$this->getUser()->hasCredential('event-reservation-change-contact') && !$this->getUser()->getContact() )
    {
      if ( $request->hasParameter('event') )
        $event_id = $request->hasParameter('event')
          ? Doctrine::getTable('Event')->findOneBySlug($request->getParameter('event'))->id
          : $event_id = 0;
      
      $this->getUser()->setFlash('error','You cannot access this object, you do not have the required credentials.');
      $this->redirect($event_id ? 'event/show?id='.$event_id : 'event/index');
    }
    
    parent::executeNew($request);
    
    if ( $request->getParameter('event') )
    {
      $event = Doctrine::getTable('Event')->findOneBySlug($request->getParameter('event'));
      if ( $event )
        $this->form->configureEvent($event);
    }
    if ( $request->getParameter('location') )
    {
      $location = Doctrine::getTable('Location')->findOneBySlug($request->getParameter('location'));
      if ( $location->id )
      $this->form->setDefault('location_id', $location->id);
    }
    if ( $request->getParameter('start') )
      $this->form->setDefault('happens_at', $request->getParameter('start')/1000);
    if ( $request->getParameter('start') && $request->getParameter('end') )
      $this->form->setDefault('duration', ($request->getParameter('end') - $request->getParameter('start'))/1000);
    
    // booking_list
    if ( ($list = $request->getParameter('booking_list', $this->getUser()->getFlash('booking_list',array())))
      && is_array($list) )
      $this->form->setDefault('booking_list', $list);
  }
  
  public function executeAjax(sfWebRequest $request)
  {
    $charset = sfConfig::get('software_internals_charset');
    $search  = iconv($charset['db'],$charset['ascii'],strtolower($request->getParameter('q')));
    $museum  = $this->getContext()->getConfiguration()->getApplication() == 'museum';
    
    $eids = array();
    if ( $search )
    {
      $e = Doctrine_Core::getTable('Event')->search($search.'*',
        Doctrine::getTable('Event')->createQuery('e')
          ->andWhere('e.museum = ?', $museum)
          ->andWhereIn('e.meta_event_id', array_keys($this->getUser()->getMetaEventsCredentials()))
      );
      foreach ( $e->execute() as $event )
        $eids[] = $event['id'];
    }
    
    if (!( $max = $request->getParameter('max',sfConfig::get('app_manifestations_max_ajax')) ))
    {
      $conf = sfConfig::get('app_transaction_manifs', array());
      $max = isset($conf['max_display']) && $conf['max_display'] ? $conf['max_display'] : 10;
    }
    
    $q = Doctrine::getTable('Manifestation')->createQuery('m')
      ->andWhere('e.museum = ?', $museum)
      ->leftJoin('m.Color c')
      ->orderBy('m.happens_at')
      ->limit($request->getParameter('limit',$max));
    if ( $eids )
      $q->andWhereIn('m.event_id',$eids);
    elseif ( $search )
      $q->andWhere('m.event_id IS NULL');
    
    if ( $e = $request->getParameter('except',false) )
      $q->andWhereNotIn('m.id', is_array($e) ? $e : array($e));
    
    if ( $request->hasParameter('display_by_default') )
      $q->andWhere('e.display_by_default = ?',true);
    
    $q = EventFormFilter::addCredentialsQueryPart($q);
    
    if ( !$search
      || $request->hasParameter('later')
      || $request->getParameter('except_transaction',false) && !$this->getUser()->hasCredential('tck-unblock') )
      $q->andWhere("manifestation_ends_at(m.happens_at, m.duration) > NOW()");
    
    // specific criterias
    switch ( $request->getParameter('for') ) {
    case 'grp':
      $q->andWhere('g.workspace_id IN (SELECT gws.workspace_id FROM GroupWorkspace gws)');
      break;
    }
    
    $manifestations = $q->select('m.*, e.*, c.*')->execute();
    
    $this->getContext()->getConfiguration()->loadHelpers('Url');
    $manifs = array();
    foreach ( $manifestations as $manif )
    {
      $go = true;
      if ( $request->getParameter('except_transaction',false) )
      {
        $go = $manif->reservation_confirmed && $manif->Gauges->count() > 0;
        $go = $go && Doctrine_Query::create()->from('ticket tck')
          ->andWhere('tck.manifestation_id = ?', $manif->id)
          ->andWhere('tck.transaction_id = ?', intval($request->getParameter('except_transaction')))
          ->count() == 0;
      }
      
      if ( $go )
      {
        $short = sfConfig::get('app_manifestation_prefer_short_name', true);
        $arr = array(
          'name'  => $manif->getName($short).(sfConfig::get('app_manifestation_show_location_ajax', false) ? ' '.$manif->Location : ''),
          'color' => (string)$manif->Color,
          'gauge_url' => url_for('gauge/state?json=true&manifestation_id='.$manif->id, true),
        );
        
        if ( $request->hasParameter('keep-order') )
          $manifs[] = $arr + array('id'    => $manif->id);
        else
        {
          $manifs[$manif->id] = $request->hasParameter('with_colors')
            ? $arr
            : $manif->getName($short);
        }
      }
    }
    
    if ( $request->hasParameter('debug') && $this->getContext()->getConfiguration()->getEnvironment() == 'dev' )
    {
      $this->getResponse()->setContentType('text/html');
      sfConfig::set('sf_debug',true);
      $this->setLayout('layout');
    }
    else
    {
      sfConfig::set('sf_debug',false);
      sfConfig::set('sf_escaping_strategy', false);
    }
    
    $this->json = $manifs;
  }

  public function executeList(sfWebRequest $request)
  {
    require(dirname(__FILE__).'/list.php');
  }
  public function executeEventList(sfWebRequest $request)
  {
    if ( !$request->getParameter('id') )
      $this->forward('manifestation','index');
    
    $this->event_id = $request->getParameter('id');
    
    $this->pager = $this->configuration->getPager('Contact');
    $this->pager->setMaxPerPage(10);
    $this->pager->setQuery(
      $q = EventFormFilter::addCredentialsQueryPart(
        Doctrine::getTable('Manifestation')->createQueryByEventId($this->event_id)
        ->select('*, g.*, w.*, wuo.*, l.*, tck.*, m.happens_at > NOW() AS after, (CASE WHEN happens_at < NOW() THEN NOW()-happens_at ELSE happens_at-NOW() END) AS before')
        ->andWhere('m.reservation_confirmed = TRUE OR m.contact_id = ? OR ?', array(
          $this->getUser()->getContactId(),
          $this->getUser()->hasCredential(array(
            'event-access-all',
          ), false)))
        //->leftJoin('m.Tickets tck')
        ->orderBy('after DESC, before')
    ));
    $this->pager->setPage($request->getParameter('page') ? $request->getParameter('page') : 1);
    $this->pager->init();
  }
  public function executeLocationList(sfWebRequest $request)
  {
    if ( !$request->getParameter('id') )
      $this->forward('manifestation','index');
    
    $place = !$request->hasParameter('resource');
    
    $this->location_id = $request->getParameter('id');
    
    $this->pager = $this->configuration->getPager('Manifestation');
    $this->pager->setMaxPerPage(10);
    $this->pager->setQuery(
      EventFormFilter::addCredentialsQueryPart(
        Doctrine::getTable('Manifestation')->createQueryByLocationId($this->location_id)
        ->select('m.*, e.*, c.*, g.*, l.*, w.*, wuo.*')
        ->leftJoin('m.Color c')
        ->andWhere('m.reservation_confirmed = TRUE OR m.contact_id = ?', $this->getUser()->getContactId())
        ->addSelect('m.happens_at > NOW() AS after, (CASE WHEN ( m.happens_at < NOW() ) THEN NOW()-m.happens_at ELSE m.happens_at-NOW() END) AS before')
        //->addSelect('tck.*')
        //->leftJoin('m.Tickets tck')
        ->orderBy('before')
    ));
    $this->pager->setPage($request->getParameter('page') ? $request->getParameter('page') : 1);
    $this->pager->init();
  }
  
  public function executeTemplating(sfWebRequest $request)
  {
    require(dirname(__FILE__).'/templating.php');
  }
  
  public function executeBatchChangeEvent(sfWebRequest $request)
  {
    $this->getContext()->getConfiguration()->loadHelpers('I18N');
    
    if (!( intval($request->getParameter('batch_event_id')).'' === ''.$request->getParameter('batch_event_id')
      && $event = Doctrine::getTable('Event')->find($request->getParameter('batch_event_id')) ))
    {
      $this->getUser()->setFlash('error', __('You must provide a valid event in order to apply the selected manifestations to.'));
      $this->redirect('manifestation/index');
    }
    
    foreach ( $manifs = Doctrine::getTable('Manifestation')->createQuery('m', true)
      ->andWhereIn('m.id', $request->getParameter('ids'))
      ->execute() as $manif )
      $manif->Event = $event;
    $manifs->save();
    
    $this->getUser()->setFlash('success', __('The provided manifestations have been applied to "%%event%%".', array('%%event%%' => $event)));
    $this->redirect('manifestation/index');
  }
  public function executeBatchPeriodicity(sfWebRequest $request)
  {
    $arg = 'periodicity[manifestation_id][%%i%%]=';
    $args = array();
    foreach ( $request->getParameter('ids') as $i => $id )
      $args[] = str_replace('%%i%%', $i, $arg).$id;
    $this->redirect('manifestation/periodicity?'.implode('&', $args));
  }
  
  protected function securityAccessFiltering(sfWebRequest $request, $deep = true)
  {
    if ( intval($request->getParameter('id')).'' !== ''.$request->getParameter('id') )
      return;
    
    $sf_user = $this->getUser();
    $manifestation = $this->getRoute()->getObject();
    if ( !$sf_user->isSuperAdmin() && !in_array($manifestation->Event->meta_event_id,array_keys($sf_user->getMetaEventsCredentials())) )
    {
      $this->getUser()->setFlash('error', $error = "You cannot access this object, you do not have the required credentials.");
      $this->redirect('@event');
    }
    
    $config = sfConfig::get('app_manifestation_reservations',array('enable' => false));
    if ( !$sf_user->hasCredential('event-manif-edit-confirmed') && !(isset($config['let_restricted_users_confirm']) && $config['let_restricted_users_confirm']) )
      error_log('The user #'.$sf_user->id.' has no credential for edition on manifestation #'.$manifestation->id);
    if ( $deep )
    if ( $manifestation->contact_id !== $sf_user->getContactId() && !$sf_user->hasCredential('event-access-all')
      || $manifestation->reservation_confirmed && !$sf_user->hasCredential('event-manif-edit-confirmed') && $manifestation->contact_id !== $sf_user->getContactId()
      || !(isset($config['let_restricted_users_confirm']) && $config['let_restricted_users_confirm']) && !$sf_user->hasCredential('event-manif-edit-confirmed') )
    {
      $this->getUser()->setFlash('error', $error = "You cannot edit this object, you do not have the required credentials.");
      $this->redirect('manifestation/show?id='.$manifestation->id);
    }
  }
  
  public function executeDelete(sfWebRequest $request)
  {
    try {
      $this->securityAccessFiltering($request);
      
      $request->checkCSRFProtection();
      $this->dispatcher->notify(new sfEvent($this, 'admin.delete_object', array('object' => $this->getRoute()->getObject())));
      $this->manifestation = $this->getRoute()->getObject();
      $eid = $this->manifestation->event_id;
      $this->manifestation->delete();
      $this->getUser()->setFlash('notice', 'The item was deleted successfully.');
      $this->redirect('event/show?id='.$eid);
    }
    catch ( Doctrine_Connection_Exception $e )
    {
      $this->getContext()->getConfiguration()->loadHelpers('I18N');
      $this->getUser()->setFlash('error',__("Deleting this object has been canceled because of remaining links to externals (like tickets)."));
      $this->redirect('manifestation/show?id='.$this->getRoute()->getObject()->id);
    }
  }
  public function executeEdit(sfWebRequest $request)
  {
    $this->securityAccessFiltering($request);
    parent::executeEdit($request);
    
    $this->getContext()->getConfiguration()->loadHelpers('CrossAppLink');
    $museum = $this->getContext()->getConfiguration()->getApplication() == 'museum';
    if ( $this->manifestation->Event->museum && !$museum )
      $this->redirect(cross_app_url_for('museum', 'manifestation/edit?id='.$this->manifestation->id));
    elseif ( !$this->manifestation->Event->museum && $museum )
      $this->redirect(cross_app_url_for('event', 'manifestation/edit?id='.$this->manifestation->id));
    
    //$this->form->prices = $this->getPrices();
    //$this->form->spectators = $this->getSpectators();
    //$this->form->unbalanced = $this->getUnbalancedTransactions();
  }
  public function executeUpdate(sfWebRequest $request)
  {
    $this->securityAccessFiltering($request);
    parent::executeUpdate($request);
    //$this->form->prices = $this->getPrices();
    //$this->form->spectators = $this->getSpectators();
    //$this->form->unbalanced = $this->getUnbalancedTransactions();
  }
  public function executeShow(sfWebRequest $request)
  {
    $this->securityAccessFiltering($request, false);
    $this->manifestation = $this->getRoute()->getObject();
    $this->forward404Unless($this->manifestation);
    
    $this->getContext()->getConfiguration()->loadHelpers('CrossAppLink');
    $museum  = $this->getContext()->getConfiguration()->getApplication() == 'museum';
    if ( $this->manifestation->Event->museum && !$museum )
      $this->redirect(cross_app_url_for('museum', 'manifestation/show?id='.$this->manifestation->id));
    elseif ( !$this->manifestation->Event->museum && $museum )
      $this->redirect(cross_app_url_for('event', 'manifestation/show?id='.$this->manifestation->id));
    
    $this->form = $this->configuration->getForm($this->manifestation);
    //$this->form->prices = $this->getPrices();
    //$this->form->spectators = $this->getSpectators();
    $this->form->unbalanced = $this->getUnbalancedTransactions();
  }
  public function executeVersions(sfWebRequest $request)
  {
    $this->executeShow($request);
    
    if ( !($v = $request->getParameter('version',false)) )
      $v = $this->manifestation->version > 1 ? $this->manifestation->version - 1 : 1;
    
    if ( intval($v).'' == ''.$v )
    foreach ( $this->manifestation->Version as $version )
    if ( $version->version == $v )
    {
      $this->manifestation->current_version = $version;
      break;
    }
    
    if ( !$this->manifestation->current_version )
    {
      $this->getContext()->getConfiguration()->loadHelpers('I18N');
      $this->getUser()->setFlash('error', __('You have requested the version #%%v%% that does not exist.', array('%%v%%' => $v)));
      $this->redirect('manifestation/show?id='.$this->manifestation->id);
    }
  }
  
  public function executeShowSpectators(sfWebRequest $request)
  {
    $this->setLayout('nude');
    $this->securityAccessFiltering($request, false);
    
    $cacher = liCacher::create('manifestation/showSpectators?id='.$request->getParameter('id'), true);
    if ( !$cacher->requiresRefresh($request) )
    if ( ($this->cache = $cacher->useCache($this->getRoute()->getObject()->getCacheTimeout())) !== false )
      return 'Success';
    
    $this->manifestation_id = $request->getParameter('id');
    $this->spectators = $this->getSpectators($request->getParameter('id'));
    $this->show_workspaces = Doctrine_Query::create()
      ->from('Gauge g')
      ->leftJoin('g.Manifestation m')
      ->andWhere('m.id = ?',$this->manifestation_id)
      ->execute()
      ->count() > 1;
    $this->cache = $this->getPartial('show_spectators_list');
    
    $cacher->setData($this->cache)->writeData();
  }
  public function executeShowTickets(sfWebRequest $request)
  {
    $this->setLayout('nude');
    $this->securityAccessFiltering($request, false);
    
    $cacher = liCacher::create('manifestation/showTickets?id='.$request->getParameter('id'), true);
    if ( !$cacher->requiresRefresh($request) )
    if ( ($this->cache = $cacher->useCache($this->getRoute()->getObject()->getCacheTimeout())) !== false )
      return 'Success';
    
    $this->manifestation_id = $request->getParameter('id');
    $this->prices = $this->getPrices($request->getParameter('id'));
    $this->cache = $this->getPartial('show_tickets_list');
    
    $cacher->setData($this->cache)->writeData();
  }
  
  protected function countTickets($manifestation_id)
  {
    $q = '
      SELECT count(*) AS nb
      FROM ticket
      WHERE cancelling IS NULL
        AND duplicating IS NULL
        AND id NOT IN (SELECT cancelling FROM ticket WHERE cancelling IS NOT NULL)
        AND manifestation_id = :manifestation_id';
    $pdo = Doctrine_Manager::getInstance()->getCurrentConnection()->getDbh();
    $stmt = $pdo->prepare($q);
    $stmt->execute(array('manifestation_id' => $manifestation_id));
    $tmp = $stmt->fetchAll();
    
    return $tmp[0]['nb'];
  }
  
  protected function getPrices($manifestation_id = NULL)
  {
    $mid = $manifestation_id ? $manifestation_id : $this->manifestation->id;
    $nb = $this->countTickets($mid);
    $q = Doctrine::getTable('Price')->createQuery('p');
    
    if ( $nb < 7500 )
    $q->leftJoin('p.Tickets t')
      ->leftJoin('t.Duplicatas duplicatas')
      ->leftJoin('duplicatas.Cancelling cancelling2')
      ->leftJoin('t.Cancelling cancelling')
      ->leftJoin('t.Transaction tr')
      ->leftJoin('tr.Contact c')
      ->leftJoin('tr.Professional pro')
      ->leftJoin('pro.Organism o')
      ->leftJoin('tr.Order order')
      ->leftJoin('t.Controls ctrl')
      ->leftJoin('ctrl.Checkpoint cp')
      ->leftJoin('t.Gauge g')
      ->leftJoin('g.Workspace w')
      ->andWhere('t.cancelling IS NULL')
      ->andWhere('t.id NOT IN (SELECT tt2.cancelling FROM ticket tt2 WHERE tt2.cancelling IS NOT NULL)')
      ->andWhere('t.id NOT IN (SELECT tt.duplicating FROM Ticket tt WHERE tt.duplicating IS NOT NULL)')
      ->andWhere('t.manifestation_id = ?',$mid)
      ->andWhere('cp.type IS NULL OR cp.type = ?', 'entrance')
      ->andWhereIn('g.workspace_id',array_keys($this->getUser()->getWorkspacesCredentials()))
      ->orderBy('g.workspace_id, w.name, pt.name, tr.id, o.name, c.name, c.firstname');
    else
    {
      $params = array();
      for ( $i = 0 ; $i < 7 ; $i++ )
        $params[] = $mid;
      $q->select('p.*')
        ->andWhere('p.id IN (SELECT DISTINCT t0.price_id FROM Ticket t0 WHERE t0.manifestation_id = ?)', $params) // the X $mid is a hack for doctrine
        ->orderBy('pt.name');
      $rank = 0;
      foreach ( array(
        'printed' => '(t%%i%%.printed_at IS NOT NULL OR t%%i%%.integrated_at IS NOT NULL)',
        'ordered' => 'NOT (t%%i%%.printed_at IS NOT NULL OR t%%i%%.integrated_at IS NOT NULL) AND t%%i%%.transaction_id IN (SELECT DISTINCT o%%i%%.transaction_id FROM Order o%%i%%)',
        'asked' => 'NOT (t%%i%%.printed_at IS NOT NULL OR t%%i%%.integrated_at IS NOT NULL) AND t%%i%%.transaction_id NOT IN (SELECT DISTINCT o%%i%%.transaction_id FROM Order o%%i%%)'
      ) as $col => $where )
      {
        $rank++;
        $q->addSelect('(SELECT count(t'.$rank.'.id) FROM Ticket t'.$rank.' LEFT JOIN t'.$rank.'.Gauge g'.$rank.' WHERE '.str_replace('%%i%%',$rank,$where).' AND t'.$rank.'.cancelling IS NULL AND t'.$rank.'.id NOT IN (SELECT ttd'.$rank.'.duplicating FROM Ticket ttd'.$rank.' WHERE ttd'.$rank.'.duplicating IS NOT NULL) AND t'.$rank.'.id NOT IN (SELECT tt'.$rank.'.cancelling FROM ticket tt'.$rank.' WHERE tt'.$rank.'.cancelling IS NOT NULL) AND t'.$rank.'.manifestation_id = ? AND g'.$rank.'.workspace_id IN ('.implode(',',array_keys($this->getUser()->getWorkspacesCredentials())).') AND t'.$rank.'.price_id = p.id) AS '.$col, $mid);
        $rank++;
        $q->addSelect('(SELECT sum(t'.$rank.'.value) FROM Ticket t'.$rank.' LEFT JOIN t'.$rank.'.Gauge g'.$rank.' WHERE '.str_replace('%%i%%',$rank,$where).' AND t'.$rank.'.cancelling IS NULL AND t'.$rank.'.duplicating IS NULL AND t'.$rank.'.id NOT IN (SELECT tt'.$rank.'.cancelling FROM ticket tt'.$rank.' WHERE tt'.$rank.'.cancelling IS NOT NULL) AND t'.$rank.'.manifestation_id = ? AND g'.$rank.'.workspace_id IN ('.implode(',',array_keys($this->getUser()->getWorkspacesCredentials())).') AND t'.$rank.'.price_id = p.id) AS '.$col.'_value', $mid);
      }
    }
    $e = $q->execute();
    return $e;
  }
  
  protected function getSpectators($manifestation_id = NULL, $only_printed_tck = false)
  {
    $mid = $manifestation_id ? $manifestation_id : $this->manifestation->id;
    $nb = $this->countTickets($mid);
    $q = Doctrine_Query::create()->from('Transaction tr')
      ->leftJoin('tr.Contact c')
      ->leftJoin('c.Groups gc')
      ->leftJoin('gc.Picture gcp')
      ->leftJoin('tr.Professional pro')
      ->leftJoin('pro.Groups gpro')
      ->leftJoin('gpro.Picture gprop')
      ->leftJoin('tr.Order order')
      ->leftJoin('tr.User u')
      ->leftJoin('pro.Organism o')
    ;
    
    $q->leftJoin('tr.Tickets tck'.($only_printed_tck ? ' ON tck.transaction_id = tr.id AND (tck.printed_at IS NOT NULL OR tck.integrated_at IS NOT NULL OR tck.cancelling IS NOT NULL)' : ''))
      ->leftJoin('tck.Duplicatas duplicatas')
      ->leftJoin('duplicatas.Cancelling cancelling2')
      ->leftJoin('tck.Cancelling cancelling')
      ->leftJoin('tr.Invoice invoice')
      ->leftJoin('tck.Cancelled cancelled')
      ->leftJoin('tck.Manifestation m')
      ->leftJoin('tck.Controls ctrl')
      ->leftJoin('tck.Price p')
      ->leftJoin('p.Translation pt WITH pt.lang = ?', $this->getUser()->getCulture())
      ->leftJoin('ctrl.Checkpoint cp')
      ->leftJoin('tck.Gauge g')
      ->leftJoin('g.Workspace w')
      ->andWhere('tck.cancelling IS NULL')
      ->andWhere('tck.id NOT IN (SELECT tt2.cancelling FROM ticket tt2 WHERE tt2.cancelling IS NOT NULL)')
      ->andWhere('tck.id NOT IN (SELECT tt3.duplicating FROM ticket tt3 WHERE tt3.duplicating IS NOT NULL)') // we want only the last duplicates (or originals if no duplication has been made)
      ->andWhere('tck.manifestation_id = ?',$manifestation_id ? $manifestation_id : $this->manifestation->id)
      ->andWhere('(cp.type IS NULL OR cp.type = ?)', 'entrance')
      ->andWhereIn('g.workspace_id',array_keys($this->getUser()->getWorkspacesCredentials()))
      ->orderBy('c.name, c.firstname, o.name, pt.name, g.workspace_id, w.name, tr.id')
    ;
    
    $spectators = $q->execute();
    return $spectators;
  }

  protected function getUnbalancedTransactions()
  {
    $con = Doctrine_Manager::getInstance()->connection();
    $st = $con->execute(
      //"SELECT DISTINCT t.*, tl.id AS translinked,
      $q = "SELECT DISTINCT t.*,
              (SELECT sum(ttt.value) + sum(CASE WHEN ttt.taxes IS NULL THEN 0 ELSE ttt.taxes END)
               FROM Ticket ttt
               WHERE ttt.transaction_id = t.id
                 AND (ttt.printed_at IS NOT NULL OR ttt.integrated_at IS NOT NULL OR cancelling IS NOT NULL)
                 AND ttt.duplicating IS NULL)
            + (SELECT CASE WHEN count(bp.id) = 0 THEN 0 ELSE sum(bp.value) + sum(CASE WHEN bp.shipping_fees IS NULL THEN 0 ELSE bp.shipping_fees END) END FROM bought_product bp WHERE bp.transaction_id = t.id AND bp.integrated_at IS NOT NULL)
               AS topay,
              (SELECT CASE WHEN sum(ppp.value) IS NULL THEN 0 ELSE sum(ppp.value) END FROM Payment ppp WHERE ppp.transaction_id = t.id) AS paid,
              c.id AS c_id, c.name, c.firstname,
              p.name AS p_name, o.id AS o_id, o.name AS o_name, o.city AS o_city
       FROM transaction t
       LEFT JOIN contact c ON c.id = t.contact_id
       LEFT JOIN professional p ON p.id = t.professional_id
       LEFT JOIN organism o ON p.organism_id = o.id
       LEFT JOIN transaction tl ON tl.transaction_id = t.id
       WHERE t.id IN (SELECT DISTINCT tt.transaction_id FROM Ticket tt WHERE tt.manifestation_id = ".intval($this->manifestation->id).")
         AND (SELECT sum(tt2.value) + sum(CASE WHEN tt2.taxes IS NULL THEN 0 ELSE tt2.taxes END) FROM Ticket tt2 WHERE tt2.transaction_id = t.id AND (tt2.printed_at IS NOT NULL OR tt2.integrated_at IS NOT NULL OR tt2.cancelling IS NOT NULL) AND tt2.duplicating IS NULL)
          +  (SELECT CASE WHEN count(bp2.id) = 0 THEN 0 ELSE sum(bp2.value) + sum(CASE WHEN bp2.shipping_fees IS NULL THEN 0 ELSE bp2.shipping_fees END) END FROM bought_product bp2 WHERE bp2.transaction_id = t.id AND bp2.integrated_at IS NOT NULL)
          != (SELECT CASE WHEN sum(pp.value) IS NULL THEN 0 ELSE sum(pp.value) END FROM Payment pp WHERE pp.transaction_id = t.id)
       ORDER BY t.id ASC");
    $transactions = $st->fetchAll();
    return $transactions;
  }

  /*
   * overriding that to redirect the user to the parent event/location's screen
   * instead of the list of manifestations
   *
   */
  protected function processForm(sfWebRequest $request, sfForm $form)
  {
    $this->getContext()->getConfiguration()->loadHelpers('I18N');
    
    $form->bind($request->getParameter($form->getName()), $request->getFiles($form->getName()));
    if ($form->isValid())
    {
      // "credentials"
      $form->updateObject($request->getParameter($form->getName()), $request->getFiles($form->getName()));
      
      // a workaround to avoid Manifestation::event_id set and a Manifesation::Event still unset, for credential checks
      if ( $form->getObject()->event_id && $form->getObject()->Event->isNew() )
        $form->getObject()->Event = Doctrine::getTable('Event')->find($form->getObject()->event_id);
      
      if ( !in_array($form->getObject()->Event->meta_event_id, array_keys($this->getUser()->getMetaEventsCredentials())) )
      {
        $this->getUser()->setFlash('error', "You don't have permissions to modify this event.");
        $this->redirect('@manifestation_new');
      }
      
      $notice = __($form->getObject()->isNew() ? "The item was created successfully. Don't forget to update prices if necessary." : 'The item was updated successfully.');
      
      $manifestation = $form->save();

      $this->dispatcher->notify(new sfEvent($this, 'admin.save_object', array('object' => $manifestation)));

      if ($request->hasParameter('_save_and_add'))
      {
        $this->getUser()->setFlash('success', $notice.' You can add another one below.');

        $this->redirect('@manifestation_new');
      }
      else
      {
        $this->getUser()->setFlash('success', $notice);
        
        $this->redirect(array('sf_route' => 'manifestation_edit', 'sf_subject' => $manifestation));
      }
    }
    else
    {
      $this->getUser()->setFlash('error', 'The item has not been saved due to some errors.', false);
    }
  }
}
