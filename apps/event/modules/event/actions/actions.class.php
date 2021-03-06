<?php

require_once dirname(__FILE__).'/../lib/eventGeneratorConfiguration.class.php';
require_once dirname(__FILE__).'/../lib/eventGeneratorHelper.class.php';

/**
 * event actions.
 *
 * @package    e-venement
 * @subpackage event
 * @author     Baptiste SIMON <baptiste.simon AT e-glop.net>
 * @version    SVN: $Id: actions.class.php 23810 2009-11-12 11:07:44Z Kris.Wallsmith $
 */
class eventActions extends autoEventActions
{
  public function executeImport(sfWebRequest $request)
  {
    $this->importForm = new sfForm;
    $ws = $this->importForm->getWidgetSchema();
    $vs = $this->importForm->getValidatorSchema();
    $ws->setNameFormat('ics[%s]');
    $ws['event_id'] = new sfWidgetFormInputHidden;
    $vs['event_id'] = new sfValidatorDoctrineChoice(array('model' => 'event'));
    $ws['location_id'] = new sfWidgetFormDoctrineChoice(array(
      'model' => 'Location',
      'query' => Doctrine::getTable('Location')->retrievePlaces(),
      'label' => 'Main location',
      'order_by' => array('name', ''),
      'add_empty' => true,
    ));
    $vs['location_id'] = new sfValidatorDoctrineChoice(array(
      'model' => 'Location',
      'query' => Doctrine::getTable('Location')->retrievePlaces(),
    ));
    $ws['book_all'] = new sfWidgetFormInputCheckbox(array('value_attribute_value' => 'all'));
    $vs['book_all'] = new sfValidatorBoolean(array('true_values' => array('all'), 'required' => false));
    $ws['file'] = new sfWidgetFormInputFile(array('label' => 'iCal/ICS File'), array('accept' => 'text/calendar'));
    $vs['file'] = new sfValidatorFile(array('mime_types' => array('text/calendar')));
    
    // import the ICS file
    if ( $ics = $request->getParameter('ics', false) )
    {
      $this->forward404Unless(isset($ics['event_id']) && $ics['event_id']);
      $this->importForm->bind($ics, $files = $request->getFiles('ics'));
      $this->forward404Unless($this->event = Doctrine::getTable('Event')->find($ics['event_id']));
      if ( !$this->importForm->isValid() )
        return 'Success';
      
      $context = $request->getRequestContext();
      $vcal = new vcalendar;
      $vcal->parse(file_get_contents($this->importForm->getValue('file')->getTempName()));
      
      if ( $this->importForm->getValue('book_all') )
        $all = Doctrine::getTable('Location')->createQuery('l')->execute();
      $vat = Doctrine::getTable('VAT')->createQuery('v')->fetchOne();
      
      while ( $vevent = $vcal->getComponent('vevent') )
      if ( $vevent->dtstart && $vevent->dtend )
      {
        $manifestation = new Manifestation;
        $manifestation->event_id = $ics['event_id'];
        $manifestation->happens_at = sprintf('%s-%s-%s %s:%s:%s',
          $vevent->dtstart['value']['year'],
          $vevent->dtstart['value']['month'],
          $vevent->dtstart['value']['day'],
          $vevent->dtstart['value']['hour'] ? $vevent->dtstart['value']['hour'] : '00',
          $vevent->dtstart['value']['min']  ? $vevent->dtstart['value']['min']  : '00',
          $vevent->dtstart['value']['sec']  ? $vevent->dtstart['value']['sec']  : '00'
        );
        $manifestation->ends_at = sprintf('%s-%s-%s %s:%s:%s',
          $vevent->dtend['value']['year'],
          $vevent->dtend['value']['month'],
          $vevent->dtend['value']['day'],
          $vevent->dtend['value']['hour'] ? $vevent->dtend['value']['hour'] : '00',
          $vevent->dtend['value']['min']  ? $vevent->dtend['value']['min']  : '00',
          $vevent->dtend['value']['sec']  ? $vevent->dtend['value']['sec']  : '00'
        );
        $manifestation->location_id = $this->importForm->getValue('location_id');
        $manifestation->vat_id = $vat->id;
        
        if ( $this->importForm->getValue('book_all') )
        foreach ( $all as $r )
          $manifestation->Booking[] = $r;
        
        $manifestation->save();
      }
      
      sfContext::getInstance()->getConfiguration()->loadHelpers('I18N');
      $this->getUser()->setFlash('notice', __('ICS file imported.'));
      $this->redirect('event/edit?id='.$ics['event_id']);
    }
    else
    {
      // if nothing to import... do this:
      $this->executeShow($request);
      $this->importForm->setDefault('event_id', $this->event->id);
    }
  }
  public function executeIndex(sfWebRequest $request)
  {
    parent::executeIndex($request);
    if ( !$this->sort[0] )
    {
      $this->sort = array('name','');
      $a = $this->pager->getQuery()->getRootAlias();
      $this->pager->getQuery()
        //->addSelect("(SELECT min(m2.happens_at) FROM manifestation m2 WHERE m2.event_id = $a.id) AS min_happens_at")
        ->addSelect("(SELECT (CASE WHEN max(m3.happens_at) IS NULL THEN false ELSE max(m3.happens_at) > now() END) FROM manifestation m3 WHERE m3.event_id = $a.id) AS now")
        ->orderBy("max_date ".(sfConfig::get('app_listing_manif_date','DESC') != 'ASC' ? 'DESC' : 'ASC').", translation.name");
    }
  }
  
