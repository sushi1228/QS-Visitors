<?php
include("data_scripts/pdo_conn.php");
include("data_scripts/utils.php");
//IP Address QS center filter
$center = GetQSCenterIDByIP($pdo);
$center = ($center !== null && $center !== '') ? (string) $center : null;
//echo $center;
//$center = 5; //test
?>
<!doctype html>
<html class="fixed">

<head>

	<!-- Basic -->
	<meta charset="UTF-8">

	<meta name="keywords" content="Visitors Management System" />
	<meta name="description" content="QS Visitors Management System">

	<!-- Mobile Metas -->
	<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
	<!-- Title -->
	<title>QS Visitors Management System</title>
	<!-- Icon -->
	<link rel="shortcut icon" href="assets/images/icon19.ico" />
	<!-- Web Fonts  -->
	<link href="http://fonts.googleapis.com/css?family=Open+Sans:300,400,600,700,800|Shadows+Into+Light"
		rel="stylesheet" type="text/css">

	<!-- Vendor CSS -->
	<link rel="stylesheet" href="assets/vendor/bootstrap/css/bootstrap.css" />
	<link rel="stylesheet" href="assets/vendor/font-awesome/css/font-awesome.css" />
	<link rel="stylesheet" href="assets/vendor/magnific-popup/magnific-popup.css" />
	<link rel="stylesheet" href="assets/vendor/bootstrap-datepicker/css/datepicker3.css" />

	<!-- Theme CSS -->
	<link rel="stylesheet" href="assets/stylesheets/theme.css" />

	<!-- Skin CSS -->
	<link rel="stylesheet" href="assets/stylesheets/skins/default.css" />

	<!-- Theme Custom CSS -->
	<link rel="stylesheet" href="assets/stylesheets/theme-custom.css">

	<!-- Head Libs -->
	<script src="assets/vendor/modernizr/modernizr.js"></script>

	<!-- Jquery autocomplete -->
	<link rel="stylesheet" href="assets/stylesheets/jquery-ui.css">
	<script src="https://code.jquery.com/jquery-1.12.4.js"></script>
	<script src="https://code.jquery.com/ui/1.12.1/jquery-ui.js"></script>

	<script>
		jQuery().ready(function ($) {

			$('#VisitorName').css({ "border-color": "#bdbdbd", "background-color": "#FFF" });

			$("#VisitorName").autocomplete({
				source: "search_namein.php",
				minLength: 2,
				select: function (event, ui) {
					$("#VisitorID").val(ui.item.id);
					$("#Email").val(ui.item.email || "");
				}
			})
				.autocomplete("instance")._renderItem = function (ul, item) {
					var details = [];

					if ($.trim(item.email || "") !== "") {
						details.push(item.email);
					}

					if ($.trim(item.empid || "") !== "") {
						details.push("EmpID: " + item.empid);
					}

					if ($.trim(item.coname || "") !== "") {
						details.push(item.coname);
					}

					return $("<li>")
						.append("<div><span class='text-bold'>" + item.value + "</span>" +
							(details.length ? " - " + details.join(" - ") : "") + "</div>")
						.appendTo(ul);
				};

			$("#VisitorName").keydown(function (e) {
				if (e.which == 13) {
					e.preventDefault();
					$("#checkout").trigger("click");
					return false;
				}
			});

			$("#cancel").click(function () {
				$(location).attr('href', "iPad/index.php");
			});

			$("#checkout").click(function () {
				var err = 0, vn = 0; var msgerr = "";

				var name = $.trim($("#VisitorName").val());
				if (name === "") {
					err = 1;
					$('#VisitorName').css({ "border-color": "red", "background-color": "#F79BB7" });
				} else if (name.search(" ") === -1) {
					vn = 1;
					$('#VisitorName').css({ "border-color": "red", "background-color": "#F79BB7" });
				} else {
					$('#VisitorName').css({ "border-color": "#bdbdbd", "background-color": "#FFF" });
				}

				if ($.trim($("#VisitorID").val()) === "") {
					err = 1;
				}

				if (err !== 0 || vn !== 0) {
					if (err === 1 && $.trim($("#VisitorID").val()) === "") {
						msgerr = "Please type 2 or more characters of your name and <u>select</u> it from the list.";
					} else if (err === 1) {
						msgerr = "Your name is required.";
					} else if (vn === 1) {
						msgerr = "Please verify your name — use a space between first and last name.";
					}
					$("#errormsg").html("<center>" + msgerr + "</center>");
				} else {
					var Visitor = $.trim($("#VisitorName").val()).split(/\s+/);
					var data = {
						vfn: Visitor[0],
						vln: Visitor[1],
						vid: $("#VisitorID").val(),
						vemail: $.trim($("#Email").val()),
						centerid: $("#CenterID").val()
					};

					jQuery.ajax({
						url: 'chkvisitorout.php',
						type: "POST",
						dataType: "html",
						data: data,
						async: false,
						success: function (msg) {
							//alert(msg);
							if (msg.indexOf("Sorry") == 0) {
								$("#errormsg").html("<center>" + msg + "</center>");
							} else if (msg.indexOf("Thank") == 0) {
								$("#ipadout").html("<br><center><span class='text-info text-xl'>" + msg + "</span><br><br><br><img src='assets/images/ajax-loader.gif' ></center>");
								setTimeout("pageRedirect()", 1000);
							}
						}
					});
				}
			});
		});

		function pageRedirect() {
			window.location.replace("iPad/index.php");
		} 
	</script>

