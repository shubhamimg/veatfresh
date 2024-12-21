<?php session_start();

    include_once ('includes/custom-functions.php');
    include_once ('includes/functions.php');
    $function = new custom_functions;
    
    // set time for session timeout
    $currentTime = time() + 25200;
    $expired = 3600;
    // if session not set go to login page
    if (!isset($_SESSION['user'])) {
        header("location:index.php");
    }
    // if current time is more than session timeout back to login page
    if ($currentTime > $_SESSION['timeout']) {
        session_destroy();
        header("location:index.php");
    }
    // destroy previous session timeout and create new one
    unset($_SESSION['timeout']);
    $_SESSION['timeout'] = $currentTime + $expired;
    $function = new custom_functions;
    $permissions = $function->get_permissions($_SESSION['id']);
    
    include "header.php";?>
<html>
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8">
        <title><?=$settings['app_name']?> - Dashboard</title>
	</head>
    <body>
       
        <!-- Content Wrapper. Contains page content -->
        <div class="content-wrapper">
            <!-- Content Header (Page header) -->
            <section class="content-header">
                <h1>Home</h1>
                <ol class="breadcrumb">
                    <li>
                        <a href="home.php"> <i class="fa fa-home"></i> Home</a>
                    </li>
                </ol>
            </section>
            <section class="content">
                <div class="row">
                    <div class="col-lg-4 col-xs-6">
                        <div class="small-box bg-aqua">
                            <div class="inner">
                                <h3><?=$function->rows_count('orders');?></h3>
                                <p>Orders</p>
                            </div>
                            <div class="icon"><i class="fa fa-shopping-cart"></i></div>
                            <a href="orders.php" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-4 col-xs-6">
                        <div class="small-box bg-yellow">
                            <div class="inner">
                                <h3><?=$function->rows_count('products');?></h3>
                                <p>Products</p>
                            </div>
                            <div class="icon"><i class="fa fa-cubes"></i></div>
                            <a href="products.php" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                    <div class="col-lg-4 col-xs-6">
                        <div class="small-box bg-red">
                            <div class="inner">
                                <h3><?=$function->rows_count('users');?></h3>
                                <p>Registered Customers</p>
                            </div>
                            <div class="icon"><i class="fa fa-users"></i></div>
                            <a href="customers.php" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                        <div class="box box-success">
                            
                             <?php 
                                if(isset($_GET['report_type']) && $_GET['report_type'] == '1-month'){
                                    $year = date("Y");
                                    $curdate = date('Y-m-d');
                                    $sql = "SELECT SUM(final_total) AS total_sale,DATE(date_added) AS order_date FROM orders WHERE YEAR(date_added) = YEAR(CURRENT_DATE - INTERVAL 1 MONTH) AND MONTH(date_added) = MONTH(CURRENT_DATE - INTERVAL 1 MONTH) GROUP BY DATE(date_added) ORDER BY DATE(date_added) ASC";
                                    $db->sql($sql);
                                    $result_order = $db->getResult(); 
                                }else if(isset($_GET['report_type']) && $_GET['report_type'] == '3-month'){
                                    $year = date("Y");
                                    $curdate = date('Y-m-d');
                                    $sql = "SELECT SUM(final_total) AS total_sale,DATE(date_added) AS order_date FROM orders WHERE date_added >= last_day(now()) + interval 1 day - interval 4 month GROUP BY DATE(date_added) ORDER BY DATE(date_added) ASC";
                                    $db->sql($sql);
                                    $result_order = $db->getResult();
                                }else if(isset($_GET['report_type']) && $_GET['report_type'] == '1-week'){
                                    $year = date("Y");
                                    $curdate = date('Y-m-d');
                                    $sql = "SELECT SUM(final_total) AS total_sale,DATE(date_added) AS order_date FROM orders WHERE YEAR(date_added) = '$year' AND DATE(date_added)<'$curdate' GROUP BY DATE(date_added) ORDER BY DATE(date_added) DESC  LIMIT 0,7";
                                    $db->sql($sql);
                                    $result_order = $db->getResult();
                                }else{
                                    $year = date("Y");
                                    $curdate = date('Y-m-d');
                                    $sql = "SELECT SUM(final_total) AS total_sale,DATE(date_added) AS order_date FROM orders WHERE YEAR(date_added) = '$year' AND DATE(date_added)<'$curdate' GROUP BY DATE(date_added) ORDER BY DATE(date_added) DESC  LIMIT 0,7";
                                    $db->sql($sql);
                                    $result_order = $db->getResult(); 
                                }
                                 ?>
                                <div class="tile-stats" style="padding:10px;">
                                    <select class="form-control" name="reports" id="reports">
                                        <option value="1-week" <?php if($_GET['report_type'] == '1-week'){echo 'selected';}?>>1 Week</option>
                                        <option value="1-month" <?php if($_GET['report_type'] == '1-month'){echo 'selected';}?>>Last 1 Month</option>
                                        <option value="3-month" <?php if($_GET['report_type'] == '3-month'){echo 'selected';}?>>Last 3 Months</option>
                                    </select>
                                    <div id="earning_chart" style="width:100%;height:350px;"></div>
                                </div>
                        </div>
                        
                    </div>
                    <div class="col-lg-6 col-md-6 col-sm-12 col-xs-12">
                        <div class="box box-danger">
                            <?php
                             $sql="SELECT `name`,(SELECT count(id) from `products` p WHERE p.category_id = c.id ) as `product_count` FROM `category` c";
                                $db->sql($sql);
                                $result_products = $db->getResult(); ?>
                                <div class="tile-stats" style="padding:10px;">
                                    <div id="piechart" style="width:100%;height:350px;"></div>
                                </div>
                        </div>
                        
                    </div>
                    
                </div>

              
                <?php if($permissions['orders']['read']==1) { ?>
				<div class="row">
					<div class="col-md-12">
						<div class="box box-info">
							<div class="box-header with-border">
								<h3 class="box-title">Latest Orders</h3>
								<div class="box-tools pull-right">
									<button class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-minus"></i></button>
									<button class="btn btn-box-tool" data-widget="remove"><i class="fa fa-times"></i></button>
								</div>
				<form method="POST" id="filter_form" name="filter_form">
    
                <div class="form-group pull-right">
                    <!--<h3 class="box-title">Filter by status</h4>-->
                        <select id="filter_order" name="filter_order" placeholder="Select Status" required class="form-control" style="width: 300px;">
                            <option value="">All Orders</option>
                            <option value='received'>Received</option>
                            <option value='processed'>Processed</option>
                            <option value='shipped'>Shipped</option>
                            <option value='delivered'>Delivered</option>
                            <option value='cancelled'>Cancelled</option>
                        </select>
                        
                    <!-- <input type="submit" name="filter_btn" id="filter_btn" value="Filter" class="btn btn-primary btn-md"> -->
                </div>
                </form>
							</div>
							<div class="box-body">
								<div id="toolbar">
									<form method="post">
										<select class='form-control' id="category_id" name="category_id" placeholder="Select Category" required style="display: none;">
											<?php
												$Query="select name, id from category";
												$db->sql($Query);
                                                $result=$db->getResult();
												if($result)
												{
												?>
											<option value="">All Products</option>
                                            <?php foreach($result as $row){?>
                                                 <option value='<?=$row['id']?>'><?=$row['name']?></option>
                                                <?php }} 
                                                    ?>
											
										</select>
									</form>
								</div>
								<div class="table-responsive">
									<table class="table no-margin" id='orders_table' data-toggle="table" 
										data-url="api-firebase/get-bootstrap-table-data.php?table=orders"
										data-page-list="[5, 10, 20, 50, 100, 200]"
										data-show-refresh="true" data-show-columns="true"
										data-side-pagination="server" data-pagination="true"
										data-search="true" data-trim-on-search="false"
										data-sort-name="id" data-sort-order="desc"
										data-toolbar="#toolbar" data-query-params="queryParams"
										>
										<thead>
											<tr>
												<th data-field="id" data-sortable='true'>O.ID</th>
												<th data-field="user_id" data-sortable='true' data-visible="false">User ID</th>
												 <th data-field="qty" data-sortable='true' data-visible="false">Qty</th>
                                                <th data-field="name" data-sortable='true'>U.Name</th>
												<th data-field="mobile" data-sortable='true' data-visible="true">Mob.</th>
												<th data-field="items" data-sortable='true' data-visible="false">Items</th>
												<th data-field="total" data-sortable='true' data-visible="true">Total(<?=$settings['currency']?>)</th>
												<th data-field="delivery_charge" data-sortable='true'>D.Chrg</th>
												<th data-field="tax" data-sortable='false'>Tax <?=$settings['currency']?>(%)</th>
												<th data-field="discount" data-sortable='true' data-visible="true">Disc.<?=$settings['currency']?>(%)</th>
												<th data-field="promo_code" data-sortable='true' data-visible="false">Promo Code</th>
												<th data-field="promo_discount" data-sortable='true' data-visible="true">Promo Disc.(<?=$settings['currency']?>)</th>
												<th data-field="wallet_balance" data-sortable='true' data-visible="true">Wallet Used(<?=$settings['currency']?>)</th>
												<th data-field="final_total" data-sortable='true'>F.Total(<?=$settings['currency']?>)</th>
												<th data-field="deliver_by" data-sortable='true' data-visible='false'>Deliver By</th>
												<th data-field="payment_method" data-sortable='true' data-visible="true">P.Method</th>
												<th data-field="address" data-sortable='true' data-visible="false">Address</th>
												<th data-field="delivery_time" data-sortable='true' data-visible='false'>D.Time</th>
												<th data-field="status" data-sortable='true' data-visible='false'>Status</th>
												<th data-field="active_status" data-sortable='true' data-visible='true'>A.Status</th>
												<th data-field="date_added" data-sortable='true' data-visible="false">O.Date</th>
												<th data-field="operate">Action</th>
                                               
												
											</tr>
										</thead>
									</table>
								</div>
							</div>
							<div class="box-footer clearfix">
								<a href="orders.php" class="btn btn-sm btn-default btn-flat pull-right">View All Orders</a>
							</div>
						</div>
					</div>
				</div>
                <?php } else { ?>
                <div class="alert alert-danger">You have no permission to view orders.</div>
            <?php } ?>
			</section>
        </div>
