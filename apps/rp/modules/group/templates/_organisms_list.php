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
*    Copyright (c) 2006-2011 Baptiste SIMON <baptiste.simon AT e-glop.net>
*    Copyright (c) 2006-2011 Libre Informatique [http://www.libre-informatique.fr/]
*
***********************************************************************************/
?>
<div class="sf_admin_edit ui-widget ui-widget-content ui-corner-all members">
  <div class="organisms">
  </div>
  <script type="text/javascript"><!--
    function group_organisms_loaded(data)
    {
      $('#more .organisms').html($($.parseHTML(data)).find(' .sf_admin_list'));
      $('#more .organisms tfoot a[href]').click(function(){
        $.get($(this).attr('href'),group_organisms_loaded);
        return false;
      });
    }
    function group_organisms_load()
    {
      $.get('<?php echo url_for('organism/groupList?id='.$group->id) ?>',group_organisms_loaded);
    }
    $(document).ready(function(){
      group_organisms_load();
    });
  --></script>
</div>
