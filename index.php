<?php
/**
 * @version: 0.0.1
 */
$tplAreas = array(
    'ui' => array(),
    'customerDetails' => array(),
    'iframe' => array(),
    'content' => array(),
    'debug' => array()
);

$cache = array();
$cache['articles'] = array();



/**
 * config & includes
 */
include( "config.php");
include( "functions.php");
include( "ApiClient.php");

/**
 * Validate Input
 */
$action = '';
if( array_key_exists( 'action', $_REQUEST ) ) {
    $action = $_REQUEST['action'];
}

if( array_key_exists( 'resturl', $_REQUEST ) ) {
    if ( strlen($_REQUEST['resturl']) > 1024 ) {
        die( 'resturl to long (max. 1024)' );
    }
    $restUrl = $_REQUEST['resturl'];
}
if( array_key_exists( 'restuser', $_REQUEST ) ) {
    $restUser = $_REQUEST['restuser'];
}
if( array_key_exists( 'restkey', $_REQUEST ) ) {
    if ( strlen($_REQUEST['restkey']) != 40 ) {
        die( 'restkey to long (=40)' );
    }
    $restKey = $_REQUEST['restkey'];
}



/**
 * Convert settings
 */
$debug = true;
$redirect = '';


/**
 * Check API-Access
 */
if( $restUrl != '' && $restUser != '' && $restKey != '' )  {
    // create Client
    $client = new ApiClient( $restUrl, $restUser, $restKey );
    

    $oUser = $client->getUserbyUserName( $restUser );

    if( is_array( $oUser ) && array_key_exists( 'id', $oUser ) && $oUser['id'] > 0 ) {
        $userdetails = '<div class="alert alert-success w-100 pt-2 pb-2 text-center" role="alert">'
                            . '<b>' . $oUser['name'] . '</b> (' . $oUser['id'] . ') <a href="mailto:' . $oUser['email'] . '">Mail</a>'
                        . '</div>';
        $tplAreas['usersidebar'][] = $userdetails;
    }
    else {
        $client = false;
    }
}
else {
    $client = false;
}



