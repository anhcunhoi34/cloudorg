<?php
/*
 * @ PHP 5.6
 * @ Decoder version : 1.0.0.1
 * @ Release on : 31.05.2024
 * @ Website    : https://cloudnest.vn
 */

use Illuminate\Database\Capsule\Manager as Capsule;

    if (!defined("WHMCS")) {
        exit("This file cannot be accessed directly");
    }

    cloudnest_upgrade();

    // cung cấp thông tin
    function cloudnest_MetaData()
    {
        return [
            "DisplayName" => "Cloudnest", 
            "APIVersion" => "1.0", 
        ];
    }

    // các fields trong dịch vụ/sản phẩm
    function cloudnest_ConfigOptions(array $params)
    {
        // $logFile = 'logs/cloudnest.log';
        // file_put_contents($logFile, json_encode($params) . PHP_EOL, FILE_APPEND);

        $namePackage = cloudnest_MetaData()['DisplayName'] . 'Package Name';
        return array(
            $namePackage => array("Type" => "text", "Size" => "25", "Loader" => "cloudnest_ListPackages", "SimpleMode" => true), 
        );
    }

    // Thêm hàm testConnection vào module cPanel
    function cloudnest_testConnection($params) {
         $logFile = 'logs/cloudnest.log';
        // file_put_contents($logFile, json_encode($params) . PHP_EOL, FILE_APPEND);
        if ( !empty($params['serverusername']) && !empty($params['serverpassword']) && !empty($params['serveraccesshash']) ) {
            try {
                $getToken = connectCloudnest($params, 'POST', 'get-token');
                if ($getToken->error) {
                    return array("error" => 'Lỗi kết nối');
                }
                else {
                    if (!empty($params['serverid'])) {
                        $cloudzoneserverauth = Capsule::table('cloudzoneserverauth')->where("serverid", $params['serverid'])->first();
                        if ( isset($cloudzoneserverauth) ) {
                            Capsule::table('cloudzoneserverauth')
                                ->where('id', $cloudzoneserverauth->id)
                                ->update([ 
                                    'auth_token' => $getToken->{'auth-token'},
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]);
                        }
                        else {
                            Capsule::table('cloudzoneserverauth')->insert([
                                'serverid' => $params['serverid'],
                                'auth_token' => $getToken->{'auth-token'},
                                'created_at' => date('Y-m-d H:i:s'),
                                'updated_at' => date('Y-m-d H:i:s')
                            ]);
                        }
                        return array("success" => true);
                    }
                    else
                    {
                        $server = Capsule::table('tblservers')
                            ->where("username", $params['serverusername'])
                            ->where("accesshash", $params['serveraccesshash'])
                            ->first();
                        if ( isset($server) ) {
                            $cloudzoneserverauth = Capsule::table('cloudzoneserverauth')->where("serverid", $server->id)->first();
                            if ( isset($cloudzoneserverauth) ) {
                                Capsule::table('cloudzoneserverauth')
                                    ->where('id', $cloudzoneserverauth->id)
                                    ->update([ 
                                        'auth_token' => $getToken->{'auth-token'},
                                        'updated_at' => date('Y-m-d H:i:s')
                                    ]);
                            }
                            else {
                                Capsule::table('cloudzoneserverauth')->insert([
                                    'serverid' => $server->id,
                                    'auth_token' => $getToken->{'auth-token'},
                                    'created_at' => date('Y-m-d H:i:s'),
                                    'updated_at' => date('Y-m-d H:i:s')
                                ]);
                            }
                            return array("success" => true);
                        }
                        else {
                            return array("error" => 'Server không tồn tại (Quý khách vui lòng Lưu lại cấu hình hoặc kiểm tra lại thông tin nhập vào');
                        }
                    }
                }
            } catch (\Throwable $th) {
                //throw $th;
                file_put_contents($logFile, $th . PHP_EOL, FILE_APPEND);
                return array("error" => 'Lỗi xử lý DB');
            }
        }

        return array("error" => 'Thông tin kết nối đến hệ thống không tồn tại');
    }

    function cloudnest_getAuthToken($serverid) {
        return Capsule::table('cloudzoneserverauth')->where("serverid", $serverid)->first();
    }

    function cloudnest_ListPackages() {
        $logFile = 'logs/cloudnest.log';

        $server = Capsule::table('tblservers')->where("type", 'cloudnest')->first();
        if (isset($server)) {
            $cloudzoneserverauth = cloudnest_getAuthToken($server->id);
            
            $params = [
                'serverusername' => $server->username,
                'serverpassword' => decrypt($server->password),
                'serveraccesshash' => $server->accesshash,
                'servertoken' => $cloudzoneserverauth->auth_token
            ];

            $getProduct = connectCloudnest($params, 'GET', 'get-product');
            if ($getProduct->error) {
                throw new WHMCS\Exception\Module\NotServicable($getProduct->message);
            }

            $groupProducts = $getProduct->products->vps;
            $dataProduct = [];
            // file_put_contents($logFile, json_encode($dataProduct) . PHP_EOL, FILE_APPEND);
            foreach ($groupProducts as $groupProduct) {
                foreach ($groupProduct->product as $product) {
                    $dataProduct[$product->product_id] = $product->name;
                }
            }

            // file_put_contents($logFile, json_encode($dataProduct) . PHP_EOL, FILE_APPEND);
            return $dataProduct;
        }
        
        throw new WHMCS\Exception\Module\NotServicable('Chưa thiết lập kết nối đến ' . cloudnest_MetaData()['DisplayName']);
    }

    function cloudnest_CreateAccount($params)
    {
        $logFile = 'logs/cloudnest.log';
        file_put_contents($logFile, date('H:i:s d-m-Y') . ': ' . 'Tạo VPS: ' . PHP_EOL, FILE_APPEND);
        file_put_contents($logFile, json_encode($params) . PHP_EOL, FILE_APPEND);

        $server = Capsule::table('tblservers')->where("id", $params['serverid'])->first();

        if (!isset($server)) {
            return 'Chưa thiết lập kết nối đến Hệ thống';
        }

        $product = Capsule::table('tblproducts')->where("id", $params['packageid'])->first();
        if (!isset($product)) {
            return 'Sản phẩm không tồn tại';
        }
        else if ( empty($product->configoption1) ) {
            return 'Sản phẩm chưa kết nối đến template của Hệ thống';
        }
        $model = $params['model'];
        if (empty($model['billingcycle'])) {
            return 'Thông tin thời gian thuê không tồn tại';
        }

        $service = Capsule::table('tblhosting')->where("id", $params['serviceid'])->first();
        $order = Capsule::table('tblorders')->where("id", $params['orderid'])->first();

        Capsule::table('tblorders')
            ->whereId($order->id)
            ->update([
                'status' => 'Active',
                'date' => date('Y-m-d H:i:s')
            ]);

        Capsule::table('tblinvoices')
            ->whereId($order->invoiceid)
            ->update([
                'status' => 'paid',
            ]);

        Capsule::table('tblhosting')
            ->whereId($service->id)
            ->update([
                'domainstatus' =>  'Active',
                'status_vps' =>  'waiting',
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        

        $dataBody = [
            "product-id" => $product->configoption1,
            "billing-cycle" => strtolower($model['billingcycle']),
            "quantity" => 1,
            "addon-cpu" => 0,
            "addon-ram" => 0,
            "addon-disk" => 0,
            "os" => "",
        ];

        $configoptions = $params['configoptions'];
        if (isset($configoptions)) {
            foreach ($configoptions as $key => $configoption) {
                if (strpos(strtolower($key), 'cpu') !== false) {
                    if ( is_numeric($configoption) ) {
                        $dataBody['addon-cpu'] = (int) $configoption;
                    }
                }
                elseif (strpos(strtolower($key), 'ram') !== false) {
                    $dataBody['addon-ram'] = 0;
                    if ( is_numeric($configoption) ) {
                        $dataBody['addon-ram'] = (int) $configoption;
                    }
                }
                elseif (strpos(strtolower($key), 'disk') !== false) {
                    $dataBody['addon-disk'] = 0;
                    if ( is_numeric($configoption) ) {
                        $dataBody['addon-disk'] = (int) $configoption * 10;
                    }
                }
                elseif (strpos(strtolower($key), 'os') !== false) {
                    $dataBody['os'] = $configoption;
                }
                elseif (strpos(strtolower($key), 'hệ điều hành') !== false) {
                    $dataBody['os'] = $configoption;
                }
            }
        }
        file_put_contents($logFile, date('H:i:s d-m-Y') . ': ' . 'Data: ' . PHP_EOL, FILE_APPEND);
        file_put_contents($logFile, json_encode($dataBody) . PHP_EOL, FILE_APPEND);
        
        $cloudzoneserverauth = cloudnest_getAuthToken($server->id);
            
        $auth = [
            'serverusername' => $server->username,
            'serverpassword' => decrypt($server->password),
            'serveraccesshash' => $server->accesshash,
            'servertoken' => $cloudzoneserverauth->auth_token
        ];
        file_put_contents($logFile, date('H:i:s d-m-Y') . ': ' . 'Auth: ' . PHP_EOL, FILE_APPEND);
        file_put_contents($logFile, json_encode($auth) . PHP_EOL, FILE_APPEND);

        $response = connectCloudnest($auth, 'POST', 'order/create-order-vps-by-whmcs', $dataBody);

        file_put_contents($logFile, date('H:i:s d-m-Y') . ': ' . 'Trả về: ' . PHP_EOL, FILE_APPEND);
        file_put_contents($logFile, json_encode($response) . PHP_EOL, FILE_APPEND);
        if (!empty($response)) {
            if ( !empty($response->error) ) {
                return $response->message;
            }
    
            foreach ($response->data as $key => $dataResponse)
            {
                $dataVps = [
                    'vm_id' =>  $dataResponse->{"vps-id"},
                    'nextduedate' =>  $dataResponse->{"next_due_date"},
                    'domainstatus' =>  'Completed',
                    'status_vps' =>  $dataResponse->{"vps-status"},
                    'domain' =>  $dataResponse->{"ip"},
                    'username' =>  $dataResponse->{"username"},
                    'password' =>  $dataResponse->{"password"},
                    'updated_at' => date('Y-m-d H:i:s'),
                    'vps_os' => $dataResponse->{"vps-os"},
                ];
    
                Capsule::table('tblhosting')
                    ->whereId($service->id)
                    ->update($dataVps);
            }
            
            return 'success';
        }
        
        return 'Đặt hàng đến hệ thống cloudnest thất bại';
    }

    // CONNECT
    function connectCloudnest($params, $method, $url, $dataBody = []) {
        $logFile = 'logs/cloudnest.log';
        $urlConnect = 'https://client.cloudnest.vn/api/agency/';
        try {
            $curl = curl_init();

            if ( $url == 'get-token' ) {     
                $authen = [
                    "api-username" => !empty($params['serverusername']) ? $params['serverusername'] : '',
                    "api-app" => !empty($params['serverpassword']) ? $params['serverpassword'] : '',
                    "api-secret" => !empty($params['serveraccesshash']) ? $params['serveraccesshash'] : '',
                    "auth-token" => !empty($params['servertoken']) ? $params['servertoken'] : '',
                ];
                // file_put_contents($logFile, json_encode($authen) . PHP_EOL, FILE_APPEND);
                $urlcloudnest = $urlConnect . $url; 
       
                curl_setopt_array($curl, array(
                    CURLOPT_URL => $urlcloudnest,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_POSTFIELDS => json_encode($authen),
                    CURLOPT_HTTPHEADER => array(
                        'Content-Type: application/json'
                    ),
                ));
            }
            else {
                $urlcloudnest = $urlConnect . $url; 

                $contenType = 'Content-Type: application/json';
                $apiName = 'api-username: '; 
                $apiName .= !empty($params['serverusername']) ? $params['serverusername'] : '';
                $apiApp = 'api-app: '; 
                $apiApp .= !empty($params['serverpassword']) ? $params['serverpassword'] : '';
                $apiSecret = 'api-secret: '; 
                $apiSecret .= !empty($params['serveraccesshash']) ? $params['serveraccesshash'] : '';
                $apiToken = 'auth-token: '; 
                $apiToken .= !empty($params['servertoken']) ? $params['servertoken'] : '';
                // file_put_contents($logFile, json_encode([
                //     $contenType, $apiName, $apiApp, $apiSecret, $apiToken 
                // ]) . PHP_EOL, FILE_APPEND);
                // file_put_contents($logFile, $urlcloudnest . PHP_EOL, FILE_APPEND);

                curl_setopt_array($curl, array(
                    CURLOPT_URL => $urlcloudnest,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_ENCODING => '',
                    CURLOPT_MAXREDIRS => 10,
                    CURLOPT_TIMEOUT => 300,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                    CURLOPT_CUSTOMREQUEST => $method,
                    CURLOPT_POSTFIELDS => json_encode($dataBody),
                    CURLOPT_HTTPHEADER => [
                        $contenType, $apiName, $apiApp, $apiSecret, $apiToken 
                    ],
                ));
            }

    
            $response = curl_exec($curl);
            curl_close($curl);
            
            // file_put_contents($logFile, json_encode($response) . PHP_EOL, FILE_APPEND);
            return json_decode($response);

        } 
        catch (\Throwable $th) {
            file_put_contents($logFile, $th . PHP_EOL, FILE_APPEND);

            return array("error" => "1", "details" => "Lỗi kết nối");
        }
    }

    function cloudnest_upgrade() {
        // Tạo đối tượng Database
        $pdo = Capsule::connection()->getPdo();
    
        // Tạo truy vấn SQL để lấy thông tin về cột vm_id trong bảng tblhosting
        $query = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tblhosting' AND COLUMN_NAME = 'vm_id'";

        // Thực hiện truy vấn SQL
        $result = full_query($query);

        // Đếm số dòng kết quả trả về
        $numRows = mysql_num_rows($result);
        if (!$numRows) {
            $alterQuery = "ALTER TABLE tblhosting ADD COLUMN vm_id BIGINT";
            $alterResult = full_query($alterQuery);
        }

        // Tạo truy vấn SQL để lấy thông tin về cột vm_id trong bảng tblhosting
        $query2 = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tblhosting' AND COLUMN_NAME = 'status_vps'";
        // Thực hiện truy vấn SQL
        $result2 = full_query($query2);
        // Đếm số dòng kết quả trả về
        $numRows2 = mysql_num_rows($result2);
        if (!$numRows2) {
            $alterQuery = "ALTER TABLE tblhosting ADD COLUMN status_vps VARCHAR(255)";
            $alterResult = full_query($alterQuery);
        }

        // Tạo truy vấn SQL để lấy thông tin về cột os trong bảng tblhosting
        $query3 = "SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = 'tblhosting' AND COLUMN_NAME = 'vps_os'";
        // Thực hiện truy vấn SQL
        $result3 = full_query($query3);
        // Đếm số dòng kết quả trả về
        $numRows3 = mysql_num_rows($result3);
        if (!$numRows3) {
            $alterQuery = "ALTER TABLE tblhosting ADD COLUMN vps_os VARCHAR(255)";
            $alterResult = full_query($alterQuery);
        }

        // Kiểm tra xem bảng có tồn tại hay không
        if (!Capsule::schema()->hasTable('cloudzoneserverauth')) {
            // Tạo bảng nếu không tồn tại
            Capsule::schema()->create('cloudzoneserverauth', function ($table) {
                $table->increments('id');
                $table->integer('serverid');
                $table->text('auth_token');
                $table->timestamps();
            });
        }
    }

    function cloudnest_clientarea($vars) {
        $action = !empty( $vars['templatevars']['action'] ) ? $vars['templatevars']['action'] : '';
        if ($_SERVER["REQUEST_METHOD"] == "POST") {
            $action_form = !empty($_POST['a']) ? $_POST['a'] : '';
            $modop = !empty($_POST['modop']) ? $_POST['modop'] : '';
            $module_addon = !empty($_POST['module_addon']) ? $_POST['module_addon'] : '';
            if ($modop == 'custom' && $module_addon == 'cloudnest') {
                $vpsId = !empty($_POST['id']) ? $_POST['id'] : 0;
                switch ($action_form) {
                    case 'off':
                        $actionVps = cloudnest_off($vpsId, $vars['userid']);
                        header("Location: clientarea.php?action=productdetails&id=" . $vpsId . "&notification=" . urlencode($actionVps['message'])  . "&error=" . $actionVps['error']);
                        exit;
                    case 'on':
                        $actionVps = cloudnest_on($vpsId, $vars['userid']);
                        header("Location: clientarea.php?action=productdetails&id=" . $vpsId . "&notification=" . urlencode($actionVps['message'])  . "&error=" . $actionVps['error']);
                        exit;
                    case 'restart':
                        $actionVps = cloudnest_restart($vpsId, $vars['userid']);
                        header("Location: clientarea.php?action=productdetails&id=" . $vpsId . "&notification=" . urlencode($actionVps['message'])  . "&error=" . $actionVps['error']);
                        exit;
                    case 'cancel':
                        $actionVps = cloudnest_cancel($vpsId, $vars['userid']);
                        header("Location: clientarea.php?action=productdetails&id=" . $vpsId . "&notification=" . urlencode($actionVps['message'])  . "&error=" . $actionVps['error']);
                        exit;
                    case 'check-os':
                        header('Content-Type: application/json');
                        echo json_encode( cloudnest_rebuild($vpsId, $vars['userid']) );
                        exit;
                    case 'comfirm-rebuild':
                        $os = !empty($_POST['os']) ? $_POST['os'] : 0;
                        $osName = !empty($_POST['osName']) ? $_POST['osName'] : 0;
                        $oldOs = !empty($_POST['oldOs']) ? $_POST['oldOs'] : 0;
                        header('Content-Type: application/json');
                        echo json_encode( cloudnest_confirm_rebuild($vpsId, $vars['userid'], $os, $osName, $oldOs) );
                        exit;
                    default:
                        $text = "Hành động không tồn tại";
                        header("Location: clientarea.php?action=productdetails&id=" . $vpsId . "&notification=" . urlencode($text)  . "&error=1");
                        exit;
                }
            }
        }

        if ( $action == 'productdetails' ) {
            $serviceId = !empty( $vars['templatevars']['serviceid'] ) ? $vars['templatevars']['serviceid'] : 0;
            // $os = cloudnest_getOsService($vars['templatevars']['configurableoptions']);

            $service = Capsule::table('tblhosting')
            ->whereId($serviceId)
            ->first();

            return array(
                'templatefile' => 'clientarea',
                'vars' => array(
                    'status_vps' => $service->status_vps,
                    'service_ip' => $service->domain,
                    'service_username' => $service->username,
                    'service_password' => $service->password,
                    'notification' => !empty($_GET['notification']) ? $_GET['notification'] : '',
                    'error' => !empty($_GET['error']) ? $_GET['error'] : 0,
                    'time' => time(),
                    'old_os' =>  $service->vps_os,
                ),
            );
            
        }
        
    }

    // echo "<pre>";
    // print_r($serviceId);
    // print_r($userId);
    // echo "</pre>";
    // exit();

    // OFF VPS
    function cloudnest_off($serviceId, $userId)
    {
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->where('userid', $userId)
            ->first();
        
        if (isset($service)) {
            if ( $service->status_vps != 'on' ) {
                return [
                    'error' => 1,
                    'message' =>  'Trạng thái VPS không phù hợp'
                ];
            }
            $server = Capsule::table('tblservers')->where("id", $service->server)->first();
            $cloudzoneserverauth = cloudnest_getAuthToken($server->id);
            
            $auth = [
                'serverusername' => $server->username,
                'serverpassword' => decrypt($server->password),
                'serveraccesshash' => $server->accesshash,
                'servertoken' => $cloudzoneserverauth->auth_token
            ];
            $dataBody = [
                "action" => 'off',
                "vps-id" => $service->vm_id,
            ];

            $response = connectCloudnest($auth, 'POST', 'vps/action-vps', $dataBody);

            if ( !empty($response->error) ) {
                return [
                    'error' => 1,
                    'message' =>  $response->message
                ];
            }
            
            
            Capsule::table('tblhosting')
            ->whereId($service->id)
            ->update([
                'status_vps' => 'off',
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            logModuleCall("cloudnest", 'off', $dataBody, $response, "", []);

            return [
                'error' => 0,
                'message' => 'Tắt VPS thành công'
            ];
        }
        return [
            'error' => 1,
            'message' => 'Dịch vụ VPS không tồn tại'
        ];
    }

    // ON VPS
    function cloudnest_on($serviceId, $userId)
    {
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->where('userid', $userId)
            ->first();
        
        if (isset($service)) {
            if ( $service->status_vps != 'off' ) {
                return [
                    'error' => 1,
                    'message' =>  'Trạng thái VPS không phù hợp'
                ];
            }
            $server = Capsule::table('tblservers')->where("id", $service->server)->first();
            $cloudzoneserverauth = cloudnest_getAuthToken($server->id);
            
            $auth = [
                'serverusername' => $server->username,
                'serverpassword' => decrypt($server->password),
                'serveraccesshash' => $server->accesshash,
                'servertoken' => $cloudzoneserverauth->auth_token
            ];
            $dataBody = [
                "action" => 'on',
                "vps-id" => $service->vm_id,
            ];

            $response = connectCloudnest($auth, 'POST', 'vps/action-vps', $dataBody);

            if ( !empty($response->error) ) {
                return [
                    'error' => 1,
                    'message' =>  $response->message
                ];
            }
            
            
            Capsule::table('tblhosting')
            ->whereId($service->id)
            ->update([
                'status_vps' => 'on',
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            logModuleCall("cloudnest", 'on', $dataBody, $response, "", []);

            return [
                'error' => 1,
                'message' =>  'Bật VPS thành công'
            ];
        }
        return [
            'error' => 1,
            'message' => 'Dịch vụ VPS không tồn tại'
        ];
    }

    // RESTART VPS
    function cloudnest_restart($serviceId, $userId)
    {
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->where('userid', $userId)
            ->first();
        
        if (isset($service)) {
            if ( $service->status_vps != 'off' && $service->status_vps != 'on' ) {
                return [
                    'error' => 1,
                    'message' =>  'Trạng thái VPS không phù hợp'
                ];
            }
            $server = Capsule::table('tblservers')->where("id", $service->server)->first();
            $cloudzoneserverauth = cloudnest_getAuthToken($server->id);
            
            $auth = [
                'serverusername' => $server->username,
                'serverpassword' => decrypt($server->password),
                'serveraccesshash' => $server->accesshash,
                'servertoken' => $cloudzoneserverauth->auth_token
            ];
            $dataBody = [
                "action" => 'restart',
                "vps-id" => $service->vm_id,
            ];

            $response = connectCloudnest($auth, 'POST', 'vps/action-vps', $dataBody);

            if ( !empty($response->error) ) {
                return [
                    'error' => 1,
                    'message' =>  $response->message
                ];
            }
            
            
            Capsule::table('tblhosting')
            ->whereId($service->id)
            ->update([
                'status_vps' => 'on',
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            logModuleCall("cloudnest", 'restart', $dataBody, $response, "", []);

            return [
                'error' => 0,
                'message' =>  'Khởi động lại VPS thành công'
            ];
        }
        return [
            'error' => 1,
            'message' => 'Dịch vụ VPS không tồn tại'
        ];
    }

    // CANCEL VPS
    function cloudnest_cancel($serviceId, $userId)
    {
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->where('userid', $userId)
            ->first();
        
        if (isset($service)) {
            if ( $service->status_vps == 'cancel' || $service->status_vps == 'change_user' || $service->status_vps == 'delete_vps' ) {
                return [
                    'error' => 1,
                    'message' =>  'Trạng thái VPS không phù hợp'
                ];
            }
            $server = Capsule::table('tblservers')->where("id", $service->server)->first();
            $cloudzoneserverauth = cloudnest_getAuthToken($server->id);
            
            $auth = [
                'serverusername' => $server->username,
                'serverpassword' => decrypt($server->password),
                'serveraccesshash' => $server->accesshash,
                'servertoken' => $cloudzoneserverauth->auth_token
            ];
            $dataBody = [
                "action" => 'cancel',
                "vps-id" => $service->vm_id,
            ];

            $response = connectCloudnest($auth, 'POST', 'vps/action-vps', $dataBody);

            if ( !empty($response->error) ) {
                return [
                    'error' => 1,
                    'message' =>  $response->message
                ];
            }
            
            
            Capsule::table('tblhosting')
            ->whereId($service->id)
            ->update([
                'domainstatus' => 'Cancelled', 
                'status_vps' => 'cancel',
                'updated_at' => date('Y-m-d H:i:s')
            ]);

            logModuleCall("cloudnest", 'cancel', $dataBody, $response, "", []);

            return [
                'error' => 0,
                'message' =>  'Huỷ dịch vụ VPS thành công'
            ];
        }
        return [
            'error' => 1,
            'message' => 'Dịch vụ VPS không tồn tại'
        ];
    }

    // CHECK OS WHEN REBUILD VPS
    function cloudnest_rebuild($serviceId, $userId)
    {
        $service = Capsule::table('tblhosting')
            ->where('id', $serviceId)
            ->where('userid', $userId)
            ->first();
        
        if (isset($service)) {
            if ( $service->status_vps != 'on' && $service->status_vps != 'off' ) {
                return [
                    'error' => 1,
                    'message' =>  'Trạng thái VPS không phù hợp'
                ];
            }
            $server = Capsule::table('tblservers')->where("id", $service->server)->first();
            $cloudzoneserverauth = cloudnest_getAuthToken($server->id);
            
            $auth = [
                'serverusername' => $server->username,
                'serverpassword' => decrypt($server->password),
                'serveraccesshash' => $server->accesshash,
                'servertoken' => $cloudzoneserverauth->auth_token
            ];
            $dataBody = [
                "action" => 'check-os-when-rebuild-vps',
                "vps-id" => $service->vm_id,
            ];

            return connectCloudnest($auth, 'POST', 'vps/action-vps', $dataBody);
        }
        return [
            'error' => 1,
            'message' => 'Dịch vụ VPS không tồn tại'
        ];
    }

    // CONFIRM REBUILD VPS
    function cloudnest_confirm_rebuild($serviceId, $userId, $os, $osName, $oldOs)
    {
        $service = Capsule::table('tblhosting')
        ->where('id', $serviceId)
        ->where('userid', $userId)
        ->first();
    
        if (isset($service)) {
            if ( $service->status_vps != 'on' && $service->status_vps != 'off' ) {
                return [
                    'error' => 1,
                    'message' =>  'Trạng thái VPS không phù hợp'
                ];
            }
            $server = Capsule::table('tblservers')->where("id", $service->server)->first();
            $cloudzoneserverauth = cloudnest_getAuthToken($server->id);
            
            $auth = [
                'serverusername' => $server->username,
                'serverpassword' => decrypt($server->password),
                'serveraccesshash' => $server->accesshash,
                'servertoken' => $cloudzoneserverauth->auth_token
            ];
            $dataBody = [
                "action" => 'confirm-rebuild-vps',
                "vps-id" => $service->vm_id,
                'os-id' => $os
            ];
            logModuleCall("cloudnest", 'rebuild', $dataBody, $os, $osName, []);

            $response = connectCloudnest($auth, 'POST', 'vps/action-vps', $dataBody);
            if (!empty($response)) {
                if ($response->error == 0) {
                    Capsule::table('tblhosting')
                    ->whereId($service->id)
                    ->update([
                        'status_vps' => 'rebuild',
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);
                }
                
                return $response;
            }
            return [
                'error' => 1,
                'message' => 'Kết nối lỗi'
            ];
        }
        return [
            'error' => 1,
            'message' => 'Dịch vụ VPS không tồn tại'
        ];
        // try {
        // } 
        // catch (\Throwable $th) {
        //     //throw $th;
        //     logModuleCall("cloudnest", 'rebuild', '', $th, "", []);
        //     return [
        //         'error' => 1,
        //         'message' => 'Kết nối đến hệ thống lỗi,'
        //     ];
        // }
    }

    function cloudnest_getOsService($configurableoptions) {
        if (count($configurableoptions)) {
            foreach ($configurableoptions as $configurableoption) {
                if (strpos(strtolower($configurableoption['optionname']), 'os') !== false) {
                    return $configurableoption['selectedname'];
                }
                elseif (strpos(strtolower($configurableoption['optionname']), 'hệ điều hành') !== false) {
                    return $configurableoption['selectedname'];
                }
            }
        }
        return '';
    }

    function cloudnest_getIdByProductConfigoption($packageid, $osName) {
        $productConfigLinks = Capsule::table('tblproductconfiglinks')->where('pid', $packageid)->get();

        if ( count($productConfigLinks) ) {
            foreach ($productConfigLinks as $productConfigLink) {
                $productConfigOptions = Capsule::table('tblproductconfigoptions')->where('gid', $productConfigLink->gid)->get();
                if ( count($productConfigOptions) ) {
                    foreach ($productConfigOptions as  $productConfigOption) {
                        $productConfigOptionsSubs = Capsule::table('tblproductconfigoptionssub')->where('configid', $productConfigOption->id)->get();
                        foreach ($productConfigOptionsSubs as $productConfigOptionsSub) {
                            if ( $productConfigOptionsSub->optionname == $osName ) {
                                return $productConfigOptionsSub->id;
                            }
                        }
                    }
                }                
            }
        }

        return 0;
    }

    function cloudnest_AdminCustomButtonArray()
    {
        return array(
            "Cập nhật VPS" => 'upgradeStatusVps',
            "Bật" => 'onVps',
            "Tắt" => 'offVps',
            "Khởi động lại" => 'restartVps',
            "Gia hạn" => 'renewVps',
        );
        
    }

    function cloudnest_onVps($params) {
        $action = cloudnest_on($params['serviceid'], $params['userid']);
        if ($action['error']) {
            return $action['message'];
        }
        return 'success';
    }

    function cloudnest_offVps($params) {
        $action = cloudnest_off($params['serviceid'], $params['userid']);
        if ($action['error']) {
            return $action['message'];
        }
        return 'success';
    }

    function cloudnest_restartVps($params) {
        $action = cloudnest_restart($params['serviceid'], $params['userid']);
        if ($action['error']) {
            return $action['message'];
        }
        return 'success';
    }

    function cloudnest_upgradeStatusVps($params) {
        $logFile = 'logs/cloudnest.log';
        $service = Capsule::table('tblhosting')
        ->where('id', $params['serviceid'])
        ->first();
        
        if (isset($service)) {
            $server = Capsule::table('tblservers')->where("id", $service->server)->first();
            $cloudzoneserverauth = cloudnest_getAuthToken($server->id);
            
            $auth = [
                'serverusername' => $server->username,
                'serverpassword' => decrypt($server->password),
                'serveraccesshash' => $server->accesshash,
                'servertoken' => $cloudzoneserverauth->auth_token
            ];
            $dataBody = [
                "vps-id" => $service->vm_id,
            ];

            $response = connectCloudnest($auth, 'GET', 'vps/get-info-vps', $dataBody);
            if ( !empty($response) ) {
                if ( $response->error == 0 ) {
                    $dataResponse = $response->data;
                    // file_put_contents($logFile, getType($response) . PHP_EOL, FILE_APPEND);
                    file_put_contents($logFile, json_encode($dataResponse) . PHP_EOL, FILE_APPEND);

                    $dataUpdate = [
                        'vm_id' =>  $dataResponse[0]->{"vps-id"},
                        'nextduedate' =>  $dataResponse[0]->{"next_due_date"},
                        'status_vps' =>  $dataResponse[0]->{"vps-status"},
                        'domain' =>  $dataResponse[0]->{"ip"},
                        'username' =>  $dataResponse[0]->{"username"},
                        'password' =>  $dataResponse[0]->{"password"},
                        'updated_at' => date('Y-m-d H:i:s'),
                        'vps_os' =>  $dataResponse->{"os"},
                    ];
                    logModuleCall("cloudnest", 'upgradeVPS', $dataBody, $dataResponse, "", []);
    
                    $service = Capsule::table('tblhosting')
                        ->where('id', $params['serviceid'])
                        ->update( $dataUpdate );
                    
                    return 'success';
                }
                
                return $response->message;
            }
            return 'Lỗi truy vấn đến hệ thống';
        }

        return 'Dịch vụ VPS không tồn tại';
    }

    function cloudnest_renewVps($params) {

        $logFile = 'logs/cloudnest.log';
        $service = Capsule::table('tblhosting')
            ->where('id', $params['serviceid'])
            ->first();

        if (isset($service)) {
            $server = Capsule::table('tblservers')->where("id", $service->server)->first();
            $cloudzoneserverauth = cloudnest_getAuthToken($server->id);
            
            $auth = [
                'serverusername' => $server->username,
                'serverpassword' => decrypt($server->password),
                'serveraccesshash' => $server->accesshash,
                'servertoken' => $cloudzoneserverauth->auth_token
            ];
            $dataBody = [
                "action" => 'renew-vps',
                "vps-id" => $service->vm_id,
                "billing-cycle" => strtolower($service->billingcycle),
            ];

            $response = connectCloudnest($auth, 'POST', 'vps/action-vps', $dataBody);
            if ( !empty($response) ) {
                if ( $response->error == 0 ) {
                    file_put_contents($logFile, json_encode($response) . PHP_EOL, FILE_APPEND);

                    $dataUpdate = [
                        'nextduedate' =>  $response->{"next_due_date"},
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    if ( $server->status_vps == 'expire' ) {
                        $dataUpdate['status_vps'] = 'on';
                    }
                    logModuleCall("cloudnest", 'renewVPS', $dataBody, $response, "", []);
    
                    $service = Capsule::table('tblhosting')
                        ->where('id', $params['serviceid'])
                        ->update( $dataUpdate );
                    
                    return 'success';
                }
                
                return $response->message;
            }
            return 'Lỗi truy vấn đến hệ thống';
        }

        return 'Dịch vụ VPS không tồn tại';
    }

?>