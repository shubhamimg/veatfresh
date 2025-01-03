
<?php
include_once('send-email.php');
include_once('send-sms.php');
include_once('../includes/crud.php');
include_once('../includes/custom-functions.php');
include_once('../includes/variables.php');
$db = new Database();
$db->connect();
$db->sql("SET NAMES utf8");
$function = new custom_functions();
$settings = $function->get_settings('system_timezone',true);
$app_name = $settings['app_name'];
	$config = $function->get_configurations();
		if(isset($config['system_timezone']) && isset($config['system_timezone_gmt'])){
			date_default_timezone_set($config['system_timezone']);
			$db->sql("SET `time_zone` = '".$config['system_timezone_gmt']."'");
		}else{
	date_default_timezone_set('Asia/Kolkata');
	$db->sql("SET `time_zone` = '+05:30'");
}
/*place_order:1
 user_id:5
 mobile:9974692496
 product_variant_id:["1","2","3"]
 quantity:["3","3","1"]
 total:60.0
 delivery_charge:20.0
 discount:10
 final_total:55
 latitude:40.1451
 longitude:-45.4545
 promo_code:NEW20 {optional}
 payment_method:PAYTM
 address:bhuj
 delivery_time:Today - Evening (4:00pm to 7:00pm)
 status:[["received","11-06-2019 12:22:44pm"],["processed","13-06-2019 11:36:49am"],["shipped","13-06-2019 12:07:29pm"],["delivered","13-06-2019 01:57:00pm"]]
 */
 
$response = array();

if(isset($_POST['ajaxCall']) && !empty($_POST['ajaxCall'])){
	$accesskey="90336";	
}else{
	if(isset($_POST['accesskey']) && $_POST['accesskey'] != ''){
		$accesskey=$_POST['accesskey'];
	}else{
		$response['error']= true;
		$response['message']="accesskey required";
		print_r(json_encode($response));
		return false;
	}
	
}

if($access_key != $accesskey){
	$response['error']= true;
	$response['message']="invalid accesskey";
	print_r(json_encode($response));
	return false;
}

