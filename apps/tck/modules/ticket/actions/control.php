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
*    Copyright (c) 2006-2016 Baptiste SIMON <baptiste.simon AT e-glop.net>
*    Copyright (c) 2006-2016 Libre Informatique [http://www.libre-informatique.fr/]
*
***********************************************************************************/
?>
<?php
    // debug
    if ( sfConfig::get('sf_web_debug', false) && $request->hasParameter('debug') )
    {
      $this->getResponse()->setContentType('text/html');
      $this->setLayout('nude');
    }
    else
      sfConfig::set('sf_web_debug', false);
    
    $this->getContext()->getConfiguration()->loadHelpers(array('CrossAppLink','I18N'));
    $this->form = new ControlForm();
    $this->form->getWidget('checkpoint_id')->setOption('default', $this->getUser()->getAttribute('control.checkpoint_id'));
    $q = Doctrine::getTable('Checkpoint')->createQuery('c')->select('c.*');
    $this->errors = $this->tickets = array();
    
    $past = sfConfig::get('app_control_past') ? sfConfig::get('app_control_past') : '6 hours';
    $future = sfConfig::get('app_control_future') ? sfConfig::get('app_control_future') : '1 day';
    
    $q->leftJoin('c.Event e')
      ->leftJoin('e.Manifestations m')
      ->andWhere('m.happens_at < ?',date('Y-m-d H:i',strtotime('now + '.$future)))
      ->andWhere('m.happens_at >= ?',date('Y-m-d H:i',strtotime('now - '.$past)));
    $this->form->getWidget('checkpoint_id')->setOption('query',$q);
    
    // retrieving the configurate field <- need some improvement for a composite nature : qrcode / id if failing (for instance)
    $field = sfConfig::get('app_tickets_id','id');
    //if ( !is_array($field) )
    //  $field = array($field);
    
    if ( count($request->getParameter($this->form->getName())) > 0 )
    {
      $params = $request->getParameter($this->form->getName());
      
      // creating tickets ids array
      if ( $field != 'othercode' )
      {
        if ( $tmp = json_decode($params['ticket_id']) )
          $params['ticket_id'] = $tmp; // json array
        else // human encoded arrays
        {
          $tmp = explode(',',$params['ticket_id']);
          if ( count($tmp) == 1 )
            $tmp = preg_split('/\s+/',$params['ticket_id']);
          $params['ticket_id'] = array();
          foreach ( $tmp as $key => $ids )
          {
            $ids = explode('-',$ids);
            
            if ( count($ids) > 0 && isset($ids[1]) )
            for ( $i = intval($ids[0]) ; $i <= intval($ids[1]) ; $i++ )
              $params['ticket_id'][$i] = $i;
            else
              $params['ticket_id'][] = $ids[0];
          }
        }
        if ( !is_array($params['ticket_id']) )
          $params['ticket_id'] = array($params['ticket_id']);
        // decode EAN if it exists
        if ( $field == 'id' )
        foreach ( $params['ticket_id'] as $key => $value )
        {
          $value = preg_replace('/!$/', '', $value);
          if ( (strlen($value) == 13 || strlen($value) == 12 ) && substr($value,0,1) === '0' )
          {
            try { $value = liBarcode::decode_ean($value); }
            catch ( sfException $e )
            { $value = intval($value); }
            $params['ticket_id'][$key] = $value;
          }
        }
      }
      else
        $params['ticket_id'] = array(preg_replace('/!$/', '', $params['ticket_id']));
      
      if ( $field != 'id' && intval($params['ticket_id'][0]).'' === ''.$params['ticket_id'][0] )
        $field = 'id';
      
      // filtering the checkpoints
      if ( isset($params['ticket_id'][0]) && $params['ticket_id'][0] )
      {
        $q->leftJoin('m.Tickets t')
          ->whereIn('t.'.$field, $params['ticket_id']);
      }
      
      if ( intval($params['checkpoint_id']).'' === ''.$params['checkpoint_id']
        && count($params['ticket_id']) > 0 )
      {
        $q = Doctrine::getTable('Checkpoint')->createQuery('c')
          ->select('c.*')
          ->leftJoin('c.Event e')
          ->leftJoin('e.Manifestations m')
          ->leftJoin('m.Tickets t')
          ->andWhereIn('t.'.$field, $params['ticket_id'])
          ->andWhere('c.id = ?', $params['checkpoint_id']);
        $checkpoint = $q->fetchOne();
        
        $cancontrol = $checkpoint instanceof Checkpoint;
        if ( !$cancontrol )
        {
          $this->errors[] = __('The ticket #%%id%% is unfoundable in the list of available tickets', array('%%id%%' => implode(', #', $params['ticket_id'])));
          foreach ( $params['ticket_id'] as $tid )
            $this->tickets[] = $tid;
        }
        elseif ( $checkpoint->type == 'entrance' )
        {
          $q = Doctrine::getTable('Control')->createQuery('c')
            ->select('c.*')
            ->leftJoin('c.Checkpoint c2')
            ->leftJoin('c2.Event e')
            ->leftJoin('e.Manifestations m')
            ->leftJoin('m.Tickets t')
            ->leftJoin('c.Ticket tc')
            ->leftJoin('c.User u')
            ->andWhereIn('tc.'.$field, $params['ticket_id'])
            ->andWhere("tc.$field = t.$field")
            ->andWhere('c.checkpoint_id = ?', $params['checkpoint_id'])
            ->orderBy('c.id DESC');
          $this->controls = $q->execute();
          $cancontrol = $this->controls->count() == 0;
          if ( !$cancontrol )
          {
            $this->errors[] = __('The ticket #%%id%% has been already controlled on this checkpoint before (%%datetime%% by %%user%%)', array(
              '%%id%%' => $this->controls[0]->Ticket->id,
              '%%datetime%%' => $this->controls[0]->created_at,
              '%%user%%' => (string)$this->controls[0]->User,
            ));
            $this->tickets = Doctrine::getTable('Ticket')->createQuery('tck')
              ->select('tck.*')
              ->andWhereIn("tck.$field", $params['ticket_id'])
              ->execute();
          }
        }
        
        $this->getUser()->setAttribute('control.checkpoint_id',$params['checkpoint_id']);
        
        $comments = array();
        foreach ( $this->tickets as $ticket )
        if ( $ticket instanceof Ticket )
        foreach ( array($ticket->DirectContact, $ticket->Transaction->Contact) as $contact )
        if ( trim($contact->flash_on_control) )
          $comments[] = trim($contact->flash_on_control);
        $params['comment'] = $comments ? implode("\n", $comments) : NULL;
        
        if ( $cancontrol )
        {
          if ( $checkpoint->id )
          {
            $err = $tck = array();
            $ids = $params['ticket_id'];
            foreach ( $ids as $id )
            {
              $params['ticket_id'] = $id;
              $this->form = new ControlForm;
              $this->form->bind($params, $request->getFiles($this->form->getName()));
              if ( $this->form->isValid() ) try
              {
                $this->form->save();
                $this->tickets[] = $this->form->getObject()->Ticket;
              } catch ( liEvenementException $e ) { error_log('TicketActions::executeControl() - '.$e->getMessage().' Passing by.'); }
              else
              {
                $err[] = $id;
                $this->tickets[] = $tck[$id] = Doctrine::getTable('Ticket')->find($id);
              }
            }
            foreach ( $err as $e )
              $this->errors[] = __('An error occurred controlling your ticket #%%id%%.', array('%%id%%' => $e))
                .($tck[$e] instanceof Ticket && !$tck[$e]->printed_at && !$tck[$e]->integrated_at ? ' '.__('This ticket is not sold yet.') : '');
            $this->success = count($err) < count($ids);
            return 'Result';
          }
          else // !$checkpoint->id
          {
            if ( !$params['checkpoint_id'] )
            {
              $this->getUser()->setFlash('error',__("Don't forget to specify a checkpoint"));
              //unset($params['checkpoint_id']);
              $params['ticket_id'] = implode(',',$params['ticket_id']);
              $this->form->bind($params);
            }
            else
            {
              $this->success = false;
              $failure = new FailedControl;
              $failure->complete($params);
              return 'Result';
            }
          }
        }
        else // !$cancontrol
        {
          $this->success = false;
          foreach ( $this->tickets as $ticket )
          {
            $failure = new FailedControl;
            if ( $ticket instanceof Ticket )
              $params['ticket_id'] = $ticket->id;
            $failure->complete($params);
          }
          return 'Result';
        }
      }
      else
      {
        $this->success = false;
        $this->errors[] = __("Don't forget to specify a checkpoint and a ticket id");
        return 'Result';
      }
    }
    
    return 'Success';
