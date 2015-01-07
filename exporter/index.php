<!DOCTYPE html>
<html>
<head>
  <title>PCRT Data Exporter Installation Script</title>
  <script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.8.3/jquery.min.js"></script>
  <script type="text/javascript">
    function ShowProgress() {
      setTimeout(function () {
          var modal = $('<div />');
          modal.addClass("modal");
          $('body').append(modal);
          var loading = $(".loading");
          loading.show();
          var top = Math.max($(window).height() / 2 - loading[0].offsetHeight / 2, 0);
          var left = Math.max($(window).width() / 2 - loading[0].offsetWidth / 2, 0);
          loading.css({ top: top, left: left });
      }, 200);
    }
    $('a').live("click", function () {
        ShowProgress();
    });
    $('form').live("submit", function () {
        ShowProgress();
    });
  </script>
  <style type="text/css">
    .modal
    {
      position: fixed;
      top: 0;
      left: 0;
      background-color: black;
      z-index: 99;
      opacity: 0.8;
      filter: alpha(opacity=80);
      -moz-opacity: 0.8;
      min-height: 100%;
      width: 100%;
    }
    .loading
    {
      font-family: Arial;
      font-size: 10pt;
      border: 5px solid #67CFF5;
      width: 200px;
      height: 100px;
      display: none;
      position: fixed;
      background-color: White;
      z-index: 999;
    }
  </style>

</head>
<?php
  error_reporting(0);
  $api_version = "/api/v1";
  $base_url = "";
  if (file_exists('config.php')) {
    require "config.php";
    // $base_url = "http://".RS_SUBDOMAIN.".lvh.me:3000";
    $base_url = "https://".RS_SUBDOMAIN.".repairshopr.com";
  }

  $step = (isset($_GET['step']) && $_GET['step'] != '') ? $_GET['step'] : '';
  switch($step){
    case '1':
      step_1();
      break;
    case '2':
      $type = (isset($_GET['type']) && $_GET['type'] != '') ? $_GET['type'] : '';
      step_2($type);
      break;
    default:
      step_1();
  }
?>
<body>
  <div class="loading" align="center">
    Please wait...<br />
    <br />
    <img src="loader.gif" alt="" />
  </div>