if( $client != false )  {



    $overview = '';
    $ui = '';


    if( $action == 'export' ) {

        /**
         * Are Orders ready for exporting?
         */
        if( ordersReadyForExport( $sqlhandle ) > 0 ) {
            /** 
             * Create Item in DB
             */
            $result = $sqlhandle->query( 'INSERT INTO cp_order_export ( lastOrderId ) VALUES ( 1 )' );
            $resultDBid = $sqlhandle->lastInsertId();
            
            $oOrders = getOrdersByStatusNull( $sqlhandle, $resultDBid );

            if( !$result ) {
                file_put_contents( $logDir . '_error.log', "stmt-Error:\n" . print_r( $result->errorInfo(), true ) . "\n", FILE_APPEND );
                printf("Error: %s.<br>\n", print_r( $sqlhandle->errorInfo(), true ));
                die();
            }

            /**
             * Create seperate Log
             */
            $logfile = $logDir . 'log_' . $resultDBid . '.log';
            if( !file_exists( $logfile ) ) {
                file_put_contents( $logfile, "" );
            }
            
            $log = '_REQUEST:' . "\n";
            foreach( $_REQUEST AS $key => $value ) {
                if( $key != 'restkey' ) {
                    $log.= $key . ': ' . $value . "\n";
                }
            }
            $log.= "Orders Total: " . $oOrders['total'] . "\n";

            file_put_contents( $logfile, $log, FILE_APPEND );

            $ui.= '<div class="col-12">';
                $ui.= '<h1>Export:</h1>';
                $ui.= '<pre>total: ' . print_r( $oOrders['total'] , true ) . '</pre>';
                $ui.= '<pre>success: ' . print_r( $oOrders['success'] , true ) . '</pre>';
            $ui.= '</div>';

            /**
             * create export-file
             */
            $exportFile = $exportDir . 'export_' . $resultDBid . '.csv';
            $fp = fopen( $exportFile, 'a' );
                fputcsv( $fp, array_fill(0, count($csvTitle), ''), $csv_sep );
                fputcsv( $fp, $csvTitle, $csv_sep );
            file_put_contents( $logfile, "Exported File: " . $exportFile . "\n", FILE_APPEND );

            /** 
             * Put TitleLine to CSV-File
             */
            foreach( $oOrders['data'] AS $orderData )
            {
                $row = json_decode( $client->get('orders/' . $orderData['id'] ), true );

                file_put_contents( $logfile, "Start Order: " . $orderData['id']
                                            . "\n----------------------------------------\n", FILE_APPEND );

                if( !$row["success"] ) {
                    file_put_contents( $logfile, $row["message"] . "\n\n", FILE_APPEND );
                    continue;
                }
                else {
                    $statusTime = $orderData['time'];  
                    $orderData = $row["data"];

                    /**
                     * Mapping
                     */
                    $position = array();
                    // prepare Line  
                    foreach( $csvTitle as $t => $n ) {
                        $position[$t] = '';
                    }
                    
                    $position['Bestell-ID'] = $orderData["number"];

                    $position['Rechnungsdatum'] = date( 'Y.m.d H:i', strtotime( $statusTime ) );

                    $payment = $orderData['payment']['name'];
                    if( $payment == 'prepayment' ) {
                        $payment = 'immediately payable without deductions.';
                    }

                    // Billig
                    $position['b_Firma'] = $orderData['billing']['company'] ? $orderData['billing']['company'] . ( $orderData['shipping']['department'] ? ' - '.$orderData['shipping']['department'] : '' )  : trim( $orderData['billing']['firstName'] . ' ' . $orderData['billing']['lastName'] );
                    $position['b_Name'] = $orderData['billing']['lastName'];
                    $position['b_Vorname'] = $orderData['billing']['firstName'];
                    $position['b_Strasse'] = $orderData['billing']['street'];
                    $position['b_PLZ'] = $orderData['billing']['zipCode'];
                    $position['b_Ort'] = $orderData['billing']['city'];
                    $position['b_Land'] = $orderData['billing']['country']['isoName'];
                    $position['b_Telefon'] = $orderData['billing']['phone'];
                    // Billig Extras
                    $position['Mailadresse'] = $orderData['customer']["email"]; // OK? 
                    $position['VAT ID'] = $orderData['billing']['vatId'];

                    $position['Zahlungsart'] = $payment;
                    
                    // Shipping
                    $position['s_Firma'] = $orderData['shipping']['company'] ? $orderData['shipping']['company'] . ( $orderData['shipping']['department'] ? ' - '.$orderData['shipping']['department'] : '' ) : '';

                    $position['b_Firma'] = $orderData['billing']['company'] ? $orderData['billing']['company'] . ( $orderData['shipping']['department'] ? ' - '.$orderData['shipping']['department'] : '' )  : trim( $orderData['billing']['firstName'] . ' ' . $orderData['billing']['lastName'] );



                    $position['s_Name'] = $orderData['shipping']['lastName'];
                    $position['s_Vorname'] = $orderData['shipping']['firstName'];
                    $position['s_Strasse'] = $orderData['shipping']['street'];
                    $position['s_PLZ'] = $orderData['shipping']['zipCode'];
                    $position['s_Ort'] = $orderData['shipping']['city'];
                    $position['s_Land'] = $orderData['shipping']['country']['isoName'];
                    $position['s_Telefon'] = $orderData['shipping']['phone'];

                    foreach( $position AS $key => $value ) {
                        $position[$key] = str_replace( ';',',', $value );
                    }


                    // Basket-Product
                    for( $details_i = 0, $details_len = count( $orderData['details'] ) ; $details_i < $details_len ; $details_i++ ) {
                        $details_row = $orderData['details'][ $details_i ];
                        
                        $purchaseunit = $details_row['attribute']['cpSagePurchaseunit'];
                        if( !$purchaseunit ) $purchaseunit = 1;

                        $position['p_Anzahl'] = $details_row['quantity'] * $purchaseunit; // * Units in Pack
                        $position['p_Produktbezeichnung'] = str_replace( ';',',', $details_row['articleName'] );
                        $position['Projectname'] = str_replace( ';',',', $details_row['articleName'] );
                        $position['p_Material'] = '';
                        $position['p_Einzelpreis'] = round( $details_row['price'], 2);
                        $position['p_Summe'] = round( $details_row['quantity'] * $details_row['price'], 2 );

                        $position['p_Produktbezeichnung'] =  $details_row['attribute']['cpSageStockCode'];
                        
                        /** 
                         * Put Position to CSV-File
                         */
                        // if( $position['p_Produktbezeichnung'] != "" ) {
                            fputcsv( $fp, $position, $csv_sep, ' ' );
                            file_put_contents( $logfile, $position['p_Produktbezeichnung'] . ' - ' . $position['Projectname'] . ' ( ' . $position['p_Anzahl'] . ' )' . "\n", FILE_APPEND );
                        // }
                        // else {
                        //     file_put_contents( $logfile, 'No Sage ID ! - ' . $position['Projectname'] . ' ( ' . $position['p_Anzahl'] . ' )' . "\n", FILE_APPEND );
                        // }
                    }
                }

                if( $orderData  && array_key_exists( "id", $orderData ) ) {
                    // Update DB Item
                    $sqlhandle->query( "UPDATE cp_order_export SET lastOrderId = " . $orderData["id"] . " WHERE id = " . $resultDBid . ";" );
                    file_put_contents( $logfile, "----------------------------------------\n\n", FILE_APPEND );
                }

            }

            $sql = "UPDATE
                        cp_order_status
                    SET 
                        status = 2
                    WHERE
                        status = 1";
            $result = $sqlhandle->query( $sql );

            fclose( $fp );
            
        }
    }
    else if( $action == 'openfile' ) {
        $logfile = $logDir . 'log_' . (int)$_REQUEST['file'] .'.log';
        file_put_contents( $logfile, 'Export over UI at ' . date( 'Y.m.d H:i:s' ) . ' - ' . $_REQUEST['restuser'] . "\n\n", FILE_APPEND );

        $file = $exportDir . 'export_' . (int)$_REQUEST['file'] .'.csv';
        
        // Export File
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="'.('export_' . (int)$_REQUEST['file'] .'.csv').'"');
        echo file_get_contents( $file );
        die();
    }
    else if( $action == 'openlog' ) {
        $logfile = $logDir . 'log_' . (int)$_REQUEST['file'] .'.log';
        file_put_contents( $logfile, 'Show Log at ' . date( 'Y.m.d H:i:s' ) . ' - ' . $_REQUEST['restuser'] . "\n\n", FILE_APPEND );
        
        // Export File
        echo nl2br(file_get_contents( $logfile ));
        die();
    }

    if( !in_array( $action, array( 'openfile' ) ) ) {

        /**
         * Get Data from DB
         */
        $sql = "SELECT
                *
            FROM
                cp_order_export
            ORDER by
                id DESC";

        $result = $sqlhandle->query($sql);
        
        if( $result->rowCount() > 0 ) {
            // output data of each row
            $overview.= '<div class="col-12" style="max-height:240px; overflow:auto;"><table width="100%">'
                    . '<tr>'
                        . '<th>Export</th>'
                        . '<th width="180">timestamp</th>'
                        . '<th width="100">Download</th>'
                        . '<th width="100">Log</th>'
                    . '</tr>';
            while( $row = $result->fetch() ) {

                $link_params = array(
                    'action' => 'openfile' ,
                    'file' => $row['id'] ,
                    'resturl' => $_REQUEST['resturl'] ,
                    'restuser' => $_REQUEST['restuser'] ,
                    'restkey' => $_REQUEST['restkey']
                );

                $log_params = array(
                    'action' => 'openlog' ,
                    'file' => $row['id'] ,
                    'resturl' => $_REQUEST['resturl'] ,
                    'restuser' => $_REQUEST['restuser'] ,
                    'restkey' => $_REQUEST['restkey']
                );

                $overview.= '<tr>'
                        . '<td nowrap>'
                            . '<h4>'
                            
                            
                            . '<a target="_blank" href="'
                            
                            . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?"https":"http")
                            . "://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}".'?'.http_build_query($link_params)
                            . '">'
                            .'Export ' . $row["id"]
                            .'</a>'
                            . '</h4>';

                $sql2 = 'SELECT
                            *
                        FROM
                            cp_order_status
                        LEFT JOIN
                            s_order_billingaddress
                        ON s_order_billingaddress.orderID = cp_order_status.orderid

                        WHERE 
                            exportid = ' .$row['id'] . '';

                $result2 = $sqlhandle->query($sql2);
                
                $overview.= '<ol>';
                while( $row2 = $result2->fetch() ) {
                    $overview.= '<li>' . htmlentities( $row2['orderNumber'] . ' - ' . $row2['company'] . ' (' . $row2['customernumber'] . ') - ' . $row2['firstname'] . ' ' . $row2['lastname'] . ' - ' . $row2['city'] ) . '</li>';
                }
                $overview.= '</ol>';

                $overview.= '</td>'
                        . '<td nowrap>'
                            . 
                            date( 'd.m.Y H:i', strtotime( $row["timestamp"] ) )
                            
                            
                        . '</td>'
                        . '<td nowrap>'
                            . '<a target="_blank" href="'
                            . (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?"https":"http")
                            . "://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}".'?'.http_build_query($link_params)
                            . '">'
                                .'Download'
                            .'</a>'
                        . '</td>'
                        .'<td nowrap>'
                            . '<a target="_blank" href="'.
                            (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?"https":"http")
                            .'://' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '?' . http_build_query( $log_params )
                            .'">Show Log'.'</a>'
                        . '</td>'
                    . '</tr>';
                $lastid = $row["id"];
                // $resultDBid = $row["lastOrderId"];
            }
            $overview.= '</table></div>';

        }
        
        $link_params = array(
            'action' => 'export' ,
            'resturl' => $_REQUEST['resturl'] ,
            'restuser' => $_REQUEST['restuser'] ,
            'restkey' => $_REQUEST['restkey']
        );

        $sqlopen = 'SELECT
                    *
                FROM cp_order_status
                LEFT JOIN s_order_billingaddress
                ON s_order_billingaddress.orderID = cp_order_status.orderid
                WHERE status = 0';

        $resultopen = $sqlhandle->query($sqlopen);

        if( $resultopen->rowCount() > 0 ) {
            $overview.= '</div><div class="row" style="background: #f0fcf0;">';
            $overview.= '<div class="col-12"></p><h4>Orders ready for export:</h4></div>';
            
            $overview.= '<div class="col-12"><ol>';
            while( $rowopen = $resultopen->fetch() ) {
                $overview.= '<li>' . str_replace( array('<','>'), array('&lt;','&gt;'), $rowopen['orderNumber'] . ' - ' . $rowopen['company'] . ' (' . $rowopen['customernumber'] . ') - ' . $rowopen['firstname'] . ' ' . $rowopen['lastname'] . ' - ' . $rowopen['city'] ) . '</li>';
            }
            $overview.= '</ol></div>';

            $overview.= '<div class="col-12"><p><a href="'.
                        (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']==='on'?"https":"http")
                        ."://{$_SERVER['HTTP_HOST']}{$_SERVER['SCRIPT_NAME']}".'?'.http_build_query($link_params)
                    .'"class="btn btn-primary mt-3">Start Export</a></p></div>';
        }


        $sqlopen = 'SELECT
                    *
                FROM cp_order_status
                LEFT JOIN s_order_billingaddress
                ON s_order_billingaddress.orderID = cp_order_status.orderid
                WHERE status = 3 ORDER BY cp_order_status.id DESC LIMIT 10';

        $resultopen = $sqlhandle->query($sqlopen);

        $overview.= '</div><div class="row">';
        $overview.= '<div class="col-12"></p><h4>Last 25 blocked Orders:</h4></div>';
        
        $overview.= '<div class="col-12" style="max-height:240px; overflow:auto;"><ol>';
        while( $rowopen = $resultopen->fetch() ) {
            $overview.= '<li>' 
                . htmlentities( $rowopen['orderNumber'] . ' - ' . $rowopen['company'] . ' (' . $rowopen['customernumber'] . ') - ' . $rowopen['firstname'] . ' ' . $rowopen['lastname'] . ' - ' . $rowopen['city'], ENT_COMPAT,'ISO-8859-1', true  )
                . '<br>' . $rowopen['comment']
            . '</li>';
        }
        $overview.= '</ol></div>';





        
    }
    
    $tplAreas['ui'] = array();
    $tplAreas['ui'][] = $ui;
    $tplAreas['ui'][] = $overview;
}
else {
    /**
     * Login Form
     */
    $tplAreas['usersidebar'][] = '<form action="#" method="post">'
                                    . '<input name="action" value="" type="hidden">'

                                        . '<!-- div class="form-group">'
                                            . '<label for="resturl">URL</label>'
                                            . '<input type="text" class="form-control" name="resturl" id="resturl" value="' . $restUrl . '" placeholder="https://.../api/">'
                                        . '</div -->'
                                        
                                        . '<div class="form-group">'
                                            . '<label for="restuser">User</label>'
                                            . '<input type="text" class="form-control" name="restuser" id="restuser" value="' . $restUser . '" placeholder="name123">'
                                        . '</div>'
                                        . '<div class="form-group">'
                                            . '<label for="restkey">API-Key</label>'
                                            . '<input type="text" class="form-control" name="restkey" id="restkey" value="' . $restKey . '" placeholder="">'
                                        . '</div>'

                                    . '<input name="Submit" value="Submit" type="submit">'
                                . '</form>';
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" integrity="sha384-Gn5384xqQ1aoWXA+058RXPxPg6fy4IWvTNh0E263XmFcJlSAwiGgFAW/dAiS6JXm" crossorigin="anonymous">
    <style>
        .listReturn {
            height: 250px;
            width: 100%;
            overflow-y: auto;
            white-space: pre;
        }

        td {
            vertical-align: top;
        }
    </style>
    <title>CPP - Order Export</title>
</head>
<body>

    <!-- Page Content -->
    <div class="container">
        <div class="row">

            <div class="usersidebar col-12 col-md-4 pt-4 order-md-1">
                <?php
                    echo implode( $tplAreas['usersidebar'] );
                ?>
            </div>

            <div class="content col-12 col-md-8 pt-4">
                <div class="row">
                    <?php
                        echo implode( $tplAreas['ui'] );
                    ?>
                </div>
            </div>

        </div>
        <div class="row">

            <div class="content col-12">
                <div class="row">
                    <?php
                        echo implode( $tplAreas['customerDetails'] );
                        echo implode( $tplAreas['iframe'] );
                        echo implode( $tplAreas['content'] );
                    ?>
                </div>
            </div>

        </div>
        <div class="row">
            <?php
                echo implode( $tplAreas['debug'] );
            ?>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.2.1.slim.min.js" integrity="sha384-KJ3o2DKtIkvYIK3UENzmM7KCkRr/rE9/Qpg6aAZGJwFDMVNA/GpGFF93hXpG5KkN" crossorigin="anonymous"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js" integrity="sha384-ApNbgh9B+Y1QKtv3Rn7W3mgPxhU9K/ScQsAP7hUibX39j7fakFPskvXusvfa0b4Q" crossorigin="anonymous"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js" integrity="sha384-JZR6Spejh4U02d8jOt6vLEHfe/JQGiRRSQQxSfFWpi1MquVdAyjUar5+76PVCmYl" crossorigin="anonymous"></script>
    <?php echo $redirect; ?>
</body>
</html>
<?php

$sqlhandle = NULL;
