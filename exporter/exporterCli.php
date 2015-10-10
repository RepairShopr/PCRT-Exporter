<?php
ini_set('memory_limit','-1');
ini_set('max_execution_time',0);

/*
 * Configuration section ==============================>
 */

if(!file_exists(dirname(__FILE__)."/configCli.php")){
    //Ask user to fill necessary data

    if (file_exists('../repair/deps.php')) {
        //get dbhost, dbname,dbuname,dbpass from here
        require dirname(__FILE__)."/../repair/deps.php";
    }else{
        fwrite(STDOUT, "\nExporter couldn't find a configuration file. Please fill the following data:");
        //Get host
        fwrite(STDOUT, "\nDatabase host: ");
        $dbhost = trim(fgets(STDIN));

        //Get dbname
        fwrite(STDOUT, "\nDatabase name: ");
        $dbname = trim(fgets(STDIN));

        //Get dbuname
        fwrite(STDOUT,"\nDatabase user: ");
        $dbuname = trim(fgets(STDIN));

        //Get dbpass
        fwrite(STDOUT,"\nDatabase password: ");
        $dbpass = trim(fgets(STDIN));
    }

    //Get rs_api_key
    fwrite(STDOUT,"\nRepairShopr API Key: ");
    $rs_api_key = trim(fgets(STDIN));

    //Get rs_subdomain
    fwrite(STDOUT,"\nRepairShopr Subdomain: ");
    $rs_subdomain = trim(fgets(STDIN));


    $db_info = "<?php
                  define('DATABASE_HOST', '".$dbhost."');
                  define('DATABASE_NAME', '".$dbname."');
                  define('DATABASE_USERNAME', '".$dbuname."');
                  define('DATABASE_PASSWORD', '".$dbpass."');
                  define('RS_API_KEY', '".$rs_api_key."');
                  define('RS_SUBDOMAIN', '".$rs_subdomain."');
                  define('BASE_URL','https://".$rs_subdomain.".repairshopr.com');                 
                  define('API_VERSION','/api/v1');
                ?>";


    file_put_contents(dirname(__FILE__)."/configCli.php",$db_info);
    require_once(dirname(__FILE__)."/configCli.php");
}else require dirname(__FILE__)."/configCli.php";


//Configuration section end ===========================>
//include config if exists
date_default_timezone_set('America/New_York');

/**
 * Set connection to a database using PDO
 */

$pdo = new PDO("mysql:host=".DATABASE_HOST.";dbname=".DATABASE_NAME,DATABASE_USERNAME,DATABASE_PASSWORD);
if(!$pdo) die("\rCould not establish connection to a database!");
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);


/**
 *  Export customers ============================================================>
 */

function exportCustomers()
{

//Get customers
    global $pdo;

    //Check for rs_cid columns
    $pdoStatement = $pdo->query("DESCRIBE `pc_owner`");
    $columns = $pdoStatement->fetchAll(PDO::FETCH_COLUMN);   
    $columns = array_flip($columns);  
    if(!isset($columns['rs_cid'])){
        $pdo->query("ALTER TABLE pc_owner ADD rs_cid INTEGER");
    }

    $pdoStatement = $pdo->prepare("SELECT
                                    pcid
                                   ,pccompany as business_name
                                   ,pcname as firstname
                                   ,'' as lastname
                                   ,pcemail as email
                                   ,pcphone as phone
                                   ,pccellphone as mobile
                                   ,pcaddress as address
                                   ,pcaddress2 as address_2
                                   ,pccity as city
                                   ,pcstate as state
                                   ,pczip as zip
                                   ,pcnotes as notes
                                FROM `pc_owner`
                                WHERE rs_cid is null and LENGTH(pcemail)>0");
    if(!$pdoStatement->execute()) die("\rCould not get the list of customers!");

    $customers = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
    echo "\nCustomer count is :"; echo count($customers)."\n";

//Send clients via API
    $curl = curl_init();
    $cnt = 0;
    $errors = 0;
    $totalCustomers = count($customers);
    foreach($customers as $customer){
        //if the process was interrupted - next time begin with "not yet imported customer"// todo:
        curl_setopt($curl, CURLOPT_URL, BASE_URL.API_VERSION."/customers.json?api_key=".RS_API_KEY);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($customer));
        //curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        $out = curl_exec($curl);
        $out = json_decode($out,true);
        $cnt++;
      //  print_r($out);
        if($out === false || $out === NULL) {
            $failure_message = "\n\rNot able to connect to RepairShopr at the moment. Please check if you entered API key and subdomain correctly";
            echo $failure_message;
            $errors++;
          
        }elseif(isset($out['message'])){
           //print_r($customer);
            echo "\n\rMessage from server while exporting customer (".$customer['email']."): ".implode("\n",$out['message'])."\n";
            $errors++;
            
        }
        else if(isset($out['customer'])) {
            $sql = "UPDATE pc_owner SET rs_cid=".$out['customer']['id']." WHERE pcid=".$customer['pcid'];
            $pdo->query($sql);
              echo "\nCustomer $cnt / $totalCustomers, Finished: ".($cnt/$totalCustomers*100)."%";
        }
       
     

    }

    curl_close($curl);
    echo "\nError count: $errors/$totalCustomers";

}