  public function executeDelPicture(sfWebRequest $request)
  {
    $q = Doctrine_Query::create()->from('Picture p')
      ->where('p.id IN (SELECT e.picture_id FROM Event e WHERE e.id = ?)',$request->getParameter('id'))
      ->delete()
      ->execute();
    return sfView::NONE;
  }
  
  public function executeOnlyFilters(sfWebRequest $request)
  {
    parent::executeIndex($request);
    $a = $this->pager->getQuery()->getRootAlias();
    $this->pager->getQuery()->select("$a.id");
  }
  
  public function executeBatchBestFreeSeat(sfWebRequest $request)
  { $this->forward('manifestation', 'bestFreeSeat'); }
  
  public function executeSearch(sfWebRequest $request)
  {
    self::executeIndex($request);
    $table = Doctrine::getTable('Event');
    
    $search = $this->sanitizeSearch($request->getParameter('s'));
    
    $this->pager->setQuery($table->search($search.'*',$this->pager->getQuery()));
    $this->pager->setPage($request->getParameter('page') ? $request->getParameter('page') : 1);
    $this->pager->init();
    
    $this->setTemplate('index');
  }
  
  public function executeShow(sfWebRequest $request)
  {
    $this->securityAccessFiltering($request);
    parent::executeShow($request);
    
    $this->getContext()->getConfiguration()->loadHelpers('CrossAppLink');
    $museum = $this->getContext()->getConfiguration()->getApplication() == 'museum';
    if ( $this->event->museum && !$museum )
      $this->redirect(cross_app_url_for('museum', 'event/show?id='.$this->event->id));
    elseif ( !$this->event->museum && $museum )
      $this->redirect(cross_app_url_for('event', 'event/show?id='.$this->event->id));
  }
  public function executeEdit(sfWebRequest $request)
  {
    $this->securityAccessFiltering($request, true);
    parent::executeEdit($request);
    
    $this->getContext()->getConfiguration()->loadHelpers('CrossAppLink');
    $museum = $this->getContext()->getConfiguration()->getApplication() == 'museum';
    if ( $this->event->museum && !$museum )
      $this->redirect(cross_app_url_for('museum', 'museum/edit?id='.$this->event->id));
    elseif ( !$this->event->museum && $museum )
      $this->redirect(cross_app_url_for('event', 'event/edit?id='.$this->event->id));
  }
  public function executeUpdate(sfWebRequest $request)
  {
    $this->securityAccessFiltering($request, true);
    parent::executeUpdate($request);
  }
  public function executeDelete(sfWebRequest $request)
  {
    try {
      $this->securityAccessFiltering($request, true);
      parent::executeDelete($request);
    }
    catch ( Doctrine_Connection_Exception $e )
    {
      $this->getContext()->getConfiguration()->loadHelpers('I18N');
      $this->getUser()->setFlash('error',__("Deleting this object has been canceled because of remaining links to externals (like tickets)."));
      $this->redirect('event/show?id='.$this->getRoute()->getObject()->id);
    }
  }
  
  protected function securityAccessFiltering(sfWebRequest $request, $deep = false)
  {
    if ( intval($request->getParameter('id')).'' != ''.$request->getParameter('id') )
      return;
    
    sfContext::getInstance()->getConfiguration()->loadHelpers('I18N');
    
    if ( $deep && !$this->getUser()->hasCredential('event-access-all') )
    foreach ( $this->getRoute()->getObject()->Manifestations as $manif )
    if ( $manif->contact_id !== $this->getUser()->getContactId() )
    {
      $this->getUser()->setFlash('error', __("You cannot edit an event object in which there are manifestations that do not belong to you."));
      $this->redirect('event/show?id='.$this->getRoute()->getObject()->getId());
    }
    
    if (!in_array(
          $this->getRoute()->getObject()->meta_event_id,
          array_keys($this->getUser()->getMetaEventsCredentials())
       ))
    {
      $this->getUser()->setFlash('error', "You can't access this object, you don't have the required permissions.");
      $this->redirect('@event');
    }
  }
  
  public function executeCalendar(sfWebRequest $request)
  {
    require(dirname(__FILE__).'/calendar.php');
  }
  