<?php
  function step_1(){
    if (file_exists('../repair/deps.php')) {
      require "../repair/deps.php";
      $config_exists = 1;
    }else{
      $config_exists = 0;
    }
    if (isset($_POST['submit']) && $_POST['submit']=="Submit") {
      if(isset($config_exists) && $config_exists == 0) {
        $dbhost = isset($_POST['db_host']) ? $_POST['db_host'] : "";
        $dbname = isset($_POST['db_name']) ? $_POST['db_name'] : "";
        $dbuname = isset($_POST['db_username']) ? $_POST['db_username'] : "";
        $dbpass = isset($_POST['db_password']) ? $_POST['db_password'] : "";
      }
      $rs_api_key = isset($_POST['rs_api_key']) ? $_POST['rs_api_key'] : "";
      $rs_subdomain = isset($_POST['rs_subdomain']) ? $_POST['rs_subdomain'] : "";

      if (($config_exists == 0 && (empty($rs_api_key)
            || empty($rs_subdomain) || empty($dbhost)
            || empty($dbuname) || empty($dbname)))
          || ($config_exists == 1 && (empty($rs_api_key)
            || empty($rs_subdomain)))) {
        echo "All fields are required! Please re-enter.<br />";
      } else {
        $f = fopen("config.php","w");
        $db_info = "<?php
          define('DATABASE_HOST', '".$dbhost."');
          define('DATABASE_NAME', '".$dbname."');
          define('DATABASE_USERNAME', '".$dbuname."');
          define('DATABASE_PASSWORD', '".$dbpass."');
          define('RS_API_KEY', '".$rs_api_key."');
          define('RS_SUBDOMAIN', '".$rs_subdomain."');
        ?>";
        if (fwrite($f,$db_info) > 0){
          fclose($f);
        }
        echo "<script type='text/javascript'>";
        echo "window.location='index.php?step=2';";
        echo "</script>";
      }
    }
    if (file_exists('config.php')) {
      $db_host = DATABASE_HOST;
      $db_name = DATABASE_NAME;
      $db_username = DATABASE_USERNAME;
      $db_password = DATABASE_PASSWORD;
      $rs_api_key = RS_API_KEY;
      $rs_subdomain = RS_SUBDOMAIN;
    }
?>
<center>
  <form method="post" action="index.php?step=1">
    <table>
      <?php if(isset($config_exists) && $config_exists == 0) {?>
      <tr>
        <td><label for="db_host">Database Host</label></td>
        <td><input type="text" name="db_host" value='localhost' size="30"></td>
      </tr>
      <tr>
        <td><label for="db_name">Database Name</label></td>
        <td><input type="text" name="db_name" size="30" value="<?php echo isset($db_name) ? $db_name : ''; ?>"></td>
      </tr>
      <tr>
        <td><label for="db_username">Database Username</label></td>
        <td><input type="text" name="db_username" size="30" value="<?php echo isset($db_username) ? $db_username:''; ?>"></td>
      </tr>
      <tr>
        <td><label for="database_password">Database Password</label></td>
        <td><input type="text" name="db_password" size="30" value="<?php echo isset($db_password) ? $db_password : ''; ?>"></td>
      </tr>
      <?php } ?>
      <tr>
        <td><label for="rs_api_key">Your RepairShopr API Key</label></td>
        <td><input type="text" name="rs_api_key" size="30" value="<?php echo isset($rs_api_key) ? $rs_api_key : ''; ?>" placeholder="Your RepairShopr API Key"></td>
      </tr>
      <tr>
        <td><label for="rs_subdomain">Your RepairShopr Subdomain</label></td>
        <td><input name="rs_subdomain" type="text" size="30" maxlength="15" value="<?php echo isset($rs_subdomain) ? $rs_subdomain : ''; ?>" placeholder="Your RepairShopr Subdomain"></td>
      </tr>
      <tr>
        <td>&nbsp;</td>
        <td><input type="submit" name="submit" value="Submit"></td>
      </tr>
    </table>
  </form>
</center>
<?php
  }

  function step_2( $type = "") {
    $message = "";
    if (!file_exists('config.php')) {
      echo "<script type='text/javascript'>";
      echo "window.location='index.php';";
      echo "</script>";
    }else{
      $connection = mysql_connect(DATABASE_HOST, DATABASE_USERNAME, DATABASE_PASSWORD);
      mysql_select_db(DATABASE_NAME, $connection);

      if($type == 1) {
        $result = mysql_query("SELECT * FROM pc_owner", $connection);
        $customers = [];
        while($row = mysql_fetch_assoc($result)) {
          $customers[] = $row;
        }
        $success_count = 0;
        $failure_count = 0;
        $failure_message = "";
        $failed_records = array();
        $total_customers = count($customers);
        foreach ($customers as $key => $value) {
          $postdata = http_build_query(
                        array(
                          'business_name' => $value['pccompany'],
                          'firstname' => $value['pcname'],
                          'lastname' =>'',
                          'email' => $value['pcemail'],
                          'phone' => $value['pcphone'],
                          'mobile' => $value['pccellphone'],
                          'address' => $value['pcaddress'],
                          'address_2' => $value['pcaddress2'],
                          'city' => $value['pccity'],
                          'state' => $value['pcstate'],
                          'zip' => $value['pczip'],
                          'notes' => $value['pcnotes']
                        )
                      );
          $opts = array('http' =>
                    array(
                      'method'  => 'POST',
                      'header'  => 'Content-type: application/x-www-form-urlencoded',
                      'content' => $postdata
                    )
          );

          $context  = stream_context_create($opts);
          
          $result = file_get_contents($GLOBALS['base_url'].$GLOBALS['api_version']."/customers.json?api_key=".RS_API_KEY, false, $context);
          $json_result = json_decode($result, true);

          if($result === false || $json_result === NULL) {
            $failure_message = "Not able to connect to RepairShopr at the moment. Please <a href='index.php'>check</a> if you entered API key and subdomain correctly";
          } elseif(isset($json_result['customer'])) {
            $success_count++;
          } else {
            if(isset($json_result['success']) && $json_result['success'] === false ){
              $failed_records[$key]['firstname'] = $json_result['params']['firstname'];
              $failed_records[$key]['message'] = $json_result['message'][0];
            }
            $failure_count++;
          }
        }
        mysql_close($connection);
      } elseif($type == 2) {
        $nice_names = array(
                        'called' => "Called" ,'pcstatus' => "PC Status",
                        'probdesc' => "Problem Description", 'pcpriority' => "PC Priority", 
                        'assigneduser' => "Assigned User", 'custassets' => "Customer Assets", 
                        'sked' => "Scheduled", 'virusfound' => "Virus Found", 'custnotes' => "Customer Notes",
                        'technotes' => "Technician Notes", 'pickupdate' => "Pickup Date", 'readydate' => "Ready Date",
                        'thepass' => "Password", 'workarea' => "Work Area", 'cibyuser' => "ci by user",
                        'notesbyuser' => "Notes By User", 'cobyuser' => "co by user",
                        'commonproblems' => "Common Problems", 'thesig' => "thesig", 'thesigwo' => "thesigwo",
                        'showsigct' => "Show Signature"
                      );
        $called_values = array(1=>"Not Called", 2=>"Called", 3=>"Called - No Answer",4=>"Called - Waiting for Call Back",5=>"Sent SMS",6=>"Sent Email");

        $result = mysql_query("SELECT * FROM pc_wo", $connection);
        $tickets = [];
        while($row = mysql_fetch_assoc($result)) {
          $tickets[] = $row;
        }
        $success_count = 0;
        $failure_count = 0;
        $failure_message = "";
        $failed_records = array();
        $total_tickets = count($tickets);
        foreach ($tickets as $key => $value) {
          $customer_sql = mysql_query("SELECT * FROM pc_owner WHERE pcid = ".$value['pcid'], $connection);
          $customer = [];
          while($row = mysql_fetch_assoc($customer_sql)) {
            $customer = $row;
          }

          $postdata = http_build_query(
                        array(
                          'email' => $customer['pcemail'],
                          'phone' => $customer['pcphone'],
                          'mobile' => $customer['pccellphone'],
                          'due_date' => (isset($value['skeddate']) && $value['skeddate'] != "0000-00-00 00:00:00") ? $value['skeddate'] : date('Y-m-d H:i:s'),
                          'created_at' => $value['dropdate'],
                          'subject' => isset($value['probdesc']) ? $value['probdesc'] : "(empty)",
                          'problem_type' => "(empty)",
                          'status' => "RESOLVED"
                        )
                      );
          $opts = array('http' =>
                    array(
                      'method'  => 'POST',
                      'header'  => 'Content-type: application/x-www-form-urlencoded',
                      'content' => $postdata
                    )
                  );

          $context  = stream_context_create($opts);

          $result = file_get_contents($GLOBALS['base_url'].$GLOBALS['api_version']."/tickets.json?api_key=".RS_API_KEY, false, $context);
          $json_result = json_decode($result, true);

          if($result === false || $json_result === NULL) {
            $failure_count++;
          } elseif(isset($json_result['ticket'])) {
            $cmt = "";
            foreach($value as $k => $v) {
              if($k == 'sked') {
                $v = ($v == 1) ? "Yes" : "No";
              } else if($k == 'pcstatus') {
                $res_b = mysql_query("SELECT * FROM boxstyles WHERE `statusid` = $v", $connection);
                while($row = mysql_fetch_assoc($res_b)) {
                  $v = $row['boxtitle'];
                }
              } else if($k == 'called') {
                if(array_key_exists($v, $called_values)) {
                  $v =  $called_values[$v];
                }
              }
              if(array_key_exists($k, $nice_names)) {
                $cmt .=  $nice_names[$k].': '. $v ."\n";
              }
            }
            $comment_post = http_build_query(
                              array(
                                'subject' => "PCRT Internal Data",
                                'body' => $cmt,
                                'hidden' => true
                              )
                            );
            $comment_opts = array('http' =>
                      array(
                        'method'  => 'POST',
                        'header'  => 'Content-type: application/x-www-form-urlencoded',
                        'content' => $comment_post
                      )
            );

            $comment_context  = stream_context_create($comment_opts);
            $result = file_get_contents($GLOBALS['base_url'].$GLOBALS['api_version']."/tickets/".$json_result['ticket']['id']."/comment.json?api_key=".RS_API_KEY, false, $comment_context);
            $success_count++;
          } else {
            if(isset($json_result['success']) && $json_result['success'] === false ){
              $failed_records[$key]['firstname'] = $json_result['params']['firstname'];
              $failed_records[$key]['message'] = $json_result['message'][0];
            }
            $failure_count++;
          }
        }
        mysql_close($connection);
      }
    }
?>
<center>
  <ul style="list-style: none;">
    <?php if($failure_message != "") {?>
    <li><?php echo $failure_message; ?></li>
    <?php } ?>
    <li>
      <a href="index.php?step=2&type=1"> Export Customers </a>
      <?php if($failure_message == "" && isset($total_customers) && isset($success_count)) {?>
      <p>
        <table>
          <tr>
            <td><b>Total Customers:</b></td>
            <td><?php echo $total_customers;?></td>
          </tr>
          <tr>
            <td><b>Success Count:</b></td>
            <td><?php echo $success_count;?></td>
          </tr>
          <tr>
            <td><b>Failure Count:</b></td>
            <td><?php echo $failure_count;?></td>
          </tr>
        </table>
      </p>
      <?php }?>
      <?php if(count($failed_records) > 0) { ?>
        <table>
          <tr>
            <th>Customer</th>
            <th>Failure Reason</th>
          </tr>
      <?php foreach ($failed_records as $key => $value) { ?>
          <tr>
            <td><b><?php echo $value['firstname']; ?></b></td>
            <td><?php echo $value['message']; ?></td>
          </tr>
      <?php } ?>
        </table>
      <?php } ?>
    </li>
    <li>
      <a href="index.php?step=2&type=2"> Export Tickets </a>
      <?php if($failure_message == "" && isset($total_tickets) && isset($success_count)) {?>
      <p>
        <table>
          <tr>
            <td><b>Total Tickets:</b></td>
            <td><?php echo $total_tickets;?></td>
          </tr>
          <tr>
            <td><b>Success Count:</b></td>
            <td><?php echo $success_count;?></td>
          </tr>
          <tr>
            <td><b>Failure Count:</b></td>
            <td><?php echo $failure_count;?></td>
          </tr>
        </table>
      </p>
      <?php }?>
    </li>
  </ul>
</center>
<?php
  }