/**
 *  Export customers End =======================================================>
 */


/**
 * Export tickets ==============================================================>
 */

function exportTickets()
{
    global $pdo;
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
    $pdoStatement = $pdo->prepare("SELECT *
                               FROM `pc_owner` pco
                               JOIN `pc_wo` pcw ON pco.pcid = pcw.pcid
                               ");
    if(!$pdoStatement->execute()) die("\rCould not get the list of tickets!");

    $list = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);

    $curl = curl_init();
    $cnt = 0;
    $totalTickets = count($list);
    foreach($list as $item){

        $cmt='';
        foreach($item as $k => $v) {
            if($k == 'sked') {
                $v = ($v == 1) ? "Yes" : "No";
            } else if($k == 'pcstatus') {
                $result = $pdo->query("SELECT * FROM boxstyles WHERE `statusid` = $v");
                $v = $result->fetch(PDO::FETCH_OBJ)->boxtitle;
            } else if($k == 'called') {
                if(array_key_exists($v, $called_values)) {
                    $v =  $called_values[$v];
                }
            }
            if(array_key_exists($k, $nice_names)) {
                $cmt .=  $nice_names[$k].': '. $v ."\n";
            }
        }

        $data = array(
            'email' => $item['pcemail'],
            'phone' => $item['pcphone'],
            'mobile' => $item['pccellphone'],
            'due_date' => (isset($item['skeddate']) && $item['skeddate'] != "0000-00-00 00:00:00") ? $item['skeddate'] : date('Y-m-d H:i:s'),
            'created_at' => $item['dropdate'],
            'subject' => isset($item['probdesc']) ? $item['probdesc'] : "(empty)",
            'problem_type' => "(empty)",
            'status' => "Resolved",
            'comment_subject'=>'PCRT Internal Data',
            'comment_body'=>$cmt,
            'comment_hidden'=>true
        );


        //if the process was interrupted - next time begin with "not yet imported customer"// todo:
        curl_setopt($curl, CURLOPT_URL, BASE_URL.API_VERSION."/tickets.json?api_key=".RS_API_KEY);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
        //  curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        $out = curl_exec($curl);
        $cnt++;
       // echo "\n$out\n";
        echo "\rTicket $cnt / $totalTickets, Completed: ".($cnt/$totalTickets*100)."%";



    }

    curl_close($curl);
}
/**
 * Export tickets End ==========================================================>
 */


/**
 * Export invoices =============================================================>
 */
function exportInvoices()
{
    global $pdo;
    $pdoStatement = $pdo->prepare("SELECT
                                        inv.*, pco.rs_cid
                                   FROM invoices inv
                                   JOIN pc_wo pcw ON inv.woid = pcw.woid
                                   JOIN pc_owner pco ON pco.pcid = pcw.pcid
                                   ");

    if(!$pdoStatement->execute()) die("\rCould not get the list of tickets!");

    $invoices = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);
    $success_count = 0;
    $failure_count = 0;
    $failure_message = "";
    $failed_records = array();
    $total_invoices = count($invoices);
    $inv_customers = array();
    $line_items = array();

    foreach($invoices as $invoice){
        $pdoStatement = $pdo->prepare("SELECT cart_price as price, cart_type as item, labor_desc as name, taxex as taxable FROM invoice_items WHERE invoice_id = :invoice_id");
        if(!$pdoStatement->execute(array(':invoice_id'=>$invoice['invoice_id'])))
            die("\n\rCould not get invoice items from the database!");

        $line_items[$invoice['invoice_id']] = $pdoStatement->fetchAll(PDO::FETCH_ASSOC);

    }

    foreach ($line_items as $key => $value) {
        foreach ($value as $k => $val) {
            $line_items[$key][$k]['quantity'] = 1;
            $line_items[$key][$k]['cost'] = 0.0;
            if($line_items[$key][$k]['taxable'] == 5){
                $line_items[$key][$k]['taxable'] = 0;
            }else{
                $line_items[$key][$k]['taxable'] = 1;
            }
        }
    }

    $curl = curl_init();
    $cnt = 0;
    foreach ($invoices as $key => $value) {
        $paid = false;
        if($value['receipt_id'] > 0){
            $paid = true;
        }

        $postdata = json_encode(
            array(
                'number' => $value['invoice_id'],
                'customer_id' => $value['rs_cid'],
                'date' => $value['invdate'],
                'date_received' => $value['invdate'],
                'paid' => $paid,
                'line_items' => $line_items[$value['invoice_id']]
            )
        );
    
        
        curl_setopt($curl, CURLOPT_URL, BASE_URL.API_VERSION."/invoices.json?api_key=".RS_API_KEY);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        //curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postdata))
        );
        $out = curl_exec($curl);

        $json_result = json_decode($out, true);
        if($out === false || $json_result === NULL) {
            echo "Not able to connect to RepairShopr at the moment. Please check if you entered API key and subdomain correctly";
        } elseif(isset($json_result['invoice'])) {
            $success_count++;
        } else {
            $failure_count++;
        }
        $cnt++;
        echo "\rInvoice $cnt / $total_invoices, Completed: ".($cnt/$total_invoices*100)."%";
    }

    curl_close($curl);



}
/**
 * Export invoices End =========================================================>
 */