if(isset($_POST['place_order']) && isset($_POST['user_id']) && !empty($_POST['product_variant_id'])){
	// echo "test";

    // $user_name = $db->escapeString($_POST['user_name']);
	$user_id = $db->escapeString($_POST['user_id']);
	$mobile = $db->escapeString($_POST['mobile']);
	$wallet_balance = (isset($_POST['wallet_balance']) && is_numeric($_POST['wallet_balance']))?$db->escapeString($_POST['wallet_balance']):0;
	$wallet_used = (isset($_POST['wallet_used']) && $_POST['wallet_used'] == 'true')?'true':'false';
	$items = $db->escapeString(stripslashes($_POST['product_variant_id']));
	$total = $db->escapeString($_POST['total']);
	$delivery_charge = $db->escapeString($_POST['delivery_charge']);
	$discount = (isset($_POST['discount']))?$db->escapeString($_POST['discount']):0;
	$final_total = $db->escapeString($_POST['final_total']);
	$payment_method = $db->escapeString($_POST['payment_method']);
	$address = $db->escapeString($_POST['address']);
	$delivery_time = (isset($_POST['delivery_time']))?$db->escapeString($_POST['delivery_time']):"";
	$latitude = $db->escapeString($_POST['latitude']);
	$longitude = $db->escapeString($_POST['longitude']);
	$instructions = $db->escapeString($_POST['instructions']);
	$promo_code = (isset($_POST['promo_code'])&& !empty($_POST['promo_code']) )?$db->escapeString($_POST['promo_code']):"";
	$status[] = array( 'received',date("d-m-Y h:i:sa") );
	// $item_details=json_decode(stripslashes(strip_tags($items)),1);
	$item_details=$function->get_product_by_variant_id($items);
	$total_amount=$total+$delivery_charge-$discount;
	// print_r($item_details);
	$quantity_arr=json_decode($_POST['quantity'],1);
	// $final_total=0;
	
	/* validate promo code if applied */
	if (isset($_POST['promo_code']) && $_POST['promo_code'] != '') {
	    $promo_code = $db->escapeString($_POST['promo_code']);
	    $response=$function->validate_promo_code($user_id,$promo_code,$total);
	    if($response['error']==true){
			echo json_encode($response);
			exit();
		}
	    
	}
	
	/* process wallet balance */
	$user_wallet_balance = $function->get_wallet_balance($user_id);
	
	if($user_wallet_balance >= $wallet_balance && $user_wallet_balance != 0 && $wallet_used=='true'){
	    
	    /* deduct the balance & set the wallet transaction */
	   // $new_balance = ($user_wallet_balance - $final_total);
	    $new_balance = $total_amount>$user_wallet_balance?0:$user_wallet_balance - $total_amount;
	    $function->update_wallet_balance($new_balance,$user_id);
		/* add wallet transaction */
		$wallet_txn_id = $function->add_wallet_transaction($user_id,'debit',$wallet_balance,'Used against Order Placement');
	}else{
	    $wallet_used = false;
	}
	
	$data = array(
		'user_id'=>$user_id,
		'mobile'=>$mobile,
		'delivery_charge'=>$delivery_charge,
		'wallet_balance' => ($wallet_used)?$wallet_balance:0,
		'total' => $total,
		'final_total' => $final_total,
		'payment_method'=>$payment_method,
		'address'=>$address,
		'delivery_time'=>$delivery_time,
		'status' => $db->escapeString(json_encode($status)),
		'latitude' => $latitude,
		'longitude' => $longitude,
		'promo_code' => $promo_code,
		'discount' => $discount,
		'instructions'=>$instructions,
		'active_status' => 'received'
	);
	$db->insert('orders',$data);
	$order_id = $db->getResult()[0];
	// print_r($item_details);
	for($i=0;$i<count($item_details);$i++){
		$product_id = $item_details[$i]['product_id'];
		// $unit = (float) filter_var( $item_details[$i][2], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION ) ; // float(55.35) 
		$measurement = $item_details[$i]['measurement'];
		$product_variant_id = $item_details[$i]['id'];
		// echo $product_variant_id;
		$measurement_unit_id = $item_details[$i]['measurement_unit_id'];
		$stock_unit_id = $item_details[$i]['stock_unit_id'];
		$price = $item_details[$i]['price'];
		$discounted_price = $item_details[$i]['discounted_price'];
		// $seller_id = $item_details[$i]['seller_id'];
		$type = $item_details[$i]['type'];
		$total_stock = $item_details[$i]['stock'];
		$quantity = $quantity_arr[$i];
		
		$sub_total = $discounted_price != 0?$discounted_price * $quantity:$price * $quantity;
		

		$data = array(
		    'user_id'=>$user_id,
		    'order_id'=>$db->escapeString($order_id),
		    // 'seller_id'=>$db->escapeString($seller_id),
		   'product_variant_id'=>$db->escapeString($product_variant_id),
		   'quantity'=>$db->escapeString($quantity),
		    'price'=>$db->escapeString($price),
		    'discounted_price'=>$db->escapeString($discounted_price),
		    'discount'=>$discount,
		    'sub_total'=>$db->escapeString($sub_total),
		    'status'=>$db->escapeString(json_encode($status)),
		    'active_status' => 'received'

		);
		$db->insert('order_items',$data);
		$res = $db->getResult();
		// $final_total=$final_total + $sub_total;
		$balance = $final_total/10;
		
		// $sql = "update seller set balance=balance+'".$balance."' where id=".$seller_id;
		// $db->sql($sql);
		// $res = $db->getResult();
	
			 if($type=='packet'){
				 $stock = $total_stock-$quantity;
				 // print_r($stock);
				 $sql = "update `product_variant` set `stock` = $stock where id = $product_variant_id";
				 // echo $sql;
				 $db->sql($sql);
				 $res = $db->getResult();
				 $db->select("product_variant","stock",null,"id='".$product_variant_id."'");
				 $variant_qty = $db->getResult();
				 if($variant_qty[0]['stock']<=0){
					$data = array(
						"serve_for"=> "Sold Out",
					);
					$db->update("product_variant",$data,"id=$product_variant_id");
					$res = $db->getResult();
				 }
			 }elseif($type=='loose'){
				 if($measurement_unit_id==$stock_unit_id){
					 $stock = $quantity*$measurement;
					}else{
						$db->select('unit','*',null,'id='.$measurement_unit_id);
						$unit = $db->getResult();
						// print_r($unit);
						// if(!empty($unit[0]['parent_id'])){
							$stock=$function->convert_to_parent(($measurement*$quantity),$unit[0]['id']);
							// $stock = ($measurement*$quantity)/$unit[0]['conversion'];
						// }
						// else{
						// 	$db->select('unit','conversion',null,"parent_id='".$measurement_unit_id."'");
						// 	$unit = $db->getResult();
						// 	$stock=$function->convert_to_child(($measurement*$quantity),$unit[0]['id']);
							
						// }
					}

					$sql = "update `product_variant` set `stock` = `stock` - $stock where product_id = $product_id AND type='loose'";
					// echo $sql;
					$db->sql($sql);
					$res = $db->getResult();
					$db->select("product_variant","stock",null,"product_id='".$product_id."'");
					$variant_qty = $db->getResult();
					if($variant_qty[0]['stock']<=0){
						$data = array(
							"serve_for"=> "Sold Out",
						);
						$db->update("product_variant",$data,"product_id=$product_id");
					}
			 }
		}
		
		// $final_total = $final_total + $delivery_charge; /* because delivery charge is already added from android */
		$data = array(
	        'final_total'=>$final_total
		);

		if($db->update('orders',$data,'id='.$order_id)){// Table name, column names and respective values
			$res = $db->getResult();
			$response['error'] = "false";
			$response['message'] = "Order placed successfully.";
			$response['order_id'] = $order_id;
			$_SESSION['checkout']=$_POST;
			$_SESSION['checkout']['order_id'] = $order_id;
			
			/* send email notification for the order received */
			$sql = "select name, email, mobile from users where id=".$user_id;
			$db->sql($sql);
			$res = $db->getResult();
			$to = $res[0]['email'];
			$mobile = $res[0]['mobile'];
			$subject = "Order received successfully";
			$message = "Hello, Dear ".ucwords($res[0]['name']).", We have received your order successfully. Your order summaries are as followed:<br><br>";
			$message .= "<b>Order ID :</b> #".$response['order_id']."<br><br>Ordered Items : <br>";
			// $items = json_decode($_POST['items']);
			$items = $db->escapeString($_POST['product_variant_id']);
			$quantity_arr=json_decode($_POST['quantity'],1);
			$item_details=$function->get_product_by_variant_id($items);
			// print_r($item_details);
			for($i=0;$i<count($item_details);$i++){
				$product_id = $item_details[$i]['product_id'];
				// $unit = (float) filter_var( $item_details[$i][2], FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION ) ; // float(55.35) 
				$measurement = $item_details[$i]['measurement'];
				$product_variant_id = $item_details[$i]['id'];
				// echo $product_variant_id;
				$measurement_unit_id = $item_details[$i]['measurement_unit_id'];
				$stock_unit_id = $item_details[$i]['stock_unit_id'];
				$price = $item_details[$i]['price'];
				$discounted_price = $item_details[$i]['discounted_price'];
				// $seller_id = $item_details[$i]['seller_id'];
				$type = $item_details[$i]['type'];
				$total_stock = $item_details[$i]['stock'];
				$quantity = $quantity_arr[$i];
				// print_r($item_details[0]['price']);
				$message .= "<b>Name : </b>".$item_details[0]['name']."<b> QTY :</b>".$quantity."<b> Subtotal :</b>".$item_details[0]['discounted_price']*$quantity."<br>";
			}
			$message .= "<b>Total Amount : </b>".$total." <b>Discount : </b>".$discount." <b>Final Total :</b>".$final_total;
			$message .= "<br>Payment Method : ".$payment_method;
			$message .= "<br><br>Thank you for placing an order with us!<br><br>You will receive future updates on your order via Email!";
			// send_email($to,$subject,$message);
			$message = "Hello, Dear ".ucwords($res[0]['name']).", We have received your order successfully. Your order is being processed. ";
			$message .= "Your Order ID is : #".$response['order_id']." Thank you for choosing eKart App. Have a great day.";
			//sendSms($mobile, $message);
			
			print_r(json_encode($response));
		}else{
			$response['error'] = "true";
			$response['message'] = "Could not place order. Try again!";
			$response['order_id'] = 0;
			$_SESSION['checkout']['order_id'] = 0;
			print_r(json_encode($response));
		}

	}elseif(isset($_POST['place_order']) && isset($_POST['user_id']) && empty(json_decode($_POST['product_variant_id']))){
		$response['error'] = "true";
		$response['message'] = "Order without items in cart can not be placed!";
		$response['order_id'] = 0;
		$_SESSION['checkout']['order_id'] = 0;
		print_r(json_encode($response));
	
}
if(isset($_POST['get_orders']) && isset($_POST['user_id'])) {
	$user_id = $db->escapeString($_POST['user_id']);
	$limit = (isset($_POST['limit']) && !empty($_POST['limit']) && is_numeric($_POST['limit']))?$_POST['limit']:10;
	$offset = (isset($_POST['offset']) && !empty($_POST['offset']) && is_numeric($_POST['offset']))?$_POST['offset']:0;
    $sql = "select *,(select name from users u where u.id=o.user_id) as user_name from orders o where user_id=".$user_id." ORDER BY date_added DESC LIMIT $offset,$limit";
    $db->sql($sql);
    $res = $db->getResult();
    $i=0; $j=0;
    foreach($res as $row){
        
        $res[$i]['date_added'] = date('d-m-Y h:i:sa', strtotime($res[$i]['date_added']));
        $sql = "select oi.*,p.name,p.image,v.measurement,(select short_code from unit u where u.id=v.measurement_unit_id) as unit from order_items oi join product_variant v on oi.product_variant_id=v.id join products p on p.id=v.product_id where order_id=".$row['id'];
        $db->sql($sql);
        $res[$i]['items'] = $db->getResult();
        $res[$i]['status'] = json_decode($res[$i]['status']);
            
            for($j=0; $j < count($res[$i]['items']); $j++){
                $res[$i]['items'][$j]['status'] = (!empty($res[$i]['items'][$j]['status']))?json_decode($res[$i]['items'][$j]['status']):array();
                // unset($res[$i]['items'][$j]['status']);
                $res[$i]['items'][$j]['image'] = DOMAIN_URL.$res[$i]['items'][$j]['image'];
            }
        $i++;
    }
        $orders = $order = array();
        
        if(!empty($res)){
            $orders['error'] = false;
            $orders['data'] = array_values($res);
            print_r(json_encode($orders));
        }else{
            $res['error'] = true;
            $res['message'] = "No orders found!";
            print_r(json_encode($res));
            // return $res;
        }
}
if(isset($_POST['update_order_item_status']) && isset($_POST['order_item_id'])) {
	$order_item_id = $db->escapeString($_POST['order_item_id']);
	$order_id = $db->escapeString($_POST['order_id']);
	$postStatus = $db->escapeString($_POST['status']);
	
	$sql = "SELECT COUNT(id) as cancelled FROM `order_items` WHERE id=".$order_item_id." && status LIKE '%$postStatus%'";
	$db->sql($sql);
	$res_cancelled = $db->getResult();
	if($res_cancelled[0]['cancelled']>0){
    	$response['error'] = true;
		$response['message'] = 'Could not update order status. Item is already '.ucwords($postStatus).'!';
		print_r(json_encode($response));
		return false;
	}
	
	$sql = "SELECT user_id,status,sub_total FROM order_items WHERE id =".$order_item_id;
	$db->sql($sql);
	$result=$db->getResult();
	
    if(!empty($result)){
    	$status = json_decode($result[0]['status']);
    	if($postStatus == 'cancelled'){
    	    $sql = 'SELECT final_total,total,user_id,payment_method,wallet_balance,delivery_charge,status FROM orders WHERE id='.$order_id;
    	    $db->sql($sql);
    	    $res_order = $db->getResult();
    	    $sql = 'SELECT oi.`product_variant_id`,oi.`quantity`,oi.`discounted_price`,oi.`price`,pv.`product_id`,pv.`type`,pv.`stock`,pv.`stock_unit_id`,pv.`measurement`,pv.`measurement_unit_id` FROM `order_items` oi join `product_variant` pv on pv.id = oi.product_variant_id WHERE oi.`id`='.$order_item_id;
    	    $db->sql($sql);
    	    $res_oi = $db->getResult();
    	   // print_r($res_oi);
    	   
    	     if($res_oi[0]['discounted_price']==0){
        	    $updated_final_total = $res_order[0]['final_total']!=0 && $res_order[0]['delivery_charge']==0?$res_order[0]['final_total'] - ( $res_oi[0]['price'] * $res_oi[0]['quantity'] ):0;
        	    $updated_total = $res_order[0]['total']-( $res_oi[0]['price'] * $res_oi[0]['quantity'] ); 
        	 }else{
    	        $updated_final_total = $res_order[0]['final_total']!=0 && $res_order[0]['delivery_charge']==0?$res_order[0]['final_total']-($res_oi[0]['discounted_price'] * $res_oi[0]['quantity']):0; 
    	        $updated_total = $res_order[0]['total']-($res_oi[0]['discounted_price'] * $res_oi[0]['quantity']); 
        	 }
        // 	 if($updated_final_total<$config['min_amount']){
        // 	     $updated_final_total+=$config['delivery_charge'];
        // 	 }
        // 	 echo $updated_final_total;
        	 
        	
        	 $delivery_charge = ($updated_total<$config['min_amount'])?$config['delivery_charge']:0;
        	 $sql_order = 'update orders set delivery_charge = '.$delivery_charge.',total='.$updated_total.' WHERE id='.$order_id;
        	 $db->sql($sql_order);
             
    	     $updated_final_total = ($updated_total<$config['min_amount'] && $res_order[0]['delivery_charge']==0 )?$updated_final_total+$delivery_charge:$updated_final_total;
    	   //  echo $updated_final_total;
    	   //  return false;
    	 
    	     $sql_order1 = 'update orders set final_total='.$updated_final_total.' WHERE id='.$order_id;
    	   //  echo $sql_order1;
    	   //   return false;
    	     $db->sql($sql_order1);
    	   // echo $sql_order;
    	   // echo $sql_order1;
        // 	  return false;
        	  
    	    
    	    
    	   // if($updated_final_total<$config['min_amount']){
    	   //     if($res_order[0]['delivery_charge']==0){
        // 	        $updated_final_total_with_d_charge=$updated_final_total+$config['delivery_charge'];
        // 	        $sql_final = 'update orders set delivery_charge = delivery_charge + '.$config['delivery_charge'].',final_total='.$updated_final_total_with_d_charge.' WHERE id='.$order_id;
        // 	        $db->sql($sql_final);
    	   //     }
    	   // }

    	    if($res_oi[0]['type']=='packet'){
    	        $sql = "UPDATE product_variant SET stock = stock + ".$res_oi[0]['quantity']." WHERE id='".$res_oi[0]['product_variant_id']."'";
    			$db->sql($sql);
    	    }else{
    	        /* When product type is loose */
    	        if($res_oi[0]['measurement_unit_id'] != $res_oi[0]['stock_unit_id']){
    	            $stock = $function->convert_to_parent($res_oi[0]['measurement'],$res_oi[0]['measurement_unit_id']);
    	            $stock = $stock * $res_oi[0]['quantity'];
    	            $sql = "UPDATE product_variant SET stock = stock + ".$stock." WHERE product_id='".$res_oi[0]['product_id']."'";
    			    $db->sql($sql);
    	        }else{
    	            $stock = $res_oi[0]['measurement'] * $res_oi[0]['quantity'];
    	            $sql = "UPDATE product_variant SET stock = stock + ".$stock." WHERE product_id='".$res_oi[0]['product_id']."'";
    			    $db->sql($sql);
    	        }
    	    }
        	$status[] = array($postStatus,date("d-m-Y h:i:sa") );
            $currentStatus = $postStatus;
            $data = array(
                'status' => $db->escapeString(json_encode($status)),
                'active_status' => $currentStatus
            );
            $db->update('order_items',$data,'id='.$order_item_id);
            
        	$sql = "SELECT id FROM order_items WHERE order_id=".$order_id;
        	$db->sql($sql);
        	$total = $db->numRows();
        	$sql = "SELECT id FROM `order_items` WHERE order_id=".$order_id." && (`active_status` LIKE '%cancelled%' OR `active_status` LIKE '%returned%' )";
        	$db->sql($sql);
        	$cancelled = $db->numRows();
        	if($cancelled==$total){
        	    if($res_order[0]['payment_method'] != 'cod' && $res_order[0]['payment_method'] !='COD'){
                	/* update user's wallet */
                    $user_id = $res_order[0]['user_id'];
                    $total_amount = $res_order[0]['total']+$res_order[0]['delivery_charge'];
                    $user_wallet_balance = $function->get_wallet_balance($user_id);
                    $new_balance = $user_wallet_balance + $total_amount;
                    // return false;
                    $function->update_wallet_balance($new_balance,$user_id);
                    /* add wallet transaction */
            	    $wallet_txn_id = $function->add_wallet_transaction($user_id,'credit',$total_amount,'Balance credited against item cancellation.');
                }else{
                if($res_order[0]['wallet_balance']!=0){
                    /* update user's wallet */
                    $user_id = $res_order[0]['user_id'];
                    // $total = $res[0]['total'];
                    $user_wallet_balance = $function->get_wallet_balance($user_id);
                    $new_balance = ($user_wallet_balance + $res_order[0]['wallet_balance']);
                    // echo $new_balance;
                    $function->update_wallet_balance($new_balance,$user_id);
        	        /* add wallet transaction */
        		    $wallet_txn_id = $function->add_wallet_transaction($user_id,'credit',$res_order[0]['wallet_balance'],'Balance credited against item cancellation.');
                }
                    
           }
        	    
            	$data_order = array(
            	    'status' => $db->escapeString(json_encode($status)),
            		'active_status' => $currentStatus
        	        );
                $db->update('orders',$data_order,'id='.$order_id);
        	}
        // 	if($res_order[0]['payment_method'] != 'cod' && $res_order[0]['payment_method'] !='COD'){
        //     	/* update user's wallet */
        //         $user_id = $result[0]['user_id'];
        //         $sub_total = $result[0]['sub_total'];
        //         $user_wallet_balance = $function->get_wallet_balance($user_id);
        //         $new_balance = ($user_wallet_balance + $sub_total);
        //         // echo $new_balance;
        //         // return false;
    	   //     $function->update_wallet_balance($new_balance,$user_id);
    	   //     /* add wallet transaction */
    		  //  $wallet_txn_id = $function->add_wallet_transaction($user_id,'credit',$sub_total,'Balance credited against item cancellation.');
    	   // }
		    
        	$response['error'] = false;
        	$response['message'] = 'Order cancelled successfully!';
        	$response['subtotal'] = $result[0]['sub_total'];
        	print_r(json_encode($response));
        	return false;
    	}
    	if($postStatus == 'returned'){
    	    $is_item_delivered = 0;
    	    foreach($status as $each_status){
        		if (in_array('delivered', $each_status)) {
        			$is_item_delivered = 1;
        			$config['max-product-return-days'];
        			$now = time(); // or your date as well
                    $status_date = strtotime($each_status[1]);
                    $datediff = $now - $status_date;
                    
                    $no_of_days = round($datediff / (60 * 60 * 24));
                    if($no_of_days > $config['max-product-return-days']){
                        $response['error'] = true;
            			$response['message'] = 'Oops! Sorry you cannot return the item now. You have crossed product\'s maximum return period';
            			print_r(json_encode($response));
            			return false;
                    }
        		}
        	}
        	if(!$is_item_delivered){
        	    $response['error'] = true;
    			$response['message'] = 'Cannot return item unless it is delivered!';
    			print_r(json_encode($response));
    			return false;
        	}
        	/* if delivered take the item as returned */
        	$status[] = array($postStatus,date("d-m-Y h:i:sa") );
            $data = array(
                'status' => $db->escapeString(json_encode($status)),
                'active_status' => $postStatus
            );
            $db->update('order_items',$data,'id='.$order_item_id);
            /* update user's wallet */
            $user_id = $result[0]['user_id'];
            $sub_total = $result[0]['sub_total'];
            $user_wallet_balance = $function->get_wallet_balance($user_id);
            $new_balance = ($user_wallet_balance + $sub_total);
	        $function->update_wallet_balance($new_balance,$user_id);
	        /* add wallet transaction */
		    $wallet_txn_id = $function->add_wallet_transaction($user_id,'credit',$sub_total,'Balance credited on product return.');
		    
		    /* check for other item status and summery of order */
		    $sql = "SELECT id FROM order_items WHERE order_id=".$order_id;
        	$db->sql($sql);
        	$total = $db->numRows();
        	$sql = "SELECT id FROM `order_items` WHERE order_id=".$order_id." && (`active_status` LIKE '%cancelled%' OR `active_status` LIKE '%returned%' )";
        	$db->sql($sql);
        	$returned = $db->numRows();
        	if($returned == $total){
        	    $sql = "SELECT status FROM orders WHERE id =".$order_id;
            	$db->sql($sql);
            	$res = $db->getResult();
            	$status_order=json_decode($res[0]['status']);
            	$status_order[] = array($postStatus,date("d-m-Y h:i:sa") );
            	$data_order = array(
            	    'status' => $db->escapeString(json_encode($status)),
            		'active_status' => $postStatus
            	);
                $db->update('orders',$data_order,'id='.$order_id);
        	}
        	$response['error'] = false;
        	$response['message'] = 'Order item returned request placed successfully! Amount is credited to your wallet';
        	$response['subtotal'] = $result[0]['sub_total'];
        	print_r(json_encode($response));
        	return false;
    	}
    }else{
	    $response['error'] = true;
    	$response['message'] = 'Order item not found!';
    	print_r(json_encode($response));
    	return false;
	}
}
if(isset($_POST['update_order_status']) && isset($_POST['id'])) {
	$id = $db->escapeString($_POST['id']);
	$postStatus = $db->escapeString($_POST['status']);
	if(isset($_POST['delivery_boy_id']) && $_POST['delivery_boy_id'] != ''){
		$delivery_boy_id = $db->escapeString($_POST['delivery_boy_id']);
		$sql="UPDATE orders SET `delivery_boy_id`='".$delivery_boy_id."' WHERE id=".$id;
		$db->sql($sql);
	}
	$sql = "SELECT COUNT(id) as cancelled FROM `orders` WHERE id=".$id." && (active_status LIKE '%cancelled%' OR active_status LIKE '%returned%')";
	$db->sql($sql);
	$res_cancelled = $db->getResult();
	if($res_cancelled[0]['cancelled']>0){
    	$response['error'] = true;
		$response['message'] = 'Could not update order status!';
		print_r(json_encode($response));
		return false;
	}

	$sql="select user_id,payment_method,wallet_balance,total,delivery_charge,status from orders where id=".$id;
	$db->sql($sql); // Table name, Column Names, JOIN, WHERE conditions, ORDER BY conditions
	$res = $db->getResult();
	$sql = "SELECT sub_total FROM order_items WHERE order_id=".$id;
	$db->sql($sql);
	$res_query = $db->getResult();
	$sql = "SELECT COUNT(id) as total FROM `orders` WHERE user_id=".$res[0]['user_id']." && status LIKE '%delivered%'";
	$db->sql($sql);
	$res_count = $db->getResult();
	$sql = "SELECT * FROM `users` WHERE id=".$res[0]['user_id'];
	$db->sql($sql);
	$res_user = $db->getResult();
	if(!empty($res)){
	$status = json_decode($res[0]['status']);
	$user_id =  $res[0]['user_id'];
	foreach($status as $each){
		if (in_array($postStatus, $each)) {
			$response['error'] = true;
			$response['message'] = 'Could not update order status. Try again!';
			print_r(json_encode($response));
			return false;
		}
	}
	if($postStatus=='cancelled' || $postStatus=='returned'){
	    $sql = 'SELECT oi.`product_variant_id`,oi.`quantity`,pv.`product_id`,pv.`type`,pv.`stock`,pv.`stock_unit_id`,pv.`measurement`,pv.`measurement_unit_id` FROM `order_items` oi join `product_variant` pv on pv.id = oi.product_variant_id WHERE `order_id`='.$id;
	    $db->sql($sql);
	    $res_oi = $db->getResult();
	    for($i=0;$i<count($res_oi);$i++){
    	    if($res_oi[$i]['type']=='packet'){
    	        $sql = "UPDATE product_variant SET stock = stock + ".$res_oi[$i]['quantity']." WHERE id='".$res_oi[$i]['product_variant_id']."'";
    			$db->sql($sql);
    	    }else{
    	        /* When product type is loose */
    	        if($res_oi[$i]['measurement_unit_id'] != $res_oi[$i]['stock_unit_id']){
    	            $stock = $function->convert_to_parent($res_oi[$i]['measurement'],$res_oi[$i]['measurement_unit_id']);
    	            $stock = $stock * $res_oi[$i]['quantity'];
    	            $sql = "UPDATE product_variant SET stock = stock + ".$stock." WHERE product_id='".$res_oi[$i]['product_id']."'";
    	            //echo $sql;
    			    $db->sql($sql);
    	        }else{
    	            $stock = $res_oi[$i]['measurement'] * $res_oi[$i]['quantity'];
    	            $sql = "UPDATE product_variant SET stock = stock + ".$stock." WHERE product_id='".$res_oi[$i]['product_id']."'";
    			    $db->sql($sql);
    	        }
    	    }
	    }
	     if($res[0]['payment_method'] != 'cod' && $res[0]['payment_method'] !='COD'){
        	/* update user's wallet */
            $user_id = $res[0]['user_id'];
            $total = $res[0]['total']+$res[0]['delivery_charge'];
            $user_wallet_balance = $function->get_wallet_balance($user_id);
            $new_balance = $user_wallet_balance + $total;
            // return false;
            $function->update_wallet_balance($new_balance,$user_id);
            /* add wallet transaction */
    	    $wallet_txn_id = $function->add_wallet_transaction($user_id,'credit',$sub_total,'Balance credited against item cancellation.');
        }else{
        if($res[0]['wallet_balance']!=0){
            /* update user's wallet */
            $user_id = $res[0]['user_id'];
            // $total = $res[0]['total'];
            $user_wallet_balance = $function->get_wallet_balance($user_id);
            $new_balance = ($user_wallet_balance + $res[0]['wallet_balance']);
            $function->update_wallet_balance($new_balance,$user_id);
	        /* add wallet transaction */
		    $wallet_txn_id = $function->add_wallet_transaction($user_id,'credit',$sub_total,'Balance credited against item cancellation.');
        }
            
   }
	}
	
	if($postStatus=='delivered'){
		$sql = "SELECT delivery_boy_id,final_total FROM orders WHERE id=".$id;
		$db->sql($sql);
		$res_boy = $db->getResult();
		if($res_boy[0]['delivery_boy_id']!=0){
			$sql = "SELECT bonus FROM delivery_boys WHERE id=".$res_boy[0]['delivery_boy_id'];
			$db->sql($sql);
			$res_bonus = $db->getResult();
			$reward = $res_boy[0]['final_total']/100*$res_bonus[0]['bonus'];
			$sql = "UPDATE delivery_boys SET balance = balance + ceil($reward) WHERE id=".$res_boy[0]['delivery_boy_id'];
			$db->sql($sql);
		}
		if($config['is-refer-earn-on']==1){
			if($res_boy[0]['final_total']>=$config['min-refer-earn-order-amount']){
				if($res_count[0]['total']==0){
					if($res_user[0]['friends_code'] != ''){
						if($config['refer-earn-method']=='percentage'){
							$percentage = $config['refer-earn-bonus'];
							$bonus_amount = $res_boy[0]['final_total']/100*$percentage;
							if($bonus_amount>$config['max-refer-earn-amount']){
								$bonus_amount = $config['max-refer-earn-amount'];
							}
							}else{
								$bonus_amount = $config['refer-earn-bonus'];
							}
						$sql  = "SELECT name,friends_code FROM users WHERE id=".$res[0]['user_id'];
						$db->sql($sql);
						$res_data = $db->getResult();
						
						$sql = " select id from `users` where `referral_code` = '".$res_data[0]['friends_code']."'";
						$db->sql($sql);
						$friend_user = $db->getResult();
						
						if(!empty($friend_user))
						    $function->add_wallet_transaction($friend_user[0]['id'],'credit',floor($bonus_amount),'Refer & Earn Bonus on first order by '.ucwords($res_data[0]['name']));
						
						$sql = "UPDATE users SET balance = balance + floor($bonus_amount) WHERE referral_code='".$res_data[0]['friends_code']."'";
						$db->sql($sql);
						
					}

				}

			}
		}
	}
	$temp=[];
	foreach($status as $s){
	    array_push($temp,$s[0]);
	}
	$sql = "SELECT id,active_status FROM order_items WHERE order_id=".$id;
    $db->sql($sql);
    $result = $db->getResult();
	if($postStatus=='cancelled'){
//     	$i = sizeof($status);
//         $currentStatus = $status[$i-1][0];
//         if($currentStatus=='delivered'){
//             $response['error'] = true;
// 			$response['message'] = 'Could not cancel. order already delivered!';
// 			print_r(json_encode($response));
// 			return false;
//         }
            
        
	   // if (!in_array('processed', $temp)) {
    //     $status[] = array('processed',date("d-m-Y h:i:sa") );
    //     $data = array('status' => $db->escapeString(json_encode($status)));
        
	   // }
	   // if (!in_array('shipped', $temp)) {
	   //     $status[] = array('shipped',date("d-m-Y h:i:sa") );
	   //     $data = array('status' => $db->escapeString(json_encode($status)));
	        
	    
	   // }
	   // if (!in_array('delivered', $temp)) {
	   //     $status[] = array('delivered',date("d-m-Y h:i:sa") );
	   //     $data = array('status' => $db->escapeString(json_encode($status)));
	        

	    
	   // }
	    if (!in_array('cancelled', $temp)) {
	        $status[] = array('cancelled',date("d-m-Y h:i:sa") );
	            $data = array(
	            'status' => $db->escapeString(json_encode($status)),
	        );
	    }
	    $db->update('orders',$data,'id='.$id);

	    foreach($result as $item){
	        if($item['active_status'] != 'cancelled'){
	            $item_data = array(
	            'status' => $db->escapeString(json_encode($status)),
    	        'active_status' => 'cancelled'
	            );
	        $db->update('order_items',$item_data,'id='.$item['id']);
	        }
	    }


	}
	
	if($postStatus=='processed'){
	    if (!in_array('processed', $temp)) {
	        $status[] = array('processed',date("d-m-Y h:i:sa") );
	        $data = array(
	            'status' => $db->escapeString(json_encode($status))
	       );
	    
	    }
	    $db->update('orders',$data,'id='.$id);
	    foreach($result as $item){
	        $item_data = array(
	            'status' => $db->escapeString(json_encode($status)),
    	        'active_status' => 'processed'
	            );
	        if($item['active_status'] != 'cancelled'){
	             $db->update('order_items',$item_data,'id='.$item['id']);
	        }
	    }

	}
	if($postStatus=='shipped'){
	    if (!in_array('processed', $temp)) {
	        $status[] = array('processed',date("d-m-Y h:i:sa") );
	        $data = array('status' => $db->escapeString(json_encode($status)));
	    
	    }
	    if (!in_array('shipped', $temp)) {
	        $status[] = array('shipped',date("d-m-Y h:i:sa") );
	        $data = array('status' => $db->escapeString(json_encode($status)));
	    
	    }
	    $db->update('orders',$data,'id='.$id);
	    foreach($result as $item){
	        $item_data = array(
            'status' => $db->escapeString(json_encode($status)),
	        'active_status' => 'shipped'
	            );
	        if($item['active_status'] != 'cancelled'){
	             $db->update('order_items',$item_data,'id='.$item['id']);
	        }
	    }

	}
	if($postStatus=='delivered'){
	    if (!in_array('processed', $temp)) {
	        $status[] = array('processed',date("d-m-Y h:i:sa") );
	        $data = array('status' => $db->escapeString(json_encode($status)));
	    
	    }
	    if (!in_array('shipped', $temp)) {
	        $status[] = array('shipped',date("d-m-Y h:i:sa") );
	        $data = array('status' => $db->escapeString(json_encode($status)));
	    
	    }
	    if (!in_array('delivered', $temp)) {
	        $status[] = array('delivered',date("d-m-Y h:i:sa") );
	        $data = array('status' => $db->escapeString(json_encode($status)));
	    
	    }
	    $db->update('orders',$data,'id='.$id);
    	 $item_data = array(
            'status' => $db->escapeString(json_encode($status)),
            'active_status' => 'delivered'
         );
	    foreach($result as $item){
	        
	        if($item['active_status'] != 'cancelled'){
	             $db->update('order_items',$item_data,'id='.$item['id']);
	        }
	    }
	   
	    
	}
	$i = sizeof($status);
    $currentStatus = $status[$i-1][0];
    $final_status = array(
    	'active_status' => $currentStatus
	);
	//$db->update('order_items',$final_status,'order_id='.$id);
 	if($db->update('orders',$final_status,'id='.$id)){// Table name, column names and respective values
		$response['error'] = false;
		$response['message'] = "Order updated successfully.";
		$res = $db->getResult();
		/* send email notification for the order received */
		$sql = "select name,email,mobile from `users` where id=".$user_id;
		$db->sql($sql);
		$res = $db->getResult();
		
		$to = $res[0]['email'];
		$mobile = $res[0]['mobile'];
		$subject = "Your order has been ".ucwords($postStatus);
		$message = "Hello, Dear ".ucwords($res[0]['name']).", Here is the new update on your order for the order ID : #".$id.". Your order has been ".ucwords($postStatus).". Please take a note of it.";
		$message .= "<br><br>Thank you for using our services!<br><br>You will receive future updates on your order via Email!";
		$function->send_order_update_notification($user_id,"Your order has been ".ucwords($postStatus),$message,'order');
		
		send_email($to,$subject,$message);
		$message = "Hello, Dear ".ucwords($res[0]['name']).", Here is the new update on your order for the order ID : #".$id.". Your order has been ".ucwords($postStatus).". Please take a note of it.";
		$message .= "Thank you for using our services! Contact us for more information";
		//sendSms($mobile, $message);
		
		print_r(json_encode($response));
	}
	else{
		$response['error'] = true;
		$response['message'] = "Could not update order. Try again!";
		print_r(json_encode($response));
	}
	}else{
		$response['error'] = true;
		$response['message'] = "Sorry Invalid order ID";
		print_r(json_encode($response));
	}
}


