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
*    Copyright (c) 2006-2013 Baptiste SIMON <baptiste.simon AT e-glop.net>
*    Copyright (c) 2011 Ayoub HIDRI <ayoub.hidri AT gmail.com>
*    Copyright (c) 2006-2013 Libre Informatique [http://www.libre-informatique.fr/]
*
***********************************************************************************/
?>
<?php
class SendManifestationNotificationsTask extends sfBaseTask{

  protected function configure() {
    $this->addOptions(array(
      new sfCommandOption('application', null, sfCommandOption::PARAMETER_REQUIRED, 'The environement', 'event'),
      new sfCommandOption('env', null, sfCommandOption::PARAMETER_REQUIRED, 'The environement', 'prod'),
    ));
    $this->namespace = 'e-venement';
    $this->name = 'send-manifestation-notifications';
    $this->briefDescription = 'Sends manifestation notifications as configured in apps/event/config/app.yml';
    $this->detailedDescription = <<<EOF
      The [smn:send-manifestation-notifications|INFO] Sends manifestation notifications:
      [./symfony e-venement:send-manifestation-notifications --env=prod|INFO]
EOF;
  }

  protected function execute($arguments = array(), $options = array())
  {
    sfContext::createInstance($this->configuration, 'dev');
    $databaseManager = new sfDatabaseManager($this->configuration);
    
    if(!class_exists('Manifestation'))
      throw new sfCommandException(sprintf('Model "%s" doesn\'t exist.', 'Manifestation'));
    
    $this->configuration->loadHelpers(array('CrossAppLink', 'Url', 'I18N', 'Date', 'Tag'));
    
    // setting alarms
    $period   = strtotime(sfConfig::get('app_synchronization_cron_period','1 hour'));
    $quitfile = sfConfig::get('app_synchronization_cron_quittime_file',sfConfig::get('sf_cache_dir').'/cron.hourly.quittime');
    $base_url = sfConfig::get('app_synchronization_base_url',false);
    $from     = sfConfig::get('app_synchronization_email_from');
    $tocome   = sfConfig::get('app_synchronization_alarms',false);
    $pendings = sfConfig::get('app_synchronization_pending_alarms',false);
    
    // if last modification time is older than $period, then use its last modification time
    if ( file_exists($quitfile) && filemtime($quitfile) < strtotime($period) )
      $period = filemtime($quitfile);

    $q = Doctrine_Query::create()->from('Manifestation m')
      ->leftJoin('m.Event e')
      ->leftJoin('m.Applicant a')
      ->leftJoin('m.Organizers o');
    
    foreach ( array('tocome', 'pendings') as $type )
    {
      $alarms = $$type;
      if (!( $alarms && in_array('email', $alarms['what']) ))
        continue;
      
      foreach ( $alarms['when'] as $when )
      {
        $time = time()*2-strtotime($when); // a trick to get -1 hour to the manif being +1 hour from now
        
        $q->andWhere('m.reservation_confirmed = ? AND m.happens_at >= ? AND m.happens_at <= ?', array(
          $type == 'tocome',
          $to = date('Y-m-d H:i:s', $time+time()-$period),
          $date = date('Y-m-d H:i:s', $time),
        ));
      }
    }
    
    $manifs = $q->execute();
    if ( $manifs->count() == 0 )
      $this->logSection('notification', sprintf('Nothing to notify.'));
    else foreach ( $manifs as $manif )
    {
      $who = isset($alarms['who']) ? $alarms['who'] : array('organizers', 'applicant');
      $emails = array();
      // related to the manifestation itself
      if ( in_array('organizers', $who) )
      foreach ( $manif->Organizers as $org )
      if ( $org->email )
        $emails[$org->email] = $org->email;
      // related to the applicants
      if ( in_array('applicant', $who) && $manif->contact_id && ($manif->Applicant->sf_guard_user_id || $manif->Applicant->email) )
      {
        $email = $manif->Applicant->sf_guard_user_id ? $manif->Applicant->User->email_address : $manif->Applicant->email;
        $emails[$email] = $email;
      }
      if ( in_array('applicant', $who) && $manif->organism_id && ($manif->ApplicantOrganism->email) )
        $emails[$manif->ApplicantOrganism->email] = $manif->ApplicantOrganism->email;
      // related to the Location
      if ( in_array('location', $who) )
      foreach ( array('contact', 'organism') as $entity )
      if ( $manif->Location->{$entity.'_id'} && $manif->Location->${ucfirst($entity)}->email )
      {
        $email = $manif->Location->${ucfirst($entity)}->email;
        $emails[$email] = $email;
      }
      if ( $manif->Location->email )
        $emails[$manif->Location->email] = $manif->Location->email;
      // the global admins
      if ( in_array('admins', $who) )
      {
        $q = Doctrine::getTable('sfGuardUser')->createQuery('u')
          ->leftJoin('u.Groups g')
          ->andWhereIn('g.name', array('event-reservation-admin', 'event-reservation-super-admin'))
          ->leftJoin('u.Contact c')
        ;
        foreach ( $q->execute() as $user )
        {
          $emails[$user->Contact->email] = $user->Contact->email;
          $emails[$user->email_address] = $user->email_address;
        }
      }
      
      foreach ( $emails as $emailaddr )
      {
        $email = new Email;
        $email->setMailer($this->getMailer());
        $email->isATest(false);
        $email->setNoSpool(true);
        
        $email->field_from = $from;
        $email->to = $emailaddr;
        $email->field_subject = $manif->reservation_confirmed
          ? __('Notification for %%manif%%', array('%%manif%%' => (string)$manif))
          : __('Notification of a pending manifestation on the %%date%%', array('%%date%%' => format_date($manif->happens_at)))
        ;
        
        // preparing the content
        $orgs = array();
        foreach ( $manif->Organizers as $org )
          $orgs[] = $org;
        $state = array();
        foreach ( array(
          '!reservation_confirmed' => __('To be confirmed'),
          'reservation_confirmed' => __('Confirmed'),
          '!blocking'              => __('Not blocking'),
        ) as $prop => $msg )
        {
          $bool = true;
          $field = $prop;
          if ( substr($prop,0,1) == '!' )
          {
            $bool = false;
            $field = substr($prop,1);
          }
          
          if ( $manif->$field === $bool )
            $state[] = $msg;
        }
        
        // content
        $email->content = sprintf(<<<EOF
          %s<br/><br/>
          %s: %s<br/><br/><br/>
          %s: %s - %s<br/><br/>
          %s: %s<br/><br/>
          %s: %s<br/><br/>
          %s: %s<br/><br/>
          %s: %s<br/><br/>
          %s: %s<br/><br/>
EOF
          , (string)$manif
          , __('State'), implode(', ',$state)
          , __('When'), $manif->mini_date, $manif->mini_end_date
          , __('Where'), (string)$manif->Location
          , __('Applicant'), (string)$manif->Applicant
          , __('Applied by organism'), (string)$manif->ApplicantOrganism
          , __('Organizers'), implode(', ',$orgs)
          , __('Memo'), $manif->description
        );
        
        $email->deleted_at = date('Y-m-d H:i:s');
        $email->save();
        //$email->delete();
        $this->logSection('Notification', sprintf('for manifestation %s sent to %s', (string)$manif, $emailaddr));
      }
      
      $this->logSection('Manifestation', sprintf('%s done.', (string)$manif));
    }
  }
}