/**
 * Export assets ===============================================================>
 */
function exportAssets()
{
    $connection = mysql_connect(DATABASE_HOST, DATABASE_USERNAME, DATABASE_PASSWORD);
    mysql_select_db(DATABASE_NAME, $connection);
    $result = mysql_query("SELECT * FROM pc_owner where rs_cid is not null", $connection) or die(mysql_error());
    $customers = [];
    while($row = mysql_fetch_assoc($result)) {
        $customers[] = $row;
    }


    $success_count = 0;
    $failure_count = 0;
    $failure_message = "";
    $failed_records = array();
    $total_customers = count($customers);
   // echo "total customers = ".$total_customers;

    $curl = curl_init();
    $cnt = 0;
    foreach ($customers as $key => $value) {
        $result = mysql_query("SELECT * FROM mainassettypes WHERE mainassettypeid = ". $value['mainassettypeid'], $connection);
        // $asset = [];
        $main_asset = [];
        $properties = [];
        while($row = mysql_fetch_assoc($result)) {
            $main_asset = $row;
        }
        $asset_info_fields = unserialize($value['pcextra']);

        foreach ($asset_info_fields as $k => $val) {
            if($val != "") {
                $result = mysql_query("SELECT * FROM mainassetinfofields WHERE mainassetfieldid = ". $k, $connection);

                while($row = mysql_fetch_assoc($result)) {
                    $properties[][$row['mainassetfieldname']] = $val;
                }
            }
        }
        $output = array();
        foreach($properties as $v) {
            $output[key($v)] = current($v);
        }
        $postdata = json_encode(
            array(
                'name' => $value['pcmake'],
                'asset_type_name' => $main_asset['mainassetname'],
                'customer_id' => $value['rs_cid'],
                'properties' => $output
            )
        );



        curl_setopt($curl, CURLOPT_URL,BASE_URL.API_VERSION."/customer_assets.json?api_key=".RS_API_KEY);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postdata);
        //curl_setopt($curl, CURLOPT_VERBOSE, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'Content-Length: ' . strlen($postdata))
        );
        $out = curl_exec($curl);
       // echo "\n";
        $json_result = json_decode($out, true);
       // print_r($json_result);
        if(isset($json_result['asset'])) {
            $success_count++;
        } elseif($out === false || $json_result === NULL) {
            $failure_count++;
        }
        $cnt++;
        echo "\rAsset $cnt / $total_customers, Completed: ".($cnt/$total_customers*100)."%";

    }

    mysql_close($connection);
}
/**
 * Export assets end ===============================================================>
 */

/**
 *  Command line interface starts here
 */
while(true){

   // ask for input
    fwrite(STDOUT, "\nAction list:
\n 1) Export customers
\n 2) Export tickets
\n 3) Export invoices
\n 4) Export customer assets
");

// get input
    $action = trim(fgets(STDIN));
    if(!is_numeric($action) || ($action<1 || $action>4)){
        fwrite(STDOUT, "It is allowed to choose only action between '1' and '4'.");
        fgets(STDIN);
        continue;

    }
    switch($action){
        case 1:exportCustomers(); break;
        case 2:exportTickets(); break;
        case 3:exportInvoices(); break;
        case 4:exportAssets(); break;
    }
}




?>