if(isset($_POST['get_settings'])) {
	$sql = "select value from `settings` where variable='system_timezone'";
	$db->sql($sql);
	$res = $db->getResult();
	$sql = "select value from `settings` where variable='currency'";
	$db->sql($sql);
	$res_currency = $db->getResult();
	   if(!empty($res)){
            $response['error'] = false;
            $response['settings'] = json_decode($res[0]['value'],1);
            $response['settings']['currency'] = $res_currency[0]['value'];
            $response['settings']['delivery_charge'] = empty($response['settings']['delivery_charge'])?"0":$response['settings']['delivery_charge'];
            $response['settings']['min-refer-earn-order-amount'] = empty($response['settings']['min-refer-earn-order-amount'])?"0":$response['settings']['min-refer-earn-order-amount'];
            $response['settings']['min_amount'] = empty($response['settings']['min_amount'])?"0":$response['settings']['min_amount'];
            $response['settings']['max-refer-earn-amount'] = empty($response['settings']['max-refer-earn-amount'])?"0":$response['settings']['max-refer-earn-amount'];
            $response['settings']['minimum-withdrawal-amount'] = empty($response['settings']['minimum-withdrawal-amount'])?"0":$response['settings']['minimum-withdrawal-amount'];
            $response['settings']['refer-earn-bonus'] = empty($response['settings']['refer-earn-bonus'])?"0":$response['settings']['refer-earn-bonus'];
            print_r(json_encode($response));
            
        }else{
            $response['error'] = true;
            $response['settings'] = "No settings found!";
            $response['message'] = "Something went wrong!";
            print_r(json_encode($response));
            
        }
}