  public function executeBatchDelete(sfWebRequest $request)
  {
    $ids = $request->getParameter('ids');

    $q = Doctrine_Query::create()
      ->from('Event e')
      ->whereIn('e.id', $ids)
      ->andWhere('(SELECT count(m.id) FROM Manifestation m WHERE m.event_id = id AND m.contact_id != ?) = 0', $this->getUser()->getContactId())
      ->delete();
    $count = EventFormFilter::addCredentialsQueryPart(Doctrine::getTable('Event')->createQuery('e')->whereIn('e.id', $ids)->select('e.id'))->execute()->count();
    
    if ($count >= count($ids))
    {
      $q->execute();
      $this->getUser()->setFlash('notice', 'The selected items have been deleted successfully.');
    }
    else
    {
      $this->getUser()->setFlash('error', 'A problem occurs when deleting the selected items.');
    }

    $this->redirect('@event');
  }
  public function executeBatchMerge(sfWebRequest $request)
  {
    $ids = $request->getParameter('ids');

    $events = Doctrine::getTable('Event')->retrieveList()->orderBy('e.updated_at DESC')
      ->andWhereIn('e.id', $ids)
      ->execute();
    if ( $events->count() <= 1 )
    {
      $this->getUser()->setFlash('error', 'You must at least select two items.');
      $this->redirect('@event');
    }
    
    $count = 0;
    $orig = $events[0];
    foreach ( $events as $event )
    {
      if ( $count == 0 )
      {
        $count++;
        continue;
      }
      
      foreach ( array('Manifestations', 'Companies', 'Checkpoints', 'Entries', 'MemberCardPrices', 'MemberCardPriceModels') as $relation )
      foreach ( $event->$relation as $relobj )
        $orig->{$relation}[] = $relobj;
      
      $orig->save();
      $event->delete();
      $count++;
    }
    
    if ($count >= count($ids))
    {
      $this->getUser()->setFlash('notice', 'The selected items have been merged successfully.');
    }
    else
    {
      $this->getUser()->setFlash('error', 'A problem occurs when merging some of the selected items.');
    }

    $this->redirect('@event');
  }
  public function executeBatchDuplicate(sfWebRequest $request)
  {
    $ids = $request->getParameter('ids');

    $events = Doctrine::getTable('Event')->retrieveList()->orderBy('e.updated_at DESC')
      ->andWhereIn('e.id', $ids)
      ->execute();
    if ( $events->count() == 0 )
    {
      $this->getUser()->setFlash('error', 'You must at least select one item.');
      $this->redirect('@event');
    }
    
    $count = 0;
    foreach ( $events as $event )
    {
      $new = $event->copy();
      
      foreach ( array('Translation', 'Manifestations', 'Companies', 'Checkpoints', 'MemberCardPrices', 'MemberCardPriceModels') as $relation )
      foreach ( $event->$relation as $relobj )
        $new->{$relation}[] = $relobj->copy();
      foreach ( array('MetaEvent', 'EventCategory') as $relation )
        $new->$relation = $event->$relation;
      foreach ( array('slug') as $prop )
        $new->$prop = NULL;
      
      $new->save();
      $count++;
    }
    
    if ($count >= count($ids))
    {
      $this->getUser()->setFlash('notice', 'The selected items have been duplicated successfully.');
    }
    else
    {
      $this->getUser()->setFlash('error', 'A problem occurs when merging some of the selected items.');
    }

    $this->redirect('@event');
  }
  
  public function executeAjax(sfWebRequest $request)
  {
    $charset = sfConfig::get('software_internals_charset');
    $search  = iconv($charset['db'],$charset['ascii'],$request->getParameter('q',''));
    
    $q = Doctrine::getTable('Event')
      ->createQuery('e')
      ->orderBy('translation.name')
      ->limit($request->getParameter('limit'))
      ->andWhereIn('e.meta_event_id',array_keys($this->getUser()->getMetaEventsCredentials()));
    if ( $request->getParameter('meta_event_id').'' === ''.intval($request->getParameter('meta_event_id')) )
      $q->andWhere('e.meta_event_id = ?', intval($request->getParameter('meta_event_id')));
    if ( $search )
      $q = Doctrine_Core::getTable('Event')
        ->search($search.'*',$q);
    
    $this->events = array();
    foreach ( $q->execute() as $event )
      $this->events[$event->id] = $request->hasParameter('with_meta_event') ? $event.' ('.$event->MetaEvent.')' : (string)$event;
    
    if (!( sfConfig::get('sf_web_debug', false) && $request->hasParameter('debug') ))
      return 'Json';
  }
  
  public function executeError404(sfWebRequest $request)
  {
  }
  
  public function executeAddManifestation(sfWebRequest $request)
  {
    $this->executeEdit($request);
    $this->redirect('manifestation/new?event='.$this->event->slug);
  }

  public static function sanitizeSearch($search)
  {
    $nb = mb_strlen($search);
    $charset = sfConfig::get('software_internals_charset');
    $transliterate = sfConfig::get('software_internals_transliterate',array());
    
    $search = str_replace(preg_split('//u', $transliterate['from'], -1), preg_split('//u', $transliterate['to'], -1), $search);
    $search = str_replace(MySearchAnalyzer::$cutchars,' ',$search);
    $search = mb_strtolower(iconv($charset['db'],$charset['ascii'], mb_substr($search,$nb-1,$nb) == '*' ? mb_substr($search,0,$nb-1) : $search));
    return $search;
  }
}