</head>

<body>
	<!-- start: page -->
	<section class="body-sign3">
		<div class="center-sign">
			<a href="/" class="logo pull-left">
				<img src="assets/images/logo.png" height="45" alt="Porto Admin" />
			</a>

			<div class="panel panel-sign">
				<div class="panel-title-sign mt-xl text-right">
					<h2 class="title text-uppercase text-bold m-none"><i class="fa fa-user mr-xs"></i>
						Visitor&nbsp;&nbsp;Check Out</h2>
				</div>
				<div class="panel-body" id="ipadout">
					<div class="alert alert-info">
						<p class="m-none text-semibold h6">Please type 2 or more characters of your name in the "Visitor
							Name" field below and <u><span class="text-danger">SELECT</span></u> it.</p>
					</div>
					<form name="fvisit" id="fvisit" method="post" onsubmit="return false;">
						<span class="text-danger">(*) required fields</span>
						<span class="text-danger" id="errormsg"></span>
						<input type="hidden" name="VisitorID" id="VisitorID" value="" />
						<input type="hidden" name="Email" id="Email" value="" />
						<input type="hidden" name="CenterID" id="CenterID" value="<?php echo $center; ?>" />
						<div class="form-group mb-lg">
							<label>Visitor First and Last Name<span class="required">*</span></label>
							<div class="input-group input-group-icon">
								<input name="VisitorName" id="VisitorName" type="text" class="form-control input-lg"
									placeholder="First name  Last name" />
							</div>
							<!-- <div class="row">
									<div class="col-sm-6 mb-lg">
										<label>Visitor Name<span class="required">*</span></label>										
										<input type="text" class="form-control" name="VisitorName" id="VisitorName" placeholder="First name  Last name" />										
									</div>
									<div class="col-sm-6 mb-lg">
										<label>E-mail Address<span class="required">*</span></label>
										<input name="Email" id="Email" type="text" class="form-control" />
									</div>
								</div>	 -->
						</div>

						<div class="row">
							<div class="col-sm-6">
								<div class="checkbox-custom checkbox-default">
									<input type="button" class="btn btn-default btn-block btn-lg mt-lg" id="cancel"
										value="Cancel">
								</div>
							</div>
							<div class="col-sm-6 text-right">
								<input type="button" class="btn btn-dark btn-block btn-lg mt-lg" id="checkout"
									value="Check Out">
							</div>
						</div>

					</form>
				</div>
			</div>

			<p class="text-center text-muted mt-md mb-md">&copy; <?php echo date("Y"); ?> Quick Start</p>
		</div>
	</section>
	<!-- end: page -->

	<!-- Vendor -->
	<script src="assets/vendor/jquery/jquery.js"></script>
	<script src="assets/vendor/jquery-browser-mobile/jquery.browser.mobile.js"></script>
	<script src="assets/vendor/bootstrap/js/bootstrap.js"></script>
	<script src="assets/vendor/nanoscroller/nanoscroller.js"></script>
	<script src="assets/vendor/bootstrap-datepicker/js/bootstrap-datepicker.js"></script>
	<script src="assets/vendor/magnific-popup/magnific-popup.js"></script>
	<script src="assets/vendor/jquery-placeholder/jquery.placeholder.js"></script>

	<script src="assets/vendor/jquery-maskedinput/jquery.maskedinput.js?v=2"></script>

	<!-- Theme Base, Components and Settings -->
	<script src="assets/javascripts/theme.js"></script>

	<!-- Theme Custom -->
	<script src="assets/javascripts/theme.custom.js"></script>

	<!-- Theme Initialization Files -->
	<script src="assets/javascripts/theme.init.js"></script>

</body>

</html>