if(isset($_POST['update_order_total_payable']) && isset($_POST['id'])){
	
	$id = $db->escapeString($_POST['id']);
	$discount = $db->escapeString($_POST['discount']);
	$deliver_by = $db->escapeString($_POST['deliver_by']);
	$ceil = $db->escapeString($_POST['total_payble']);
	$total_payble = ceil($ceil);
	// echo $total_payble;
	$data = array(
		'discount' =>$discount,
		'deliver_by' => $deliver_by,
	);
	$data1 = array(
		 'discount' =>$discount,
		 'final_total' => $total_payble,
// 		 'total' => $total_payble,
	);

	
	if($discount >= 0){
	    $db->update('order_items',$data,'order_id='.$id);
	    $db->update('orders',$data1,'id='.$id);  // Table name, column names and respective values
    	$res = $db->getResult();
    	if(!empty($res)){
    		// print_r($res);
    	    
        	$response['error'] = false;
            $response['message'] = "Order updated successfully.";
            print_r(json_encode($response));
    	}else{
            $response['error'] = true;
            $response['message'] = "Could not update order. Try again!";
            print_r(json_encode($response));
    	}
	}
}


if(isset($_POST['add_transaction']) && $_POST['add_transaction'] == true){
	/*add data to transaction table*/
	
	$user_id = $_POST['user_id'];
	$order_id = $_POST['order_id'];
	$type = $_POST['type'];
	$txn_id = $_POST['txn_id'];
	$amount = $_POST['amount'];
	$status = $db->escapeString($_POST['status']);
	$message = $db->escapeString($_POST['message']);
	$transaction_date = (isset($_POST['addedon']) && !empty($_POST['addedon']))?$db->escapeString($_POST['addedon']):date('Y-m-d H:i:s');
	$data = array(
		'user_id' =>$user_id,
		'order_id' =>$order_id,
		'type' => $type,
		'txn_id' => $txn_id,
		'amount' => $amount,
		'status' => $status,
		'message' => $message,
		'transaction_date' => $transaction_date
	);	
	$db->insert('transactions',$data);  // Table name, column names and respective values
	$res = $db->getResult();
	$response['error'] = false;
	$response['transaction_id'] = $res[0];
	$response['message'] = "Transaction added successfully!";
	echo json_encode($response);
}
?>