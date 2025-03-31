<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap.min.css">
<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/css/bootstrap-theme.min.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/2.1.3/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.5/js/bootstrap.min.js"></script>

<script src="/system/expressionengine/modules/subscriber/views/tableExport.js"></script>
<script type="text/javascript" src="/system/expressionengine/modules/subscriber/views/jquery.base64.js"></script>
<style type="text/css">.btn-primary{ font-size: 14px } .dropdown-menu{ position: inherit; }</style>

<h3><?=lang('ref_view_subscriberlist')?></h3><br>

<div class="table-responsive" id="employee_table">  
<table id="employees" class="table table-striped">
	<tbody>
		<tr>
			<td>S.N.</td>
			<td>Name</td>
			<td>Email</td>
			<td>Mobile</td>
		</tr>
		<?php $count = 1;
			foreach($subscriberlist as $key => $value){?>
		<tr>			
			<td><?php echo $count; ?>.</td>
			<td><?php echo $value['name']; ?></td>
			<td><?php echo $value['email']; ?></td>
			<td><?php echo $value['mobile']; ?></td>		
		</tr>
		<?php $count++; }?>
	</tbody>
</table>
</div>
<br>
<?php if (!empty($subscriberlist)) { ?>
<div class="btn-group pull-right">
	<button type="button" class="btn btn-primary btn-lg dropdown-toggle" data-toggle="dropdown">Export <span class="caret"></span></button>
	<ul class="dropdown-menu" role="menu">
		<li><a class="dataExport" data-type="csv">CSV</a></li>
		<li><a class="dataExport" data-type="excel">XLS</a></li>       
				 			  
	</ul>
</div>
<?php } ?>

<hr>

<h3>Elite Member List</h3><br>

<div class="table-responsive" id="elitesubscriber_table">  
<table id="elitesubscribers" class="table table-striped">
	<tbody>
		<tr>
			<td>S.N.</td>
			<td>Name</td>
			<td>Email</td>
			<td>Mobile</td>
		</tr>
		<?php $count1 = 1;
			foreach($elitesubscriberlist as $key => $eltvalue){?>
		<tr>			
			<td><?php echo $count1; ?>.</td>
			<td><?php echo $eltvalue['name']; ?></td>
			<td><?php echo $eltvalue['email']; ?></td>
			<td><?php echo $eltvalue['mobile']; ?></td>		
		</tr>
		<?php $count1++; }?>
	</tbody>
</table>
</div>
<br>
<?php if (!empty($elitesubscriberlist)) { ?>
	<div class="btn-group pull-right">
		<button type="button" class="btn btn-primary btn-lg dropdown-toggle" data-toggle="dropdown">Export <span class="caret"></span></button>
		<ul class="dropdown-menu" role="menu">
			<li><a class="dataExport1" data-type="csv">CSV</a></li>
			<li><a class="dataExport1" data-type="excel">XLS</a></li>       
					 			  
		</ul>
	</div>
<?php } ?>





<script>  
$( document ).ready(function() {
	$(".dataExport").click(function() {
		var exportType = $(this).data('type');		
		$('#employees').tableExport({
			type : exportType,			
			escape : 'false',
			ignoreColumn: []
		});		
	});

	$(".dataExport1").click(function() {
		var exportType = $(this).data('type');		
		$('#elitesubscribers').tableExport({
			type : exportType,			
			escape : 'false',
			ignoreColumn: []
		});		
	});
});  
 </script>  