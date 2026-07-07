<?php
$qrycount = "Select count(*) as nb
from Users u
inner join Roles r on r.RoleID = u.Role";
//echo $qrycount;
$rscount = $pdo->query($qrycount)->fetch();
?>
<section class="panel">
	<header class="panel-heading">
		<div class="panel-actions">
			<a href="#" class="fa fa-caret-down"></a>
			<a href="#" class="fa fa-times"></a>
		</div>

		<h2 class="panel-title">
			<?php echo strtoupper($_SESSION["center"]) . " - Total Host/QS Staff: " . $rscount[0]; ?>
		</h2>
	</header>
	<div class="panel-body">
		<?php if (!empty($_GET["ac"])) {
			$msg = ($_GET["ac"] == 1) ? "Host/QS Staff has been successfully added into the system!" : "Host/QS Staff info has been successfully updated!."; ?>
			<span class="text-success" id="msg_update"><?php echo $msg; ?></span>
		<?php }
		if (!empty($rscount[0])) { ?>
			<br>Select table row(s) below before clicking one of the following buttons to change multiple user(s)'
			status(es) to Yes/No: <br>
			<input type="button" id="btntableup" value="Update selected Status(es)" class="btn btn-primary m-xs"
				data-toggle="tooltip" data-placement="top" title="Update selected QS Staff Status(es)" />&nbsp; &nbsp;
			<input type="button" id="btntableunsel" value="Unselect selected rows" class="btn m-xs" data-toggle="tooltip"
				data-placement="top" title="Unselect selected rows to display pagination" />&nbsp; &nbsp;
			<input type="button" id="btntabledel" value="Delete selected Host(s)" class="btn btn-danger m-xs"
				data-toggle="tooltip" data-placement="top" title="Delete selected QS Staff" />&nbsp; &nbsp;
			<table class="table table-bordered table-striped mb-none" id="datatable-default">
				<thead>
					<tr>
						<th class="colHidden" width="1">hidden</th>
						<th>Active Status</th>
						<th>Host/QS Staff name</th>
						<th>Host/QS Email</th>
						<th>Role</th>
						<th>Cell Phone</th>
						<th>Edit</th>
						<th class="colHidden" width="1">hidden</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$qrylist = "Select u.UserID,
concat_ws(' ', FirstName, LastName) as name,
Email,
r.Role,
Mobile,
datetime,
u.Status
from Users u
inner join Roles r on r.RoleID = u.Role
order by datetime desc";
					foreach ($pdo->query($qrylist) as $rowlist) {
						$statusn = (empty($rowlist["Status"])) ? "No" : "Yes";
						?>
						<tr class="gradeX">
							<td class="colHidden" width="1"><?php echo $rowlist["datetime"]; ?></td>
							<td class="center" id="st_<?php echo $rowlist["UserID"]; ?>"><?php echo $statusn; ?></td>
							<td><?php echo $rowlist["name"]; ?></td>
							<td><?php echo $rowlist["Email"]; ?></td>
							<td class="center hidden-phone"><?php echo $rowlist["Role"]; ?></td>
							<td class="center hidden-phone"><?php echo $rowlist["Mobile"]; ?></td>
							<td class="center"><a href="admin.php?p=add_QS_Staff&uid=<?php echo $rowlist["UserID"]; ?>"
									class="on-default edit-row"><i class="fa fa-pencil"></i></a></td>
							<td class="colHidden" width="1"><?php echo $rowlist["UserID"]; ?></td>
						</tr>
					<?php } ?>

				</tbody>
			</table>
		<?php } ?>
	</div>
</section>

<script>
	$(document).ready(function () {
		var table = $('#datatable-default').DataTable({ select: true, "order": [[0, "desc"]] });

		/*$('#datatable-default tbody').on( 'click', 'tr', function () {
			$(this).toggleClass('selected');
			var rows = table.rows('.selected').data();
			var ids="";
			for(var key in rows) {
				var value = rows[key];						
				if (Number.isInteger(parseInt(key))) {
					ids+=value[7]+",";
				} 
			}
			$("#dataids").val(ids);					
		} );*/
		/*var count = 0;
		$("#datatable-default tbody tr" ).each(function() {				  
		  //var count = 0;
		  $(this).click(function() {
			var nbsel = table.rows('.selected').data().length;
			if (nbsel == 0) $("#datatable-default_paginate").show(); 
			else $("#datatable-default_paginate").hide();
			
			//count++;
			//if (count % 2 === 0)  $("#datatable-default_paginate").show();  					
			//else $("#datatable-default_paginate").hide();					
		  });
		}); */


		$('#datatable-default tbody').on('click', 'tr', function () {
			$(this).toggleClass('selected');
			var nbsel = table.rows('.selected').data().length;
			if (nbsel == 0) $("#datatable-default_paginate").show();
			else $("#datatable-default_paginate").hide();
		});

		$("#btntableunsel").click(function () {
			$('#datatable-default tbody tr').removeClass('selected');
			$("#datatable-default_paginate").show();
			//location.reload();
		});

		$('#btntableup').click(function () {
			var nbsel = table.rows('.selected').data().length;
			var status = "";
			if (confirm("Are you sure to update the status of " + nbsel + " user(s) selected?")) {
				//alert($("#dataids").val());	
				var rows = table.rows('.selected').data().toArray();
				//alert(JSON.stringify(rows, null, 4));

				// ajax
				var data = {
					idst: rows
				};
				jQuery.ajax({
					url: 'user_status.php',
					type: "POST",
					dataType: "html",
					data: data,
					async: false,
					success: function (msg) {
						//alert(msg);	
						$("#msg_update").html(msg);
						//table.rows('.gradeX').deselect();
						//table.rows('.selected').toggleClass('selected');
					}
				});

				for (var i = 0; i < nbsel; i++) {
					if (rows[i][1] == "Yes") status = "No";
					else status = "Yes";
					$("#st_" + rows[i][7]).html(status);
					rows[i][1] = status;
					//alert("#st_"+rows[i][7] + ":" + status);
				}

			}
		});

		$('#btntabledel').click(function () {
			var nbsel = table.rows('.selected').data().length;

			if (nbsel == 0) {
				alert("Please select at least one host to delete.");
				return;
			}

			if (confirm("Are you sure you want to delete " + nbsel + " selected host(s)?")) {
				var rows = table.rows('.selected').data().toArray();

				jQuery.ajax({
					url: 'delete_QS_Staff.php',
					type: "POST",
					dataType: "html",
					data: { idst: rows },
					success: function (msg) {
						$("#msg_update").html(msg);
						table.rows('.selected').remove().draw(false);
					}
				});
			}
		});
	});
</script>