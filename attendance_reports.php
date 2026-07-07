<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();

}

include("data_scripts/pdo_conn.php");

$todayDisplay = date('m/d/Y');
$downloadBase = 'Reports generate/download_attendance_reports.php';
$centerName = htmlspecialchars((string) ($_SESSION['center'] ?? ''), ENT_QUOTES, 'UTF-8');
$companyOptions = [];

$qryCompanies = "
    SELECT DISTINCT r.CompanyName
    FROM Visitors r
    INNER JOIN Visits v ON v.VisitorID = r.VisitorID
    WHERE v.CenterID = " . $pdo->quote($_SESSION['id_center']) . "
      AND v.VisitDate = " . $pdo->quote(date('Y-m-d')) . "
      AND r.CompanyName IS NOT NULL
      AND r.CompanyName <> ''
    ORDER BY r.CompanyName
";

foreach ($pdo->query($qryCompanies) as $row) {
    $companyOptions[] = $row['CompanyName'];
}

?>

<section class="panel">
    <header class="panel-heading">
        <h2 class="panel-title">
            <?php echo $centerName !== '' ? $centerName . ' - ' : ''; ?>Daily Visitor Reports
        </h2>
    </header>

    <div class="panel-body">

        <p class="text-muted" style="margin-bottom:18px;">
            Download Excel attendance reports for Visitors.
        </p>

        <div class="row">

            <div class="col-md-12">
                <div class="well text-center" style="padding:18px;margin-bottom:10px;">
                    <i class="fa fa-users text-primary" style="font-size:28px;"></i>
                    <h5 style="margin-top:10px;">General Report</h5>
                    <label>Company</label>
                    <select id="companyFilter" class="form-control" style="max-width:300px;margin:0 auto 15px;">
                        <option value="">All Companies</option>

                        <?php foreach ($companyOptions as $company) { ?>
                            <option value="<?php echo htmlspecialchars($company, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo htmlspecialchars($company, ENT_QUOTES, 'UTF-8'); ?>
                            </option>
                        <?php } ?>
                    </select>
                    <button type="button" id="downloadGeneralReport" class="btn btn-primary btn-sm">
                        <i class="fa fa-download"></i> Download
                    </button>
                </div>
            </div>



        </div>

    </div>
</section>

<script>
    (function ($) {
        var downloadBase = <?php echo json_encode($downloadBase); ?>;

        function loadCompanies() {

            var selectedDate = $("#reportDate").val();

            $.get("get_report_companies.php", {
                date: selectedDate
            }, function (data) {

                $("#companyFilter").html(data);

            });

        }
        function buildDownloadUrl(type) {
            var selectedDate = $('#reportDate').val();
            var company = $('#companyFilter').val();

            return downloadBase + '?' + $.param({
                type: type,
                date_from: selectedDate,
                date_to: selectedDate,
                company: company
            });
        }

        $('#downloadGeneralReport').on('click', function () {
            window.location.href = buildDownloadUrl('general');
        });

        $('#reportDate').datepicker({
            changeMonth: true,
            numberOfMonths: 1,
            onSelect: function () {
                loadCompanies();
            }
        });

    loadCompanies();
    
    })(jQuery);
</script>