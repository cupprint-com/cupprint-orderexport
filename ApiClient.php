<?php
class ApiClient
{
    const METHOD_GET = 'GET';
    const METHOD_PUT = 'PUT';
    const METHOD_POST = 'POST';
    const METHOD_DELETE = 'DELETE';

    protected $validMethods = [
        self::METHOD_GET,
        self::METHOD_PUT,
        self::METHOD_POST,
        self::METHOD_DELETE,
    ];
    protected $apiUrl;
    protected $username;
    protected $apiKey;
    protected $cURL;

    public function __construct( $apiUrl, $username, $apiKey )
    {
        $this->apiUrl = rtrim($apiUrl, '/') . '/';
        $this->username = $username;
        $this->apiKey = $apiKey;
        //Initializes the cURL instance
    }

    public function call( $url, $method = self::METHOD_GET, $data = [], $params = [] )
    {
        if (!in_array($method, $this->validMethods)) {
            throw new Exception('Invalid HTTP-Methode: ' . $method);
        }
        $queryString = '';
        if (!empty($params)) {
            $queryString = http_build_query($params);
        }
        $url = rtrim($url, '?') . '?';
        $url = $this->apiUrl . $url . $queryString;
        $dataString = json_encode($data);

        /* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - */
        $this->cURL = curl_init();

        curl_setopt( $this->cURL, CURLOPT_URL, $url );
        curl_setopt($this->cURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->cURL, CURLOPT_ENCODING, "" );
        curl_setopt($this->cURL, CURLOPT_MAXREDIRS, 10 );
        curl_setopt($this->cURL, CURLOPT_TIMEOUT, 30 );
        curl_setopt($this->cURL, CURLOPT_HTTP_VERSION, CURL_HTTP_VERSION_1_1 );
        curl_setopt( $this->cURL, CURLOPT_CUSTOMREQUEST, $method );
        curl_setopt( $this->cURL, CURLOPT_FRESH_CONNECT, TRUE );
        
        # curl_setopt($this->cURL, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($this->cURL, CURLOPT_USERAGENT, 'Shopware ApiClient');
        curl_setopt($this->cURL, CURLOPT_HTTPAUTH, CURLAUTH_DIGEST);
        curl_setopt($this->cURL, CURLOPT_USERPWD, $this->username . ':' . $this->apiKey);
        curl_setopt($this->cURL, CURLOPT_HTTPHEADER, ['Content-Type: application/json; charset=utf-8'] );

        curl_setopt( $this->cURL, CURLOPT_POSTFIELDS, $dataString );
        curl_setopt( $this->cURL, CURLOPT_FRESH_CONNECT, TRUE );

        $result = curl_exec( $this->cURL );
        $httpCode = curl_getinfo( $this->cURL, CURLINFO_HTTP_CODE );

        /* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - */
        $err = curl_error($this->cURL);
        curl_close($this->cURL);
        if($err){ 
            die('<pre>'.print_r($err,true).'</pre>');
        }
        else if( 401 == $httpCode ) {
            die( $result );
        }
        /* - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - - */

        return $result; // $this->prepareResponse($result, $httpCode);
    }

    public function get($url, $params = [])
    {
        return $this->call($url, self::METHOD_GET, [], $params);
    }

    
    public function getCustomerbyCustomerNo( $customerNo )
    {
        $params = [ 'filter' => [ [
            'property' => 'number',
            'expression' => '=',
            'value' => $customerNo
        ] ] ];

        $r = json_decode( $this->get( 'customers', $params ), true );
        
        return $r['data'][0];
    }
    
    public function getUserbyUserName( $userName )
    {
        $params = [ 'filter' => [ [
            'property' => 'username',
            'expression' => '=',
            'value' => $userName
        ] ] ];

        $r = json_decode( $this->get( 'users', $params ), true );
        
        return $r['data'][0];
    }
    
    public function getOrdersWithNumberbeginId( $lastorderid )
    {
        $params = ['filter'=>[
                        [
                            'property' => 'id' ,
                            'expression' => '>' ,
                            'value' => $lastorderid
                        ],
                        [
                            'property' => 'number' ,
                            'expression' => '!=' ,
                            'value' => 0
                        ]
                    ]
                ];

        return json_decode( $this->get( 'orders',$params ), true );
    }
    
    public function getArticlePricesbyArticleNo( $detailNumber )
    {
        $params = [ 'filter' => [ 
                                    [
                                        'property' => 'detail.number',
                                        'expression' => '=',
                                        'value' => $detailNumber
                                    ]
                                ] ];

        return json_decode( $this->get( 'CustomerPrices', $params ), true );
    }

    public function getArticlePricesbyCustomerArticleNo( $customerNo , $detailNumber ) 
    {
        $params = [ 'filter' => [
                                    [
                                        'property' => 'detail.number',
                                        'expression' => '=',
                                        'value' => $detailNumber
                                    ],
                                    [
                                        'property' => 'customer.number',
                                        'expression' => '=',
                                        'value' => $customerNo
                                    ]
                                ] ];

        return json_decode( $this->get( 'CustomerPrices', $params ), true );
    }


    public function post($url, $data = [], $params = [])
    {
        return $this->call($url, self::METHOD_POST, $data, $params);
    }

    public function put($url, $data = [], $params = [])
    {
        return $this->call($url, self::METHOD_PUT, $data, $params);
    }

    public function delete($url, $params = [])
    {
        return $this->call($url, self::METHOD_DELETE, [], $params);
    }

    protected function prepareResponse($result, $httpCode)
    {
        echo "<h2>HTTP: $httpCode</h2>";
        if (null === $decodedResult = json_decode($result, true)) {
            $jsonErrors = [
                JSON_ERROR_NONE => 'No error occurred',
                JSON_ERROR_DEPTH => 'The maximum stack depth has been reached',
                JSON_ERROR_CTRL_CHAR => 'Control character issue, maybe wrong encoded',
                JSON_ERROR_SYNTAX => 'Syntaxerror',
            ];
            echo '<h2>Could not decode json</h2>';
            echo 'json_last_error: ' . $jsonErrors[json_last_error()];
            echo '<br>Raw:<br>';
            echo '<pre>' . print_r($result, true) . '</pre>';

            return;
        }
        if (!isset($decodedResult['success'])) {
            echo 'Invalid Response';

            return;
        }
        if (!$decodedResult['success']) {
            echo '<h2>No Success</h2>';
            echo '<p>' . $decodedResult['message'] . '</p>';
            if (array_key_exists('errors', $decodedResult) && is_array($decodedResult['errors'])) {
                echo '<p>' . join('</p><p>', $decodedResult['errors']) . '</p>';
            }

            return;
        }
        echo '<h2>Success</h2>';
        if (isset($decodedResult['data'])) {
            echo '<pre>' . print_r($decodedResult['data'], true) . '</pre>';
        }

        return $decodedResult;
    }

    public function getLink( $link , $action )
    {
        
        if( strpos( $link , '?' ) >= 0 ) {
            $link.= '&';
        }
        else {
            $link.= '?';
        }

        $link .= 'action=' . $action
            . '&resturl=' . urlencode( $this->apiUrl ) . '&restuser=' . urlencode( $this->username ) . '&restkey=' . urlencode( $this->apiKey ) ;
        
        return $link;
    }
}