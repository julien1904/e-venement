<?php

/**
 * PluginManifestation
 * 
 * This class has been auto-generated by the Doctrine ORM Framework
 * 
 * @package    e-venement
 * @subpackage model
 * @author     Baptiste SIMON <baptiste.simon AT e-glop.net>
 * @version    SVN: $Id: Builder.php 7490 2010-03-29 19:53:27Z jwage $
 */
abstract class PluginManifestation extends BaseManifestation implements liMetaEventSecurityAccessor
{
  static protected $credentials = array(
    'contact_id'            => 'event-reservation-change-contact',
    'reservation_confirmed' => 'event-reservation-confirm',
    'authorize_conflicts'   => 'event-reservation-conflicts',
    'access_all'            => 'event-access-all',
  );
  protected $cache = array();
  
  public function duplicate($save = true)
  {
    $manif = $this->copy();
    
    foreach ( array('id', 'updated_at', 'created_at', 'sf_guard_user_id') as $property )
      $manif->$property = NULL;
    foreach ( array('Gauges', 'PriceManifestations', 'ManifestationOrganizer', 'LocationBookings', 'ExtraInformations',) as $subobjects )
    {
      $collection = $manif->$subobjects;
      foreach ( $this->$subobjects as $subobject )
      {
        $copy = $subobject->copy();
        
        // specific case of PriceGauges on Gauges...
        if ( $subobject instanceof Gauge )
        foreach ( $subobject->PriceGauges as $pg )
          $copy->PriceGauges[] = $pg->copy();
        
        $collection[] = $copy;
      }
    }
    
    if ( $save )
      $manif->save();
    
    return $manif;
  }
  
  public function preSave($event)
  {
    sfApplicationConfiguration::getActive()->loadHelpers(array('I18N'));
    parent::preSave($event);
    
    // converting duration from "1:00" to 3600 (seconds)
    if ( intval($this->duration).'' != ''.$this->duration )
    {
      $str = $this->duration;
      $this->duration = intval(strtotime($this->duration.'+0',0));
      
      // for durations > 24h
      if ( !$this->duration )
      {
        $arr = explode(':', $str);
        $this->duration = intval($arr[1])*60 + intval($arr[0])*3600;
      }
    }
    
    // completing or correcting reservation fields
    $config = sfConfig::get('app_manifestation_reservations',array('enable' => false));
    $enable = isset($config['enable']) && $config['enable'];
    if ( !$enable )
    {
      $this->reservation_begins_at = $this->happens_at;
      $this->reservation_ends_at = $this->ends_at;
      $this->reservation_confirmed = true;
      return;
    }
    
    // reservation stuff
    
    if ( !$this->reservation_begins_at
      || $this->reservation_begins_at && $this->reservation_begins_at > $this->happens_at )
      $this->reservation_begins_at = $this->happens_at;
    if ( !$this->reservation_ends_at
      || $this->reservation_ends_at && $this->reservation_ends_at < date('Y-m-d H:i:s',strtotime($this->happens_at)+$this->duration) )
      $this->reservation_ends_at = date('Y-m-d H:i:s',strtotime($this->happens_at)+$this->duration);
    if ( sfContext::hasInstance() )
    {
      $sf_user = sfContext::getInstance()->getUser();
      if ( !$sf_user->hasCredential(self::$credentials['contact_id']) )
      {
        if ( $sf_user->getContact() )
          $this->Applicant = $sf_user->getContact();
        else
          throw new liBookingException('User %%name%% is not linked to any contact, and does not have the %%credential%% credential', array('%%name%%' => (string)$sf_user, '%%credential%%' => self::$credentials['contact_id']));
      }
      
      if ( !$sf_user->hasCredential(self::$credentials['access_all']) && $this->contact_id !== $sf_user->getContactId() )
        throw new liBookingException('The current user %%name%% cannot access manifestations which does not belong to itself', array('%%name%%' => (string)$sf_user));
    
      if ( sfContext::hasInstance()
        && $this->reservation_confirmed
        && !sfContext::getInstance()->getUser()->hasCredential(self::$credentials['reservation_confirmed'])
        && sfContext::getInstance()->getUser()->getContactId() !== $this->contact_id )
      {
        $this->reservation_confirmed = false;
        sfContext::getInstance()->getUser()->setFlash('notice', __('You do not have the credential to confirm any manifestation.'));
      }
    }
    
    // previously was in postSave($event)
    $notice = false;
    
    // manifestation in conflict
    if ( $this->hasAnyConflict() )
    {
      // global notice if any conflict is possible
      $notice = __('This manifestation conflicts with another.').' ';
      
      // manifestation confirmed
      if ( $this->reservation_confirmed )
      {
        // no credential to tolerate conflicts
        if ( sfContext::hasInstance()
          && !sfContext::getInstance()->getUser()->hasCredential(self::$credentials['authorize_conflicts']) )
        {
          $this->reservation_confirmed = false;
          $notice .= __('Its status "confirmed" has been disabled.');
        }
        else // special credentials for conflicts
          $notice .= __('Its status "confirmed" has been kept, because you have specific credentials for that.');
      }
      else // not yet confirmed
        $notice .= __('But it is not yet confirmed.');
    }
    
    if ( sfContext::hasInstance() )
    {
      $notices = array();
      if ( sfContext::getInstance()->getUser()->hasFlash('notice') )
        $notices[] = sfContext::getInstance()->getUser()->getFlash('notice');
      if ( $notice ) $notices[] = $notice;
      if ( $notices )
        sfContext::getInstance()->getUser()->setFlash('notice',implode(' | ', $notices));
    }
    
    return parent::preSave($event);
  }
  
