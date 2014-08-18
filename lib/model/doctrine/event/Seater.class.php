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

class Seater
{
  protected $seats, $query, $kept, $done, $gauge_id = 0;
  
  public function __construct($gauge_id)
  {
    $this->kept = new Doctrine_Collection('Seat');
    $this->done = new Doctrine_Collection('Seat');
    $this->gauge_id = $gauge_id;
    $this->seats = $this->createQuery()->execute();
  }
  
  public function createQuery($alias = 's')
  {
    return Doctrine::getTable('Seat')->createQuery($alias)
      ->select("$alias.*, n.*")
      ->leftJoin("$alias.SeatedPlan sp")
      ->leftJoin('sp.Workspaces spw')
      ->leftJoin('spw.Gauge g')
      ->andWhere('g.id = ?', $this->gauge_id)
      ->leftJoin('g.Manifestation m')
      
      ->leftJoin("$alias.Tickets tck WITH tck.manifestation_id = m.id")
      ->andWhere('tck.id IS NULL')

      ->leftJoin("$alias.Neighbors n")
      
      ->orderBy("$alias.rank, $alias.name")
    ;
  }
  
  /**
    * Find seats for $qty tickets
    * @param $qty     integer             how many seats do you need
    * @param $exclude Doctrine_Collection if you want to avoid looking for seats in that direction... Doctrine_Collection with the association Seat->id => Seat
    * @return         Doctrine_Collection the Seats we have found
    *
    **/
  public function findSeats($qty, Doctrine_Collection $exclude = NULL)
  {
    if ( !$exclude )
      $exclude = new Doctrine_Collection('Seat');
    $this->done = $exclude;
    
    foreach ( $this->seats as $seat )
    {
      $this->_findSeatsWalk($seat);
      if ( $this->kept->count() >= intval($qty) )
        break;
      $this->kept = new Doctrine_Collection('Seat');
    }
    
    $i = 0;
    foreach ( $this->kept as $key => $seat )
    {
      if ( $i >= intval($qty) )
        unset($this->kept[$key]);
      $i++;
    }
    
    return $this->kept;
  }
  
  /**
    * Find orphans that can be generated by an action...
    * @param $seats   Doctrine_Collection|string|NULL  a Doctrine_Collection representing seats to book, or the name of a single seat, or nothing to look for every orphans in the venue
    * @return         Doctrine_Collection              detected orphans
    *
    **/
  public function findOrphansWith($seats = NULL)
  {
    $orphans = new Doctrine_Collection(Doctrine::getTable('Seat'));
    
    if ( is_string($seats) )
    {
      $this->kept = new Doctrine_Collection('Seat');
      foreach ( $this->seats as $key => $seat )
      if ( $seat->name == $seats )
      {
        $this->kept[$seat->id] = $seat;
        break;
      }
      $seats = new Doctrine_Collection(Doctrine::getTable('Seat'));
    }
    elseif ( $seats instanceof Doctrine_Collection )
      $this->kept = $seats;
    else
      throw new liSeatedException('If you want to look for orphans, you have to tell correctly the Seater what to look for.');
    
    try {
    foreach ( $this->kept as $seat )
      $orphans->merge($this->_findOrphansWithWalk($seat));
    }
    catch ( Exception $e )
    {
      echo $seats->getTable()->getTableName();
      echo "\n";
      echo $e->getMessage();
    }
    
    return $orphans;
  }
  
  protected function _findOrphansWithWalk(Seat $seat)
  {
    $seats = new Doctrine_Collection('Seat');
    
    foreach ( $seat->Neighbors as $n )
    {
      // check if the seat can be an orphan, at least
      if ( in_array($n->id, $this->kept->getPrimaryKeys())      // if it's one of the selected seats
        || !in_array($n->id, $this->seats->getPrimaryKeys()) )  // if it's a seat that is already booked
        continue; // this neighbor is not a free seat, it cannot be an orphan
      
      $n = $this->seats[array_search($n->id, $this->seats->getPrimaryKeys())];
      $i = $n->Neighbors->count();
      foreach ( $n->Neighbors as $n2 )
      {
        if (  in_array($n2->id, $this->kept->getPrimaryKeys())     // if it's one of the selected seats
          || !in_array($n2->id, $this->seats->getPrimaryKeys()) )  // if it's a seat that is already booked
          $i--; // the neighbor of the neighbor is not a free seat, so the neighbor is an orphan on this side
      }
      
      if ( $n->Neighbors->count() > 1 && $i == 0 )
        $seats[] = $n;
    }
    
    return $seats;
  }
  
  protected function _findSeatsWalk(Seat $seat)
  {
    $i = 0;
    if ( in_array($seat->id, $this->done->getPrimaryKeys()) || !in_array($seat->id, $this->seats->getPrimaryKeys()) )
      return $i;
    
    $this->done[$seat->id] = $seat;
    $this->kept[$seat->id] = $seat;
    
    // the other neighbors
    foreach ( $seat->Neighbors as $n )
      $this->_findSeatsWalk($this->seats[array_search($n->id, $this->seats->getPrimaryKeys())]);
  }
}