<script>
	$('#filter_order').on('change',function(){
    $('#orders_table').bootstrapTable('refresh');
    });
</script>
<script>
function queryParams(p){
	return {
		"filter_order": $('#filter_order').val(),
		limit:p.limit,
		sort:p.sort,
		order:p.order,
		offset:p.offset,
		search:p.search
	};
}
</script>
<?php include "footer.php";?>
<script type="text/javascript" src="https://www.gstatic.com/charts/loader.js"></script>
<script>
    google.charts.load('current', {'packages':['corechart']});
    google.charts.setOnLoadCallback(drawPieChart);

    function drawPieChart() {

        var data1 = google.visualization.arrayToDataTable([
            ['Product', 'Count'],
            <?php
                foreach($result_products as $row){ echo "['".$row['name']."',".$row['product_count']."],";}
            ?>
        ]);
    
        var options1 = {
          title: 'Category Wise Product\'s Count',
          is3D: true
        };
    
        var chart1 = new google.visualization.PieChart(document.getElementById('piechart'));
    
        chart1.draw(data1, options1);
    }
</script>

<script>
    google.charts.load('current', {'packages':['bar']});
    google.charts.setOnLoadCallback(drawChart);
    function drawChart() {
        var data = google.visualization.arrayToDataTable([
            ['Date', 'Total Sale In <?=$settings['currency']?>'],
            <?php foreach($result_order as $row){
                $date = date('d-M', strtotime($row['order_date']));
                echo "['".$date."',".$row['total_sale']."],"; 
            } ?>]);
        var url = window.location.href; 
        if(url == 'https://admin.veatfresh.in/home.php?report_type=1-month'){
            const current = new Date();
            current.setMonth(current.getMonth()-1);
            const previousMonth = current.toLocaleString('default', { month: 'long' });
            var options = {
                chart: {
                    title: 'Monthly Sales',
                    subtitle: 'Total Sale In Last Month (Month: '+previousMonth+')',
                }
            };
        }else if(url == 'https://admin.veatfresh.in/home.php?report_type=3-month'){
            const current = new Date();
            const cur = new Date();
            current.setMonth(current.getMonth()-3);
            const previousTMonth = current.toLocaleString('default', { month: 'long' });
            cur.setMonth(cur.getMonth()-1);
            const previousMonth = cur.toLocaleString('default', { month: 'long' });
            var options = {
                chart: {
                    title: '3 Month Sales',
                    subtitle: 'Total Sale In Last 3 Months (Monthsw: '+previousTMonth+' To '+previousMonth+')',
                }
            };
        }else{
            var options = {
                chart: {
                    title: 'Weekly Sale',
                    subtitle: 'Total Sale In Last Week (Month: <?php echo date("M"); ?>)',
                }
            };
        }
        
    var chart = new google.charts.Bar(document.getElementById('earning_chart'));
    chart.draw(data,google.charts.Bar.convertOptions(options));
    }
</script>
<script>
$("#reports").on('change',function(){
    //var loc = location.href; 
    var report = $(this).val();
    // loc += loc.indexOf("?") === -1 ? "?" : "&";

    // location.href = loc + "report_type="+report;
    var url = window.location.href;  
    var url2 = url.split('?')[0];
    if (url2.indexOf('?') > -1){
       url2 += '&report_type='+report
    }else{
       url2 += '?report_type='+report
    }
    window.location.href = url2;
});
</script>
  </body>
</html>