  public function postInsert($event)
  {
    $add_prices = false;
    if ( sfContext::hasInstance()
      && sfContext::getInstance()->getUser()->hasCredential(array('tck-transaction', 'event-admin-price',), false) )
      $add_prices = true;
    
    $q = Doctrine::getTable('Price')->createQuery('p', false)
      ->andWhere('p.hide = ?', false)
    ;
    
    if ( $this->PriceManifestations->count() == 0 && $add_prices )
    foreach ( $q->execute() as $price )
    {
      $pm = PriceManifestation::createPrice($price);
      $pm->manifestation_id = $this->id;
      //$pm->save();
      $this->PriceManifestations[] = $pm;
    }
    $this->save();
    
    parent::postInsert($event);
  }
  
  public function getDurationHR()
  {
    if ( intval($this->duration).'' != ''.$this->duration )
      return $this->duration;
    
    sfApplicationConfiguration::getActive()->loadHelpers(array('I18N'));
    $days = floor($this->duration/(3600*24));
    $hours = floor($this->duration%(3600*24)/3600);
    $minutes = str_pad(floor($this->duration%3600/60), 2, '0', STR_PAD_LEFT);
    return ($days > 0 ? __('%%d%% day(s)',array('%%d%%' => $days)) : '').' '.$hours.':'.$minutes;
  }
  
  public function getMEid()
  {
    return $this->Event->getMEid();
  }
  
  public function getIndexesPrefix()
  {
    return strtolower(get_class($this));
  }
  
  public static function getCredentials($credential = false)
  {
    if ( $credential )
      return self::$credentials[$credential];
    return self::$credentials;
  }
  
  public function clearCache()
  {
    $this->cache = array();
    return $this;
  }
  /**
    * Get data from the cache
    * @param  string    $name the name of the cached object
    * @return mixed     cached value
    *
    **/
  protected function getFromCache($name)
  {
    if ( !isset($this->cache[$name]) )
      throw new liEvenementException('Nothing is cached with the name: '.$name.'.');
    
    return $this->cache[$name];
  }
  /**
    * Set data in the cache
    * @param  string    $name  the name of the cached object
    * @param  mixed     $value the content to be cached
    * @return Manifestation $this
    *
    **/
  protected function setInCache($name, $value)
  {
    $this->cache[$name] = $value;
    return $value;
  }
}
